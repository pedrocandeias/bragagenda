<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Mailer.php';

Auth::start();

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$db  = new Database();
$pdo = $db->getConnection();

$error   = '';
$success = '';

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
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, email, password_hash, role, active) VALUES (?, ?, ?, 'user', 0)")
                ->execute([$username, $email ?: null, $hash]);

            $userId = (int)$pdo->lastInsertId();

            // Generate confirmation token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $pdo->prepare("UPDATE users SET confirmation_token = ?, token_expires_at = ? WHERE id = ?")
                ->execute([$token, $expiresAt, $userId]);

            if (Mailer::isConfigured($pdo)) {
                $confirmUrl = rtrim(BASE_URL, '/') . '/admin/confirm-email.php?token=' . urlencode($token);
                $body = "Olá {$username},\n\n"
                      . "Foi criada uma conta em Braga Agenda Admin.\n"
                      . "Para confirmar o teu email e ativar a conta, clica no link abaixo:\n\n"
                      . $confirmUrl . "\n\n"
                      . "Este link é válido durante 24 horas.\n\n"
                      . "Se não criaste esta conta, podes ignorar este email.";

                try {
                    Mailer::send($pdo, $email ?: '', 'Confirma o teu email - Braga Agenda', $body);
                    header('Location: login.php?message=' . urlencode('Conta criada. Confirma o teu email para ativar a conta (verifica a caixa de entrada).'));
                    exit;
                } catch (Exception $e) {
                    // Account created but email failed — show inline error; admin can activate manually
                    $error = 'Conta criada, mas não foi possível enviar o email de confirmação: ' . $e->getMessage()
                           . ' Um administrador pode ativar a conta manualmente.';
                }
            } else {
                // SMTP not configured — fall back to admin-approval flow
                header('Location: login.php?message=' . urlencode('Conta criada. Aguarda aprovação do administrador.'));
                exit;
            }
        } catch (Exception $e) {
            if ($e->getCode() == 23000 || str_contains($e->getMessage(), 'UNIQUE')) {
                $error = 'Nome de utilizador já existe.';
            } else {
                $error = 'Erro ao criar conta: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registar - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .register-card { max-width: 440px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">
    <div class="register-card w-100 mx-3">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1 text-center">
                    <i class="bi bi-calendar-event"></i> Braga Agenda
                </h1>
                <p class="text-muted text-center mb-4">Criar conta</p>

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
                        <i class="bi bi-person-plus"></i> Registar
                    </button>
                </form>

                <div class="text-center mt-3 small">
                    <a href="login.php">Já tem conta? Entrar →</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
