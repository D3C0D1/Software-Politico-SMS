<?php
require_once 'config.php';

try {
    $stmt = $pdo->query("PRAGMA table_info(registros)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in 'registros' table:\n";
    foreach ($columns as $col) {
        echo "- " . $col['name'] . " (" . $col['type'] . ")\n";
    }

    $stmt = $pdo->query("SELECT * FROM registros LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "\nExample row:\n";
        print_r($row);
    }
    else {
        echo "\nTable is empty.\n";
    }

}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
