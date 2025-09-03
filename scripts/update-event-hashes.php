<?php
require_once 'config.php';
require_once 'includes/Database.php';

echo "Atualizando hashes dos eventos existentes...\n";
echo "===========================================\n\n";

$db = new Database();
$conn = $db->getConnection();

// Get all events without hashes
$stmt = $conn->query("SELECT id, title, event_date, url FROM events WHERE event_hash IS NULL OR event_hash = ''");
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "✅ Todos os eventos já têm hash.\n";
    exit;
}

echo "📊 Encontrados " . count($events) . " eventos sem hash.\n\n";

$updated = 0;

foreach ($events as $event) {
    // Generate hash using the same logic as BaseScraper
    $data = strtolower(trim($event['title'])) . '|' . date('Y-m-d', strtotime($event['event_date']));
    if ($event['url']) {
        $data .= '|' . $event['url'];
    }
    $hash = md5($data);
    
    // Update event with hash
    $stmt = $conn->prepare("UPDATE events SET event_hash = ? WHERE id = ?");
    if ($stmt->execute([$hash, $event['id']])) {
        echo "✅ Atualizado: " . $event['title'] . " [" . substr($hash, 0, 8) . "]\n";
        $updated++;
    } else {
        echo "❌ Falha: " . $event['title'] . "\n";
    }
}

echo "\n📊 Total de eventos atualizados: $updated\n";
echo "🎉 Todos os eventos têm agora hashes únicos!\n\n";

// Verify no duplicates
$stmt = $conn->query("
    SELECT event_hash, COUNT(*) as count 
    FROM events 
    WHERE event_hash IS NOT NULL 
    GROUP BY event_hash 
    HAVING COUNT(*) > 1
");

$duplicateHashes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicateHashes)) {
    echo "✅ Nenhum hash duplicado encontrado.\n";
} else {
    echo "⚠️ Encontrados " . count($duplicateHashes) . " hashes duplicados:\n";
    foreach ($duplicateHashes as $dup) {
        echo "   " . $dup['event_hash'] . " (x" . $dup['count'] . ")\n";
    }
}

echo "\n";