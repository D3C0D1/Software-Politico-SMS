<?php
require_once 'config.php';

header('Content-Type: application/json');

// Solo admins autenticados
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$org_id = $_SESSION['organizacion_id'] ?? 1;

try {
    // 1. Encontrar cédulas duplicadas en esta organización
    $stmtDupes = $pdo->prepare("
        SELECT cedula, MIN(id) as keep_id, COUNT(*) as total
        FROM registros
        WHERE tipo = 'votante' AND organizacion_id = ?
        GROUP BY cedula
        HAVING COUNT(*) > 1
    ");
    $stmtDupes->execute([$org_id]);
    $dupes = $stmtDupes->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dupes)) {
        echo json_encode(['status' => 'ok', 'eliminados' => 0, 'message' => 'No hay duplicados para eliminar.']);
        exit;
    }

    $totalEliminados = 0;

    foreach ($dupes as $dupe) {
        // Eliminar todos EXCEPTO el más antiguo (menor ID)
        $stmtDel = $pdo->prepare("
            DELETE FROM registros
            WHERE cedula = ? AND tipo = 'votante' AND organizacion_id = ? AND id != ?
        ");
        $stmtDel->execute([$dupe['cedula'], $org_id, $dupe['keep_id']]);
        $totalEliminados += $stmtDel->rowCount();
    }

    echo json_encode([
        'status' => 'ok',
        'eliminados' => $totalEliminados,
        'message' => "Se eliminaron $totalEliminados registros duplicados correctamente. Se conservó el registro más antiguo de cada cédula."
    ]);

}
catch (Exception $e) {
    echo json_encode(['error' => 'Error al eliminar duplicados: ' . $e->getMessage()]);
}
?>