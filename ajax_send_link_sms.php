<?php
// ajax_send_link_sms.php - Enviar SMS con link de confirmación
require_once 'config.php';

// Respuesta siempre en JSON
header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no iniciada.']);
    exit;
}

// Leer JSON del cuerpo de la petición
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? '';
$phone = $data['phone'] ?? '';
$link = $data['link'] ?? '';

if (empty($phone) || empty($link)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos (teléfono o enlace).']);
    exit;
}

$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
if (empty($cleanPhone) || strpos($cleanPhone, '3000') === 0) {
    echo json_encode(['success' => false, 'message' => 'El número de celular registrado es inválido/falso (inicia con 3000). Envío ignorado.']);
    exit;
}

// --- FORZADO DE CREDENCIALES (MODO DEBUG) ---
// Ignoramos base de datos y config por ahora para asegurar que funcione en el hosting.
$client = '7389';
$key = 'baf0076e7d995fc544c21cea4fdf898ce00612f268dc5f38c3565';

$settings = [
    'onurix_client' => $client,
    'onurix_key' => $key
];

// Verificación Final (Esto NUNCA debería fallar con los valores arriba)
if (empty($settings['onurix_client']) || empty($settings['onurix_key'])) {
    echo json_encode(['success' => false, 'message' => 'Error [X100]: Credenciales vacías incluso hardcodeadas.']);
    exit;
}

// Construir mensaje (Máx 160 chars recomendados, aunque Onurix soporta más concatenando)
// Link suele ser largo, así que el mensaje debe ser corto.
$message = "Hola! Tu enlace para confirmar el voto: " . $link;

// Enviar SMS mediante API Onurix
$url = "https://www.onurix.com/api/v1/send-sms";
$postData = [
    'client' => $settings['onurix_client'],
    'key' => $settings['onurix_key'],
    'phone' => $phone,
    'sms' => $message,
    'country-code' => 'CO' // Colombia por defecto
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Fix for local environments (SSL certificate issues)
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo json_encode(['success' => false, 'message' => 'Error CURL: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// Interpretar respuesta de Onurix
$result = json_decode($response, true);

// Onurix suele devolver: {"status": "1", "msg": "Mensaje enviado", ...} o {"status": "0", "msg": "Error..."}
if ($httpCode == 200 && isset($result['status']) && (string)$result['status'] === '1') {
    if (!empty($id)) {
        try {
            $pdo->prepare("UPDATE registros SET sms_confirmacion = 1 WHERE id = ?")->execute([$id]);
        }
        catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Unknown column') !== false || $e->getCode() == '42S22') {
                try {
                    $pdo->exec("ALTER TABLE registros ADD COLUMN sms_confirmacion TINYINT DEFAULT 0");
                    $pdo->prepare("UPDATE registros SET sms_confirmacion = 1 WHERE id = ?")->execute([$id]);
                }
                catch (Exception $ex) {
                }
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Enlace enviado correctamente a ' . $phone]);
}
else {
    $errorMsg = $result['msg'] ?? 'Respuesta desconocida del servidor SMS';
    echo json_encode(['success' => false, 'message' => 'Error al enviar SMS: ' . $errorMsg]);
}
?>