<?php
require_once 'config.php';

// Access Control: ONLY Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

// --- Helper Functions (Copied from configuraciones.php) ---
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

// Default Organization for Superadmin Config (Assume Global/Master = 1)
$current_org_id = 1;

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Save Onurix (Master Account)
    if (isset($_POST['save_config'])) {
        $onurix_client = trim($_POST['onurix_client']);
        $onurix_key = trim($_POST['onurix_key']);
        try {
            // Update Superadmin User (assuming current session is superadmin)
            $stmt = $pdo->prepare("UPDATE users SET onurix_client = ?, onurix_key = ? WHERE id = ?");
            $stmt->execute([$onurix_client, $onurix_key, $_SESSION['user_id']]);
            $message = "Credenciales Maestras de Onurix guardadas.";
        }
        catch (PDOException $e) {
            $error = "Error al guardar Onurix: " . $e->getMessage();
        }
    }

    // Save Login Design
    if (isset($_POST['save_branding'])) {
        try {
            $login_title = trim($_POST['login_title']);
            $login_subtitle = trim($_POST['login_subtitle']);
            $login_bg_color = trim($_POST['login_bg_color']);
            $login_btn_color = trim($_POST['login_btn_color']);

            set_config_val($pdo, 'login_title', $login_title, $current_org_id);
            set_config_val($pdo, 'login_subtitle', $login_subtitle, $current_org_id);
            set_config_val($pdo, 'login_bg_color', $login_bg_color, $current_org_id);
            set_config_val($pdo, 'login_btn_color', $login_btn_color, $current_org_id);

            // Upload Directory
            $target_dir = 'assets/img/uploads/';
            if (!is_dir($target_dir))
                mkdir($target_dir, 0755, true);

            // Logo del Login
            if (isset($_FILES['login_logo_file']) && $_FILES['login_logo_file']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                $ext = strtolower(pathinfo($_FILES['login_logo_file']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    if ($_FILES['login_logo_file']['size'] > 5000000)
                        throw new Exception("El logo es demasiado grande (máx 5MB).");
                    $new_name = 'login_logo_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['login_logo_file']['tmp_name'], $target_dir . $new_name)) {
                        set_config_val($pdo, 'login_logo', $target_dir . $new_name, $current_org_id);
                    }
                    else
                        throw new Exception("Error al subir el logo.");
                }
                else
                    $error .= "Formato de logo inválido. ";
            }

            if (empty($error))
                $message = "Diseño del Login actualizado correctamente.";
        }
        catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }

    // Handle SMS Template Savings
    if (isset($_POST['save_templates'])) {
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
        $message = "Plantillas de SMS globales actualizadas correctamente.";
    }
}

// 2. Fetch User Settings (Superadmin)
$stmt = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch Login Design Config
$login_config = [
    'login_logo' => get_config_val($pdo, 'login_logo', $current_org_id, 'assets/img/logo_default.png'),
    'login_title' => get_config_val($pdo, 'login_title', $current_org_id, 'Plataforma Política'),
    'login_subtitle' => get_config_val($pdo, 'login_subtitle', $current_org_id, 'Gestión de Campañas'),
    'login_bg_color' => get_config_val($pdo, 'login_bg_color', $current_org_id, '#000000'),
    'login_btn_color' => get_config_val($pdo, 'login_btn_color', $current_org_id, '#E30613'),
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

// 4. Fetch Current Templates
$smsTemplates = [];
$stmtTpl = $pdo->prepare("SELECT name, content FROM sms_templates WHERE organizacion_id = ?");
$stmtTpl->execute([$current_org_id]);
while ($row = $stmtTpl->fetch(PDO::FETCH_ASSOC)) {
    $smsTemplates[$row['name']] = $row['content'];
}
// Defaults
$smsTemplates['inscripcion'] = $smsTemplates['inscripcion'] ?? 'Hola {NOMBRE}! Gracias por registrarte...';
$smsTemplates['citacion'] = $smsTemplates['citacion'] ?? 'Hola {NOMBRE}, recordatorio...';
$smsTemplates['confirmacion'] = $smsTemplates['confirmacion'] ?? 'Gracias por votar...';

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Configuración Global - Super Admin</title>
    <!-- Use Superadmin CSS but also include specific styles from configuraciones.php -->
    <link rel="stylesheet" href="assets/css/superadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Styles ported from configuraciones.php */
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid #e2e8f0;
        }

        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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

        .service-title h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #333;
        }

        .service-title span {
            font-size: 0.85rem;
            color: #777;
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
        .modal {
            display: none;
            position: fixed;
            z-index: 1005;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 0;
            border: none;
            width: 90%;
            max-width: 550px;
            border-radius: 16px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .config-modal-header {
            background: #002e6d;
            color: white;
            padding: 20px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .config-modal-header h2 {
            margin: 0;
            color: white;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-form {
            padding: 25px;
        }

        .close {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .close:hover {
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

        .form-group-custom input,
        .form-group-custom textarea {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafa;
            box-sizing: border-box;
        }

        .form-group-custom input:focus,
        .form-group-custom textarea:focus {
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
        }

        .btn-save {
            background: #002e6d;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <div class="superadmin-layout">
        <aside class="super-sidebar">
            <div class="super-sidebar-header">
                <h2>SISTEMA ELITE</h2>
                <div style="font-size: 0.75rem; opacity: 0.6; margin-top: 0.5rem;">Gestión de Multi-Tenancy</div>
            </div>
            <nav class="super-nav">
                <a href="superadmin_dashboard.php" class="super-nav-item">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="superadmin_organizations.php" class="super-nav-item">
                    <i class="fas fa-briefcase"></i> Organizaciones
                </a>
                <a href="superadmin_logs.php" class="super-nav-item">
                    <i class="fas fa-sms"></i> Logs SMS
                </a>
                <a href="superadmin_system_logs.php" class="super-nav-item">
                    <i class="fas fa-server"></i> Logs del Sistema
                </a>
                <a href="superadmin_config.php" class="super-nav-item active">
                    <i class="fas fa-cog"></i> Configuración Global
                </a>
            </nav>
        </aside>

        <main class="super-main-content">
            <div class="super-topbar">
                <h1>Configuración Global del Sistema (Master)</h1>
                <div class="super-user-info">
                    <span>Sesión Admin: <strong>Dios</strong></span>
                    <a href="logout.php" class="btn-logout-super">Cerrar Sesión</a>
                </div>
            </div>

            <div class="super-content-area">

                <?php if ($message): ?>
                <script>Swal.fire({ icon: 'success', title: '¡Guardado!', text: '<?php echo $message; ?>', confirmButtonColor: '#002e6d' });</script>
                <?php
endif; ?>

                <?php if ($error): ?>
                <script>Swal.fire({ icon: 'error', title: 'Error', text: '<?php echo $error; ?>', confirmButtonColor: '#d33' });</script>
                <?php
endif; ?>

                <p style="color: #666; margin-bottom: 20px;">
                    Aquí puedes configurar los parámetros globales del sistema, incluyendo la apariencia por defecto y
                    la cuenta maestra de SMS.
                </p>

                <div class="settings-grid">

                    <!-- Card 0: Branding Config -->
                    <div class="service-card" style="border-top: 6px solid var(--primary-red);">
                        <div class="card-header-styled">
                            <div class="service-icon"
                                style="background: rgba(227, 6, 19, 0.1); color: var(--primary-red);">
                                <i class="fas fa-paint-brush"></i>
                            </div>
                            <div class="service-title">
                                <h3>Personalización</h3>
                                <span>Apariencia Global</span>
                            </div>
                        </div>

                        <div style="flex-grow: 1;">
                            <p class="service-desc">
                                Personaliza el logotipo, título y colores de la plataforma para ajustarse a la identidad
                                maestra.
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
                            <h4>Saldo Maestro</h4>
                            <div class="balance-amount">
                                <?php echo $balance; ?>
                            </div>
                            <div style="font-size: 0.8rem; margin-top: 5px; opacity: 0.8;">Actualizado en tiempo real
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Service Info & Config -->
                    <div class="service-card onurix-card">
                        <div class="card-header-styled">
                            <div class="service-icon onurix-icon">
                                <i class="fas fa-comment-sms"></i>
                            </div>
                            <div class="service-title">
                                <h3>Onurix SMS</h3>
                                <span>Cuenta Maestra</span>
                            </div>
                        </div>

                        <div style="flex-grow: 1;">
                            <p class="service-desc">
                                Configura las credenciales de Onurix que se usarán globalmente si una organización no
                                tiene sus propias credenciales.
                            </p>
                        </div>

                        <div style="display: flex; gap: 10px; margin-top: auto;">
                            <button onclick="openConfigModal()" class="btn-config" style="flex: 1;">
                                <i class="fas fa-cog"></i> Configurar Maestra
                            </button>
                            <a href="superadmin_users_creds.php" class="btn-config"
                                style="flex: 1; text-decoration: none; border-color: #64748b; color: #64748b;">
                                <i class="fas fa-users-cog"></i> Ver Líderes
                            </a>
                        </div>
                    </div>

                    <!-- Card 3: SMS Templates -->
                    <div class="service-card" style="border-top: 6px solid #ffc107;">
                        <div class="card-header-styled">
                            <div class="service-icon" style="background: rgba(255, 193, 7, 0.1); color: #d39e00;">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="service-title">
                                <h3>Plantillas de SMS</h3>
                                <span>Mensajes por Defecto</span>
                            </div>
                        </div>

                        <div style="flex-grow: 1;">
                            <p class="service-desc">
                                Edita el contenido de los mensajes de texto por defecto que se utilizan si una
                                organización no define los suyos.
                            </p>
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

                    <!-- Card 4: Database Info -->
                    <div class="service-card" style="border-top: 6px solid #28a745;">
                        <div class="card-header-styled">
                            <div class="service-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="service-title">
                                <h3>Base de Datos</h3>
                                <span>Estado del Sistema</span>
                            </div>
                        </div>
                        <div style="flex-grow: 1;">
                            <div class="metric-value" style="font-size: 1.5rem; text-align: center; margin-top: 10px;">
                                <?php echo ($isLocal) ? 'MySQL Local <br><small>(AMPPS / Localhost)</small>' : 'MySQL Remoto <br><small>(Hostinger)</small>'; ?>
                            </div>
                        </div>
                    </div>

                </div>

            </div>
        </main>
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
                    <input type="hidden" name="save_branding" value="1">
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
                        <button type="submit" class="btn-save" style="background: var(--primary-red);">Guardar
                            Cambios</button>
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
                    para conectar la cuenta maestra.</p>

                <form method="POST" action="">
                    <input type="hidden" name="save_config" value="1">
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
                        <button type="submit" class="btn-save">Guardar Cambios</button>
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
                    <input type="hidden" name="save_templates" value="1">
                    <div class="template-tabs"
                        style="display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #eee;">
                        <button type="button" class="tab-btn active"
                            onclick="showTab('inscripcion')">Inscripción</button>
                        <button type="button" class="tab-btn" onclick="showTab('citacion')">Citación</button>
                        <button type="button" class="tab-btn" onclick="showTab('confirmacion')">Confirmación</button>
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
                        <button type="submit" class="btn-save" style="background: #ffc107; color: #333;">Guardar
                            Plantillas</button>
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
            document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabName).style.display = 'block';
            if (event && event.target) event.target.classList.add('active');
        }

        window.onclick = function (event) {
            if (event.target == modal) closeConfigModal();
            if (event.target == brandingModal) closeBrandingModal();
            if (event.target == templateModal) closeTemplateModal();
        }
    </script>
</body>

</html>