<?php
class CentesimaScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            // Try multiple possible endpoints for the Angular app
            $possibleEndpoints = [
                'https://centesima.com/api/events',
                'https://centesima.com/api/agenda',
                'https://centesima.com/eventos',
                'https://api.centesima.com/events',
                'https://centesima.com/wp-json/wp/v2/events', // WordPress REST API
            ];
            
            $html = null;
            $currentUrl = null;
            
            // Try each endpoint until we find one that works
            foreach ($possibleEndpoints as $url) {
                $response = $this->fetchUrl($url);
                if ($response && strlen($response) > 100) {
                    // Check if response looks like JSON
                    $jsonData = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        return $this->processJsonData($jsonData, $eventsScraped, $errors);
                    }
                    
                    // Check if response contains event-like HTML content
                    if (strpos($response, 'evento') !== false || 
                        strpos($response, 'agenda') !== false ||
                        strpos($response, 'event') !== false) {
                        $html = $response;
                        $currentUrl = $url;
                        break;
                    }
                }
            }
            
            // If no API endpoint worked, try the main agenda page with different user agents
            if (!$html) {
                $html = $this->fetchUrlWithJS("https://centesima.com/agenda");
                $currentUrl = "https://centesima.com/agenda";
            }
            
            if (!$html) {
                return ['error' => 'Failed to fetch any data from Centésima website'];
            }
            
            // Check if this is an Angular SPA with minimal content (not fully rendered)
            $isAngularShell = (strpos($html, 'app-root') !== false && strpos($html, '<script') !== false);
            $hasRenderContent = (strpos($html, 'agenda') !== false || strpos($html, 'evento') !== false || 
                                strpos($html, 'information') !== false || strpos($html, 'titulo') !== false);
            
            if ($isAngularShell && !$hasRenderContent) {
                $errors[] = "Centésima uses a dynamic Angular application. Manual configuration needed.";
                return [
                    'events_scraped' => 0,
                    'errors' => $errors,
                    'info' => 'This website uses dynamic content loading. Please check browser developer tools to find the API endpoints.'
                ];
            }
            
            // Try to parse HTML content
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Look for Centésima-specific event title elements as individual events
            $eventSelectors = [
                '//div[contains(@class, "titulo")]',
                '//div[contains(@class, "information")]',
                '//*[contains(@class, "titulo")]',
            ];
            
            $eventNodes = null;
            foreach ($eventSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $eventNodes = $nodes;
                    break;
                }
            }
            
            if (!$eventNodes || $eventNodes->length === 0) {
                $errors[] = "No event containers found. Website structure may have changed.";
                return [
                    'events_scraped' => 0,
                    'errors' => $errors,
                    'debug_url' => $currentUrl,
                    'html_sample' => substr($html, 0, 500) . '...'
                ];
            }
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $category = $this->extractCategory($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    $image = $this->extractEventImage($xpath, $eventNode);
                    
                    // Only process events that have both title and valid date
                    if ($title && $dateInfo['date']) {
                        // Handle multiple categories
                        $categories = $this->splitCategories($category ?: 'Cultura');
                        
                        foreach ($categories as $singleCategory) {
                            if ($this->saveEvent(
                                $title, 
                                $description, 
                                $dateInfo['date'], 
                                $singleCategory, 
                                $image,
                                $eventUrl,
                                'Centésima',
                                $dateInfo['start_date'],
                                $dateInfo['end_date']
                            )) {
                                $eventsScraped++;
                            }
                        }
                    } else if ($title && !$dateInfo['date']) {
                        $errors[] = "Event '$title' skipped - no date found: " . ($dateInfo['raw'] ?? 'unknown');
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
    
    private function processJsonData($jsonData, &$eventsScraped, &$errors) {
        try {
            // Handle different JSON structures
            $events = $jsonData;
            
            // If it's wrapped in a data property
            if (isset($jsonData['data']) && is_array($jsonData['data'])) {
                $events = $jsonData['data'];
            }
            
            // If it's wrapped in an events property
            if (isset($jsonData['events']) && is_array($jsonData['events'])) {
                $events = $jsonData['events'];
            }
            
            foreach ($events as $event) {
                if (!is_array($event)) continue;
                
                $title = $event['title'] ?? $event['name'] ?? $event['titulo'] ?? null;
                $description = $event['description'] ?? $event['content'] ?? $event['descricao'] ?? '';
                $date = $event['date'] ?? $event['data'] ?? $event['start_date'] ?? null;
                $category = $event['category'] ?? $event['categoria'] ?? $event['type'] ?? 'Cultura';
                $image = $event['image'] ?? $event['featured_image'] ?? $event['imagem'] ?? null;
                $url = $event['url'] ?? $event['link'] ?? $event['permalink'] ?? null;
                
                if ($title && $date) {
                    $parsedDate = $this->parseDate($date);
                    if ($parsedDate) {
                        // Handle multiple categories
                        $categories = $this->splitCategories($category);
                        
                        foreach ($categories as $singleCategory) {
                            if ($this->saveEvent(
                                $title,
                                $description,
                                $parsedDate,
                                $singleCategory,
                                $image,
                                $url,
                                'Centésima'
                            )) {
                                $eventsScraped++;
                            }
                        }
                    }
                }
            }
            
            return [
                'events_scraped' => $eventsScraped,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $errors[] = "Error processing JSON data: " . $e->getMessage();
            return [
                'events_scraped' => 0,
                'errors' => $errors
            ];
        }
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
    
    private function extractEventTitle($xpath, $eventNode) {
        // If this node itself is a titulo, use its content
        if (strpos($eventNode->getAttribute('class'), 'titulo') !== false) {
            $title = trim($eventNode->textContent);
            if (strlen($title) > 3 && strlen($title) < 200) {
                return $title;
            }
        }
        
        // Look for title in Centésima-specific locations
        $titleSelectors = [
            './/*[contains(@class, "titulo")]',
            './/h1', './/h2', './/h3', './/h4', './/h5',
            './/*[contains(@class, "title")]',
            './/*[contains(@class, "name")]',
        ];
        
        foreach ($titleSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                if (strlen($title) > 3 && strlen($title) < 200) {
                    return $title;
                }
            }
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        $urlSelectors = [
            './/a[contains(@href, "evento")]/@href',
            './/a[contains(@href, "event")]/@href',
            './/a[1]/@href',
        ];
        
        foreach ($urlSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                
                if ($href) {
                    // Make absolute URL if needed
                    if (strpos($href, 'http') !== 0) {
                        if (strpos($href, '/') === 0) {
                            return 'https://centesima.com' . $href;
                        } else {
                            return 'https://centesima.com/' . $href;
                        }
                    }
                    return $href;
                }
            }
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // Look for date elements in the Centésima structure
        // The date is usually in a <div class="data"> element near the event title
        
        $dateSelectors = [
            // Look for data div in the same parent container
            '..//div[contains(@class, "data")]',
            // Look for data div in preceding siblings
            'preceding-sibling::div[contains(@class, "data")]',
            // Look for data div in following siblings  
            'following-sibling::div[contains(@class, "data")]',
            // Look for data div in parent's children
            '../div[contains(@class, "data")]',
            // Look for data div anywhere in the same information container
            'ancestor::div[contains(@class, "information")]//div[contains(@class, "data")]',
            // Look for strong elements containing dates
            '..//strong[text()[contains(., "SET") or contains(., "OUT") or contains(., "NOV") or contains(., "DEZ") or contains(., "JAN") or contains(., "FEV")]]'
        ];
        
        foreach ($dateSelectors as $selector) {
            $dateNodes = $xpath->query($selector, $eventNode);
            if ($dateNodes->length > 0) {
                $dateText = trim($dateNodes->item(0)->textContent);
                $parsedDate = $this->parsePortugueseDate($dateText);
                if ($parsedDate) {
                    return [
                        'date' => $parsedDate['start'],
                        'start_date' => $parsedDate['start'], 
                        'end_date' => $parsedDate['end'],
                        'raw' => $dateText
                    ];
                }
            }
        }
        
        // If no date found, try to extract from nearby text content
        $parentText = $eventNode->parentNode ? $eventNode->parentNode->textContent : '';
        $parsedDate = $this->parsePortugueseDate($parentText);
        if ($parsedDate) {
            return [
                'date' => $parsedDate['start'],
                'start_date' => $parsedDate['start'],
                'end_date' => $parsedDate['end'],
                'raw' => 'From parent text'
            ];
        }
        
        // If still no date, don't create the event (return null date)
        return [
            'date' => null,
            'start_date' => null,
            'end_date' => null,
            'raw' => 'No date found'
        ];
    }
    
    private function extractCategory($xpath, $eventNode) {
        $categorySelectors = [
            './/*[contains(@class, "category")]',
            './/*[contains(@class, "categoria")]',
            './/*[contains(@class, "type")]',
            './/*[contains(@class, "tag")]',
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
        
        // Fallback: look for keywords in text
        $text = strtolower($eventNode->textContent);
        $categories = [
            'música' => 'Música', 'musica' => 'Música', 'concerto' => 'Música',
            'teatro' => 'Teatro', 'dança' => 'Dança', 'danca' => 'Dança',
            'cinema' => 'Cinema', 'filme' => 'Cinema',
            'exposição' => 'Exposição', 'exposicao' => 'Exposição',
            'workshop' => 'Workshop', 'conferência' => 'Conferência'
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
            'música' => 'Música', 'musica' => 'Música', 'music' => 'Música',
            'teatro' => 'Teatro', 'theatre' => 'Teatro',
            'dança' => 'Dança', 'danca' => 'Dança', 'dance' => 'Dança',
            'cinema' => 'Cinema', 'film' => 'Cinema', 'filme' => 'Cinema',
            'exposição' => 'Exposição', 'exposicao' => 'Exposição',
            'workshop' => 'Workshop', 'oficina' => 'Workshop',
            'conferência' => 'Conferência', 'conferencia' => 'Conferência',
            'literatura' => 'Literatura', 'arte' => 'Arte'
        ];
        
        return $categoryMap[$category] ?? ucfirst($category);
    }
    
    private function extractDescription($xpath, $eventNode) {
        $descriptions = [];
        
        // Look for preceding subtitulo (description type)
        $prevNodes = $xpath->query('preceding-sibling::div[contains(@class, "subtitulo")][1]', $eventNode);
        if ($prevNodes->length > 0) {
            $desc = trim($prevNodes->item(0)->textContent);
            if (strlen($desc) > 2) {
                $descriptions[] = $desc;
            }
        }
        
        // Look for following autor (author/details)
        $nextNodes = $xpath->query('following-sibling::div[contains(@class, "autor")][1]', $eventNode);
        if ($nextNodes->length > 0) {
            $desc = trim($nextNodes->item(0)->textContent);
            if (strlen($desc) > 2) {
                $descriptions[] = $desc;
            }
        }
        
        // Fallback to normal selectors
        $descSelectors = [
            './/*[contains(@class, "subtitulo")]',
            './/*[contains(@class, "autor")]',
            './/p[1]',
            './/*[contains(@class, "description")]',
            './/*[contains(@class, "descricao")]',
            './/*[contains(@class, "content")]',
        ];
        
        foreach ($descSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $desc = trim($nodes->item(0)->textContent);
                if (strlen($desc) > 2) {
                    $descriptions[] = $desc;
                }
            }
        }
        
        return !empty($descriptions) ? implode(' | ', array_unique($descriptions)) : null;
    }
    
    private function extractEventImage($xpath, $eventNode) {
        // Look for images in the event node and nearby elements
        $imageSelectors = [
            './/img/@src',
            './/img/@data-src',
            './preceding-sibling::div[contains(@class, "cover-event")]//img/@src',
            './following-sibling::div[contains(@class, "cover-event")]//img/@src', 
            '../div[contains(@class, "cover-event")]//img/@src',
            './ancestor::div[contains(@class, "row")]//div[contains(@class, "cover-event")]//img/@src',
        ];
        
        foreach ($imageSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $imageSrc = $nodes->item(0)->nodeValue;
                
                if ($imageSrc && $this->isValidImageUrl($imageSrc)) {
                    // Make absolute URL if needed
                    if (strpos($imageSrc, 'http') !== 0) {
                        if (strpos($imageSrc, '//') === 0) {
                            return 'https:' . $imageSrc;
                        } elseif (strpos($imageSrc, '/') === 0) {
                            return 'https://centesima.com' . $imageSrc;
                        } else {
                            return 'https://centesima.com/' . $imageSrc;
                        }
                    }
                    return $imageSrc;
                }
            }
        }
        
        // Also check for background images in style attributes
        $styleNodes = $xpath->query('.//*[@style]', $eventNode);
        foreach ($styleNodes as $styleNode) {
            $style = $styleNode->getAttribute('style');
            if (preg_match('/background-image:\s*url\((["\']?)([^"\']+)\1\)/i', $style, $matches)) {
                $imageSrc = $matches[2];
                if ($this->isValidImageUrl($imageSrc)) {
                    // Make absolute URL if needed
                    if (strpos($imageSrc, 'http') !== 0) {
                        if (strpos($imageSrc, '//') === 0) {
                            return 'https:' . $imageSrc;
                        } elseif (strpos($imageSrc, '/') === 0) {
                            return 'https://centesima.com' . $imageSrc;
                        } else {
                            return 'https://centesima.com/' . $imageSrc;
                        }
                    }
                    return $imageSrc;
                }
            }
        }
        
        return null;
    }
    
    private function parseDate($dateString) {
        if (!$dateString) return null;
        
        // Portuguese month names
        $months = [
            'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        // Try Portuguese format: "10 Jan 2025"
        if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = substr($matches[2], 0, 3);
            $year = $matches[3];
            
            $month = $months[$monthName] ?? '01';
            return "$year-$month-$day 20:00:00";
        }
        
        // Try ISO format or standard formats
        $timestamp = strtotime($dateString);
        if ($timestamp && $timestamp > time() - (365 * 24 * 60 * 60)) { // Not older than 1 year
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return null;
    }
    
    private function parsePortugueseDate($dateText) {
        if (!$dateText) return null;
        
        // Portuguese month abbreviations
        $months = [
            'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08', 
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
        ];
        
        $dateText = strtolower(trim(strip_tags($dateText)));
        $currentYear = date('Y');
        
        // Pattern 1: "1 a 30 SET" (day range in same month)
        if (preg_match('/(\d{1,2})\s+a\s+(\d{1,2})\s+(\w{3})/', $dateText, $matches)) {
            $startDay = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $endDay = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $monthAbbr = $matches[3];
            
            $month = $months[$monthAbbr] ?? null;
            if ($month) {
                $startDate = "$currentYear-$month-$startDay 20:00:00";
                $endDate = "$currentYear-$month-$endDay 20:00:00";
                
                return [
                    'start' => $startDate,
                    'end' => $endDate
                ];
            }
        }
        
        // Pattern 2: "15 OUT" (single day)
        if (preg_match('/(\d{1,2})\s+(\w{3})/', $dateText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthAbbr = $matches[2];
            
            $month = $months[$monthAbbr] ?? null;
            if ($month) {
                $date = "$currentYear-$month-$day 20:00:00";
                
                return [
                    'start' => $date,
                    'end' => $date
                ];
            }
        }
        
        // Pattern 3: "SET 2024" (whole month)
        if (preg_match('/(\w{3})\s+(\d{4})/', $dateText, $matches)) {
            $monthAbbr = $matches[1];
            $year = $matches[2];
            
            $month = $months[$monthAbbr] ?? null;
            if ($month) {
                $startDate = "$year-$month-01 20:00:00";
                $endDate = "$year-$month-" . date('t', strtotime("$year-$month-01")) . " 20:00:00";
                
                return [
                    'start' => $startDate,
                    'end' => $endDate
                ];
            }
        }
        
        // Pattern 4: Just month name "SET" (assume current year)
        if (preg_match('/^(\w{3})$/', $dateText, $matches)) {
            $monthAbbr = $matches[1];
            
            $month = $months[$monthAbbr] ?? null;
            if ($month) {
                $year = $currentYear;
                // If the month is in the past, assume next year
                if ($month < date('m')) {
                    $year = $currentYear + 1;
                }
                
                $startDate = "$year-$month-01 20:00:00";
                $endDate = "$year-$month-" . date('t', strtotime("$year-$month-01")) . " 20:00:00";
                
                return [
                    'start' => $startDate,
                    'end' => $endDate
                ];
            }
        }
        
        return null;
    }
    
    private function fetchUrlWithJS($url) {
        // Try to use Node.js to render the page if available
        $nodeScript = __DIR__ . '/render-centesima.js';
        if (file_exists($nodeScript)) {
            $command = "timeout 90 node " . escapeshellarg($nodeScript) . " 2>&1";
            $output = shell_exec($command);
            if ($output && strlen($output) > 1000 && strpos($output, '<html') !== false) {
                return $output;
            } else {
                // Log why Node.js rendering failed for debugging
                error_log("Node.js rendering failed or returned insufficient content. Output length: " . strlen($output));
            }
        }
        
        // Fallback to cURL with proper compression handling
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Handle all encodings
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: pt-PT,pt;q=0.9,en;q=0.8',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
        ]);
        
        $result = curl_exec($ch);
        curl_close($ch);
        
        return $result;
    }
    
    private function isValidImageUrl($url) {
        if (!$url) return false;
        
        // Skip very small or placeholder images  
        if (strpos($url, 'data:image') === 0 && strlen($url) < 100) {
            return false;
        }
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff'];
        $lowerUrl = strtolower($url);
        
        // Check for image extensions
        foreach ($imageExtensions as $ext) {
            if (strpos($lowerUrl, '.' . $ext) !== false) {
                return true;
            }
        }
        
        // Also allow data: URLs for base64 encoded images
        if (strpos($lowerUrl, 'data:image') === 0) {
            return true;
        }
        
        // Allow URLs that might be dynamically generated images (common in CMSs)
        if (strpos($lowerUrl, '/image') !== false || 
            strpos($lowerUrl, '/img') !== false ||
            strpos($lowerUrl, '/asset') !== false ||
            strpos($lowerUrl, '/media') !== false ||
            strpos($lowerUrl, '/upload') !== false) {
            return true;
        }
        
        return false;
    }
}