<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/MuseuDDiogoScraper.php';

echo "Testing Museu D. Diogo de Sousa scraper...\n\n";

$db = new Database();

// Get Museu D. Diogo de Sousa source from database
$stmt = $db->getConnection()->prepare("SELECT * FROM sources WHERE scraper_class = 'MuseuDDiogoScraper' LIMIT 1");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    echo "❌ Museu D. Diogo de Sousa source not found in database.\n";
    exit(1);
}

// Test the scraper
$scraper = new MuseuDDiogoScraper($db, $source['id']);
$result = $scraper->scrape();

echo "=== SCRAPER RESULTS ===\n";
if (isset($result['error'])) {
    echo "❌ ERROR: " . $result['error'] . "\n";
} else {
    echo "✅ SUCCESS!\n";
    echo "Events scraped: " . ($result['events_scraped'] ?? 0) . "\n";
    
    if (!empty($result['errors'])) {
        echo "\nWarnings/Errors during processing:\n";
        foreach ($result['errors'] as $error) {
            echo "⚠️  " . $error . "\n";
        }
    }
}

// Show some recent events from database
echo "\n=== RECENT EVENTS IN DATABASE ===\n";
$stmt = $db->getConnection()->query("
    SELECT title, event_date, category, url 
    FROM events 
    WHERE source_id = {$source['id']} 
    ORDER BY created_at DESC 
    LIMIT 5
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No events found in database.\n";
} else {
    foreach ($events as $event) {
        echo "📅 " . date('d/m/Y H:i', strtotime($event['event_date'])) . 
             " - " . $event['title'] . 
             ($event['category'] ? " [{$event['category']}]" : "") . 
             "\n";
    }
}

echo "\nTest completed.\n";