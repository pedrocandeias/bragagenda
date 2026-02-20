<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

Auth::requireRole('admin');

$db  = new Database();
$pdo = $db->getConnection();

$currentUser = Auth::currentUser();
$message     = '';
$messageType = 'success';

// ---- Handle POST actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username  = trim($_POST['username'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role'] ?? 'contributor';

        if (!in_array($role, ['admin', 'contributor', 'user'])) $role = 'contributor';

        if (strlen($username) < 3) {
            $message = 'O nome de utilizador deve ter pelo menos 3 caracteres.';
            $messageType = 'error';
        } elseif (strlen($password) < 8) {
            $message = 'A palavra-passe deve ter pelo menos 8 caracteres.';
            $messageType = 'error';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, active) VALUES (?, ?, ?, ?, 1)");
                $stmt->execute([$username, $email ?: null, $hash, $role]);
                $message = 'Utilizador criado com sucesso.';
            } catch (Exception $e) {
                $message = 'Erro: nome de utilizador já existe.';
                $messageType = 'error';
            }
        }

    } elseif ($action === 'edit_role') {
        $uid     = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';
        $active  = isset($_POST['active']) ? 1 : 0;

        if (!in_array($newRole, ['admin', 'contributor', 'user'])) {
            $message = 'Papel inválido.';
            $messageType = 'error';
        } elseif ($uid === $currentUser['id'] && $newRole !== 'admin') {
            $message = 'Não pode rebaixar a sua própria conta.';
            $messageType = 'error';
        } else {
            $pdo->prepare("UPDATE users SET role = ?, active = ? WHERE id = ?")
                ->execute([$newRole, $active, $uid]);
            $message = 'Utilizador atualizado.';
        }

    } elseif ($action === 'reset_password') {
        $uid      = (int)($_POST['user_id'] ?? 0);
        $password = $_POST['new_password'] ?? '';

        if (strlen($password) < 8) {
            $message = 'A palavra-passe deve ter pelo menos 8 caracteres.';
            $messageType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([$hash, $uid]);
            $message = 'Palavra-passe redefinida.';
        }

    } elseif ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid === $currentUser['id']) {
            $message = 'Não pode eliminar a sua própria conta.';
            $messageType = 'error';
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $message = 'Utilizador eliminado.';
        }
    }
}

$users = $pdo->query("SELECT id, username, email, role, active, last_login, created_at FROM users ORDER BY created_at")->fetchAll(PDO::FETCH_ASSOC);

function roleLabel(string $role): string {
    return match ($role) {
        'admin'       => '<span class="badge bg-danger">Admin</span>',
        'contributor' => '<span class="badge bg-primary">Contributor</span>',
        default       => '<span class="badge bg-secondary">User</span>',
    };
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilizadores - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <header class="py-4 border-bottom">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="h4 mb-0"><i class="bi bi-people"></i> Utilizadores</h1>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span class="text-muted small">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($currentUser['username']) ?>
                    <span class="badge bg-danger ms-1"><?= htmlspecialchars($currentUser['role']) ?></span>
                </span>
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Voltar
                </a>
                <a href="logout.php" class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </header>

    <main class="py-4">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType === 'error' ? 'danger' : 'success' ?> alert-dismissible fade show">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add user -->
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-person-plus"></i> Adicionar utilizador</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="add">
                    <div class="col-md-3">
                        <input type="text" name="username" class="form-control" placeholder="Utilizador" minlength="3" required>
                    </div>
                    <div class="col-md-3">
                        <input type="email" name="email" class="form-control" placeholder="Email (opcional)">
                    </div>
                    <div class="col-md-2">
                        <input type="password" name="password" class="form-control" placeholder="Palavra-passe" minlength="8" required>
                    </div>
                    <div class="col-md-2">
                        <select name="role" class="form-select">
                            <option value="contributor">Contributor</option>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-plus-lg"></i> Criar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- User list -->
        <div class="card">
            <div class="card-header"><i class="bi bi-people"></i> Lista de utilizadores (<?= count($users) ?>)</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Utilizador</th>
                            <th>Email</th>
                            <th>Papel</th>
                            <th>Estado</th>
                            <th>Último acesso</th>
                            <th>Criado em</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td>
                                <?= htmlspecialchars($u['username']) ?>
                                <?php if ($u['id'] === $currentUser['id']): ?>
                                    <span class="badge bg-light text-dark border ms-1">Você</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= htmlspecialchars($u['email'] ?? '—') ?></small></td>
                            <td><?= roleLabel($u['role']) ?></td>
                            <td>
                                <?php if ($u['active']): ?>
                                    <span class="badge bg-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '—' ?></small></td>
                            <td><small class="text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></small></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <!-- Edit role -->
                                    <button class="btn btn-sm btn-outline-secondary" type="button"
                                            data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <!-- Reset password -->
                                    <button class="btn btn-sm btn-outline-warning" type="button"
                                            data-bs-toggle="modal" data-bs-target="#pwModal<?= $u['id'] ?>">
                                        <i class="bi bi-key"></i>
                                    </button>
                                    <?php if ($u['id'] !== $currentUser['id']): ?>
                                    <!-- Delete -->
                                    <form method="POST" onsubmit="return confirm('Eliminar utilizador <?= htmlspecialchars($u['username'], ENT_QUOTES) ?>?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>

                        <!-- Edit role modal -->
                        <div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-sm">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Editar <?= htmlspecialchars($u['username']) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="edit_role">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Papel</label>
                                                <select name="role" class="form-select">
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                    <option value="contributor" <?= $u['role'] === 'contributor' ? 'selected' : '' ?>>Contributor</option>
                                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                </select>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="active" id="active<?= $u['id'] ?>" value="1" <?= $u['active'] ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="active<?= $u['id'] ?>">Ativo</label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Reset password modal -->
                        <div class="modal fade" id="pwModal<?= $u['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-sm">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Redefinir palavra-passe</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <label class="form-label">Nova palavra-passe (mín. 8 char.)</label>
                                            <input type="password" name="new_password" class="form-control" minlength="8" required>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                                            <button type="submit" class="btn btn-warning btn-sm">Redefinir</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
