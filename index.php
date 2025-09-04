<?php
require_once 'config.php';
require_once 'includes/Database.php';
require_once 'includes/Event.php';

$db = new Database();
$eventModel = new Event($db);

// Handle month navigation
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Handle filters
$locationFilter = isset($_GET['location']) ? trim($_GET['location']) : '';
$categoryFilter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Handle pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$eventsPerPage = 12;
$offset = ($page - 1) * $eventsPerPage;

// Handle view mode
$viewMode = isset($_GET['view']) && in_array($_GET['view'], ['list', 'mosaic']) ? $_GET['view'] : 'mosaic';

// Validate month/year
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');

// Get total events count for pagination
$totalEvents = $eventModel->getEventsCountByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter);
$totalPages = ceil($totalEvents / $eventsPerPage);

$events = $eventModel->getEventsByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter, $eventsPerPage, $offset);
$locations = $eventModel->getAllLocations();
$categories = $eventModel->getAllCategories();

// Navigation dates
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$monthNames = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
    5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
    9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
];

// Build query string for navigation preserving filters
function buildNavUrl($month, $year, $location = '', $category = '', $page = 1, $view = '') {
    global $locationFilter, $categoryFilter, $viewMode;
    $params = ['month' => $month, 'year' => $year];
    if ($location ?: $locationFilter) $params['location'] = $location ?: $locationFilter;
    if ($category ?: $categoryFilter) $params['category'] = $category ?: $categoryFilter;
    if ($page > 1) $params['page'] = $page;
    if ($view ?: $viewMode) $params['view'] = $view ?: $viewMode;
    return '?' . http_build_query($params);
}

// Build current URL with different page
function buildPageUrl($page) {
    global $currentMonth, $currentYear, $locationFilter, $categoryFilter, $viewMode;
    return buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, $page, $viewMode);
}

// Format date range for display
function formatEventDate($event) {
    $startDate = $event['start_date'] ?: $event['event_date'];
    $endDate = $event['end_date'] ?: $event['event_date'];
    
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    
    // Portuguese month names
    $monthMap = [
        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
    ];
    
    // If same date, show single date with time (if not midnight)
    if (date('Y-m-d', $startTimestamp) === date('Y-m-d', $endTimestamp)) {
        $dateFormatted = date('j M Y', $startTimestamp);
        $dateFormatted = str_replace(array_keys($monthMap), array_values($monthMap), $dateFormatted);
        
        $time = date('H:i', $startTimestamp);
        if ($time !== '00:00') {
            return $dateFormatted . ' - ' . $time;
        } else {
            return $dateFormatted;
        }
    }
    
    // Different dates - show range
    $startFormatted = date('j M Y', $startTimestamp);
    $endFormatted = date('j M Y', $endTimestamp);
    
    foreach ($monthMap as $en => $pt) {
        $startFormatted = str_replace($en, $pt, $startFormatted);
        $endFormatted = str_replace($en, $pt, $endFormatted);
    }
    
    // If same month/year, show compact format: "18 a 31 Out 2025"
    if (date('Y-m', $startTimestamp) === date('Y-m', $endTimestamp)) {
        $startDay = date('j', $startTimestamp);
        $endDay = date('j', $endTimestamp);
        
        // If start and end day are the same, show just one date
        if ($startDay === $endDay) {
            return $startFormatted;
        }
        
        $monthYear = date('M Y', $startTimestamp);
        $monthYear = str_replace(array_keys($monthMap), array_values($monthMap), $monthYear);
        return "$startDay a $endDay $monthYear";
    }
    
    // Different months/years: "18 Set 2025 a 31 Out 2025"
    return "$startFormatted a $endFormatted";
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braga Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container-fluid">
        <header class="py-4 border-bottom">
            <div class="container">
                <h1 class="text-center mb-4">Braga Agenda</h1>
        
                <nav class="d-flex justify-content-center align-items-center gap-4 mb-4">
                    <a href="<?= buildNavUrl($prevMonth, $prevYear) ?>" class="btn btn-outline-dark">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <h2 class="mb-0"><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></h2>
                    <a href="<?= buildNavUrl($nextMonth, $nextYear) ?>" class="btn btn-outline-dark">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </nav>

                <div class="row align-items-center">
                    <div class="col-md-8">
                        <form method="GET" class="d-flex gap-3 align-items-center flex-wrap">
                            <input type="hidden" name="month" value="<?= $currentMonth ?>">
                            <input type="hidden" name="year" value="<?= $currentYear ?>">
                            <input type="hidden" name="view" value="<?= $viewMode ?>">
                            
                            <select name="location" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                                <option value="">Todos os locais</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= htmlspecialchars($location) ?>" 
                                            <?= $locationFilter === $location ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($location) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="category" onchange="this.form.submit()" class="form-select form-select-sm" style="width: auto;">
                                <option value="">Todas as categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= htmlspecialchars($category) ?>" 
                                            <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if ($locationFilter || $categoryFilter): ?>
                                <a href="<?= buildNavUrl($currentMonth, $currentYear) ?>" class="btn btn-sm btn-outline-secondary">Limpar filtros</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-end">
                            <div class="btn-group" role="group">
                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, $page, 'mosaic') ?>" 
                                   class="btn <?= $viewMode === 'mosaic' ? 'btn-dark' : 'btn-outline-dark' ?> btn-sm">
                                    <i class="bi bi-grid-3x3-gap"></i> Mosaico
                                </a>
                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $categoryFilter, $page, 'list') ?>" 
                                   class="btn <?= $viewMode === 'list' ? 'btn-dark' : 'btn-outline-dark' ?> btn-sm">
                                    <i class="bi bi-list"></i> Lista
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="container my-4">
            <?php if (empty($events)): ?>
                <div class="text-center py-5">
                    <p class="text-muted fs-5">Nenhum evento encontrado para este mês.</p>
                </div>
            <?php else: ?>
                <?php if ($viewMode === 'mosaic'): ?>
                    <div class="row g-4">
                        <?php foreach ($events as $event): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100">
                                    <?php if ($event['image']): ?>
                                        <img src="<?= htmlspecialchars($event['image']) ?>" 
                                             class="card-img-top" 
                                             alt="<?= htmlspecialchars($event['title']) ?>"
                                             style="height: 200px; object-fit: cover;">
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">
                                            <?= htmlspecialchars($event['title']) ?>
                                            <?php if (!empty($event['featured'])): ?>
                                                <span class="badge bg-warning text-dark ms-2">
                                                    <i class="bi bi-star-fill"></i> Destaque
                                                </span>
                                            <?php endif; ?>
                                        </h5>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-calendar"></i> 
                                                <?= formatEventDate($event) ?>
                                            </small>
                                            <?php if ($event['location']): ?>
                                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $event['location'], $categoryFilter, $page, $viewMode) ?>" 
                                                   class="badge bg-primary text-decoration-none me-1">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($event['category']): ?>
                                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $event['category'], $page, $viewMode) ?>" 
                                                   class="badge bg-secondary text-decoration-none">
                                                    <?= htmlspecialchars($event['category']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($event['description']): ?>
                                            <p class="card-text text-muted small flex-grow-1">
                                                <?= htmlspecialchars(substr($event['description'], 0, 120)) ?><?= strlen($event['description']) > 120 ? '...' : '' ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['url']): ?>
                                            <a href="<?= htmlspecialchars($event['url']) ?>" 
                                               target="_blank" 
                                               rel="noopener" 
                                               class="btn btn-outline-primary btn-sm mt-auto">
                                                Ver mais <i class="bi bi-arrow-up-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($events as $event): ?>
                            <div class="list-group-item">
                                <div class="row align-items-center">
                                    <?php if ($event['image']): ?>
                                        <div class="col-auto">
                                            <img src="<?= htmlspecialchars($event['image']) ?>" 
                                                 class="rounded" 
                                                 alt="<?= htmlspecialchars($event['title']) ?>"
                                                 style="width: 80px; height: 80px; object-fit: cover;">
                                        </div>
                                    <?php endif; ?>
                                    <div class="col">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h5 class="mb-1">
                                                <?= htmlspecialchars($event['title']) ?>
                                                <?php if (!empty($event['featured'])): ?>
                                                    <span class="badge bg-warning text-dark ms-2">
                                                        <i class="bi bi-star-fill"></i> Destaque
                                                    </span>
                                                <?php endif; ?>
                                            </h5>
                                            <small class="text-muted">
                                                <?= formatEventDate($event) ?>
                                            </small>
                                        </div>
                                        
                                        <div class="mb-2">
                                            <?php if ($event['location']): ?>
                                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $event['location'], $categoryFilter, $page, $viewMode) ?>" 
                                                   class="badge bg-primary text-decoration-none me-1">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($event['category']): ?>
                                                <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $event['category'], $page, $viewMode) ?>" 
                                                   class="badge bg-secondary text-decoration-none">
                                                    <?= htmlspecialchars($event['category']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($event['description']): ?>
                                            <p class="mb-1 text-muted"><?= htmlspecialchars($event['description']) ?></p>
                                        <?php endif; ?>
                                        
                                        <?php if ($event['url']): ?>
                                            <a href="<?= htmlspecialchars($event['url']) ?>" 
                                               target="_blank" 
                                               rel="noopener" 
                                               class="btn btn-outline-primary btn-sm">
                                                Ver mais <i class="bi bi-arrow-up-right"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Navegação da página" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPageUrl($page - 1) ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php 
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPageUrl(1) ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= buildPageUrl($i) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPageUrl($totalPages) ?>"><?= $totalPages ?></a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?= buildPageUrl($page + 1) ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </main>

        <footer class="text-center py-4 border-top mt-5">
            <div class="container">
                <p class="text-muted mb-0">Eventos agregados automaticamente</p>
            </div>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>