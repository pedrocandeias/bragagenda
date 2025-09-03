<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';

echo "Debug Gnration Scraper - Analyzing Page Structure\n";
echo "================================================\n\n";

$url = "https://www.gnration.pt/ver-todos/";

echo "1. Fetching URL: $url\n";
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

echo "✅ Page fetched successfully (" . strlen($html) . " bytes)\n\n";

// Parse HTML
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

echo "2. Searching for common event patterns...\n";

// Search for various patterns
$searches = [
    "Links with '/event/' in href" => "//a[contains(@href, '/event/')]",
    "Elements with 'event' in class" => "//*[contains(@class, 'event')]",
    "Article elements" => "//article",
    "H1-H4 headings" => "//h1 | //h2 | //h3 | //h4",
    "Time elements" => "//time",
    "Elements with dates (2024/2025)" => "//*[contains(text(), '2024') or contains(text(), '2025')]",
    "List items" => "//li",
    "Divs with common classes" => "//div[contains(@class, 'item') or contains(@class, 'post') or contains(@class, 'card')]"
];

foreach ($searches as $description => $query) {
    $nodes = $xpath->query($query);
    echo sprintf("   %-35s: %d found\n", $description, $nodes->length);
    
    if ($nodes->length > 0 && $nodes->length < 50) { // Show details for reasonable amounts
        for ($i = 0; $i < min(3, $nodes->length); $i++) {
            $node = $nodes->item($i);
            $text = trim(substr($node->textContent, 0, 100));
            $tag = $node->nodeName;
            
            // Get class attribute if exists
            $class = $node->getAttribute('class');
            $href = $node->getAttribute('href');
            
            echo "      [$i] <$tag";
            if ($class) echo " class=\"$class\"";
            if ($href) echo " href=\"$href\"";
            echo "> " . ($text ? "\"$text...\"" : "(empty)") . "\n";
        }
    }
}

echo "\n3. Looking for text patterns...\n";

// Look for Portuguese months and dates
$monthPattern = '/(janeiro|fevereiro|março|abril|maio|junho|julho|agosto|setembro|outubro|novembro|dezembro|jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)/i';
$datePattern = '/\d{1,2}.*(?:' . substr($monthPattern, 2, -3) . ').*\d{4}/i';

$textNodes = $xpath->query("//text()[string-length(normalize-space(.)) > 10]");
$dateMatches = 0;

echo "   Scanning " . $textNodes->length . " text nodes for dates...\n";

for ($i = 0; $i < min(20, $textNodes->length); $i++) {
    $text = trim($textNodes->item($i)->textContent);
    if (preg_match($datePattern, $text, $matches)) {
        echo "   📅 Date found: \"" . substr($text, 0, 80) . "\"\n";
        $dateMatches++;
        if ($dateMatches >= 5) break; // Limit output
    }
}

echo "\n4. Sample of page content (first 1000 chars):\n";
echo "---\n";
$bodyContent = $xpath->query("//body")->item(0);
if ($bodyContent) {
    echo substr(trim($bodyContent->textContent), 0, 1000) . "...\n";
} else {
    echo substr(trim($dom->textContent), 0, 1000) . "...\n";
}
echo "---\n\n";

echo "Debug complete. Use this information to update the scraper selectors.\n";