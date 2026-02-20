<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';
require_once '../includes/Auth.php';

Auth::requireLoginAjax();

header('Content-Type: application/json');

$db = new Database();
$eventModel = new Event($db);

$eventId = $_GET['id'] ?? 0;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'Event ID is required']);
    exit;
}

$event = $eventModel->getEventById($eventId);

if (!$event) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit;
}

// Return event data as JSON
echo json_encode($event);