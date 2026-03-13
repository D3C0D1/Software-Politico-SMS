<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';
$registro = null;

// Handle Form Submission
$typeParam = isset($_REQUEST['type']) ? $_REQUEST['type'] : 'votante';

// Access Control for Leaders: Can only manage 'votante'
if (isset($_SESSION['role']) && $_SESSION['role'] === 'lider' && $typeParam !== 'votante') {
    header("Location: registros.php");
    exit;
}
$redirectUrl = ($typeParam === 'lider') ? 'lideres.php' : 'registros.php';
if (isset($_REQUEST['return']) && $_REQUEST['return'] === 'todos') {
    $redirectUrl = 'todos_registros.php';
}
$formTitle = ($typeParam === 'lider') ? 'Líder' : 'Votante';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? $_POST['id'] : '';
    $nombres = $_POST['nombres_apellidos'];
    $cedula = $_POST['cedula'];
    $lugar = $_POST['lugar_votacion'];
    $mesa = $_POST['mesa'];
    $celular = $_POST['celular'];
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : $typeParam;

    // SMS tracking fields
    $sms_inscripcion = isset($_POST['sms_inscripcion']) ? 1 : 0;
    $sms_citacion = isset($_POST['sms_citacion']) ? 1 : 0;
    $sms_confirmacion = isset($_POST['sms_confirmacion']) ? 1 : 0;

    // Validation
    if (empty($nombres) || empty($cedula) || empty($lugar) || empty($mesa) || empty($celular)) {
        $error = "Todos los campos son obligatorios.";
    }
    else {
        try {
            if ($id) {
                // Update - Verify the record belongs to the current user
                $stmt = $pdo->prepare("SELECT user_id, organizacion_id FROM registros WHERE id = ?");
                $stmt->execute([$id]);
                $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$existingRecord) {
                    $error = "Registro no encontrado.";
                }
                elseif ($existingRecord['organizacion_id'] != ($_SESSION['organizacion_id'] ?? 1) && $_SESSION['role'] !== 'superadmin') {
                    $error = "No tiene permisos para editar este registro. Pertenece a otra organización.";
                }
                elseif ($existingRecord['user_id'] != $_SESSION['user_id'] && !in_array($_SESSION['role'], ['admin', 'operador', 'superadmin'])) {
                    $error = "No tiene permisos para editar este registro. Este votante pertenece a otro líder.";
                }
                else {
                    // Check if cedula is being changed and if it already exists for another voter IN THE SAME ORG
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE cedula = ? AND id != ? AND organizacion_id = ?");
                    $stmt->execute([$cedula, $id, $_SESSION['organizacion_id'] ?? 1]);
                    if ($stmt->fetchColumn() > 0) {
                        $error = "La cédula ya está registrada para otro votante.";
                    }
                    else {
                        // Update - DO NOT change user_id to maintain ownership
                        $stmt = $pdo->prepare("UPDATE registros SET nombres_apellidos = ?, cedula = ?, lugar_votacion = ?, mesa = ?, celular = ?, tipo = ?, sms_inscripcion = ?, sms_citacion = ?, sms_confirmacion = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$nombres, $cedula, $lugar, $mesa, $celular, $tipo, $sms_inscripcion, $sms_citacion, $sms_confirmacion, $id, $_SESSION['user_id']]);

                        // Log Update
                        logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'] ?? 1, 'update_voter', "Actualizó votante ID $id: $nombres");

                        // Redirect with success message
                        header("Location: " . $redirectUrl . "?msg=editado");
                        exit;
                    }
                }
            }
            else {
                // Insert - Check if cedula already exists WITHIN the same organization
                $stmt = $pdo->prepare("SELECT user_id, nombres_apellidos FROM registros WHERE cedula = ? AND organizacion_id = ?");
                $stmt->execute([$cedula, $_SESSION['organizacion_id'] ?? 1]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // Get the leader's name who owns this voter
                    $stmtLeader = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmtLeader->execute([$existing['user_id']]);
                    $leader = $stmtLeader->fetch(PDO::FETCH_ASSOC);

                    // Specific message format requested
                    $error = "La cédula ya está registrada. Este votante (" . htmlspecialchars($existing['nombres_apellidos']) . ") pertenece al líder: " . htmlspecialchars($leader['username'] ?? 'Desconocido');
                }
                else {
                    // Insert new voter assigned to current user AND current organization
                    $stmt = $pdo->prepare("INSERT INTO registros (nombres_apellidos, cedula, lugar_votacion, mesa, celular, tipo, user_id, organizacion_id, sms_inscripcion, sms_citacion, sms_confirmacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nombres, $cedula, $lugar, $mesa, $celular, $tipo, $_SESSION['user_id'], $_SESSION['organizacion_id'], $sms_inscripcion, $sms_citacion, $sms_confirmacion]);

                    // Get the inserted ID
                    $insertedId = $pdo->lastInsertId();

                    // Log Creation
                    logSystemAction($pdo, $_SESSION['user_id'], $_SESSION['organizacion_id'] ?? 1, 'create_voter_form', "Creó votante desde formulario: $nombres (CC: $cedula)");

                    // --- AUTO-CREATE USER ACCOUNT FOR LEADERS ---
                    if ($tipo === 'lider') {
                        // Check if user account already exists for this cedula
                        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                        $stmtCheck->execute([$cedula]);

                        if ($stmtCheck->fetchColumn() == 0) {
                            // Create user with username = cedula and password = cedula
                            $defaultPass = password_hash($cedula, PASSWORD_DEFAULT);
                            $stmtUser = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, 'lider', ?)");
                            $stmtUser->execute([$cedula, $defaultPass, $nombres, $_SESSION['organizacion_id']]);
                        }
                    }

                    // --- SEND AUTOMATIC SMS FOR VOTERS ---
                    if ($tipo === 'votante' && !empty($celular)) {
                        // Generate confirmation link (MATCHING confirmar_voto.php LOGIC)
                        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $scriptPath = dirname($_SERVER['PHP_SELF']);
                        $baseUrl = rtrim("$protocol://$host$scriptPath", '/');

                        // FIX: Use MD5 hash as expected by confirmation page
                        $token = md5($insertedId . $cedula . 'voto2026');
                        $confirmLink = "$baseUrl/confirmar_voto.php?id=$insertedId&token=$token";

                        // Get API credentials
                        $apiClient = null;
                        $apiKey = null;

                        $stmtCreds = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE id = ?");
                        $stmtCreds->execute([$_SESSION['user_id']]);
                        $userCreds = $stmtCreds->fetch(PDO::FETCH_ASSOC);

                        if ($userCreds && !empty($userCreds['onurix_client']) && !empty($userCreds['onurix_key'])) {
                            $apiClient = $userCreds['onurix_client'];
                            $apiKey = $userCreds['onurix_key'];
                        }
                        else {
                            $stmtAdmin = $pdo->prepare("SELECT onurix_client, onurix_key FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
                            $stmtAdmin->execute();
                            $adminCreds = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

                            if ($adminCreds && !empty($adminCreds['onurix_client']) && !empty($adminCreds['onurix_key'])) {
                                $apiClient = $adminCreds['onurix_client'];
                                $apiKey = $adminCreds['onurix_key'];
                            }
                        }

                        // Send SMS if credentials are available
                        if ($apiClient && $apiKey) {
                            // Fetch Template for INSCRIPCION
                            $smsMessage = "Bienvenido a la campaña! Gracias por su apoyo."; // Fallback

                            $stmtTpl = $pdo->prepare("SELECT content FROM sms_templates WHERE name = 'inscripcion' AND organizacion_id = ?");
                            $stmtTpl->execute([$_SESSION['organizacion_id']]);
                            $tpl = $stmtTpl->fetch(PDO::FETCH_ASSOC);

                            if ($tpl && !empty($tpl['content'])) {
                                $smsMessage = $tpl['content'];
                                // Replace variables
                                $smsMessage = str_replace('{NOMBRE}', $nombres, $smsMessage);
                                $smsMessage = str_replace('{CEDULA}', $cedula, $smsMessage);
                                $smsMessage = str_replace('{LINK_CONFIRMACION}', $confirmLink, $smsMessage);
                            }

                            $smsUrl = "https://www.onurix.com/api/v1/send-sms";
                            $postData = [
                                'client' => $apiClient,
                                'key' => $apiKey,
                                'phone' => $celular,
                                'sms' => $smsMessage,
                                'country-code' => 'CO'
                            ];

                            $ch = curl_init();
                            curl_setopt($ch, CURLOPT_URL, $smsUrl);
                            curl_setopt($ch, CURLOPT_POST, 1);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                            // Fix for local environments (SSL certificate issues)
                            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

                            $smsResponse = curl_exec($ch);
                            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                            if (!curl_errno($ch) && $httpCode == 200) {
                                // Mark as inscripcion sent
                                $stmtUpdateSMS = $pdo->prepare("UPDATE registros SET sms_inscripcion = 1 WHERE id = ?");
                                $stmtUpdateSMS->execute([$insertedId]);
                            }

                            curl_close($ch);
                        }
                    }

                    // Redirect based on type with success message
                    header("Location: " . $redirectUrl . "?msg=creado");
                    exit;
                }
            }
        }
        catch (PDOException $e) {
            $error = "Error en la base de datos: " . $e->getMessage();
        }
    }
}

// Fetch for Edit
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM registros WHERE id = ? AND organizacion_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['organizacion_id'] ?? 1]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registro) {
        $error = "Registro no encontrado o no pertenece a su organización.";
    }
    elseif ($registro['user_id'] != $_SESSION['user_id'] && !in_array($_SESSION['role'], ['admin', 'operador', 'superadmin'])) {
        // Prevent editing records that don't belong to this user
        $error = "No tiene permisos para editar este registro. Este votante pertenece a otro líder.";
        $registro = null; // Clear the record
    }
    else {
        // If editing, use the record's type
        if (isset($registro['tipo'])) {
            $typeParam = $registro['tipo'];
            $redirectUrl = ($typeParam === 'lider') ? 'lideres.php' : 'registros.php';
            $formTitle = ($typeParam === 'lider') ? 'Líder' : 'Votante';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $registro ? 'Editar ' . $formTitle : 'Nuevo ' . $formTitle; ?> - Partido Liberal
    </title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="dashboard-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2 class="page-title">
                <?php echo $registro ? 'Editar ' . $formTitle : 'Nuevo ' . $formTitle; ?>
            </h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </strong></span>
                <a href="<?php echo $redirectUrl; ?>" class="btn-logout"><i class="fas fa-arrow-left"></i> Volver</a>
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
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Registro',
                        text: '<?php echo str_replace("'", "\'", $error); ?>',
                        confirmButtonColor: '#E30613',
                        position: 'top'
                    });
                });
            </script>
            <?php
endif; ?>

            <div class="card" style="max-width: 600px; margin: 0 auto; border-top: 4px solid var(--primary-red);">
                <form method="POST" action="">
                    <input type="hidden" name="tipo" value="<?php echo htmlspecialchars($typeParam); ?>">
                    <?php if (isset($_REQUEST['return'])): ?>
                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($_REQUEST['return']); ?>">
                    <?php
endif; ?>
                    <?php if ($registro): ?>
                    <input type="hidden" name="id" value="<?php echo $registro['id']; ?>">
                    <?php
endif; ?>

                    <?php if ($registro): ?>
                    <div class="form-group">
                        <label>Número (ID)</label>
                        <input type="text" value="<?php echo $registro['id']; ?>" class="form-control" disabled>
                    </div>
                    <?php
endif; ?>

                    <div class="form-group">
                        <label for="nombres">Nombres y Apellidos</label>
                        <input type="text" id="nombres" name="nombres_apellidos" class="form-control" required
                            value="<?php echo $registro ? htmlspecialchars($registro['nombres_apellidos']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="cedula">Cédula</label>
                        <input type="number" id="cedula" name="cedula" class="form-control" required
                            value="<?php echo $registro ? htmlspecialchars($registro['cedula']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="lugar">Lugar de Votación</label>
                        <input type="text" id="lugar" name="lugar_votacion" class="form-control" required
                            value="<?php echo $registro ? htmlspecialchars($registro['lugar_votacion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="mesa">Mesa</label>
                        <input type="text" id="mesa" name="mesa" class="form-control" required
                            value="<?php echo $registro ? htmlspecialchars($registro['mesa']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="celular">Celular</label>
                        <input type="tel" id="celular" name="celular" class="form-control" required pattern="[0-9]{10}"
                            title="Debe ser un número de 10 dígitos"
                            value="<?php echo $registro ? htmlspecialchars($registro['celular']) : ''; ?>">
                    </div>

                    <div class="form-group" style="display: none;">
                        <label
                            style="display: block; margin-bottom: 10px; font-weight: bold; color: var(--primary-red);">
                            <i class="fas fa-sms"></i> Seguimiento de SMS
                        </label>

                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="sms_inscripcion" value="1" <?php echo ($registro &&
    isset($registro['sms_inscripcion']) && $registro['sms_inscripcion'] == 1) ? 'checked'
    : ''; ?> style="margin-right: 10px; width: 18px; height: 18px;">
                                <span>SMS de Inscripción Enviado</span>
                            </label>

                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="sms_citacion" value="1" <?php echo ($registro &&
    isset($registro['sms_citacion']) && $registro['sms_citacion'] == 1) ? 'checked' : '';
?> style="margin-right: 10px; width: 18px; height: 18px;">
                                <span>SMS de Citación Enviado</span>
                            </label>

                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="sms_confirmacion" value="1" <?php echo ($registro &&
    isset($registro['sms_confirmacion']) && $registro['sms_confirmacion'] == 1)
    ? 'checked' : ''; ?> style="margin-right: 10px; width: 18px; height: 18px;">
                                <span>SMS de Confirmación de Votación Enviado</span>
                            </label>
                        </div>
                    </div>

                    <div style="text-align: right; margin-top: 20px;">
                        <a href="<?php echo $redirectUrl; ?>" class="btn"
                            style="background: #ccc; margin-right: 10px;">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Guardar Registro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>

</html>