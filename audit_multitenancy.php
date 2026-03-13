<?php
require_once 'config.php';

// Set headers for plain text output
header('Content-Type: text/plain');

echo "=== AUDITORÍA DE INTEGRIDAD DE DATOS (MULTITENANCY) ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // 1. Check for Voters/Leaders appearing in multiple organizations (by Cedula)
    echo "1. VERIFICANDO DUPLICADOS ENTRE ORGANIZACIONES (CÉDULA)...\n";
    $stmt = $pdo->query("
        SELECT cedula, GROUP_CONCAT(DISTINCT organizacion_id) as orgs, COUNT(DISTINCT organizacion_id) as org_count 
        FROM registros 
        WHERE cedula IS NOT NULL AND cedula != '' 
        GROUP BY cedula 
        HAVING org_count > 1
    ");
    $dupes = $stmt->fetchAll();

    if (count($dupes) > 0) {
        echo "[ALERTA] Se encontraron " . count($dupes) . " personas registradas en MÚLTIPLES organizaciones:\n";
        foreach ($dupes as $d) {
            echo " - Cédula: " . $d['cedula'] . " en Organizaciones: [" . $d['orgs'] . "]\n";
        }
    }
    else {
        echo "[OK] No se encontraron cédulas duplicadas entre organizaciones diferentes.\n";
    }
    echo "\n";

    // 2. Check for Mismatch between Record Organization and Leader Organization
    echo "2. VERIFICANDO CONSISTENCIA ORGANIZACIÓN: REGISTRO vs LÍDER...\n";
    // Check records where the assigned user (leader) belongs to a different organization
    $stmt = $pdo->query("
        SELECT r.id, r.cedula, r.nombres_apellidos, r.organizacion_id as reg_org, 
               u.id as user_id, u.username as leader, u.organizacion_id as leader_org
        FROM registros r
        JOIN users u ON r.user_id = u.id
        WHERE r.organizacion_id != u.organizacion_id AND u.organizacion_id IS NOT NULL AND r.organizacion_id IS NOT NULL
    ");
    $mismatches = $stmt->fetchAll();

    if (count($mismatches) > 0) {
        echo "[ERROR] Se encontraron " . count($mismatches) . " registros asignados a líderes de OTRA organización:\n";
        foreach ($mismatches as $m) {
            echo " - Registro ID " . $m['id'] . " (Cédula " . $m['cedula'] . ") está en Org " . $m['reg_org'] .
                " pero su líder (" . $m['leader'] . ") es de Org " . $m['leader_org'] . "\n";
        }
    }
    else {
        echo "[OK] Todos los registros pertenecen a la misma organización que su líder asignado.\n";
    }
    echo "\n";

    // 3. Check for Leaders assigned to Organization 0 or NULL
    echo "3. VERIFICANDO USUARIOS SIN ORGANIZACIÓN VÁLIDA...\n";
    $stmt = $pdo->query("SELECT id, username, organizacion_id FROM users WHERE (organizacion_id IS NULL OR organizacion_id = 0) AND username != 'admin' AND username != 'dios'");
    $orphans = $stmt->fetchAll();

    if (count($orphans) > 0) {
        echo "[ALERTA] Usuarios sin organización válida:\n";
        foreach ($orphans as $o) {
            echo " - User " . $o['username'] . " (ID " . $o['id'] . ") -> Org: " . var_export($o['organizacion_id'], true) . "\n";
        }
    }
    else {
        echo "[OK] Todos los usuarios (excepto admins globales) tienen organización asignada.\n";
    }
    echo "\n";

    // 4. Check for Registros without Organization
    echo "4. VERIFICANDO REGISTROS SIN ORGANIZACIÓN...\n";
    $stmt = $pdo->query("SELECT count(*) as count FROM registros WHERE organizacion_id IS NULL OR organizacion_id = 0");
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo "[ALERTA] Hay $count registros sin organización asignada (ID=0 o NULL).\n";
    }
    else {
        echo "[OK] Todos los registros tienen organización asignada.\n";
    }
    echo "\n";

    // 5. Check if a User is a 'Lider' in multiple organizations (if username is not unique, which it should be)
    echo "5. VERIFICANDO USERNAME DUPLICADOS EN USERS (Control de Unicidad)...\n";
    $stmt = $pdo->query("SELECT username, count(*) as c FROM users GROUP BY username HAVING c > 1");
    $userDupes = $stmt->fetchAll();
    if (count($userDupes) > 0) {
        echo "[CRITICO] Usernames duplicados en tabla users (Violación de unicidad):\n";
        foreach ($userDupes as $u) {
            echo " - " . $u['username'] . " (x" . $u['c'] . ")\n";
        }
    }
    else {
        echo "[OK] Usernames únicos.\n";
    }
    echo "\n";

    // 6. Check for Voters with Multiple Leaders in Same Organization
    echo "6. VERIFICANDO VOTANTES CON MÚLTIPLES LÍDERES (Misma Org)...\n";
    $stmt = $pdo->query("
        SELECT cedula, organizacion_id, GROUP_CONCAT(DISTINCT user_id) as leaders, COUNT(DISTINCT user_id) as c 
        FROM registros 
        WHERE cedula IS NOT NULL AND cedula != '' 
        GROUP BY cedula, organizacion_id 
        HAVING c > 1
     ");
    $dupLeaders = $stmt->fetchAll();
    if (count($dupLeaders) > 0) {
        echo "[ALERTA] Votantes asignados a múltiples líderes en la misma organización:\n";
        foreach ($dupLeaders as $d) {
            echo " - Cédula " . $d['cedula'] . " (Org " . $d['organizacion_id'] . ") -> Leaders IDs: [" . $d['leaders'] . "]\n";
        }
    }
    else {
        echo "[OK] Cada votante tiene un único líder por organización.\n";
    }
    echo "\n";

    // 7. Check for LEADERS (Users) with same Name in multiple organizations
    echo "7. VERIFICANDO LÍDERES (USERS) DUPLICADOS POR NOMBRE EN DIFERENTES ORGS...\n";
    // Note: Name might not be unique naturally, but checking might reveal issues.
    $stmt = $pdo->query("
        SELECT name, GROUP_CONCAT(DISTINCT organizacion_id) as orgs, COUNT(DISTINCT organizacion_id) as c 
        FROM users 
        WHERE name IS NOT NULL AND name != '' AND name != 'Administrador Principal'
        GROUP BY name 
        HAVING c > 1
     ");
    $dupUserNames = $stmt->fetchAll();
    if (count($dupUserNames) > 0) {
        echo "[ALERTA] Usuarios con el mismo NOMBRE en diferentes organizaciones (Posible violación):\n";
        foreach ($dupUserNames as $u) {
            echo " - " . $u['name'] . " -> Orgs: [" . $u['orgs'] . "]\n";
        }
    }
    else {
        echo "[OK] Nombres de usuarios únicos entre organizaciones.\n";
    }
    echo "\n";

    // 8. Check for LEADERS (Registros) with same Name/Phone in multiple organizations
    echo "8. VERIFICANDO LÍDERES (REGISTROS) DUPLICADOS POR NOMBRE EN DIFERENTES ORGS...\n";
    $stmt = $pdo->query("
        SELECT nombres_apellidos, GROUP_CONCAT(DISTINCT organizacion_id) as orgs, COUNT(DISTINCT organizacion_id) as c 
        FROM registros 
        WHERE tipo='lider' AND nombres_apellidos IS NOT NULL
        GROUP BY nombres_apellidos 
        HAVING c > 1
     ");
    $dupRegNames = $stmt->fetchAll();
    if (count($dupRegNames) > 0) {
        echo "[ALERTA] Líderes (Registros) con mismo NOMBRE en diferentes organizaciones:\n";
        foreach ($dupRegNames as $r) {
            echo " - " . $r['nombres_apellidos'] . " -> Orgs: [" . $r['orgs'] . "]\n";
        }
    }
    else {
        echo "[OK] Nombres de líderes (registros) únicos entre organizaciones.\n";
    }

}
catch (PDOException $e) {
    echo "ERROR DE BASE DE DATOS: " . $e->getMessage();
}
?>