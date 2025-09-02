<?php
require_once 'config.php';
require_once 'includes/Database.php';

echo "Updating Events with Location Data\n";
echo "==================================\n\n";

$db = new Database();
$conn = $db->getConnection();

// First, let's check if the location column exists
echo "1. Checking database schema...\n";
$stmt = $conn->query("PRAGMA table_info(events)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasLocationColumn = false;
foreach ($columns as $column) {
    if ($column['name'] === 'location') {
        $hasLocationColumn = true;
        break;
    }
}

if ($hasLocationColumn) {
    echo "✅ Location column exists\n";
} else {
    echo "❌ Location column missing - running database migration...\n";
    // Force database recreation to add column
    $db = new Database();
    echo "✅ Database updated\n";
}

echo "\n2. Mapping sources to locations...\n";

// Define location mappings based on source
$locationMappings = [
    'GnrationScraper' => 'Gnration',
    'TeatroCircoScraper' => 'Teatro Circo'
];

$updated = 0;

foreach ($locationMappings as $scraperClass => $location) {
    echo "Updating events from $scraperClass → $location\n";
    
    // Update events for this scraper
    $stmt = $conn->prepare("
        UPDATE events 
        SET location = ? 
        WHERE source_id = (
            SELECT id FROM sources WHERE scraper_class = ?
        ) AND (location IS NULL OR location = '')
    ");
    
    $stmt->execute([$location, $scraperClass]);
    $count = $stmt->rowCount();
    
    echo "   ✅ Updated $count events\n";
    $updated += $count;
}

echo "\n3. Results summary...\n";
echo "📊 Total events updated: $updated\n";

// Show current location statistics
$stmt = $conn->query("
    SELECT location, COUNT(*) as count 
    FROM events 
    WHERE location IS NOT NULL AND location != ''
    GROUP BY location 
    ORDER BY count DESC
");

echo "\n📍 Events by location:\n";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("   %-15s: %d events\n", $row['location'], $row['count']);
}

// Check for events without location
$stmt = $conn->query("SELECT COUNT(*) as count FROM events WHERE location IS NULL OR location = ''");
$withoutLocation = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($withoutLocation > 0) {
    echo "\n⚠️  Events without location: $withoutLocation\n";
} else {
    echo "\n✅ All events have locations assigned!\n";
}

echo "\n🎉 Location update completed!\n";