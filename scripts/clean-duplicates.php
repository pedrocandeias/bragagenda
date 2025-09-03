<?php
require_once 'config.php';
require_once 'includes/Database.php';

echo "Braga Agenda - Limpeza de Eventos Duplicados\n";
echo "=============================================\n\n";

$db = new Database();
$conn = $db->getConnection();

// Find duplicates based on title and date
echo "1. Procurando duplicados por título e data...\n";

$stmt = $conn->query("
    SELECT title, DATE(event_date) as event_day, COUNT(*) as count, 
           GROUP_CONCAT(id) as ids
    FROM events 
    GROUP BY title, DATE(event_date)
    HAVING COUNT(*) > 1
    ORDER BY count DESC
");

$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($duplicates)) {
    echo "✅ Nenhum duplicado encontrado.\n";
} else {
    echo "❌ Encontrados " . count($duplicates) . " grupos de eventos duplicados:\n\n";
    
    $totalRemoved = 0;
    
    foreach ($duplicates as $duplicate) {
        $ids = explode(',', $duplicate['ids']);
        $keepId = array_shift($ids); // Keep the first one
        $removeIds = $ids;
        
        echo "📅 {$duplicate['title']} ({$duplicate['event_day']})\n";
        echo "   Instâncias: {$duplicate['count']} | Manter ID: $keepId | Remover: " . implode(', ', $removeIds) . "\n";
        
        // Remove duplicates
        foreach ($removeIds as $removeId) {
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            if ($stmt->execute([$removeId])) {
                echo "   ✅ Removido evento ID: $removeId\n";
                $totalRemoved++;
            } else {
                echo "   ❌ Falha ao remover evento ID: $removeId\n";
            }
        }
        echo "\n";
    }
    
    echo "📊 Total de eventos duplicados removidos: $totalRemoved\n";
}

// Find duplicates based on URL
echo "\n2. Procurando duplicados por URL...\n";

$stmt = $conn->query("
    SELECT url, COUNT(*) as count, GROUP_CONCAT(id) as ids
    FROM events 
    WHERE url IS NOT NULL AND url != ''
    GROUP BY url
    HAVING COUNT(*) > 1
    ORDER BY count DESC
");

$urlDuplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($urlDuplicates)) {
    echo "✅ Nenhum duplicado por URL encontrado.\n";
} else {
    echo "❌ Encontrados " . count($urlDuplicates) . " URLs duplicados:\n\n";
    
    $urlRemoved = 0;
    
    foreach ($urlDuplicates as $duplicate) {
        $ids = explode(',', $duplicate['ids']);
        $keepId = array_shift($ids); // Keep the first one
        $removeIds = $ids;
        
        echo "🔗 {$duplicate['url']}\n";
        echo "   Instâncias: {$duplicate['count']} | Manter ID: $keepId | Remover: " . implode(', ', $removeIds) . "\n";
        
        // Remove duplicates
        foreach ($removeIds as $removeId) {
            $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
            if ($stmt->execute([$removeId])) {
                echo "   ✅ Removido evento ID: $removeId\n";
                $urlRemoved++;
            } else {
                echo "   ❌ Falha ao remover evento ID: $removeId\n";
            }
        }
        echo "\n";
    }
    
    echo "📊 Total de eventos com URL duplicado removidos: $urlRemoved\n";
}

// Show final statistics
echo "\n3. Estatísticas finais...\n";

$stmt = $conn->query("SELECT COUNT(*) as total FROM events");
$total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $conn->query("SELECT COUNT(DISTINCT source_id) as sources FROM events WHERE source_id IS NOT NULL");
$sources = $stmt->fetch(PDO::FETCH_ASSOC)['sources'];

echo "📊 Total de eventos na base de dados: $total\n";
echo "📊 Fontes diferentes: $sources\n";

echo "\n🎉 Limpeza de duplicados concluída!\n";
echo "\nPróximos passos:\n";
echo "- Executar scrapers: php run-scrapers.php\n";
echo "- Verificar no site: index.php\n";

echo "\n";