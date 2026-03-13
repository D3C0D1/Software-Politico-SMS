<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'];
$user_role = $_SESSION['role'] ?? 'votante';
$type = $_GET['type'] ?? 'votante'; // 'votante' or 'lider'

// Lógica de seguridad
if ($type === 'lider' && $user_role === 'lider') {
    die("No tienes permiso.");
}

try {
    $sql = "";
    $params = [];

    if ($type === 'lider') {
        // Delete ALL Lideres of the Organization
        $sql = "DELETE FROM registros WHERE tipo = 'lider' AND organizacion_id = ?";
        $params = [$org_id];
    }
    elseif ($type === 'todos') {
        // Delete ALL records (Lideres AND Votantes) for the Organization
        // Only Admin should do this
        if ($user_role === 'superadmin' || $user_role === 'admin') {
            $sql = "DELETE FROM registros WHERE organizacion_id = ?";
            $params = [$org_id];
        }
        else {
            die("No tienes permiso.");
        }
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

    // Redirigir de vuelta con mensaje
    $redirectUrl = ($type === 'lider') ? 'lideres.php' : 'registros.php';
    header("Location: $redirectUrl?msg=eliminado_todo&count=$count");

}
catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}