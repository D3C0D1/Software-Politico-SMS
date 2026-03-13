<?php
// config.php - Configuración de Base de Datos con Detección Automática Robustecida

// --- Detección de Entorno ---
$isLocal = false;

// 1. Detección por Dominio/IP (localhost, 127.0.0.1)
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    $isLocal = true;
}

// 2. Detección por Sistema de Archivos (Específico para tu entorno Ampps)
// Si la ruta contiene 'Ampps' o estamos en Windows (Hostinger usa Linux), asumimos local.
if (stripos(__DIR__, 'Ampps') !== false || DIRECTORY_SEPARATOR === '\\') {
    $isLocal = true;
}

try {
    if ($isLocal) {
        // --- ENTORNO LOCAL (MySQL) ---
        // Se ejecuta cuando estás en tu PC (localhost/Ampps)
        $credsFile = __DIR__ . '/mysql_creds.json';
        if (file_exists($credsFile)) {
            $creds = json_decode(file_get_contents($credsFile), true);
            $dbHost = $creds['host'];
            $dbName = $creds['db'];
            $dbUser = $creds['user'];
            $dbPass = $creds['pass'];
        }
        else {
            // Fallback default
            $dbHost = '127.0.0.1';
            $dbName = 'politica';
            $dbUser = 'root';
            $dbPass = 'mysql';
        }

        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Zona horaria
        date_default_timezone_set('America/Bogota');
    }
    else {
        // --- ENTORNO PRODUCCIÓN (HOSTINGER: glamcity.store) ---
        $dbHost = 'localhost';
        $dbName = 'u469305563_campa';
        $dbUser = 'u469305563_campa';
        $dbPass = 'A0347a1312#'; // Contraseña confirmada por usuario

        $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Zona horaria
        date_default_timezone_set('America/Bogota');

        // Inicialización de Tablas MySQL (Auto-creación)
        // 1. Usuarios
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100),
            role VARCHAR(20) DEFAULT 'user',
            onurix_client VARCHAR(100),
            onurix_key VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 2. Registros
        $pdo->exec("CREATE TABLE IF NOT EXISTS registros (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            tipo VARCHAR(20) DEFAULT 'votante',
            nombres_apellidos VARCHAR(150),
            cedula VARCHAR(20),
            lugar_votacion VARCHAR(150),
            mesa VARCHAR(10),
            celular VARCHAR(20),
            municipio VARCHAR(100),
            departamento VARCHAR(100),
            barrio_vereda VARCHAR(100),
            direccion VARCHAR(150),
            email VARCHAR(100),
            estado_voto VARCHAR(20) DEFAULT 'pendiente',
            ya_voto TINYINT(1) DEFAULT 0,
            sms_inscripcion TINYINT(1) DEFAULT 0,
            sms_citacion TINYINT(1) DEFAULT 0,
            sms_confirmacion TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    // --- Usuario Admin (Común para ambos) ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        // Ajustamos la query para ser compatible con ambos (el campo 'name' lo añadimos a MySQL antes)
        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, role, name) VALUES ('admin', ?, 'admin', 'Administrador Principal')");
        $stmtInsert->execute([$hash]);
    }

}
catch (PDOException $e) {
    // Mensaje de error detallado
    $env = $isLocal ? "LOCAL (SQLite)" : "PRODUCCIÓN (MySQL)";
    die("<h3>Error Crítico de Conexión</h3><p>Entorno detectado: <strong>$env</strong></p><p>Detalle: " . $e->getMessage() . "</p>");
}

// Sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// --- Auto-Migration Check Skipped for MySQL (Schema handled by cleanup) ---
// If you need auto-migration, implement using SHOW COLUMNS or standard SQL.

// --- Load App Configuration (Organization Aware) ---
$app_config = [
    'app_title' => 'Partido Liberal',
    'logo_path' => 'assets/img/logo.png',
    'profile_path' => 'assets/img/liberal.png',
    'primary_color' => '#E30613',
    // Credenciales Globales ONURIX (Por defecto para todos)
    'onurix_client' => '7389',
    'onurix_key' => 'baf0076e7d995fc544c21cea4fdf898ce00612f268dc5f38c3565'
];

try {
    $current_org_id = $_SESSION['organizacion_id'] ?? 1;
    if (empty($current_org_id))
        $current_org_id = 1;
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_config WHERE organizacion_id = ?");
    $stmt->execute([$current_org_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $app_config[$row['setting_key']] = $row['setting_value'];
    }
}
catch (Exception $e) {
// Fallback if column missing or table missing
}
// Helper: Convert Hex to RGB
function hex2rgb($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    }
    else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}
$app_config['primary_rgb'] = hex2rgb($app_config['primary_color']);

// --- System Logging Helper ---
function logSystemAction($pdo, $user_id, $org_id, $action, $description)
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare("INSERT INTO system_logs (user_id, organizacion_id, action_type, description, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $org_id, $action, $description, $ip]);
    }
    catch (Exception $e) {
        // Silently fail to not disrupt user flow, or log to error log
        error_log("Logging Failed: " . $e->getMessage());
    }
}
?>