<?php
class PedroRemyScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $url = "https://www.pedroremy.com/concertos-em-cartaz";
            $html = $this->fetchUrl($url);
            
            if (!$html) {
                return ['error' => 'Failed to fetch URL'];
            }
            
            // Parse HTML using DOMDocument
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Look for concert events - targeting common event containers
            $eventNodes = $xpath->query('//div[contains(@class, "event") or contains(@class, "concert") or contains(@class, "card")]');
            
            if ($eventNodes->length === 0) {
                // Fallback to links that might contain event information
                $eventNodes = $xpath->query('//a[contains(@href, "concert") or contains(@href, "event")]');
            }
            
            if ($eventNodes->length === 0) {
                // Another fallback - look for any structured content that might be events
                $eventNodes = $xpath->query('//article | //section[contains(@class, "content")]//div');
            }
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $location = $this->extractLocation($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    
                    if ($title && $dateInfo['date']) {
                        // Try to extract image
                        $image = $this->extractEventImage($eventUrl);
                        
                        // Category is Música since this is a concerts website
                        $category = 'Música';
                        
                        // Save event
                        if ($this->saveEvent(
                            $title, 
                            $description, 
                            $dateInfo['date'], 
                            $category, 
                            $image,
                            $eventUrl,
                            $location ?: 'Pedro Remy',
                            $dateInfo['start_date'],
                            $dateInfo['end_date']
                        )) {
                            $eventsScraped++;
                        }
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error processing event: " . $e->getMessage();
                }
            }
            
            $this->updateLastScraped();
            
            return [
                'events_scraped' => $eventsScraped,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function extractEventTitle($xpath, $eventNode) {
        $titleSelectors = [
            './/h1', './/h2', './/h3', './/h4',
            './/a[contains(@class, "title")]',
            './/div[contains(@class, "title")]',
            './/span[contains(@class, "title")]',
            './/a'
        ];
        
        foreach ($titleSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                if (strlen($title) > 5 && !preg_match('/^\d+/', $title) && !$this->isDateString($title)) {
                    return $title;
                }
            }
        }
        
        // Fallback to node text content if it looks like a title
        $nodeText = trim($eventNode->textContent);
        if (strlen($nodeText) > 5 && strlen($nodeText) < 200 && !$this->isDateString($nodeText)) {
            return $nodeText;
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        // If the node itself is a link
        if ($eventNode->nodeName === 'a' && $eventNode->getAttribute('href')) {
            $href = $eventNode->getAttribute('href');
            return $this->makeAbsoluteUrl($href);
        }
        
        // Look for nested links
        $urlSelectors = [
            './/a/@href'
        ];
        
        foreach ($urlSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                if ($href && !empty(trim($href))) {
                    return $this->makeAbsoluteUrl($href);
                }
            }
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        $fullText = $eventNode->textContent;
        
        // Check for Portuguese date patterns
        // Pattern: "18 de Janeiro de 2025", "18/01/2025", "2025-01-18"
        $datePatterns = [
            // Portuguese format: "18 de Janeiro de 2025"
            '/(\d{1,2})\s+de\s+(Janeiro|Fevereiro|Março|Abril|Maio|Junho|Julho|Agosto|Setembro|Outubro|Novembro|Dezembro)\s+de\s+(\d{4})/i',
            // Abbreviated: "18 Jan 2025"
            '/(\d{1,2})\s+(Jan|Fev|Mar|Abr|Mai|Jun|Jul|Ago|Set|Out|Nov|Dez)\s+(\d{4})/i',
            // Numeric formats
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})/',
            '/(\d{4})-(\d{1,2})-(\d{1,2})/'
        ];
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $parsedDate = $this->parsePortugueseDate($matches[0]);
                if ($parsedDate) {
                    return [
                        'date' => $parsedDate,
                        'start_date' => $parsedDate,
                        'end_date' => $parsedDate,
                        'raw' => $matches[0]
                    ];
                }
            }
        }
        
        // Check for date ranges
        if (preg_match('/(\d{1,2})\s+(?:de\s+)?(\w+)\s+(?:de\s+)?(\d{4})\s+a\s+(\d{1,2})\s+(?:de\s+)?(\w+)\s+(?:de\s+)?(\d{4})/i', $fullText, $matches)) {
            $startDate = $matches[1] . ' de ' . $matches[2] . ' de ' . $matches[3];
            $endDate = $matches[4] . ' de ' . $matches[5] . ' de ' . $matches[6];
            $startParsed = $this->parsePortugueseDate($startDate);
            $endParsed = $this->parsePortugueseDate($endDate);
            
            if ($startParsed && $endParsed) {
                return [
                    'date' => $startParsed,
                    'start_date' => $startParsed,
                    'end_date' => $endParsed,
                    'raw' => $startDate . ' a ' . $endDate
                ];
            }
        }
        
        return ['date' => null, 'start_date' => null, 'end_date' => null, 'raw' => ''];
    }
    
    private function extractLocation($xpath, $eventNode) {
        $locationSelectors = [
            './/div[contains(@class, "location")]',
            './/span[contains(@class, "location")]',
            './/div[contains(@class, "venue")]',
            './/span[contains(@class, "venue")]',
            './/address'
        ];
        
        foreach ($locationSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $location = trim($nodes->item(0)->textContent);
                if (strlen($location) > 2 && strlen($location) < 100) {
                    return $location;
                }
            }
        }
        
        // Look for location patterns in text
        $text = $eventNode->textContent;
        if (preg_match('/(?:em|@|at)\s+([A-Za-zÀ-ÿ\s]{3,50})/', $text, $matches)) {
            $location = trim($matches[1]);
            if (!$this->isDateString($location)) {
                return $location;
            }
        }
        
        return null;
    }
    
    private function extractDescription($xpath, $eventNode) {
        $descSelectors = [
            './/p',
            './/div[contains(@class, "description")]',
            './/div[contains(@class, "content")]',
            './/span[contains(@class, "desc")]'
        ];
        
        foreach ($descSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $desc = trim($nodes->item(0)->textContent);
                if (strlen($desc) > 20 && strlen($desc) < 1000) {
                    return $desc;
                }
            }
        }
        
        return null;
    }
    
    private function parsePortugueseDate($dateString) {
        if (!$dateString) return null;
        
        $months = [
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',
            'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        // Pattern: "18 de janeiro de 2025"
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            
            $month = $months[$monthName] ?? '01';
            return "$year-$month-$day 20:00:00";
        }
        
        // Pattern: "18 jan 2025"
        if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            
            $month = $months[$monthName] ?? '01';
            return "$year-$month-$day 20:00:00";
        }
        
        // Pattern: "18/01/2025"
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            return "$year-$month-$day 20:00:00";
        }
        
        // Pattern: "2025-01-18"
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $dateString, $matches)) {
            $year = $matches[1];
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $day = str_pad($matches[3], 2, '0', STR_PAD_LEFT);
            return "$year-$month-$day 20:00:00";
        }
        
        // Fallback: try standard date parsing
        $timestamp = strtotime($dateString);
        if ($timestamp && $timestamp > time()) {
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return null;
    }
    
    private function extractEventImage($eventUrl) {
        if (!$eventUrl) return null;
        
        try {
            $html = $this->fetchUrl($eventUrl);
            if (!$html) return null;
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            $imageSelectors = [
                '//meta[@property="og:image"]/@content',
                '//img[contains(@class, "event-image")]/@src',
                '//img[contains(@class, "featured")]/@src',
                '//article//img[1]/@src',
                '//main//img[1]/@src',
                '//div[contains(@class, "content")]//img[1]/@src'
            ];
            
            foreach ($imageSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $imageSrc = trim($nodes->item(0)->nodeValue);
                    
                    if ($imageSrc && !empty($imageSrc)) {
                        $absoluteUrl = $this->makeAbsoluteUrl($imageSrc);
                        if ($this->isValidImageUrl($absoluteUrl)) {
                            return $absoluteUrl;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            // Fail silently for images
        }
        
        return null;
    }
    
    private function makeAbsoluteUrl($url) {
        if (!$url) return null;
        
        if (strpos($url, 'http') === 0) {
            return $url;
        }
        
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        
        if (strpos($url, '/') === 0) {
            return 'https://www.pedroremy.com' . $url;
        }
        
        return 'https://www.pedroremy.com/' . $url;
    }
    
    private function isValidImageUrl($url) {
        if (!$url) return false;
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        $lowerUrl = strtolower($url);
        
        foreach ($imageExtensions as $ext) {
            if (strpos($lowerUrl, '.' . $ext) !== false) {
                return true;
            }
        }
        
        if (strpos($lowerUrl, 'image') !== false || 
            strpos($lowerUrl, 'photo') !== false ||
            strpos($lowerUrl, 'media') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function isDateString($text) {
        $text = strtolower(trim($text));
        $dateWords = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 
                      'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
                      'jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
        
        foreach ($dateWords as $word) {
            if (strpos($text, $word) !== false) {
                return true;
            }
        }
        
        return preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $text) || 
               preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $text);
    }
}