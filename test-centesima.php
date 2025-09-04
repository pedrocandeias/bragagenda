<?php
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/CentesimaScraper.php';

echo "=== TESTING CENTÉSIMA SCRAPER ===\n";

$db = new Database();
$scraper = new CentesimaScraper($db, 'Centésima', 'https://centesima.com/agenda');

echo "Running scraper...\n";
$result = $scraper->scrape();

echo "Result:\n";
echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
?>