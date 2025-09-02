<?php
class Event {
    private $db;
    
    public function __construct(Database $database) {
        $this->db = $database->getConnection();
    }
    
    public function getEventsByMonth($year, $month, $location = '', $category = '') {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));
        
        $sql = "SELECT *, 
                       COALESCE(start_date, event_date) as start_date,
                       COALESCE(end_date, event_date) as end_date
                FROM events 
                WHERE DATE(event_date) BETWEEN ? AND ?";
        $params = [$startDate, $endDate];
        
        if ($location) {
            $sql .= " AND location = ?";
            $params[] = $location;
        }
        
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        
        $sql .= " ORDER BY event_date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllCategories() {
        $stmt = $this->db->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getAllLocations() {
        $stmt = $this->db->query("SELECT DISTINCT location FROM events WHERE location IS NOT NULL AND location != '' ORDER BY location");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function createEvent($title, $description, $eventDate, $category, $image = null, $url = null, $sourceId = null, $location = null) {
        $sql = "INSERT INTO events (title, description, event_date, category, location, image, url, source_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$title, $description, $eventDate, $category, $location, $image, $url, $sourceId]);
    }
    
    public function updateEvent($id, $title, $description, $eventDate, $category, $image = null, $url = null, $location = null) {
        $sql = "UPDATE events SET title = ?, description = ?, event_date = ?, category = ?, location = ?, image = ?, url = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$title, $description, $eventDate, $category, $location, $image, $url, $id]);
    }
    
    public function deleteEvent($id) {
        $stmt = $this->db->prepare("DELETE FROM events WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getEventById($id) {
        $stmt = $this->db->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllEvents() {
        $stmt = $this->db->query("SELECT * FROM events ORDER BY event_date DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}