<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// --- 0. Initialize Configuration Table (Auto-Migration) ---
// Checks if app_config table exists, if not creates it.
try {
    $pdo->query("SELECT 1 FROM app_config LIMIT 1");
}
catch (PDOException $e) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS app_config (
            id INTEGER PRIMARY KEY AUTOINCREMENT, 
            setting_key VARCHAR(50) UNIQUE NOT NULL,
            setting_value TEXT
        )";
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql = "CREATE TABLE IF NOT EXISTS app_config (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                setting_key VARCHAR(50) UNIQUE NOT NULL,
                setting_value TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        $pdo->exec($sql);

        $defaults = [
            'app_title' => 'Partido Liberal',
            'logo_path' => 'assets/img/logo.png',
            'profile_path' => 'assets/img/liberal.png',
            'primary_color' => '#E30613'
        ];

        $insert = $pdo->prepare("INSERT INTO app_config (setting_key, setting_value) VALUES (?, ?)");
        // Ignoring duplicate errors manually or trusting UNIQUE constraint failure to be caught or ignored
        foreach ($defaults as $key => $value) {
            try {
                $insert->execute([$key, $value]);
            }
            catch (Exception $ex) {
            }
        }
    }
    catch (PDOException $ex) {
    // Silent fail or log
    }
}

// --- Helper Functions ---
function get_config_val($pdo, $key, $org_id, $default = '')
{
    $stmt = $pdo->prepare("SELECT setting_value FROM app_config WHERE setting_key = ? AND organizacion_id = ?");
    $stmt->execute([$key, $org_id]);
    $val = $stmt->fetchColumn();
    return $val !== false ? $val : $default;
}

function set_config_val($pdo, $key, $value, $org_id)
{
    $stmt = $pdo->prepare("UPDATE app_config SET setting_value = ? WHERE setting_key = ? AND organizacion_id = ?");
    $stmt->execute([$value, $key, $org_id]);
    if ($stmt->rowCount() == 0) {
        // Need to check if it exists but really didn't change, or doesn't exist.
        // Easiest is to try insert and ignore duplicate key error if we had unique constraints,
        // but now we have (org_id, key).
        $check = $pdo->prepare("SELECT 1 FROM app_config WHERE setting_key = ? AND organizacion_id = ?");
        $check->execute([$key, $org_id]);
        if ($check->fetchColumn() === false) {
            $stmt = $pdo->prepare("INSERT INTO app_config (setting_key, setting_value, organizacion_id) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$key, $value, $org_id]);
            }
            catch (Exception $e) {
            }
        }
    }
}

$message = '';
$error = '';
$balance = '---';

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Onurix
    if (isset($_POST['save_config'])) {
        $onurix_client = trim($_POST['onurix_client']);
        $onurix_key = trim($_POST['onurix_key']);
        try {
            $stmt = $pdo->prepare("UPDATE users SET onurix_client = ?, onurix_key = ? WHERE id = ?");
            $stmt->execute([$onurix_client, $onurix_key, $_SESSION['user_id']]);
            $message = "Credenciales Onurix guardadas.";
        }
        catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
                $pdo->exec("ALTER TABLE users ADD onurix_client VARCHAR(100) NULL, ADD onurix_key VARCHAR(100) NULL");
                $stmt = $pdo->prepare("UPDATE users SET onurix_client = ?, onurix_key = ? WHERE id = ?");
                $stmt->execute([$onurix_client, $onurix_key, $_SESSION['user_id']]);
                $message = "Base de datos actualizada y credenciales guardadas.";
            }
            else {
                $error = "Error al guardar Onurix: " . $e->getMessage();
            }
        }
    }

    // Save Branding
    if (isset($_POST['save_branding'])) {
        try {
            $app_title = trim($_POST['app_title']);
            $primary_color = trim($_POST['primary_color']);

            $current_org_id = $_SESSION['organizacion_id'] ?? 1;

            set_config_val($pdo, 'app_title', $app_title, $current_org_id);
            set_config_val($pdo, 'primary_color', $primary_color, $current_org_id);

            // Upload Directory Check
            $target_dir = 'assets/img/uploads/';
            if (!is_dir($target_dir)) {
                if (!mkdir($target_dir, 0755, true)) {
                    throw new Exception("No se pudo crear el directorio de subidas. Verifique permisos.");
                }
            }

            // --- Logo Upload ---
            if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                $filename = $_FILES['logo_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    // Prevent giant file uploads causing timeouts/memory issues
                    if ($_FILES['logo_file']['size'] > 5000000) { // 5MB
                        throw new Exception("El logo es demasiado grande (máx 5MB).");
                    }

                    $new_name = 'logo_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $target_dir . $new_name)) {
                        set_config_val($pdo, 'logo_path', $target_dir . $new_name, $current_org_id);
                    }
                    else {
                        throw new Exception("Error al mover el archivo de logo.");
                    }
                }
                else {
                    $error .= "Formato de logo inválido. ";
                }
            }
            elseif (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] != 4) {
                // Error check (4 means no file uploaded)
                $errCode = $_FILES['logo_file']['error'];
                if ($errCode == 1 || $errCode == 2) {
                    throw new Exception("El archivo excede el tamaño máximo permitido por el servidor.");
                }
            }

            // --- Profile Upload ---
            if (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                $filename = $_FILES['profile_file']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed)) {
                    if ($_FILES['profile_file']['size'] > 5000000) { // 5MB
                        throw new Exception("La imagen de perfil es demasiado grande (máx 5MB).");
                    }

                    $new_name = 'profile_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['profile_file']['tmp_name'], $target_dir . $new_name)) {
                        set_config_val($pdo, 'profile_path', $target_dir . $new_name, $current_org_id);
                    }
                    else {
                        throw new Exception("Error al mover la imagen de perfil.");
                    }
                }
                else {
                    $error .= "Formato de perfil inválido. ";
                }
            }
            elseif (isset($_FILES['profile_file']) && $_FILES['profile_file']['error'] != 4) {
                $errCode = $_FILES['profile_file']['error'];
                if ($errCode == 1 || $errCode == 2) {
                    throw new Exception("El archivo excede el tamaño máximo permitido por el servidor.");
                }
            }

            if (empty($error)) {
                $message = "Configuración de apariencia actualizada.";
            }

        }
        catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// 2. Fetch User Settings
$stmt = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Branding Config
$app_config = [
    'app_title' => get_config_val($pdo, 'app_title', $_SESSION['organizacion_id'], 'Partido Liberal'),
    'logo_path' => get_config_val($pdo, 'logo_path', $_SESSION['organizacion_id'], 'assets/img/logo.png'),
    'profile_path' => get_config_val($pdo, 'profile_path', $_SESSION['organizacion_id'], 'assets/img/liberal.png'),
    'primary_color' => get_config_val($pdo, 'primary_color', $_SESSION['organizacion_id'], '#E30613')
];


// 3. Real-time Balance Check
if (!empty($settings['onurix_client']) && !empty($settings['onurix_key'])) {
    $url = "https://www.onurix.com/api/v1/balance?client=" . $settings['onurix_client'] . "&key=" . $settings['onurix_key'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $json = json_decode($response, true);
        $balance = isset($json['balance']) ? number_format($json['balance']) : "Error API";
    }
    else {
        $balance = "0 (Error Auth)";
    }
}

// 4. Handle SMS Template Savings
if (isset($_POST['save_templates'])) {
    $current_org_id = $_SESSION['organizacion_id'] ?? 1;

    $templatesToSave = [
        'inscripcion' => $_POST['tpl_inscripcion'] ?? '',
        'citacion' => $_POST['tpl_citacion'] ?? '',
        'confirmacion' => $_POST['tpl_confirmacion'] ?? ''
    ];

    foreach ($templatesToSave as $name => $content) {
        if (!empty($content)) {
            // Upsert Logic (MySQL specific ON DUPLICATE KEY UPDATE)
            $sql = "INSERT INTO sms_templates (name, label, content, organizacion_id) 
                    VALUES (:name, :label, :content, :org_id)
                    ON DUPLICATE KEY UPDATE content = :content_update";

            $labels = [
                'inscripcion' => 'SMS de Inscripción',
                'citacion' => 'SMS de Citación',
                'confirmacion' => 'SMS de Confirmación'
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':name' => $name,
                ':label' => $labels[$name],
                ':content' => $content,
                ':org_id' => $current_org_id,
                ':content_update' => $content
            ]);
        }
    }
    $message = "Plantillas de SMS actualizadas correctamente.";
}

// 5. Fetch Current Templates
$current_org_id = $_SESSION['organizacion_id'] ?? 1;
$smsTemplates = [];
$stmtTpl = $pdo->prepare("SELECT name, content FROM sms_templates WHERE organizacion_id = ?");
$stmtTpl->execute([$current_org_id]);
while ($row = $stmtTpl->fetch(PDO::FETCH_ASSOC)) {
    $smsTemplates[$row['name']] = $row['content'];
}
// Defaults if missing in DB (though setup script should handle it)
$smsTemplates['inscripcion'] = $smsTemplates['inscripcion'] ?? 'Gracias por inscribirte y hacer parte del cambio';
$smsTemplates['citacion'] = $smsTemplates['citacion'] ?? 'Recuerda que con el Partido Liberal lograremos el cambio';
$smsTemplates['confirmacion'] = $smsTemplates['confirmacion'] ?? 'Confirma tu voto aquí: {LINK_CONFIRMACION}';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Servicios - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar-toggle.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-red: <?php echo htmlspecialchars($app_config['primary_color']);
?>;
            --primary-dark: <?php echo htmlspecialchars($app_config['primary_color']);
?>;
        }

        .file-upload-wrapper {
            border: 2px dashed #ddd;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: #fafafa;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary-red);
            background: #fff;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }

        .service-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .service-card:hover {
            transform: translateY(-5px);
        }

        /* Banner effect */
        .service-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--primary-red);
        }

        .onurix-card::before {
            background: #002e6d;
            /* Onurix Blue */
        }

        .card-header-styled {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .service-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 30px;
            background: #f0f4f8;
            color: #555;
        }

        .onurix-icon {
            background: #e6efff;
            color: #002e6d;
        }

        .onurix-img {
            max-width: 120px;
            height: auto;
        }

        .service-title h3 {
            margin: 0;
            font-size: 1.3rem;
            color: var(--text-main);
        }

        .service-title span {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .balance-display {
            background: linear-gradient(135deg, #002e6d 0%, #0050b3 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
            position: relative;
        }

        .balance-display h4 {
            margin: 0 0 10px 0;
            font-weight: 400;
            opacity: 0.9;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .balance-amount {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }

        .service-desc {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .btn-config {
            margin-top: auto;
            background: white;
            border: 2px solid #002e6d;
            color: #002e6d;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-config:hover {
            background: #002e6d;
            color: white;
        }

        /* Modal Custom Styles */
        .config-modal-header {
            background: #002e6d;
            /* Match Onurix */
            color: white;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Override global modal h2 style */
        .config-modal-header h2 {
            background: transparent !important;
            padding: 0 !important;
            margin: 0 !important;
            color: white !important;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .config-modal-header .close {
            position: static;
            /* Reset absolute position from specific context if needed, or rely on flex */
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.5rem;
            margin-left: auto;
            cursor: pointer;
            transition: color 0.2s;
        }

        .config-modal-header .close:hover {
            color: white;
        }

        .form-group-custom {
            margin-bottom: 25px;
        }

        .form-group-custom label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 0.9rem;
        }

        .form-group-custom input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .form-group-custom input:focus {
            border-color: #002e6d;
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 46, 109, 0.1);
            outline: none;
        }

        .help-text {
            font-size: 0.8rem;
            color: #888;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .modal-footer {
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .btn-cancel {
            background: #f1f3f5;
            color: #555;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-cancel:hover {
            background: #e9ecef;
            color: #333;
        }

        .btn-save {
            background: #002e6d;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 46, 109, 0.2);
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: #001f4d;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(0, 46, 109, 0.25);
        }
    </style>
</head>

<body class="dashboard-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <div class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="page-title">Configuración de Plataforma</h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>
                    </strong></span>
                <div class="profile-dropdown">
                    <button onclick="document.getElementById('profileDropdown').classList.toggle('show-dropdown')"
                        class="profile-btn">
                        <img src="<?php echo htmlspecialchars($app_config['profile_path']); ?>" alt="Profile"
                            style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                    </button>
                    <div id="profileDropdown" class="dropdown-content">
                        <a href="dashboard.php">Volver al Inicio</a>
                        <a href="logout.php">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">

            <?php if ($message): ?>
            <script>Swal.fire({ icon: 'success', title: '¡Guardado!', text: '<?php echo $message; ?>', confirmButtonColor: '#002e6d' });</script>
            <?php
endif; ?>

            <?php if ($error): ?>
            <script>Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $error; ?>', confirmButtonColor: '#d33' });</script>
            <?php
endif; ?>

            <div class="settings-grid">

                <!-- Card 0: Branding Config -->
                <div class="service-card" style="border-top: 6px solid var(--primary-red);">
                    <div class="card-header-styled">
                        <div class="service-icon" style="background: rgba(227, 6, 19, 0.1); color: var(--primary-red);">
                            <i class="fas fa-paint-brush"></i>
                        </div>
                        <div class="service-title">
                            <h3>Personalización</h3>
                            <span>Apariencia del Software</span>
                        </div>
                    </div>

                    <div style="flex-grow: 1;">
                        <p class="service-desc">
                            Personaliza el logotipo, título y colores de la plataforma para ajustarse a tu identidad.
                        </p>
                        <div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                            <img src="<?php echo htmlspecialchars($app_config['logo_path']) . '?v=' . time(); ?>"
                                style="max-height: 40px; border: 1px solid #eee; padding: 2px; border-radius: 4px;">
                            <div
                                style="width: 30px; height: 30px; background: <?php echo htmlspecialchars($app_config['primary_color']); ?>; border-radius: 50%;">
                            </div>
                        </div>
                    </div>

                    <button onclick="openBrandingModal()" class="btn-config"
                        style="border-color: var(--primary-red); color: var(--primary-red);">
                        <i class="fas fa-magic"></i> Editar Apariencia
                    </button>

                    <style>
                        .btn-config:hover {
                            background: var(--primary-red) !important;
                            color: white !important;
                        }
                    </style>
                </div>

                <!-- Card 1: Balance Status -->
                <div class="service-card onurix-card">
                    <div class="card-header-styled">
                        <div class="service-icon onurix-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="service-title">
                            <h3>Estado de Cuenta</h3>
                            <span>Créditos SMS Disponibles</span>
                        </div>
                    </div>

                    <div class="balance-display">
                        <h4>Saldo Actual</h4>
                        <div class="balance-amount">
                            <?php echo $balance; ?>
                        </div>
                        <div style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8;">Actualizado en tiempo real</div>
                    </div>

                    <p class="service-desc">
                        Este saldo se consume automáticamente con cada SMS de confirmación, citación o campaña enviado
                        desde la plataforma.
                    </p>
                </div>

                <!-- Card 2: Service Info & Config -->
                <div class="service-card onurix-card">
                    <div class="card-header-styled">
                        <!-- Use FontAwesome if logo not available, or placeholder -->
                        <div class="service-icon onurix-icon">
                            <i class="fas fa-comment-sms"></i>
                        </div>
                        <div class="service-title">
                            <h3>Onurix SMS</h3>
                            <span>Proveedor de Mensajería</span>
                        </div>
                    </div>

                    <div style="flex-grow: 1;">
                        <p class="service-desc">
                            <strong>Onurix</strong> es la plataforma encargada de entregar los mensajes de texto a tus
                            votantes.
                        </p>
                        <ul style="color: #666; margin-bottom: 20px; padding-left: 20px; font-size: 0.95rem;">
                            <li>Alta tasa de entrega garantizada.</li>
                            <li>Soporte para operadores nacionales.</li>
                            <li>API segura y rápida.</li>
                        </ul>

                        <div
                            style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #002e6d; font-size: 0.9rem; color: #555;">
                            <i class="fas fa-info-circle" style="color: #002e6d;"></i> Asegúrate de mantener tus
                            credenciales actualizadas para evitar interrupciones en el servicio.
                        </div>
                    </div>

                    <button onclick="openConfigModal()" class="btn-config">
                        <i class="fas fa-cog"></i> Configurar Credenciales
                    </button>
                </div>

                <!-- Card 3: SMS Templates -->
                <div class="service-card" style="border-top: 6px solid #ffc107;">
                    <div class="card-header-styled">
                        <div class="service-icon" style="background: rgba(255, 193, 7, 0.1); color: #d39e00;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="service-title">
                            <h3>Plantillas de SMS</h3>
                            <span>Mensajes Personalizados</span>
                        </div>
                    </div>

                    <div style="flex-grow: 1;">
                        <p class="service-desc">
                            Edita el contenido de los mensajes de texto que se envían automáticamente a tus votantes.
                        </p>
                        <ul style="color: #666; margin-bottom: 20px; padding-left: 20px; font-size: 0.95rem;">
                            <li>Inscripción (Bienvenida)</li>
                            <li>Citación (Día de Votación)</li>
                            <li>Confirmación (Check-in)</li>
                        </ul>
                    </div>

                    <button onclick="openTemplateModal()" class="btn-config"
                        style="border-color: #ffc107; color: #d39e00;">
                        <i class="fas fa-edit"></i> Editar Plantillas
                    </button>
                    <style>
                        .btn-config[onclick="openTemplateModal()"]:hover {
                            background: #ffc107 !important;
                            color: white !important;
                        }
                    </style>
                </div>

                <!-- Card 4: Database Backup -->
                <div class="service-card" style="border-top: 6px solid #28a745;">
                    <div class="card-header-styled">
                        <div class="service-icon icon-green">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="service-title">
                            <h3>Base de Datos</h3>
                            <span>Respaldo y Migración</span>
                        </div>
                    </div>

                    <div style="flex-grow: 1;">
                        <p class="service-desc">
                            Descarga una copia de seguridad de tu base de datos local compatible con MySQL.
                        </p>
                        <ul style="color: #666; margin-bottom: 20px; padding-left: 20px; font-size: 0.95rem;">
                            <li>Incluye usuarios y registros.</li>
                            <li>Formato listo para importar.</li>
                        </ul>
                        <div
                            style="background: #e8f5e9; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; font-size: 0.9rem; color: #555;">
                            <i class="fas fa-cloud-upload-alt" style="color:#28a745;"></i> Sube el archivo
                            <strong>.sql</strong> generado al phpMyAdmin de tu hosting.
                        </div>
                    </div>

                    <a href="export_db.php" target="_blank" class="btn-config"
                        style="border-color: #28a745; color: #28a745;">
                        <i class="fas fa-file-export"></i> Exportar a SQL
                    </a>
                </div>
            </div>
        </div>

        <!-- Branding Configuration Modal -->
        <div id="brandingModal" class="modal">
            <div class="modal-content">
                <div class="config-modal-header" style="background: var(--primary-red);">
                    <h2><i class="fas fa-paint-brush"></i> Personalizar Plataforma</h2>
                    <span class="close" onclick="closeBrandingModal()">&times;</span>
                </div>
                <div class="modal-form">
                    <form method="POST" action="" enctype="multipart/form-data">

                        <div class="form-group-custom">
                            <label>Nombre del Software (Título)</label>
                            <input type="text" name="app_title"
                                value="<?php echo htmlspecialchars($app_config['app_title']); ?>" required>
                        </div>

                        <div class="form-group-custom">
                            <label>Color Principal</label>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <input type="color" name="primary_color"
                                    value="<?php echo htmlspecialchars($app_config['primary_color']); ?>"
                                    style="height: 50px; width: 80px; padding: 2px;">
                                <span style="font-size: 0.9rem; color: #666;">Selecciona el color de botones y
                                    encabezados.</span>
                            </div>
                        </div>

                        <div class="form-group-custom">
                            <label>Logo del Software (Sidebar/Login)</label>
                            <div style="display: flex; gap: 15px; align-items: start;">
                                <div style="text-align: center; width: 100px;">
                                    <span
                                        style="font-size: 0.8rem; color: #777; display: block; margin-bottom: 5px;">Actual:</span>
                                    <img src="<?php echo htmlspecialchars($app_config['logo_path']) . '?v=' . time(); ?>"
                                        style="max-width: 100%; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
                                </div>
                                <div class="file-upload-wrapper" style="flex-grow: 1;"
                                    onclick="document.getElementById('logo_file').click()">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #ccc;"></i>
                                    <p style="margin: 5px 0 0; color: #777; font-size: 0.9rem;">Clic para cambiar logo
                                    </p>
                                    <p id="logo-filename"
                                        style="margin: 0; font-size: 0.8rem; color: var(--primary-red); font-weight: bold;">
                                    </p>
                                    <input type="file" name="logo_file" id="logo_file" style="display: none;"
                                        accept="image/*"
                                        onchange="document.getElementById('logo-filename').innerText = this.files[0].name">
                                </div>
                            </div>
                        </div>

                        <div class="form-group-custom">
                            <label>Imagen de Perfil (Header)</label>
                            <div style="display: flex; gap: 15px; align-items: start;">
                                <div style="text-align: center; width: 100px;">
                                    <span
                                        style="font-size: 0.8rem; color: #777; display: block; margin-bottom: 5px;">Actual:</span>
                                    <img src="<?php echo htmlspecialchars($app_config['profile_path']) . '?v=' . time(); ?>"
                                        style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 1px solid #ddd;">
                                </div>
                                <div class="file-upload-wrapper" style="flex-grow: 1;"
                                    onclick="document.getElementById('profile_file').click()">
                                    <i class="fas fa-user-circle" style="font-size: 24px; color: #ccc;"></i>
                                    <p style="margin: 5px 0 0; color: #777; font-size: 0.9rem;">Clic para cambiar imagen
                                    </p>
                                    <p id="profile-filename"
                                        style="margin: 0; font-size: 0.8rem; color: var(--primary-red); font-weight: bold;">
                                    </p>
                                    <input type="file" name="profile_file" id="profile_file" style="display: none;"
                                        accept="image/*"
                                        onchange="document.getElementById('profile-filename').innerText = this.files[0].name">
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeBrandingModal()" class="btn-cancel">Cancelar</button>
                            <button type="submit" name="save_branding" class="btn-save"
                                style="background: var(--primary-red);">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Onurix Configuration Modal -->
        <div id="configModal" class="modal">
            <div class="modal-content">
                <div class="config-modal-header">
                    <h2><i class="fas fa-key"></i> Credenciales Onurix</h2>
                    <span class="close" onclick="closeConfigModal()">&times;</span>
                </div>
                <div class="modal-form">
                    <p style="margin-bottom: 20px; color: #666;">Ingresa tu Client ID y Key proporcionados por Onurix
                        para conectar la plataforma.</p>

                    <form method="POST" action="">
                        <div class="form-group-custom">
                            <label for="client">Client ID</label>
                            <input type="text" name="onurix_client" id="client"
                                value="<?php echo htmlspecialchars($settings['onurix_client'] ?? ''); ?>"
                                placeholder="Ej: CLIENT-ID-..." required>
                        </div>

                        <div class="form-group-custom">
                            <label for="key">Client Key</label>
                            <input type="password" name="onurix_key" id="key"
                                value="<?php echo htmlspecialchars($settings['onurix_key'] ?? ''); ?>"
                                placeholder="Ej: KEY-SECRET-..." required>
                            <p class="help-text">Esta información es privada y se guarda de forma segura.</p>
                        </div>

                        <div class="modal-footer">
                            <button type="button" onclick="closeConfigModal()" class="btn-cancel">Cancelar</button>
                            <button type="submit" name="save_config" class="btn-save">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- SMS Templates Modal -->
        <div id="templateModal" class="modal">
            <div class="modal-content" style="max-width: 800px;">
                <div class="config-modal-header" style="background: #ffc107; color: #333;">
                    <h2 style="color: #333 !important;"><i class="fas fa-file-alt"></i> Editor de Plantillas SMS</h2>
                    <span class="close" onclick="closeTemplateModal()"
                        style="color: #333 !important; opacity: 0.7;">&times;</span>
                </div>
                <div class="modal-form">
                    <form method="POST" action="">
                        <div class="template-tabs"
                            style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee;">
                            <button type="button" class="tab-btn active"
                                onclick="showTab('inscripcion')">Inscripción</button>
                            <button type="button" class="tab-btn" onclick="showTab('citacion')">Citación</button>
                            <button type="button" class="tab-btn"
                                onclick="showTab('confirmacion')">Confirmación</button>
                        </div>

                        <!-- Inscripcion -->
                        <div id="tab-inscripcion" class="tab-content" style="display: block;">
                            <div class="form-group-custom">
                                <label>Mensaje de Inscripción (Bienvenida)</label>
                                <textarea name="tpl_inscripcion" rows="4" class="form-control"
                                    style="width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 8px;"
                                    maxlength="160"><?php echo htmlspecialchars($smsTemplates['inscripcion'] ?? ''); ?></textarea>
                                <div class="help-text"><i class="fas fa-info-circle"></i> Variables disponibles:
                                    {NOMBRE}, {CEDULA}, {LINK_CONFIRMACION}</div>
                            </div>
                        </div>

                        <!-- Citacion -->
                        <div id="tab-citacion" class="tab-content" style="display: none;">
                            <div class="form-group-custom">
                                <label>Mensaje de Citación (Recordatorio)</label>
                                <textarea name="tpl_citacion" rows="4" class="form-control"
                                    style="width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 8px;"
                                    maxlength="160"><?php echo htmlspecialchars($smsTemplates['citacion'] ?? ''); ?></textarea>
                                <div class="help-text"><i class="fas fa-info-circle"></i> Variables disponibles:
                                    {NOMBRE}, {CEDULA}, {LUGAR_VOTACION}, {MESA}</div>
                            </div>
                        </div>

                        <!-- Confirmacion -->
                        <div id="tab-confirmacion" class="tab-content" style="display: none;">
                            <div class="form-group-custom">
                                <label>Mensaje de Confirmación (Post-Voto)</label>
                                <textarea name="tpl_confirmacion" rows="4" class="form-control"
                                    style="width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 8px;"
                                    maxlength="160"><?php echo htmlspecialchars($smsTemplates['confirmacion'] ?? ''); ?></textarea>
                                <div class="help-text"><i class="fas fa-info-circle"></i> Variables disponibles:
                                    {NOMBRE}, {LINK_CONFIRMACION}</div>
                            </div>
                        </div>

                        <style>
                            .tab-btn {
                                background: none;
                                border: none;
                                padding: 10px 20px;
                                cursor: pointer;
                                font-weight: 600;
                                color: #666;
                                border-bottom: 3px solid transparent;
                            }

                            .tab-btn.active {
                                color: #333;
                                border-bottom-color: #ffc107;
                            }

                            .tab-btn:hover {
                                background: #f9f9f9;
                            }
                        </style>

                        <div class="modal-footer">
                            <button type="button" onclick="closeTemplateModal()" class="btn-cancel">Cancelar</button>
                            <button type="submit" name="save_templates" class="btn-save"
                                style="background: #ffc107; color: #333;">Guardar Plantillas</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            // Modal Logic
            const modal = document.getElementById("configModal");
            const brandingModal = document.getElementById("brandingModal");
            const templateModal = document.getElementById("templateModal");

            function openConfigModal() {
                modal.style.display = "block";
            }

            function closeConfigModal() {
                modal.style.display = "none";
            }
            function openBrandingModal() {
                brandingModal.style.display = "block";
            }

            function closeBrandingModal() {
                brandingModal.style.display = "none";
            }

            function openTemplateModal() {
                if (templateModal) templateModal.style.display = "block";
            }

            function closeTemplateModal() {
                if (templateModal) templateModal.style.display = "none";
            }

            function showTab(tabName) {
                // Hide all
                document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
                document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

                // Show selected
                document.getElementById('tab-' + tabName).style.display = 'block';
                // Find button that triggered this? Or passed 'active'?
                // Simple hack: loop buttons to check text or onclick, but easier:
                // Actually, event.target is passed implicitly if called from onclick?
                // But let's fix the tab button active state explicitly via selector if possible, 
                // but event.target is standard.
                if (event && event.target) event.target.classList.add('active');
            }

            // Close on click outside
            window.onclick = function (event) {
                if (event.target == modal) {
                    closeConfigModal();
                }
                if (event.target == brandingModal) {
                    closeBrandingModal();
                }
                if (event.target == templateModal) {
                    closeTemplateModal();
                }
                // Close Profile Dropdown if clicked outside
                if (!event.target.matches('.profile-btn') && !event.target.matches('.profile-btn *')) {
                    var dropdown = document.getElementById("profileDropdown");
                    if (dropdown && dropdown.classList.contains('show-dropdown')) {
                        dropdown.classList.remove('show-dropdown');
                    }
                }
            }

            // Sidebar Toggle Logic
            function toggleSidebar() {
                document.body.classList.toggle('sidebar-closed');
                document.body.classList.toggle('sidebar-open');
            }
        </script>
</body>

</html>