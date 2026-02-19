<?php
/**
 * Download all remaining remote Forum Braga images and store them locally.
 * Safe to run multiple times (skips already-local images).
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/BaseScraper.php';

// Minimal stub so we can instantiate BaseScraper methods without a real scraper
class ImageMigrator extends BaseScraper {
    public function __construct(Database $db) { $this->db = $db->getConnection(); }
    public function scrape() {}
    public function download($url) { return $this->downloadImage($url); }
}

$db      = new Database();
$pdo     = $db->getConnection();
$migrate = new ImageMigrator($db);

$stmt = $pdo->query("SELECT id, image FROM events WHERE image LIKE '%forumbraga.com%'");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo count($rows) . " remote images to migrate\n";

$ok = 0; $fail = 0;
foreach ($rows as $row) {
    $local = $migrate->download($row['image']);
    if ($local) {
        $pdo->prepare("UPDATE events SET image = ? WHERE id = ?")->execute([$local, $row['id']]);
        echo "  OK  [{$row['id']}] $local\n";
        $ok++;
    } else {
        $pdo->prepare("UPDATE events SET image = NULL WHERE id = ?")->execute([$row['id']]);
        echo "  404 [{$row['id']}] {$row['image']}\n";
        $fail++;
    }
}

echo "\nDone. Downloaded: $ok, Failed (nulled): $fail\n";
