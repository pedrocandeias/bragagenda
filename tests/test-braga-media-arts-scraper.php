<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/ScraperManager.php';
require_once __DIR__ . '/../scrapers/BaseScraper.php';
require_once __DIR__ . '/../scrapers/BragaMediaArtsScraper.php';

echo "Testing Braga Media Arts scraper...\n\n";

$db = new Database();

// Get Braga Media Arts source from database
$stmt = $db->getConnection()->prepare("SELECT * FROM sources WHERE scraper_class = 'BragaMediaArtsScraper' LIMIT 1");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    echo "❌ Braga Media Arts source not found. Adding it to the database...\n";
    
    // Add the source to database
    $stmt = $db->getConnection()->prepare("
        INSERT INTO sources (name, url, scraper_class, active) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        'Braga Media Arts',
        'https://www.bragamediaarts.com/pt/agenda/',
        'BragaMediaArtsScraper',
        1
    ]);
    
    $sourceId = $db->getConnection()->lastInsertId();
    echo "✅ Added Braga Media Arts source with ID: $sourceId\n\n";
} else {
    $sourceId = $source['id'];
    echo "✅ Found Braga Media Arts source with ID: $sourceId\n\n";
}

// Test the scraper
$scraper = new BragaMediaArtsScraper($db, $sourceId);
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
    
    if (isset($result['info'])) {
        echo "\nℹ️  INFO: " . $result['info'] . "\n";
    }
    
    if (isset($result['debug_url'])) {
        echo "\n🔍 DEBUG URL: " . $result['debug_url'] . "\n";
    }
    
    if (isset($result['html_sample'])) {
        echo "\n📄 HTML SAMPLE:\n" . $result['html_sample'] . "\n";
    }
}

// Show some recent events from database
echo "\n=== RECENT EVENTS IN DATABASE ===\n";
$stmt = $db->getConnection()->query("
    SELECT title, event_date, category, location, url 
    FROM events 
    WHERE source_id = $sourceId 
    ORDER BY created_at DESC 
    LIMIT 10
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No events found in database.\n";
    echo "\n💡 TROUBLESHOOTING TIPS:\n";
    echo "1. Check if the website is accessible\n";
    echo "2. Verify the HTML structure hasn't changed:\n";
    echo "   - Visit https://www.bragamediaarts.com/pt/agenda/\n";
    echo "   - Check if events are displayed as expected\n";
    echo "3. Look at the page source to verify HTML structure\n";
    echo "4. Check if there are any JavaScript errors preventing content load\n";
} else {
    foreach ($events as $event) {
        echo "📅 " . date('d/m/Y H:i', strtotime($event['event_date'])) . 
             " - " . $event['title'] . 
             ($event['category'] ? " [{$event['category']}]" : "") . 
             ($event['location'] ? " @ {$event['location']}" : "") . 
             "\n";
        if ($event['url']) {
            echo "   🔗 " . $event['url'] . "\n";
        }
    }
}

echo "\nTest completed.\n";