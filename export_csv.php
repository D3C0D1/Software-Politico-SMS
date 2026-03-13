<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    exit('Acceso denegado');
}

$view = $_GET['view'] ?? 'registros';
$filename = "exportacion_" . $view . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Headers matching the DB columns conceptually
// Nombres, Cedula, Lugar, Mesa, Celular, Estado, (Tipo), (Lider Responsable if Admin)
$headers = ['Nombres y Apellidos', 'Cedula', 'Lugar Votacion', 'Mesa', 'Celular', 'Estado'];

if ($view === 'todos') {
    $headers[] = 'Tipo';
    $headers[] = 'Lider Responsable';
}

fputcsv($output, $headers);

// Build Query
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$org_id = $_SESSION['organizacion_id'] ?? 1;

if ($view === 'todos') {
    // Only Admin/Operador can export all - but ONLY their own organization
    if ($role === 'lider') {
        exit('Acceso denegado');
    }
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as leader_username, u.name as leader_name 
        FROM registros r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.organizacion_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$org_id]);
}
elseif ($view === 'lideres') {
    if ($role === 'lider') {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'lider' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $org_id]);
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'lider' AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$org_id]);
    }
}
else { // registros (votantes)
    if ($role === 'lider') {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $org_id]);
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $org_id]);
    }
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {
    $data = [
        $row['nombres_apellidos'],
        $row['cedula'],
        $row['lugar_votacion'],
        $row['mesa'],
        $row['celular'],
        ($row['estado_voto'] === 'voto' ? 'Voto' : 'Pendiente')
    ];

    if ($view === 'todos') {
        $data[] = ucfirst($row['tipo']);
        $data[] = $row['leader_name'] ? $row['leader_name'] : ($row['leader_username'] ? $row['leader_username'] : 'Directo');
    }

    fputcsv($output, $data);
}

fclose($output);
exit;
?>