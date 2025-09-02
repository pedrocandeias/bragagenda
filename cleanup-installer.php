<?php
/**
 * Post-installation cleanup script
 * Removes installer files after successful installation
 */

echo "Braga Agenda - Limpeza Pós-Instalação\n";
echo "=====================================\n\n";

$installerFiles = [
    'install.php',
    'install.sh',
    'cleanup-installer.php',
    'check-requirements.php',
    'setup-gnration-scraper.php',
    'setup-example-scraper.php'
];

$removed = 0;
$errors = 0;

foreach ($installerFiles as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "✅ Removido: $file\n";
            $removed++;
        } else {
            echo "❌ Erro ao remover: $file\n";
            $errors++;
        }
    } else {
        echo "⚠️  Não encontrado: $file\n";
    }
}

echo "\n";
echo "Ficheiros removidos: $removed\n";

if ($errors > 0) {
    echo "Erros: $errors\n";
    echo "\nRemova manualmente os ficheiros que falharam.\n";
} else {
    echo "🎉 Limpeza concluída com sucesso!\n";
    echo "\nO sistema está agora limpo dos ficheiros de instalação.\n";
}

echo "\nFicheiros principais do sistema:\n";
echo "• index.php - Página principal\n";
echo "• admin/ - Painel de administração\n";
echo "• run-scrapers.php - Executar scrapers\n";
echo "• test-gnration-scraper.php - Testar scraper\n";

echo "\n";