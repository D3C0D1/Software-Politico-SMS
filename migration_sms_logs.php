<?php
// migration_sms_logs.php
// Create table for detailed SMS logging
require_once 'config.php';

try {
    echo "Starting Migration: Creating sms_logs table...\n";

    $sql = "CREATE TABLE IF NOT EXISTS sms_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        organizacion_id INTEGER,
        user_id INTEGER,
        recipient_phone VARCHAR(20),
        message TEXT,
        status VARCHAR(20) DEFAULT 'success',
        response_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);
    echo "Table 'sms_logs' created successfully (or already exists).\n";

}
catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
}
?>