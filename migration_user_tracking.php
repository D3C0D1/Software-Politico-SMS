<?php
require_once 'config.php';

try {
    // Add user_id to records to track who created them
    $pdo->exec("ALTER TABLE registros ADD COLUMN user_id INTEGER DEFAULT 0");
    echo "Column 'user_id' added to 'registros'.\n";
}
catch (PDOException $e) {
    echo "Column 'user_id' likely exists in 'registros'.\n";
}

try {
    // Add name to users to track real names of leaders
    $pdo->exec("ALTER TABLE users ADD COLUMN name TEXT");
    echo "Column 'name' added to 'users'.\n";
}
catch (PDOException $e) {
    echo "Column 'name' likely exists in 'users'.\n";
}
?>
