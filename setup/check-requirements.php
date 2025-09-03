<?php
echo "Braga Agenda - System Requirements Check\n";
echo "========================================\n\n";

$requirements = [
    'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'SQLite3 Support' => extension_loaded('pdo_sqlite'),
    'cURL Support' => extension_loaded('curl'),
    'Multibyte String Support' => extension_loaded('mbstring'),
    'DOM Support' => extension_loaded('dom'),
    'libxml Support' => extension_loaded('libxml'),
];

$allPassed = true;

foreach ($requirements as $requirement => $passed) {
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    echo sprintf("%-30s %s\n", $requirement, $status);
    if (!$passed) {
        $allPassed = false;
    }
}

echo "\nDirectory Permissions:\n";

$directories = [
    'data' => __DIR__ . '/data',
    'uploads' => __DIR__ . '/uploads',
];

foreach ($directories as $name => $path) {
    if (!file_exists($path)) {
        echo sprintf("%-15s ⚠️  Directory does not exist (will be created)\n", $name);
    } elseif (is_writable($path)) {
        echo sprintf("%-15s ✅ WRITABLE\n", $name);
    } else {
        echo sprintf("%-15s ❌ NOT WRITABLE\n", $name);
        $allPassed = false;
    }
}

echo "\nPHP Configuration:\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";
echo "Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";

if ($allPassed) {
    echo "\n🎉 All requirements met! You can proceed with installation.\n";
    echo "\nNext steps:\n";
    echo "1. Run: php setup-gnration-scraper.php\n";
    echo "2. Test: php test-gnration-scraper.php\n";
    echo "3. Visit your site in a browser\n";
} else {
    echo "\n⚠️  Some requirements are missing. Please install missing extensions.\n";
    echo "\nOn Ubuntu/Debian:\n";
    echo "sudo apt install php-sqlite3 php-curl php-mbstring php-dom\n";
    echo "\nOn CentOS/RHEL:\n";
    echo "sudo yum install php-pdo php-curl php-mbstring php-xml\n";
}

echo "\n";