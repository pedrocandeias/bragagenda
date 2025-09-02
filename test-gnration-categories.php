<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/GnrationScraper.php';

echo "Testing Improved Gnration Category Detection\n";
echo "==========================================\n\n";

$db = new Database();

// Debug the page structure to see card__category elements
$url = "https://www.gnration.pt/ver-todos/";
$context = stream_context_create([
    'http' => [
        'user_agent' => 'Mozilla/5.0 (compatible; BragaAgenda/1.0)',
        'timeout' => 30
    ]
]);

$html = file_get_contents($url, false, $context);

if (!$html) {
    echo "❌ Failed to fetch page\n";
    exit(1);
}

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

echo "1. Looking for card__category elements...\n";

// Search for card__category elements
$categoryElements = $xpath->query('//*[contains(@class, "card__category")]');
echo "Found " . $categoryElements->length . " card__category elements\n\n";

if ($categoryElements->length > 0) {
    echo "Sample card__category content:\n";
    for ($i = 0; $i < min(5, $categoryElements->length); $i++) {
        $element = $categoryElements->item($i);
        $content = trim($element->textContent);
        $class = $element->getAttribute('class');
        echo "   [$i] class='$class' content='$content'\n";
    }
} else {
    echo "⚠️ No card__category elements found. Let's check other category patterns...\n";
    
    // Look for other category patterns
    $patterns = [
        'elements with "category" in class' => '//*[contains(@class, "category")]',
        'elements with "type" in class' => '//*[contains(@class, "type")]',
        'elements with "tag" in class' => '//*[contains(@class, "tag")]',
        'span elements' => '//span'
    ];
    
    foreach ($patterns as $desc => $pattern) {
        $elements = $xpath->query($pattern);
        echo "\n$desc: " . $elements->length . " found\n";
        
        if ($elements->length > 0 && $elements->length < 50) {
            for ($i = 0; $i < min(3, $elements->length); $i++) {
                $element = $elements->item($i);
                $content = trim(substr($element->textContent, 0, 50));
                $class = $element->getAttribute('class');
                if ($content && $class) {
                    echo "   [$i] class='$class' content='$content'\n";
                }
            }
        }
    }
}

echo "\n2. Testing category extraction with current events...\n";

// Test with a few event URLs
$eventUrls = [
    'https://www.gnration.pt/event/2025/genesis-mario-de-vega/',
    'https://www.gnration.pt/event/2025/pos-laboratorios-de-verao-3/',
    'https://www.gnration.pt/event/2025/gnration-noite-branca-evols/'
];

$scraper = new GnrationScraper($db, 1);

// Use reflection to access private methods
$reflection = new ReflectionClass($scraper);
$extractCategoryMethod = $reflection->getMethod('extractCategory');
$extractCategoryMethod->setAccessible(true);

foreach ($eventUrls as $i => $eventUrl) {
    echo "\nEvent " . ($i + 1) . ": $eventUrl\n";
    
    try {
        $eventHtml = file_get_contents($eventUrl, false, $context);
        if ($eventHtml) {
            $eventDom = new DOMDocument();
            libxml_use_internal_errors(true);
            $eventDom->loadHTML($eventHtml);
            libxml_clear_errors();
            
            $eventXpath = new DOMXPath($eventDom);
            
            // Look for card__category on the event page
            $categoryEls = $eventXpath->query('//*[contains(@class, "card__category")]');
            echo "   card__category elements on page: " . $categoryEls->length . "\n";
            
            if ($categoryEls->length > 0) {
                for ($j = 0; $j < $categoryEls->length; $j++) {
                    $cat = trim($categoryEls->item($j)->textContent);
                    echo "   Category found: '$cat'\n";
                }
            }
            
            // Test our extraction method (simulate with main content)
            $mainContent = $eventXpath->query('//main | //body')->item(0);
            if ($mainContent) {
                $category = $extractCategoryMethod->invoke($scraper, $eventXpath, $mainContent);
                echo "   Extracted category: " . ($category ?: 'NULL') . "\n";
            }
        }
        
        // Small delay
        sleep(1);
        
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

echo "\nCategory detection test completed.\n";