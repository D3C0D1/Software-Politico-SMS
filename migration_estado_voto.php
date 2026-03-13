<?php
require_once 'config.php';

try {
    $pdo->exec("ALTER TABLE registros ADD COLUMN estado_voto TEXT DEFAULT 'pendiente'");
    echo "Column 'estado_voto' added successfully.";
}
catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "Column 'estado_voto' already exists.";
    }
    else {
        echo "Error: " . $e->getMessage();
    }
}
?>
