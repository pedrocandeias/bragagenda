<?php
class TheatroCircoScraper extends BaseScraper {

    public function scrape() {
        $eventsScraped = 0;
        $errors = [];

        try {
            $html = $this->fetchUrl('https://theatrocirco.com/programa/');
            if (!$html) {
                return ['error' => 'Failed to fetch /programa/'];
            }

            if (!preg_match('/TC_EVENTS\s*=\s*(\[.*?\]);\s*\n/s', $html, $m)) {
                return ['error' => 'TC_EVENTS array not found on page'];
            }

            $tcEvents = json_decode($m[1], true);
            if (!$tcEvents) {
                return ['error' => 'Failed to decode TC_EVENTS JSON'];
            }

            $cutoff = strtotime('-7 days');

            foreach ($tcEvents as $ev) {
                // Skip archived (past) events — only process current programme
                if (($ev['category'] ?? '') !== 'Agenda') continue;

                try {
                    $title    = $this->buildTitle($ev);
                    $dateInfo = $this->parseDateString($ev['date'] ?? '');

                    if (!$title || !$dateInfo) continue;

                    // Skip events more than 7 days in the past
                    if (strtotime($dateInfo['start']) < $cutoff) continue;

                    $imageUrl = ($ev['image'] && $ev['image'] !== 'false')
                        ? $this->downloadImage($ev['image'])
                        : null;

                    $category = $this->normalizeCategory($ev['category_name'] ?? '');
                    $url      = $ev['link'] ?? null;

                    if ($this->saveEvent(
                        $title,
                        null,
                        $dateInfo['start'],
                        $category,
                        $imageUrl,
                        $url,
                        'Theatro Circo',
                        $dateInfo['start'],
                        $dateInfo['end']
                    )) {
                        $eventsScraped++;
                    }

                } catch (Exception $e) {
                    $errors[] = 'Error processing event: ' . $e->getMessage();
                }
            }

            $this->updateLastScraped();

            return ['events_scraped' => $eventsScraped, 'errors' => $errors];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------

    private function buildTitle(array $ev): ?string {
        $main = html_entity_decode(trim($ev['line_one_text'] ?? $ev['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sub  = html_entity_decode(trim($ev['line_two_text'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (!$main) return null;
        return $sub ? "$main — $sub" : $main;
    }

    /**
     * Parse TC date strings into ['start' => 'Y-m-d H:i:s', 'end' => 'Y-m-d H:i:s'].
     *
     * Formats seen:
     *   "24 abril (sex)"
     *   "1 e 2 abril (qua e qui)"
     *   "17 a 24 abril"
     *   "7 a 9 abril (ter a qui)"
     *   "12 a 14 março (qui a sáb)"
     *   "8 Agosto"
     */
    private function parseDateString(string $raw): ?array {
        if (!$raw) return null;

        $months = [
            'janeiro'=>'01','fevereiro'=>'02','março'=>'03','marco'=>'03',
            'abril'=>'04','maio'=>'05','junho'=>'06','julho'=>'07',
            'agosto'=>'08','setembro'=>'09','outubro'=>'10','novembro'=>'11','dezembro'=>'12',
        ];

        $s = strtolower(trim($raw));

        // Strip day-of-week annotations like "(sex)" or "(ter a qui)"
        $s = preg_replace('/\([^)]*\)/', '', $s);
        $s = trim($s);

        $month = null;
        foreach ($months as $name => $num) {
            if (strpos($s, $name) !== false) {
                $month = $num;
                $s     = trim(str_replace($name, '', $s));
                break;
            }
        }
        if (!$month) return null;

        // Extract day numbers remaining in $s
        preg_match_all('/\d+/', $s, $dayMatches);
        $days = $dayMatches[0];

        if (empty($days)) return null;

        $startDay = (int)$days[0];
        $endDay   = (int)($days[count($days) - 1]);

        $year     = $this->inferYear($month, $startDay);
        $start    = sprintf('%04d-%02d-%02d 19:30:00', $year, $month, $startDay);
        $end      = sprintf('%04d-%02d-%02d 19:30:00', $year, $month, $endDay);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Pick the year (prev / current / next) whose date is closest to today,
     * excluding candidates that are more than 180 days in the past.
     * This handles events whose month/day appear in the TC JSON after the event
     * has already passed — e.g. "8 novembro" should resolve to Nov 2025, not Nov 2026.
     */
    private function inferYear(string $month, int $day): int {
        $now      = time();
        $year     = (int)date('Y');
        $pastLimit = strtotime('-180 days');

        $candidates = [
            $year - 1 => mktime(0, 0, 0, (int)$month, $day, $year - 1),
            $year     => mktime(0, 0, 0, (int)$month, $day, $year),
            $year + 1 => mktime(0, 0, 0, (int)$month, $day, $year + 1),
        ];

        // Drop dates too far in the past
        $valid = array_filter($candidates, fn($ts) => $ts >= $pastLimit);

        if (empty($valid)) {
            return $year + 1; // shouldn't happen
        }

        // Return the year whose date is closest to today
        $best = null;
        $bestDiff = PHP_INT_MAX;
        foreach ($valid as $y => $ts) {
            $diff = abs($ts - $now);
            if ($diff < $bestDiff) {
                $bestDiff = $diff;
                $best = $y;
            }
        }

        return $best;
    }

    private function normalizeCategory(string $cat): string {
        $map = [
            'música'           => 'Música',
            'musica'           => 'Música',
            'cineconcerto'     => 'Música',
            'ópera'            => 'Ópera',
            'opera'            => 'Ópera',
            'teatro'           => 'Teatro',
            'dança'            => 'Dança',
            'danca'            => 'Dança',
            'dança e música'   => 'Dança',
            'cinema'           => 'Cinema',
            'mediação'         => 'Mediação',
            'mediacao'         => 'Mediação',
            'multidisciplinar' => 'Multidisciplinar',
            'exposição'        => 'Exposição',
            'exposicao'        => 'Exposição',
            'instalação'       => 'Exposição',
            'infantojuvenil'   => 'Infantojuvenil',
            'conversa'         => 'Conversa',
            'workshop'         => 'Workshop',
            'oficina'          => 'Workshop',
            'performance'      => 'Performance',
            'noite branca'     => 'Noite Branca',
            'outros'           => 'Outros',
            'sem categoria'    => 'Cultura',
        ];

        $key = strtolower(trim($cat));
        return $map[$key] ?? ucfirst($cat) ?: 'Cultura';
    }
}
