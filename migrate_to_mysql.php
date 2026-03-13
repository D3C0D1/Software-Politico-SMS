<?php
// migrate_sqlite_to_mysql.php

// SQLite Connection
$sqlite = new PDO('sqlite:database.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// MySQL Connection
$host = '127.0.0.1';
$db = 'politica';
$user = 'root';
$pass = 'mysql';

echo "Migrating from SQLite -> MySQL ($db)...\n";

try {
    $mysql = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create DB if not exists
    $mysql->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $mysql->exec("USE `$db`");

    // --- 1. Organizations ---
    echo "Creating 'organizaciones'...\n";
    $mysql->exec("CREATE TABLE IF NOT EXISTS organizaciones (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre_organizacion VARCHAR(150) NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $sqlite->query("SELECT * FROM organizaciones");
    $orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orgs as $row) {
        $stmtIns = $mysql->prepare("INSERT IGNORE INTO organizaciones (id, nombre_organizacion, status, created_at) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$row['id'], $row['nombre_organizacion'], $row['status'] ?? 'active', $row['created_at']]);
    }
    echo " - Imported " . count($orgs) . " organizations.\n";

    // --- 2. Users ---
    echo "Creating 'users'...\n";
    // Ensure all columns exist
    $mysql->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(150),
        role VARCHAR(50) DEFAULT 'user',
        organizacion_id INT,
        onurix_client VARCHAR(100),
        onurix_key VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $sqlite->query("SELECT * FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $row) {
        // Adjust for potential missing columns in old sqlite rows
        $org_id = $row['organizacion_id'] ?? null;
        $stmtIns = $mysql->prepare("INSERT IGNORE INTO users (id, username, password, name, role, organizacion_id, onurix_client, onurix_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([
            $row['id'],
            $row['username'],
            $row['password'],
            $row['name'],
            $row['role'],
            $org_id,
            $row['onurix_client'] ?? null,
            $row['onurix_key'] ?? null,
            $row['created_at']
        ]);
    }
    echo " - Imported " . count($users) . " users.\n";

    // --- 3. Registros ---
    echo "Creating 'registros'...\n";
    $mysql->exec("CREATE TABLE IF NOT EXISTS registros (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        organizacion_id INT,
        tipo VARCHAR(50) DEFAULT 'votante',
        nombres_apellidos VARCHAR(200),
        cedula VARCHAR(50),
        lugar_votacion VARCHAR(200),
        mesa VARCHAR(20),
        celular VARCHAR(30),
        municipio VARCHAR(100),
        departamento VARCHAR(100),
        barrio_vereda VARCHAR(100),
        direccion VARCHAR(200),
        email VARCHAR(150),
        estado_voto VARCHAR(30) DEFAULT 'pendiente',
        ya_voto TINYINT(1) DEFAULT 0,
        sms_inscripcion TINYINT(1) DEFAULT 0,
        sms_citacion TINYINT(1) DEFAULT 0,
        sms_confirmacion TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (organizacion_id) REFERENCES organizaciones(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $sqlite->query("SELECT * FROM registros");
    $regs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($regs as $row) {
        $org_id = $row['organizacion_id'] ?? 1; // Default to 1 if missing in old sqlite

        $stmtIns = $mysql->prepare("INSERT IGNORE INTO registros (
            id, user_id, organizacion_id, tipo, nombres_apellidos, cedula, 
            lugar_votacion, mesa, celular, municipio, departamento, barrio_vereda, direccion, email, 
            estado_voto, ya_voto, sms_inscripcion, sms_citacion, sms_confirmacion, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtIns->execute([
            $row['id'], $row['user_id'], $org_id, $row['tipo'], $row['nombres_apellidos'], $row['cedula'],
            $row['lugar_votacion'], $row['mesa'], $row['celular'], $row['municipio'] ?? '', $row['departamento'] ?? '',
            $row['barrio_vereda'] ?? '', $row['direccion'] ?? '', $row['email'] ?? '',
            $row['estado_voto'], $row['ya_voto'], $row['sms_inscripcion'], $row['sms_citacion'], $row['sms_confirmacion'],
            $row['created_at'], $row['updated_at']
        ]);
    }
    echo " - Imported " . count($regs) . " registros.\n";

    // --- 4. App Config ---
    echo "Creating 'app_config'...\n";
    $mysql->exec("CREATE TABLE IF NOT EXISTS app_config (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT,
        organizacion_id INT DEFAULT 1,
        UNIQUE(setting_key, organizacion_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // SQLite might not have app_config fully populated or created, check first
    try {
        $stmt = $sqlite->query("SELECT * FROM app_config");
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($configs as $row) {
            $stmtIns = $mysql->prepare("INSERT IGNORE INTO app_config (id, setting_key, setting_value, organizacion_id) VALUES (?, ?, ?, ?)");
            $stmtIns->execute([$row['id'], $row['setting_key'], $row['setting_value'], $row['organizacion_id'] ?? 1]);
        }
        echo " - Imported " . count($configs) . " configs.\n";
    }
    catch (Exception $e) {
        echo " - No app_config in SQLite (Skipping)\n";
    }

    // --- 5. SMS Templates ---
    echo "Creating 'sms_templates'...\n";
    $mysql->exec("CREATE TABLE IF NOT EXISTS sms_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        label VARCHAR(150),
        content TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Check if SQLite has it
    try {
        $stmt = $sqlite->query("SELECT * FROM sms_templates");
        $tpls = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tpls as $row) {
            $stmtIns = $mysql->prepare("INSERT IGNORE INTO sms_templates (id, name, label, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$row['id'], $row['name'], $row['label'], $row['content'], $row['created_at'], $row['updated_at']]);
        }
        echo " - Imported " . count($tpls) . " templates.\n";
    }
    catch (Exception $e) {
        echo " - No sms_templates in SQLite (Skipping)\n";
    }

    echo "\nMigration Complete!\n";

}
catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage());
}
?>