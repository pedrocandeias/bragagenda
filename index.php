<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

// Month navigation
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear  = isset($_GET['year'])  ? (int)$_GET['year']  : date('Y');
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');

// Filters
$locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';
$searchQuery    = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$viewMode       = isset($_GET['view']) && in_array($_GET['view'], ['list', 'grid']) ? $_GET['view'] : 'grid';

// Pagination
$page          = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$eventsPerPage = 24;
$offset        = ($page - 1) * $eventsPerPage;

// Data
$totalEvents = $eventModel->getEventsCountByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter, $searchQuery);
$totalPages  = ceil($totalEvents / $eventsPerPage);
$events      = $eventModel->getEventsByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter, $eventsPerPage, $offset, $searchQuery);
$locations   = $eventModel->getAllLocations();
$categories  = $eventModel->getAllCategories();

// Month prev/next
$prevMonth = $currentMonth - 1; $prevYear = $currentYear;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $currentMonth + 1; $nextYear = $currentYear;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

$monthNames = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',    4=>'Abril',
    5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
    9=>'Setembro',10=>'Outubro',  11=>'Novembro', 12=>'Dezembro',
];

function buildNavUrl($month, $year, $location = null, $category = null, $page = 1, $view = null, $search = null) {
    global $locationFilter, $categoryFilter, $viewMode, $searchQuery;
    $params = ['month' => $month, 'year' => $year];
    $loc = $location !== null ? $location : $locationFilter;
    $cat = $category !== null ? $category : $categoryFilter;
    $v   = $view     !== null ? $view     : $viewMode;
    $q   = $search   !== null ? $search   : $searchQuery;
    if ($loc)      $params['location'] = $loc;
    if ($cat)      $params['category'] = $cat;
    if ($page > 1) $params['page']     = $page;
    if ($v !== 'grid') $params['view'] = $v;
    if ($q)        $params['q']        = $q;
    return '?' . http_build_query($params);
}

function buildPageUrl($p) {
    global $currentMonth, $currentYear, $locationFilter, $categoryFilter, $viewMode, $searchQuery;
    return buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, $p, $viewMode, $searchQuery);
}

function chipUrl($type, $value) {
    global $currentMonth, $currentYear, $locationFilter, $categoryFilter, $viewMode, $searchQuery;
    if ($type === 'category') {
        $cat = ($categoryFilter === $value) ? '' : $value;
        return buildNavUrl($currentMonth, $currentYear, $locationFilter, $cat, 1, $viewMode, $searchQuery);
    } else {
        $loc = ($locationFilter === $value) ? '' : $value;
        return buildNavUrl($currentMonth, $currentYear, $loc, $categoryFilter, 1, $viewMode, $searchQuery);
    }
}

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
        if ($sd === $ed) return $sf;
        $my = str_replace(array_keys($monthMap), array_values($monthMap), date('M Y', $startTs));
        return "$sd–$ed $my";
    }
    return "$sf – $ef";
}

$hasFilters = $locationFilter || $categoryFilter || $searchQuery;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braga Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<!-- ── Header ────────────────────────────────────────── -->
<header class="site-header">
    <div class="site-header-inner">
        <div class="page-intro-inner">
            <h1>BragAgenda</h1>
            <p>Eventos e atividades culturais em Braga.</p>
        </div>
        <a href="/admin/" class="admin-link">Admin</a>
    </div>
</header>

<!-- ── Main layout: sidebar + content ────────────────── -->
<div class="page-layout">

    <!-- ── Sidebar ──────────────────────────────────── -->
    <aside class="sidebar">

        <!-- Month navigation -->
        <div class="sidebar-month-nav">
            <a href="<?= buildNavUrl($prevMonth, $prevYear, $locationFilter, $categoryFilter, 1, $viewMode, $searchQuery) ?>" class="month-nav-btn" aria-label="Mês anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="current-month-label"><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></span>
            <a href="<?= buildNavUrl($nextMonth, $nextYear, $locationFilter, $categoryFilter, 1, $viewMode, $searchQuery) ?>" class="month-nav-btn" aria-label="Próximo mês">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <!-- Search -->
        <form method="GET" id="mainForm" action="/" class="sidebar-form">
            <input type="hidden" name="month"    value="<?= $currentMonth ?>">
            <input type="hidden" name="year"     value="<?= $currentYear ?>">
            <?php if ($viewMode !== 'grid'): ?>
            <input type="hidden" name="view"     value="<?= htmlspecialchars($viewMode) ?>">
            <?php endif; ?>
            <input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter) ?>">
            <input type="hidden" name="location" value="<?= htmlspecialchars($locationFilter) ?>">

            <div class="search-section">
                <label class="search-label" for="searchInput">Pesquisar por nome ou local</label>
                <div class="search-input-wrap">
                    <i class="bi bi-search search-icon"></i>
                    <input type="search" name="q" id="searchInput" class="search-input"
                           value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
            </div>
        </form>

        <!-- Filters -->
        <div class="sidebar-filters">

            <!-- Mobile toggle -->
            <button class="mobile-filters-toggle" id="mobileFiltersToggle" type="button">
                <span>Filtros</span>
                <i class="bi bi-chevron-down"></i>
            </button>

            <div class="sidebar-filters-body" id="sidebarFiltersBody">

                <?php if ($hasFilters): ?>
                <div class="filter-clear-row">
                    <a href="<?= buildNavUrl($currentMonth, $currentYear, '', '', 1, $viewMode, '') ?>" class="btn-clear-all">
                        <i class="bi bi-x"></i> Limpar filtros
                    </a>
                </div>
                <?php endif; ?>

                <!-- Category -->
                <div class="filter-section">
                    <button class="filter-section-header accordion-toggle open" type="button">
                        <span>Categoria</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="filter-chips-wrap">
                        <?php foreach ($categories as $cat): ?>
                            <a href="<?= chipUrl('category', $cat) ?>"
                               class="filter-chip<?= $categoryFilter === $cat ? ' selected' : '' ?>">
                                <?= htmlspecialchars($cat) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Location -->
                <div class="filter-section">
                    <button class="filter-section-header accordion-toggle open" type="button">
                        <span>Local</span>
                        <i class="bi bi-chevron-down"></i>
                    </button>
                    <div class="filter-chips-wrap">
                        <?php foreach ($locations as $loc): ?>
                            <a href="<?= chipUrl('location', $loc) ?>"
                               class="filter-chip<?= $locationFilter === $loc ? ' selected' : '' ?>">
                                <?= htmlspecialchars($loc) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /sidebar-filters-body -->
        </div><!-- /sidebar-filters -->

    </aside>

    <!-- ── Main content ──────────────────────────────── -->
    <main class="main-content">

        <!-- Top bar: results + view toggle -->
        <div class="controls-top">
            <span class="results-count"><?= $totalEvents ?> resultado<?= $totalEvents != 1 ? 's' : '' ?></span>
            <div class="view-toggle">
                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, 1, 'grid', $searchQuery) ?>"
                   class="view-btn<?= $viewMode === 'grid' ? ' active' : '' ?>">Grelha</a>
                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, 1, 'list', $searchQuery) ?>"
                   class="view-btn<?= $viewMode === 'list' ? ' active' : '' ?>">Lista</a>
            </div>
        </div>

        <!-- Events -->
        <?php if (empty($events)): ?>
            <div class="no-events">
                <p>Nenhum evento encontrado<?= $hasFilters ? ' para os filtros selecionados' : '' ?>.</p>
                <?php if ($hasFilters): ?>
                    <a href="<?= buildNavUrl($currentMonth, $currentYear, '', '', 1, $viewMode, '') ?>">Ver todos os eventos</a>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="<?= $viewMode === 'list' ? 'events-list' : 'events-grid' ?>">
                <?php foreach ($events as $event): ?>
                <article class="event-item">
                    <a href="<?= htmlspecialchars($event['url'] ?: '#') ?>" target="_blank" rel="noopener" class="event-image-link">
                        <div class="event-image-wrap">
                            <img src="<?= htmlspecialchars($event['image'] ?: 'assets/placeholder-event.svg') ?>"
                                 alt="<?= htmlspecialchars($event['title']) ?>"
                                 class="event-img<?= $event['image'] ? '' : ' event-img--placeholder' ?>" loading="lazy">
                            <?php if ($event['category']): ?>
                                <span class="category-chip"><?= htmlspecialchars($event['category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="event-body">
                        <h3 class="event-title">
                            <a href="<?= htmlspecialchars($event['url'] ?: '#') ?>" target="_blank" rel="noopener">
                                <?= htmlspecialchars($event['title']) ?>
                            </a>
                        </h3>
                        <p class="event-meta-date">
                            <i class="bi bi-calendar3"></i>
                            <?= formatEventDate($event) ?>
                        </p>
                        <?php if ($event['location']): ?>
                        <p class="event-meta-location">
                            <i class="bi bi-geo-alt"></i>
                            <a href="<?= chipUrl('location', $event['location']) ?>">
                                <?= htmlspecialchars($event['location']) ?>
                            </a>
                        </p>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Navegação de páginas">
            <?php if ($page > 1): ?>
                <a href="<?= buildPageUrl($page - 1) ?>" class="page-link-item"><i class="bi bi-chevron-left"></i></a>
            <?php endif; ?>
            <?php
            $startPage = max(1, $page - 2);
            $endPage   = min($totalPages, $page + 2);
            if ($startPage > 1): ?>
                <a href="<?= buildPageUrl(1) ?>" class="page-link-item">1</a>
                <?php if ($startPage > 2): ?><span class="page-link-item disabled">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= buildPageUrl($i) ?>" class="page-link-item<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="page-link-item disabled">…</span><?php endif; ?>
                <a href="<?= buildPageUrl($totalPages) ?>" class="page-link-item"><?= $totalPages ?></a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= buildPageUrl($page + 1) ?>" class="page-link-item"><i class="bi bi-chevron-right"></i></a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

    </main>
</div>

<!-- ── Footer ─────────────────────────────────────────── -->
<footer class="site-footer">
    <p>Braga Agenda — Eventos agregados automaticamente</p>
</footer>

<script>
(function () {
    // Accordion sections
    document.querySelectorAll('.accordion-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.nextElementSibling;
            var collapsed = wrap.classList.toggle('collapsed');
            btn.classList.toggle('open', !collapsed);
        });
    });

    // Mobile filters toggle
    var mobileToggle = document.getElementById('mobileFiltersToggle');
    var filtersBody  = document.getElementById('sidebarFiltersBody');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function () {
            filtersBody.classList.toggle('open');
            mobileToggle.classList.toggle('open');
        });
    }

    // Auto-submit search form on Enter (native) — also submit when search cleared
    var searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('search', function () {
            document.getElementById('mainForm').submit();
        });
    }
})();
</script>

</body>
</html>
