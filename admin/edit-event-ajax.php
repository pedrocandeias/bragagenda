<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

header('Content-Type: application/json');

$db = new Database();
$eventModel = new Event($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$eventId = $_POST['id'] ?? 0;

if (!$eventId) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

$event = $eventModel->getEventById($eventId);

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

// Get form data
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$eventDate = $_POST['event_date'] ?? '';
$category = trim($_POST['category'] ?? '');
$location = trim($_POST['location'] ?? '');
$url = trim($_POST['url'] ?? '');
$image = $event['image']; // Keep existing image by default

// Validate required fields
if (empty($title) || empty($eventDate)) {
    echo json_encode(['success' => false, 'message' => 'Title and event date are required']);
    exit;
}

// Handle image upload
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $_FILES['image']['type'];
    
    if (in_array($fileType, $allowedTypes)) {
        // Generate unique filename
        $fileInfo = pathinfo($_FILES['image']['name']);
        $fileName = time() . '_' . uniqid() . '.' . ($fileInfo['extension'] ?? 'jpg');
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            // Delete old image if it exists and is not the default
            if ($event['image'] && file_exists('../' . $event['image'])) {
                unlink('../' . $event['image']);
            }
            $image = 'uploads/' . $fileName;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid image format. Only JPEG, PNG, GIF, and WebP are allowed']);
        exit;
    }
}

// Update event
try {
    if ($eventModel->updateEvent($eventId, $title, $description, $eventDate, $category, $image, $url, $location)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Evento atualizado com sucesso!',
            'event_id' => $eventId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update event in database']);
    }
} catch (Exception $e) {
    error_log('Error updating event: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}