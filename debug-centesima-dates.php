<?php
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';
require_once 'scrapers/CentesimaScraper.php';

class DebugCentesimaScraper extends CentesimaScraper {
    
    public function debugScrape() {
        echo "=== DEBUGGING CENTÉSIMA DATE EXTRACTION ===\n\n";
        
        // Try to get HTML by calling the scraper's public method first
        echo "Attempting to fetch HTML using scraper method...\n";
        $result = $this->scrape();
        
        // If that doesn't work, try fetching directly with curl
        if (isset($result['error'])) {
            echo "Scraper failed, trying direct curl fetch...\n";
            $html = $this->fetchUrl("https://centesima.com/agenda");
        } else {
            echo "Scraper succeeded, getting HTML manually...\n";
            $html = $this->fetchUrl("https://centesima.com/agenda");
        }
        
        if (!$html) {
            echo "❌ Failed to fetch HTML content\n";
            return;
        }
        
        echo "✅ Successfully fetched HTML (" . strlen($html) . " characters)\n\n";
        
        // Save a sample of the HTML for inspection
        file_put_contents('centesima-debug-full.html', $html);
        echo "Saved full HTML to centesima-debug-full.html\n\n";
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Look for event containers
        $eventSelectors = [
            '//div[contains(@class, "titulo")]',
            '//div[contains(@class, "information")]',
            '//*[contains(@class, "titulo")]',
        ];
        
        $eventNodes = null;
        foreach ($eventSelectors as $selector) {
            $nodes = $xpath->query($selector);
            echo "Selector '$selector': Found {$nodes->length} elements\n";
            if ($nodes->length > 0) {
                $eventNodes = $nodes;
                break;
            }
        }
        
        if (!$eventNodes || $eventNodes->length === 0) {
            echo "❌ No event nodes found. Let's check what's in the HTML...\n";
            echo "HTML sample (first 1000 chars):\n";
            echo substr($html, 0, 1000) . "\n\n";
            
            // Look for any elements with 'data' or 'date' in the text or attributes
            echo "=== SEARCHING FOR DATE-RELATED ELEMENTS ===\n";
            $dateElements = $xpath->query("//*[contains(text(), '2024') or contains(text(), '2025') or contains(text(), '2026')]");
            echo "Found {$dateElements->length} elements with year references\n";
            
            for ($i = 0; $i < min(10, $dateElements->length); $i++) {
                $element = $dateElements->item($i);
                echo "  {$element->tagName}: '" . trim($element->textContent) . "'\n";
            }
            
            return;
        }
        
        echo "\n=== ANALYZING EVENT NODES ===\n";
        for ($i = 0; $i < min(5, $eventNodes->length); $i++) {
            $eventNode = $eventNodes->item($i);
            echo "Event $i:\n";
            echo "  Title area content: " . substr(trim($eventNode->textContent), 0, 100) . "...\n";
            
            // Look for date elements around this event node
            $dateQueries = [
                'preceding-sibling::div[contains(@class, "data")]',
                'following-sibling::div[contains(@class, "data")]',
                '../div[contains(@class, "data")]',
                'preceding-sibling::*[contains(text(), "2024") or contains(text(), "2025")]',
                'following-sibling::*[contains(text(), "2024") or contains(text(), "2025")]',
                '../*[contains(text(), "2024") or contains(text(), "2025")]',
                './/*[contains(text(), "2024") or contains(text(), "2025")]'
            ];
            
            foreach ($dateQueries as $query) {
                $dateNodes = $xpath->query($query, $eventNode);
                if ($dateNodes->length > 0) {
                    echo "    Date query '$query': Found {$dateNodes->length} elements\n";
                    for ($j = 0; $j < min(3, $dateNodes->length); $j++) {
                        echo "      [{$j}] " . trim($dateNodes->item($j)->textContent) . "\n";
                    }
                }
            }
            echo "\n";
        }
    }
}

$db = new Database();
$debugScraper = new DebugCentesimaScraper($db, 'Centésima', 'https://centesima.com/agenda');
$debugScraper->debugScrape();
?>