<?php
require_once 'config.php';

$orgId = 4;
$stmt = $pdo->prepare("SELECT id, username, name, created_at FROM users WHERE role = 'lider' AND organizacion_id = ? ORDER BY created_at DESC");
$stmt->execute([$orgId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "ID | Usuario | Nombre | Fecha Creación\n";
echo "---|---|---|---\n";
foreach ($users as $u) {
    echo "{$u['id']} | {$u['username']} | {$u['name']} | {$u['created_at']}\n";
}