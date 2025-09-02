<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

$eventId = $_GET['id'] ?? 0;
$event = $eventModel->getEventById($eventId);

if (!$event) {
    header('Location: index.php');
    exit;
}

$message = '';

if ($_POST) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $eventDate = $_POST['event_date'];
    $category = trim($_POST['category']);
    $location = trim($_POST['location']);
    $url = trim($_POST['url']);
    $image = $event['image'];
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = '../uploads/';
        $fileName = time() . '_' . $_FILES['image']['name'];
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $image = 'uploads/' . $fileName;
        }
    }
    
    if ($eventModel->updateEvent($eventId, $title, $description, $eventDate, $category, $image, $url, $location)) {
        $message = 'Evento atualizado com sucesso!';
        $event = $eventModel->getEventById($eventId);
    } else {
        $message = 'Erro ao atualizar evento.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Evento - Admin</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Editar Evento</h1>
        <nav>
            <a href="index.php">← Voltar</a>
        </nav>
    </header>

    <main>
        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="event-form">
            <div class="form-group">
                <label for="title">Título *</label>
                <input type="text" id="title" name="title" value="<?= htmlspecialchars($event['title']) ?>" required>
            </div>

            <div class="form-group">
                <label for="event_date">Data do evento *</label>
                <input type="datetime-local" id="event_date" name="event_date" 
                       value="<?= date('Y-m-d\TH:i', strtotime($event['event_date'])) ?>" required>
            </div>

            <div class="form-group">
                <label for="category">Categoria</label>
                <input type="text" id="category" name="category" value="<?= htmlspecialchars($event['category'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="location">Local</label>
                <input type="text" id="location" name="location" value="<?= htmlspecialchars($event['location'] ?? '') ?>" placeholder="ex: Gnration, Theatro Circo">
            </div>

            <div class="form-group">
                <label for="image">Imagem</label>
                <input type="file" id="image" name="image" accept="image/*">
                <?php if ($event['image']): ?>
                    <div class="current-image">
                        <img src="../<?= htmlspecialchars($event['image']) ?>" alt="Current image">
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="description">Descrição</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="url">URL</label>
                <input type="url" id="url" name="url" value="<?= htmlspecialchars($event['url'] ?? '') ?>">
            </div>

            <button type="submit">Atualizar evento</button>
        </form>
    </main>
</body>
</html>