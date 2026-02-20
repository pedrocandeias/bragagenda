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

// Handle venue operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'merge') {
        $fromVenue = trim($_POST['from_venue'] ?? '');
        $toVenue = trim($_POST['to_venue'] ?? '');
        
        if ($fromVenue && $toVenue && $fromVenue !== $toVenue) {
            $stmt = $db->getConnection()->prepare("UPDATE events SET location = ? WHERE location = ?");
            $result = $stmt->execute([$toVenue, $fromVenue]);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $message = "Successfully merged '{$fromVenue}' into '{$toVenue}'. Updated {$rowCount} events.";
            } else {
                $message = "Failed to merge venues.";
                $messageType = 'error';
            }
        } else {
            $message = "Please provide valid venues for merging.";
            $messageType = 'error';
        }
    }
    
    if ($action === 'rename') {
        $oldName = trim($_POST['old_name'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        
        if ($oldName && $newName && $oldName !== $newName) {
            $stmt = $db->getConnection()->prepare("UPDATE events SET location = ? WHERE location = ?");
            $result = $stmt->execute([$newName, $oldName]);
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                $message = "Successfully renamed '{$oldName}' to '{$newName}'. Updated {$rowCount} events.";
            } else {
                $message = "Failed to rename venue.";
                $messageType = 'error';
            }
        } else {
            $message = "Please provide valid venue names for renaming.";
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete') {
        $venueToDelete = trim($_POST['venue_to_delete'] ?? '');
        $replacementVenue = trim($_POST['replacement_venue'] ?? '');
        
        if ($venueToDelete) {
            if ($replacementVenue) {
                // Move events to replacement venue
                $stmt = $db->getConnection()->prepare("UPDATE events SET location = ? WHERE location = ?");
                $result = $stmt->execute([$replacementVenue, $venueToDelete]);
            } else {
                // Set venue to NULL for these events
                $stmt = $db->getConnection()->prepare("UPDATE events SET location = NULL WHERE location = ?");
                $result = $stmt->execute([$venueToDelete]);
            }
            
            if ($result) {
                $rowCount = $stmt->rowCount();
                if ($replacementVenue) {
                    $message = "Successfully moved {$rowCount} events from '{$venueToDelete}' to '{$replacementVenue}'.";
                } else {
                    $message = "Successfully removed venue from {$rowCount} events (venue '{$venueToDelete}').";
                }
            } else {
                $message = "Failed to delete venue.";
                $messageType = 'error';
            }
        }
    }
}

// Get venue statistics
$stmt = $db->getConnection()->query("
    SELECT location, COUNT(*) as event_count 
    FROM events 
    WHERE location IS NOT NULL AND location != '' 
    GROUP BY location 
    ORDER BY event_count DESC, location ASC
");
$venues = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique venues for dropdowns
$allVenues = array_column($venues, 'location');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Locais - Admin</title>
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
                <!-- Venues List -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Locais Existentes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($venues)): ?>
                                <p class="text-muted">Nenhum local encontrado.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Local</th>
                                                <th class="text-end">Eventos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($venues as $venue): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-start">
                                                            <i class="bi bi-geo-alt text-muted me-2 mt-1"></i>
                                                            <div class="venue-name" style="max-width: 300px; word-wrap: break-word; font-size: 0.9em;">
                                                                <?= htmlspecialchars($venue['location']) ?>
                                                                <?php if (strlen($venue['location']) > 60): ?>
                                                                    <small class="d-block text-danger mt-1">
                                                                        <i class="bi bi-exclamation-triangle"></i> Nome longo - considere simplificar
                                                                    </small>
                                                                <?php endif; ?>
                                                                <?php if (preg_match('/https?:\/\//', $venue['location'])): ?>
                                                                    <small class="d-block text-warning mt-1">
                                                                        <i class="bi bi-link"></i> Contém URL - considere limpar
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-end">
                                                        <span class="badge bg-primary">
                                                            <?= $venue['event_count'] ?>
                                                        </span>
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
                    <!-- Rename Venue -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pencil"></i> Renomear Local</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="rename">
                                <div class="mb-3">
                                    <label class="form-label">Local atual:</label>
                                    <select name="old_name" class="form-select" required>
                                        <option value="">Selecionar local...</option>
                                        <?php foreach ($allVenues as $venue): ?>
                                            <option value="<?= htmlspecialchars($venue) ?>">
                                                <?= htmlspecialchars(strlen($venue) > 60 ? substr($venue, 0, 60) . '...' : $venue) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Novo nome:</label>
                                    <input type="text" name="new_name" class="form-control" 
                                           placeholder="ex: Guria Coffee" 
                                           maxlength="100" required>
                                    <small class="form-text text-muted">
                                        Mantenha o nome simples e limpo (ex: "Guria Coffee" em vez de "Guria! Coffee Spot, https://...")
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm">
                                    <i class="bi bi-pencil"></i> Renomear
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Merge Venues -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-arrow-down-up"></i> Fundir Locais</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="merge">
                                <div class="mb-3">
                                    <label class="form-label">De:</label>
                                    <select name="from_venue" class="form-select" required>
                                        <option value="">Selecionar local...</option>
                                        <?php foreach ($allVenues as $venue): ?>
                                            <option value="<?= htmlspecialchars($venue) ?>">
                                                <?= htmlspecialchars(strlen($venue) > 60 ? substr($venue, 0, 60) . '...' : $venue) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Para:</label>
                                    <select name="to_venue" class="form-select" required>
                                        <option value="">Selecionar local...</option>
                                        <?php foreach ($allVenues as $venue): ?>
                                            <option value="<?= htmlspecialchars($venue) ?>">
                                                <?= htmlspecialchars(strlen($venue) > 60 ? substr($venue, 0, 60) . '...' : $venue) ?>
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

                    <!-- Delete Venue -->
                    <div class="card">
                        <div class="card-header bg-danger text-white">
                            <h6 class="mb-0"><i class="bi bi-trash"></i> Eliminar Local</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="return confirm('Tem certeza? Esta ação não pode ser desfeita.')">
                                <input type="hidden" name="action" value="delete">
                                <div class="mb-3">
                                    <label class="form-label">Local para eliminar:</label>
                                    <select name="venue_to_delete" class="form-select" required>
                                        <option value="">Selecionar local...</option>
                                        <?php foreach ($allVenues as $venue): ?>
                                            <option value="<?= htmlspecialchars($venue) ?>">
                                                <?= htmlspecialchars(strlen($venue) > 60 ? substr($venue, 0, 60) . '...' : $venue) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Mover eventos para (opcional):</label>
                                    <select name="replacement_venue" class="form-select">
                                        <option value="">Remover local dos eventos</option>
                                        <?php foreach ($allVenues as $venue): ?>
                                            <option value="<?= htmlspecialchars($venue) ?>">
                                                <?= htmlspecialchars(strlen($venue) > 60 ? substr($venue, 0, 60) . '...' : $venue) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Se não selecionar um local de substituição, o campo local será removido dos eventos.
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

            <!-- Quick Actions for Common Issues -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-lightbulb"></i> Sugestões de Limpeza</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php
                                // Find venues with URLs that need cleaning
                                $venuesWithUrls = array_filter($allVenues, function($venue) {
                                    return preg_match('/https?:\/\//', $venue);
                                });
                                
                                // Find venues with excessive punctuation
                                $venuesWithPunctuation = array_filter($allVenues, function($venue) {
                                    return preg_match('/[!]{2,}|[,]{2,}/', $venue);
                                });
                                
                                // Find very long venue names
                                $longVenues = array_filter($allVenues, function($venue) {
                                    return strlen($venue) > 60;
                                });
                                ?>
                                
                                <?php if (!empty($venuesWithUrls)): ?>
                                <div class="col-md-4">
                                    <h6 class="text-warning"><i class="bi bi-link"></i> Locais com URLs</h6>
                                    <ul class="list-unstyled small">
                                        <?php foreach (array_slice($venuesWithUrls, 0, 3) as $venue): ?>
                                            <li class="mb-1">
                                                <code><?= htmlspecialchars(substr($venue, 0, 50)) ?><?= strlen($venue) > 50 ? '...' : '' ?></code>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($venuesWithUrls) > 3): ?>
                                            <li><small class="text-muted">e mais <?= count($venuesWithUrls) - 3 ?>...</small></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($longVenues)): ?>
                                <div class="col-md-4">
                                    <h6 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Nomes Muito Longos</h6>
                                    <ul class="list-unstyled small">
                                        <?php foreach (array_slice($longVenues, 0, 3) as $venue): ?>
                                            <li class="mb-1">
                                                <code><?= htmlspecialchars(substr($venue, 0, 30)) ?>...</code>
                                                <small class="text-muted">(<?= strlen($venue) ?> chars)</small>
                                            </li>
                                        <?php endforeach; ?>
                                        <?php if (count($longVenues) > 3): ?>
                                            <li><small class="text-muted">e mais <?= count($longVenues) - 3 ?>...</small></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-md-4">
                                    <h6 class="text-info"><i class="bi bi-info-circle"></i> Dicas</h6>
                                    <ul class="small mb-0">
                                        <li>Mantenha nomes simples: "Guria Coffee"</li>
                                        <li>Remova URLs e símbolos extras</li>
                                        <li>Use a função "Renomear" para limpar</li>
                                        <li>Funda locais duplicados/similares</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>