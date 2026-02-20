<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Event.php';

$db         = new Database();
$pdo        = $db->getConnection();
$eventModel = new Event($db);

// ── Route: /evento/123 or ?id=123 ─────────────────────────────────────────────
$_requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!isset($_GET['id']) && preg_match('#^/evento/(\d+)/?$#', $_requestPath, $_m)) {
    $_GET['id'] = $_m[1];
}

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = $id ? $eventModel->getEventById($id) : null;

if (!$event || !empty($event['hidden'])) {
    http_response_code(404);
    include '404.php';
    exit;
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function formatEventDate($event) {
    $startDate = $event['start_date'] ?: $event['event_date'];
    $endDate   = $event['end_date']   ?: $event['event_date'];
    $startTs   = strtotime($startDate);
    $endTs     = strtotime($endDate);
    $monthMap  = [
        'Jan'=>'Jan','Feb'=>'Fev','Mar'=>'Mar','Apr'=>'Abr',
        'May'=>'Mai','Jun'=>'Jun','Jul'=>'Jul','Aug'=>'Ago',
        'Sep'=>'Set','Oct'=>'Out','Nov'=>'Nov','Dec'=>'Dez',
    ];
    if (date('Y-m-d', $startTs) === date('Y-m-d', $endTs)) {
        $d = str_replace(array_keys($monthMap), array_values($monthMap), date('j M Y', $startTs));
        $t = date('H:i', $startTs);
        return $t !== '00:00' ? "$d, $t" : $d;
    }
    $sf = str_replace(array_keys($monthMap), array_values($monthMap), date('j M Y', $startTs));
    $ef = str_replace(array_keys($monthMap), array_values($monthMap), date('j M Y', $endTs));
    if (date('Y-m', $startTs) === date('Y-m', $endTs)) {
        $sd = date('j', $startTs); $ed = date('j', $endTs);
        $my = str_replace(array_keys($monthMap), array_values($monthMap), date('M Y', $startTs));
        return "$sd–$ed $my";
    }
    return "$sf – $ef";
}

// ── Related events ─────────────────────────────────────────────────────────────
// Same category, same month, visible, excluding this event — up to 4
$relatedEvents = [];
$eventMonth = date('n', strtotime($event['event_date']));
$eventYear  = date('Y', strtotime($event['event_date']));

$relSql = "SELECT * FROM events
           WHERE (hidden IS NULL OR hidden = 0)
             AND id != :id
             AND strftime('%Y-%m', event_date) = :ym";
$relParams = [':id' => $id, ':ym' => sprintf('%04d-%02d', $eventYear, $eventMonth)];

if ($event['category']) {
    $relSql .= " AND category = :cat";
    $relParams[':cat'] = $event['category'];
}
$relSql .= " ORDER BY featured DESC, event_date ASC LIMIT 4";

$relStmt = $pdo->prepare($relSql);
$relStmt->execute($relParams);
$relatedEvents = $relStmt->fetchAll(PDO::FETCH_ASSOC);

// ── Back link ─────────────────────────────────────────────────────────────────
$backUrl = '/' . sprintf('%02d', $eventMonth) . '/' . $eventYear;

// ── Image ─────────────────────────────────────────────────────────────────────
$img = $event['image'];
if ($img && !preg_match('#^https?://#', $img)) $img = '/' . ltrim($img, '/');
$isPlaceholder = !$event['image'];

// ── Page title ────────────────────────────────────────────────────────────────
$pageTitle = htmlspecialchars($event['title']) . ' — Braga Agenda';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <meta name="description" content="<?= htmlspecialchars(strip_tags(substr($event['description'] ?? '', 0, 160))) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- ── Header ────────────────────────────────────────── -->
<header class="site-header">
    <div class="site-header-inner">
        <div class="page-intro-inner">
            <h1><a href="/">BragAgenda</a></h1>
            <p>Eventos e atividades culturais em Braga.</p>
        </div>
        <a href="/admin/" class="admin-link">Admin</a>
    </div>
</header>

<!-- ── Single event ──────────────────────────────────── -->
<div class="event-detail-page">

    <!-- Breadcrumb -->
    <nav class="event-detail-breadcrumb">
        <a href="<?= $backUrl ?>"><i class="bi bi-arrow-left"></i> Agenda</a>
        <?php if ($event['category']): ?>
            <span class="breadcrumb-sep">/</span>
            <span><?= htmlspecialchars($event['category']) ?></span>
        <?php endif; ?>
    </nav>

    <!-- Hero -->
    <div class="event-detail-hero">
        <div class="event-detail-hero-text">
            <?php if ($event['category']): ?>
                <span class="event-detail-category"><?= htmlspecialchars($event['category']) ?></span>
            <?php endif; ?>
            <h2 class="event-detail-title"><?= htmlspecialchars($event['title']) ?></h2>
            <?php if ($event['location']): ?>
                <p class="event-detail-venue"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?></p>
            <?php endif; ?>
        </div>
        <div class="event-detail-hero-image">
            <img src="<?= htmlspecialchars($img ?: '/assets/placeholder-event.svg') ?>"
                 alt="<?= htmlspecialchars($event['title']) ?>"
                 class="event-detail-img<?= $isPlaceholder ? ' event-img--placeholder' : '' ?>">
        </div>
    </div>

    <!-- Body: description + meta -->
    <div class="event-detail-body-wrap">

        <div class="event-detail-description">
            <?php if ($event['description']): ?>
                <?php
                // Render as HTML if it looks like markup, otherwise as plain text
                $desc = $event['description'];
                if (strip_tags($desc) === $desc) {
                    echo nl2br(htmlspecialchars($desc));
                } else {
                    echo $desc; // already HTML (from Quill editor)
                }
                ?>
            <?php else: ?>
                <p class="event-detail-no-desc">Sem descrição disponível.</p>
            <?php endif; ?>
        </div>

        <aside class="event-detail-meta">
            <dl class="event-meta-list">
                <div class="event-meta-row">
                    <dt><i class="bi bi-calendar3"></i> Data</dt>
                    <dd><?= formatEventDate($event) ?></dd>
                </div>
                <?php if ($event['location']): ?>
                <div class="event-meta-row">
                    <dt><i class="bi bi-geo-alt"></i> Local</dt>
                    <dd><a href="/<?= sprintf('%02d', $eventMonth) ?>/<?= $eventYear ?>/<?= rawurlencode($event['location']) ?>"><?= htmlspecialchars($event['location']) ?></a></dd>
                </div>
                <?php endif; ?>
                <?php if ($event['category']): ?>
                <div class="event-meta-row">
                    <dt><i class="bi bi-tag"></i> Categoria</dt>
                    <dd><a href="/<?= sprintf('%02d', $eventMonth) ?>/<?= $eventYear ?>/<?= rawurlencode($event['category']) ?>"><?= htmlspecialchars($event['category']) ?></a></dd>
                </div>
                <?php endif; ?>
                <?php if ($event['url']): ?>
                <div class="event-meta-row">
                    <dt><i class="bi bi-box-arrow-up-right"></i> Mais info</dt>
                    <dd><a href="<?= htmlspecialchars($event['url']) ?>" target="_blank" rel="noopener">Site oficial</a></dd>
                </div>
                <?php endif; ?>
            </dl>
        </aside>

    </div>

    <!-- Related events -->
    <?php if ($relatedEvents): ?>
    <section class="event-detail-related">
        <h3 class="event-detail-related-title">Outros eventos <?= $event['category'] ? 'de ' . htmlspecialchars($event['category']) : 'este mês' ?></h3>
        <div class="event-detail-related-grid">
            <?php foreach ($relatedEvents as $rel):
                $relImg = $rel['image'];
                if ($relImg && !preg_match('#^https?://#', $relImg)) $relImg = '/' . ltrim($relImg, '/');
                $relUrl = $rel['url'] ?: '/evento/' . $rel['id'];
                $relTarget = $rel['url'] ? ' target="_blank" rel="noopener"' : '';
            ?>
            <article class="event-item">
                <a href="<?= htmlspecialchars($relUrl) ?>"<?= $relTarget ?> class="event-image-link">
                    <div class="event-image-wrap">
                        <img src="<?= htmlspecialchars($relImg ?: '/assets/placeholder-event.svg') ?>"
                             alt="<?= htmlspecialchars($rel['title']) ?>"
                             class="event-img<?= $rel['image'] ? '' : ' event-img--placeholder' ?>" loading="lazy">
                        <?php if ($rel['category']): ?>
                            <span class="category-chip"><?= htmlspecialchars($rel['category']) ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="event-body">
                    <h3 class="event-title">
                        <a href="<?= htmlspecialchars($relUrl) ?>"<?= $relTarget ?>>
                            <?= htmlspecialchars($rel['title']) ?>
                        </a>
                    </h3>
                    <p class="event-meta-date">
                        <i class="bi bi-calendar3"></i>
                        <?= formatEventDate($rel) ?>
                    </p>
                    <?php if ($rel['location']): ?>
                    <p class="event-meta-location">
                        <i class="bi bi-geo-alt"></i>
                        <?= htmlspecialchars($rel['location']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>

<!-- ── Footer ─────────────────────────────────────────── -->
<footer class="site-footer">
    <p>Braga Agenda — Eventos agregados automaticamente</p>
</footer>

</body>
</html>
