<?php
class Event {
    private $db;
    
    public function __construct(Database $database) {
        $this->db = $database->getConnection();
    }
    
    public function getEventsByMonth($year, $month, $location = '', $category = '', $limit = null, $offset = 0, $search = '') {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $sql = "SELECT *,
                       COALESCE(start_date, event_date) as start_date,
                       COALESCE(end_date, event_date) as end_date
                FROM events
                WHERE DATE(event_date) BETWEEN ? AND ?
                AND (hidden IS NULL OR hidden = 0)";
        $params = [$startDate, $endDate];

        if ($location) {
            $sql .= " AND location = ?";
            $params[] = $location;
        }

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if ($search) {
            $sql .= " AND (title LIKE ? OR location LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY featured DESC, event_date ASC";
        
        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getEventsCountByMonth($year, $month, $location = '', $category = '', $search = '') {
        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate = date('Y-m-t', strtotime($startDate));

        $sql = "SELECT COUNT(*) FROM events
                WHERE DATE(event_date) BETWEEN ? AND ?
                AND (hidden IS NULL OR hidden = 0)";
        $params = [$startDate, $endDate];

        if ($location) {
            $sql .= " AND location = ?";
            $params[] = $location;
        }

        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }

        if ($search) {
            $sql .= " AND (title LIKE ? OR location LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    public function getEventsByDateRange($fromDate, $toDate, $location = '', $category = '', $limit = null, $offset = 0, $search = '') {
        $sql = "SELECT *,
                       COALESCE(start_date, event_date) as start_date,
                       COALESCE(end_date, event_date) as end_date
                FROM events
                WHERE DATE(event_date) BETWEEN ? AND ?
                AND (hidden IS NULL OR hidden = 0)";
        $params = [$fromDate, $toDate];

        if ($location) {
            $sql .= " AND location = ?";
            $params[] = $location;
        }
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($search) {
            $sql .= " AND (title LIKE ? OR location LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY featured DESC, event_date ASC";

        if ($limit) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEventsCountByDateRange($fromDate, $toDate, $location = '', $category = '', $search = '') {
        $sql = "SELECT COUNT(*) FROM events
                WHERE DATE(event_date) BETWEEN ? AND ?
                AND (hidden IS NULL OR hidden = 0)";
        $params = [$fromDate, $toDate];

        if ($location) {
            $sql .= " AND location = ?";
            $params[] = $location;
        }
        if ($category) {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($search) {
            $sql .= " AND (title LIKE ? OR location LIKE ?)";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function getAllCategories() {
        $stmt = $this->db->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != '' ORDER BY category");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function getAllLocations() {
        $stmt = $this->db->query("SELECT DISTINCT location FROM events WHERE location IS NOT NULL AND location != '' ORDER BY location");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function createEvent($title, $description, $eventDate, $category, $image = null, $url = null, $sourceId = null, $location = null, $createdBy = null, $startDate = null, $endDate = null) {
        $sql = "INSERT INTO events (title, description, event_date, start_date, end_date, category, location, image, url, source_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$title, $description, $eventDate, $startDate ?: $eventDate, $endDate ?: $eventDate, $category, $location, $image, $url, $sourceId, $createdBy]);
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
    
    public function toggleHidden($id) {
        $sql = "UPDATE events SET hidden = CASE WHEN hidden = 1 THEN 0 ELSE 1 END WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    public function toggleFeatured($id) {
        $sql = "UPDATE events SET featured = CASE WHEN featured = 1 THEN 0 ELSE 1 END WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    public function setHidden($id, $hidden) {
        $sql = "UPDATE events SET hidden = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$hidden ? 1 : 0, $id]);
    }
    
    public function setFeatured($id, $featured) {
        $sql = "UPDATE events SET featured = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$featured ? 1 : 0, $id]);
    }
}