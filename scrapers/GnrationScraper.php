<?php
class GnrationScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $url = "https://www.gnration.pt/ver-todos/";
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
            
            // Use event links to get all events (59 found in debug)
            $eventNodes = $xpath->query('//a[contains(@href, "/event/")]');
            
            if ($eventNodes->length === 0) {
                // Fallback to event-grid divs
                $eventNodes = $xpath->query('//div[@class="event-grid"]');
            }
            
            foreach ($eventNodes as $eventNode) {
                try {
                    // Try to extract event information from various possible structures
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $category = $this->extractCategory($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    
                    if ($title && $dateInfo['date']) {
                        // Try to extract image
                        $image = $this->extractEventImage($eventUrl);
                        
                        // Handle multiple categories by creating separate events
                        $categories = $this->splitCategories($category ?: 'Cultura');
                        
                        foreach ($categories as $singleCategory) {
                            // Save event with date range support
                            if ($this->saveEvent(
                                $title, 
                                $description, 
                                $dateInfo['date'], 
                                $singleCategory, 
                                $image,
                                $eventUrl,
                                'Gnration',
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
    
    private function extractEventTitle($xpath, $eventNode) {
        // For event-grid divs, look for nested links or headings
        // For link nodes, use the link text directly
        if ($eventNode->nodeName === 'a') {
            return trim($eventNode->textContent);
        }
        
        $titleSelectors = [
            './/a[contains(@href, "/event/")]',
            './/h1', './/h2', './/h3', './/h4',
            './/a'
        ];
        
        foreach ($titleSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                if (strlen($title) > 5 && !preg_match('/^\d+/', $title)) { // Avoid dates as titles
                    return $title;
                }
            }
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        // For link nodes, use the href directly
        if ($eventNode->nodeName === 'a' && $eventNode->getAttribute('href')) {
            $href = $eventNode->getAttribute('href');
            if (strpos($href, '/event/') !== false) {
                return strpos($href, 'http') === 0 ? $href : 'https://www.gnration.pt' . $href;
            }
        }
        
        // For other nodes, look for nested links
        $urlSelectors = [
            './/a[contains(@href, "/event/")]/@href',
            './/@href'
        ];
        
        foreach ($urlSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                if (strpos($href, '/event/') !== false) {
                    return strpos($href, 'http') === 0 ? $href : 'https://www.gnration.pt' . $href;
                }
            }
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // For link nodes, we need to look at parent/sibling elements for date info
        if ($eventNode->nodeName === 'a') {
            // Look at parent container for date information
            $parent = $eventNode->parentNode;
            while ($parent && $parent->nodeName !== 'body') {
                $parentText = $parent->textContent;
                
                // Check for date ranges: "18 Set 2025 a 31 Out 2025" or "18 a 31 Set 2025"
                if (preg_match('/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})\s+a\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $parentText, $matches)) {
                    $startDate = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];
                    $endDate = $matches[4] . ' ' . $matches[5] . ' ' . $matches[6];
                    return [
                        'date' => $this->parsePortugueseDate($startDate),
                        'start_date' => $this->parsePortugueseDate($startDate),
                        'end_date' => $this->parsePortugueseDate($endDate),
                        'raw' => $startDate . ' a ' . $endDate
                    ];
                }
                
                // Check for same month range: "18 a 31 Set 2025"
                if (preg_match('/(\d{1,2})\s+a\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $parentText, $matches)) {
                    $startDate = $matches[1] . ' ' . $matches[3] . ' ' . $matches[4];
                    $endDate = $matches[2] . ' ' . $matches[3] . ' ' . $matches[4];
                    return [
                        'date' => $this->parsePortugueseDate($startDate),
                        'start_date' => $this->parsePortugueseDate($startDate),
                        'end_date' => $this->parsePortugueseDate($endDate),
                        'raw' => $matches[1] . ' a ' . $matches[2] . ' ' . $matches[3] . ' ' . $matches[4]
                    ];
                }
                
                // Single date
                if (preg_match('/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $parentText, $matches)) {
                    $dateText = $matches[0];
                    $parsedDate = $this->parsePortugueseDate($dateText);
                    return [
                        'date' => $parsedDate,
                        'start_date' => $parsedDate,
                        'end_date' => $parsedDate,
                        'raw' => $dateText
                    ];
                }
                $parent = $parent->parentNode;
            }
            
            // Try to extract year from URL and use current year + 6 months as fallback
            $href = $eventNode->getAttribute('href');
            if (preg_match('/\/event\/(\d{4})\//', $href, $matches)) {
                $year = $matches[1];
                // Use next month as a reasonable default
                $nextMonth = date('Y-m-d', strtotime('+1 month'));
                $fallbackDate = $year . substr($nextMonth, 4) . ' 20:00:00';
                return [
                    'date' => $fallbackDate,
                    'start_date' => $fallbackDate,
                    'end_date' => $fallbackDate,
                    'raw' => "Year $year (estimated)"
                ];
            }
        } else {
            // For other nodes, use existing logic with range support
            $fullText = $eventNode->textContent;
            
            // Check for date ranges
            if (preg_match('/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})\s+a\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $fullText, $matches)) {
                $startDate = $matches[1] . ' ' . $matches[2] . ' ' . $matches[3];
                $endDate = $matches[4] . ' ' . $matches[5] . ' ' . $matches[6];
                return [
                    'date' => $this->parsePortugueseDate($startDate),
                    'start_date' => $this->parsePortugueseDate($startDate),
                    'end_date' => $this->parsePortugueseDate($endDate),
                    'raw' => $startDate . ' a ' . $endDate
                ];
            }
            
            // Check for same month range
            if (preg_match('/(\d{1,2})\s+a\s+(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $fullText, $matches)) {
                $startDate = $matches[1] . ' ' . $matches[3] . ' ' . $matches[4];
                $endDate = $matches[2] . ' ' . $matches[3] . ' ' . $matches[4];
                return [
                    'date' => $this->parsePortugueseDate($startDate),
                    'start_date' => $this->parsePortugueseDate($startDate),
                    'end_date' => $this->parsePortugueseDate($endDate),
                    'raw' => $matches[1] . ' a ' . $matches[2] . ' ' . $matches[3] . ' ' . $matches[4]
                ];
            }
            
            // Single date
            if (preg_match('/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $fullText, $matches)) {
                $dateText = $matches[0];
                $parsedDate = $this->parsePortugueseDate($dateText);
                return [
                    'date' => $parsedDate,
                    'start_date' => $parsedDate,
                    'end_date' => $parsedDate,
                    'raw' => $dateText
                ];
            }
        }
        
        return ['date' => null, 'start_date' => null, 'end_date' => null, 'raw' => ''];
    }
    
    private function extractCategory($xpath, $eventNode) {
        // First try the specific Gnration category element
        $categorySelectors = [
            './/div[contains(@class, "card__category")]',
            './/span[contains(@class, "card__category")]',
            './/*[contains(@class, "card__category")]'
        ];
        
        foreach ($categorySelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $category = trim($nodes->item(0)->textContent);
                if ($category) {
                    return $this->normalizeCategory($category);
                }
            }
        }
        
        // Fallback to parent container search
        $parent = $eventNode->parentNode;
        while ($parent && $parent->nodeName !== 'body') {
            $categoryElements = $xpath->query('.//*[contains(@class, "card__category")]', $parent);
            if ($categoryElements->length > 0) {
                $category = trim($categoryElements->item(0)->textContent);
                if ($category) {
                    return $this->normalizeCategory($category);
                }
            }
            $parent = $parent->parentNode;
        }
        
        // Last resort: try to extract from text content
        $text = strtolower($eventNode->textContent);
        $categories = ['música', 'exposição', 'teatro', 'dança', 'cinema', 'literatura', 'workshop', 'concerto'];
        
        foreach ($categories as $cat) {
            if (strpos($text, $cat) !== false) {
                return $this->normalizeCategory($cat);
            }
        }
        
        return 'Cultura';
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
        $category = trim(strtolower($category));
        
        // Map Gnration categories to standardized ones
        $categoryMap = [
            'música' => 'Música',
            'musica' => 'Música',
            'music' => 'Música',
            'concerto' => 'Música',
            'exposição' => 'Exposição',
            'exposicao' => 'Exposição',
            'exhibition' => 'Exposição',
            'expo' => 'Exposição',
            'teatro' => 'Teatro',
            'theatre' => 'Teatro',
            'dança' => 'Dança',
            'danca' => 'Dança',
            'dance' => 'Dança',
            'cinema' => 'Cinema',
            'film' => 'Cinema',
            'filme' => 'Cinema',
            'literatura' => 'Literatura',
            'workshop' => 'Workshop',
            'oficina' => 'Workshop',
            'conferência' => 'Conferência',
            'conferencia' => 'Conferência',
            'talk' => 'Conferência',
            'conversa' => 'Conversa',
            'discussão' => 'Conversa',
            'discussao' => 'Conversa',
            'debate' => 'Conversa',
            'arte' => 'Arte',
            'art' => 'Arte'
        ];
        
        return $categoryMap[$category] ?? ucfirst($category);
    }
    
    private function extractDescription($xpath, $eventNode) {
        $descSelectors = [
            './/p',
            './/div[contains(@class, "description")]',
            './/span[contains(@class, "desc")]'
        ];
        
        foreach ($descSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $desc = trim($nodes->item(0)->textContent);
                if (strlen($desc) > 20) {
                    return $desc;
                }
            }
        }
        
        return null;
    }
    
    private function parsePortugueseDate($dateString) {
        if (!$dateString) return null;
        
        $months = [
            'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12',
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        // Pattern: "10 Jul 2025" or "10 Julho 2025"
        if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = $matches[2];
            $year = $matches[3];
            
            // Find month number
            $month = '01';
            foreach ($months as $name => $num) {
                if (strpos($monthName, $name) === 0 || strpos($name, $monthName) === 0) {
                    $month = $num;
                    break;
                }
            }
            
            return "$year-$month-$day 20:00:00"; // Default to 8 PM if no time specified
        }
        
        // Fallback: try standard date parsing
        $timestamp = strtotime($dateString);
        if ($timestamp && $timestamp > time()) { // Only future dates
            return date('Y-m-d H:i:s', $timestamp);
        }
        
        return null;
    }
    
    private function extractEventImage($eventUrl) {
        if (!$eventUrl) return null;
        
        try {
            // Fetch event detail page for image
            $html = $this->fetchUrl($eventUrl);
            if (!$html) return null;
            
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Look for event images in common locations
            $imageSelectors = [
                '//meta[@property="og:image"]/@content',  // Open Graph image
                '//img[contains(@class, "event-image")]/@src',
                '//img[contains(@class, "featured")]/@src',
                '//article//img[1]/@src',  // First image in article
                '//main//img[1]/@src',     // First image in main content
                '//div[contains(@class, "content")]//img[1]/@src'
            ];
            
            foreach ($imageSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    $imageSrc = trim($nodes->item(0)->nodeValue);
                    
                    if ($imageSrc && !empty($imageSrc)) {
                        // Make absolute URL if needed
                        if (strpos($imageSrc, 'http') !== 0) {
                            if (strpos($imageSrc, '//') === 0) {
                                $imageSrc = 'https:' . $imageSrc;
                            } elseif (strpos($imageSrc, '/') === 0) {
                                $imageSrc = 'https://www.gnration.pt' . $imageSrc;
                            } else {
                                $imageSrc = 'https://www.gnration.pt/' . $imageSrc;
                            }
                        }
                        
                        // Validate image URL
                        if ($this->isValidImageUrl($imageSrc)) {
                            return $imageSrc;
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            // Fail silently for images - don't break event scraping
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
}