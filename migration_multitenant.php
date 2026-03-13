<?php
require_once 'config.php';

echo "<h2>Iniciando Migración Multi-Tenant...</h2>";

try {
    // 1. Create 'organizaciones' table
    echo "1. Creando tabla 'organizaciones'...<br>";
    $sqlOrg = "CREATE TABLE IF NOT EXISTS organizaciones (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        nombre_organizacion VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    // MySQL compatibility
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sqlOrg = "CREATE TABLE IF NOT EXISTS organizaciones (
            id INT AUTO_INCREMENT PRIMARY KEY, 
            nombre_organizacion VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    }
    $pdo->exec($sqlOrg);

    // 2. Insert Default Organization (if empty)
    $stmt = $pdo->query("SELECT COUNT(*) FROM organizaciones");
    if ($stmt->fetchColumn() == 0) {
        echo "   - Insertando 'Organización Principal' (ID 1)...<br>";
        $stmt = $pdo->prepare("INSERT INTO organizaciones (nombre_organizacion) VALUES (?)");
        $stmt->execute(['Organización Principal']);
        $defaultOrgId = $pdo->lastInsertId();
    }
    else {
        echo "   - 'Organización Principal' ya existe.<br>";
        // Assume ID 1 is the default
        $defaultOrgId = 1;
    }

    // 3. Add 'organizacion_id' to 'users' table
    echo "2. Actualizando tabla 'users'...<br>";
    try {
        $pdo->query("SELECT organizacion_id FROM users LIMIT 1");
        echo "   - Columna 'organizacion_id' ya existe en 'users'.<br>";
    }
    catch (PDOException $e) {
        echo "   - Agregando columna 'organizacion_id' a 'users'...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN organizacion_id INTEGER DEFAULT 1");
        // Update existing users to default org
        $pdo->exec("UPDATE users SET organizacion_id = $defaultOrgId WHERE organizacion_id IS NULL");
    }

    // 4. Add 'organizacion_id' to 'registros' table
    echo "3. Actualizando tabla 'registros'...<br>";
    try {
        $pdo->query("SELECT organizacion_id FROM registros LIMIT 1");
        echo "   - Columna 'organizacion_id' ya existe en 'registros'.<br>";
    }
    catch (PDOException $e) {
        echo "   - Agregando columna 'organizacion_id' a 'registros'...<br>";
        $pdo->exec("ALTER TABLE registros ADD COLUMN organizacion_id INTEGER DEFAULT 1");
        // Update existing records to default org
        $pdo->exec("UPDATE registros SET organizacion_id = $defaultOrgId WHERE organizacion_id IS NULL");
    }

    // 5. Update 'app_config' for Multi-tenancy
    echo "4. Actualizando tabla 'app_config'...<br>";
    try {
        $pdo->query("SELECT organizacion_id FROM app_config LIMIT 1");
        echo "   - Columna 'organizacion_id' ya existe en 'app_config'.<br>";
    }
    catch (PDOException $e) {
        echo "   - Agregando columna 'organizacion_id' a 'app_config'...<br>";
        $pdo->exec("ALTER TABLE app_config ADD COLUMN organizacion_id INTEGER DEFAULT 1");

        // Remove UNIQUE constraint on setting_key if possible (complex in SQLite/MySQL without dropping)
        // For simplicity, we will just update the current rows to match ID 1.
        // In future usage, we will select by WHERE organizacion_id = ? AND setting_key = ?
        $pdo->exec("UPDATE app_config SET organizacion_id = $defaultOrgId WHERE organizacion_id IS NULL");
    }

    // Note: To properly make (organizacion_id, setting_key) unique and remove setting_key unique, 
    // it usually requires recreating the table. For now, we rely on the application logic 
    // to check both fields, and we won't strictly enforce DB constraint to avoid data loss risk during this script.

    echo "<h2>¡Migración completada con éxito!</h2>";
    echo "<p>El sistema ahora soporta múltiples organizaciones.</p>";
    echo "<a href='index.php'>Volver al Inicio</a>";

}
catch (PDOException $e) {
    echo "<h2>Error en la Migración:</h2>";
    echo $e->getMessage();
}
?>