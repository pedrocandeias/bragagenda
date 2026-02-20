<?php
class Auth {

    private static function ensureStarted() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function start() {
        self::ensureStarted();
    }

    public static function isLoggedIn(): bool {
        self::ensureStarted();
        return !empty($_SESSION['user']);
    }

    public static function currentUser(): ?array {
        self::ensureStarted();
        return $_SESSION['user'] ?? null;
    }

    // Role hierarchy: admin > contributor. user has no admin panel access.
    private static function roleLevel(string $role): int {
        return match ($role) {
            'admin'       => 2,
            'contributor' => 1,
            default       => 0,
        };
    }

    public static function hasMinRole(string $minRole): bool {
        $user = self::currentUser();
        if (!$user) return false;
        return self::roleLevel($user['role']) >= self::roleLevel($minRole);
    }

    // ------------------------------------------------------------------ //
    // Redirect-based guards (HTML pages)
    // ------------------------------------------------------------------ //

    public static function requireLogin(): void {
        self::ensureStarted();

        // Check for empty users table → redirect to setup
        if (!self::isLoggedIn()) {
            try {
                require_once __DIR__ . '/../config.php';
                $pdo = new PDO('sqlite:' . DB_PATH);
                $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                if ($count === 0) {
                    header('Location: ' . self::adminBase() . 'setup.php');
                    exit;
                }
            } catch (Exception $e) {
                // Table may not exist yet — setup will handle it
                header('Location: ' . self::adminBase() . 'setup.php');
                exit;
            }

            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . self::adminBase() . 'login.php?redirect=' . $redirect);
            exit;
        }
    }

    public static function requireRole(string $minRole): void {
        self::requireLogin();
        if (!self::hasMinRole($minRole)) {
            header('Location: ' . self::adminBase() . 'index.php?message=' . urlencode('Acesso negado.') . '&type=error');
            exit;
        }
    }

    // ------------------------------------------------------------------ //
    // JSON guards (AJAX endpoints)
    // ------------------------------------------------------------------ //

    public static function requireLoginAjax(): void {
        self::ensureStarted();
        if (!self::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    public static function requireRoleAjax(string $minRole): void {
        self::requireLoginAjax();
        if (!self::hasMinRole($minRole)) {
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado']);
            exit;
        }
    }

    // ------------------------------------------------------------------ //
    // Login / logout
    // ------------------------------------------------------------------ //

    public static function login(string $username, string $password, PDO $pdo): bool {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Write session
        self::ensureStarted();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'email'    => $user['email'],
            'role'     => $user['role'],
        ];

        // Update last_login
        $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")
            ->execute([$user['id']]);

        return true;
    }

    public static function logout(): void {
        self::ensureStarted();
        session_destroy();
    }

    // ------------------------------------------------------------------ //
    // Helpers
    // ------------------------------------------------------------------ //

    private static function adminBase(): string {
        // Works whether we are called from admin/ or a subdirectory
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        if (str_contains($script, '/admin/')) {
            return '';          // already in admin/
        }
        return 'admin/';
    }
}
