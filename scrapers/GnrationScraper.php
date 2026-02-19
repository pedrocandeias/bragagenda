<?php
class GnrationScraper extends BaseScraper {

    public function scrape() {
        $eventsScraped = 0;
        $errors        = [];

        try {
            $html = $this->fetchUrl('https://www.gnration.pt/ver-todos/');
            if (!$html) {
                return ['error' => 'Failed to fetch /ver-todos/'];
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $cards = $xpath->query('//div[contains(@class,"event-grid")]//div[contains(@class,"card column")]');

            foreach ($cards as $card) {
                try {
                    $data = $this->extractCard($xpath, $card);
                    if (!$data['title'] || !$data['start']) continue;

                    $imageUrl = $data['image'] ? $this->downloadImage($data['image']) : null;

                    $categories = $this->splitCategories($data['category'] ?: 'Cultura');
                    foreach ($categories as $category) {
                        if ($this->saveEvent(
                            $data['title'],
                            null,
                            $data['start'],
                            $category,
                            $imageUrl,
                            $data['url'],
                            'Gnration',
                            $data['start'],
                            $data['end']
                        )) {
                            $eventsScraped++;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = 'Error processing card: ' . $e->getMessage();
                }
            }

            $this->updateLastScraped();
            return ['events_scraped' => $eventsScraped, 'errors' => $errors];

        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------

    private function extractCard($xpath, $card): array {
        // Title + URL
        $titleNode = $xpath->query('.//h3[contains(@class,"card__title")]//a', $card)->item(0);
        $title     = $titleNode ? html_entity_decode(trim($titleNode->textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
        $url       = $titleNode ? $titleNode->getAttribute('href') : null;

        // Start date: <p class="card__date ...">25 Fev 2026</p>
        $startRaw  = trim($xpath->query('.//*[contains(@class,"card__date")]', $card)->item(0)?->textContent ?? '');
        // End date: <span class="card__extra-date ..."> a 13 Mar 2026</span>
        $endRaw    = trim($xpath->query('.//*[contains(@class,"card__extra-date")]', $card)->item(0)?->textContent ?? '');
        $endRaw    = ltrim($endRaw, 'a '); // strip leading "a "

        // Time: <p class="card__hour ...">21:00</p>
        $timeRaw   = trim($xpath->query('.//*[contains(@class,"card__hour")]', $card)->item(0)?->textContent ?? '');
        // Take first time if multiple (e.g. "11:00 + 12:00")
        preg_match('/(\d{1,2}:\d{2})/', $timeRaw, $tm);
        $time = $tm[1] ?? '20:00';

        $start = $this->parseDate($startRaw, $time);
        $end   = $endRaw ? ($this->parseDate($endRaw, $time) ?? $start) : $start;

        // Category: <span class="project__category">música / online</span>
        $catNode  = $xpath->query('.//*[contains(@class,"project__category")]', $card)->item(0);
        $category = $catNode ? html_entity_decode(trim($catNode->textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '';

        // Image: background-image:url(...) on card__image div
        $imgNode  = $xpath->query('.//*[contains(@class,"card__image")]', $card)->item(0);
        $image    = null;
        if ($imgNode) {
            $style = $imgNode->getAttribute('style');
            if (preg_match('/url\(([^)]+)\)/', $style, $m)) {
                $image = trim($m[1], "'\" ");
            }
        }

        return compact('title', 'url', 'start', 'end', 'category', 'image');
    }

    /**
     * Parse "25 Fev 2026" + "21:00" → "2026-02-25 21:00:00"
     */
    private function parseDate(string $raw, string $time = '20:00'): ?string {
        static $months = [
            'jan'=>'01','fev'=>'02','mar'=>'03','abr'=>'04',
            'mai'=>'05','jun'=>'06','jul'=>'07','ago'=>'08',
            'set'=>'09','out'=>'10','nov'=>'11','dez'=>'12',
        ];

        // Expect "D Mon YYYY"
        if (!preg_match('/(\d{1,2})\s+(\w{3,})\s+(\d{4})/i', trim($raw), $m)) return null;

        $day   = str_pad((int)$m[1], 2, '0', STR_PAD_LEFT);
        $month = $months[strtolower(substr($m[2], 0, 3))] ?? null;
        $year  = $m[3];

        if (!$month) return null;

        [$h, $i] = explode(':', $time);
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $h, $i);
    }

    private function splitCategories(string $cat): array {
        $parts = preg_split('/\s*[\/|,]\s*/', $cat);
        $result = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p) $result[] = $this->normalizeCategory($p);
        }
        return $result ?: ['Cultura'];
    }

    private function normalizeCategory(string $cat): string {
        static $map = [
            'música'               => 'Música',
            'musica'               => 'Música',
            'concerto'             => 'Música',
            'online'               => 'Música',
            'exposição'            => 'Exposição',
            'exposicao'            => 'Exposição',
            'programa expositivo'  => 'Exposição',
            'instalação'           => 'Exposição',
            'instalacao'           => 'Exposição',
            'imagem'               => 'Imagem',
            'cinema'               => 'Cinema',
            'cinex'                => 'Cinema',
            'teatro'               => 'Teatro',
            'dança'                => 'Dança',
            'danca'                => 'Dança',
            'conversa'             => 'Conversa',
            'workshop'             => 'Workshop',
            'oficina'              => 'Workshop',
            'masterclass'          => 'Masterclass',
            'visita guiada'        => 'Visita guiada',
            'serviço educativo'    => 'Serviço educativo',
            'servico educativo'    => 'Serviço educativo',
            'residência artística' => 'Residência artística',
            'residencia artistica' => 'Residência artística',
            'open call'            => 'Open call',
            'performance'          => 'Performance',
            'espetáculo'           => 'Espetáculo',
            'espetaculo'           => 'Espetáculo',
        ];

        $key = strtolower(trim($cat));
        return $map[$key] ?? ucfirst($cat);
    }
}
