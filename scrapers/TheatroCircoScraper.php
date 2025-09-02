<?php
class TheatroCircoScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $url = "https://www.theatrocirco.com/pt/agendaebilheteira";
            $html = $this->fetchUrl($url);
            
            if (!$html) {
                return ['error' => 'Failed to fetch URL'];
            }
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Find all event links within the events container
            $eventNodes = $xpath->query('//a[contains(@href, "programacultural")]');
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $category = $this->extractCategory($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    
                    if ($title && $dateInfo['date'] && $eventUrl) {
                        // Try to extract image from detail page
                        $image = $this->extractEventImage($eventUrl);
                        
                        if ($this->saveEvent(
                            $title, 
                            $description, 
                            $dateInfo['date'], 
                            $category ?: 'Cultura', 
                            $image,
                            $eventUrl,
                            'Theatro Circo',
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
        // For link nodes, use the link text or look for nested title elements
        $title = trim($eventNode->textContent);
        
        if (strlen($title) > 5) {
            // Clean up the title - remove extra whitespace and unwanted text
            $title = preg_replace('/\s+/', ' ', $title);
            $title = preg_replace('/Mais Informação.*$/i', '', $title);
            $title = preg_replace('/Comprar Bilhete.*$/i', '', $title);
            $title = trim($title);
            
            // Skip if it's just a date or navigation text
            if (!preg_match('/^\d+/', $title) && 
                !preg_match('/^(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)/i', $title) &&
                strlen($title) > 8) {
                return $title;
            }
        }
        
        // Look for title in parent/sibling elements
        $parent = $eventNode->parentNode;
        if ($parent) {
            $titleElements = $xpath->query('.//h1 | .//h2 | .//h3 | .//h4 | .//*[contains(@class, "title")]', $parent);
            foreach ($titleElements as $titleEl) {
                $parentTitle = trim($titleEl->textContent);
                if (strlen($parentTitle) > 5 && !preg_match('/^\d+/', $parentTitle)) {
                    return $parentTitle;
                }
            }
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        $href = $eventNode->getAttribute('href');
        
        if ($href && strpos($href, 'programacultural') !== false) {
            return strpos($href, 'http') === 0 ? $href : 'https://www.theatrocirco.com' . $href;
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // Look at the parent container and surrounding text for date information
        $parent = $eventNode->parentNode;
        $attempts = 0;
        
        while ($parent && $attempts < 3) {
            $parentText = $parent->textContent;
            
            // Look for date ranges: "2 a 4 de Setembro 2025"
            if (preg_match('/(\d{1,2})\s+a\s+(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/i', $parentText, $matches)) {
                $startDay = $matches[1];
                $endDay = $matches[2];
                $month = $matches[3];
                $year = $matches[4];
                
                $startDate = "$startDay de $month $year";
                $endDate = "$endDay de $month $year";
                
                return [
                    'date' => $this->parsePortugueseDate($startDate),
                    'start_date' => $this->parsePortugueseDate($startDate),
                    'end_date' => $this->parsePortugueseDate($endDate),
                    'raw' => "$startDay a $endDay de $month $year"
                ];
            }
            
            // Look for single date: "15 de Janeiro 2025"
            if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/i', $parentText, $matches)) {
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                
                $dateText = "$day de $month $year";
                $parsedDate = $this->parsePortugueseDate($dateText);
                return [
                    'date' => $parsedDate,
                    'start_date' => $parsedDate,
                    'end_date' => $parsedDate,
                    'raw' => $dateText
                ];
            }
            
            // Try next parent
            $parent = $parent->parentNode;
            $attempts++;
        }
        
        // Look in the full event node text
        $fullText = $eventNode->textContent;
        
        // Check for date ranges in full text
        if (preg_match('/(\d{1,2})\s+a\s+(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/i', $fullText, $matches)) {
            $startDay = $matches[1];
            $endDay = $matches[2];
            $month = $matches[3];
            $year = $matches[4];
            
            $startDate = "$startDay de $month $year";
            $endDate = "$endDay de $month $year";
            
            return [
                'date' => $this->parsePortugueseDate($startDate),
                'start_date' => $this->parsePortugueseDate($startDate),
                'end_date' => $this->parsePortugueseDate($endDate),
                'raw' => "$startDay a $endDay de $month $year"
            ];
        }
        
        // Single date in full text
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/i', $fullText, $matches)) {
            $day = $matches[1];
            $month = $matches[2];  
            $year = $matches[3];
            
            $dateText = "$day de $month $year";
            $parsedDate = $this->parsePortugueseDate($dateText);
            return [
                'date' => $parsedDate,
                'start_date' => $parsedDate,
                'end_date' => $parsedDate,
                'raw' => $dateText
            ];
        }
        
        return ['date' => null, 'start_date' => null, 'end_date' => null, 'raw' => ''];
    }
    
    private function extractCategory($xpath, $eventNode) {
        // Look for category indicators in the surrounding content
        $parent = $eventNode->parentNode;
        
        if ($parent) {
            $text = strtolower($parent->textContent);
            
            // Common Theatro Circo categories
            $categories = [
                'teatro' => 'Teatro',
                'música' => 'Música', 
                'musica' => 'Música',
                'dança' => 'Dança',
                'danca' => 'Dança',
                'cinema' => 'Cinema',
                'literatura' => 'Literatura',
                'exposição' => 'Exposição',
                'exposicao' => 'Exposição',
                'conferência' => 'Conferência',
                'conferencia' => 'Conferência',
                'workshop' => 'Workshop',
                'concerto' => 'Música',
                'espetáculo' => 'Espetáculo',
                'espetaculo' => 'Espetáculo'
            ];
            
            foreach ($categories as $keyword => $category) {
                if (strpos($text, $keyword) !== false) {
                    return $category;
                }
            }
        }
        
        return 'Cultura';
    }
    
    private function extractDescription($xpath, $eventNode) {
        // Look for description in parent containers
        $parent = $eventNode->parentNode;
        
        if ($parent) {
            $descriptions = $xpath->query('.//*[contains(@class, "desc") or contains(@class, "content")]', $parent);
            
            foreach ($descriptions as $desc) {
                $text = trim($desc->textContent);
                if (strlen($text) > 20 && strlen($text) < 500) {
                    return $text;
                }
            }
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
            
            // Look for event images
            $imageSelectors = [
                '//meta[@property="og:image"]/@content',
                '//img[contains(@class, "evento")]/@src',
                '//img[contains(@class, "agenda")]/@src',
                '//img[contains(@src, "agenda")]/@src',
                '//img[contains(@src, "evento")]/@src',
                '//main//img[1]/@src',
                '//article//img[1]/@src'
            ];
            
            foreach ($imageSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $imageSrc = trim($nodes->item(0)->nodeValue);
                    
                    if ($imageSrc && !empty($imageSrc)) {
                        // Make absolute URL
                        if (strpos($imageSrc, 'http') !== 0) {
                            if (strpos($imageSrc, '//') === 0) {
                                $imageSrc = 'https:' . $imageSrc;
                            } elseif (strpos($imageSrc, '/') === 0) {
                                $imageSrc = 'https://www.theatrocirco.com' . $imageSrc;
                            } else {
                                $imageSrc = 'https://www.theatrocirco.com/' . $imageSrc;
                            }
                        }
                        
                        if ($this->isValidImageUrl($imageSrc)) {
                            return $imageSrc;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            // Fail silently for images
        }
        
        return null;
    }
    
    private function parsePortugueseDate($dateString) {
        if (!$dateString) return null;
        
        $months = [
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        // Pattern: "15 de janeiro 2025"
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            
            $month = '01';
            foreach ($months as $name => $num) {
                if (strpos($monthName, $name) === 0 || strpos($name, $monthName) === 0) {
                    $month = $num;
                    break;
                }
            }
            
            return "$year-$month-$day 19:30:00"; // Default to 7:30 PM for theatre
        }
        
        return null;
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
        
        return false;
    }
}