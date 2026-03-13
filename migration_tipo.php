<?php
require_once 'config.php';

try {
    // Check if column exists first (SQLite doesn't support IF NOT EXISTS for columns in older versions, but let's try add)
    // Or just run it and catch error.
    $pdo->exec("ALTER TABLE registros ADD COLUMN tipo TEXT DEFAULT 'votante'");
    echo "Column 'tipo' added successfully.";
}
catch (PDOException $e) {
    if (strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "Column 'tipo' already exists.";
    }
    else {
        echo "Error: " . $e->getMessage();
    }
}
?>
