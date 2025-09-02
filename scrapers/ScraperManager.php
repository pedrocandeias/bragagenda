<?php
require_once 'BaseScraper.php';

class ScraperManager {
    private $db;
    
    public function __construct(Database $database) {
        $this->db = $database;
    }
    
    public function runAllScrapers() {
        $sources = $this->getActiveSources();
        $results = [];
        
        foreach ($sources as $source) {
            $results[$source['name']] = $this->runScraper($source);
        }
        
        return $results;
    }
    
    public function runScraper($source) {
        $scraperClass = $source['scraper_class'];
        $scraperFile = __DIR__ . '/' . $scraperClass . '.php';
        
        if (!file_exists($scraperFile)) {
            return ['error' => "Scraper file not found: $scraperFile"];
        }
        
        require_once $scraperFile;
        
        if (!class_exists($scraperClass)) {
            return ['error' => "Scraper class not found: $scraperClass"];
        }
        
        try {
            $scraper = new $scraperClass($this->db, $source['id']);
            $result = $scraper->scrape();
            return ['success' => true, 'result' => $result];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    private function getActiveSources() {
        $stmt = $this->db->getConnection()->query("SELECT * FROM sources WHERE active = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addSource($name, $url, $scraperClass) {
        $stmt = $this->db->getConnection()->prepare("INSERT INTO sources (name, url, scraper_class) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $url, $scraperClass]);
    }
}