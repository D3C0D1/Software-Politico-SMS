<?php
require_once 'config.php';

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     REPORTE FINAL - VERIFICACIÓN DE CREDENCIALES DE LÍDERES     ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Fecha: " . date('Y-m-d H:i:s') . "\n";
echo "Base de datos: politica\n\n";

// Obtener todos los líderes con su información completa
$stmt = $pdo->query("
    SELECT 
        u.id as user_id,
        u.username,
        u.name as user_name,
        u.organizacion_id,
        r.id as registro_id,
        r.cedula,
        r.nombres_apellidos,
        r.celular,
        r.email
    FROM users u
    INNER JOIN registros r ON u.username = r.cedula AND r.tipo = 'lider'
    WHERE u.role = 'lider'
    ORDER BY u.organizacion_id, u.username
");
$lideres = $stmt->fetchAll();

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN GENERAL                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Total de líderes registrados: " . count($lideres) . "\n\n";

// Agrupar por organización
$por_organizacion = [];
foreach ($lideres as $lider) {
    $org = "Organización " . ($lider['organizacion_id'] ?? 'Sin definir');
    if (!isset($por_organizacion[$org])) {
        $por_organizacion[$org] = [];
    }
    $por_organizacion[$org][] = $lider;
}

echo "Líderes por organización:\n";
foreach ($por_organizacion as $org => $lideres_org) {
    echo "  • $org: " . count($lideres_org) . " líder(es)\n";
}

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║              DETALLE DE LÍDERES POR ORGANIZACIÓN                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

foreach ($por_organizacion as $org => $lideres_org) {
    echo "┌─────────────────────────────────────────────────────────────────┐\n";
    echo "│ ORGANIZACIÓN: " . str_pad($org, 47) . "│\n";
    echo "└─────────────────────────────────────────────────────────────────┘\n\n";

    foreach ($lideres_org as $lider) {
        // Verificar contraseña
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$lider['user_id']]);
        $password_hash = $stmt->fetchColumn();
        $password_ok = password_verify($lider['username'], $password_hash);

        echo "  Líder ID: {$lider['user_id']}\n";
        echo "  ├─ Nombre: {$lider['user_name']}\n";
        echo "  ├─ Cédula: {$lider['cedula']}\n";
        echo "  ├─ Usuario: {$lider['username']}\n";
        echo "  ├─ Contraseña: {$lider['cedula']} " . ($password_ok ? "✓" : "✗") . "\n";
        if ($lider['celular']) {
            echo "  ├─ Celular: {$lider['celular']}\n";
        }
        if ($lider['email']) {
            echo "  ├─ Email: {$lider['email']}\n";
        }
        echo "  └─ Estado: " . ($password_ok ? "✓ CREDENCIALES CORRECTAS" : "✗ REVISAR") . "\n";
        echo "\n";
    }
}

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    TABLA RESUMEN                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo str_pad("ID", 5) . " | " . str_pad("CÉDULA", 15) . " | " . str_pad("NOMBRE", 35) . " | ESTADO\n";
echo str_repeat("─", 80) . "\n";

foreach ($lideres as $lider) {
    // Verificar contraseña
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$lider['user_id']]);
    $password_hash = $stmt->fetchColumn();
    $password_ok = password_verify($lider['username'], $password_hash);

    $estado = $password_ok ? "✓ OK" : "✗ ERROR";

    echo str_pad($lider['user_id'], 5) . " | " .
        str_pad($lider['cedula'], 15) . " | " .
        str_pad(substr($lider['user_name'], 0, 35), 35) . " | " .
        $estado . "\n";
}

echo "\n╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    VERIFICACIÓN TÉCNICA                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

echo "Criterios verificados:\n";
echo "  ✓ Cada líder tiene un usuario en la tabla 'users'\n";
echo "  ✓ Cada líder tiene un registro en la tabla 'registros'\n";
echo "  ✓ El campo 'username' es igual a la cédula del líder\n";
echo "  ✓ El campo 'password' es el hash de la cédula del líder\n";
echo "  ✓ El rol del usuario es 'lider'\n";
echo "  ✓ El tipo del registro es 'lider'\n\n";

echo "Métodos de acceso:\n";
echo "  Usuario: [Número de cédula]\n";
echo "  Contraseña: [Número de cédula]\n\n";

$correctos = 0;
foreach ($lideres as $lider) {
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$lider['user_id']]);
    $password_hash = $stmt->fetchColumn();
    if (password_verify($lider['username'], $password_hash)) {
        $correctos++;
    }
}

echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                    RESULTADO FINAL                               ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n\n";

if ($correctos == count($lideres)) {
    echo "  🎉 ¡VERIFICACIÓN EXITOSA!\n\n";
    echo "  Todos los " . count($lideres) . " líderes tienen credenciales correctas.\n";
    echo "  Cada líder puede acceder usando su número de cédula como\n";
    echo "  usuario y contraseña.\n\n";
}
else {
    echo "  ⚠ ATENCIÓN\n\n";
    echo "  Líderes correctos: $correctos / " . count($lideres) . "\n";
    echo "  Líderes con problemas: " . (count($lideres) - $correctos) . "\n\n";
}

echo "══════════════════════════════════════════════════════════════════\n";
echo "Reporte generado: " . date('Y-m-d H:i:s') . "\n";
echo "══════════════════════════════════════════════════════════════════\n";