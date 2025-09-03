<?php
/**
 * Braga Agenda Installer
 * Interactive installation script for the event aggregation site
 */

class BragaAgendaInstaller {
    private $steps = [
        'check_requirements',
        'create_directories',
        'init_database',
        'setup_scrapers',
        'create_sample_data',
        'finalize'
    ];
    
    private $currentStep = 0;
    private $errors = [];
    private $warnings = [];
    
    public function run() {
        $this->displayHeader();
        
        if (isset($_POST['action'])) {
            $this->handleAction($_POST['action']);
        } else {
            $this->showWelcome();
        }
    }
    
    private function displayHeader() {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Instalador - Braga Agenda</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; color: #000; line-height: 1.6; }
                .container { max-width: 800px; margin: 2rem auto; padding: 0 1rem; }
                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 2rem; margin-bottom: 2rem; }
                .header h1 { font-size: 2.5rem; font-weight: 700; }
                .step { background: #f9f9f9; border: 1px solid #000; padding: 2rem; margin: 1rem 0; }
                .step h2 { font-size: 1.5rem; margin-bottom: 1rem; }
                .step-complete { background: #fff; border-color: #000; }
                .step-current { background: #fff; border: 2px solid #000; }
                .btn { background: #000; color: #fff; border: none; padding: 1rem 2rem; font-size: 1rem; cursor: pointer; margin: 0.5rem; }
                .btn:hover { background: #333; }
                .btn-secondary { background: #fff; color: #000; border: 1px solid #000; }
                .btn-secondary:hover { background: #000; color: #fff; }
                .error { color: #000; background: #f5f5f5; border: 1px solid #000; padding: 1rem; margin: 0.5rem 0; }
                .warning { color: #000; background: #fafafa; border: 1px dashed #000; padding: 1rem; margin: 0.5rem 0; }
                .success { color: #000; background: #f9f9f9; border: 2px solid #000; padding: 1rem; margin: 0.5rem 0; }
                .requirement { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
                .pass { font-weight: bold; }
                .fail { font-weight: bold; }
                ul { margin-left: 2rem; margin-top: 1rem; }
                code { background: #f5f5f5; padding: 0.25rem 0.5rem; font-family: 'Courier New', monospace; }
                .progress { width: 100%; height: 20px; border: 1px solid #000; margin: 1rem 0; }
                .progress-bar { height: 100%; background: #000; transition: width 0.3s; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Braga Agenda</h1>
                    <p>Instalador do Sistema de Agregação de Eventos</p>
                </div>
        <?php
    }
    
    private function showWelcome() {
        ?>
        <div class="step step-current">
            <h2>Bem-vindo ao Instalador</h2>
            <p>Este instalador irá configurar o Braga Agenda no seu servidor.</p>
            
            <h3>O que será instalado:</h3>
            <ul>
                <li>Base de dados SQLite</li>
                <li>Estrutura de diretórios</li>
                <li>Scraper do Gnration</li>
                <li>Dados de exemplo</li>
            </ul>
            
            <form method="post">
                <input type="hidden" name="action" value="start_install">
                <button type="submit" class="btn">Iniciar Instalação</button>
            </form>
        </div>
        <?php
        $this->displayFooter();
    }
    
    private function handleAction($action) {
        switch ($action) {
            case 'start_install':
                $this->runInstallation();
                break;
            case 'retry':
                $this->runInstallation();
                break;
            default:
                $this->showWelcome();
        }
    }
    
    private function runInstallation() {
        $totalSteps = count($this->steps);
        $completedSteps = 0;
        
        echo '<div class="progress">';
        echo '<div class="progress-bar" style="width: 0%"></div>';
        echo '</div>';
        
        foreach ($this->steps as $step) {
            $result = $this->executeStep($step);
            $completedSteps++;
            $progress = ($completedSteps / $totalSteps) * 100;
            
            echo '<script>document.querySelector(".progress-bar").style.width = "' . $progress . '%";</script>';
            
            if (!$result) {
                $this->showError("Instalação falhou no passo: $step");
                return;
            }
        }
        
        $this->showSuccess();
    }
    
    private function executeStep($step) {
        switch ($step) {
            case 'check_requirements':
                return $this->checkRequirements();
            case 'create_directories':
                return $this->createDirectories();
            case 'init_database':
                return $this->initDatabase();
            case 'setup_scrapers':
                return $this->setupScrapers();
            case 'create_sample_data':
                return $this->createSampleData();
            case 'finalize':
                return $this->finalize();
            default:
                return false;
        }
    }
    
    private function checkRequirements() {
        echo '<div class="step step-current">';
        echo '<h2>1. Verificando Requisitos</h2>';
        
        $requirements = [
            'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'SQLite3 PDO' => extension_loaded('pdo_sqlite'),
            'cURL' => extension_loaded('curl'),
            'Multibyte String' => extension_loaded('mbstring'),
            'DOM' => extension_loaded('dom'),
            'libxml' => extension_loaded('libxml'),
        ];
        
        $allPassed = true;
        
        foreach ($requirements as $req => $passed) {
            echo '<div class="requirement">';
            echo '<span>' . $req . '</span>';
            echo '<span class="' . ($passed ? 'pass">✅' : 'fail">❌') . '</span>';
            echo '</div>';
            
            if (!$passed) {
                $allPassed = false;
                $this->errors[] = "Requisito em falta: $req";
            }
        }
        
        if (!$allPassed) {
            echo '<div class="error">';
            echo '<h3>Extensões PHP em Falta</h3>';
            echo '<p>Instale as extensões necessárias:</p>';
            echo '<code>sudo apt install php-sqlite3 php-mbstring php-curl php-dom</code>';
            echo '</div>';
        }
        
        echo '</div>';
        return $allPassed;
    }
    
    private function createDirectories() {
        echo '<div class="step step-current">';
        echo '<h2>2. Criando Diretórios</h2>';
        
        $dirs = ['data', 'uploads', 'admin'];
        $success = true;
        
        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                if (mkdir($dir, 0755, true)) {
                    echo "<p>✅ Criado: $dir/</p>";
                } else {
                    echo "<p>❌ Falha ao criar: $dir/</p>";
                    $success = false;
                }
            } else {
                echo "<p>✅ Existe: $dir/</p>";
            }
            
            if (!is_writable($dir)) {
                echo "<p>⚠️ Sem permissão de escrita: $dir/</p>";
                $this->warnings[] = "Diretório $dir pode não ter permissões corretas";
            }
        }
        
        echo '</div>';
        return $success;
    }
    
    private function initDatabase() {
        echo '<div class="step step-current">';
        echo '<h2>3. Inicializando Base de Dados</h2>';
        
        try {
            require_once 'includes/Database.php';
            $db = new Database();
            echo '<p>✅ Base de dados SQLite criada</p>';
            echo '<p>✅ Tabelas criadas</p>';
            echo '</div>';
            return true;
        } catch (Exception $e) {
            echo '<p>❌ Erro: ' . $e->getMessage() . '</p>';
            echo '</div>';
            return false;
        }
    }
    
    private function setupScrapers() {
        echo '<div class="step step-current">';
        echo '<h2>4. Configurando Scrapers</h2>';
        
        try {
            require_once 'scrapers/ScraperManager.php';
            require_once 'includes/Database.php';
            
            $db = new Database();
            $scraperManager = new ScraperManager($db);
            
            if ($scraperManager->addSource('Gnration - Braga', 'https://www.gnration.pt/ver-todos/', 'GnrationScraper')) {
                echo '<p>✅ Scraper Gnration adicionado</p>';
            } else {
                echo '<p>⚠️ Scraper Gnration já existe</p>';
            }
            
            echo '</div>';
            return true;
        } catch (Exception $e) {
            echo '<p>❌ Erro: ' . $e->getMessage() . '</p>';
            echo '</div>';
            return false;
        }
    }
    
    private function createSampleData() {
        echo '<div class="step step-current">';
        echo '<h2>5. Criando Dados de Exemplo</h2>';
        
        try {
            require_once 'includes/Database.php';
            require_once 'includes/Event.php';
            
            $db = new Database();
            $eventModel = new Event($db);
            
            $sampleEvents = [
                [
                    'title' => 'Concerto de Inauguração',
                    'description' => 'Evento de exemplo para demonstrar o sistema',
                    'event_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
                    'category' => 'Música',
                    'url' => 'https://example.com/event1'
                ],
                [
                    'title' => 'Exposição de Arte Contemporânea',
                    'description' => 'Mostra de artistas locais de Braga',
                    'event_date' => date('Y-m-d H:i:s', strtotime('+2 weeks')),
                    'category' => 'Arte',
                    'url' => 'https://example.com/event2'
                ]
            ];
            
            foreach ($sampleEvents as $event) {
                $eventModel->createEvent(
                    $event['title'],
                    $event['description'],
                    $event['event_date'],
                    $event['category'],
                    null,
                    $event['url']
                );
            }
            
            echo '<p>✅ ' . count($sampleEvents) . ' eventos de exemplo criados</p>';
            echo '</div>';
            return true;
        } catch (Exception $e) {
            echo '<p>❌ Erro: ' . $e->getMessage() . '</p>';
            echo '</div>';
            return false;
        }
    }
    
    private function finalize() {
        echo '<div class="step step-current">';
        echo '<h2>6. Finalizando</h2>';
        
        // Create .htaccess if it doesn't exist
        if (!file_exists('.htaccess')) {
            $htaccessContent = 'RewriteEngine On
Header always set X-Content-Type-Options nosniff
<Files "*.db">
    Order Allow,Deny
    Deny from all
</Files>';
            
            if (file_put_contents('.htaccess', $htaccessContent)) {
                echo '<p>✅ Arquivo .htaccess criado</p>';
            }
        }
        
        echo '<p>✅ Instalação concluída</p>';
        echo '</div>';
        return true;
    }
    
    private function showError($message) {
        echo '<div class="error">';
        echo '<h3>Erro na Instalação</h3>';
        echo '<p>' . $message . '</p>';
        
        if (!empty($this->errors)) {
            echo '<ul>';
            foreach ($this->errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<form method="post">';
        echo '<input type="hidden" name="action" value="retry">';
        echo '<button type="submit" class="btn btn-secondary">Tentar Novamente</button>';
        echo '</form>';
        echo '</div>';
        
        $this->displayFooter();
    }
    
    private function showSuccess() {
        echo '<div class="success">';
        echo '<h2>🎉 Instalação Concluída com Sucesso!</h2>';
        echo '<p>O Braga Agenda foi instalado e está pronto para usar.</p>';
        
        echo '<h3>Próximos Passos:</h3>';
        echo '<ul>';
        echo '<li><strong>Ver o site:</strong> <a href="index.php">index.php</a></li>';
        echo '<li><strong>Painel admin:</strong> <a href="admin/">admin/</a></li>';
        echo '<li><strong>Executar scrapers:</strong> <code>php run-scrapers.php</code></li>';
        echo '<li><strong>Testar Gnration scraper:</strong> <code>php test-gnration-scraper.php</code></li>';
        echo '</ul>';
        
        if (!empty($this->warnings)) {
            echo '<h3>Avisos:</h3>';
            echo '<ul>';
            foreach ($this->warnings as $warning) {
                echo '<li>' . $warning . '</li>';
            }
            echo '</ul>';
        }
        
        echo '<h3>Automatização:</h3>';
        echo '<p>Para executar scrapers automaticamente, adicione ao crontab:</p>';
        echo '<code>0 * * * * cd ' . __DIR__ . ' && php run-scrapers.php</code>';
        
        echo '<p style="margin-top: 2rem;">';
        echo '<a href="index.php" class="btn">Ver Site</a>';
        echo '<a href="admin/" class="btn btn-secondary">Painel Admin</a>';
        echo '</p>';
        
        echo '</div>';
        
        $this->displayFooter();
    }
    
    private function displayFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }
}

// Run installer
$installer = new BragaAgendaInstaller();
$installer->run();
?>