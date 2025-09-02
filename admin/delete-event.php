<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

$eventId = $_GET['id'] ?? 0;

if ($eventId && $eventModel->deleteEvent($eventId)) {
    header('Location: index.php?message=deleted');
} else {
    header('Location: index.php?error=1');
}
exit;