<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../scrapers/BaseScraper.php';
require_once '../scrapers/FacebookScraper.php';

$db  = new Database();
$pdo = $db->getConnection();

// Find Taberna do Peter source
$stmt   = $pdo->prepare("SELECT id FROM sources WHERE scraper_class = 'FacebookScraper' LIMIT 1");
$stmt->execute();
$source = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$source) {
    echo "Scraper não registado. Corre primeiro: php setup/setup-taberna-peter-scraper.php\n";
    exit(1);
}

$scraper = new FacebookScraper($db, $source['id']);
$result  = $scraper->scrape();

if (isset($result['error'])) {
    echo "ERRO: " . $result['error'] . "\n";
    exit(1);
}

echo "Eventos novos : " . $result['events_scraped'] . "\n";

if (!empty($result['errors'])) {
    echo "Erros (" . count($result['errors']) . "):\n";
    foreach ($result['errors'] as $err) {
        echo "  - $err\n";
    }
}

echo "Concluído.\n";
