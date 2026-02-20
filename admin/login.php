<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

Auth::start();

// Already logged in
if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db  = new Database();
$pdo = $db->getConnection();

// No users yet → redirect to setup
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount === 0) {
    header('Location: setup.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? 'index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        if (Auth::login($username, $password, $pdo)) {
            $dest = filter_var($redirect, FILTER_VALIDATE_URL) ? 'index.php' : $redirect;
            header('Location: ' . $dest);
            exit;
        }
    }
    $error = 'Utilizador ou palavra-passe incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .login-card { max-width: 400px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="login-card w-100 mx-3">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-4 text-center">
                    <i class="bi bi-calendar-event"></i> Braga Agenda
                </h1>
                <h2 class="h6 text-muted text-center mb-4">Área de administração</h2>

                <?php if (!empty($_GET['message'])): ?>
                    <div class="alert alert-success py-2">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_GET['message']) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Utilizador</label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autofocus autocomplete="username" required>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Palavra-passe</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right"></i> Entrar
                    </button>
                </form>

                <div class="text-center mt-3 small">
                    <a href="register.php">Ainda não tem conta? Registar</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
