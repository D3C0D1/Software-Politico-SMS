<?php
// export_db.php - Exporta Base de Datos Local (SQLite/MySQL) a un archivo .sql compatible
error_reporting(0);
ini_set('display_errors', 0);
ini_set('memory_limit', '512M'); // Aumentar memoria para bases grandes
set_time_limit(300); // 5 minutos máximo

require_once 'config.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Acceso Denegado.");
}

$filename = "politico_backup_" . date('Y-m-d_H-i') . ".sql";

// Headers
header('Content-Type: application/sql; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');

// SQL Header
echo "-- Backup Generado: " . date('Y-m-d H:i:s') . "\n";
echo "-- Sistema: Politico App\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "START TRANSACTION;\n";
echo "SET time_zone = \"-05:00\";\n\n";

// Helper function
function sql_value($value, $pdo)
{
    if ($value === null)
        return 'NULL';
    return $pdo->quote($value);
}

// Helper to get CREATE TABLE statement
function get_create_table_sql($pdo, $table)
{
    try {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row && isset($row[1])) {
            return $row[1] . ";";
        }
    }
    catch (Exception $e) {
        return "";
    }
    return "";
}

$tables = ['registros', 'users', 'sms_templates']; // Reordered to put child table first just in case

foreach ($tables as $table) {
    // 1. Structure
    echo "-- Estructura de tabla para `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";

    $createSQL = get_create_table_sql($pdo, $table);

    if ($createSQL) {
        echo $createSQL . "\n\n";
    }
    else {
        // Fallback Manual Structure
        if ($table === 'users') {
            echo "CREATE TABLE `users` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `username` varchar(50) NOT NULL,
              `password` varchar(255) NOT NULL,
              `name` varchar(100) DEFAULT NULL,
              `email` varchar(100) DEFAULT NULL,
              `phone` varchar(20) DEFAULT NULL,
              `role` varchar(20) DEFAULT 'votante',
              `onurix_client` varchar(100) DEFAULT NULL,
              `onurix_key` varchar(100) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
        elseif ($table === 'registros') {
            echo "CREATE TABLE `registros` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) DEFAULT NULL,
              `tipo` varchar(20) DEFAULT 'votante',
              `nombres_apellidos` varchar(150) DEFAULT NULL,
              `cedula` varchar(20) DEFAULT NULL,
              `lugar_votacion` varchar(150) DEFAULT NULL,
              `mesa` varchar(10) DEFAULT NULL,
              `celular` varchar(20) DEFAULT NULL,
              `municipio` varchar(100) DEFAULT NULL,
              `departamento` varchar(100) DEFAULT NULL,
              `barrio_vereda` varchar(100) DEFAULT NULL,
              `direccion` varchar(255) DEFAULT NULL,
              `email` varchar(100) DEFAULT NULL,
              `estado_voto` varchar(20) DEFAULT 'pendiente',
              `ya_voto` tinyint(1) DEFAULT 0,
              `sms_inscripcion` tinyint(1) DEFAULT 0,
              `sms_citacion` tinyint(1) DEFAULT 0,
              `sms_confirmacion` tinyint(1) DEFAULT 0,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `user_id` (`user_id`),
              KEY `cedula` (`cedula`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
        elseif ($table === 'sms_templates') {
            echo "CREATE TABLE `sms_templates` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(50) NOT NULL,
              `label` varchar(100) DEFAULT NULL,
              `content` text NOT NULL,
              `created_at` timestamp NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\n\n";
        }
    }

    // 2. Data (Row by Row to avoid Memory Limit)
    echo "-- Volcado de datos para `$table`\n";
    try {
        // Count rows first
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $rowCount = $countStmt->fetchColumn();
        echo "-- Total registros encontrados: $rowCount\n";

        if ($rowCount > 0) {
            // Get columns first
            $stmt = $pdo->query("SELECT * FROM `$table` LIMIT 1");
            $firstRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $columns = array_keys($firstRow);
            $colNames = implode("`, `", $columns);

            echo "INSERT INTO `$table` (`$colNames`) VALUES \n";

            // Now fetch all
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $firstTable = true;

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$firstTable)
                    echo ",\n";

                $values = [];
                foreach ($columns as $col) {
                    // Ensure we check if key exists in this row, though it should
                    $val = isset($row[$col]) ? $row[$col] : null;
                    $values[] = sql_value($val, $pdo);
                }
                echo "(" . implode(", ", $values) . ")";
                $firstTable = false;

                // Periodic flush
                if (ob_get_level() > 0)
                    ob_flush();
                flush();
            }
            echo ";\n\n";
        }
    }
    catch (Exception $e) {
        echo "-- Error exportando datos de $table: " . $e->getMessage() . "\n\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "COMMIT;\n";
?>
