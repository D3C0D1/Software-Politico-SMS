<?php
// fill_local_db.php - Script para rellenar base de datos local desde CLI
$_SERVER['HTTP_HOST'] = 'localhost'; // Simular entorno local para config.php
require_once 'config.php';

echo "Conectado a la base de datos local SQLite.\n";
echo "Generando datos de prueba...\n";

// 1. Crear Líderes
$lideres = [
    ['Carlos Perez', '1001', '3001234567'],
    ['Maria Rodriguez', '1002', '3109876543'],
    ['Juan Gomez', '1003', '3205556677'],
    ['Ana Martinez', '1004', '3151112233'],
    ['Luis Diaz', '1005', '3019998877']
];

$liderIds = [1]; // Empezamos con admin (ID 1)

foreach ($lideres as $l) {
    $nombre = $l[0];
    $cedula = $l[1];
    $celular = $l[2];

    // Verificar si existe usuario
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$cedula]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        $pass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'lider')");
        $stmtInsert->execute([$cedula, $pass, $nombre]);
        $userId = $pdo->lastInsertId();
        echo "[+] Creado Líder: $nombre\n";

        // Crear registro asociado
        $stmtReg = $pdo->prepare("INSERT INTO registros (user_id, tipo, nombres_apellidos, cedula, celular, lugar_votacion, mesa) VALUES (?, 'lider', ?, ?, ?, 'Sede Principal', '1')");
        $stmtReg->execute([1, $nombre, $cedula, $celular]);
    }
    else {
        $liderIds[] = $userId;
    }
    // Asegurar que el ID se añade a la lista para asignar votantes
    if (!in_array($userId, $liderIds))
        $liderIds[] = $userId;
}

// 2. Crear Votantes (50)
$nombres = ['Jose', 'Pedro', 'Marta', 'Lucia', 'Jorge', 'Sofia', 'Andres', 'Camila', 'Diego', 'Valentina', 'Ricardo', 'Paula', 'Fernando'];
$apellidos = ['Lopez', 'Garcia', 'Torres', 'Ramirez', 'Vargas', 'Jimenez', 'Moreno', 'Rojas', 'Muñoz', 'Castro', 'Ortiz', 'Silva'];
$lugares = ['Colegio San Jose', 'Escuela La Esperanza', 'Coliseo Municipal', 'Salon Comunal Centro'];

$count = 0;
for ($i = 0; $i < 50; $i++) {
    $nombre = $nombres[array_rand($nombres)] . ' ' . $apellidos[array_rand($apellidos)];
    $cedula = rand(10000000, 99999999);
    $liderId = $liderIds[array_rand($liderIds)];
    $lugar = $lugares[array_rand($lugares)];
    $mesa = rand(1, 20);
    $celular = '3' . rand(0, 9) . rand(0, 9) . rand(1000000, 9999999);

    $yaVoto = (rand(0, 100) > 60) ? 1 : 0;
    $estado = $yaVoto ? 'voto' : 'pendiente';
    $daysAgo = rand(0, 7);
    // SQLite datetime format
    $date = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

    // Check duplicate
    $stmtCheck = $pdo->prepare("SELECT id FROM registros WHERE cedula = ?");
    $stmtCheck->execute([$cedula]);

    if (!$stmtCheck->fetch()) {
        $stmtVoto = $pdo->prepare("INSERT INTO registros (user_id, tipo, nombres_apellidos, cedula, lugar_votacion, mesa, celular, estado_voto, ya_voto, created_at) VALUES (?, 'votante', ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtVoto->execute([$liderId, $nombre, $cedula, $lugar, $mesa, $celular, $estado, $yaVoto, $date]);
        $count++;
    }
}

echo "------------------------------------------------\n";
echo "¡Éxito! Se insertaron $count votantes nuevos y líderes.\n";
echo "Actualiza tu navegador (localhost) para ver los datos.\n";
?>
