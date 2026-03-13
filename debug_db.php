<?php
require_once 'config.php';

echo "<h2>Tablas en la base de datos:</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "<h3>Tabla: $table</h3>";
    $columns = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
}

echo "<h2>Datos de ejemplo de 'users' (primeros 5):</h2>";
$users = $pdo->query("SELECT * FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($users);
echo "</pre>";

echo "<h2>Datos de ejemplo de 'registros' (primeros 5):</h2>";
$registros = $pdo->query("SELECT * FROM registros LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($registros);
echo "</pre>";
?>
