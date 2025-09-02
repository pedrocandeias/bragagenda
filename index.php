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

// Validate month/year
if ($currentMonth < 1 || $currentMonth > 12) $currentMonth = date('n');
if ($currentYear < 2020 || $currentYear > 2030) $currentYear = date('Y');

$events = $eventModel->getEventsByMonth($currentYear, $currentMonth, $locationFilter, $categoryFilter);
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
function buildNavUrl($month, $year, $location = '', $category = '') {
    global $locationFilter, $categoryFilter;
    $params = ['month' => $month, 'year' => $year];
    if ($location ?: $locationFilter) $params['location'] = $location ?: $locationFilter;
    if ($category ?: $categoryFilter) $params['category'] = $category ?: $categoryFilter;
    return '?' . http_build_query($params);
}

// Format date range for display
function formatEventDate($event) {
    $startDate = $event['start_date'] ?: $event['event_date'];
    $endDate = $event['end_date'] ?: $event['event_date'];
    
    $startTimestamp = strtotime($startDate);
    $endTimestamp = strtotime($endDate);
    
    // If same date, show single date with time (if not midnight)
    if (date('Y-m-d', $startTimestamp) === date('Y-m-d', $endTimestamp)) {
        $time = date('H:i', $startTimestamp);
        if ($time !== '00:00') {
            return date('d/m/Y') . ' - ' . $time;
        } else {
            return date('d/m/Y', $startTimestamp);
        }
    }
    
    // Different dates - show range
    $startFormatted = date('d M Y', $startTimestamp);
    $endFormatted = date('d M Y', $endTimestamp);
    
    // Portuguese month names
    $monthMap = [
        'Jan' => 'Jan', 'Feb' => 'Fev', 'Mar' => 'Mar', 'Apr' => 'Abr',
        'May' => 'Mai', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
        'Sep' => 'Set', 'Oct' => 'Out', 'Nov' => 'Nov', 'Dec' => 'Dez'
    ];
    
    foreach ($monthMap as $en => $pt) {
        $startFormatted = str_replace($en, $pt, $startFormatted);
        $endFormatted = str_replace($en, $pt, $endFormatted);
    }
    
    // If same month/year, show compact format: "18 a 31 Out 2025"
    if (date('Y-m', $startTimestamp) === date('Y-m', $endTimestamp)) {
        $startDay = date('j', $startTimestamp);
        $endDay = date('j', $endTimestamp);
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
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Braga Agenda</h1>
        
        <nav class="month-nav">
            <a href="<?= buildNavUrl($prevMonth, $prevYear) ?>" class="nav-btn">←</a>
            <h2><?= $monthNames[$currentMonth] ?> <?= $currentYear ?></h2>
            <a href="<?= buildNavUrl($nextMonth, $nextYear) ?>" class="nav-btn">→</a>
        </nav>

        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="hidden" name="month" value="<?= $currentMonth ?>">
                <input type="hidden" name="year" value="<?= $currentYear ?>">
                
                <div class="filter-group">
                    <select name="location" onchange="this.form.submit()">
                        <option value="">Todos os locais</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= htmlspecialchars($location) ?>" 
                                    <?= $locationFilter === $location ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <select name="category" onchange="this.form.submit()">
                        <option value="">Todas as categorias</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category) ?>" 
                                    <?= $categoryFilter === $category ? 'selected' : '' ?>>
                                <?= htmlspecialchars($category) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($locationFilter || $categoryFilter): ?>
                    <a href="<?= buildNavUrl($currentMonth, $currentYear) ?>" class="clear-filters">Limpar filtros</a>
                <?php endif; ?>
            </form>
        </div>
    </header>

    <main>
        <div class="events-grid">
            <?php if (empty($events)): ?>
                <p class="no-events">Nenhum evento encontrado para este mês.</p>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <article class="event-card">
                        <?php if ($event['image']): ?>
                            <div class="event-image">
                                <img src="<?= htmlspecialchars($event['image']) ?>" 
                                     alt="<?= htmlspecialchars($event['title']) ?>">
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-content">
                            <h3><?= htmlspecialchars($event['title']) ?></h3>
                            
                            <div class="event-meta">
                                <time datetime="<?= $event['event_date'] ?>">
                                    <?= formatEventDate($event) ?>
                                </time>
                                <?php if ($event['location']): ?>
                                    <a href="<?= buildNavUrl($currentMonth, $currentYear, $event['location'], $categoryFilter) ?>" class="location clickable-tag">
                                        <?= htmlspecialchars($event['location']) ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($event['category']): ?>
                                    <a href="<?= buildNavUrl($currentMonth, $currentYear, $locationFilter, $event['category']) ?>" class="category clickable-tag">
                                        <?= htmlspecialchars($event['category']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($event['description']): ?>
                                <p class="event-description"><?= htmlspecialchars($event['description']) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($event['url']): ?>
                                <a href="<?= htmlspecialchars($event['url']) ?>" target="_blank" rel="noopener" class="event-link">Ver mais</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>Eventos agregados automaticamente</p>
    </footer>
</body>
</html>