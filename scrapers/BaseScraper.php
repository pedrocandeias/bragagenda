<?php
abstract class BaseScraper {
    protected $db;
    protected $sourceId;
    
    public function __construct(Database $database, $sourceId) {
        $this->db = $database->getConnection();
        $this->sourceId = $sourceId;
    }
    
    abstract public function scrape();
    
    protected function saveEvent($title, $description, $eventDate, $category, $image = null, $url = null, $location = null, $startDate = null, $endDate = null) {
        // If start/end dates are not provided, use eventDate for both
        if ($startDate === null) {
            $startDate = $eventDate;
        }
        if ($endDate === null) {
            $endDate = $eventDate;
        }
        
        // Generate a unique hash for the event
        $eventHash = $this->generateEventHash($title, $eventDate, $url);
        
        // Check if event already exists by hash first (fastest)
        $stmt = $this->db->prepare("SELECT id FROM events WHERE event_hash = ?");
        $stmt->execute([$eventHash]);
        
        if ($stmt->fetch()) {
            return false; // Event already exists
        }
        
        // Additional check by URL if provided
        if ($url) {
            $stmt = $this->db->prepare("SELECT id FROM events WHERE url = ?");
            $stmt->execute([$url]);
            
            if ($stmt->fetch()) {
                return false; // Event with same URL already exists
            }
        }
        
        // Check for same title and date (broader duplicate check)
        $stmt = $this->db->prepare("
            SELECT id FROM events 
            WHERE title = ? AND DATE(event_date) = DATE(?)
        ");
        $stmt->execute([$title, $eventDate]);
        
        if ($stmt->fetch()) {
            return false; // Event with same title and date already exists
        }
        
        // Save new event with hash, location, and date ranges
        $stmt = $this->db->prepare("INSERT INTO events (title, description, event_date, category, location, image, url, source_id, event_hash, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$title, $description, $eventDate, $category, $location, $image, $url, $this->sourceId, $eventHash, $startDate, $endDate]);
    }
    
    private function generateEventHash($title, $eventDate, $url = null) {
        // Create a unique hash based on title, date, and URL
        $data = strtolower(trim($title)) . '|' . date('Y-m-d', strtotime($eventDate));
        if ($url) {
            $data .= '|' . $url;
        }
        return md5($data);
    }
    
    protected function fetchUrl($url) {
        $context = stream_context_create([
            'http' => [
                'user_agent' => 'Mozilla/5.0 (compatible; BragaAgenda/1.0)',
                'timeout' => 30
            ]
        ]);
        
        return file_get_contents($url, false, $context);
    }
    
    protected function updateLastScraped() {
        $stmt = $this->db->prepare("UPDATE sources SET last_scraped = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$this->sourceId]);
    }
}