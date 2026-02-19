<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/ScraperManager.php';

$db  = new Database();
$mgr = new ScraperManager($db);

$ok = $mgr->addSource(
    'Mosteiro de Tibães',
    'https://www.mosteirodetibaes.gov.pt/',
    'MosteiroDeTibaesScraper'
);

echo $ok ? "✅ Source added.\n" : "ℹ️  Already exists or insert ignored.\n";

$row = $db->getConnection()
    ->query("SELECT id, name, scraper_class FROM sources WHERE scraper_class = 'MosteiroDeTibaesScraper'")
    ->fetch(PDO::FETCH_ASSOC);

print_r($row);
