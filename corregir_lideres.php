<?php
require_once 'config.php';

echo "=== CORRECCIÓN DE CREDENCIALES DE LÍDERES ===\n\n";

$correcciones = 0;

// 1. Actualizar contraseñas para que coincidan con la cédula
echo "--- ACTUALIZANDO CONTRASEÑAS ---\n\n";

$stmt = $pdo->query("SELECT id, username, name FROM users WHERE role = 'lider'");
$usuarios = $stmt->fetchAll();

foreach ($usuarios as $usuario) {
    // Verificar si el registro existe
    $stmt = $pdo->prepare("SELECT * FROM registros WHERE cedula = ? AND tipo = 'lider'");
    $stmt->execute([$usuario['username']]);
    $registro = $stmt->fetch();

    if ($registro) {
        // Actualizar la contraseña para que sea igual a la cédula
        $new_password = password_hash($usuario['username'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $usuario['id']]);

        echo "✓ Usuario ID {$usuario['id']} ({$usuario['username']} - {$usuario['name']}): Contraseña actualizada a su cédula\n";
        $correcciones++;
    }
}

// 2. Crear usuarios faltantes para registros sin usuario
echo "\n--- CREANDO USUARIOS FALTANTES ---\n\n";

$stmt = $pdo->query("SELECT * FROM registros WHERE tipo = 'lider'");
$registros = $stmt->fetchAll();

foreach ($registros as $registro) {
    // Verificar si existe el usuario
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'lider'");
    $stmt->execute([$registro['cedula']]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        // Crear el usuario
        $password = password_hash($registro['cedula'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, 'lider', ?)");
        $stmt->execute([
            $registro['cedula'],
            $password,
            $registro['nombres_apellidos'],
            $registro['organizacion_id']
        ]);

        echo "✓ Usuario creado para: {$registro['cedula']} - {$registro['nombres_apellidos']}\n";
        $correcciones++;
    }
}

// 3. Eliminar usuarios sin registro correspondiente (usuarios huérfanos)
echo "\n--- REVISANDO USUARIOS SIN REGISTRO ---\n\n";

$stmt = $pdo->query("SELECT id, username, name FROM users WHERE role = 'lider'");
$usuarios = $stmt->fetchAll();

foreach ($usuarios as $usuario) {
    $stmt = $pdo->prepare("SELECT * FROM registros WHERE cedula = ? AND tipo = 'lider'");
    $stmt->execute([$usuario['username']]);
    $registro = $stmt->fetch();

    if (!$registro) {
        echo "⚠ Usuario ID {$usuario['id']} ({$usuario['username']} - {$usuario['name']}): No tiene registro correspondiente\n";
        echo "  Opciones: (1) Eliminar usuario, (2) Crear registro, (3) Mantener\n";
        echo "  Por seguridad, este usuario se mantendrá. Revise manualmente.\n\n";
    }
}

echo "\n=== RESUMEN ===\n";
echo "Total de correcciones realizadas: $correcciones\n\n";

echo "--- VERIFICACIÓN FINAL ---\n\n";

// Verificar todas las credenciales nuevamente
$stmt = $pdo->query("SELECT u.id, u.username, u.name, r.cedula, r.nombres_apellidos 
                     FROM users u 
                     INNER JOIN registros r ON u.username = r.cedula 
                     WHERE u.role = 'lider' AND r.tipo = 'lider'
                     ORDER BY u.username");
$lideres = $stmt->fetchAll();

echo "Total de líderes con credenciales correctas: " . count($lideres) . "\n\n";

foreach ($lideres as $lider) {
    // Verificar que la contraseña es correcta
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$lider['id']]);
    $password_hash = $stmt->fetchColumn();

    $password_ok = password_verify($lider['username'], $password_hash);
    $status = $password_ok ? "✓" : "✗";

    echo "$status ID: {$lider['id']} | Username/Cédula: {$lider['username']} | Nombre: {$lider['name']}\n";
}

echo "\n¡Corrección completada!\n";