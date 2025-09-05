<?php
class MuseuDDiogoScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            $url = "https://www.museuddiogodesousa.gov.pt/todos-eventos/";
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
            
            // Look for event blocks - the events are in div.events-block containers
            $eventNodes = $xpath->query('//div[@class="events-block"]');
            
            foreach ($eventNodes as $eventNode) {
                try {
                    $title = $this->extractEventTitle($xpath, $eventNode);
                    $eventUrl = $this->extractEventUrl($xpath, $eventNode);
                    $dateInfo = $this->extractDateInfo($xpath, $eventNode);
                    $description = $this->extractDescription($xpath, $eventNode);
                    $image = $this->extractEventImage($xpath, $eventNode);
                    
                    if ($title && $dateInfo['date']) {
                        // Save event
                        if ($this->saveEvent(
                            $title, 
                            $description ?: 'Evento no Museu D. Diogo de Sousa', 
                            $dateInfo['date'], 
                            'Cultura', 
                            $image,
                            $eventUrl,
                            'Museu D. Diogo de Sousa',
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
        // Look for title in h3 tag within events-content
        $titleNodes = $xpath->query('.//h3/a', $eventNode);
        if ($titleNodes->length > 0) {
            return trim($titleNodes->item(0)->textContent);
        }
        
        // Fallback to h3 text directly
        $titleNodes = $xpath->query('.//h3', $eventNode);
        if ($titleNodes->length > 0) {
            return trim($titleNodes->item(0)->textContent);
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        // Look for the event URL in the "Ver detalhes" link or h3 link
        $linkNodes = $xpath->query('.//h3/a/@href | .//a[contains(text(), "Ver detalhes")]/@href', $eventNode);
        if ($linkNodes->length > 0) {
            $href = $linkNodes->item(0)->nodeValue;
            // Make absolute URL
            if (strpos($href, 'http') === 0) {
                return $href;
            } elseif (strpos($href, '/') === 0) {
                return 'https://www.museuddiogodesousa.gov.pt' . $href;
            } else {
                return 'https://www.museuddiogodesousa.gov.pt/' . $href;
            }
        }
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // Look for date in the post-date div - format: "05 Setembro 2025-07 Setembro 2025"
        $dateNodes = $xpath->query('.//div[@class="post-date"]', $eventNode);
        if ($dateNodes->length > 0) {
            $fullText = $dateNodes->item(0)->textContent;
            
            // Portuguese months mapping
            $months = [
                'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
                'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
                'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',
                'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
                'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
                'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
            ];
            
            // Look for date ranges: "05 Setembro 2025-07 Setembro 2025"
            if (preg_match('/(\d{1,2})\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)\s+(\d{4})-(\d{1,2})\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)\s+(\d{4})/i', $fullText, $matches)) {
                $startDay = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $startMonthName = strtolower($matches[2]);
                $startYear = $matches[3];
                $endDay = str_pad($matches[4], 2, '0', STR_PAD_LEFT);
                $endMonthName = strtolower($matches[5]);
                $endYear = $matches[6];
                
                $startMonth = $months[$startMonthName] ?? '01';
                $endMonth = $months[$endMonthName] ?? '01';
                
                $startDate = "$startYear-$startMonth-$startDay 14:00:00";
                $endDate = "$endYear-$endMonth-$endDay 14:00:00";
                
                return [
                    'date' => $startDate,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'raw' => $matches[0]
                ];
            }
            
            // Look for single date patterns: "05 Setembro 2025"
            if (preg_match('/(\d{1,2})\s+(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)\s+(\d{4})/i', $fullText, $matches)) {
                $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                $monthName = strtolower($matches[2]);
                $year = $matches[3];
                
                $month = $months[$monthName] ?? '01';
                $parsedDate = "$year-$month-$day 14:00:00";
                
                return [
                    'date' => $parsedDate,
                    'start_date' => $parsedDate,
                    'end_date' => $parsedDate,
                    'raw' => $matches[0]
                ];
            }
        }
        
        return ['date' => null, 'start_date' => null, 'end_date' => null, 'raw' => ''];
    }
    
    private function extractDescription($xpath, $eventNode) {
        // Look for description in the paragraph after the h3
        $descNodes = $xpath->query('.//div[@class="events-content"]//p', $eventNode);
        if ($descNodes->length > 0) {
            $desc = trim($descNodes->item(0)->textContent);
            if (strlen($desc) > 20 && strlen($desc) < 1000) {
                return $desc;
            }
        }
        
        return null;
    }
    
    private function extractEventImage($xpath, $eventNode) {
        // Look for images in the img-block section
        $imgNodes = $xpath->query('.//div[contains(@class, "img-block")]//img', $eventNode);
        
        if ($imgNodes->length > 0) {
            $imgSrc = $imgNodes->item(0)->getAttribute('src');
            
            if ($imgSrc) {
                // Make absolute URL
                if (strpos($imgSrc, 'http') === 0) {
                    return $imgSrc;
                } elseif (strpos($imgSrc, '//') === 0) {
                    return 'https:' . $imgSrc;
                } elseif (strpos($imgSrc, '/') === 0) {
                    return 'https://www.museuddiogodesousa.gov.pt' . $imgSrc;
                } else {
                    return 'https://www.museuddiogodesousa.gov.pt/' . $imgSrc;
                }
            }
        }
        
        return null;
    }
}