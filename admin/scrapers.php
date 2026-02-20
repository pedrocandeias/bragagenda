<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

Auth::requireRole('admin');

$db  = new Database();
$pdo = $db->getConnection();

$stmt    = $pdo->query("SELECT * FROM sources ORDER BY name");
$sources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Event count per source
$countStmt = $pdo->query("SELECT source_id, COUNT(*) as cnt FROM events GROUP BY source_id");
$eventCounts = [];
foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $eventCounts[$row['source_id']] = $row['cnt'];
}

$activeSources = array_filter($sources, fn($s) => $s['active']);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scrapers - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .scraper-row.running  { background: #fffbea; }
        .scraper-row.done-ok  { background: #f0faf2; }
        .scraper-row.done-err { background: #fff4f4; }

        .result-cell { min-width: 220px; }
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 0.72rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .pill-new     { background: #d1fae5; color: #065f46; }
        .pill-skip    { background: #e5e7eb; color: #374151; }
        .pill-found   { background: #dbeafe; color: #1e40af; }
        .pill-err     { background: #fee2e2; color: #991b1b; }
        .pill-time    { background: #f3f4f6; color: #6b7280; }

        .log-panel {
            background: #111827;
            color: #d1d5db;
            font-family: 'Courier New', monospace;
            font-size: 0.8rem;
            border-radius: 0 0 6px 6px;
            padding: 1rem 1.25rem;
            min-height: 180px;
            max-height: 420px;
            overflow-y: auto;
        }
        .log-line  { margin: 0; line-height: 1.65; }
        .log-ok    { color: #34d399; }
        .log-new   { color: #6ee7b7; font-weight: 600; }
        .log-err   { color: #f87171; }
        .log-warn  { color: #fbbf24; }
        .log-info  { color: #60a5fa; }
        .log-muted { color: #6b7280; }
        .log-head  { color: #a78bfa; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid">
    <?php include '_nav.php'; ?>

    <main class="py-4">

        <!-- Toolbar -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                <?= count($activeSources) ?> scraper<?= count($activeSources) !== 1 ? 's' : '' ?> ativos
            </p>
            <button class="btn btn-success" id="runAllBtn" <?= empty($activeSources) ? 'disabled' : '' ?>>
                <i class="bi bi-play-circle"></i> Executar todos
            </button>
        </div>

        <!-- Scrapers table -->
        <div class="card mb-4">
            <div class="card-body p-0">
                <?php if (empty($sources)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        Nenhum scraper configurado.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nome</th>
                                <th>Classe</th>
                                <th>Última execução</th>
                                <th class="text-center">Total eventos</th>
                                <th>Resultado</th>
                                <th class="text-center">Estado</th>
                                <th style="width:160px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sources as $source): ?>
                            <tr class="scraper-row" data-id="<?= $source['id'] ?>">
                                <td>
                                    <strong><?= htmlspecialchars($source['name']) ?></strong>
                                    <?php if ($source['url']): ?>
                                    <br><a href="<?= htmlspecialchars($source['url']) ?>" target="_blank"
                                           class="text-muted small text-decoration-none">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                        <?= htmlspecialchars(parse_url($source['url'], PHP_URL_HOST) ?: $source['url']) ?>
                                    </a>
                                    <?php endif; ?>
                                </td>
                                <td><code class="small"><?= htmlspecialchars($source['scraper_class']) ?></code></td>
                                <td class="last-run-cell text-muted small">
                                    <?php if ($source['last_scraped']): ?>
                                        <?= date('j M Y, H:i', strtotime($source['last_scraped'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary total-count-badge">
                                        <?= $eventCounts[$source['id']] ?? 0 ?>
                                    </span>
                                </td>
                                <td class="result-cell">
                                    <span class="run-result text-muted small">—</span>
                                </td>
                                <td class="text-center">
                                    <?php if ($source['active']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 align-items-center">
                                        <button class="btn btn-sm btn-outline-primary run-btn"
                                                data-id="<?= $source['id'] ?>"
                                                <?= $source['active'] ? '' : 'disabled' ?>>
                                            <i class="bi bi-play"></i> Executar
                                        </button>
                                        <span class="run-spinner d-none align-items-center gap-1">
                                            <span class="spinner-border spinner-border-sm text-primary"></span>
                                            <span class="run-timer text-muted small" style="font-variant-numeric: tabular-nums; min-width: 2.5rem;">0s</span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Log panel -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center py-2">
                <span class="fw-semibold"><i class="bi bi-terminal me-1"></i> Log de execução</span>
                <button class="btn btn-sm btn-outline-secondary" id="clearLogBtn">
                    <i class="bi bi-trash"></i> Limpar
                </button>
            </div>
            <div class="log-panel" id="logPanel">
                <p class="log-line log-muted">Aguardando execução...</p>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const logPanel = document.getElementById('logPanel');

function log(text, cls = '') {
    const p = document.createElement('p');
    p.className = 'log-line' + (cls ? ' ' + cls : '');
    p.textContent = text;
    logPanel.appendChild(p);
    logPanel.scrollTop = logPanel.scrollHeight;
}

function logSep(label = '') {
    log('');
    const bar = '─'.repeat(label ? Math.max(2, 52 - label.length - 3) : 54);
    log((label ? `── ${label} ` : '') + bar, 'log-head');
}

function clearLog() {
    logPanel.innerHTML = '';
}

// ── Render inline result pills in the table row ──────────────────
function renderRowResult(entry) {
    const row = document.querySelector(`.scraper-row[data-id="${entry.id}"]`);
    if (!row) return;

    const cell = row.querySelector('.run-result');
    if (!cell) return;

    if (entry.error) {
        cell.innerHTML = pill('pill-err', '✗', entry.error.length > 40
            ? entry.error.slice(0, 40) + '…' : entry.error);
        return;
    }

    const parts = [];
    if (entry.events_found !== null) {
        parts.push(pill('pill-found', '↓', entry.events_found + ' encontrados'));
    }
    parts.push(pill('pill-new',  '+', entry.events_new  + ' novos'));
    if (entry.events_skipped !== null && entry.events_skipped > 0) {
        parts.push(pill('pill-skip', '≡', entry.events_skipped + ' duplicados'));
    }
    if (entry.errors && entry.errors.length) {
        parts.push(pill('pill-err', '⚠', entry.errors.length + ' erros'));
    }
    parts.push(pill('pill-time', '⏱', entry.duration + 's'));
    cell.innerHTML = parts.join(' ');

    // Update the total count badge
    const badge = row.querySelector('.total-count-badge');
    if (badge) badge.textContent = entry.events_total;
}

function pill(cls, icon, label) {
    return `<span class="stat-pill ${cls}">${icon} ${label}</span>`;
}

// ── Render detailed log entry ────────────────────────────────────
function logResult(entry) {
    logSep(entry.name);

    if (entry.error) {
        log('  ✗ ' + entry.error, 'log-err');
        return;
    }

    // Stats line
    const found   = entry.events_found   !== null ? entry.events_found   : '?';
    const newEvts = entry.events_new;
    const skipped = entry.events_skipped !== null ? entry.events_skipped : '?';

    log(`  ↓ Encontrados : ${found}`, 'log-info');
    log(`  + Novos       : ${newEvts}`, newEvts > 0 ? 'log-new' : 'log-muted');
    log(`  ≡ Duplicados  : ${skipped}`, 'log-muted');
    log(`  # Total BD    : ${entry.events_total}`, 'log-muted');
    log(`  ⏱ Duração     : ${entry.duration}s`, 'log-muted');

    if (entry.errors && entry.errors.length) {
        log('', '');
        log(`  ⚠ ${entry.errors.length} erro(s):`, 'log-warn');
        entry.errors.forEach(e => log('    · ' + e, 'log-err'));
    }
}

// ── Per-row elapsed timers ───────────────────────────────────────
const rowTimers = {};

function startTimer(id) {
    const row = document.querySelector(`.scraper-row[data-id="${id}"]`);
    if (!row) return;
    const el = row.querySelector('.run-timer');
    if (!el) return;
    const start = Date.now();
    el.textContent = '0s';
    rowTimers[id] = setInterval(() => {
        el.textContent = Math.floor((Date.now() - start) / 1000) + 's';
    }, 1000);
}

function stopTimer(id) {
    clearInterval(rowTimers[id]);
    delete rowTimers[id];
}

// ── Row state (running / ok / err) ──────────────────────────────
function setRowState(id, state) {
    const row = document.querySelector(`.scraper-row[data-id="${id}"]`);
    if (!row) return;
    row.classList.remove('running', 'done-ok', 'done-err');
    const btn     = row.querySelector('.run-btn');
    const spinner = row.querySelector('.run-spinner');
    if (state === 'running') {
        row.classList.add('running');
        btn.classList.add('d-none');
        spinner.classList.remove('d-none');
        row.querySelector('.run-result').innerHTML = '<span class="text-muted small">A executar…</span>';
        startTimer(id);
    } else {
        stopTimer(id);
        btn.classList.remove('d-none');
        spinner.classList.add('d-none');
        if (state === 'ok')  row.classList.add('done-ok');
        if (state === 'err') row.classList.add('done-err');
    }
}

function updateLastRun(id) {
    const row = document.querySelector(`.scraper-row[data-id="${id}"]`);
    if (!row) return;
    const cell = row.querySelector('.last-run-cell');
    if (cell) cell.textContent = new Date().toLocaleString('pt-PT', {
        day: 'numeric', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

// ── Fetch helpers ────────────────────────────────────────────────
async function runScraper(sourceId) {
    setRowState(sourceId, 'running');
    try {
        const resp = await fetch('run-scraper-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ source_id: sourceId }),
        });
        const data = await resp.json();
        if (data.success && data.results) {
            data.results.forEach(entry => {
                setRowState(entry.id, entry.error ? 'err' : 'ok');
                updateLastRun(entry.id);
                renderRowResult(entry);
                logResult(entry);
            });
        } else {
            setRowState(sourceId, 'err');
            log('✗ ' + (data.error || 'Erro desconhecido'), 'log-err');
        }
    } catch (e) {
        setRowState(sourceId, 'err');
        log('✗ Erro de rede: ' + e.message, 'log-err');
    }
}

async function runAll() {
    document.querySelectorAll('.scraper-row').forEach(row => {
        if (!row.querySelector('.run-btn').disabled) setRowState(row.dataset.id, 'running');
    });

    const btn = document.getElementById('runAllBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A executar…';

    try {
        const resp = await fetch('run-scraper-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ run_all: true }),
        });
        const data = await resp.json();
        if (data.success && data.results) {
            let totalNew = 0, totalErrors = 0;
            data.results.forEach(entry => {
                setRowState(entry.id, entry.error ? 'err' : 'ok');
                updateLastRun(entry.id);
                renderRowResult(entry);
                logResult(entry);
                totalNew    += entry.events_new ?? 0;
                totalErrors += entry.error ? 1 : (entry.errors?.length ?? 0);
            });
            // Summary
            log('');
            logSep('Resumo');
            log(`  Scrapers executados : ${data.results.length}`, 'log-info');
            log(`  Eventos novos total : ${totalNew}`, totalNew > 0 ? 'log-new' : 'log-muted');
            if (totalErrors > 0) log(`  Erros               : ${totalErrors}`, 'log-warn');
            log(`  Concluído às ${new Date().toLocaleTimeString('pt-PT')}`, 'log-muted');
        } else {
            log('✗ ' + (data.error || 'Erro desconhecido'), 'log-err');
        }
    } catch (e) {
        log('✗ Erro de rede: ' + e.message, 'log-err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-circle"></i> Executar todos';
    }
}

// ── Event listeners ──────────────────────────────────────────────
document.querySelectorAll('.run-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        clearLog();
        runScraper(parseInt(btn.dataset.id));
    });
});

document.getElementById('runAllBtn').addEventListener('click', () => {
    clearLog();
    runAll();
});

document.getElementById('clearLogBtn').addEventListener('click', () => {
    clearLog();
    log('Aguardando execução...', 'log-muted');
});
</script>
</body>
</html>
