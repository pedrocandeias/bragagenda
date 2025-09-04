<?php
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/ForumBragaScraper.php';

echo "Testing Forum Braga scraper...\n\n";

$db = new Database();
$scraper = new ForumBragaScraper($db, 'Forum Braga', 'https://www.forumbraga.com/Agenda/Programacao');

echo "=== SCRAPING FORUM BRAGA ===\n";
$result = $scraper->scrape();

if (isset($result['error'])) {
    echo "❌ Error: " . $result['error'] . "\n";
    if (isset($result['debug_info'])) {
        echo "Debug info: " . $result['debug_info'] . "\n";
    }
} else {
    echo "✅ SUCCESS!\n";
    echo "Events scraped: " . $result['events_scraped'] . "\n";
    
    if (!empty($result['errors'])) {
        echo "\n⚠️  Warnings:\n";
        foreach ($result['errors'] as $error) {
            echo "  - " . $error . "\n";
        }
    }
}

// Show recent Forum Braga events from database
echo "\n=== RECENT EVENTS IN DATABASE ===\n";
$stmt = $db->getConnection()->prepare("
    SELECT title, event_date, category 
    FROM events 
    WHERE location = 'Forum Braga' 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute();
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No Forum Braga events found in database.\n";
} else {
    foreach ($events as $event) {
        $date = date('d/m/Y H:i', strtotime($event['event_date']));
        echo "📅 {$date} - {$event['title']} [{$event['category']}]\n";
    }
}

echo "\nTest completed.\n";
?>