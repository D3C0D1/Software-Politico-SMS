<?php
require_once 'config.php';

try {
    // Create 'registros' table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombres_apellidos TEXT,
        cedula TEXT UNIQUE,
        lugar_votacion TEXT,
        mesa TEXT,
        celular TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Checked/Created 'registros' table.<br>";

    // Add Onurix columns to 'users' table if they don't exist
    $cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'name');

    if (!in_array('name', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN name TEXT");
        echo "Added 'name' column.<br>";
    }

    if (!in_array('email', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email TEXT");
        echo "Added 'email' column.<br>";
    }

    if (!in_array('phone', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN phone TEXT");
        echo "Added 'phone' column.<br>";
    }

    if (!in_array('onurix_client', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN onurix_client TEXT");
        echo "Added 'onurix_client' column.<br>";
    }

    if (!in_array('onurix_key', $colNames)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN onurix_key TEXT");
        echo "Added 'onurix_key' column.<br>";
    }

    echo "Migration completed successfully.";

}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
