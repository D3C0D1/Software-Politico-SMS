<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = isset($_POST['client_id']) ? trim($_POST['client_id']) : '';
    $api_key = isset($_POST['api_key']) ? trim($_POST['api_key']) : '';

    if (empty($client_id) || empty($api_key)) {
        echo json_encode(['success' => false, 'message' => 'Client ID y API Key son requeridos']);
        exit;
    }

    // Test connection by getting balance
    $url = 'https://www.onurix.com/api/v1/balance';
    $data = [
        'client' => $client_id,
        'key' => $api_key
    ];

    // Build URL with query parameters for GET request
    $url_with_params = $url . '?' . http_build_query($data);

    $ch = curl_init($url_with_params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For testing
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPGET, true); // Explicitly set GET method

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode([
            'success' => false,
            'message' => 'Error de cURL: ' . $curlError
        ]);
        exit;
    }

    $responseData = json_decode($response, true);

    // Check if successful - API returns status as integer 1 or string '1'
    if ($httpCode == 200 && isset($responseData['status']) && ($responseData['status'] === 1 || $responseData['status'] === '1')) {
        $balance = isset($responseData['balance']) ? $responseData['balance'] : 'N/A';
        echo json_encode([
            'success' => true,
            'message' => 'Saldo disponible: ' . $balance . ' SMS'
        ]);
    }
    else {
        $errorMsg = 'HTTP Code: ' . $httpCode;
        if (isset($responseData['message'])) {
            $errorMsg .= '<br>Mensaje: ' . htmlspecialchars($responseData['message']);
        }
        if (isset($responseData['error'])) {
            $errorMsg .= '<br>Error: ' . htmlspecialchars($responseData['error']);
        }
        $errorMsg .= '<br>Respuesta completa: ' . htmlspecialchars($response);

        echo json_encode([
            'success' => false,
            'message' => $errorMsg
        ]);
    }
}
else {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
}
?>
