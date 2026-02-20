<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

Auth::start();

$db  = new Database();
$pdo = $db->getConnection();

// Only accessible when no users exist
$userCount = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($userCount > 0) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (strlen($username) < 3) {
        $error = 'O nome de utilizador deve ter pelo menos 3 caracteres.';
    } elseif (strlen($password) < 8) {
        $error = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($password !== $password2) {
        $error = 'As palavras-passe não coincidem.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, active) VALUES (?, ?, ?, 'admin', 1)");
        $stmt->execute([$username, $email ?: null, $hash]);
        header('Location: login.php?message=' . urlencode('Conta criada. Pode agora entrar.'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração inicial - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .setup-card { max-width: 440px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="setup-card w-100 mx-3">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1 text-center">
                    <i class="bi bi-calendar-event"></i> Braga Agenda
                </h1>
                <p class="text-muted text-center mb-4">Criar conta de administrador</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Utilizador <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               minlength="3" autofocus required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-muted">(opcional)</span></label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Palavra-passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password"
                               minlength="8" required>
                        <div class="form-text">Mínimo 8 caracteres.</div>
                    </div>
                    <div class="mb-4">
                        <label for="password2" class="form-label">Confirmar palavra-passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password2" name="password2"
                               minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-person-plus"></i> Criar administrador
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
