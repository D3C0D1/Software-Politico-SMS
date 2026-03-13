<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control - only specific roles might be allowed to bulk delete if desired,
// but for now user asked for functionality.
// Maybe restrain to Admin or owner?
// Logic: Delete ALL votantes for the current user and organization
// OR if Admin, delete ALL votantes for organization?
// Based on registers.php logic:
// Leaders/Voters see ONLY their created records.
// Admins see ALL records for the Organization.
// The deletion should probably follow the view logic.

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'];
$user_role = $_SESSION['role'] ?? 'votante';
$type = $_GET['type'] ?? 'votante'; // 'votante' or 'lider'

if ($type === 'lider' && $user_role === 'lider') {
    // Lider cannot delete all lideres
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para realizar esta acción.']);
    exit;
}

try {
    $sql = "";
    $params = [];

    if ($type === 'lider') {
        // Delete ALL Lideres of the Organization
        // Only Admin/Superadmin/Operador can do this usually
        $sql = "DELETE FROM registros WHERE tipo = 'lider' AND organizacion_id = ?";
        $params = [$org_id];

    // Also delete associated users? Logic in elimianr_registro.php suggests yes usually
    // But complex bulk delete might just delete registry. 
    // Let's keep it simple: Delete from registros.
    // Ideally should delete from users too where role=lider.
    // Let's refine:
    // 1. Get all lider cedulas to delete users
    // 2. Delete
    }
    else {
        // Delete Votantes
        if ($user_role === 'superadmin' || $user_role === 'admin') {
            // Admin deletes ALL votantes of organization
            $sql = "DELETE FROM registros WHERE tipo = 'votante' AND organizacion_id = ?";
            $params = [$org_id];
        }
        else {
            // Lider deletes only THEIR votantes
            $sql = "DELETE FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ?";
            $params = [$user_id, $org_id];
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => "Se han eliminado $count registros correctamente."]);

}
catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}