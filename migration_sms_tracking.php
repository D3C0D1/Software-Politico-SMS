<?php
require_once 'config.php';

try {
    // Add SMS tracking columns to registros table
    $pdo->exec("ALTER TABLE registros ADD COLUMN sms_inscripcion INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE registros ADD COLUMN sms_citacion INTEGER DEFAULT 0");
    $pdo->exec("ALTER TABLE registros ADD COLUMN sms_confirmacion INTEGER DEFAULT 0");

    echo "Migration completed successfully! SMS tracking columns added.\n";
    echo "- sms_inscripcion (0 = No enviado, 1 = Enviado)\n";
    echo "- sms_citacion (0 = No enviado, 1 = Enviado)\n";
    echo "- sms_confirmacion (0 = No enviado, 1 = Enviado)\n";
}
catch (PDOException $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
    echo "Note: Columns may already exist.\n";
}
?>
