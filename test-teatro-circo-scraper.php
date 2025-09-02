<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/TeatroCircoScraper.php';

echo "Testing Teatro Circo scraper...\n\n";

$db = new Database();

// Get Teatro Circo source from database
$stmt = $db->getConnection()->prepare("SELECT * FROM sources WHERE scraper_class = 'TeatroCircoScraper' LIMIT 1");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    echo "❌ Teatro Circo source not found. Run setup-teatro-circo-scraper.php first.\n";
    exit(1);
}

// Test the scraper
$scraper = new TeatroCircoScraper($db, $source['id']);
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
    LIMIT 10
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No events found in database.\n";
} else {
    foreach ($events as $event) {
        echo "🎭 " . date('d/m/Y H:i', strtotime($event['event_date'])) . 
             " - " . substr($event['title'], 0, 50) . 
             ($event['category'] ? " [{$event['category']}]" : "") . 
             "\n";
    }
}

echo "\nTest completed.\n";