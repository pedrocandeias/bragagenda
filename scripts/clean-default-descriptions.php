<?php
require_once 'config.php';
require_once 'includes/Database.php';

echo "Removing Default 'Evento no X' Descriptions\n";
echo "===========================================\n\n";

$db = new Database();
$conn = $db->getConnection();

// Find events with default descriptions
$stmt = $conn->query("
    SELECT id, title, description 
    FROM events 
    WHERE description LIKE 'Evento no %'
    ORDER BY id
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "✅ No default descriptions found to remove.\n";
    exit;
}

echo "📊 Found " . count($events) . " events with default descriptions:\n\n";

$updated = 0;

foreach ($events as $event) {
    echo "• " . substr($event['title'], 0, 50) . "...\n";
    echo "  Old: " . $event['description'] . "\n";
    
    // Remove the default description (set to NULL)
    $updateStmt = $conn->prepare("UPDATE events SET description = NULL WHERE id = ?");
    if ($updateStmt->execute([$event['id']])) {
        echo "  ✅ Description removed\n";
        $updated++;
    } else {
        echo "  ❌ Failed to update\n";
    }
    echo "\n";
}

echo "📊 Summary:\n";
echo "✅ Updated: $updated events\n";
echo "🎉 Default descriptions cleaned up!\n\n";

// Show statistics
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        COUNT(description) as with_desc,
        COUNT(*) - COUNT(description) as without_desc
    FROM events
");

$stats = $stmt->fetch(PDO::FETCH_ASSOC);

echo "📈 Final Statistics:\n";
echo "Total events: " . $stats['total'] . "\n";
echo "With descriptions: " . $stats['with_desc'] . "\n";
echo "Without descriptions: " . $stats['without_desc'] . "\n";

echo "\n";