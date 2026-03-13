<?php
require_once 'config.php';

echo "=== VERIFICACIÓN DE CREDENCIALES DE LÍDERES ===\n\n";

// Obtener todos los líderes de la tabla registros
$stmt = $pdo->query("SELECT id, cedula, nombres_apellidos, user_id FROM registros WHERE tipo = 'lider' ORDER BY cedula");
$lideres_registros = $stmt->fetchAll();

echo "Total de líderes en registros: " . count($lideres_registros) . "\n\n";

// Obtener todos los usuarios con rol lider
$stmt = $pdo->query("SELECT id, username, password, name FROM users WHERE role = 'lider' ORDER BY username");
$usuarios_lideres = $stmt->fetchAll();

echo "Total de usuarios con rol 'lider': " . count($usuarios_lideres) . "\n\n";

echo "--- VERIFICACIÓN DE USUARIOS LÍDERES ---\n\n";

$problemas = [];

foreach ($usuarios_lideres as $usuario) {
    echo "Usuario ID: {$usuario['id']}\n";
    echo "  Username: {$usuario['username']}\n";
    echo "  Nombre: {$usuario['name']}\n";

    // Buscar el registro correspondiente por username = cedula
    $stmt = $pdo->prepare("SELECT * FROM registros WHERE cedula = ? AND tipo = 'lider'");
    $stmt->execute([$usuario['username']]);
    $registro = $stmt->fetch();

    if ($registro) {
        echo "  ✓ Registro encontrado: {$registro['nombres_apellidos']}\n";

        // Verificar si la contraseña es igual a la cédula
        if (password_verify($usuario['username'], $usuario['password'])) {
            echo "  ✓ Password coincide con cédula\n";
        }
        else {
            echo "  ✗ Password NO coincide con cédula\n";
            $problemas[] = [
                'user_id' => $usuario['id'],
                'username' => $usuario['username'],
                'cedula' => $usuario['username'],
                'tipo' => 'password_incorrecto'
            ];
        }
    }
    else {
        echo "  ✗ No se encontró registro con cédula: {$usuario['username']}\n";
        $problemas[] = [
            'user_id' => $usuario['id'],
            'username' => $usuario['username'],
            'tipo' => 'sin_registro'
        ];
    }
    echo "\n";
}

echo "\n--- VERIFICACIÓN DE REGISTROS DE LÍDERES ---\n\n";

foreach ($lideres_registros as $registro) {
    echo "Registro ID: {$registro['id']}\n";
    echo "  Cédula: {$registro['cedula']}\n";
    echo "  Nombre: {$registro['nombres_apellidos']}\n";

    // Buscar el usuario correspondiente
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'lider'");
    $stmt->execute([$registro['cedula']]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        echo "  ✓ Usuario encontrado (ID: {$usuario['id']})\n";
    }
    else {
        echo "  ✗ No existe usuario con username: {$registro['cedula']}\n";
        $problemas[] = [
            'registro_id' => $registro['id'],
            'cedula' => $registro['cedula'],
            'nombre' => $registro['nombres_apellidos'],
            'tipo' => 'sin_usuario'
        ];
    }
    echo "\n";
}

echo "\n=== RESUMEN DE PROBLEMAS ===\n\n";

if (empty($problemas)) {
    echo "✓ No se encontraron problemas. Todos los líderes tienen credenciales correctas.\n";
}
else {
    echo "Se encontraron " . count($problemas) . " problemas:\n\n";

    $sin_registro = array_filter($problemas, fn($p) => $p['tipo'] === 'sin_registro');
    $sin_usuario = array_filter($problemas, fn($p) => $p['tipo'] === 'sin_usuario');
    $password_incorrecto = array_filter($problemas, fn($p) => $p['tipo'] === 'password_incorrecto');

    if (!empty($sin_registro)) {
        echo "Usuarios sin registro correspondiente (" . count($sin_registro) . "):\n";
        foreach ($sin_registro as $p) {
            echo "  - Username: {$p['username']} (User ID: {$p['user_id']})\n";
        }
        echo "\n";
    }

    if (!empty($sin_usuario)) {
        echo "Registros sin usuario correspondiente (" . count($sin_usuario) . "):\n";
        foreach ($sin_usuario as $p) {
            echo "  - Cédula: {$p['cedula']} - {$p['nombre']} (Registro ID: {$p['registro_id']})\n";
        }
        echo "\n";
    }

    if (!empty($password_incorrecto)) {
        echo "Usuarios con contraseña incorrecta (" . count($password_incorrecto) . "):\n";
        foreach ($password_incorrecto as $p) {
            echo "  - Username: {$p['username']} (User ID: {$p['user_id']})\n";
        }
        echo "\n";
    }
}

echo "\n¿Desea corregir estos problemas? (y/n): ";