<?php
class ConservatorioScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            // Use WordPress REST API to fetch MEC events
            $events = $this->fetchEventsFromAPI();
            
            foreach ($events as $event) {
                if ($this->saveEvent(
                    $event['title'], 
                    $event['description'], 
                    $event['date'], 
                    $event['category'], 
                    $event['image'],
                    $event['url'],
                    'Conservatório de Música Calouste Gulbenkian',
                    $event['start_date'],
                    $event['end_date']
                )) {
                    $eventsScraped++;
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
    
    private function fetchEventsFromAPI() {
        $events = [];
        
        // WordPress REST API endpoint for MEC events with embedded media
        $apiUrl = 'https://conservatoriodebraga.pt/wp-json/wp/v2/mec-events?per_page=100&_embed';
        
        $response = $this->fetchUrl($apiUrl);
        if (!$response) {
            throw new Exception('Failed to fetch events from API');
        }
        
        $data = json_decode($response, true);
        if (!$data || !is_array($data)) {
            throw new Exception('Invalid API response format');
        }
        
        foreach ($data as $apiEvent) {
            $event = $this->parseApiEvent($apiEvent);
            if ($event) {
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function parseApiEvent($apiEvent) {
        // Extract basic information
        $title = $apiEvent['title']['rendered'] ?? null;
        $content = $apiEvent['content']['rendered'] ?? '';
        $excerpt = $apiEvent['excerpt']['rendered'] ?? '';
        $eventUrl = $apiEvent['link'] ?? null;
        
        // Extract featured image from embedded media
        $image = $this->extractFeaturedImage($apiEvent);
        
        // Extract dates from content
        $dateInfo = $this->extractDateFromContent($content . ' ' . $excerpt);
        
        // Only process June 2025 events
        if (!$dateInfo['date'] || !$this->isJune2025Event($dateInfo['date'])) {
            return null;
        }
        
        // Extract category
        $category = 'Música';
        if (!empty($apiEvent['mec_category'])) {
            $category = $this->getCategoryName($apiEvent['mec_category'][0]);
        }
        
        // Clean up description
        $description = $this->cleanDescription($content, $excerpt);
        
        return [
            'title' => $title,
            'description' => $description,
            'date' => $dateInfo['date'],
            'start_date' => $dateInfo['start_date'],
            'end_date' => $dateInfo['end_date'],
            'category' => $category,
            'image' => $image,
            'url' => $eventUrl
        ];
    }
    
    private function extractDateFromContent($text) {
        // Portuguese months
        $months = [
            'janeiro' => '01', 'fevereiro' => '02', 'março' => '03', 'abril' => '04',
            'maio' => '05', 'junho' => '06', 'julho' => '07', 'agosto' => '08',
            'setembro' => '09', 'outubro' => '10', 'novembro' => '11', 'dezembro' => '12',
            'jan' => '01', 'fev' => '02', 'mar' => '03', 'abr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'ago' => '08',
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
        ];
        
        // Clean HTML tags from text
        $cleanText = strip_tags($text);
        
        // Look for specific June dates: "25 de junho", "27 de junho | 21:00"
        if (preg_match('/(\d{1,2})\s+de\s+junho[\s|]*(\d{1,2}:\d{2}|\d{1,2}h\d{0,2})?/i', $cleanText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $time = '19:30:00'; // Default time
            
            if (!empty($matches[2])) {
                if (strpos($matches[2], 'h') !== false) {
                    // Format: "21h00" or "21h"
                    $timeParts = explode('h', $matches[2]);
                    $hour = str_pad($timeParts[0], 2, '0', STR_PAD_LEFT);
                    $minute = !empty($timeParts[1]) ? str_pad($timeParts[1], 2, '0', STR_PAD_LEFT) : '00';
                    $time = "$hour:$minute:00";
                } elseif (strpos($matches[2], ':') !== false) {
                    // Format: "21:00"
                    $time = $matches[2] . ':00';
                }
            }
            
            $parsedDate = "2025-06-$day $time";
            
            return [
                'date' => $parsedDate,
                'start_date' => $parsedDate,
                'end_date' => $parsedDate,
                'raw' => $matches[0]
            ];
        }
        
        // Look for general date pattern with June
        if (preg_match('/(\d{1,2})\s+(junho|jun)[\s,]*(\d{4})?/i', $cleanText, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $year = !empty($matches[3]) ? $matches[3] : '2025';
            
            // Only process 2025 events
            if ($year === '2025') {
                $parsedDate = "$year-06-$day 19:30:00";
                
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
    
    private function isJune2025Event($dateString) {
        if (!$dateString) return false;
        
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        if (!$date) return false;
        
        return $date->format('Y-m') === '2025-06';
    }
    
    private function getCategoryName($categoryId) {
        // Map common MEC category IDs to readable names
        $categoryMap = [
            52 => 'Concertos',
            // Add more mappings as needed
        ];
        
        return $categoryMap[$categoryId] ?? 'Música';
    }
    
    private function cleanDescription($content, $excerpt) {
        // Use excerpt if available, otherwise clean content
        $text = !empty($excerpt) ? $excerpt : $content;
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Limit length
        if (strlen($text) > 300) {
            $text = substr($text, 0, 300) . '...';
        }
        
        return $text ?: 'Evento musical no Conservatório de Música Calouste Gulbenkian';
    }
    
    private function extractFeaturedImage($apiEvent) {
        // Try multiple sources for the featured image
        
        // 1. First try jetpack_featured_media_url (if available)
        if (!empty($apiEvent['jetpack_featured_media_url'])) {
            return $apiEvent['jetpack_featured_media_url'];
        }
        
        // 2. Try embedded featured media
        if (!empty($apiEvent['_embedded']['wp:featuredmedia'][0]['source_url'])) {
            return $apiEvent['_embedded']['wp:featuredmedia'][0]['source_url'];
        }
        
        // 3. Try different sizes from embedded media
        if (!empty($apiEvent['_embedded']['wp:featuredmedia'][0]['media_details']['sizes'])) {
            $sizes = $apiEvent['_embedded']['wp:featuredmedia'][0]['media_details']['sizes'];
            
            // Prefer medium or large size
            if (!empty($sizes['large']['source_url'])) {
                return $sizes['large']['source_url'];
            }
            if (!empty($sizes['medium']['source_url'])) {
                return $sizes['medium']['source_url'];
            }
            if (!empty($sizes['full']['source_url'])) {
                return $sizes['full']['source_url'];
            }
        }
        
        // 4. Fallback to featured_media ID and construct URL
        if (!empty($apiEvent['featured_media']) && $apiEvent['featured_media'] > 0) {
            // Try to fetch the media details separately if needed
            return $this->getMediaUrl($apiEvent['featured_media']);
        }
        
        return null;
    }
    
    private function getMediaUrl($mediaId) {
        try {
            $mediaUrl = "https://conservatoriodebraga.pt/wp-json/wp/v2/media/{$mediaId}";
            $response = $this->fetchUrl($mediaUrl);
            
            if ($response) {
                $mediaData = json_decode($response, true);
                if (!empty($mediaData['source_url'])) {
                    return $mediaData['source_url'];
                }
            }
        } catch (Exception $e) {
            // Ignore errors and return null
        }
        
        return null;
    }
}