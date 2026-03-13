<?php
// setup_mysql.php
// Setup MySQL database and seed dummy data

$credsFile = __DIR__ . '/mysql_creds.json';
if (file_exists($credsFile)) {
    $creds = json_decode(file_get_contents($credsFile), true);
    $host = $creds['host']; // 127.0.0.1
    $user = $creds['user'];
    $pass = $creds['pass'];
    $dbName = $creds['db']; // politica
}
else {
    // Fallback or Error
    die("Error: mysql_creds.json not found.");
}

try {
    // 1. Connect to MySQL Server (no DB selected yet)
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected to MySQL Server.\n";

    // 2. Create Database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '$dbName' created or exists.\n";

    // 3. Select Database
    $pdo->exec("USE `$dbName`");

    // 4. Run Schema
    $schemaFile = __DIR__ . '/db_schema_mysql.sql';
    if (!file_exists($schemaFile)) {
        die("Error: db_schema_mysql.sql not found.");
    }
    $sql = file_get_contents($schemaFile);

    // Split by semicolon to execute mostly safely (basic splitter)
    try {
        $pdo->exec($sql);
        echo "Schema executed successfully.\n";
    }
    catch (PDOException $e) {
        // Fallback: Split by ';'
        echo "Bulk execution failed, trying statement by statement...\n";
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
        echo "Schema executed statement by statement.\n";
    }

    // 5. Insert Dummy Data
    echo "Inserting Dummy Data...\n";

    // Organizations
    $orgs = ['Partido Acción', 'Movimiento Futuro', 'Coalición Esperanza'];
    $orgIds = [];

    // Clear Organizations (ID 1 is usually reserved/default)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE organizaciones");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("TRUNCATE TABLE registros");
    $pdo->exec("TRUNCATE TABLE app_config");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Insert Orgs
    $stmtOrg = $pdo->prepare("INSERT INTO organizaciones (nombre_organizacion) VALUES (?)");
    foreach ($orgs as $orgName) {
        $stmtOrg->execute([$orgName]);
        $orgIds[] = $pdo->lastInsertId();
    }
    echo "Created " . count($orgIds) . " organizations.\n";

    // Create Superadmin (dios)
    $passDios = password_hash('dios123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES ('dios', ?, 'Super Admin', 'superadmin', NULL)")
        ->execute([$passDios]);
    echo "Created Superadmin (dios).\n";

    // Common names for generation
    $firstNames = ['Juan', 'Maria', 'Carlos', 'Ana', 'Pedro', 'Laura', 'Luis', 'Sofia', 'Jorge', 'Elena'];
    $lastNames = ['Perez', 'Gomez', 'Rodriguez', 'Lopez', 'Martinez', 'Garcia', 'Fernandez', 'Gonzalez', 'Diaz', 'Torres'];

    function getRandomName($f, $l)
    {
        return $f[array_rand($f)] . ' ' . $l[array_rand($l)];
    }

    // Process per Organization
    foreach ($orgIds as $index => $orgId) {
        $orgName = $orgs[$index];

        // 1. Create Admin for this Org
        $adminUser = "admin_" . strtolower(explode(' ', $orgName)[1]); // admin_accion
        $adminPass = "admin123";
        $adminHash = password_hash($adminPass, PASSWORD_DEFAULT);

        $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, 'admin', ?)")
            ->execute([$adminUser, $adminHash, "Admin " . $orgName, $orgId]);

        echo "Created Admin for Org $orgId: $adminUser / $adminPass\n";

        // 2. Create Leaders (2 per org)
        for ($i = 1; $i <= 2; $i++) {
            $leaderName = getRandomName($firstNames, $lastNames);
            $leaderCedula = (100000000 + $orgId * 1000 + $i); // Fake Cedula

            // Password = 12345678 (as requested)
            $leaderPassRaw = '12345678';
            $leaderHash = password_hash($leaderPassRaw, PASSWORD_DEFAULT);

            // Create User Account for Leader
            $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, 'lider', ?)")
                ->execute([$leaderCedula, $leaderHash, $leaderName, $orgId]);

            $leaderUserId = $pdo->lastInsertId();

            // Create Registry Entry for Leader (registros table, type='lider')
            // Leader needs to be OWNED by the Admin? Or self?
            // Usually leaders are created by admins. Let's assign user_id to the Org Admin we just created?
            // Or just leave user_id as NULL or the admin's ID.
            // Let's get the admin ID.
            $stmtAdminId = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtAdminId->execute([$adminUser]);
            $adminId = $stmtAdminId->fetchColumn();

            $pdo->prepare("INSERT INTO registros (user_id, organizacion_id, tipo, nombres_apellidos, cedula, lugar_votacion, mesa, celular, sms_inscripcion) VALUES (?, ?, 'lider', ?, ?, 'Puesto Central', '1', '3000000000', 0)")
                ->execute([$adminId, $orgId, $leaderName, $leaderCedula]);

            echo "  Created Leader: $leaderName (User/Cedula: $leaderCedula, Pass: $leaderPassRaw)\n";

            // 3. Create Voters for this Leader (5 voters)
            for ($v = 1; $v <= 5; $v++) {
                $voterName = getRandomName($firstNames, $lastNames);
                $voterCedula = (200000000 + $orgId * 10000 + $leaderUserId * 100 + $v);

                $mesa = rand(1, 20);
                $lugar = "Escuela " . chr(rand(65, 90)); // Random school name

                $pdo->prepare("INSERT INTO registros (user_id, organizacion_id, tipo, nombres_apellidos, cedula, lugar_votacion, mesa, celular, sms_inscripcion) VALUES (?, ?, 'votante', ?, ?, ?, ?, '3100000000', 0)")
                    ->execute([$leaderUserId, $orgId, $voterName, $voterCedula, $lugar, $mesa]);
            }
        }

        // Insert Default App Config for this Org
        $pdo->prepare("INSERT INTO app_config (setting_key, setting_value, organizacion_id) VALUES ('app_title', ?, ?)")
            ->execute(["Partido " . $orgName, $orgId]);
        $pdo->prepare("INSERT INTO app_config (setting_key, setting_value, organizacion_id) VALUES ('primary_color', '#E30613', ?)")
            ->execute([$orgId]);

        // Insert Default SMS Templates for this Org
        $templates = [
            [
                'name' => 'inscripcion',
                'label' => 'SMS de Inscripción',
                'content' => 'Hola {NOMBRE}! Gracias por registrarte en nuestra campaña. Contamos con tu apoyo.'
            ],
            [
                'name' => 'citacion',
                'label' => 'SMS de Citación',
                'content' => 'Hola {NOMBRE}, recordatorio: Te esperamos el día de las elecciones en {LUGAR_VOTACION}, Mesa {MESA}.'
            ],
            [
                'name' => 'confirmacion',
                'label' => 'SMS de Confirmación',
                'content' => 'Gracias por ejercer tu derecho al voto! Confirma tu asistencia aquí: {LINK_CONFIRMACION}'
            ]
        ];

        $stmtTpl = $pdo->prepare("INSERT INTO sms_templates (name, label, content, organizacion_id) VALUES (?, ?, ?, ?)");
        foreach ($templates as $tpl) {
            $stmtTpl->execute([$tpl['name'], $tpl['label'], $tpl['content'], $orgId]);
        }
    }

    echo "\nSetup Complete!\n";

}
catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
?>