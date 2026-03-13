<?php
require_once 'config.php';

try {
    // Check if column exists first
    $stmt = $pdo->query("PRAGMA table_info(registros)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasYaVoto = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'ya_voto') {
            $hasYaVoto = true;
            break;
        }
    }

    if ($hasYaVoto) {
        // Update existing NULL values to ensure consistency
        $pdo->exec("UPDATE registros SET ya_voto = NULL WHERE ya_voto = 0 OR ya_voto IS NULL");
        echo "✅ Columna 'ya_voto' actualizada. Todos los registros sin respuesta ahora tienen NULL.\n";
    }
    else {
        echo "ℹ️  La columna 'ya_voto' no existe. Ejecute primero migration_ya_voto.php\n";
    }


}
catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
