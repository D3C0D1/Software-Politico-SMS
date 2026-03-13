<?php
require_once 'config.php';

$orgId = 4;

echo "--- USERS with role 'lider' (Count: 10) ---\n";
$stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE role = 'lider' AND organizacion_id = ?");
$stmt->execute([$orgId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "User ID: {$u['id']} - {$u['name']} ({$u['username']})\n";
}

echo "\n--- REGISTROS with tipo 'lider' (Count: 5) ---\n";
$stmt = $pdo->prepare("SELECT id, nombres_apellidos, cedula FROM registros WHERE tipo = 'lider' AND organizacion_id = ?");
$stmt->execute([$orgId]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($registros as $r) {
    echo "Registro ID: {$r['id']} - {$r['nombres_apellidos']} ({$r['cedula']})\n";
}