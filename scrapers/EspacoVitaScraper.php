<?php
class EspacoVitaScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $url = "https://www.espacovita.pt/agenda/";
            $html = $this->fetchUrl($url);
            
            if (!$html) {
                return ['error' => 'Failed to fetch URL'];
            }
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Find all event containers
            $eventNodes = $xpath->query('//div[contains(@class, "listEventoSingle")]');
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $category = $this->extractCategory($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    $image = $this->extractEventImage($xpath, $eventNode);
                    
                    if ($title && $dateInfo['date']) {
                        // Save event with date range support
                        if ($this->saveEvent(
                            $title, 
                            $description, 
                            $dateInfo['date'], 
                            $category ?: 'Cultura', 
                            $image,
                            $eventUrl,
                            'Espaço Vita',
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
        // The title is the text that appears after the H4 (category) element
        // We need to look for text nodes that contain the actual event name
        
        // Strategy: Find text lines and pick the one that looks like a title
        $textContent = $eventNode->textContent;
        $lines = array_filter(array_map('trim', explode("\n", $textContent)));
        
        $foundCategory = false;
        foreach ($lines as $line) {
            // Skip category line (h4 content)
            if (in_array(strtolower($line), ['humor', 'música', 'teatro', 'dança', 'cinema', 'arte', 'cultura'])) {
                $foundCategory = true;
                continue;
            }
            
            // After finding category, the next substantial line should be the title
            if ($foundCategory && strlen($line) > 3 && strlen($line) < 100) {
                // Skip if it looks like a person name (contains lowercase words that suggest it's an artist)
                $lowerLine = strtolower($line);
                if (!preg_match('/\bc\/\b|\bcom\b|\bde\b|\be\b|\bda\b|\bdo\b/', $lowerLine) && 
                    !preg_match('/auditório|palco|sala/', $lowerLine) &&
                    !preg_match('/segunda|terça|quarta|quinta|sexta|sábado|domingo/', $lowerLine) &&
                    !preg_match('/\d+h\d+/', $lowerLine) &&
                    !preg_match('/\d+\s*(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/', $lowerLine)) {
                    return $line;
                }
            }
        }
        
        // Fallback: use h3 if no better title found (this might be the artist name)
        $h3Elements = $xpath->query('.//h3', $eventNode);
        if ($h3Elements->length > 0) {
            $title = trim($h3Elements->item(0)->textContent);
            if (strlen($title) > 3) {
                return $title;
            }
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        // Look for the main link in the event container
        $linkElements = $xpath->query('.//a[@href]', $eventNode);
        
        if ($linkElements->length > 0) {
            $href = $linkElements->item(0)->getAttribute('href');
            
            if ($href) {
                // Make absolute URL if needed
                if (strpos($href, 'http') !== 0) {
                    if (strpos($href, '/') === 0) {
                        return 'https://www.espacovita.pt' . $href;
                    } else {
                        return 'https://www.espacovita.pt/' . $href;
                    }
                }
                return $href;
            }
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // Look for date information in various possible containers
        $fullText = $eventNode->textContent;
        
        // Try to find Portuguese day names and dates with time
        // Pattern: "quarta, 24 setembro" followed by "21h30"
        if (preg_match('/(segunda|terça|quarta|quinta|sexta|sábado|domingo),?\s*(\d{1,2})\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(\s+(\d{4}))?/i', $fullText, $matches)) {
            $day = $matches[2];
            $month = $matches[3];
            $year = isset($matches[5]) ? $matches[5] : date('Y');
            
            // If the month has passed this year, assume next year
            $currentMonth = date('n');
            $monthNum = $this->getMonthNumber($month);
            if ($monthNum < $currentMonth && !isset($matches[5])) {
                $year = date('Y') + 1;
            }
            
            // Look for time in format "21h30" or "21h00"
            $hour = 20; // Default
            $minute = 0; // Default
            
            if (preg_match('/(\d{1,2})h(\d{2})/', $fullText, $timeMatches)) {
                $hour = intval($timeMatches[1]);
                $minute = intval($timeMatches[2]);
            } elseif (preg_match('/(\d{1,2})h/', $fullText, $timeMatches)) {
                $hour = intval($timeMatches[1]);
                $minute = 0;
            }
            
            $dateText = "$day de $month $year $hour:$minute";
            $parsedDate = $this->parsePortugueseDate($dateText);
            
            return [
                'date' => $parsedDate,
                'start_date' => $parsedDate,
                'end_date' => $parsedDate,
                'raw' => $dateText
            ];
        }
        
        // Try simpler pattern: "20 setembro" with time
        if (preg_match('/(\d{1,2})\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro)(\s+(\d{4}))?/i', $fullText, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = isset($matches[4]) ? $matches[4] : date('Y');
            
            // If the month has passed this year, assume next year
            $currentMonth = date('n');
            $monthNum = $this->getMonthNumber($month);
            if ($monthNum < $currentMonth && !isset($matches[4])) {
                $year = date('Y') + 1;
            }
            
            // Look for time in format "21h30" or "21h00"
            $hour = 20; // Default
            $minute = 0; // Default
            
            if (preg_match('/(\d{1,2})h(\d{2})/', $fullText, $timeMatches)) {
                $hour = intval($timeMatches[1]);
                $minute = intval($timeMatches[2]);
            } elseif (preg_match('/(\d{1,2})h/', $fullText, $timeMatches)) {
                $hour = intval($timeMatches[1]);
                $minute = 0;
            }
            
            $dateText = "$day de $month $year $hour:$minute";
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
    
    private function getMonthNumber($monthName) {
        $months = [
            'janeiro' => 1, 'fevereiro' => 2, 'março' => 3, 'abril' => 4,
            'maio' => 5, 'junho' => 6, 'julho' => 7, 'agosto' => 8,
            'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12
        ];
        
        return $months[strtolower($monthName)] ?? 1;
    }
    
    private function extractCategory($xpath, $eventNode) {
        // Look for h4 elements which contain the category
        $categoryElements = $xpath->query('.//h4', $eventNode);
        
        if ($categoryElements->length > 0) {
            $category = trim($categoryElements->item(0)->textContent);
            if ($category && strlen($category) < 50) {
                return $this->normalizeCategory($category);
            }
        }
        
        // Look for category elements - could be in various places
        $categorySelectors = [
            './/*[contains(@class, "categoria")]',
            './/*[contains(@class, "category")]',
            './/*[contains(@class, "tipo")]',
        ];
        
        foreach ($categorySelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $category = trim($nodes->item(0)->textContent);
                if ($category && strlen($category) < 50) {
                    return $this->normalizeCategory($category);
                }
            }
        }
        
        // Look for common category keywords in the text
        $text = strtolower($eventNode->textContent);
        $categories = [
            'música' => 'Música',
            'musica' => 'Música', 
            'concerto' => 'Música',
            'teatro' => 'Teatro',
            'dança' => 'Dança',
            'danca' => 'Dança',
            'humor' => 'Humor',
            'comédia' => 'Humor',
            'comedia' => 'Humor',
            'espetáculo' => 'Espetáculo',
            'espetaculo' => 'Espetáculo',
            'show' => 'Espetáculo',
            'cinema' => 'Cinema',
            'filme' => 'Cinema'
        ];
        
        foreach ($categories as $keyword => $category) {
            if (strpos($text, $keyword) !== false) {
                return $category;
            }
        }
        
        return 'Cultura';
    }
    
    private function normalizeCategory($category) {
        $category = trim(strtolower($category));
        
        $categoryMap = [
            'música' => 'Música',
            'musica' => 'Música',
            'music' => 'Música',
            'concerto' => 'Música',
            'teatro' => 'Teatro',
            'theatre' => 'Teatro',
            'dança' => 'Dança',
            'danca' => 'Dança',
            'dance' => 'Dança',
            'humor' => 'Humor',
            'comédia' => 'Humor',
            'comedia' => 'Humor',
            'comedy' => 'Humor',
            'espetáculo' => 'Espetáculo',
            'espetaculo' => 'Espetáculo',
            'show' => 'Espetáculo',
            'cinema' => 'Cinema',
            'film' => 'Cinema',
            'filme' => 'Cinema'
        ];
        
        return $categoryMap[$category] ?? ucfirst($category);
    }
    
    private function extractDescription($xpath, $eventNode) {
        // Look for description or performer information
        $descSelectors = [
            './/*[contains(@class, "performer")]',
            './/*[contains(@class, "artista")]',
            './/*[contains(@class, "description")]',
            './/*[contains(@class, "desc")]',
            './/p'
        ];
        
        foreach ($descSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $desc = trim($nodes->item(0)->textContent);
                if (strlen($desc) > 10 && strlen($desc) < 500) {
                    return $desc;
                }
            }
        }
        
        return null;
    }
    
    private function extractEventImage($xpath, $eventNode) {
        // Look for images in the event node
        $imageElements = $xpath->query('.//img[@src]', $eventNode);
        
        if ($imageElements->length > 0) {
            $imageSrc = $imageElements->item(0)->getAttribute('src');
            
            if ($imageSrc) {
                // Make absolute URL if needed
                if (strpos($imageSrc, 'http') !== 0) {
                    if (strpos($imageSrc, '//') === 0) {
                        return 'https:' . $imageSrc;
                    } elseif (strpos($imageSrc, '/') === 0) {
                        return 'https://www.espacovita.pt' . $imageSrc;
                    } else {
                        return 'https://www.espacovita.pt/' . $imageSrc;
                    }
                }
                
                if ($this->isValidImageUrl($imageSrc)) {
                    return $imageSrc;
                }
            }
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
        
        // Check for common image URL patterns
        if (strpos($lowerUrl, 'image') !== false || 
            strpos($lowerUrl, 'photo') !== false ||
            strpos($lowerUrl, 'media') !== false) {
            return true;
        }
        
        return false;
    }
    
    private function parsePortugueseDate($dateString) {
        if (!$dateString) return null;
        
        $months = [
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        // Pattern: "15 de janeiro 2025 21:30" (with time)
        if (preg_match('/(\d{1,2})\s+de\s+(\w+)\s+(\d{4})\s+(\d{1,2}):(\d{2})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            $hour = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
            $minute = $matches[5];
            
            $month = '01';
            foreach ($months as $name => $num) {
                if (strpos($monthName, $name) === 0 || strpos($name, $monthName) === 0) {
                    $month = $num;
                    break;
                }
            }
            
            return "$year-$month-$day $hour:$minute:00";
        }
        
        // Pattern: "15 de janeiro 2025" (without specific time)
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
            
            return "$year-$month-$day 20:00:00"; // Default to 8:00 PM
        }
        
        return null;
    }
}