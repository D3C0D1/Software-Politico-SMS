<?php
require_once 'config.php';

try {
    // Add 'ya_voto' column to registros table
    $pdo->exec("ALTER TABLE registros ADD COLUMN ya_voto INTEGER DEFAULT 0");

    echo "✅ Columna 'ya_voto' añadida exitosamente a la tabla registros!\n";
    echo "   0 = No ha votado\n";
    echo "   1 = Ya votó\n";


}
catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column name') !== false) {
        echo "ℹ️  La columna 'ya_voto' ya existe.\n";
    }
    else {
        echo "❌ Error en la migración: " . $e->getMessage() . "\n";
    }
}
?>
