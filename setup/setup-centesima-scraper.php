<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

echo "Setting up Centésima scraper...\n";

// Add Centésima scraper source
if ($scraperManager->addSource(
    'Centésima - Braga', 
    'https://centesima.com/agenda', 
    'CentesimaScraper'
)) {
    echo "✅ Centésima scraper source added successfully!\n";
    echo "\nNote: Centésima uses a dynamic Angular application.\n";
    echo "The scraper includes multiple strategies to handle this.\n\n";
    echo "To test the scraper, run:\n";
    echo "php tests/test-centesima-scraper.php\n";
    echo "\nTo run all scrapers:\n";
    echo "php run-scrapers.php\n";
} else {
    echo "❌ Failed to add Centésima scraper source.\n";
    echo "It may already exist in the database.\n";
}

echo "\nDone.\n";