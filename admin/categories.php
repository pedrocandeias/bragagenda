<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';
require_once '../includes/Auth.php';

Auth::requireRole('admin');

$db = new Database();
$eventModel = new Event($db);

$message = '';
$messageType = 'success';

// Handle category operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'merge') {
        $fromCategory = trim($_POST['from_category'] ?? '');
        $toCategory = trim($_POST['to_category'] ?? '');
        
        if ($fromCategory && $toCategory && $fromCategory !== $toCategory) {
            $stmt = $db->getConnection()->prepare("UPDATE events SET category = ? WHERE category = ?");
            $result = $stmt->execute([$toCategory, $fromCategory]);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $message = "Successfully merged '{$fromCategory}' into '{$toCategory}'. Updated {$rowCount} events.";
            } else {
                $message = "Failed to merge categories.";
                $messageType = 'error';
            }
        } else {
            $message = "Please provide valid categories for merging.";
            $messageType = 'error';
        }
    }
    
    if ($action === 'rename') {
        $oldName = trim($_POST['old_name'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        
        if ($oldName && $newName && $oldName !== $newName) {
            $stmt = $db->getConnection()->prepare("UPDATE events SET category = ? WHERE category = ?");
            $result = $stmt->execute([$newName, $oldName]);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $message = "Successfully renamed '{$oldName}' to '{$newName}'. Updated {$rowCount} events.";
            } else {
                $message = "Failed to rename category.";
                $messageType = 'error';
            }
        } else {
            $message = "Please provide valid category names for renaming.";
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete') {
        $categoryToDelete = trim($_POST['category_to_delete'] ?? '');
        $replacementCategory = trim($_POST['replacement_category'] ?? '');
        
        if ($categoryToDelete) {
            if ($replacementCategory) {
                // Move events to replacement category
                $stmt = $db->getConnection()->prepare("UPDATE events SET category = ? WHERE category = ?");
                $result = $stmt->execute([$replacementCategory, $categoryToDelete]);
            } else {
                // Delete events with this category
                $stmt = $db->getConnection()->prepare("DELETE FROM events WHERE category = ?");
                $result = $stmt->execute([$categoryToDelete]);
            }
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                if ($replacementCategory) {
                    $message = "Successfully moved {$rowCount} events from '{$categoryToDelete}' to '{$replacementCategory}'.";
                } else {
                    $message = "Successfully deleted {$rowCount} events with category '{$categoryToDelete}'.";
                }
            } else {
                $message = "Failed to delete category.";
                $messageType = 'error';
            }
        }
    }
}

// Get category statistics
$stmt = $db->getConnection()->query("
    SELECT category, COUNT(*) as event_count 
    FROM events 
    WHERE category IS NOT NULL AND category != '' 
    GROUP BY category 
    ORDER BY event_count DESC, category ASC
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique categories for dropdowns
$allCategories = array_column($categories, 'category');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Categorias - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <?php include '_nav.php'; ?>

        <main class="py-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Categories List -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-tags"></i> Categorias Existentes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($categories)): ?>
                                <p class="text-muted">Nenhuma categoria encontrada.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Categoria</th>
                                                <th class="text-end">Eventos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($category['category']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <?= $category['event_count'] ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Management Tools -->
                <div class="col-lg-6">
                    <!-- Merge Categories -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-arrow-down-up"></i> Fundir Categorias</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="merge">
                                <div class="mb-3">
                                    <label class="form-label">De:</label>
                                    <select name="from_category" class="form-select" required>
                                        <option value="">Selecionar categoria...</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Para:</label>
                                    <select name="to_category" class="form-select" required>
                                        <option value="">Selecionar categoria...</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning btn-sm">
                                    <i class="bi bi-arrow-down-up"></i> Fundir
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Rename Category -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pencil"></i> Renomear Categoria</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="rename">
                                <div class="mb-3">
                                    <label class="form-label">Categoria atual:</label>
                                    <select name="old_name" class="form-select" required>
                                        <option value="">Selecionar categoria...</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Novo nome:</label>
                                    <input type="text" name="new_name" class="form-control" required>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm">
                                    <i class="bi bi-pencil"></i> Renomear
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Delete Category -->
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-trash"></i> Eliminar Categoria</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('Tem certeza? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="action" value="delete">
                                <div class="mb-3">
                                    <label class="form-label">Categoria para eliminar:</label>
                                    <select name="category_to_delete" class="form-select" required>
                                        <option value="">Selecionar categoria...</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mover eventos para (opcional):</label>
                                    <select name="replacement_category" class="form-select">
                                        <option value="">Eliminar eventos também</option>
                                        <?php foreach ($allCategories as $category): ?>
                                            <option value="<?= htmlspecialchars($category) ?>">
                                                <?= htmlspecialchars($category) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Se não selecionar uma categoria de substituição, os eventos serão eliminados.
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>