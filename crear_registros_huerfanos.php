<?php
require_once 'config.php';

echo "=== CREACIÓN DE REGISTROS PARA USUARIOS HUÉRFANOS ===\n\n";

// Usuarios huérfanos identificados
$usuarios_huerfanos = [6, 7, 14];

foreach ($usuarios_huerfanos as $user_id) {
    // Obtener información del usuario
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();

    if ($usuario) {
        echo "Usuario ID: {$usuario['id']}\n";
        echo "  Username: {$usuario['username']}\n";
        echo "  Nombre: {$usuario['name']}\n";
        echo "  Organización: {$usuario['organizacion_id']}\n";

        // Verificar si existe un registro
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE cedula = ? AND tipo = 'lider'");
        $stmt->execute([$usuario['username']]);
        $registro = $stmt->fetch();

        if ($registro) {
            echo "  ✓ Ya tiene registro\n\n";
        }
        else {
            // Crear el registro
            $stmt = $pdo->prepare("INSERT INTO registros 
                (user_id, organizacion_id, tipo, nombres_apellidos, cedula, created_at) 
                VALUES (?, ?, 'lider', ?, ?, NOW())");

            $stmt->execute([
                $usuario['id'],
                $usuario['organizacion_id'],
                $usuario['name'],
                $usuario['username']
            ]);

            echo "  ✓ Registro creado exitosamente\n\n";
        }
    }
}

echo "\n=== VERIFICACIÓN FINAL COMPLETA ===\n\n";

// Verificar todos los líderes
$stmt = $pdo->query("
    SELECT u.id, u.username, u.name, u.organizacion_id, r.id as registro_id, r.cedula, r.nombres_apellidos
    FROM users u
    LEFT JOIN registros r ON u.username = r.cedula AND r.tipo = 'lider'
    WHERE u.role = 'lider'
    ORDER BY u.id
");
$todos_lideres = $stmt->fetchAll();

echo "Total de usuarios con rol 'lider': " . count($todos_lideres) . "\n\n";

$completos = 0;
$incompletos = 0;

foreach ($todos_lideres as $lider) {
    if ($lider['registro_id']) {
        // Verificar contraseña
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$lider['id']]);
        $password_hash = $stmt->fetchColumn();
        $password_ok = password_verify($lider['username'], $password_hash);

        if ($password_ok) {
            echo "✓ COMPLETO | ID: {$lider['id']} | Cédula: {$lider['username']} | Nombre: {$lider['name']} | Org: {$lider['organizacion_id']}\n";
            $completos++;
        }
        else {
            echo "⚠ PASSWORD INCORRECTO | ID: {$lider['id']} | Cédula: {$lider['username']} | Nombre: {$lider['name']}\n";
            $incompletos++;
        }
    }
    else {
        echo "✗ SIN REGISTRO | ID: {$lider['id']} | Username: {$lider['username']} | Nombre: {$lider['name']}\n";
        $incompletos++;
    }
}

echo "\n=== RESUMEN FINAL ===\n";
echo "✓ Líderes con credenciales correctas: $completos\n";
echo "✗ Líderes con problemas: $incompletos\n";
echo "\n";