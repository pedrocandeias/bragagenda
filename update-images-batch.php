<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/GnrationScraper.php';

$batchSize = isset($argv[1]) ? (int)$argv[1] : 10;

echo "Updating Events with Images (Batch of $batchSize)\n";
echo str_repeat("=", 50) . "\n\n";

$db = new Database();

// Get events without images
$stmt = $db->getConnection()->prepare("
    SELECT id, title, url 
    FROM events 
    WHERE source_id = (SELECT id FROM sources WHERE scraper_class = 'GnrationScraper') 
    AND (image IS NULL OR image = '')
    ORDER BY id
    LIMIT ?
");

$stmt->execute([$batchSize]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "✅ No events need image updates!\n";
    exit;
}

echo "🔄 Processing " . count($events) . " events...\n\n";

$scraper = new GnrationScraper($db, 1);
$reflection = new ReflectionClass($scraper);
$extractImageMethod = $reflection->getMethod('extractEventImage');
$extractImageMethod->setAccessible(true);

$updated = 0;

foreach ($events as $i => $event) {
    $num = $i + 1;
    echo "[$num] " . substr($event['title'], 0, 40) . "...\n";
    
    $image = $extractImageMethod->invoke($scraper, $event['url']);
    
    if ($image) {
        $updateStmt = $db->getConnection()->prepare("UPDATE events SET image = ? WHERE id = ?");
        if ($updateStmt->execute([$image, $event['id']])) {
            echo "   ✅ " . basename($image) . "\n";
            $updated++;
        }
    } else {
        echo "   ⚪ No image\n";
    }
    
    // Brief delay
    sleep(1);
}

echo "\n📊 Updated $updated events with images\n";
echo "🎉 Batch completed!\n";