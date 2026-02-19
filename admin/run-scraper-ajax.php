<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../scrapers/ScraperManager.php';

ob_start();
header('Content-Type: application/json');
set_time_limit(300);

define('SCRAPER_LOG', __DIR__ . '/../data/scraper.log');

$db = new Database();
$pdo = $db->getConnection();
$scraperManager = new ScraperManager($db);

$input    = json_decode(file_get_contents('php://input'), true);
$sourceId = $input['source_id'] ?? null;
$runAll   = !empty($input['run_all']);

function writeLog($line) {
    $ts = date('Y-m-d H:i:s');
    file_put_contents(SCRAPER_LOG, "[$ts] $line\n", FILE_APPEND | LOCK_EX);
}

function countEvents($pdo, $sourceId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE source_id = ?");
    $stmt->execute([$sourceId]);
    return (int)$stmt->fetchColumn();
}

function runSource($pdo, $scraperManager, $source) {
    $countBefore = countEvents($pdo, $source['id']);

    $start    = microtime(true);
    $result   = $scraperManager->runScraper($source);
    $duration = round(microtime(true) - $start, 1);

    $countAfter  = countEvents($pdo, $source['id']);
    $eventsNew   = $countAfter - $countBefore;

    $pdo->prepare("UPDATE sources SET last_scraped = CURRENT_TIMESTAMP WHERE id = ?")
        ->execute([$source['id']]);

    // Extract fields from scraper result
    $eventsFound = null;
    $errors      = [];
    if (empty($result['error']) && isset($result['result'])) {
        $eventsFound = $result['result']['events_scraped'] ?? null;
        $errors      = $result['result']['errors'] ?? [];
    }

    $eventsSkipped = ($eventsFound !== null) ? max(0, $eventsFound - $eventsNew) : null;

    $name = $source['name'];

    // Log fatal scraper error
    if (!empty($result['error'])) {
        writeLog("[{$name}] FATAL: " . $result['error']);
    } else {
        // Log run summary
        $found   = $eventsFound  ?? '?';
        $skipped = $eventsSkipped ?? '?';
        writeLog("[{$name}] OK: found={$found} new={$eventsNew} skipped={$skipped} total={$countAfter} duration={$duration}s");

        // Log individual errors
        foreach ($errors as $err) {
            writeLog("[{$name}] ERROR: $err");
        }
    }

    return [
        'id'             => $source['id'],
        'name'           => $name,
        'duration'       => $duration,
        'events_found'   => $eventsFound,
        'events_new'     => $eventsNew,
        'events_skipped' => $eventsSkipped,
        'events_total'   => $countAfter,
        'errors'         => $errors,
        'error'          => $result['error'] ?? null,
    ];
}

if ($runAll) {
    $stmt    = $pdo->query("SELECT * FROM sources WHERE active = 1 ORDER BY name");
    $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = [];
    foreach ($sources as $source) {
        $results[] = runSource($pdo, $scraperManager, $source);
    }
    ob_end_clean();
    echo json_encode(['success' => true, 'results' => $results]);

} elseif ($sourceId) {
    $stmt = $pdo->prepare("SELECT * FROM sources WHERE id = ?");
    $stmt->execute([(int)$sourceId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Scraper não encontrado']);
        exit;
    }
    ob_end_clean();
    echo json_encode(['success' => true, 'results' => [runSource($pdo, $scraperManager, $source)]]);

} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Nenhum scraper especificado']);
}
