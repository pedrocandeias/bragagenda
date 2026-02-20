<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../scrapers/ScraperManager.php';

$db      = new Database();
$manager = new ScraperManager($db);

$manager->addSource(
    'Taberna do Peter',
    'https://www.facebook.com/aTabernadoPeter/events',
    'FacebookScraper'
);

echo "Taberna do Peter scraper registado com sucesso.\n";
