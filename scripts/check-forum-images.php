<?php
$ctx  = stream_context_create(['http' => [
    'user_agent' => 'Mozilla/5.0 (compatible; BragaAgenda/1.0)',
    'timeout'    => 30,
]]);
$html = file_get_contents('https://www.forumbraga.com/Agenda/Programacao', false, $ctx);

// Find .png GUIDs in the raw HTML
preg_match_all('/Content\/Images\/([a-f0-9-]+\.png)/i', $html, $m);
echo "PNG image GUIDs in raw HTML:\n";
foreach (array_unique($m[0]) as $src) echo "  $src\n";

echo "\n---\n\n";

// Show full raw HTML of the second article (Raphael Ghanem)
$dom = new DOMDocument();
libxml_use_internal_errors(true);
$dom->loadHTML($html);
libxml_clear_errors();
$xpath    = new DOMXPath($dom);
$articles = $xpath->query('//article');
echo "Full HTML of article[1]:\n";
echo $dom->saveHTML($articles->item(1));
