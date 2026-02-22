<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/BaseScraper.php';
require_once __DIR__ . '/../scrapers/PedroRemyScraper.php';

try {
    // Initialize database
    $database = new Database();
    
    // Check if Pedro Remy source exists, create if not
    $stmt = $database->getConnection()->prepare("SELECT id FROM sources WHERE name = 'Pedro Remy'");
    $stmt->execute();
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$source) {
        echo "Creating Pedro Remy source...\n";
        $stmt = $database->getConnection()->prepare("INSERT INTO sources (name, url, scraper_class) VALUES ('Pedro Remy', 'https://www.pedroremy.com/concertos-em-cartaz', 'PedroRemyScraper')");
        $stmt->execute();
        $sourceId = $database->getConnection()->lastInsertId();
        echo "Created source with ID: $sourceId\n";
    } else {
        $sourceId = $source['id'];
        echo "Using existing Pedro Remy source (ID: $sourceId)\n";
    }
    
    // Test the scraper
    echo "Testing Pedro Remy scraper...\n";
    $scraper = new PedroRemyScraper($database, $sourceId);
    $result = $scraper->scrape();
    
    echo "Scraping completed!\n";
    echo "Events scraped: " . $result['events_scraped'] . "\n";
    
    if (!empty($result['errors'])) {
        echo "Errors encountered:\n";
        foreach ($result['errors'] as $error) {
            echo "- $error\n";
        }
    }
    
    if (isset($result['error'])) {
        echo "Fatal error: " . $result['error'] . "\n";
    }
    
    // Show recently scraped events
    echo "\nRecent events from Pedro Remy:\n";
    $stmt = $database->getConnection()->prepare("
        SELECT title, event_date, category, location, url 
        FROM events 
        WHERE source_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$sourceId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($events as $event) {
        echo "- {$event['title']} ({$event['event_date']}) - {$event['category']} at {$event['location']}\n";
        if ($event['url']) {
            echo "  URL: {$event['url']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}