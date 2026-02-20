<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';
require_once '../includes/Auth.php';

Auth::requireLogin();

$currentUser = Auth::currentUser();
$db = new Database();
$eventModel = new Event($db);

$eventId = $_GET['id'] ?? 0;

// Load event and check ownership for contributors
if ($eventId) {
    $event = $eventModel->getEventById($eventId);
    if ($event && !Auth::hasMinRole('admin') && (empty($event['created_by']) || $event['created_by'] != $currentUser['id'])) {
        header('Location: index.php?message=' . urlencode('Sem permissão para eliminar este evento.') . '&type=error');
        exit;
    }
}

if ($eventId && $eventModel->deleteEvent($eventId)) {
    header('Location: index.php?message=deleted');
} else {
    header('Location: index.php?error=1');
}
exit;