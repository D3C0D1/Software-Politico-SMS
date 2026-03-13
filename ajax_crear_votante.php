<?php
// ajax_crear_votante.php - Handles creation of new "votante" with SMS option
// Disable error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Sesión expirada.');
    }

    // Role Check: Only Leaders and Admins can create voters
    if (!in_array($_SESSION['role'], ['lider', 'leader', 'admin', 'superadmin'])) {
        throw new Exception('Permiso denegado. Solo roles autorizados pueden registrar votantes.');
    }

    // POST data
    $nombres = trim($_POST['nombres_apellidos'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $lugar = trim($_POST['lugar_votacion'] ?? '');
    $mesa = trim($_POST['mesa'] ?? '');
    $celular = trim($_POST['celular'] ?? '');
    // Check explicitly sent "true" or checkbox presence defined
    $enviar_sms = (isset($_POST['send_sms']) && ($_POST['send_sms'] === 'true' || $_POST['send_sms'] === '1'));

    // Basic Validation
    if (empty($nombres) || empty($cedula) || empty($lugar) || empty($mesa) || empty($celular)) {
        throw new Exception('Todos los campos son obligatorios.');
    }

    // 1. Check if Cedula exists WITHIN the same organization
    $stmt = $pdo->prepare("SELECT user_id, nombres_apellidos FROM registros WHERE cedula = ? AND organizacion_id = ?");
    $stmt->execute([$cedula, $_SESSION['organizacion_id']]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Get the leader's name
        $stmtLeader = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmtLeader->execute([$existing['user_id']]);
        $leader = $stmtLeader->fetch(PDO::FETCH_ASSOC);

        $msg = "La cédula ya está registrada. Este votante (" . htmlspecialchars($existing['nombres_apellidos']) . ") pertenece al líder: " . htmlspecialchars($leader['username'] ?? 'Desconocido');
        throw new Exception($msg);
    }

    // 2. Insert Voter - Removed created_at to use DB default, added organizacion_id
    $stmtInsert = $pdo->prepare("INSERT INTO registros (nombres_apellidos, cedula, lugar_votacion, mesa, celular, tipo, user_id, organizacion_id, sms_inscripcion, sms_citacion, sms_confirmacion) VALUES (?, ?, ?, ?, ?, 'votante', ?, ?, 0, 0, 0)");
    $stmtInsert->execute([$nombres, $cedula, $lugar, $mesa, $celular, $_SESSION['user_id'], $_SESSION['organizacion_id']]);
    $insertedId = $pdo->lastInsertId();

    // 3. Handle SMS
    $smsSent = false;
    $smsError = '';

    if ($enviar_sms && !empty($celular)) {
        // Get Credentials
        $apiClient = null;
        $apiKey = null;

        // Try user settings
        $stmtCreds = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE id = ?");
        $stmtCreds->execute([$_SESSION['user_id']]);
        $userCreds = $stmtCreds->fetch(PDO::FETCH_ASSOC);

        if ($userCreds && !empty($userCreds['onurix_client']) && !empty($userCreds['onurix_key'])) {
            $apiClient = $userCreds['onurix_client'];
            $apiKey = $userCreds['onurix_key'];
        }
        else {
            // Fallback to admin
            $stmtAdmin = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
            $stmtAdmin->execute();
            $adminCreds = $stmtAdmin->fetch(PDO::FETCH_ASSOC);
            if ($adminCreds && !empty($adminCreds['onurix_client']) && !empty($adminCreds['onurix_key'])) {
                $apiClient = $adminCreds['onurix_client'];
                $apiKey = $adminCreds['onurix_key'];
            }
        }

        if ($apiClient && $apiKey) {
            // Generate Confirm Link
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $scriptPath = dirname($_SERVER['PHP_SELF']);
            $baseUrl = rtrim("$protocol://$host$scriptPath", '/');

            $token = md5($insertedId . $cedula . 'voto2026');
            $confirmLink = "$baseUrl/confirmar_voto.php?id=$insertedId&token=$token";

            // Get 'inscripcion' Template
            $message = "Bienvenido a la campaña! Gracias por su apoyo."; // Default fallback
            $stmtTpl = $pdo->prepare("SELECT content FROM sms_templates WHERE name = 'inscripcion' AND organizacion_id = ?");
            $stmtTpl->execute([$_SESSION['organizacion_id']]);
            $tpl = $stmtTpl->fetch(PDO::FETCH_ASSOC);

            if ($tpl && !empty($tpl['content'])) {
                $message = $tpl['content'];
                $message = str_replace('{NOMBRE}', $nombres, $message);
                $message = str_replace('{CEDULA}', $cedula, $message);
                $message = str_replace('{LINK_CONFIRMACION}', $confirmLink, $message);
            }

            // Send via Onurix
            $url = "https://www.onurix.com/api/v1/send-sms";
            $postData = [
                'client' => $apiClient,
                'key' => $apiKey,
                'phone' => $celular,
                'sms' => $message,
                'country-code' => 'CO'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if (!$curlError && $httpCode == 200) {
                // Parse response to be sure
                $result = json_decode($response, true);
                if (isset($result['status']) && ((string)$result['status'] === '1' || $result['status'] === 1)) {
                    $smsSent = true;
                    // Update DB flag
                    $upd = $pdo->prepare("UPDATE registros SET sms_inscripcion = 1 WHERE id = ?");
                    $upd->execute([$insertedId]);

                    // Log SMS Success
                    logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'], 'send_sms_inscripcion', "SMS enviado a $celular (Votante: $nombres)");
                }
                else {
                    // Log SMS API Failure (Response)
                    logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'], 'sms_failed_api', "Fallo Onurix: " . json_encode($result));
                }
            }
            else {
                // Log SMS error but don't fail voter creation
                $smsError = "Error envío SMS: " . ($curlError ?: "HTTP Code $httpCode");
                logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'], 'sms_failed_curl', $smsError);
            }
        }
    }

    // Log Voter Creation
    logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'], 'create_voter', "Creó votante: $nombres (CC: $cedula)");

    echo json_encode([
        'success' => true,
        'message' => 'Votante creado exitosamente.' . ($smsSent ? ' SMS enviado.' : ''),
        'sms_sent' => $smsSent,
        'debug_sms' => $smsError // For debug
    ]);

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>