<?php
require_once 'includes/Database.php';
require_once 'scrapers/ScraperManager.php';

echo "Setting up Forum Braga scraper...\n\n";

try {
    $db = new Database();
    $scraperManager = new ScraperManager($db);
    
    // Add Forum Braga as a source
    $result = $scraperManager->addSource(
        'Forum Braga',
        'https://www.forumbraga.com/Agenda/Programacao',
        'ForumBragaScraper'
    );
    
    if ($result) {
        echo "✅ Forum Braga scraper source added successfully!\n";
        echo "Source: Forum Braga\n";
        echo "URL: https://www.forumbraga.com/Agenda/Programacao\n";
        echo "Scraper Class: ForumBragaScraper\n\n";
        
        echo "You can now run this scraper using:\n";
        echo "- php run-scrapers.php (runs all scrapers)\n";
        echo "- Or test individually with: php tests/test-forum-braga-scraper.php\n";
        
    } else {
        echo "⚠️  Forum Braga source may already exist or there was an error adding it.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error setting up Forum Braga scraper: " . $e->getMessage() . "\n";
}

echo "\nSetup completed.\n";
?>