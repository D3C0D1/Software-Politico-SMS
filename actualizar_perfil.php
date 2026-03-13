<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    // Add other fields if needed, e.g. email, phone.

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
            $stmt->execute([$name, $_SESSION['user_id']]);

            // Update Session
            $_SESSION['name'] = $name;

            // Redirect back with success
            header("Location: dashboard.php?msg=Perfil actualizado");
            exit;
        }
        catch (PDOException $e) {
            die("Error al actualizar perfil: " . $e->getMessage());
        }
    }
    else {
        header("Location: dashboard.php?error=El nombre no puede estar vacío");
        exit;
    }
}
?>
