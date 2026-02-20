<?php
require_once '../config.php';
require_once '../includes/Database.php';

$db  = new Database();
$pdo = $db->getConnection();

$token = trim($_GET['token'] ?? '');

function showError(string $title, string $message, string $extra = ''): void {
    ?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmação de Email - Braga Agenda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { background: #f8f9fa; }</style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div style="max-width:420px;" class="w-100 mx-3">
        <div class="card shadow-sm text-center p-4">
            <i class="bi bi-x-circle-fill text-danger fs-1 mb-3"></i>
            <h1 class="h5 mb-2"><?= htmlspecialchars($title) ?></h1>
            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
            <?= $extra ?>
            <a href="login.php" class="btn btn-outline-primary mt-2">Ir para o login</a>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

if (!$token) {
    showError('Token inválido', 'O link de confirmação é inválido ou está incompleto.');
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE confirmation_token = ?");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    showError('Token não encontrado', 'Este link de confirmação não é válido ou já foi utilizado.');
}

// Already active — token was cleared on a previous confirmation
if ($user['active'] && !$user['confirmation_token']) {
    header('Location: login.php?message=' . urlencode('A conta já está ativa. Pode entrar.'));
    exit;
}

// Check expiry
if (!empty($user['token_expires_at']) && strtotime($user['token_expires_at']) < time()) {
    showError(
        'Link expirado',
        'Este link de confirmação expirou (válido durante 24 horas).',
        '<a href="register.php" class="btn btn-primary">Registar novamente</a>'
    );
}

// Activate the account
$pdo->prepare("UPDATE users SET active = 1, confirmation_token = NULL, token_expires_at = NULL WHERE id = ?")
    ->execute([$user['id']]);

header('Location: login.php?message=' . urlencode('Email confirmado. Pode agora entrar.'));
exit;
