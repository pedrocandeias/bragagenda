<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

echo "Setting up Gnration scraper...\n";

// Add Gnration scraper source
if ($scraperManager->addSource(
    'Gnration - Braga', 
    'https://www.gnration.pt/ver-todos/', 
    'GnrationScraper'
)) {
    echo "✅ Gnration scraper source added successfully!\n";
    echo "\nTo test the scraper, run:\n";
    echo "php test-gnration-scraper.php\n";
    echo "\nTo run all scrapers:\n";
    echo "php run-scrapers.php\n";
} else {
    echo "❌ Failed to add Gnration scraper source.\n";
    echo "It may already exist in the database.\n";
}

echo "\nDone.\n";