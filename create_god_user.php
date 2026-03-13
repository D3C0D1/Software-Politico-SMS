<?php
require_once 'config.php';

echo "<h2>Creando Usuario Dios (Super Admin)...</h2>";

try {
    $username = 'dios';
    $password = 'dios123'; // Default password, user should change it
    $name = 'Admin Sistema';
    $role = 'superadmin';

    // 1. Check if exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo "El usuario '$username' ya existe. Actualizando rol y contraseña...<br>";
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = ?, role = ?, name = ?, organizacion_id = NULL WHERE username = ?");
        $upd->execute([$hash, $role, $name, $username]);
    }
    else {
        echo "Creando usuario '$username'...<br>";
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // Insert with NULL organization_id for superadmin
        $ins = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, ?, NULL)");
        $ins->execute([$username, $hash, $name, $role]);
    }

    echo "<h3>¡Usuario Dios creado con éxito!</h3>";
    echo "Usuario: <strong>$username</strong><br>";
    echo "Contraseña: <strong>$password</strong><br>";
    echo "<br><a href='index.php'>Ir al Login</a>";

}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>