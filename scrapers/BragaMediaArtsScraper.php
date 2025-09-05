<?php
class BragaMediaArtsScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $html = $this->fetchUrl('https://www.bragamediaarts.com/pt/agenda/');
            
            if (!$html) {
                return ['error' => 'Failed to fetch data from Braga Media Arts website'];
            }
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Find all event links in the agenda
            $eventLinks = $xpath->query('//a[contains(@href, "/pt/agenda/") and @href != "/pt/agenda/"]');
            
            if ($eventLinks->length === 0) {
                $errors[] = "No event links found. Website structure may have changed.";
                return [
                    'events_scraped' => 0,
                    'errors' => $errors
                ];
            }
            
            foreach ($eventLinks as $eventLink) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventLink);
                    $eventUrl = $this->extractEventUrl($eventLink);
                    $dateInfo = $this->extractDateInfo($xpath, $eventLink);
                    $location = $this->extractLocation($xpath, $eventLink);
                    $categories = $this->extractCategories($xpath, $eventLink);
                    $description = $this->extractDescription($xpath, $eventLink);
                    
                    // If no specific venue found, use "Vários Locais" (Various Locations)
                    // since Braga Media Arts events can be in different venues
                    if (!$location) {
                        $location = 'Vários Locais';
                    }
                    
                    if ($title && $dateInfo['date']) {
                        // Handle multiple categories from hashtags
                        $categoryList = $this->splitCategories(implode(' / ', $categories));
                        
                        foreach ($categoryList as $singleCategory) {
                            if ($this->saveEvent(
                                $title, 
                                $description, 
                                $dateInfo['date'], 
                                $singleCategory, 
                                null, // Images will need to be extracted from individual event pages if needed
                                $eventUrl,
                                $location,
                                $dateInfo['start_date'],
                                $dateInfo['end_date']
                            )) {
                                $eventsScraped++;
                            }
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
    
    private function extractEventTitle($xpath, $eventLink) {
        $titleNodes = $xpath->query('.//h2', $eventLink);
        if ($titleNodes->length > 0) {
            $title = trim($titleNodes->item(0)->textContent);
            if (strlen($title) > 3 && strlen($title) < 200) {
                return $title;
            }
        }
        return null;
    }
    
    private function extractEventUrl($eventLink) {
        $href = $eventLink->getAttribute('href');
        if ($href) {
            // Make absolute URL
            if (strpos($href, 'http') !== 0) {
                if (strpos($href, '/') === 0) {
                    return 'https://www.bragamediaarts.com' . $href;
                } else {
                    return 'https://www.bragamediaarts.com/' . $href;
                }
            }
            return $href;
        }
        return null;
    }
    
    private function extractDateInfo($xpath, $eventLink) {
        $listItems = $xpath->query('.//ul/li', $eventLink);
        
        foreach ($listItems as $item) {
            $text = trim($item->textContent);
            
            // Look for date patterns (e.g., "06 Set. 18:00" or "06 Set. até 07 Set.")
            if (preg_match('/(\d{1,2})\s+(\w+)\.?\s*(.*)/', $text, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $monthAbbr = strtolower($matches[2]);
                $timeAndRange = $matches[3];
                
                // Map Portuguese month abbreviations
                $months = [
                    'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
                    'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
                    'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
                ];
                
                $month = $months[$monthAbbr] ?? '01';
                $year = date('Y'); // Current year, adjust if needed
                
                // Extract time if present
                $time = '20:00'; // Default time
                if (preg_match('/(\d{1,2}):(\d{2})/', $timeAndRange, $timeMatches)) {
                    $time = $timeMatches[1] . ':' . $timeMatches[2];
                }
                
                $eventDate = "$year-$month-$day $time:00";
                
                // Check for date range (até = until)
                $endDate = $eventDate;
                if (preg_match('/até\s+(\d{1,2})\s+(\w+)/', $timeAndRange, $rangeMatches)) {
                    $endDay = str_pad($rangeMatches[1], 2, '0', STR_PAD_LEFT);
                    $endMonthAbbr = strtolower($rangeMatches[2]);
                    $endMonth = $months[$endMonthAbbr] ?? $month;
                    $endDate = "$year-$endMonth-$endDay $time:00";
                }
                
                return [
                    'date' => $eventDate,
                    'start_date' => $eventDate,
                    'end_date' => $endDate,
                    'raw' => $text
                ];
            }
        }
        
        // Fallback to current date
        $currentDate = date('Y-m-d 20:00:00');
        return [
            'date' => $currentDate,
            'start_date' => $currentDate,
            'end_date' => $currentDate,
            'raw' => 'Default date'
        ];
    }
    
    private function extractLocation($xpath, $eventLink) {
        $listItems = $xpath->query('.//ul/li', $eventLink);
        
        foreach ($listItems as $item) {
            $text = trim($item->textContent);
            
            // Debug: Log what we're seeing (comment out in production)
            // error_log("BragaMediaArts - Checking text: " . $text);
            
            // Skip items that are clearly dates, promoters, hashtags
            if (preg_match('/^\d{1,2}\s+\w+\./', $text) || 
                strpos($text, 'Promotor:') !== false || 
                strpos($text, '#') === 0) {
                continue;
            }
            
            // Check if this text contains venue information within time/session details
            if (preg_match('/\d{1,2}:\d{2}/', $text)) {
                // Look for venue patterns within time strings like "11:00 às 13:00 no Theatro Circo"
                if (preg_match('/\b(?:no|na|em)\s+(.+?)(?:\s*$|\s*\|)/', $text, $matches)) {
                    $venueText = trim($matches[1]);
                    if (strlen($venueText) > 3 && strlen($venueText) < 100) {
                        return $venueText;
                    }
                }
                // Also check for direct venue mentions in time strings
                $venues = [
                    'gnration', 'Theatro Circo', 'Teatro Circo', 'Salão Medieval', 
                    'Reitoria da Universidade do Minho', 'CERCI', 'Universidade do Minho',
                    'Centro Cultural Vila Flor', 'Museu dos Biscainhos', 'Centro de Arte',
                    'Biblioteca', 'Conservatório', 'Espaço Vita', 'Centésima', 'Forum Braga'
                ];
                
                foreach ($venues as $venue) {
                    if (stripos($text, $venue) !== false) {
                        // Extract the venue part from the text
                        if (preg_match('/\b' . preg_quote($venue, '/') . '[^|]*?(?=\s*\||$)/i', $text, $matches)) {
                            $extractedVenue = trim($matches[0]);
                            return $extractedVenue;
                        }
                    }
                }
                continue; // Skip other time-related texts
            }
            
            // Extended list of known venues in Braga
            $venues = [
                'gnration', 
                'Theatro Circo', 
                'Teatro Circo',
                'Salão Medieval', 
                'Reitoria da Universidade do Minho',
                'Centro de Arte Contemporânea', 
                'Museu',
                'Universidade do Minho',
                'Campus de Gualtar',
                'Instituto Cultural de Ponte de Lima',
                'Biblioteca Lúcio Craveiro da Silva',
                'Centro Cultural Vila Flor',
                'Museu dos Biscainhos',
                'Arquivo Distrital de Braga',
                'Conservatório de Música Calouste Gulbenkian',
                'Centro de Juventude',
                'Convento do Pópulo',
                'Casa da Juventude',
                'Espaço Vita',
                'Centésima',
                'Forum Braga'
            ];
            
            // Check if text contains any known venue
            foreach ($venues as $venue) {
                if (stripos($text, $venue) !== false) {
                    return $text;
                }
            }
            
            // If text looks like a venue (doesn't contain common non-venue patterns)
            // and has reasonable length, consider it a venue
            if (strlen($text) > 3 && strlen($text) < 100) {
                // Additional patterns that indicate this is likely a venue
                $venueIndicators = [
                    'rua ', 'avenida ', 'praça ', 'largo ', 'travessa ',  // Street addresses
                    'auditório', 'sala ', 'centro ', 'museu ', 'biblioteca',
                    'teatro ', 'cinema ', 'galeria ', 'espaço ', 'pavilhão',
                    'instituto ', 'escola ', 'universidade ', 'faculdade'
                ];
                
                $textLower = strtolower($text);
                foreach ($venueIndicators as $indicator) {
                    if (strpos($textLower, $indicator) !== false) {
                        return $text;
                    }
                }
            }
        }
        
        return null; // No venue found - let it be handled by the calling code
    }
    
    private function extractCategories($xpath, $eventLink) {
        $categories = [];
        $listItems = $xpath->query('.//ul/li', $eventLink);
        
        foreach ($listItems as $item) {
            $text = trim($item->textContent);
            
            // Look for hashtag categories
            if (strpos($text, '#') === 0) {
                $category = $this->hashtagToCategory($text);
                if ($category) {
                    $categories[] = $category;
                }
            }
        }
        
        // If no categories found from hashtags, try to infer from title
        if (empty($categories)) {
            $title = $this->extractEventTitle($xpath, $eventLink);
            $categories[] = $this->inferCategoryFromTitle($title);
        }
        
        return empty($categories) ? ['Cultura'] : $categories;
    }
    
    private function hashtagToCategory($hashtag) {
        $hashtag = strtolower($hashtag);
        
        $hashtagMap = [
            '#circuito para todos' => 'Cultura',
            '#circuito escolar' => 'Educação',
            '#música' => 'Música',
            '#musica' => 'Música', 
            '#teatro' => 'Teatro',
            '#dança' => 'Dança',
            '#danca' => 'Dança',
            '#cinema' => 'Cinema',
            '#exposição' => 'Exposição',
            '#exposicao' => 'Exposição',
            '#workshop' => 'Workshop',
            '#conferência' => 'Conferência',
            '#conferencia' => 'Conferência',
            '#concerto' => 'Música',
            '#arte' => 'Arte'
        ];
        
        return $hashtagMap[$hashtag] ?? 'Cultura';
    }
    
    private function inferCategoryFromTitle($title) {
        if (!$title) return 'Cultura';
        
        $title = strtolower($title);
        
        if (strpos($title, 'orquestra') !== false || strpos($title, 'música') !== false || strpos($title, 'concerto') !== false) {
            return 'Música';
        }
        if (strpos($title, 'teatro') !== false || strpos($title, 'peça') !== false) {
            return 'Teatro';
        }
        if (strpos($title, 'dança') !== false) {
            return 'Dança';
        }
        if (strpos($title, 'cinema') !== false || strpos($title, 'filme') !== false) {
            return 'Cinema';
        }
        if (strpos($title, 'exposição') !== false || strpos($title, 'visita') !== false) {
            return 'Exposição';
        }
        if (strpos($title, 'workshop') !== false || strpos($title, 'oficina') !== false) {
            return 'Workshop';
        }
        
        return 'Cultura';
    }
    
    private function extractDescription($xpath, $eventLink) {
        $descriptions = [];
        $listItems = $xpath->query('.//ul/li', $eventLink);
        
        foreach ($listItems as $item) {
            $text = trim($item->textContent);
            
            // Include promoter information and time details as description
            if (strpos($text, 'Promotor:') !== false) {
                $descriptions[] = $text;
            } elseif (strpos($text, 'Sessões') !== false || strpos($text, 'Público') !== false) {
                $descriptions[] = $text;
            }
        }
        
        return !empty($descriptions) ? implode(' | ', $descriptions) : null;
    }
    
    private function splitCategories($categoryString) {
        if (!$categoryString) {
            return ['Cultura'];
        }
        
        // Split by common separators: / | , and
        $categories = preg_split('/\s*[\/\|,]\s*|\s+e\s+|\s+and\s+/i', $categoryString);
        
        $normalizedCategories = [];
        foreach ($categories as $category) {
            $category = trim($category);
            if (!empty($category)) {
                $normalizedCategories[] = $this->normalizeCategory($category);
            }
        }
        
        return empty($normalizedCategories) ? ['Cultura'] : array_unique($normalizedCategories);
    }
    
    private function normalizeCategory($category) {
        $category = trim($category);
        
        $categoryMap = [
            'Música' => 'Música', 'Musica' => 'Música', 'Music' => 'Música',
            'Teatro' => 'Teatro', 'Theatre' => 'Teatro',
            'Dança' => 'Dança', 'Danca' => 'Dança', 'Dance' => 'Dança',
            'Cinema' => 'Cinema', 'Film' => 'Cinema', 'Filme' => 'Cinema',
            'Exposição' => 'Exposição', 'Exposicao' => 'Exposição',
            'Workshop' => 'Workshop', 'Oficina' => 'Workshop',
            'Conferência' => 'Conferência', 'Conferencia' => 'Conferência',
            'Literatura' => 'Literatura', 'Arte' => 'Arte',
            'Cultura' => 'Cultura', 'Educação' => 'Educação', 'Educacao' => 'Educação'
        ];
        
        return $categoryMap[$category] ?? $category;
    }
}