<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/ScraperManager.php';
require_once __DIR__ . '/../scrapers/BaseScraper.php';
require_once __DIR__ . '/../scrapers/CentesimaScraper.php';

echo "Testing Centésima scraper...\n\n";

$db = new Database();

// Get Centésima source from database
$stmt = $db->getConnection()->prepare("SELECT * FROM sources WHERE scraper_class = 'CentesimaScraper' LIMIT 1");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    echo "❌ Centésima source not found. Run setup/setup-centesima-scraper.php first.\n";
    exit(1);
}

// Test the scraper
$scraper = new CentesimaScraper($db, $source['id']);
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
    SELECT title, event_date, category, url 
    FROM events 
    WHERE source_id = {$source['id']} 
    ORDER BY created_at DESC 
    LIMIT 5
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No events found in database.\n";
    echo "\n💡 TROUBLESHOOTING TIPS:\n";
    echo "1. Centésima uses Angular - content loads dynamically\n";
    echo "2. Check browser developer tools for API endpoints:\n";
    echo "   - Open https://centesima.com/agenda in browser\n";
    echo "   - Open Developer Tools (F12)\n";
    echo "   - Go to Network tab\n";
    echo "   - Refresh page and look for API calls\n";
    echo "3. Look for endpoints like:\n";
    echo "   - /api/events\n";
    echo "   - /api/agenda\n";
    echo "   - /wp-json/wp/v2/events (if WordPress)\n";
    echo "4. Update the scraper with the correct API endpoint\n";
} else {
    foreach ($events as $event) {
        echo "📅 " . date('d/m/Y H:i', strtotime($event['event_date'])) . 
             " - " . $event['title'] . 
             ($event['category'] ? " [{$event['category']}]" : "") . 
             "\n";
    }
}

echo "\nTest completed.\n";