<?php
require_once 'config.php';

echo "=== DIAGNÓSTICO PARA ADMIN_CHOLISMO ===\n";

// 1. Buscar el usuario admin_cholismo
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin_cholismo'");
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "Usuario 'admin_cholismo' NO ENCONTRADO en la tabla users.\n";

   die();
}

$userId = $user['id'];
$orgId = $user['organizacion_id'] ?? 4; // Default to 4 if not found? But previous run showed 4.
echo "Usuario encontrado: ID: $userId, Username: {$user['username']}, Organizacion ID: $orgId\n";

// 2. Estructura de registros
echo "\n--- Estructura de 'registros' ---\n";
// (Skipping describe for brevity)

// 3. Líderes de la organización 4
echo "\n--- Líderes para Org ID $orgId ---\n";
$stmt = $pdo->prepare("SELECT id, nombres_apellidos, cedula, celular, tipo FROM registros WHERE organizacion_id = ? AND tipo = 'lider'");
$stmt->execute([$orgId]);
$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Total Líderes encontrados: " . count($leaders) . "\n";
foreach ($leaders as $l) {
    echo " -> {$l['nombres_apellidos']} (ID: {$l['id']}, Cedula: {$l['cedula']}, Celular: {$l['celular']})\n";
}

// 4. Buscar si admin_cholismo tiene un registro en la tabla registros
echo "\n--- Registros asociados al UID $userId (admin_cholismo) ---\n";
$stmt = $pdo->prepare("SELECT * FROM registros WHERE user_id = ?");
$stmt->execute([$userId]);
$adminRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($adminRecords) == 0) {
    echo "El usuario admin_cholismo NO tiene ningún registro asociado en la tabla 'registros'.\n";
}
foreach ($adminRecords as $ar) {
    echo "ID: {$ar['id']} - Name: {$ar['nombres_apellidos']} - Tipo: {$ar['tipo']} - OrgID: {$ar['organizacion_id']}\n";
}>
