<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

// Convert a category/location name to a URL slug: "Música" → "musica"
function slugify(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

// Build a clean URL: /MM/YYYY[/cat-slug[/loc-slug]][?page=...&view=...&q=...&interval=...&from=...&to=...]
// Single filter (cat OR loc) uses one segment; both use two segments (cat first).
function buildNavUrl($month, $year, $location = null, $category = null, $page = 1, $view = null, $search = null, $interval = null, $from = null, $to = null) {
    global $locationFilter, $categoryFilter, $viewMode, $searchQuery, $currentInterval, $fromDate, $toDate;
    $loc = $location !== null ? $location : $locationFilter;
    $cat = $category !== null ? $category : $categoryFilter;
    $v   = $view     !== null ? $view     : $viewMode;
    $q   = $search   !== null ? $search   : $searchQuery;
    $iv  = $interval !== null ? $interval : $currentInterval;
    $f   = $from     !== null ? $from     : $fromDate;
    $t   = $to       !== null ? $to       : $toDate;

    $path = '/' . sprintf('%02d', $month) . '/' . $year;
    if ($cat !== '' && $loc !== '') {
        $path .= '/' . slugify($cat) . '/' . slugify($loc);
    } elseif ($cat !== '') {
        $path .= '/' . slugify($cat);
    } elseif ($loc !== '') {
        $path .= '/' . slugify($loc);
    }

    $params = [];
    if ($page > 1)      $params['page']     = $page;
    if ($v !== 'grid')  $params['view']      = $v;
    if ($q)             $params['q']         = $q;
    if ($iv && $iv !== 'month') {
        $params['interval'] = $iv;
        if ($f) $params['from'] = $f;
        if ($iv === 'range' && $t) $params['to'] = $t;
    }

    return $path . ($params ? '?' . http_build_query($params) : '');
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

// ── Parse clean URL path as fallback (for servers without mod_rewrite) ───────
// When Apache mod_rewrite is active it populates $_GET[month/year/category/location].
// When using the PHP built-in dev server (php -S … index.php), REQUEST_URI carries
// the path but $_GET only has the query string, so we parse the path ourselves.
$_requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Single event page — nginx routes /evento/ID here; redirect to the real file.
if (preg_match('#^/evento/(\d+)/?$#', $_requestPath, $_em)) {
    header('Location: /evento.php?id=' . (int)$_em[1], true, 301);
    exit;
}

if (!isset($_GET['month']) && !isset($_GET['year'])) {
    if (preg_match('#^/(\d{1,2})/(\d{4})/([^/]+)/([^/]+)/?$#', $_requestPath, $_pm)) {
        $_GET['month']    = $_pm[1];
        $_GET['year']     = $_pm[2];
        $_GET['category'] = $_pm[3];
        $_GET['location'] = $_pm[4];
    } elseif (preg_match('#^/(\d{1,2})/(\d{4})/([^/]+)/?$#', $_requestPath, $_pm)) {
        $_GET['month']    = $_pm[1];
        $_GET['year']     = $_pm[2];
        $_GET['category'] = $_pm[3];
    } elseif (preg_match('#^/(\d{1,2})/(\d{4})/?$#', $_requestPath, $_pm)) {
        $_GET['month'] = $_pm[1];
        $_GET['year']  = $_pm[2];
    }
}

// ── Interval + date range ─────────────────────────────────────
// Default to 'week' only on the root page (no month/year in URL).
// Explicit /MM/YYYY paths default to 'month'; explicit ?interval= overrides everything.
if (isset($_GET['interval']) && in_array($_GET['interval'], ['month', 'week', 'day', 'range'])) {
    $currentInterval = $_GET['interval'];
} elseif (!isset($_GET['month']) && !isset($_GET['year'])) {
    $currentInterval = 'week';
} else {
    $currentInterval = 'month';
}
$fromDate = '';
$toDate   = '';

if ($currentInterval === 'month') {
    $currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $currentYear  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
    if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = (int)date('n');
    if ($currentYear < 2020 || $currentYear > 2030) $currentYear = (int)date('Y');
} elseif ($currentInterval === 'week') {
    $fromRaw = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-d');
    $fromTs  = strtotime($fromRaw) ?: time();
    $dow     = (int)date('N', $fromTs); // 1=Mon
    $fromTs  = strtotime('-' . ($dow - 1) . ' days', $fromTs);
    $fromDate     = date('Y-m-d', $fromTs);
    $toDate       = date('Y-m-d', strtotime('+6 days', $fromTs));
    $currentMonth = (int)date('n', $fromTs);
    $currentYear  = (int)date('Y', $fromTs);
} elseif ($currentInterval === 'day') {
    $fromRaw = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-d');
    $fromTs  = strtotime($fromRaw) ?: time();
    $fromDate     = date('Y-m-d', $fromTs);
    $toDate       = $fromDate;
    $currentMonth = (int)date('n', $fromTs);
    $currentYear  = (int)date('Y', $fromTs);
} else { // range
    $fromRaw = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-01');
    $toRaw   = !empty($_GET['to'])   ? $_GET['to']   : date('Y-m-t');
    $fromTs  = strtotime($fromRaw) ?: strtotime(date('Y-m-01'));
    $toTs    = strtotime($toRaw);
    if (!$toTs || $toTs < $fromTs) $toTs = $fromTs;
    $fromDate     = date('Y-m-d', $fromTs);
    $toDate       = date('Y-m-d', $toTs);
    $currentMonth = (int)date('n', $fromTs);
    $currentYear  = (int)date('Y', $fromTs);
}

// ── Filters ───────────────────────────────────────────────────
$rawCategory = isset($_GET['category']) ? trim($_GET['category']) : '';
$rawLocation = isset($_GET['location']) ? trim($_GET['location']) : '';
$searchQuery = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$viewMode    = isset($_GET['view']) && in_array($_GET['view'], ['list', 'grid']) ? $_GET['view'] : 'grid';

// Load categories + locations before slug resolution and rendering
$categories = $eventModel->getAllCategories();
$locations  = $eventModel->getAllLocations();

// Resolve slugs → real names stored in the DB
// 4-segment URL: category=$3&location=$4 — resolve each against its own list
// 2-segment URL: category=$3 only — try categories first, then locations (for location-only URLs)
$categoryFilter = '';
$locationFilter = '';

if ($rawLocation !== '') {
    $rawLocSlug = slugify($rawLocation);
    foreach ($locations as $loc) {
        if (slugify($loc) === $rawLocSlug) { $locationFilter = $loc; break; }
    }
    if ($locationFilter === '') $locationFilter = $rawLocation;
}

if ($rawCategory !== '') {
    $rawSlug = slugify($rawCategory);
    foreach ($categories as $cat) {
        if (slugify($cat) === $rawSlug) { $categoryFilter = $cat; break; }
    }
    if ($categoryFilter === '' && $rawLocation === '') {
        foreach ($locations as $loc) {
            if (slugify($loc) === $rawSlug) { $locationFilter = $loc; break; }
        }
    }
    if ($categoryFilter === '' && $locationFilter === '') {
        foreach ($categories as $cat) {
            if (mb_strtolower($cat, 'UTF-8') === mb_strtolower($rawCategory, 'UTF-8')) {
                $categoryFilter = $cat; break;
            }
        }
        if ($categoryFilter === '') $categoryFilter = $rawCategory;
    }
}

// ── Pagination ────────────────────────────────────────────────
$page          = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$eventsPerPage = 24;
$offset        = ($page - 1) * $eventsPerPage;

// ── Data ──────────────────────────────────────────────────────
if ($currentInterval === 'month') {
    $totalEvents = $eventModel->getEventsCountByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter, $searchQuery);
    $events      = $eventModel->getEventsByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter, $eventsPerPage, $offset, $searchQuery);
} else {
    $totalEvents = $eventModel->getEventsCountByDateRange($fromDate, $toDate, $locationFilter, $categoryFilter, $searchQuery);
    $events      = $eventModel->getEventsByDateRange($fromDate, $toDate, $locationFilter, $categoryFilter, $eventsPerPage, $offset, $searchQuery);
}
$totalPages = (int)ceil($totalEvents / $eventsPerPage);

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

$monthNames = [
    1=>'Janeiro', 2=>'Fevereiro', 3=>'Março',    4=>'Abril',
    5=>'Maio',    6=>'Junho',     7=>'Julho',     8=>'Agosto',
    9=>'Setembro',10=>'Outubro',  11=>'Novembro', 12=>'Dezembro',
];

// ── Period label ──────────────────────────────────────────────
function formatPeriodLabel($interval, $monthNames, $currentMonth, $currentYear, $fromDate = '', $toDate = '') {
    if ($interval === 'month') {
        return $monthNames[$currentMonth] . ' ' . $currentYear;
    }
    $months = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    $days   = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
    $fmt = function($ts) use ($months) {
        return date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
    };
    $fmtShort = function($ts) use ($months) {
        return date('j', $ts) . ' ' . $months[(int)date('n', $ts) - 1];
    };
    if ($interval === 'day') {
        $ts = strtotime($fromDate);
        return $fmt($ts) . ', ' . $days[(int)date('w', $ts)];
    }
    $fromTs = strtotime($fromDate);
    $toTs   = strtotime($toDate);
    if (date('Y-m', $fromTs) === date('Y-m', $toTs)) {
        $my = $months[(int)date('n', $fromTs) - 1] . ' ' . date('Y', $fromTs);
        return date('j', $fromTs) . '–' . date('j', $toTs) . ' ' . $my;
    }
    if (date('Y', $fromTs) === date('Y', $toTs)) {
        return $fmtShort($fromTs) . ' – ' . $fmt($toTs);
    }
    return $fmt($fromTs) . ' – ' . $fmt($toTs);
}

$periodLabel = formatPeriodLabel($currentInterval, $monthNames, $currentMonth, $currentYear, $fromDate, $toDate);

// ── Month prev/next (used for month mode + reference) ─────────
$prevMonth = $currentMonth - 1; $prevYear = $currentYear;
if ($prevMonth < 1)  { $prevMonth = 12; $prevYear--; }
$nextMonth = $currentMonth + 1; $nextYear = $currentYear;
if ($nextMonth > 12) { $nextMonth = 1;  $nextYear++; }

// ── Prev/next nav URLs ────────────────────────────────────────
if ($currentInterval === 'month') {
    $prevUrl = buildNavUrl($prevMonth, $prevYear, $locationFilter, $categoryFilter, 1, $viewMode, $searchQuery, 'month');
    $nextUrl = buildNavUrl($nextMonth, $nextYear, $locationFilter, $categoryFilter, 1, $viewMode, $searchQuery, 'month');
} elseif ($currentInterval === 'week') {
    $p = strtotime('-7 days', strtotime($fromDate));
    $n = strtotime('+7 days', strtotime($fromDate));
    $prevUrl = buildNavUrl((int)date('n',$p),(int)date('Y',$p),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'week',date('Y-m-d',$p));
    $nextUrl = buildNavUrl((int)date('n',$n),(int)date('Y',$n),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'week',date('Y-m-d',$n));
} elseif ($currentInterval === 'day') {
    $p = strtotime('-1 day', strtotime($fromDate));
    $n = strtotime('+1 day', strtotime($fromDate));
    $prevUrl = buildNavUrl((int)date('n',$p),(int)date('Y',$p),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'day',date('Y-m-d',$p));
    $nextUrl = buildNavUrl((int)date('n',$n),(int)date('Y',$n),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'day',date('Y-m-d',$n));
} else { // range
    $fromTs   = strtotime($fromDate);
    $toTs     = strtotime($toDate);
    $rangeLen = max(0, (int)(($toTs - $fromTs) / 86400));
    $p0 = strtotime('-' . ($rangeLen + 1) . ' days', $fromTs);
    $p1 = strtotime('-1 day', $fromTs);
    $n0 = strtotime('+1 day', $toTs);
    $n1 = strtotime('+' . $rangeLen . ' days', $toTs);
    $prevUrl = buildNavUrl((int)date('n',$p0),(int)date('Y',$p0),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'range',date('Y-m-d',$p0),date('Y-m-d',$p1));
    $nextUrl = buildNavUrl((int)date('n',$n0),(int)date('Y',$n0),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'range',date('Y-m-d',$n0),date('Y-m-d',$n1));
}

// ── Interval tab switch URLs ──────────────────────────────────
// Reference date: today if currently in the displayed month, else first of that month
if ($currentInterval !== 'month') {
    $refDate = $fromDate;
} else {
    $isCurrentMonth = ((int)date('n') === $currentMonth && (int)date('Y') === $currentYear);
    $refDate = $isCurrentMonth ? date('Y-m-d') : sprintf('%04d-%02d-01', $currentYear, $currentMonth);
}
$refTs      = strtotime($refDate);
$refDow     = (int)date('N', $refTs); // 1=Mon
$wMondayTs  = strtotime('-' . ($refDow - 1) . ' days', $refTs);
$wMonday    = date('Y-m-d', $wMondayTs);
$rangeFrom  = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
$rangeTo    = date('Y-m-t', strtotime($rangeFrom));

$tabMonthUrl = buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, 1, $viewMode, $searchQuery, 'month');
$tabWeekUrl  = buildNavUrl((int)date('n',$wMondayTs),(int)date('Y',$wMondayTs),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'week',$wMonday);
$tabDayUrl   = buildNavUrl((int)date('n',$refTs),(int)date('Y',$refTs),$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'day',$refDate);
$tabRangeUrl = buildNavUrl($currentMonth,$currentYear,$locationFilter,$categoryFilter,1,$viewMode,$searchQuery,'range',$rangeFrom,$rangeTo);

// ── Form action base path (shared by search form + range picker) ─
$formActionPath = '/' . sprintf('%02d', $currentMonth) . '/' . $currentYear;
if ($categoryFilter !== '' && $locationFilter !== '') {
    $formActionPath .= '/' . slugify($categoryFilter) . '/' . slugify($locationFilter);
} elseif ($categoryFilter !== '') {
    $formActionPath .= '/' . slugify($categoryFilter);
} elseif ($locationFilter !== '') {
    $formActionPath .= '/' . slugify($locationFilter);
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

<!-- ── Main layout: sidebar + content ────────────────── -->
<div class="page-layout">

    <!-- ── Sidebar ──────────────────────────────────── -->
    <aside class="sidebar">

        <!-- Interval tabs -->
        <div class="interval-tabs">
            <a href="<?= $tabMonthUrl ?>" class="interval-tab<?= $currentInterval === 'month' ? ' active' : '' ?>">Mês</a>
            <a href="<?= $tabWeekUrl  ?>" class="interval-tab<?= $currentInterval === 'week'  ? ' active' : '' ?>">Semana</a>
            <a href="<?= $tabDayUrl   ?>" class="interval-tab<?= $currentInterval === 'day'   ? ' active' : '' ?>">Dia</a>
            <a href="<?= $tabRangeUrl ?>" class="interval-tab<?= $currentInterval === 'range' ? ' active' : '' ?>">Intervalo</a>
        </div>

        <!-- Period navigation -->
        <div class="sidebar-month-nav<?= $currentInterval === 'range' ? ' sidebar-month-nav--has-range' : '' ?>">
            <a href="<?= $prevUrl ?>" class="month-nav-btn" aria-label="Período anterior">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="current-month-label"><?= htmlspecialchars($periodLabel) ?></span>
            <a href="<?= $nextUrl ?>" class="month-nav-btn" aria-label="Próximo período">
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>

        <?php if ($currentInterval === 'range'): ?>
        <!-- Range date picker -->
        <form method="GET" action="<?= htmlspecialchars($formActionPath) ?>" class="range-picker">
            <input type="hidden" name="interval" value="range">
            <?php if ($viewMode !== 'grid'): ?><input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>"><?php endif; ?>
            <?php if ($searchQuery): ?><input type="hidden" name="q" value="<?= htmlspecialchars($searchQuery) ?>"><?php endif; ?>
            <input type="date" name="from" class="range-date-input" value="<?= htmlspecialchars($fromDate) ?>">
            <span class="range-picker-sep">–</span>
            <input type="date" name="to" class="range-date-input" value="<?= htmlspecialchars($toDate) ?>">
            <button type="submit" class="range-picker-btn">OK</button>
        </form>
        <?php endif; ?>

        <!-- Search -->
        <form method="GET" id="mainForm" action="<?= htmlspecialchars($formActionPath) ?>" class="sidebar-form">
            <?php if ($viewMode !== 'grid'): ?>
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
            <?php endif; ?>
            <?php if ($currentInterval !== 'month'): ?>
            <input type="hidden" name="interval" value="<?= htmlspecialchars($currentInterval) ?>">
            <input type="hidden" name="from" value="<?= htmlspecialchars($fromDate) ?>">
            <?php if ($currentInterval === 'range'): ?><input type="hidden" name="to" value="<?= htmlspecialchars($toDate) ?>"><?php endif; ?>
            <?php endif; ?>

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
                <?php foreach ($events as $event):
                    $eventLink   = $event['url'] ?: '/evento/' . $event['id'];
                    $eventTarget = $event['url'] ? ' target="_blank" rel="noopener"' : '';
                ?>
                <article class="event-item">
                    <a href="<?= htmlspecialchars($eventLink) ?>"<?= $eventTarget ?> class="event-image-link">
                        <div class="event-image-wrap">
                            <?php
                            $img = $event['image'];
                            if ($img && !preg_match('#^https?://#', $img)) $img = '/' . ltrim($img, '/');
                            ?>
                            <img src="<?= htmlspecialchars($img ?: '/assets/placeholder-event.svg') ?>"
                                 alt="<?= htmlspecialchars($event['title']) ?>"
                                 class="event-img<?= $event['image'] ? '' : ' event-img--placeholder' ?>" loading="lazy">
                            <?php if ($event['category']): ?>
                                <span class="category-chip"><?= htmlspecialchars($event['category']) ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                    <div class="event-body">
                        <h3 class="event-title">
                            <a href="<?= htmlspecialchars($eventLink) ?>"<?= $eventTarget ?>>
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
