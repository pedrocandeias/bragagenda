<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

// Format date in Portuguese format
function formatAdminDate($dateString) {
    $timestamp = strtotime($dateString);
    $formatted = date('j M Y', $timestamp);
    
    // Portuguese month names
    $monthMap = [
        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
    ];
    
    return str_replace(array_keys($monthMap), array_values($monthMap), $formatted);
}

// Build pagination URL preserving filters
function buildAdminUrl($page, $category = '', $location = '', $search = '', $month = 0, $year = 0) {
    global $categoryFilter, $locationFilter, $searchFilter, $monthFilter, $yearFilter;
    $params = [];
    
    if ($page > 1) $params['page'] = $page;
    if ($category ?: $categoryFilter) $params['category'] = $category ?: $categoryFilter;
    if ($location ?: $locationFilter) $params['location'] = $location ?: $locationFilter;
    if ($search ?: $searchFilter) $params['search'] = $search ?: $searchFilter;
    if ($month ?: $monthFilter) $params['month'] = $month ?: $monthFilter;
    if ($year ?: $yearFilter) $params['year'] = $year ?: $yearFilter;
    
    return '?' . http_build_query($params);
}

// Handle pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$eventsPerPage = 50;
$offset = ($page - 1) * $eventsPerPage;

// Handle filters
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';
$searchFilter = isset($_GET['search']) ? trim($_GET['search']) : '';
$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;

// Build query
$whereClause = '';
$params = [];

if ($categoryFilter) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'category = ?';
    $params[] = $categoryFilter;
}

if ($locationFilter) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'location = ?';
    $params[] = $locationFilter;
}

if ($searchFilter) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . '(title LIKE ? OR description LIKE ?)';
    $params[] = '%' . $searchFilter . '%';
    $params[] = '%' . $searchFilter . '%';
}

if ($monthFilter > 0 && $yearFilter > 0) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'strftime("%Y", event_date) = ? AND strftime("%m", event_date) = ?';
    $params[] = (string)$yearFilter;
    $params[] = str_pad($monthFilter, 2, '0', STR_PAD_LEFT);
} elseif ($yearFilter > 0) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'strftime("%Y", event_date) = ?';
    $params[] = (string)$yearFilter;
} elseif ($monthFilter > 0) {
    $whereClause .= ($whereClause ? ' AND ' : ' WHERE ') . 'strftime("%m", event_date) = ?';
    $params[] = str_pad($monthFilter, 2, '0', STR_PAD_LEFT);
}

// Get total count
$countSql = "SELECT COUNT(*) FROM events" . $whereClause;
$stmt = $db->getConnection()->prepare($countSql);
$stmt->execute($params);
$totalEvents = $stmt->fetchColumn();
$totalPages = ceil($totalEvents / $eventsPerPage);

// Get events
$sql = "SELECT * FROM events" . $whereClause . " ORDER BY event_date DESC LIMIT ? OFFSET ?";
$params[] = $eventsPerPage;
$params[] = $offset;
$stmt = $db->getConnection()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories and locations for filters
$categories = $eventModel->getAllCategories();
$locations = $eventModel->getAllLocations();

// Handle messages
$message = $_GET['message'] ?? '';
$messageType = $_GET['type'] ?? 'success';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Braga Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <header class="py-4 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <h1>Admin - Braga Agenda</h1>
                <nav class="d-flex gap-2 flex-wrap">
                    <a href="../index.php" class="btn btn-outline-primary">
                        <i class="bi bi-globe"></i> Ver site
                    </a>
                    <a href="categories.php" class="btn btn-outline-secondary">
                        <i class="bi bi-tags"></i> Categorias
                    </a>
                    <a href="venues.php" class="btn btn-outline-info">
                        <i class="bi bi-geo-alt"></i> Locais
                    </a>
                    <a href="scrapers.php" class="btn btn-outline-dark">
                        <i class="bi bi-cloud-download"></i> Scrapers
                    </a>
                    <a href="add-event.php" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Adicionar evento
                    </a>
                </nav>
            </div>
        </header>

        <main class="py-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Pesquisar por título ou descrição..." 
                                   value="<?= htmlspecialchars($searchFilter) ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="category" class="form-select">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" 
                                            <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="location" class="form-select">
                                <option value="">Todos os locais</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= htmlspecialchars($location) ?>" 
                                            <?= $locationFilter === $location ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($location) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <select name="month" class="form-select">
                                <option value="">Mês</option>
                                <?php 
                                $monthNames = [
                                    1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
                                    5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
                                    9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
                                ];
                                foreach ($monthNames as $num => $name): ?>
                                    <option value="<?= $num ?>" <?= $monthFilter === $num ? 'selected' : '' ?>>
                                        <?= $name ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <select name="year" class="form-select">
                                <option value="">Ano</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($year = $currentYear - 1; $year <= $currentYear + 2; $year++): ?>
                                    <option value="<?= $year ?>" <?= $yearFilter === $year ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> Pesquisar
                            </button>
                            <?php if ($categoryFilter || $locationFilter || $searchFilter || $monthFilter || $yearFilter): ?>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x"></i> Limpar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Events Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event"></i> 
                        Eventos (<?= number_format($totalEvents) ?>)
                    </h5>
                    <button type="button" class="btn btn-danger btn-sm" id="batchDeleteBtn" disabled>
                        <i class="bi bi-trash"></i> Eliminar selecionados
                    </button>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($events)): ?>
                        <div class="text-center py-5">
                            <p class="text-muted">Nenhum evento encontrado.</p>
                        </div>
                    <?php else: ?>
                        <form id="batchDeleteForm" method="POST" action="batch-delete.php">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">
                                                <input type="checkbox" class="form-check-input" id="selectAll">
                                            </th>
                                            <th>Título</th>
                                            <th>Data</th>
                                            <th>Categoria</th>
                                            <th>Local</th>
                                            <th>Status</th>
                                            <th style="width: 160px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input event-checkbox" 
                                                           name="event_ids[]" value="<?= $event['id'] ?>">
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                    <?php if ($event['description']): ?>
                                                        <br><small class="text-muted">
                                                            <?= htmlspecialchars(substr($event['description'], 0, 100)) ?><?= strlen($event['description']) > 100 ? '...' : '' ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small><?= formatAdminDate($event['event_date']) ?></small>
                                                    <?php if (date('H:i', strtotime($event['event_date'])) !== '00:00'): ?>
                                                        <br><small class="text-muted"><?= date('H:i', strtotime($event['event_date'])) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($event['category']): ?>
                                                        <span class="badge bg-secondary">
                                                            <?= htmlspecialchars($event['category']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($event['location']): ?>
                                                        <small class="text-muted">
                                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($event['featured'])): ?>
                                                        <span class="badge bg-warning text-dark">
                                                            <i class="bi bi-star-fill"></i> Destaque
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($event['hidden'])): ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="bi bi-eye-slash"></i> Oculto
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                title="Editar" onclick="editEvent(<?= $event['id'] ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn <?= !empty($event['featured']) ? 'btn-warning' : 'btn-outline-warning' ?> btn-sm" 
                                                                title="<?= !empty($event['featured']) ? 'Remover destaque' : 'Destacar evento' ?>"
                                                                onclick="toggleEventStatus(<?= $event['id'] ?>, 'toggle-featured')">
                                                            <i class="bi bi-star<?= !empty($event['featured']) ? '-fill' : '' ?>"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn <?= !empty($event['hidden']) ? 'btn-secondary' : 'btn-outline-secondary' ?> btn-sm" 
                                                                title="<?= !empty($event['hidden']) ? 'Mostrar evento' : 'Ocultar evento' ?>"
                                                                onclick="toggleEventStatus(<?= $event['id'] ?>, 'toggle-hidden')">
                                                            <i class="bi bi-eye<?= !empty($event['hidden']) ? '-slash' : '' ?>"></i>
                                                        </button>
                                                        <a href="delete-event.php?id=<?= $event['id'] ?>" 
                                                           class="btn btn-outline-danger btn-sm" title="Eliminar"
                                                           onclick="return confirm('Tem certeza?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildAdminUrl($page - 1) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildAdminUrl(1) ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="<?= buildAdminUrl($i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildAdminUrl($totalPages) ?>"><?= $totalPages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= buildAdminUrl($page + 1) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Batch delete functionality
        const selectAllCheckbox = document.getElementById('selectAll');
        const eventCheckboxes = document.querySelectorAll('.event-checkbox');
        const batchDeleteBtn = document.getElementById('batchDeleteBtn');
        const batchDeleteForm = document.getElementById('batchDeleteForm');
        
        // Only initialize batch delete if elements exist (there are events)
        if (selectAllCheckbox && batchDeleteBtn) {
            function updateBatchDeleteBtn() {
            const checkedBoxes = document.querySelectorAll('.event-checkbox:checked');
            batchDeleteBtn.disabled = checkedBoxes.length === 0;
            batchDeleteBtn.textContent = checkedBoxes.length > 0 
                ? `Eliminar selecionados (${checkedBoxes.length})` 
                : 'Eliminar selecionados';
        }
        
        selectAllCheckbox.addEventListener('change', function() {
            eventCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBatchDeleteBtn();
        });
        
        eventCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkedBoxes = document.querySelectorAll('.event-checkbox:checked');
                selectAllCheckbox.checked = checkedBoxes.length === eventCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < eventCheckboxes.length;
                updateBatchDeleteBtn();
            });
        });
        
        batchDeleteBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.event-checkbox:checked');
            if (checkedBoxes.length > 0) {
                if (confirm(`Tem certeza que deseja eliminar ${checkedBoxes.length} evento(s) selecionado(s)? Esta ação não pode ser desfeita.`)) {
                    batchDeleteForm.submit();
                }
            }
        });
        
            // Initialize
            updateBatchDeleteBtn();
        }
        
        // Edit Event Modal Functions
        function editEvent(eventId) {
            // Clear previous messages
            document.getElementById('editEventMessage').innerHTML = '';
            
            // Load event data via AJAX
            fetch(`edit-event-data.php?id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Erro ao carregar evento: ' + data.error);
                        return;
                    }
                    
                    // Populate form fields
                    document.getElementById('editEventId').value = data.id;
                    document.getElementById('editTitle').value = data.title || '';
                    document.getElementById('editEventDate').value = data.event_date ? 
                        new Date(data.event_date).toISOString().slice(0, 16) : '';
                    document.getElementById('editCategory').value = data.category || '';
                    document.getElementById('editLocation').value = data.location || '';
                    document.getElementById('editDescription').value = data.description || '';
                    document.getElementById('editUrl').value = data.url || '';
                    
                    // Show current image if exists
                    const currentImageDiv = document.getElementById('currentImage');
                    if (data.image) {
                        currentImageDiv.innerHTML = `
                            <div class="current-image-preview">
                                <small class="text-muted">Imagem atual:</small><br>
                                <img src="../${data.image}" alt="Current image" class="img-thumbnail" style="max-width: 150px;">
                            </div>
                        `;
                    } else {
                        currentImageDiv.innerHTML = '';
                    }
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editEventModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading event data:', error);
                    alert('Erro ao carregar dados do evento');
                });
        }
        
        // Handle form submission
        document.getElementById('editEventForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Atualizando...';
            
            // Clear previous messages
            document.getElementById('editEventMessage').innerHTML = '';
            
            fetch('edit-event-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    document.getElementById('editEventMessage').innerHTML = `
                        <div class="alert alert-success">${data.message}</div>
                    `;
                    
                    // Refresh the page after a short delay
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    // Show error message
                    document.getElementById('editEventMessage').innerHTML = `
                        <div class="alert alert-danger">${data.message || 'Erro ao atualizar evento'}</div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error updating event:', error);
                document.getElementById('editEventMessage').innerHTML = `
                    <div class="alert alert-danger">Erro ao comunicar com o servidor</div>
                `;
            })
            .finally(() => {
                // Restore button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });

        // Toggle event status (hide/show, feature/unfeature)
        function toggleEventStatus(eventId, action) {
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            
            // Disable button and show loading
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
            
            fetch('toggle-event-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: eventId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the page to reflect changes
                    window.location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha ao atualizar evento'));
                    // Restore button
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao comunicar com o servidor');
                // Restore button
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            });
        }
    </script>
    
    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editEventForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div id="editEventMessage"></div>
                        
                        <input type="hidden" id="editEventId" name="id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="editTitle" class="form-label">Título *</label>
                                    <input type="text" class="form-control" id="editTitle" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="editEventDate" class="form-label">Data do evento *</label>
                                    <input type="datetime-local" class="form-control" id="editEventDate" name="event_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editCategory" class="form-label">Categoria</label>
                                    <input type="text" class="form-control" id="editCategory" name="category">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editLocation" class="form-label">Local</label>
                                    <input type="text" class="form-control" id="editLocation" name="location" 
                                           placeholder="ex: Gnration, Theatro Circo">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Descrição</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editImage" class="form-label">Imagem</label>
                                    <input type="file" class="form-control" id="editImage" name="image" accept="image/*">
                                    <div id="currentImage" class="mt-2"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="editUrl" class="form-label">URL</label>
                                    <input type="url" class="form-control" id="editUrl" name="url">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Atualizar evento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>