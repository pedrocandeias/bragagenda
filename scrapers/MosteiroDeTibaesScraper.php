<?php
class MosteiroDeTibaesScraper extends BaseScraper {

    private const API_URL = 'https://www.mosteirodetibaes.gov.pt/wp-json/wp/v2/posts';
    private const LOCATION  = 'Mosteiro de Tibães';

    public function scrape() {
        $eventsScraped = 0;
        $errors        = [];

        // Fetch posts from the last 60 days so we catch recently-published upcoming events
        $after = date('Y-m-d', strtotime('-60 days')) . 'T00:00:00';
        $url   = self::API_URL . '?per_page=50&_embed=1&after=' . urlencode($after);

        $json = $this->fetchUrl($url);
        if (!$json) {
            return ['error' => 'Failed to fetch posts from Mosteiro de Tibães API'];
        }

        $posts = json_decode($json, true);
        if (!is_array($posts)) {
            return ['error' => 'Invalid JSON from Mosteiro de Tibães API'];
        }

        foreach ($posts as $post) {
            try {
                $data = $this->extractPost($post);
                if (!$data) continue;

                // Skip events more than 1 day in the past
                if (strtotime($data['start']) < strtotime('-1 day')) continue;

                $imageUrl = $data['image'] ? $this->downloadImage($data['image']) : null;

                if ($this->saveEvent(
                    $data['title'],
                    null,
                    $data['start'],
                    $data['category'],
                    $imageUrl,
                    $data['url'],
                    self::LOCATION,
                    $data['start'],
                    $data['end']
                )) {
                    $eventsScraped++;
                }
            } catch (Exception $e) {
                $errors[] = 'Error processing post: ' . $e->getMessage();
            }
        }

        $this->updateLastScraped();
        return ['events_scraped' => $eventsScraped, 'errors' => $errors];
    }

    // -----------------------------------------------------------------------

    private function extractPost(array $post): ?array {
        $title = html_entity_decode(
            strip_tags($post['title']['rendered'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        if (!$title) return null;

        $url = $post['link'] ?? null;

        // Plain text of content (used for date/time extraction)
        $rawContent  = $post['content']['rendered'] ?? '';
        $contentText = html_entity_decode(
            strip_tags($rawContent),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $contentText = preg_replace('/\s+/', ' ', $contentText);

        // Use excerpt text for category detection (shorter, usually describes the event)
        $excerptText = html_entity_decode(
            strip_tags($post['excerpt']['rendered'] ?? ''),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        [$day, $month] = $this->parseDayMonth($contentText);
        if (!$day) return null;   // No specific date found – skip generic posts

        $time  = $this->parseTime($contentText);
        $year  = $this->inferYear((int)$month, (int)$day, $post['date']);
        $start = sprintf('%04d-%02d-%02d %s:00', $year, $month, $day, $time);

        $category = $this->detectCategory($title . ' ' . $excerptText . ' ' . $contentText);

        // Featured image
        $image = null;
        $media = $post['_embedded']['wp:featuredmedia'][0] ?? null;
        if ($media && !empty($media['source_url'])) {
            $image = $media['source_url'];
        }

        return [
            'title'    => $title,
            'url'      => $url,
            'start'    => $start,
            'end'      => $start,
            'category' => $category,
            'image'    => $image,
        ];
    }

    /**
     * Find the first "X de mês" date mention in the text.
     * Returns [day, month] or [null, null].
     */
    private function parseDayMonth(string $text): array {
        static $months = [
            'janeiro'=>1,'fevereiro'=>2,'março'=>3,'marco'=>3,
            'abril'=>4,'maio'=>5,'junho'=>6,'julho'=>7,
            'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12,
        ];

        // "dia 28 de fevereiro" / "28 de fevereiro"
        if (preg_match('/(?:dia\s+)?(\d{1,2})\s+de\s+([a-záéíóúãõçêôâ]+)/iu', $text, $m)) {
            $day = (int)$m[1];
            $mon = $months[mb_strtolower($m[2])] ?? null;
            if ($mon && $day >= 1 && $day <= 31) {
                return [$day, $mon];
            }
        }

        return [null, null];
    }

    /**
     * Find the event start time in the text.
     * Handles "pelas 15 horas", "pelas 15h00", "às 14h30", "às 10:00".
     * Returns "HH:MM" string.
     */
    private function parseTime(string $text): string {
        // "pelas X hora(s)" – e.g. "pelas 15 horas"
        if (preg_match('/pelas?\s+(\d{1,2})\s+hora/i', $text, $m)) {
            return sprintf('%02d:00', (int)$m[1]);
        }

        // "pelas XhYY" or "pelas X:YY"
        if (preg_match('/pelas?\s+(\d{1,2})[h:](\d{2})/i', $text, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        // "às XhYY" or "às X:YY"
        if (preg_match('/às?\s+(\d{1,2})[h:](\d{2})/i', $text, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }

        return '10:00'; // sensible default for morning activities
    }

    /**
     * Infer year for a given month/day.
     *
     * Posts are typically published 0–4 weeks before the event. So if the
     * event date (day/month) falls on or after the publication date, use the
     * publication year. Only bump to the next year when the computed date
     * would be notably before the publication date (i.e. the post was
     * published after the event passed, meaning it refers to next year).
     */
    private function inferYear(int $month, int $day, string $pubDate): int {
        $pubTs   = strtotime($pubDate);
        $pubYear = (int)date('Y', $pubTs);

        $ts = mktime(0, 0, 0, $month, $day, $pubYear);

        // Accept if the event date is on or after publication date (minus 1 day buffer)
        if ($ts >= $pubTs - 86400) {
            return $pubYear;
        }

        // The event date precedes the publication date → must be next year
        return $pubYear + 1;
    }

    /**
     * Detect a category from the full text.
     */
    private function detectCategory(string $text): string {
        $lower = mb_strtolower($text);

        $map = [
            'espetáculo'  => 'Espetáculo',
            'espetaculo'  => 'Espetáculo',
            'teatro'      => 'Teatro',
            'dança'       => 'Dança',
            'danca'       => 'Dança',
            'concerto'    => 'Música',
            'música'      => 'Música',
            'musica'      => 'Música',
            'exposição'   => 'Exposição',
            'exposicao'   => 'Exposição',
            'cinema'      => 'Cinema',
            'workshop'    => 'Workshop',
            'oficina'     => 'Workshop',
            'visita'      => 'Visita guiada',
            'conversa'    => 'Conversa',
            'palestra'    => 'Conversa',
            'conferência' => 'Conversa',
            'conferencia' => 'Conversa',
        ];

        foreach ($map as $keyword => $category) {
            if (str_contains($lower, $keyword)) {
                return $category;
            }
        }

        return 'Cultura';
    }
}
