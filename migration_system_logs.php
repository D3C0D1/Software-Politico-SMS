<?php
// migration_system_logs.php
require_once 'config.php';

try {
    echo "Iniciando migración de tabla system_logs...\n";

    $sql = "CREATE TABLE IF NOT EXISTS `system_logs` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `organizacion_id` INT,
      `user_id` INT,
      `action_type` VARCHAR(50),
      `description` TEXT,
      `ip_address` VARCHAR(45),
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`organizacion_id`) REFERENCES `organizaciones`(`id`) ON DELETE SET NULL,
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Tabla system_logs creada o verificada correctamente.\n";

}
catch (PDOException $e) {
    echo "Error en migración: " . $e->getMessage() . "\n";
}
?>
