<?php
echo "Debug Teatro Circo Website Structure\n";
echo "===================================\n\n";

$url = "https://www.theatrocirco.com/pt/agendaebilheteira";

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

$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();

$xpath = new DOMXPath($dom);

echo "2. Searching for event patterns...\n";

$searches = [
    "Event container (#showaevents)" => "//*[@id='showaevents']",
    "List items with agenda" => "//li[contains(@class, 'agenda')]",
    "Div with agenda/event classes" => "//div[contains(@class, 'agenda') or contains(@class, 'event')]",
    "Event links" => "//a[contains(@href, 'programacultural')]",
    "Event images" => "//img[contains(@src, 'agenda') or contains(@src, 'evento')]",
    "Date elements" => "//*[contains(text(), '2024') or contains(text(), '2025')]",
    "All links" => "//a[@href]",
    "Theatre/Culture containers" => "//div[contains(@class, 'teatro') or contains(@class, 'cultura')]"
];

foreach ($searches as $description => $query) {
    $nodes = $xpath->query($query);
    echo sprintf("   %-40s: %d found\n", $description, $nodes->length);
    
    if ($nodes->length > 0 && $nodes->length < 20) {
        for ($i = 0; $i < min(3, $nodes->length); $i++) {
            $node = $nodes->item($i);
            $text = trim(substr($node->textContent, 0, 80));
            $class = $node->getAttribute('class');
            $href = $node->getAttribute('href');
            $src = $node->getAttribute('src');
            
            echo "      [$i] ";
            if ($class) echo "class='$class' ";
            if ($href) echo "href='$href' ";
            if ($src) echo "src='$src' ";
            if ($text) echo "text='$text...'";
            echo "\n";
        }
    }
}

echo "\n3. Looking for specific event structure...\n";

// Look for the main event container
$eventContainer = $xpath->query("//*[@id='showaevents']");
if ($eventContainer->length > 0) {
    echo "✅ Found #showaevents container\n";
    
    $container = $eventContainer->item(0);
    $childElements = $xpath->query('.//*', $container);
    echo "   Child elements: " . $childElements->length . "\n";
    
    // Look for event items within the container
    $eventItems = $xpath->query('.//li | .//div', $container);
    echo "   Potential event items (li/div): " . $eventItems->length . "\n";
    
    if ($eventItems->length > 0) {
        for ($i = 0; $i < min(3, $eventItems->length); $i++) {
            $item = $eventItems->item($i);
            $text = trim(substr($item->textContent, 0, 100));
            $class = $item->getAttribute('class');
            echo "      Item $i: class='$class' text='$text...'\n";
        }
    }
} else {
    echo "❌ No #showaevents container found\n";
}

echo "\n4. Sample page content (first 800 chars):\n";
echo "---\n";
$body = $xpath->query("//body")->item(0);
if ($body) {
    echo substr(trim($body->textContent), 0, 800) . "...\n";
}
echo "---\n\n";

echo "Debug complete.\n";