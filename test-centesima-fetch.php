<?php
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/CentesimaScraper.php';

class TestCentesimaScraper extends CentesimaScraper {
    
    public function testFetchWithJS() {
        // Make the private method accessible for testing
        $reflection = new ReflectionClass($this);
        $method = $reflection->getMethod('fetchUrlWithJS');
        $method->setAccessible(true);
        
        echo "Testing fetchUrlWithJS method...\n";
        $html = $method->invoke($this, 'https://centesima.com/agenda');
        
        if (!$html) {
            echo "❌ Failed to fetch HTML\n";
            return;
        }
        
        echo "✅ Successfully fetched HTML (" . strlen($html) . " characters)\n\n";
        
        // Save to file for analysis
        file_put_contents('centesima-test-output.html', $html);
        echo "Saved HTML to centesima-test-output.html\n\n";
        
        // Look for event-related content
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Check for various event selectors
        $selectors = [
            '//div[contains(@class, "titulo")]',
            '//div[contains(@class, "information")]', 
            '//div[contains(@class, "data")]',
            '//*[contains(text(), "Entrada livre")]',
            '//*[contains(text(), "setembro")]',
            '//*[contains(text(), "2024")]',
            '//*[contains(text(), "2025")]',
        ];
        
        foreach ($selectors as $selector) {
            $nodes = $xpath->query($selector);
            echo "Selector '{$selector}': Found {$nodes->length} elements\n";
            
            if ($nodes->length > 0) {
                for ($i = 0; $i < min(3, $nodes->length); $i++) {
                    $node = $nodes->item($i);
                    echo "  [{$i}] " . trim(substr($node->textContent, 0, 100)) . "...\n";
                }
            }
        }
    }
}

echo "=== TESTING CENTÉSIMA FETCH METHOD ===\n";

$db = new Database();
$testScraper = new TestCentesimaScraper($db, 'Centésima', 'https://centesima.com/agenda');
$testScraper->testFetchWithJS();
?>