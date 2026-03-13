<?php
// import_csv_to_mysql.php

$host = '127.0.0.1';
$db = 'politica';
$user = 'root';
$pass = 'mysql';

echo "Importing CSVs to MySQL ($db)...\n";

try {
    $mysql = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. Organizations ---
    echo "Importing 'organizaciones'...\n";
    $mysql->exec("TRUNCATE TABLE organizaciones"); // Clean start
    if (($handle = fopen("organizaciones.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $stmt = $mysql->prepare("INSERT INTO organizaciones (id, nombre_organizacion, created_at, status) VALUES (?, ?, ?, ?)");
            // CSV order: id, nombre_organizacion, created_at, status
            $stmt->execute([$data[0], $data[1], $data[2], $data[3]]);
        }
        fclose($handle);
    }
    echo " - Done.\n";

    // --- 2. Users ---
    echo "Importing 'users'...\n";
    $mysql->exec("TRUNCATE TABLE users");
    if (($handle = fopen("users.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        // CSV: id,username,password,name,role,onurix_client,onurix_key,created_at,organizacion_id
        // NOTE: CSV column order depends on sqlite SELECT * order.
        // SQLite schema: id, username, password, name, role, onurix_client, onurix_key, created_at, organizacion_id
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Handle potential NULL organizacion_id (empty string in CSV)
            $org_id = $data[8] === '' ? NULL : $data[8];

            $stmt = $mysql->prepare("INSERT INTO users (id, username, password, name, role, onurix_client, onurix_key, created_at, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7], $org_id]);
        }
        fclose($handle);
    }
    echo " - Done.\n";

    // --- 3. SMS Templates ---
    echo "Importing 'sms_templates'...\n";
    $mysql->exec("TRUNCATE TABLE sms_templates");
    if (file_exists("sms_templates.csv") && ($handle = fopen("sms_templates.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $stmt = $mysql->prepare("INSERT INTO sms_templates (id, name, label, content, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data[0], $data[1], $data[2], $data[3], $data[4], $data[5]]);
        }
        fclose($handle);
    }
    echo " - Done.\n";

    // --- 4. Registros ---
    echo "Importing 'registros'...\n";
    $mysql->exec("TRUNCATE TABLE registros");
    if (($handle = fopen("registros.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        // SQLite schema: id,user_id,tipo,nombres_apellidos,cedula,lugar_votacion,mesa,celular,municipio,departamento,barrio_vereda,direccion,email,estado_voto,ya_voto,sms_inscripcion,sms_citacion,sms_confirmacion,created_at,updated_at,organizacion_id
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $org_id = isset($data[20]) && $data[20] !== '' ? $data[20] : 1;

            $stmt = $mysql->prepare("INSERT INTO registros (
                id, user_id, tipo, nombres_apellidos, cedula, lugar_votacion, mesa, celular, 
                municipio, departamento, barrio_vereda, direccion, email, 
                estado_voto, ya_voto, sms_inscripcion, sms_citacion, sms_confirmacion, 
                created_at, updated_at, organizacion_id
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $stmt->execute([
                $data[0], $data[1], $data[2], $data[3], $data[4], $data[5], $data[6], $data[7],
                $data[8], $data[9], $data[10], $data[11], $data[12],
                $data[13], $data[14], $data[15], $data[16], $data[17],
                $data[18], $data[19], $org_id
            ]);
        }
        fclose($handle);
    }
    echo " - Done.\n";

    // --- 5. App Config ---
    echo "Importing 'app_config'...\n";
    $mysql->exec("TRUNCATE TABLE app_config");
    if (file_exists("app_config.csv") && ($handle = fopen("app_config.csv", "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $stmt = $mysql->prepare("INSERT INTO app_config (id, setting_key, setting_value, organizacion_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$data[0], $data[1], $data[2], $data[3]]);
        }
        fclose($handle);
    }
    echo " - Done.\n";

}
catch (Exception $e) {
    die("Import Failed: " . $e->getMessage() . "\n");
}
?>