<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

echo "Starting scrapers...\n";
$results = $scraperManager->runAllScrapers();

foreach ($results as $sourceName => $result) {
    echo "\n--- $sourceName ---\n";
    if (isset($result['error'])) {
        echo "ERROR: " . $result['error'] . "\n";
    } else {
        echo "SUCCESS: " . print_r($result['result'], true) . "\n";
    }
}

echo "\nScrapers completed.\n";