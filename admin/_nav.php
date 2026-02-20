<?php
// Shared admin nav — include after Auth::requireLogin() / Auth::requireRole()
// Usage: include '_nav.php';
$_navUser = Auth::currentUser();
?>
<header class="py-3 border-bottom mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="index.php" class="text-decoration-none text-dark">
            <h1 class="h5 mb-0"><i class="bi bi-calendar-event"></i> Braga Agenda</h1>
        </a>
        <nav class="d-flex gap-2 flex-wrap align-items-center">
            <a href="../index.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-globe"></i> <span class="d-none d-md-inline">Ver site</span>
            </a>
            <?php if (Auth::hasMinRole('admin')): ?>
            <a href="categories.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-tags"></i> <span class="d-none d-md-inline">Categorias</span>
            </a>
            <a href="venues.php" class="btn btn-sm btn-outline-info">
                <i class="bi bi-geo-alt"></i> <span class="d-none d-md-inline">Locais</span>
            </a>
            <a href="scrapers.php" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-cloud-download"></i> <span class="d-none d-md-inline">Scrapers</span>
            </a>
            <a href="configs.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-gear"></i> <span class="d-none d-md-inline">Configs</span>
            </a>
            <a href="users.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-people"></i> <span class="d-none d-md-inline">Utilizadores</span>
            </a>
            <?php endif; ?>
            <a href="add-event.php" class="btn btn-sm btn-success">
                <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline">Adicionar evento</span>
            </a>
            <span class="text-muted small ms-2">
                <i class="bi bi-person-circle"></i>
                <?= htmlspecialchars($_navUser['username']) ?>
                <span class="badge bg-<?= $_navUser['role'] === 'admin' ? 'danger' : 'primary' ?> ms-1">
                    <?= htmlspecialchars($_navUser['role']) ?>
                </span>
            </span>
            <a href="logout.php" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> <span class="d-none d-md-inline">Sair</span>
            </a>
        </nav>
    </div>
</header>
