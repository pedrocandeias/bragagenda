<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

echo "Setting up Theatro Circo scraper...\n";

// Add Theatro Circo scraper source
if ($scraperManager->addSource(
    'Theatro Circo - Braga', 
    'https://www.theatrocirco.com/pt/agendaebilheteira', 
    'TheatroCircoScraper'
)) {
    echo "✅ Theatro Circo scraper source added successfully!\n";
    echo "\nTo test the scraper, run:\n";
    echo "php test-theatro-circo-scraper.php\n";
    echo "\nTo run all scrapers:\n";
    echo "php run-scrapers.php\n";
} else {
    echo "❌ Failed to add Theatro Circo scraper source.\n";
    echo "It may already exist in the database.\n";
}

echo "\nDone.\n";