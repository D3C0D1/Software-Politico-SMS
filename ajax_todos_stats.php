<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Optimized single query for all stats - filtered by organization
    $organizacion_id = $_SESSION['organizacion_id'] ?? 1;
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN tipo = 'lider' THEN 1 ELSE 0 END) as lideres,
        SUM(CASE WHEN tipo != 'lider' THEN 1 ELSE 0 END) as votantes,
        SUM(CASE WHEN estado_voto = 'voto' THEN 1 ELSE 0 END) as han_votado,
        SUM(CASE WHEN estado_voto != 'voto' OR estado_voto IS NULL THEN 1 ELSE 0 END) as pendientes
    FROM registros
    WHERE organizacion_id = ?");
    $stmt->execute([$organizacion_id]);

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure numbers, not nulls
    $response = [
        'total' => (int)$stats['total'],
        'lideres' => (int)$stats['lideres'],
        'votantes' => (int)$stats['votantes'],
        'han_votado' => (int)$stats['han_votado'],
        'pendientes' => (int)$stats['pendientes']
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
}
catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>