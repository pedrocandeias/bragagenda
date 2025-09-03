<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

$db = new Database();
$scraperManager = new ScraperManager($db);

// Add example scraper source
if ($scraperManager->addSource('Example Braga Events', 'https://example-braga-events.pt/events', 'ExampleBragaScraper')) {
    echo "Example scraper source added successfully!\n";
    echo "You can now run scrapers using: php run-scrapers.php\n";
} else {
    echo "Failed to add example scraper source.\n";
}