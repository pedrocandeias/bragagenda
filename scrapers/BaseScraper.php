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
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->maybeUpdateImage($row['id'], $image);
            return false;
        }

        // Additional check by URL if provided
        if ($url) {
            $stmt = $this->db->prepare("SELECT id, event_date FROM events WHERE url = ?");
            $stmt->execute([$url]);
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->maybeUpdateImage($row['id'], $image);
                $this->maybeUpdateDate($row['id'], $row['event_date'], $eventDate, $startDate, $endDate, $title, $url);
                return false;
            }
        }

        // Check for same title and date (broader duplicate check)
        $stmt = $this->db->prepare("
            SELECT id FROM events
            WHERE title = ? AND DATE(event_date) = DATE(?)
        ");
        $stmt->execute([$title, $eventDate]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->maybeUpdateImage($row['id'], $image);
            return false;
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
        
        return @file_get_contents($url, false, $context);
    }
    
    protected function updateLastScraped() {
        $stmt = $this->db->prepare("UPDATE sources SET last_scraped = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$this->sourceId]);
    }

    private function maybeUpdateDate($eventId, $storedDate, $newDate, $newStart, $newEnd, $title, $url) {
        if (!$newDate) return;
        if (date('Y-m-d', strtotime($storedDate)) === date('Y-m-d', strtotime($newDate))) return;

        $newHash = $this->generateEventHash($title, $newDate, $url);
        $this->db->prepare(
            "UPDATE events SET event_date = ?, start_date = ?, end_date = ?, event_hash = ? WHERE id = ?"
        )->execute([$newDate, $newStart ?: $newDate, $newEnd ?: $newDate, $newHash, $eventId]);
    }

    private function maybeUpdateImage($eventId, $newImage) {
        if (!$newImage || strpos($newImage, 'uploads/') !== 0) return;
        // Only overwrite if the stored image is still a remote URL or empty
        $this->db->prepare(
            "UPDATE events SET image = ? WHERE id = ? AND (image IS NULL OR image NOT LIKE 'uploads/%')"
        )->execute([$newImage, $eventId]);
    }

    protected function downloadImage($url) {
        if (!$url) return null;

        $uploadDir  = __DIR__ . '/../uploads/scraped/';
        $publicBase = 'uploads/scraped/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Derive extension from URL, default to jpg
        $urlPath = parse_url($url, PHP_URL_PATH) ?: '';
        $ext     = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            $ext = 'jpg';
        }

        $filename  = md5($url) . '.' . $ext;
        $localPath = $uploadDir . $filename;

        // Return cached file immediately
        if (file_exists($localPath)) {
            return $publicBase . $filename;
        }

        // Try original URL, then .jpg fallback if URL uses another extension
        $imageData = @file_get_contents($url, false, stream_context_create([
            'http' => ['user_agent' => 'Mozilla/5.0 (compatible; BragaAgenda/1.0)', 'timeout' => 15]
        ]));

        if (!$imageData && $ext !== 'jpg') {
            $fallbackUrl = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.jpg', $url);
            $imageData   = @file_get_contents($fallbackUrl, false, stream_context_create([
                'http' => ['user_agent' => 'Mozilla/5.0 (compatible; BragaAgenda/1.0)', 'timeout' => 15]
            ]));
            if ($imageData) {
                $filename  = md5($url) . '.jpg';
                $localPath = $uploadDir . $filename;
            }
        }

        if (!$imageData) return null;

        file_put_contents($localPath, $imageData);
        return $publicBase . $filename;
    }
}