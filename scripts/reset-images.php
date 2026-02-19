<?php
/**
 * Reset scraped images so the next scraper run re-downloads them.
 *
 * Usage:
 *   php scripts/reset-images.php            # all sources
 *   php scripts/reset-images.php 10         # source_id 10 (Forum Braga)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$sourceId = isset($argv[1]) ? (int)$argv[1] : null;

$db  = new Database();
$pdo = $db->getConnection();

$where  = $sourceId ? "WHERE source_id = $sourceId" : '';
$events = $pdo->query("SELECT id, image FROM events $where")->fetchAll(PDO::FETCH_ASSOC);

$deleted = 0;
$cleared = 0;

foreach ($events as $event) {
    if (!$event['image']) continue;

    // Delete local file if it exists
    if (strpos($event['image'], 'uploads/') === 0) {
        $path = __DIR__ . '/../' . $event['image'];
        if (file_exists($path)) {
            unlink($path);
            $deleted++;
        }
    }

    // Clear the image field
    $pdo->prepare("UPDATE events SET image = NULL WHERE id = ?")->execute([$event['id']]);
    $cleared++;
}

$scope = $sourceId ? "source_id=$sourceId" : 'all sources';
echo "[$scope] Cleared $cleared image fields, deleted $deleted local files.\n";
echo "Run the scraper to re-download images.\n";
