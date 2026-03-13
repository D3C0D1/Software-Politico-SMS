<?php
// seed_data.php - Rellenar base de datos con datos de prueba
require_once 'config.php';

// Solo permitir si el usuario es Admin o estamos en entorno local para evitar mal uso público
if (!isset($_SESSION['user_id']) && !$isLocal) {
    die("<h1>Acceso Denegado</h1><p>Por seguridad, inicia sesión como administrador antes de ejecutar este script en producción.</p><a href='index.php'>Iniciar Sesión</a>");
}

echo "<div style='font-family: sans-serif; padding: 20px;'>";
echo "<h2>🌱 Iniciando Generación de Datos de Prueba...</h2>";
echo "<ul style='background: #f4f4f4; padding: 20px; border-radius: 8px;'>";

// 1. Crear Líderes de Prueba
$lideres = [
    ['Carlos Perez', '1001', '3001234567'],
    ['Maria Rodriguez', '1002', '3109876543'],
    ['Juan Gomez', '1003', '3205556677'],
    ['Ana Martinez', '1004', '3151112233'],
    ['Luis Diaz', '1005', '3019998877']
];

$liderIds = [];
// Añadir al admin (id 1) como posible líder también si existe
if (isset($_SESSION['user_id'])) {
    $liderIds[] = $_SESSION['user_id'];
}
else {
    $liderIds[] = 1; // Fallback
}

foreach ($lideres as $l) {
    $nombre = $l[0];
    $cedula = $l[1];
    $celular = $l[2];

    // Verificar si existe usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$cedula]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        // Crear Usuario Líder
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'lider')");
        $stmtInsert->execute([$cedula, $pass, $nombre]);
        $userId = $pdo->lastInsertId();
        echo "<li style='color: green;'>✅ Creado Usuario Líder: <strong>$nombre</strong> (User: $cedula, Pass: admin123)</li>";

        // Crear Registro Líder asociado
        // Asignamos el registro al Admin (1) para que aparezca en la lista de líderes global
        $stmtReg = $pdo->prepare("INSERT INTO registros (user_id, tipo, nombres_apellidos, cedula, celular, lugar_votacion, mesa) VALUES (?, 'lider', ?, ?, ?, 'Sede Principal', '1')");
        $stmtReg->execute([1, $nombre, $cedula, $celular]);
    }
    else {
        echo "<li style='color: blue;'>ℹ️ Líder ya existente: $nombre</li>";
    }
    $liderIds[] = $userId;
}

// 2. Crear Votantes de Prueba (50 registros)
$nombres = ['Jose', 'Pedro', 'Marta', 'Lucia', 'Jorge', 'Sofia', 'Andres', 'Camila', 'Diego', 'Valentina', 'Ricardo', 'Paula', 'Fernando', 'Laura', 'Gabriel'];
$apellidos = ['Lopez', 'Garcia', 'Torres', 'Ramirez', 'Vargas', 'Jimenez', 'Moreno', 'Rojas', 'Muñoz', 'Castro', 'Ortiz', 'Silva', 'Hernandez', 'Morales', 'Peña'];
$lugares = ['Colegio San Jose', 'Escuela La Esperanza', 'Coliseo Municipal', 'Salon Comunal Centro', 'Institucion Educativa Norte', 'Puesto Salud Sur', 'Biblioteca Central'];

$count = 0;
// Generar fechas aleatorias en los últimos 10 días para simular actividad
for ($i = 0; $i < 50; $i++) {
    $nombre = $nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)];
    $cedula = rand(10000000, 99999999);
    $liderId = $liderIds[array_rand($liderIds)]; // Asignar a un líder aleatorio
    $lugar = $lugares[array_rand($lugares)];
    $mesa = rand(1, 50);
    $celular = '3' . rand(0, 2) . rand(0, 9) . rand(1000000, 9999999);

    // Estado aleatorio
    $yaVoto = (rand(0, 100) > 65) ? 1 : 0; // 35% prob de haber votado
    $estado = $yaVoto ? 'voto' : 'pendiente';

    // Fecha aleatoria
    $daysAgo = rand(0, 10);
    $date = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

    // Verificar duplicado
    $stmtCheck = $pdo->prepare("SELECT id FROM registros WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);

    if (!$stmtCheck->fetch()) {
        $stmtVoto = $pdo->prepare("INSERT INTO registros (user_id, tipo, nombres_apellidos, cedula, lugar_votacion, mesa, celular, estado_voto, ya_voto, created_at) VALUES (?, 'votante', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtVoto->execute([$liderId, $nombre, $cedula, $lugar, $mesa, $celular, $estado, $yaVoto, $date]);
        $count++;
    }
}

echo "</ul>";
echo "<h3>🎉 Proceso Finalizado con Éxito</h3>";
echo "<p>Se han insertado <strong>$count</strong> nuevos votantes de prueba.</p>";
echo "<div style='margin-top: 20px;'>";
echo "<a href='todos_registros.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Ver Panel General</a>";
echo "<a href='lideres.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Ver Líderes</a>";
echo "</div>";
echo "</div>";
?>
