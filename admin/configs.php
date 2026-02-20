<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/Mailer.php';

Auth::requireRole('admin');

$db  = new Database();
$pdo = $db->getConnection();

function getSetting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['value'] : $default;
}

function saveSetting($pdo, $key, $value) {
    $pdo->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)")
        ->execute([$key, $value]);
}

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';

    if ($action === 'test_smtp') {
        $user = Auth::currentUser();
        $to   = $user['email'] ?? '';
        if (!$to) {
            echo json_encode(['success' => false, 'error' => 'A tua conta não tem email configurado. Edita o teu utilizador para adicionar um email.']);
            exit;
        }
        try {
            Mailer::send($pdo, $to, 'Teste SMTP - Braga Agenda', "Este é um email de teste enviado pelo painel de administração do Braga Agenda.\n\nSe recebeste este email, as definições SMTP estão corretas.");
            echo json_encode(['success' => true, 'message' => 'Email de teste enviado para ' . $to . '.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'save_facebook_credentials') {
        if (isset($input['app_id']))     saveSetting($pdo, 'facebook_app_id',     trim($input['app_id']));
        if (isset($input['app_secret'])) saveSetting($pdo, 'facebook_app_secret', trim($input['app_secret']));
        echo json_encode(['success' => true, 'message' => 'Credenciais guardadas.']);
        exit;
    }

    if ($action === 'save_facebook_token') {
        $token = trim($input['token'] ?? '');
        if (!$token) {
            echo json_encode(['success' => false, 'error' => 'Token vazio.']);
            exit;
        }
        saveSetting($pdo, 'facebook_access_token', $token);
        $appId     = getSetting($pdo, 'facebook_app_id');
        $appSecret = getSetting($pdo, 'facebook_app_secret');
        $expiresAt = null;
        if ($appId && $appSecret) {
            $debugUrl  = "https://graph.facebook.com/v22.0/debug_token"
                       . "?input_token=" . urlencode($token)
                       . "&access_token=" . urlencode("{$appId}|{$appSecret}");
            $resp = @file_get_contents($debugUrl);
            if ($resp) {
                $debugData = json_decode($resp, true);
                if (!empty($debugData['data']['expires_at']) && $debugData['data']['expires_at'] > 0) {
                    $expiresAt = date('Y-m-d H:i:s', $debugData['data']['expires_at']);
                    saveSetting($pdo, 'facebook_token_expires', $expiresAt);
                } elseif (!empty($debugData['data']['is_valid']) && $debugData['data']['is_valid']) {
                    $pdo->prepare("DELETE FROM settings WHERE key = 'facebook_token_expires'")->execute();
                }
            }
        }
        echo json_encode([
            'success'    => true,
            'message'    => 'Token guardado.',
            'expires_at' => $expiresAt,
        ]);
        exit;
    }

    if ($action === 'exchange_facebook_token') {
        $appId      = trim($input['app_id']     ?? getSetting($pdo, 'facebook_app_id'));
        $appSecret  = trim($input['app_secret'] ?? getSetting($pdo, 'facebook_app_secret'));
        $shortToken = trim($input['short_token'] ?? '');

        if (!$appId || !$appSecret || !$shortToken) {
            echo json_encode(['success' => false, 'error' => 'App ID, App Secret e token curto são obrigatórios.']);
            exit;
        }

        $url  = "https://graph.facebook.com/v22.0/oauth/access_token"
              . "?grant_type=fb_exchange_token"
              . "&client_id=" . urlencode($appId)
              . "&client_secret=" . urlencode($appSecret)
              . "&fb_exchange_token=" . urlencode($shortToken);
        $resp = @file_get_contents($url);
        if (!$resp) {
            echo json_encode(['success' => false, 'error' => 'Falha ao comunicar com o Facebook.']);
            exit;
        }
        $data = json_decode($resp, true);
        if (isset($data['error'])) {
            echo json_encode(['success' => false, 'error' => $data['error']['message']]);
            exit;
        }

        $longToken = $data['access_token'];
        $expiresIn = $data['expires_in'] ?? null;
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

        saveSetting($pdo, 'facebook_access_token', $longToken);
        if ($expiresAt) saveSetting($pdo, 'facebook_token_expires', $expiresAt);

        echo json_encode([
            'success'      => true,
            'access_token' => $longToken,
            'expires_at'   => $expiresAt,
            'message'      => 'Token de longa duração obtido e guardado.',
        ]);
        exit;
    }

    if ($action === 'test_facebook_token') {
        $token = trim($input['token'] ?? getSetting($pdo, 'facebook_access_token'));
        if (!$token) {
            echo json_encode(['success' => false, 'error' => 'Nenhum token configurado.']);
            exit;
        }

        $url  = "https://graph.facebook.com/v22.0/me?fields=id,name&access_token=" . urlencode($token);
        $resp = @file_get_contents($url);
        if (!$resp) {
            echo json_encode(['success' => false, 'error' => 'Falha ao comunicar com o Facebook.']);
            exit;
        }
        $data = json_decode($resp, true);
        if (isset($data['error'])) {
            echo json_encode(['success' => false, 'error' => $data['error']['message']]);
            exit;
        }
        echo json_encode([
            'success' => true,
            'message' => 'Token válido. Conta: ' . ($data['name'] ?? $data['id']),
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
    exit;
}

// ── Save SMTP settings (standard POST) ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $fields = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];
    foreach ($fields as $field) {
        if ($field === 'smtp_password' && $_POST[$field] === '') {
            continue;
        }
        saveSetting($pdo, $field, trim($_POST[$field] ?? ''));
    }
    header('Location: configs.php?tab=smtp&saved=1');
    exit;
}

// ── Active tab ────────────────────────────────────────────────────────────────
$activeTab = (($_GET['tab'] ?? '') === 'facebook') ? 'facebook' : 'smtp';

// ── Load SMTP settings ────────────────────────────────────────────────────────
$smtpHost       = getSetting($pdo, 'smtp_host');
$smtpPort       = getSetting($pdo, 'smtp_port', '587');
$smtpEncryption = getSetting($pdo, 'smtp_encryption', 'tls');
$smtpUsername   = getSetting($pdo, 'smtp_username');
$smtpPassword   = getSetting($pdo, 'smtp_password');
$smtpFromEmail  = getSetting($pdo, 'smtp_from_email');
$smtpFromName   = getSetting($pdo, 'smtp_from_name');
$isConfigured   = Mailer::isConfigured($pdo);

// ── Load Facebook settings ────────────────────────────────────────────────────
$appId       = getSetting($pdo, 'facebook_app_id');
$appSecret   = getSetting($pdo, 'facebook_app_secret');
$accessToken = getSetting($pdo, 'facebook_access_token');
$tokenExp    = getSetting($pdo, 'facebook_token_expires');

$tokenStatus   = 'none';
$tokenDaysLeft = null;
if ($accessToken) {
    if ($tokenExp) {
        $expTs = strtotime($tokenExp);
        if ($expTs > time()) {
            $tokenStatus   = 'valid';
            $tokenDaysLeft = (int) ceil(($expTs - time()) / 86400);
        } else {
            $tokenStatus = 'expired';
        }
    } else {
        $tokenStatus = 'set';
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Braga Agenda Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <?php include '_nav.php'; ?>

    <main class="py-4" style="max-width: 760px;">
        <h2 class="h5 mb-4"><i class="bi bi-gear me-1"></i> Configurações</h2>

        <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success py-2 mb-4">
            <i class="bi bi-check-circle"></i> Definições guardadas com sucesso.
        </div>
        <?php endif; ?>

        <!-- Tab nav -->
        <ul class="nav nav-tabs mb-4" id="configTabs">
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'smtp' ? 'active' : '' ?>"
                   href="configs.php?tab=smtp">
                    <i class="bi bi-envelope-gear me-1"></i> SMTP
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $activeTab === 'facebook' ? 'active' : '' ?>"
                   href="configs.php?tab=facebook">
                    <i class="bi bi-facebook me-1"></i> Facebook API
                </a>
            </li>
        </ul>

        <!-- ── SMTP pane ─────────────────────────────────────────────────── -->
        <div id="pane-smtp" <?= $activeTab !== 'smtp' ? 'style="display:none"' : '' ?>>

            <?php if ($isConfigured): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>SMTP configurado — os emails de confirmação de registo estão ativos.</div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-circle-fill fs-5"></i>
                <div>SMTP não configurado — os novos registos requerem aprovação manual pelo administrador.</div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header fw-semibold">
                    <i class="bi bi-server me-1"></i> Configuração do servidor SMTP
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Host <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="smtp_host"
                                       value="<?= htmlspecialchars($smtpHost) ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Porta</label>
                                <input type="number" class="form-control" name="smtp_port"
                                       value="<?= htmlspecialchars($smtpPort ?: '587') ?>"
                                       placeholder="587">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Encriptação</label>
                            <select class="form-select" name="smtp_encryption">
                                <option value="tls"  <?= $smtpEncryption === 'tls'  ? 'selected' : '' ?>>STARTTLS (recomendado, porta 587)</option>
                                <option value="ssl"  <?= $smtpEncryption === 'ssl'  ? 'selected' : '' ?>>SSL/TLS (porta 465)</option>
                                <option value="none" <?= $smtpEncryption === 'none' ? 'selected' : '' ?>>Nenhuma (não recomendado)</option>
                            </select>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Utilizador</label>
                                <input type="text" class="form-control" name="smtp_username"
                                       value="<?= htmlspecialchars($smtpUsername) ?>"
                                       placeholder="utilizador@exemplo.pt"
                                       autocomplete="username">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">
                                    Palavra-passe
                                    <?php if ($smtpPassword): ?>
                                        <span class="text-muted small">(deixa em branco para manter a atual)</span>
                                    <?php endif; ?>
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="smtpPassword" name="smtp_password"
                                           placeholder="<?= $smtpPassword ? '••••••••' : '' ?>"
                                           autocomplete="new-password">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye" id="pwEyeIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Email do remetente</label>
                                <input type="email" class="form-control" name="smtp_from_email"
                                       value="<?= htmlspecialchars($smtpFromEmail) ?>"
                                       placeholder="noreply@exemplo.pt">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nome do remetente</label>
                                <input type="text" class="form-control" name="smtp_from_name"
                                       value="<?= htmlspecialchars($smtpFromName) ?>"
                                       placeholder="Braga Agenda">
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Guardar
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="testBtn"
                                    <?= !$isConfigured ? 'disabled title="Configura o SMTP primeiro"' : '' ?>>
                                <i class="bi bi-plug"></i> Enviar email de teste
                            </button>
                        </div>
                    </form>
                    <div id="testMsg" class="mt-3"></div>
                </div>
            </div>

            <div class="card border-0 bg-light mt-4">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-question-circle me-1"></i> Dicas de configuração</h6>
                    <ul class="mb-0 small text-muted">
                        <li><strong>Gmail:</strong> host <code>smtp.gmail.com</code>, porta <code>587</code>, STARTTLS. Usa uma <a href="https://support.google.com/accounts/answer/185833" target="_blank">App Password</a> em vez da tua palavra-passe normal.</li>
                        <li><strong>Outlook / Microsoft 365:</strong> host <code>smtp.office365.com</code>, porta <code>587</code>, STARTTLS.</li>
                        <li>O email de teste é enviado para o endereço de email da tua conta de administrador.</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ── Facebook pane ──────────────────────────────────────────────── -->
        <div id="pane-facebook" <?= $activeTab !== 'facebook' ? 'style="display:none"' : '' ?>>

            <!-- Token status banner -->
            <?php if ($tokenStatus === 'valid'): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div>
                    Token ativo —
                    <?php if ($tokenDaysLeft !== null): ?>
                        expira em <strong><?= $tokenDaysLeft ?> dia<?= $tokenDaysLeft !== 1 ? 's' : '' ?></strong>
                        (<?= date('j M Y', strtotime($tokenExp)) ?>).
                        <?php if ($tokenDaysLeft <= 14): ?>
                            <span class="text-warning fw-semibold">Renova em breve.</span>
                        <?php endif; ?>
                    <?php else: ?>
                        sem data de expiração.
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif ($tokenStatus === 'expired'): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div>Token <strong>expirado</strong> em <?= date('j M Y', strtotime($tokenExp)) ?>. Renova o token abaixo.</div>
            </div>
            <?php elseif ($tokenStatus === 'set'): ?>
            <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-info-circle-fill fs-5"></i>
                <div>Token configurado — data de expiração desconhecida. Introduz App ID + Secret para obter essa informação.</div>
            </div>
            <?php else: ?>
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-circle-fill fs-5"></i>
                <div>Nenhum token configurado. Segue as instruções abaixo.</div>
            </div>
            <?php endif; ?>

            <!-- API Credentials -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-key me-1"></i> Credenciais da App
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Cria uma app em <a href="https://developers.facebook.com" target="_blank">developers.facebook.com</a>
                        (tipo Consumer) e copia o App ID e App Secret.
                        Estes são necessários para trocar tokens curtos por tokens de longa duração.
                    </p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">App ID</label>
                            <input type="text" id="appId" class="form-control font-monospace"
                                   value="<?= htmlspecialchars($appId) ?>" placeholder="123456789012345">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">App Secret</label>
                            <input type="password" id="appSecret" class="form-control font-monospace"
                                   value="<?= htmlspecialchars($appSecret) ?>" placeholder="••••••••••••••••">
                            <button class="btn btn-link btn-sm p-0 mt-1 text-muted" type="button"
                                    onclick="toggleSecret()">
                                <i class="bi bi-eye" id="secretEyeIcon"></i> mostrar
                            </button>
                        </div>
                    </div>
                    <div id="credMsg" class="mt-3"></div>
                    <button class="btn btn-primary mt-3" onclick="saveCredentials()">
                        <i class="bi bi-save"></i> Guardar credenciais
                    </button>
                </div>
            </div>

            <!-- Access Token -->
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    <i class="bi bi-person-badge me-1"></i> Access Token
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Obtém um User Access Token no
                        <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>
                        com a permissão <code>pages_read_engagement</code>.
                        Cola-o aqui e usa o botão "Trocar" para o converter num token de 60 dias.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Token</label>
                        <textarea id="accessToken" class="form-control font-monospace"
                                  rows="3" placeholder="EAAxxxxxx..."><?= htmlspecialchars($accessToken) ?></textarea>
                        <?php if ($tokenExp): ?>
                            <div class="form-text">
                                Expiração guardada: <?= date('j M Y \à\s H:i', strtotime($tokenExp)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="tokenMsg" class="mb-3"></div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" onclick="saveToken()">
                            <i class="bi bi-save"></i> Guardar token
                        </button>
                        <button class="btn btn-outline-success" onclick="exchangeToken()"
                                <?= (!$appId || !$appSecret) ? 'disabled title="Configura App ID e Secret primeiro"' : '' ?>>
                            <i class="bi bi-arrow-repeat"></i> Trocar por token de longa duração
                        </button>
                        <button class="btn btn-outline-secondary" onclick="testToken()">
                            <i class="bi bi-plug"></i> Testar token
                        </button>
                    </div>
                </div>
            </div>

            <!-- How to get a token -->
            <div class="card border-0 bg-light">
                <div class="card-body">
                    <h6 class="fw-semibold mb-2"><i class="bi bi-question-circle me-1"></i> Como obter um token de 60 dias</h6>
                    <ol class="mb-0 small text-muted">
                        <li>Vai ao <a href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a></li>
                        <li>Seleciona a tua app no menu "Meta App"</li>
                        <li>Clica em <strong>Generate Access Token</strong> e autoriza a permissão <code>pages_read_engagement</code></li>
                        <li>Copia o token gerado (válido por ~1 hora)</li>
                        <li>Cola-o acima e clica em <strong>Trocar por token de longa duração</strong> (valid por ~60 dias)</li>
                    </ol>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── SMTP ──────────────────────────────────────────────────────────────────────
document.getElementById('togglePassword').addEventListener('click', function () {
    const input = document.getElementById('smtpPassword');
    const icon  = document.getElementById('pwEyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
});

document.getElementById('testBtn').addEventListener('click', async function () {
    const btn   = this;
    const msgEl = document.getElementById('testMsg');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> A enviar...';
    msgEl.innerHTML = '';
    try {
        const resp = await fetch('configs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ action: 'test_smtp' }),
        });
        const data = await resp.json();
        msgEl.innerHTML = `<div class="alert alert-${data.success ? 'success' : 'danger'} py-2">${data.message || data.error}</div>`;
    } catch (e) {
        msgEl.innerHTML = `<div class="alert alert-danger py-2">Erro de comunicação.</div>`;
    }
    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-plug"></i> Enviar email de teste';
});

// ── Facebook ──────────────────────────────────────────────────────────────────
function showMsg(elId, type, text) {
    document.getElementById(elId).innerHTML =
        `<div class="alert alert-${type} py-2 mb-0">${text}</div>`;
}

function clearMsg(elId) {
    document.getElementById(elId).innerHTML = '';
}

function toggleSecret() {
    const el   = document.getElementById('appSecret');
    const icon = document.getElementById('secretEyeIcon');
    if (el.type === 'password') {
        el.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        el.type = 'password';
        icon.className = 'bi bi-eye';
    }
}

async function post(action, extra = {}) {
    const resp = await fetch('configs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ action, ...extra }),
    });
    return resp.json();
}

async function saveCredentials() {
    clearMsg('credMsg');
    const data = await post('save_facebook_credentials', {
        app_id:     document.getElementById('appId').value.trim(),
        app_secret: document.getElementById('appSecret').value.trim(),
    });
    showMsg('credMsg', data.success ? 'success' : 'danger', data.message || data.error);
}

async function saveToken() {
    clearMsg('tokenMsg');
    const token = document.getElementById('accessToken').value.trim();
    if (!token) { showMsg('tokenMsg', 'warning', 'Token vazio.'); return; }
    const data = await post('save_facebook_token', { token });
    if (data.success) {
        const exp = data.expires_at
            ? ` Expira em ${new Date(data.expires_at).toLocaleDateString('pt-PT', {day:'numeric',month:'short',year:'numeric'})}.`
            : '';
        showMsg('tokenMsg', 'success', data.message + exp);
    } else {
        showMsg('tokenMsg', 'danger', data.error);
    }
}

async function exchangeToken() {
    clearMsg('tokenMsg');
    const shortToken = document.getElementById('accessToken').value.trim();
    if (!shortToken) { showMsg('tokenMsg', 'warning', 'Cola primeiro o token curto no campo acima.'); return; }
    const data = await post('exchange_facebook_token', {
        app_id:      document.getElementById('appId').value.trim(),
        app_secret:  document.getElementById('appSecret').value.trim(),
        short_token: shortToken,
    });
    if (data.success) {
        document.getElementById('accessToken').value = data.access_token;
        const exp = data.expires_at
            ? ` Válido até ${new Date(data.expires_at).toLocaleDateString('pt-PT', {day:'numeric',month:'short',year:'numeric'})}.`
            : '';
        showMsg('tokenMsg', 'success', data.message + exp);
    } else {
        showMsg('tokenMsg', 'danger', data.error);
    }
}

async function testToken() {
    clearMsg('tokenMsg');
    const token = document.getElementById('accessToken').value.trim();
    const data  = await post('test_facebook_token', { token: token || undefined });
    showMsg('tokenMsg', data.success ? 'success' : 'danger', data.message || data.error);
}
</script>
</body>
</html>
