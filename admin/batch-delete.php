<?php
require_once '../config.php';
require_once '../includes/Database.php';

$db = new Database();

$message = '';
$messageType = 'success';

// Handle batch delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_events'])) {
    $eventIds = $_POST['event_ids'] ?? [];
    
    if (!empty($eventIds)) {
        // Sanitize IDs
        $eventIds = array_map('intval', $eventIds);
        $placeholders = str_repeat('?,', count($eventIds) - 1) . '?';
        
        $stmt = $db->getConnection()->prepare("DELETE FROM events WHERE id IN ($placeholders)");
        $result = $stmt->execute($eventIds);
        
        if ($result) {
            $deletedCount = $stmt->rowCount();
            $message = "Successfully deleted {$deletedCount} event(s).";
        } else {
            $message = "Failed to delete events.";
            $messageType = 'error';
        }
    } else {
        $message = "No events selected for deletion.";
        $messageType = 'error';
    }
}

// Redirect back to admin with message
$queryParams = [
    'message' => $message,
    'type' => $messageType
];

header('Location: index.php?' . http_build_query($queryParams));
exit;