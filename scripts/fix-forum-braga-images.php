<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$db  = new Database();
$pdo = $db->getConnection();

$stmt = $pdo->query("SELECT id, image FROM events WHERE source_id = 10 AND image LIKE 'http%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo 'Forum Braga events with remote images: ' . count($rows) . PHP_EOL;

$fixed = 0;
foreach ($rows as $row) {
    $headers = @get_headers($row['image']);
    $broken  = !$headers || strpos($headers[0], '404') !== false;
    if ($broken) {
        $pdo->prepare('UPDATE events SET image = NULL WHERE id = ?')->execute([$row['id']]);
        echo '  Cleared event ' . $row['id'] . ': ' . $row['image'] . PHP_EOL;
        $fixed++;
    }
}
echo 'Done. Cleared ' . $fixed . ' broken images.' . PHP_EOL;
