<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/BaseScraper.php';
require_once __DIR__ . '/../scrapers/MosteiroDeTibaesScraper.php';

$db  = new Database();
$pdo = $db->getConnection();

$src = $pdo->query("SELECT id FROM sources WHERE scraper_class = 'MosteiroDeTibaesScraper'")->fetch(PDO::FETCH_ASSOC);
if (!$src) {
    die("Source not found. Run setup/setup-mosteiro-tibaes-scraper.php first.\n");
}

echo "Running MosteiroDeTibaesScraper (source id={$src['id']})…\n\n";

$scraper = new MosteiroDeTibaesScraper($db, $src['id']);
$result  = $scraper->scrape();

echo "Events scraped : " . ($result['events_scraped'] ?? 0) . "\n";
if (!empty($result['errors'])) {
    echo "Errors:\n";
    foreach ($result['errors'] as $e) echo "  - $e\n";
}
if (isset($result['error'])) {
    echo "Fatal: " . $result['error'] . "\n";
}

echo "\n--- Recent Mosteiro de Tibães events in DB ---\n";
$stmt = $pdo->prepare("
    SELECT title, event_date, category, image IS NOT NULL AS has_image, url
    FROM events
    WHERE source_id = ?
    ORDER BY event_date ASC
    LIMIT 20
");
$stmt->execute([$src['id']]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as $ev) {
    printf("%-50s  %s  %-20s  img=%s\n",
        mb_substr($ev['title'], 0, 50),
        $ev['event_date'],
        $ev['category'],
        $ev['has_image'] ? 'yes' : 'no'
    );
}
echo "\nTotal: " . count($events) . " event(s)\n";
