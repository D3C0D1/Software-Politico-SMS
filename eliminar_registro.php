<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $role = $_SESSION['role'] ?? '';

    try {
        // Fetch details AND verify ownership by organization
        $stmtDetails = $pdo->prepare("SELECT nombres_apellidos, cedula, organizacion_id, user_id FROM registros WHERE id = ?");
        $stmtDetails->execute([$id]);
        $details = $stmtDetails->fetch(PDO::FETCH_ASSOC);

        if (!$details) {
            header("Location: registros.php?error=Registro+no+encontrado");
            exit;
        }

        // Security: superadmin can delete any, admin/lider only their own org
        if ($role !== 'superadmin' && $details['organizacion_id'] != $org_id) {
            header("Location: registros.php?error=Sin+permisos+para+eliminar+este+registro");
            exit;
        }

        // Lider can only delete their own records
        if ($role === 'lider' && $details['user_id'] != $_SESSION['user_id']) {
            header("Location: registros.php?error=Sin+permisos+para+eliminar+este+registro");
            exit;
        }

        $voterName = $details['nombres_apellidos'];
        $cedula = $details['cedula'];

        // Obtener el tipo del registro para saber si es líder
        $stmtTipo = $pdo->prepare("SELECT tipo FROM registros WHERE id = ?");
        $stmtTipo->execute([$id]);
        $tipo = $stmtTipo->fetchColumn();

        $stmt = $pdo->prepare("DELETE FROM registros WHERE id = ? AND organizacion_id = ?");
        $stmt->execute([$id, $org_id]);

        // Si era un líder, eliminar también el usuario del sistema (users.username = cedula)
        if ($tipo === 'lider' && !empty($cedula)) {
            $stmtDelUser = $pdo->prepare("DELETE FROM users WHERE username = ? AND organizacion_id = ?");
            $stmtDelUser->execute([$cedula, $org_id]);
        }

        // Log Deletion
        logSystemAction($pdo, $_SESSION['user_id'], $org_id, 'delete_voter', "Eliminó " . ($tipo === 'lider' ? 'líder' : 'votante') . ": $voterName");
        // Check return URL
        $return = isset($_GET['return']) ? $_GET['return'] : '';
        $redirect = 'registros.php';

        if ($return === 'lideres') {
            $redirect = 'lideres.php';
        }
        elseif ($return === 'todos') {
            $redirect = 'todos_registros.php';
        }
        elseif ($return === 'estadisticas') {
            $redirect = 'estadisticas.php';
        }
        elseif ($return === 'tus') {
            $redirect = 'tus_votantes.php';
        }

        header("Location: " . $redirect . "?msg=eliminado");
        exit;
    }
    catch (PDOException $e) {
        die("Error al eliminar: " . $e->getMessage());
    }
}
else {
    header("Location: registros.php");
    exit;
}
?>