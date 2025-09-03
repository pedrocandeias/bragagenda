<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/GnrationScraper.php';

echo "Testing Image Extraction from Gnration Events\n";
echo "=============================================\n\n";

$db = new Database();

// Get a few events to test image extraction
$stmt = $db->getConnection()->query("
    SELECT id, title, url 
    FROM events 
    WHERE source_id = (SELECT id FROM sources WHERE scraper_class = 'GnrationScraper') 
    AND (image IS NULL OR image = '')
    LIMIT 3
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "No events without images found for testing.\n";
    exit;
}

echo "Testing image extraction for " . count($events) . " events...\n\n";

// Create scraper instance to use the image extraction method
$scraper = new GnrationScraper($db, 1);

// Use reflection to access private method for testing
$reflection = new ReflectionClass($scraper);
$extractImageMethod = $reflection->getMethod('extractEventImage');
$extractImageMethod->setAccessible(true);

foreach ($events as $i => $event) {
    echo "Event " . ($i + 1) . ": " . $event['title'] . "\n";
    echo "URL: " . $event['url'] . "\n";
    
    // Test image extraction
    $image = $extractImageMethod->invoke($scraper, $event['url']);
    
    if ($image) {
        echo "✅ Image found: " . $image . "\n";
        
        // Update the event with the image
        $updateStmt = $db->getConnection()->prepare("UPDATE events SET image = ? WHERE id = ?");
        if ($updateStmt->execute([$image, $event['id']])) {
            echo "✅ Event updated with image\n";
        } else {
            echo "❌ Failed to update event\n";
        }
    } else {
        echo "❌ No image found\n";
    }
    
    echo "\n";
    
    // Small delay to be respectful to the server
    sleep(1);
}

echo "Image extraction test completed.\n";