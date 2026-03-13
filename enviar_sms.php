<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control: Only Admin and Lider
if (!in_array($_SESSION['role'], ['admin', 'lider'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
$type = $_REQUEST['type'] ?? '';
$phone = $_REQUEST['phone'] ?? '';

// Definir a qué página volver basado en el rol de forma inteligente
$return_page = 'registros.php';
$role = $_SESSION['role'] ?? '';
if ($role === 'admin' || $role === 'superadmin') {
    $return_page = 'tus_votantes.php'; // Admine nunca va a registros.php
    if ($type === 'all') {
        $return_page = 'todos_registros.php';
    }
}
if ($type === 'lider' || (isset($_REQUEST['target']) && $_REQUEST['target'] === 'lider')) {
    $return_page = 'lideres.php';
}

// Get user settings for API Key
$user_id = $_SESSION['user_id'];

// --- FORZADO DE CREDENCIALES (MODO DEBUG) ---
$client = '7389';
$key = 'baf0076e7d995fc544c21cea4fdf898ce00612f268dc5f38c3565';

$settings = [
    'onurix_client' => $client,
    'onurix_key' => $key
];

if (empty($settings['onurix_client']) || empty($settings['onurix_key'])) {
    $error = "Error [X100]: Credenciales vacías incluso hardcodeadas.";
}

// Load SMS templates from database (Organization scoped)
$smsTemplates = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM sms_templates WHERE organizacion_id = ? ORDER BY id ASC");
    $stmt->execute([$_SESSION['organizacion_id']]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($templates as $template) {
        $smsTemplates[$template['name']] = $template['content'];
    }
}
catch (PDOException $e) {
// Ignore error
}

// --- 1. Fetch Organization Name ---
$orgName = 'el Partido Liberal'; // Default Fallback

try {
    $stmtOrg = $pdo->prepare("SELECT nombre_organizacion FROM organizaciones WHERE id = ?");
    $stmtOrg->execute([$_SESSION['organizacion_id']]);
    $orgDB = $stmtOrg->fetchColumn();

    if ($orgDB) {
        $orgName = $orgDB;
    }
    else {
        $stmtConfig = $pdo->prepare("SELECT setting_value FROM app_config WHERE setting_key = 'app_title' AND organizacion_id = ?");
        $stmtConfig->execute([$_SESSION['organizacion_id']]);
        $titleDB = $stmtConfig->fetchColumn();
        if ($titleDB)
            $orgName = $titleDB;
    }
}
catch (Exception $e) {
// Keep default
}

// --- 2. Define Standard Defaults ---
$defaultInscripcion = 'Gracias por inscribirte y hacer parte del cambio';
$defaultCitacion = 'Recuerda votar con ' . $orgName . '. Tu puesto: {LUGAR}, Mesa: {MESA}. Cuenta con nosotros!';
$defaultConfirmacion = 'Confirma tu voto aquí: {LINK_CONFIRMACION}';

// --- 3. Enforcement & Initialization Logic ---

function saveTemplate($pdo, $name, $label, $content, $orgId)
{
    try {
        $pdo->prepare("INSERT INTO sms_templates (name, label, content, organizacion_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE content = ?")
            ->execute([$name, $label, $content, $orgId, $content]);
    }
    catch (Exception $e) {
    }
}

// Check and Initialize 'inscripcion'
if (empty($smsTemplates['inscripcion']) || stripos($smsTemplates['inscripcion'], '{NOMBRE}') !== false || stripos($smsTemplates['inscripcion'], 'Hola') !== false) {
    $smsTemplates['inscripcion'] = $defaultInscripcion;
    saveTemplate($pdo, 'inscripcion', 'SMS de Inscripción', $defaultInscripcion, $_SESSION['organizacion_id']);
}

// Check and Initialize 'citacion'
// Solo reiniciar si está vacío o no tiene las variables {MESA}/{LUGAR}
if (empty($smsTemplates['citacion']) || (stripos($smsTemplates['citacion'], '{MESA}') === false && stripos($smsTemplates['citacion'], '{LUGAR}') === false)) {
    $smsTemplates['citacion'] = $defaultCitacion;
    saveTemplate($pdo, 'citacion', 'SMS de Citación', $defaultCitacion, $_SESSION['organizacion_id']);
}

// Check and Initialize 'confirmacion'
if (empty($smsTemplates['confirmacion'])) {
    $smsTemplates['confirmacion'] = $defaultConfirmacion;
    saveTemplate($pdo, 'confirmacion', 'SMS de Confirmación', $defaultConfirmacion, $_SESSION['organizacion_id']);
}

// Handle Sending
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $smsContent = $_POST['message'] ?? '';
    $targetPhone = $_POST['phone'] ?? '';
    $targetType = $_POST['type'] ?? '';
    $templateId = $_POST['template_id'] ?? '';
    $isAjax = isset($_POST['ajax']);

    $response = ['status' => 'error', 'message' => 'Error desconocido'];

    if (empty($smsContent)) {
        $response['message'] = "El mensaje no puede estar vacío.";
        if (!$isAjax)
            $error = $response['message'];
    }
    elseif (empty($settings['onurix_client']) || empty($settings['onurix_key'])) {
        $response['message'] = "Faltan credenciales de API.";
        if (!$isAjax)
            $error = $response['message'];
    }
    else {
        $client = $settings['onurix_client'];
        $key = $settings['onurix_key'];

        $recipientsData = [];

        if ($targetType === 'all' || $targetType === 'pending' || $targetType === 'lider') {
            $sql = "SELECT id, cedula, celular, nombres_apellidos, mesa, lugar_votacion FROM registros WHERE celular IS NOT NULL AND celular != '' AND organizacion_id = ?";
            $params = [$_SESSION['organizacion_id']];

            if ($targetType === 'pending') {
                $sql .= " AND (ya_voto IS NULL OR ya_voto = 0)";
            }
            elseif ($targetType === 'lider') {
                $sql .= " AND tipo = 'lider'";
            }

            if ($_SESSION['role'] !== 'admin') {
                $sql .= " AND user_id = ?";
                $params[] = $_SESSION['user_id'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $recipientsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif ($targetPhone) {
            $recipientId = $_POST['recipient_id'] ?? '';
            $foundUser = false;

            if (!empty($recipientId)) {
                $stmt = $pdo->prepare("SELECT id, cedula, celular, nombres_apellidos, mesa, lugar_votacion FROM registros WHERE id = ? LIMIT 1");
                $stmt->execute([$recipientId]);
                $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$foundUser) {
                $stmt = $pdo->prepare("SELECT id, cedula, celular, nombres_apellidos, mesa, lugar_votacion FROM registros WHERE celular LIKE ? OR celular LIKE ? LIMIT 1");
                $cleanTarget = preg_replace('/[^0-9]/', '', $targetPhone);
                $stmt->execute(['%' . $cleanTarget . '%', $targetPhone]);
                $foundUser = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            if ($foundUser) {
                $recipientsData[] = $foundUser;
            }
            else {
                $recipientsData[] = ['celular' => $targetPhone, 'id' => null, 'cedula' => null, 'mesa' => '', 'lugar_votacion' => ''];
            }
        }

        if (empty($recipientsData)) {
            $response['message'] = "No hay destinatarios válidos.";
            if (!$isAjax)
                $error = $response['message'];
        }
        else {
            $successCount = 0;
            $failCount = 0;
            $debugInfo = [];
            $validTemplates = ['inscripcion', 'citacion', 'confirmacion'];

            foreach ($recipientsData as $userData) {
                $recipientPhone = $userData['celular'];
                $cleanPhone = preg_replace('/[^0-9]/', '', $recipientPhone);

                // Evitar números falsos de prueba que empiezan por 3000 (como 3000000000, 3000000001, etc.)
                if (empty($cleanPhone) || strpos($cleanPhone, '3000') === 0) {
                    $failCount++;
                    $debugInfo[] = ['phone' => $recipientPhone, 'error' => 'Número de celular falso omitido (Inicia con 3000)'];
                    continue; // Omite este registro y pasa al siguiente
                }

                $finalMessage = $smsContent;

                // Reemplazar variables individuales de mesa y lugar
                $mesaVotante = !empty($userData['mesa']) ? $userData['mesa'] : 'N/A';
                $lugarVotante = !empty($userData['lugar_votacion']) ? $userData['lugar_votacion'] : 'N/A';
                $nombreVotante = !empty($userData['nombres_apellidos']) ? explode(' ', trim($userData['nombres_apellidos']))[0] : '';

                $finalMessage = str_replace('{MESA}', $mesaVotante, $finalMessage);
                $finalMessage = str_replace('{LUGAR}', $lugarVotante, $finalMessage);
                $finalMessage = str_replace('{NOMBRE}', $nombreVotante, $finalMessage);

                if (strpos($finalMessage, '{LINK_CONFIRMACION}') !== false) {
                    if ($userData['id'] && $userData['cedula']) {
                        $token = md5($userData['id'] . $userData['cedula'] . 'voto2026');
                        $uniqueLink = "https://glamcity.store/partido/confirmar_voto.php?id=" . $userData['id'] . "&token=" . $token;
                        $finalMessage = str_replace('{LINK_CONFIRMACION}', $uniqueLink, $finalMessage);
                    }
                    else {
                        $finalMessage = str_replace('{LINK_CONFIRMACION}', 'https://glamcity.store/partido/', $finalMessage);
                    }
                }

                $url = 'https://www.onurix.com/api/v1/send-sms';
                $data = [
                    'client' => $client,
                    'key' => $key,
                    'phone' => $cleanPhone,
                    'sms' => $finalMessage,
                    'country-code' => 'CO'
                ];

                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);

                $curlResponse = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $responseData = json_decode($curlResponse, true);

                if ($httpCode == 200 && isset($responseData['status']) && ($responseData['status'] === 1 || $responseData['status'] === '1')) {
                    $successCount++;
                    if (in_array($templateId, $validTemplates) && $userData['id']) {
                        try {
                            $col = "sms_" . $templateId;
                            $pdo->prepare("UPDATE registros SET $col = 1 WHERE id = ?")->execute([$userData['id']]);
                        }
                        catch (PDOException $e) {
                            if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
                                try {
                                    $pdo->exec("ALTER TABLE registros ADD COLUMN $col TINYINT DEFAULT 0");
                                    $pdo->prepare("UPDATE registros SET $col = 1 WHERE id = ?")->execute([$userData['id']]);
                                }
                                catch (Exception $ex) {
                                }
                            }
                        }
                    }
                }
                else {
                    $failCount++;
                    $debugInfo[] = ['phone' => $recipientPhone, 'http_code' => $httpCode, 'response' => $curlResponse];
                }

                try {
                    $logStatus = ($httpCode == 200 && isset($responseData['status']) && ($responseData['status'] === 1 || $responseData['status'] === '1')) ? 'success' : 'failed';
                    $stmtLog = $pdo->prepare("INSERT INTO sms_logs (organizacion_id, user_id, recipient_phone, message, status, response_data) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtLog->execute([$_SESSION['organizacion_id'], $_SESSION['user_id'], $cleanPhone, $finalMessage, $logStatus, json_encode($responseData)]);
                }
                catch (Exception $e) {
                }
            }

            if ($successCount > 0 && $failCount == 0) {
                $response['status'] = 'success';
                $response['message'] = 'Ok listo realizado. Se enviaron ' . $successCount . ' mensajes correctamente.';
                if (!$isAjax) {
                    $alertType = 'success';
                    $alertTitle = '¡Éxito!';
                    $alertMessage = $response['message'];
                }
            }
            elseif ($failCount > 0) {
                $response['status'] = 'error';
                $response['message'] = 'Hubo problemas al enviar algunos mensajes.';
                if (!$isAjax) {
                    $alertType = 'error';
                    $alertTitle = 'Error en el envío';
                    $alertMessage = $response['message'];
                }
            }
        }
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar SMS - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="dashboard-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2 class="page-title">Enviar SMS</h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['name']); ?>
                    </strong></span>
                <a href="javascript:history.back()" class="btn-logout"><i class="fas fa-arrow-left"></i> Volver</a>
            </div>
        </div>

        <div class="content-area">
            <?php if ($message): ?>
            <div
                style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <?php echo $message; ?>
            </div>
            <?php
endif; ?>

            <?php if ($error): ?>
            <div class="error-msg">
                <?php echo $error; ?>
            </div>
            <?php
endif; ?>

            <?php
$name = $_REQUEST['name'] ?? '';
$recipientId = $_REQUEST['id'] ?? '';
?>

            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <h3>
                    <?php
if ($type === 'all')
    echo 'Enviar a TODOS los Registros (Masivo)';
elseif ($type === 'pending')
    echo 'Enviar a PENDIENTES (Recordatorio)';
elseif ($type === 'lider')
    echo 'Enviar a LÍDERES (Coordinación)';
else
    echo 'Enviar Mensaje a: ' . ($name ? htmlspecialchars($name) : 'Individual');
?>
                </h3>

                <form method="POST" action="">
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                    <input type="hidden" name="template_id" id="template_id" value="">

                    <?php if ($type !== 'all' && $type !== 'pending' && $type !== 'lider'): ?>
                    <div class="form-group">
                        <label>Destinatario:</label>
                        <div
                            style="background: #f8f9fa; padding: 10px; border-radius: 5px; border: 1px solid #ddd; display: flex; align-items: center;">
                            <i class="fas fa-user" style="color: #666; margin-right: 10px;"></i>
                            <strong style="margin-right: 15px;">
                                <?php echo htmlspecialchars($name ?: 'Usuario'); ?>
                            </strong>
                            <i class="fas fa-mobile-alt" style="color: #666; margin-right: 5px;"></i>
                            <span>
                                <?php echo htmlspecialchars($phone); ?>
                            </span>
                        </div>
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                        <input type="hidden" name="recipient_id" value="<?php echo htmlspecialchars($recipientId); ?>">
                    </div>
                    <?php
else: ?>
                    <p><strong>Destinatarios:</strong> Todos los registros con número de celular válido.</p>
                    <?php
endif; ?>

                    <div class="form-group">
                        <label>Seleccionar Plantilla Predefinida:</label>
                        <div style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                            <button type="button" class="btn-template" onclick="applyTemplate('inscripcion')">
                                <i class="fas fa-user-plus"></i> Inscripción (Bienvenida)
                            </button>

                            <button type="button" class="btn-template" onclick="applyTemplate('citacion')">
                                <i class="fas fa-calendar-alt"></i> Citación (Día de Votación)
                            </button>

                            <button type="button" class="btn-template" onclick="applyTemplate('confirmacion')">
                                <i class="fas fa-check-circle"></i> Confirmación (Check-in)
                            </button>
                        </div>
                        <style>
                            .btn-template {
                                background: #f8f9fa;
                                border: 1px solid #ddd;
                                padding: 8px 15px;
                                border-radius: 20px;
                                cursor: pointer;
                                transition: all 0.2s;
                                color: #555;
                                font-size: 0.9rem;
                                display: flex;
                                align-items: center;
                                gap: 6px;
                            }

                            .btn-template:hover {
                                background: #e9ecef;
                                color: #333;
                                border-color: #ccc;
                            }

                            .btn-template i {
                                color: var(--primary-red);
                            }
                        </style>
                    </div>

                    <div class="form-group">
                        <label for="message">Mensaje SMS</label>
                        <textarea id="message" name="message" class="form-control" rows="4" maxlength="160" required
                            placeholder="Escriba su mensaje aquí (máx 160 caracteres)..."></textarea>
                        <small id="charCount" style="color: #666; float: right;">0/160</small>
                    </div>
                    <div style="clear: both;"></div>

                    <div style="text-align: right; margin-top: 20px;">
                        <a href="javascript:history.back()" class="btn"
                            style="background: #ccc; margin-right: 10px;">Cancelar</a>
                        <button type="submit" class="btn btn-secondary">Enviar SMS</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Store templates safely in JS
        const smsTemplates = {
            'inscripcion': <?php echo json_encode($smsTemplates['inscripcion'] ?? $defaultInscripcion); ?>,
                'citacion': <?php echo json_encode($smsTemplates['citacion'] ?? $defaultCitacion); ?>,
                    'confirmacion': <?php echo json_encode($smsTemplates['confirmacion'] ?? $defaultConfirmacion); ?>
        };

        const messageBox = document.getElementById('message');
        const counter = document.getElementById('charCount');
        const form = document.querySelector('form');

        function applyTemplate(type) {
            const text = smsTemplates[type];
            if (text) {
                messageBox.value = text;
                document.getElementById('template_id').value = type;
                updateCounter();

                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000,
                    timerProgressBar: true
                });
                Toast.fire({ icon: 'success', title: 'Plantilla cargada' });
            }
        }

        function updateCounter() {
            const currentLength = messageBox.value.length;
            counter.textContent = currentLength + '/160';
            counter.style.color = currentLength > 160 ? 'red' : '#666';
        }

        if (messageBox) {
            messageBox.addEventListener('input', updateCounter);
        }

        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(form);
                formData.append('ajax', '1');
                let apiResponse = null;

                // 60-second timer for UX
                let timerInterval;
                Swal.fire({
                    title: 'Enviando mensajes...',
                    html: 'Por favor espere <b></b> segundos.',
                    timer: 60000,
                    timerProgressBar: true,
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                        const b = Swal.getHtmlContainer().querySelector('b');
                        timerInterval = setInterval(() => {
                            b.textContent = Math.ceil(Swal.getTimerLeft() / 1000);
                        }, 100);

                        // Start AJAX Request only when alert opens
                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                apiResponse = data;
                                // Close popup faster if done
                                Swal.close();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                apiResponse = { status: 'error', message: 'Error de conexión' };
                                Swal.close();
                            });
                    },
                    willClose: () => {
                        clearInterval(timerInterval);
                    }
                }).then((result) => {
                    // This runs when Swal.close() is called or timer ends
                    if (apiResponse && apiResponse.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: apiResponse.message,
                            confirmButtonColor: '#E30613'
                        }).then(() => {
                            window.history.back();
                        });
                    } else if (apiResponse && apiResponse.status === 'error') {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: apiResponse.message,
                            confirmButtonColor: '#E30613'
                        });
                    } else if (!apiResponse) {
                        // If closed by timer but no response yet (rare if timeout matches)
                        Swal.fire({
                            icon: 'info',
                            title: 'Procesando',
                            text: 'La operación está tardando más de lo esperado. Verifique en unos minutos.',
                        });
                    }
                });
            });
        }

        <?php if (isset($alertType)): ?>
            Swal.fire({
                icon: '<?php echo $alertType; ?>',
                title: '<?php echo $alertTitle; ?>',
                text: '<?php echo $alertMessage; ?>',
                confirmButtonColor: '#E30613'
            });
        <?php
endif; ?>
    </script>
</body>

</html>