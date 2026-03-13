<?php
require_once 'config.php';

echo "=== ACTUALIZACIÓN DE CONTRASEÑAS PENDIENTES ===\n\n";

// IDs de usuarios con contraseñas incorrectas
$usuarios_pendientes = [6, 7, 14];

foreach ($usuarios_pendientes as $user_id) {
    // Obtener información del usuario
    $stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        echo "Actualizando Usuario ID: {$usuario['id']}\n";
        echo "  Username/Cédula: {$usuario['username']}\n";
        echo "  Nombre: {$usuario['name']}\n";

        // Generar el hash de la contraseña (igual a la cédula)
        $password_hash = password_hash($usuario['username'], PASSWORD_DEFAULT);

        // Actualizar la contraseña
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$password_hash, $usuario['id']]);

        echo "  ✓ Contraseña actualizada correctamente\n\n";
    }
}

echo "=== VERIFICACIÓN FINAL ===\n\n";

// Verificar todos los líderes
$stmt = $pdo->query("
    SELECT u.id, u.username, u.name, u.password, u.organizacion_id
    FROM users u
    WHERE u.role = 'lider'
    ORDER BY u.id
");
$todos_lideres = $stmt->fetchAll();

$total = count($todos_lideres);
$correctos = 0;
$incorrectos = 0;

echo "Verificando " . $total . " líderes:\n\n";

foreach ($todos_lideres as $lider) {
    // Verificar que la contraseña coincide con el username (cédula)
    $password_ok = password_verify($lider['username'], $lider['password']);

    if ($password_ok) {
        echo "✓ ID: {$lider['id']} | Cédula: {$lider['username']} | Nombre: {$lider['name']}\n";
        $correctos++;
    }
    else {
        echo "✗ ID: {$lider['id']} | Cédula: {$lider['username']} | Nombre: {$lider['name']} | PASSWORD INCORRECTO\n";
        $incorrectos++;
    }
}

echo "\n=== RESUMEN FINAL ===\n";
echo "Total de líderes: $total\n";
echo "✓ Con credenciales correctas (usuario = cédula, password = cédula): $correctos\n";
echo "✗ Con problemas: $incorrectos\n";

if ($incorrectos == 0) {
    echo "\n🎉 ¡TODOS LOS LÍDERES TIENEN CREDENCIALES CORRECTAS!\n";
}
else {
    echo "\n⚠ Aún hay líderes con problemas. Revise manualmente.\n";
}