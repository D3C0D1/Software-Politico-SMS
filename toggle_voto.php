<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $field = $_GET['field'] ?? 'estado';

    try {
        // Security: verify the record belongs to the current organization
        $stmtCheck = $pdo->prepare("SELECT organizacion_id, user_id FROM registros WHERE id = ?");
        $stmtCheck->execute([$id]);
        $record = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $return_page = $_GET['v'] ?? 'registros.php';

        if (!$record) {
            header("Location: $return_page?error=Registro+no+encontrado");
            exit;
        }

        if ($record['organizacion_id'] != $org_id) {
            header("Location: $return_page?error=Sin+permisos");
            exit;
        }

        if ($field === 'yavoto') {
            // Toggle ya_voto (0 <-> 1)
            $stmt = $pdo->prepare("SELECT ya_voto FROM registros WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$id, $org_id]);
            $current = $stmt->fetchColumn();
            $newVal = ($current == 1) ? 0 : 1;

            $stmt = $pdo->prepare("UPDATE registros SET ya_voto = ? WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$newVal, $id, $org_id]);

            logSystemAction($pdo, $_SESSION['user_id'], $org_id, 'toggle_yavoto', "Cambió 'Ya Voto' de ID $id a '$newVal'");
        }
        else {
            // Toggle estado_voto: pendiente <-> voto (refleja lo que el votante confirmó)
            $stmt = $pdo->prepare("SELECT estado_voto FROM registros WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$id, $org_id]);
            $current = $stmt->fetchColumn();
            $newVal = ($current === 'voto') ? 'pendiente' : 'voto';

            $stmt = $pdo->prepare("UPDATE registros SET estado_voto = ? WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$newVal, $id, $org_id]);

            logSystemAction($pdo, $_SESSION['user_id'], $org_id, 'toggle_status', "Cambió estado de voto ID $id a '$newVal'");
        }

        header("Location: $return_page?msg=Estado+actualizado");
        exit;

    }
    catch (PDOException $e) {
        die("Error actualizando estado: " . $e->getMessage());
    }
}
else {
    $return_page = $_GET['v'] ?? 'registros.php';
    header("Location: $return_page");
    exit;
}
?>