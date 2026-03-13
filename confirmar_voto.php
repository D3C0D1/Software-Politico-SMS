<?php
require_once 'config.php';

// Get voter ID from URL
$voterId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

$voter = null;
$error = '';
$success = '';

// ─── 1. Cargar votante y validar token ───────────────────────────────────────
if ($voterId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE id = ?");
        $stmt->execute([$voterId]);
        $voter = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($voter) {
            $expectedToken = md5($voter['id'] . $voter['cedula'] . 'voto2026');
            if ($token !== $expectedToken) {
                $error = "Token inválido.";
                $voter = null;
            }
        }
        else {
            $error = "Votante no encontrado.";
        }
    }
    catch (PDOException $e) {
        $error = "Error al verificar datos.";
    }
}
else {
    $error = "Link inválido.";
}

// ─── 2. Cargar branding de la organización ────────────────────────────────────
// Valores por defecto (fallback al Partido Liberal original)
$orgBranding = [
    'app_title' => 'Partido Liberal',
    'org_name' => 'Partido Liberal Colombiano',
    'logo_path' => 'assets/img/logo.png',
    'primary_color' => '#E30613',
];

if ($voter && !empty($voter['organizacion_id'])) {
    $orgId = (int)$voter['organizacion_id'];

    try {
        // Obtener nombre de la organización
        $stmtOrg = $pdo->prepare("SELECT nombre_organizacion FROM organizaciones WHERE id = ?");
        $stmtOrg->execute([$orgId]);
        $orgRow = $stmtOrg->fetchColumn();
        if ($orgRow) {
            $orgBranding['org_name'] = $orgRow;
        }

        // Obtener configuración de branding (app_config por organización)
        $stmtCfg = $pdo->prepare(
            "SELECT setting_key, setting_value FROM app_config WHERE organizacion_id = ?"
        );
        $stmtCfg->execute([$orgId]);
        while ($row = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
            if (isset($orgBranding[$row['setting_key']])) {
                $orgBranding[$row['setting_key']] = $row['setting_value'];
            }
            // app_title lo usamos como título de pestaña también
            if ($row['setting_key'] === 'app_title') {
                $orgBranding['app_title'] = $row['setting_value'];
            }
        }
    }
    catch (Exception $e) {
    // Mantener fallback silenciosamente
    }
}

// Helper: hex → rgb para usar en rgba() del CSS
function hexToRgb(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "$r, $g, $b";
}

$primaryColor = htmlspecialchars($orgBranding['primary_color']);
$primaryRgb = hexToRgb($orgBranding['primary_color']);
$logoPath = htmlspecialchars($orgBranding['logo_path']);
$orgTitle = htmlspecialchars($orgBranding['app_title']);
$orgFullName = htmlspecialchars($orgBranding['org_name']);

// Generar color oscuro para hover (oscurecer ~20%)
function darkenHex(string $hex, int $percent = 20): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    $r = max(0, hexdec(substr($hex, 0, 2)) - round(255 * $percent / 100));
    $g = max(0, hexdec(substr($hex, 2, 2)) - round(255 * $percent / 100));
    $b = max(0, hexdec(substr($hex, 4, 2)) - round(255 * $percent / 100));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}
$primaryDark = darkenHex($orgBranding['primary_color'], 20);

// ─── 3. Manejar envío del formulario ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $voter) {
    $yaVoto = (isset($_POST['ya_voto']) && $_POST['ya_voto'] == '1') ? 1 : 0;

    try {
        $estado = ($yaVoto == 1) ? 'voto' : 'pendiente';
        $stmt = $pdo->prepare("UPDATE registros SET estado_voto = ?, ya_voto = ? WHERE id = ?");
        $stmt->execute([$estado, $yaVoto, $voterId]);

        $success = ($yaVoto == 1)
            ? "¡Gracias por confirmar tu voto!"
            : "Gracias por responder que AÚN NO has votado.";

        // Refrescar datos del votante
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE id = ?");
        $stmt->execute([$voterId]);
        $voter = $stmt->fetch(PDO::FETCH_ASSOC);

    }
    catch (PDOException $e) {
        $error = "Error al guardar la respuesta.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Voto - <?php echo $orgTitle; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary:      <?php echo $primaryColor; ?>;
            --primary-dark: <?php echo $primaryDark; ?>;
            --primary-rgb:  <?php echo $primaryRgb; ?>;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg,
                    var(--primary) 0%,
                    var(--primary-dark) 60%,
                    rgba(0,0,0,0.85) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Patrón decorativo de fondo */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(0,0,0,0.2) 0%, transparent 50%);
            pointer-events: none;
        }

        body::after {
            content: '';
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            border: 1px solid rgba(255,255,255,0.06);
            top: -150px;
            right: -150px;
            pointer-events: none;
        }

        /* ── Tarjeta principal ── */
        .container {
            background: #fff;
            border-radius: 24px;
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255,255,255,0.1);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            position: relative;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header con branding dinámico ── */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 36px 24px 28px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255,255,255,0.06);
            top: -100px;
            right: -80px;
        }

        .header::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,0.04);
            bottom: -80px;
            left: -60px;
        }

        .logo-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 90px;
            height: 90px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.3);
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
            backdrop-filter: blur(4px);
        }

        .logo-wrapper img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            filter: brightness(0) invert(1) drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .header h1 {
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
            text-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .header p {
            font-size: 0.9rem;
            opacity: 0.85;
            font-weight: 500;
            position: relative;
            z-index: 1;
        }

        /* Divider decorativo */
        .header-divider {
            height: 4px;
            background: linear-gradient(
                90deg,
                transparent 0%,
                rgba(255,255,255,0.5) 50%,
                transparent 100%
            );
        }

        /* ── Contenido ── */
        .content {
            padding: 28px 24px;
        }

        /* ── Alertas ── */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.95rem;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .alert i { font-size: 1.2rem; flex-shrink: 0; }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* ── Info del votante ── */
        .voter-info {
            background: #f8f9fc;
            padding: 18px;
            border-radius: 14px;
            margin-bottom: 24px;
            border-left: 4px solid var(--primary);
        }

        .voter-info h3 {
            color: var(--primary);
            margin-bottom: 12px;
            font-size: 1rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .voter-info .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #555;
            margin: 7px 0;
            font-size: 0.9rem;
        }

        .voter-info .info-row i {
            color: var(--primary);
            width: 16px;
            opacity: 0.7;
        }

        .voter-info strong { color: #222; }

        /* ── Badges de estado ── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.82rem;
            font-weight: 700;
            margin-top: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.voted {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        /* ── Pregunta ── */
        .question {
            text-align: center;
            margin: 20px 0 24px;
        }

        .question h2 {
            color: #1a1a2e;
            font-size: 1.7rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .question p {
            color: #888;
            font-size: 0.9rem;
            font-weight: 400;
        }

        /* ── Botones de voto ── */
        .vote-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 10px;
        }

        .vote-btn {
            padding: 22px 12px;
            border: 2.5px solid #e5e7eb;
            border-radius: 16px;
            background: #fff;
            cursor: pointer;
            transition: all 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: #555;
            letter-spacing: 0.3px;
        }

        .vote-btn i {
            font-size: 2.8rem;
            display: block;
            margin-bottom: 10px;
        }

        .vote-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .vote-btn:active {
            transform: translateY(-1px);
        }

        /* SÍ → color primario de la org */
        .vote-btn.yes {
            border-color: rgba(var(--primary-rgb), 0.3);
            color: var(--primary);
        }

        .vote-btn.yes i { color: var(--primary); }

        .vote-btn.yes:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: 0 8px 24px rgba(var(--primary-rgb), 0.35);
        }

        .vote-btn.yes:hover i { color: #fff; }

        /* NO → naranja/gris neutro */
        .vote-btn.no {
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .vote-btn.no i { color: #ef4444; }

        .vote-btn.no:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.3);
        }

        .vote-btn.no:hover i { color: #fff; }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 16px 20px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            color: #bbb;
            font-size: 0.8rem;
        }

        .footer strong { color: var(--primary); }

        /* ── Error page ── */
        .error-page {
            text-align: center;
            padding: 40px 20px;
        }

        .error-icon {
            font-size: 4rem;
            color: #ef4444;
            margin-bottom: 16px;
            display: block;
        }

        .error-page h2 {
            font-size: 1.4rem;
            color: #333;
            margin-bottom: 8px;
        }

        .error-page p { color: #888; font-size: 0.9rem; }

        /* ── Success overlay ── */
        .success-page {
            text-align: center;
            padding: 32px 20px;
        }

        .success-icon-big {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(var(--primary-rgb), 0.35);
            animation: popIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes popIn {
            from { opacity: 0; transform: scale(0.5); }
            to   { opacity: 1; transform: scale(1); }
        }

        .success-icon-big i { color: #fff; font-size: 2.2rem; }

        .success-page h2 {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }

        .success-page p { color: #666; font-size: 0.95rem; line-height: 1.6; }

        /* ── Responsive ── */
        @media (max-width: 400px) {
            .header { padding: 28px 16px 22px; }
            .content { padding: 20px 16px; }
            .question h2 { font-size: 1.4rem; }
            .vote-btn { padding: 18px 8px; font-size: 0.9rem; }
            .vote-btn i { font-size: 2.2rem; }
        }
    </style>
</head>

<body>
    <div class="container">

        <!-- ── Header Dinámico ── -->
        <div class="header">
            <div class="logo-wrapper">
                <img src="<?php echo $logoPath; ?>" alt="<?php echo $orgTitle; ?>">
            </div>
            <h1>Confirmación de Voto</h1>
            <p><?php echo $orgFullName; ?></p>
        </div>
        <div class="header-divider"></div>

        <!-- ── Contenido Principal ── -->
        <div class="content">

            <?php if ($error): ?>
            <!-- Error: link inválido o token malo -->
            <div class="error-page">
                <i class="fas fa-shield-alt error-icon"></i>
                <h2>Enlace No Válido</h2>
                <p><?php echo htmlspecialchars($error); ?></p>
                <p style="margin-top:8px; font-size:0.8rem;">Si crees que esto es un error, contacta a tu coordinador.</p>
            </div>

            <?php
elseif ($success): ?>
            <!-- Éxito: respuesta registrada -->
            <div class="success-page">
                <?php if (isset($voter['ya_voto']) && $voter['ya_voto'] == 1): ?>
                <div class="success-icon-big"><i class="fas fa-check"></i></div>
                <h2>¡Voto Confirmado!</h2>
                <p><?php echo htmlspecialchars($success); ?><br>
                   Tu participación ha sido registrada exitosamente.</p>
                <?php
    else: ?>
                <div class="success-icon-big" style="background: linear-gradient(135deg,#f59e0b,#d97706);">
                    <i class="fas fa-clock"></i>
                </div>
                <h2>Respuesta Registrada</h2>
                <p><?php echo htmlspecialchars($success); ?><br>
                   ¡Recuerda ejercer tu derecho al voto!</p>
                <?php
    endif; ?>
            </div>

            <?php
elseif ($voter): ?>
            <!-- ── Info del votante ── -->
            <div class="voter-info">
                <h3><i class="fas fa-id-card"></i> Tus datos de votación</h3>
                <div class="info-row">
                    <i class="fas fa-user"></i>
                    <span><strong><?php echo htmlspecialchars($voter['nombres_apellidos']); ?></strong></span>
                </div>
                <div class="info-row">
                    <i class="fas fa-fingerprint"></i>
                    <span>Cédula: <strong><?php echo htmlspecialchars($voter['cedula']); ?></strong></span>
                </div>
                <?php if (!empty($voter['mesa'])): ?>
                <div class="info-row">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Mesa: <strong><?php echo htmlspecialchars($voter['mesa']); ?></strong></span>
                </div>
                <?php
    endif; ?>
                <?php if (!empty($voter['lugar_votacion'])): ?>
                <div class="info-row">
                    <i class="fas fa-building"></i>
                    <span>Puesto: <strong><?php echo htmlspecialchars($voter['lugar_votacion']); ?></strong></span>
                </div>
                <?php
    endif; ?>

                <?php if ($voter['ya_voto'] == 1): ?>
                <span class="status-badge voted">
                    <i class="fas fa-check-circle"></i> Ya confirmó su voto
                </span>
                <?php
    else: ?>
                <span class="status-badge pending">
                    <i class="fas fa-hourglass-half"></i> Pendiente de confirmar
                </span>
                <?php
    endif; ?>
            </div>

            <!-- ── Pregunta de confirmación ── -->
            <div class="question">
                <h2>¿Ya votó hoy?</h2>
                <p>Confirme si ya ejerció su derecho al voto</p>
            </div>

            <!-- ── Formulario ── -->
            <form method="POST" id="voteForm">
                <div class="vote-buttons">
                    <button type="submit" name="ya_voto" value="1" class="vote-btn yes">
                        <i class="fas fa-check-circle"></i>
                        SÍ, YA VOTÉ
                    </button>

                    <button type="submit" name="ya_voto" value="0" class="vote-btn no">
                        <i class="fas fa-times-circle"></i>
                        NO, AÚN NO
                    </button>
                </div>
            </form>
            <?php
endif; ?>

        </div><!-- /content -->

        <!-- ── Footer Dinámico ── -->
        <div class="footer">
            &copy; <?php echo date('Y'); ?> &nbsp;
            <strong><?php echo $orgFullName; ?></strong>
            &nbsp;·&nbsp; Sistema de Confirmación de Voto
        </div>

    </div><!-- /container -->

    <script>
        // Feedback visual al enviar
        const voteForm = document.getElementById('voteForm');
        if (voteForm) {
            voteForm.addEventListener('submit', function (e) {
                const clicked = document.activeElement;
                const btns = document.querySelectorAll('.vote-btn');

                btns.forEach(btn => {
                    if (btn !== clicked) {
                        btn.style.opacity = '0.3';
                        btn.style.pointerEvents = 'none';
                    } else {
                        btn.style.transform = 'scale(0.96)';
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br>Enviando...';
                    }
                });
            });
        }
    </script>
</body>

</html>