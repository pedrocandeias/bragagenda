<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

$events = $eventModel->getAllEvents();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Braga Agenda</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <header>
        <h1>Admin - Braga Agenda</h1>
        <nav>
            <a href="../index.php">Ver site</a>
            <a href="add-event.php" class="btn">Adicionar evento</a>
        </nav>
    </header>

    <main>
        <table class="events-table">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Data</th>
                    <th>Categoria</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= htmlspecialchars($event['title']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($event['event_date'])) ?></td>
                        <td><?= htmlspecialchars($event['category'] ?? '') ?></td>
                        <td class="actions">
                            <a href="edit-event.php?id=<?= $event['id'] ?>">Editar</a>
                            <a href="delete-event.php?id=<?= $event['id'] ?>" 
                               onclick="return confirm('Tem certeza?')" class="delete">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>