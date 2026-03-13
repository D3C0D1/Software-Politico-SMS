<?php
require_once 'config.php';

echo "<h2>Verificando y Corrigiendo Estructura de Base de Datos...</h2>";

try {
    // 1. Check 'users' table columns
    echo "1. Verificando tabla 'users'...<br>";
    $columns = [];
    $stmt = $pdo->query("PRAGMA table_info(users)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['name'];
    }

    if (!in_array('organizacion_id', $columns)) {
        echo "   - Falta columna 'organizacion_id'. Agregándola...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN organizacion_id INTEGER DEFAULT 1");
        echo "   - Columna agregada.<br>";
    }
    else {
        echo "   - Columna 'organizacion_id' ya existe.<br>";
    }

    // 2. Check 'registros' table columns
    echo "2. Verificando tabla 'registros'...<br>";
    $rColumns = [];
    $stmt = $pdo->query("PRAGMA table_info(registros)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rColumns[] = $row['name'];
    }

    if (!in_array('organizacion_id', $rColumns)) {
        echo "   - Falta columna 'organizacion_id'. Agregándola...<br>";
        $pdo->exec("ALTER TABLE registros ADD COLUMN organizacion_id INTEGER DEFAULT 1");
        echo "   - Columna agregada.<br>";
    }
    else {
        echo "   - Columna 'organizacion_id' ya existe.<br>";
    }

    // 3. Check 'app_config' table columns
    echo "3. Verificando tabla 'app_config'...<br>";
    $acColumns = [];
    $stmt = $pdo->query("PRAGMA table_info(app_config)");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $acColumns[] = $row['name'];
    }

    if (!in_array('organizacion_id', $acColumns)) {
        echo "   - Falta columna 'organizacion_id'. Agregándola...<br>";
        $pdo->exec("ALTER TABLE app_config ADD COLUMN organizacion_id INTEGER DEFAULT 1");
        echo "   - Columna agregada.<br>";
    }
    else {
        echo "   - Columna 'organizacion_id' ya existe.<br>";
    }

    echo "<h3>Verificación completada. Intente iniciar sesión nuevamente.</h3>";
    echo "<a href='index.php'>Volver al Login</a>";

}
catch (PDOException $e) {
    echo "<h2>Error Crítico:</h2>";
    echo $e->getMessage();
}
?>