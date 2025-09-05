<?php
class MeetupScraper extends BaseScraper {
    
    public function scrape() {
        $eventsScraped = 0;
        $errors = [];
        
        try {
            // URL for Braga in-person events within 5 miles (10km)
            $url = 'https://www.meetup.com/pt-BR/find/?location=pt--Braga&source=EVENTS&eventType=inPerson&distance=fiveMiles';
            
            $html = $this->fetchUrl($url);
            if (!$html) {
                return ['error' => 'Failed to fetch Meetup page'];
            }
            
            // Extract embedded JSON data from Next.js
            $jsonData = $this->extractNextJSData($html);
            if (!$jsonData) {
                return ['error' => 'Failed to extract event data from page'];
            }
            
            // Parse events from the embedded data
            $events = $this->parseEventsFromData($jsonData);
            
            foreach ($events as $event) {
                if ($this->saveEvent(
                    $event['title'], 
                    $event['description'], 
                    $event['date'], 
                    $event['category'], 
                    $event['image'],
                    $event['url'],
                    $event['location'],
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
    
    private function extractNextJSData($html) {
        // Look for the __NEXT_DATA__ script tag
        if (preg_match('/__NEXT_DATA__"\s*type="application\/json">(.+?)<\/script>/s', $html, $matches)) {
            $jsonString = $matches[1];
            return json_decode($jsonString, true);
        }
        
        return null;
    }
    
    private function parseEventsFromData($jsonData) {
        $events = [];
        
        if (!isset($jsonData['props']['pageProps']['__APOLLO_STATE__'])) {
            return $events;
        }
        
        $apolloState = $jsonData['props']['pageProps']['__APOLLO_STATE__'];
        
        // Find recommended events in ROOT_QUERY
        $recommendedEventsQuery = null;
        if (isset($apolloState['ROOT_QUERY'])) {
            foreach ($apolloState['ROOT_QUERY'] as $key => $data) {
                if (strpos($key, 'recommendedEvents') === 0) {
                    $recommendedEventsQuery = $data;
                    break;
                }
            }
        }
        
        if (!$recommendedEventsQuery || !isset($recommendedEventsQuery['edges'])) {
            return $events;
        }
        
        // Process each event
        foreach ($recommendedEventsQuery['edges'] as $edge) {
            // Get the edge reference first
            $edgeRef = $edge['__ref'] ?? null;
            if (!$edgeRef) continue;
            
            // Get the edge data which contains the actual event reference
            $edgeData = $apolloState[$edgeRef] ?? null;
            if (!$edgeData || !isset($edgeData['node'])) continue;
            
            // Get the actual event reference
            $eventRef = $edgeData['node']['__ref'] ?? null;
            if (!$eventRef) continue;
            
            // Get event data from Apollo state
            $eventData = $apolloState[$eventRef] ?? null;
            if (!$eventData) continue;
            
            $event = $this->parseEvent($eventData, $apolloState);
            if ($event) {
                $events[] = $event;
            }
        }
        
        return $events;
    }
    
    private function parseEvent($eventData, $apolloState) {
        // Extract basic event information
        $title = $eventData['title'] ?? null;
        $description = $eventData['description'] ?? '';
        $eventUrl = $eventData['eventUrl'] ?? null;
        $dateTime = $eventData['dateTime'] ?? null;
        $eventType = $eventData['eventType'] ?? '';
        
        if (!$title || !$dateTime) {
            return null;
        }
        
        // Parse date
        $parsedDate = $this->parseDateTime($dateTime);
        if (!$parsedDate) {
            return null;
        }
        
        // Extract venue information
        $location = 'Braga, Portugal'; // Default location
        if (isset($eventData['venue']['__ref'])) {
            $venueRef = $eventData['venue']['__ref'];
            $venueData = $apolloState[$venueRef] ?? null;
            if ($venueData) {
                $location = $this->formatLocation($venueData);
            }
        }
        
        // Extract group information for category
        $category = 'Meetup';
        if (isset($eventData['group']['__ref'])) {
            $groupRef = $eventData['group']['__ref'];
            $groupData = $apolloState[$groupRef] ?? null;
            if ($groupData && isset($groupData['name'])) {
                $category = $this->categorizeEvent($groupData['name'], $description);
            }
        }
        
        // Extract image
        $image = null;
        if (isset($eventData['featuredEventPhoto']['__ref'])) {
            $photoRef = $eventData['featuredEventPhoto']['__ref'];
            $photoData = $apolloState[$photoRef] ?? null;
            if ($photoData && isset($photoData['highResUrl'])) {
                $image = $photoData['highResUrl'];
            }
        }
        
        // Clean and limit description
        $cleanDescription = $this->cleanDescription($description);
        
        return [
            'title' => $title,
            'description' => $cleanDescription,
            'date' => $parsedDate,
            'start_date' => $parsedDate,
            'end_date' => $parsedDate,
            'category' => $category,
            'image' => $image,
            'url' => $eventUrl,
            'location' => $location
        ];
    }
    
    private function parseDateTime($dateTimeString) {
        try {
            // Parse ISO 8601 datetime: "2025-09-10T18:00:00+01:00"
            $dateTime = new DateTime($dateTimeString);
            return $dateTime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return null;
        }
    }
    
    private function formatLocation($venueData) {
        $parts = [];
        
        if (isset($venueData['name']) && $venueData['name']) {
            $parts[] = $venueData['name'];
        }
        
        if (isset($venueData['address']) && $venueData['address']) {
            $parts[] = $venueData['address'];
        }
        
        if (isset($venueData['city']) && $venueData['city']) {
            $parts[] = $venueData['city'];
        }
        
        if (empty($parts)) {
            return 'Braga, Portugal';
        }
        
        return implode(', ', $parts);
    }
    
    private function categorizeEvent($groupName, $description) {
        $text = strtolower($groupName . ' ' . $description);
        
        // Technology and Programming
        if (preg_match('/\b(elixir|beam|programming|developer|tech|javascript|python|php|ruby|java|coding|software|web|api|react|vue|angular|docker|kubernetes)\b/', $text)) {
            return 'Tecnologia';
        }
        
        // QA and Testing
        if (preg_match('/\b(qa|quality assurance|testing|browserstack|test automation)\b/', $text)) {
            return 'Tecnologia';
        }
        
        // Business and Career
        if (preg_match('/\b(business|career|networking|professional|entrepreneur|startup|marketing)\b/', $text)) {
            return 'Negócios';
        }
        
        // Social and Networking
        if (preg_match('/\b(social|friends|networking|conversation|talk|discuss|meet|coffee)\b/', $text)) {
            return 'Social';
        }
        
        // Fitness and Sports
        if (preg_match('/\b(fitness|sport|running|yoga|gym|exercise|workout|hiking|cycling)\b/', $text)) {
            return 'Desporto';
        }
        
        // Languages and Culture
        if (preg_match('/\b(language|english|portuguese|spanish|french|culture|international)\b/', $text)) {
            return 'Cultura';
        }
        
        // Arts and Creativity
        if (preg_match('/\b(art|music|creative|design|photography|painting|writing)\b/', $text)) {
            return 'Arte';
        }
        
        // Games and Hobbies
        if (preg_match('/\b(game|gaming|hobby|board games|rpg|tabletop)\b/', $text)) {
            return 'Entretenimento';
        }
        
        return 'Meetup';
    }
    
    private function cleanDescription($description) {
        // Remove HTML tags
        $text = strip_tags($description);
        
        // Clean up whitespace and special characters
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Remove markdown-style formatting
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text); // Bold
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text); // Italic
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // Links
        
        // Limit length
        if (strlen($text) > 500) {
            $text = substr($text, 0, 500) . '...';
        }
        
        return $text ?: 'Evento Meetup em Braga';
    }
}