<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Event.php';
require_once '../includes/Auth.php';

Auth::requireLogin();

$currentUser = Auth::currentUser();
$db          = new Database();
$eventModel  = new Event($db);

$categories = $eventModel->getAllCategories();
$locations  = $eventModel->getAllLocations();

$error  = '';
$values = [];   // re-populate on validation failure

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = [
        'title'       => trim($_POST['title']       ?? ''),
        'event_date'  => trim($_POST['event_date']  ?? ''),
        'start_date'  => trim($_POST['start_date']  ?? ''),
        'end_date'    => trim($_POST['end_date']     ?? ''),
        'category'    => trim($_POST['category']    ?? ''),
        'location'    => trim($_POST['location']    ?? ''),
        'image'       => trim($_POST['image']       ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'url'         => trim($_POST['url']         ?? ''),
    ];

    if ($values['title'] === '') {
        $error = 'O título é obrigatório.';
    } elseif ($values['event_date'] === '') {
        $error = 'A data do evento é obrigatória.';
    } else {
        // Normalise datetime — datetime-local gives "Y-m-d\TH:i", store as "Y-m-d H:i:s"
        $eventDate  = date('Y-m-d H:i:s', strtotime($values['event_date']));
        $startDate  = $values['start_date']  ? date('Y-m-d H:i:s', strtotime($values['start_date']))  : null;
        $endDate    = $values['end_date']    ? date('Y-m-d H:i:s', strtotime($values['end_date']))    : null;

        $ok = $eventModel->createEvent(
            $values['title'],
            $values['description'],
            $eventDate,
            $values['category'],
            $values['image'] ?: null,
            $values['url']   ?: null,
            null,
            $values['location'],
            $currentUser['id'],
            $startDate,
            $endDate
        );

        if ($ok) {
            header('Location: index.php?message=' . urlencode('Evento criado com sucesso.') . '&type=success');
            exit;
        } else {
            $error = 'Erro ao guardar o evento. Tenta novamente.';
        }
    }
}

function val($values, $key) {
    return htmlspecialchars($values[$key] ?? '');
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Evento - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <style>
        .ql-container { font-size: 0.95rem; min-height: 140px; border-bottom-left-radius: 0.375rem; border-bottom-right-radius: 0.375rem; }
        .ql-toolbar { border-top-left-radius: 0.375rem; border-top-right-radius: 0.375rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include '_nav.php'; ?>

    <main class="py-4" style="max-width: 760px;">
        <h2 class="h5 mb-4"><i class="bi bi-calendar-plus me-1"></i> Adicionar Evento</h2>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-4">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-info-circle me-1"></i> Informação básica
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Título <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title"
                               value="<?= val($values, 'title') ?>" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <div id="descriptionEditor"></div>
                        <textarea name="description" id="descriptionHidden" class="d-none"><?= val($values, 'description') ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" name="category">
                                <option value="">— sem categoria —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"
                                        <?= ($values['category'] ?? '') === $c ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Local</label>
                            <select class="form-select" name="location">
                                <option value="">— sem local —</option>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= htmlspecialchars($l) ?>"
                                        <?= ($values['location'] ?? '') === $l ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-calendar-event me-1"></i> Datas
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Data e hora do evento <span class="text-danger">*</span></label>
                        <input type="datetime-local" class="form-control" name="event_date"
                               value="<?= val($values, 'event_date') ?>" required>
                        <div class="form-text">Utilizada como referência principal para ordenação e navegação mensal.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Início <span class="text-muted small">(eventos com vários dias)</span></label>
                            <input type="date" class="form-control" name="start_date"
                                   value="<?= val($values, 'start_date') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fim <span class="text-muted small">(eventos com vários dias)</span></label>
                            <input type="date" class="form-control" name="end_date"
                                   value="<?= val($values, 'end_date') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-link-45deg me-1"></i> Imagem e ligações
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">URL da imagem</label>
                        <input type="url" class="form-control" name="image"
                               value="<?= val($values, 'image') ?>"
                               placeholder="https://exemplo.pt/imagem.jpg"
                               id="imageUrl">
                        <div id="imagePreview" class="mt-2"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">URL do evento</label>
                        <input type="url" class="form-control" name="url"
                               value="<?= val($values, 'url') ?>"
                               placeholder="https://exemplo.pt/evento">
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-calendar-plus"></i> Criar evento
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
const quill = new Quill('#descriptionEditor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link'],
            ['clean'],
        ],
    },
});

// Pre-fill with existing content (on validation failure re-render)
const existing = document.getElementById('descriptionHidden').value;
if (existing) quill.clipboard.dangerouslyPasteHTML(existing);

// Sync to hidden textarea before submit
document.querySelector('form').addEventListener('submit', function () {
    document.getElementById('descriptionHidden').value = quill.getSemanticHTML();
});

document.getElementById('imageUrl').addEventListener('input', function () {
    const preview = document.getElementById('imagePreview');
    const url = this.value.trim();
    if (url) {
        preview.innerHTML = `<img src="${url}" alt="Pré-visualização" class="img-thumbnail" style="max-height:120px;" onerror="this.style.display='none'">`;
    } else {
        preview.innerHTML = '';
    }
});
</script>
</body>
</html>
