<?php
require_once 'config.php';

try {
    // Create settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");

    // Insert default empty values if not exist
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('onurix_client', '')");
    $stmt->execute();
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('onurix_key', '')");
    $stmt->execute();

    echo "Settings table created/verified successfully.";

}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
