<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/GnrationScraper.php';

echo "Updating All Events with Images\n";
echo "===============================\n\n";

$db = new Database();

// Get all events without images
$stmt = $db->getConnection()->query("
    SELECT id, title, url 
    FROM events 
    WHERE source_id = (SELECT id FROM sources WHERE scraper_class = 'GnrationScraper') 
    AND (image IS NULL OR image = '')
    ORDER BY id
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "✅ All events already have images!\n";
    
    // Show count of events with images
    $stmt = $db->getConnection()->query("
        SELECT COUNT(*) as with_images 
        FROM events 
        WHERE image IS NOT NULL AND image != ''
    ");
    $withImages = $stmt->fetch(PDO::FETCH_ASSOC)['with_images'];
    echo "📊 Events with images: $withImages\n";
    exit;
}

echo "📊 Found " . count($events) . " events without images.\n";
echo "🔄 Starting image extraction...\n\n";

// Create scraper instance
$scraper = new GnrationScraper($db, 1);

// Use reflection to access private method
$reflection = new ReflectionClass($scraper);
$extractImageMethod = $reflection->getMethod('extractEventImage');
$extractImageMethod->setAccessible(true);

$updated = 0;
$failed = 0;

foreach ($events as $i => $event) {
    $num = $i + 1;
    echo "[$num/" . count($events) . "] " . substr($event['title'], 0, 50) . "...\n";
    
    try {
        $image = $extractImageMethod->invoke($scraper, $event['url']);
        
        if ($image) {
            // Update the event with the image
            $updateStmt = $db->getConnection()->prepare("UPDATE events SET image = ? WHERE id = ?");
            if ($updateStmt->execute([$image, $event['id']])) {
                echo "   ✅ Image: " . substr($image, strrpos($image, '/') + 1) . "\n";
                $updated++;
            } else {
                echo "   ❌ Database update failed\n";
                $failed++;
            }
        } else {
            echo "   ⚪ No image found\n";
        }
        
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
    
    // Be respectful to the server - small delay
    if ($num % 5 == 0) {
        echo "   💤 Brief pause...\n";
        sleep(2);
    } else {
        usleep(500000); // 0.5 second delay
    }
}

echo "\n📊 Summary:\n";
echo "✅ Updated: $updated events\n";
echo "❌ Failed: $failed events\n";
echo "📁 Total events with images now: ";

$stmt = $db->getConnection()->query("
    SELECT COUNT(*) as with_images 
    FROM events 
    WHERE image IS NOT NULL AND image != ''
");
$withImages = $stmt->fetch(PDO::FETCH_ASSOC)['with_images'];
echo "$withImages\n";

echo "\n🎉 Image update completed!\n";