<?php
class ForumBragaScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            // Fetch the main agenda page
            $html = $this->fetchUrl('https://www.forumbraga.com/Agenda/Programacao');
            
            if (!$html) {
                return ['error' => 'Failed to fetch Forum Braga agenda page'];
            }
            
            // Parse HTML content
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            
            $xpath = new DOMXPath($dom);
            
            // Look for event containers - try different selectors based on typical structure
            $eventSelectors = [
                '//div[contains(@class, "event")]',
                '//div[contains(@class, "programa")]',
                '//div[contains(@class, "item")]',
                '//div[contains(@class, "card")]',
                '//article',
                '//div[@class="row"]//div[contains(@class, "col")]'
            ];
            
            $eventNodes = null;
            foreach ($eventSelectors as $selector) {
                $nodes = $xpath->query($selector);
                if ($nodes->length > 0) {
                    // Filter nodes that seem to contain event info
                    foreach ($nodes as $node) {
                        $text = strtolower($node->textContent);
                        if (strpos($text, '20') !== false && (
                            strpos($text, 'jan') !== false || strpos($text, 'fev') !== false ||
                            strpos($text, 'mar') !== false || strpos($text, 'abr') !== false ||
                            strpos($text, 'mai') !== false || strpos($text, 'jun') !== false ||
                            strpos($text, 'jul') !== false || strpos($text, 'ago') !== false ||
                            strpos($text, 'set') !== false || strpos($text, 'out') !== false ||
                            strpos($text, 'nov') !== false || strpos($text, 'dez') !== false ||
                            strpos($text, 'comprar') !== false || strpos($text, 'info') !== false
                        )) {
                            if (!$eventNodes) $eventNodes = new DOMNodeList();
                            $eventNodes = $nodes;
                            break 2;
                        }
                    }
                }
            }
            
            // If no specific event containers found, look for any divs with event-like content
            if (!$eventNodes || $eventNodes->length === 0) {
                $eventNodes = $xpath->query('//div[contains(text(), "2025") or contains(text(), "2024")]');
            }
            
            if (!$eventNodes || $eventNodes->length === 0) {
                $errors[] = "No event containers found. The website structure may have changed.";
                return [
                    'events_scraped' => 0,
                    'errors' => $errors,
                    'debug_info' => 'HTML length: ' . strlen($html) . ', Contains scripts: ' . (strpos($html, '<script') ? 'Yes' : 'No')
                ];
            }
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $eventData = $this->extractEventData($xpath, $eventNode);
                    
                    if ($eventData['title'] && $eventData['date']) {
                        // Handle multiple categories if present
                        $categories = $this->splitCategories($eventData['category'] ?: 'Espetáculo');
                        
                        foreach ($categories as $category) {
                            if ($this->saveEvent(
                                $eventData['title'],
                                $eventData['description'],
                                $eventData['date'],
                                $category,
                                $eventData['image'],
                                $eventData['url'],
                                'Forum Braga'
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
    
    private function extractEventData($xpath, $eventNode) {
        $data = [
            'title' => null,
            'description' => null,
            'date' => null,
            'category' => null,
            'image' => null,
            'url' => null
        ];
        
        // Extract title
        $data['title'] = $this->extractTitle($xpath, $eventNode);
        
        // Extract image
        $data['image'] = $this->extractImage($xpath, $eventNode);
        
        // Extract date
        $data['date'] = $this->extractDate($xpath, $eventNode);
        
        // Extract category
        $data['category'] = $this->extractCategory($xpath, $eventNode);
        
        // Extract URL
        $data['url'] = $this->extractUrl($xpath, $eventNode);
        
        // Extract description
        $data['description'] = $this->extractDescription($xpath, $eventNode);
        
        return $data;
    }
    
    private function extractTitle($xpath, $eventNode) {
        // Look for titles in heading elements first (prioritize h6 which Forum Braga uses for event titles)
        $headingSelectors = ['.//h6', './/h5', './/h4', './/h3', './/h2', './/h1'];
        
        foreach ($headingSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                // Filter out common non-title text but be less restrictive for headings
                if (strlen($title) > 3 && strlen($title) < 200 && 
                    !preg_match('/^(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|\d+)/i', $title) &&
                    strpos(strtolower($title), 'comprar') === false &&
                    strpos(strtolower($title), 'info') === false &&
                    !in_array(strtolower($title), ['espetáculo', 'concerto', 'teatro', 'música', 'dança', 'arte'])) {
                    return $title;
                }
            }
        }
        
        // Look for other title elements
        $titleSelectors = [
            './/*[contains(@class, "title")]',
            './/*[contains(@class, "name")]',
            './/*[contains(@class, "evento")]',
            './/strong',
            './/b'
        ];
        
        foreach ($titleSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                // Filter out very short titles or common non-title text
                if (strlen($title) > 5 && strlen($title) < 200 && 
                    !preg_match('/^(jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez|\d+)/i', $title) &&
                    strpos(strtolower($title), 'comprar') === false &&
                    strpos(strtolower($title), 'info') === false &&
                    !in_array(strtolower($title), ['espetáculo', 'concerto', 'teatro', 'música', 'dança', 'arte'])) {
                    return $title;
                }
            }
        }
        
        // Fallback: look for longest text content that looks like a title
        $textContent = $eventNode->textContent;
        $lines = array_filter(array_map('trim', explode("\n", $textContent)));
        foreach ($lines as $line) {
            if (strlen($line) > 10 && strlen($line) < 200 && 
                !preg_match('/^\d+.*20\d\d/', $line) &&
                strpos($line, 'COMPRAR') === false &&
                !in_array(strtolower($line), ['espetáculo', 'concerto', 'teatro', 'música', 'dança', 'arte'])) {
                return $line;
            }
        }
        
        return null;
    }
    
    private function extractImage($xpath, $eventNode) {
        $imageSelectors = [
            './/img/@src',
            './/img/@data-src'
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
                            return 'https://www.forumbraga.com' . $imageSrc;
                        } else {
                            return 'https://www.forumbraga.com/' . $imageSrc;
                        }
                    }
                    return $imageSrc;
                }
            }
        }
        
        return null;
    }
    
    private function extractDate($xpath, $eventNode) {
        $text = $eventNode->textContent;
        
        // Portuguese months
        $months = [
            'janeiro' => '01', 'jan' => '01',
            'fevereiro' => '02', 'fev' => '02',
            'março' => '03', 'mar' => '03',
            'abril' => '04', 'abr' => '04',
            'maio' => '05', 'mai' => '05',
            'junho' => '06', 'jun' => '06',
            'julho' => '07', 'jul' => '07',
            'agosto' => '08', 'ago' => '08',
            'setembro' => '09', 'set' => '09',
            'outubro' => '10', 'out' => '10',
            'novembro' => '11', 'nov' => '11',
            'dezembro' => '12', 'dez' => '12'
        ];
        
        // Try various Portuguese date formats
        $datePatterns = [
            '/(\d{1,2})\s+de\s+(\w+)\s+de\s+(\d{4})/',  // "15 de janeiro de 2025"
            '/(\d{1,2})\s+(\w+)\s+(\d{4})/',            // "15 janeiro 2025"
            '/(\d{1,2})\/(\d{1,2})\/(\d{4})/',          // "15/01/2025"
            '/(\d{4})-(\d{1,2})-(\d{1,2})/',            // "2025-01-15"
        ];
        
        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                if (count($matches) == 4) {
                    $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                    
                    // Check if second match is a month name or number
                    if (is_numeric($matches[2])) {
                        $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
                    } else {
                        $monthName = strtolower($matches[2]);
                        $month = $months[$monthName] ?? '01';
                    }
                    
                    $year = $matches[3];
                    
                    // Look for time in the text
                    if (preg_match('/(\d{1,2}):(\d{2})/', $text, $timeMatches)) {
                        $hour = str_pad($timeMatches[1], 2, '0', STR_PAD_LEFT);
                        $minute = $timeMatches[2];
                        return "$year-$month-$day $hour:$minute:00";
                    } else {
                        return "$year-$month-$day 20:00:00";
                    }
                }
            }
        }
        
        return null;
    }
    
    private function extractCategory($xpath, $eventNode) {
        $text = $eventNode->textContent;
        
        // Look for categories in square brackets like [Comédia], [Arte], etc.
        if (preg_match('/\[([^\]]+)\]/', $text, $matches)) {
            return $this->normalizeCategory($matches[1]);
        }
        
        // Look for common categories in text
        $categoryKeywords = [
            'comédia' => 'Comédia', 'comedia' => 'Comédia',
            'música' => 'Música', 'musica' => 'Música', 'concerto' => 'Música',
            'teatro' => 'Teatro',
            'dança' => 'Dança', 'danca' => 'Dança',
            'arte' => 'Arte',
            'cinema' => 'Cinema',
            'conferência' => 'Conferência', 'conferencia' => 'Conferência',
            'espetáculo' => 'Espetáculo', 'espetaculo' => 'Espetáculo'
        ];
        
        $textLower = strtolower($text);
        foreach ($categoryKeywords as $keyword => $category) {
            if (strpos($textLower, $keyword) !== false) {
                return $category;
            }
        }
        
        return 'Espetáculo'; // Default category
    }
    
    private function extractUrl($xpath, $eventNode) {
        $linkSelectors = [
            './/a[contains(text(), "info")]/@href',
            './/a[contains(@class, "btn")]/@href',
            './/a[1]/@href'
        ];
        
        foreach ($linkSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                if ($href) {
                    // Make absolute URL if needed
                    if (strpos($href, 'http') !== 0) {
                        if (strpos($href, '/') === 0) {
                            return 'https://www.forumbraga.com' . $href;
                        } else {
                            return 'https://www.forumbraga.com/' . $href;
                        }
                    }
                    return $href;
                }
            }
        }
        
        return 'https://www.forumbraga.com/Agenda/Programacao';
    }
    
    private function extractDescription($xpath, $eventNode) {
        // Look for description in various elements
        $descSelectors = [
            './/p[position()=1]',
            './/*[contains(@class, "description")]',
            './/*[contains(@class, "desc")]',
            './/*[contains(@class, "content")]'
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
    
    private function splitCategories($categoryString) {
        if (!$categoryString) {
            return ['Espetáculo'];
        }
        
        // Split by common separators
        $categories = preg_split('/\s*[\/\|,]\s*|\s+e\s+|\s+and\s+/i', $categoryString);
        
        $normalizedCategories = [];
        foreach ($categories as $category) {
            $category = trim($category);
            if (!empty($category)) {
                $normalizedCategories[] = $this->normalizeCategory($category);
            }
        }
        
        return empty($normalizedCategories) ? ['Espetáculo'] : array_unique($normalizedCategories);
    }
    
    private function normalizeCategory($category) {
        $category = trim(strtolower($category));
        
        $categoryMap = [
            'música' => 'Música', 'musica' => 'Música', 'music' => 'Música',
            'teatro' => 'Teatro', 'theatre' => 'Teatro',
            'dança' => 'Dança', 'danca' => 'Dança', 'dance' => 'Dança',
            'cinema' => 'Cinema', 'film' => 'Cinema',
            'comédia' => 'Comédia', 'comedia' => 'Comédia', 'comedy' => 'Comédia',
            'arte' => 'Arte', 'art' => 'Arte',
            'conferência' => 'Conferência', 'conferencia' => 'Conferência',
            'espetáculo' => 'Espetáculo', 'espetaculo' => 'Espetáculo',
            'show' => 'Espetáculo'
        ];
        
        return $categoryMap[$category] ?? ucfirst($category);
    }
    
    private function isValidImageUrl($url) {
        if (!$url) return false;
        
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
        $lowerUrl = strtolower($url);
        
        foreach ($imageExtensions as $ext) {
            if (strpos($lowerUrl, '.' . $ext) !== false) {
                return true;
            }
        }
        
        // Also allow common CMS image URLs
        if (strpos($lowerUrl, '/image') !== false || 
            strpos($lowerUrl, '/img') !== false ||
            strpos($lowerUrl, '/upload') !== false ||
            strpos($lowerUrl, '/media') !== false) {
            return true;
        }
        
        return false;
    }
}
?>