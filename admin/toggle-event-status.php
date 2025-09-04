<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$id = (int)$input['id'];
$action = $input['action'];

if (!in_array($action, ['toggle-hidden', 'toggle-featured'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}

try {
    $db = new Database();
    $eventModel = new Event($db);
    
    // Verify event exists
    $event = $eventModel->getEventById($id);
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        exit;
    }
    
    $success = false;
    if ($action === 'toggle-hidden') {
        $success = $eventModel->toggleHidden($id);
        $newStatus = $event['hidden'] ? 0 : 1;
    } elseif ($action === 'toggle-featured') {
        $success = $eventModel->toggleFeatured($id);
        $newStatus = $event['featured'] ? 0 : 1;
    }
    
    if ($success) {
        echo json_encode([
            'success' => true, 
            'action' => $action,
            'newStatus' => $newStatus
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update event']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>