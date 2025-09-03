<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

echo "Setting up Espaço Vita scraper...\n";

// Add Espaço Vita scraper source
if ($scraperManager->addSource(
    'Espaço Vita - Braga', 
    'https://www.espacovita.pt/pt/agenda', 
    'EspacoVitaScraper'
)) {
    echo "✅ Espaço Vita scraper source added successfully!\n";
    echo "\nTo test the scraper, run:\n";
    echo "php test-espaco-vita-scraper.php\n";
    echo "\nTo run all scrapers:\n";
    echo "php run-scrapers.php\n";
} else {
    echo "❌ Failed to add Espaço Vita scraper source.\n";
    echo "It may already exist in the database.\n";
}

echo "\nDone.\n";