<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'scrapers/BaseScraper.php';

class DebugGnrationScraper extends BaseScraper {
    public function scrape() {
        echo "=== DETAILED SCRAPER DEBUG ===\n\n";
        
        $url = "https://www.gnration.pt/ver-todos/";
        echo "1. Fetching: $url\n";
        
        $html = $this->fetchUrl($url);
        if (!$html) {
            echo "❌ Failed to fetch URL\n";
            return ['error' => 'Failed to fetch URL'];
        }
        
        echo "✅ Fetched " . strlen($html) . " bytes\n\n";
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        echo "2. Looking for event containers...\n";
        
        // Try event-grid divs first
        $eventGrids = $xpath->query('//div[@class="event-grid"]');
        echo "   event-grid divs: " . $eventGrids->length . "\n";
        
        if ($eventGrids->length > 0) {
            echo "   → Using event-grid divs\n";
            $eventNodes = $eventGrids;
        } else {
            // Fallback to event links
            $eventLinks = $xpath->query('//a[contains(@href, "/event/")]');
            echo "   event links: " . $eventLinks->length . "\n";
            echo "   → Using event links\n";
            $eventNodes = $eventLinks;
        }
        
        echo "\n3. Processing " . $eventNodes->length . " nodes...\n";
        
        $processed = 0;
        $saved = 0;
        
        foreach ($eventNodes as $i => $eventNode) {
            if ($i >= 5) break; // Limit for debugging
            
            echo "\n   --- Node $i ---\n";
            echo "   Tag: " . $eventNode->nodeName . "\n";
            
            if ($eventNode->getAttribute('class')) {
                echo "   Class: " . $eventNode->getAttribute('class') . "\n";
            }
            if ($eventNode->getAttribute('href')) {
                echo "   Href: " . $eventNode->getAttribute('href') . "\n";
            }
            
            // Extract data
            $title = $this->extractEventTitle($xpath, $eventNode);
            $eventUrl = $this->extractEventUrl($xpath, $eventNode);
            $dateInfo = $this->extractDateInfo($xpath, $eventNode);
            $category = $this->extractCategory($xpath, $eventNode);
            
            echo "   Title: " . ($title ?: "NULL") . "\n";
            echo "   URL: " . ($eventUrl ?: "NULL") . "\n";
            echo "   Date: " . ($dateInfo['date'] ?: "NULL") . " (raw: " . ($dateInfo['raw'] ?: "NULL") . ")\n";
            echo "   Category: " . ($category ?: "NULL") . "\n";
            
            // Show some content for context
            $content = trim(substr($eventNode->textContent, 0, 200));
            echo "   Content: \"" . $content . "\"\n";
            
            $processed++;
            
            if ($title && $dateInfo['date']) {
                echo "   → Would save this event\n";
                $saved++;
            } else {
                echo "   → Skipping (missing title or date)\n";
            }
        }
        
        echo "\n4. Summary:\n";
        echo "   Processed: $processed\n";
        echo "   Would save: $saved\n";
        
        return ['events_scraped' => 0, 'debug' => true];
    }
    
    // Copy the extraction methods from GnrationScraper for testing
    private function extractEventTitle($xpath, $eventNode) {
        if ($eventNode->nodeName === 'a') {
            return trim($eventNode->textContent);
        }
        
        $titleSelectors = [
            './/a[contains(@href, "/event/")]',
            './/h1', './/h2', './/h3', './/h4',
            './/a'
        ];
        
        foreach ($titleSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $title = trim($nodes->item(0)->textContent);
                if (strlen($title) > 5 && !preg_match('/^\d+/', $title)) {
                    return $title;
                }
            }
        }
        
        return null;
    }
    
    private function extractEventUrl($xpath, $eventNode) {
        if ($eventNode->nodeName === 'a' && $eventNode->getAttribute('href')) {
            $href = $eventNode->getAttribute('href');
            if (strpos($href, '/event/') !== false) {
                return strpos($href, 'http') === 0 ? $href : 'https://www.gnration.pt' . $href;
            }
        }
        
        $urlSelectors = [
            './/a[contains(@href, "/event/")]/@href',
            './/@href'
        ];
        
        foreach ($urlSelectors as $selector) {
            $nodes = $xpath->query($selector, $eventNode);
            if ($nodes->length > 0) {
                $href = $nodes->item(0)->nodeValue;
                if (strpos($href, '/event/') !== false) {
                    return strpos($href, 'http') === 0 ? $href : 'https://www.gnration.pt' . $href;
                }
            }
        }
        
        return null;
    }
    
    private function extractDateInfo($xpath, $eventNode) {
        // Look for date patterns in text content
        $fullText = $eventNode->textContent;
        
        // Pattern for dates like "10 Jul 2025"
        if (preg_match('/(\d{1,2})\s+(Jan|Feb|Mar|Apr|Mai|Jun|Jul|Aug|Set|Out|Nov|Dez)\s+(\d{4})/i', $fullText, $matches)) {
            $dateText = $matches[0];
            return [
                'date' => $this->parsePortugueseDate($dateText),
                'raw' => $dateText
            ];
        }
        
        return ['date' => null, 'raw' => ''];
    }
    
    private function extractCategory($xpath, $eventNode) {
        $text = strtolower($eventNode->textContent);
        $categories = ['música', 'exposição', 'teatro', 'dança', 'cinema', 'literatura', 'workshop'];
        
        foreach ($categories as $cat) {
            if (strpos($text, $cat) !== false) {
                return ucfirst($cat);
            }
        }
        
        return 'Cultura';
    }
    
    private function parsePortugueseDate($dateString) {
        $months = [
            'jan' => '01', 'feb' => '02', 'mar' => '03', 'apr' => '04',
            'mai' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
            'set' => '09', 'out' => '10', 'nov' => '11', 'dez' => '12'
        ];
        
        $dateString = strtolower(trim($dateString));
        
        if (preg_match('/(\d{1,2})\s+(\w+)\s+(\d{4})/', $dateString, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthName = substr($matches[2], 0, 3);
            $year = $matches[3];
            
            $month = $months[$monthName] ?? '01';
            
            return "$year-$month-$day 20:00:00";
        }
        
        return null;
    }
}

// Run debug scraper
$db = new Database();
$debugScraper = new DebugGnrationScraper($db, 1);
$debugScraper->scrape();