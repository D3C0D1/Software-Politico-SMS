<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Cargar configuración del diseño del login desde la BD
$login_config = [
    'login_logo' => 'Logo.webp',
    'login_title' => 'Plataforma Política',
    'login_subtitle' => 'Gestión de Campañas',
    'login_bg_color' => '#000000',
    'login_btn_color' => '#E30613',
];
try {
    $stmtCfg = $pdo->prepare("SELECT setting_key, setting_value FROM app_config WHERE setting_key IN ('login_logo','login_title','login_subtitle','login_bg_color','login_btn_color') AND organizacion_id = 1");
    $stmtCfg->execute();
    while ($row = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
        $login_config[$row['setting_key']] = $row['setting_value'];
    }
}
catch (Exception $e) { /* Usar defaults si falla */
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $error = "Por favor ingrese usuario y contraseña.";
    }
    else {
        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.name, u.password, u.role, u.organizacion_id, o.status as org_status 
                FROM users u 
                LEFT JOIN organizaciones o ON u.organizacion_id = o.id 
                WHERE u.username = :username
            ");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                // Check if Organization is Archived (ignore for superadmin)
                if ($user['role'] !== 'superadmin' && $user['org_status'] === 'archived') {
                    $error = "Su organización ha sido archivada. Contacte al soporte técnico.";
                }
                else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['name'] = $user['name'] ?? $username; // Use name if available
                    $_SESSION['role'] = $user['role'];

                    if ($user['role'] === 'superadmin') {
                        // Superadmin might have NULL org_id. Set to 1 just for config loading safety
                        $_SESSION['organizacion_id'] = 1;
                        header("Location: superadmin_dashboard.php");
                    }
                    elseif ($user['role'] === 'lider') {
                        $_SESSION['organizacion_id'] = $user['organizacion_id'] ?? 1;
                        header("Location: registros.php");
                    }
                    else {
                        $_SESSION['organizacion_id'] = $user['organizacion_id'] ?? 1;
                        header("Location: dashboard.php");
                    }
                    exit;
                }
            }
            else {
                $error = "Usuario o contraseña inválidos.";
            }
        }
        catch (PDOException $e) {
            $error = "Error en el sistema: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Plataforma Política</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <style>
        :root {
            --primary-red: <?php echo htmlspecialchars($login_config['login_btn_color']);
?>;
            --primary-dark: <?php echo htmlspecialchars($login_config['login_bg_color']);
?>;
            --accent-color: <?php echo htmlspecialchars($login_config['login_btn_color']);
?>;
        }

        body.auth-container {
            background: <?php echo htmlspecialchars($login_config['login_bg_color']);
?> !important;
            background: linear-gradient(135deg, <?php echo htmlspecialchars($login_config['login_bg_color']); ?> 0%, <?php echo htmlspecialchars($login_config['login_bg_color']); ?>cc 100%) !important;
        }

        .btn-primary {
            background-color: <?php echo htmlspecialchars($login_config['login_btn_color']);
?>;
            border-color: <?php echo htmlspecialchars($login_config['login_btn_color']);
?>;
        }

        .btn-primary:hover {
            background-color: <?php echo htmlspecialchars($login_config['login_btn_color']);
?>cc;
        }
    </style>
</head>

<body class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <img src="<?php echo htmlspecialchars($login_config['login_logo']); ?>" alt="Logo"
                onerror="this.src='Logo.webp'" style="width: 200px; margin-bottom: 20px; max-width: 100%;">
            <h1>
                <?php echo htmlspecialchars($login_config['login_title']); ?>
            </h1>
            <p style="color: #777;">
                <?php echo htmlspecialchars($login_config['login_subtitle']); ?>
            </p>
        </div>

        <?php if ($error): ?>
        <div class="error-msg">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php
endif; ?>

        <?php if (isset($isLocal) && $isLocal): ?>
        <div style="text-align: center; margin-bottom: 20px;">
            <button onclick="document.getElementById('credsModal').style.display='block'" class="btn"
                style="background-color: #f0ad4e; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-key"></i> Ver Credenciales de Demo
            </button>
        </div>

        <!-- Credentials Modal -->
        <div id="credsModal"
            style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8);">
            <div
                style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 8px; color: #333;">
                <span onclick="document.getElementById('credsModal').style.display='none'"
                    style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                <h2 style="color: #E30613; border-bottom: 2px solid #E30613; padding-bottom: 10px;">Credenciales de
                    Acceso (Entorno Local)</h2>

                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
                    <!-- Super Admin -->
                    <div
                        style="flex: 1; min-width: 200px; background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <h3 style="color: #333; margin-top: 0;"><i class="fas fa-user-shield"></i> Dios (Superadmin)
                        </h3>
                        <ul style="list-style: none; padding: 0;">
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                                <strong>Usuario:</strong> dios<br>
                                <strong>Clave:</strong> dios123
                            </li>
                        </ul>
                    </div>

                    <!-- Admins -->
                    <div
                        style="flex: 1; min-width: 200px; background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <h3 style="color: #333; margin-top: 0;"><i class="fas fa-building"></i> Organizaciones (Admins)
                        </h3>
                        <ul style="list-style: none; padding: 0; max-height: 300px; overflow-y: auto;">
                            <?php
    try {
        $stmtAdmins = $pdo->query("SELECT u.username, o.nombre_organizacion FROM users u JOIN organizaciones o ON u.organizacion_id = o.id WHERE u.role = 'admin' ORDER BY o.id");
        while ($row = $stmtAdmins->fetch(PDO::FETCH_ASSOC)) {
            echo "<li style='padding: 5px 0; border-bottom: 1px solid #eee;'>
                                        <strong>Org:</strong> " . htmlspecialchars($row['nombre_organizacion']) . "<br>
                                        <strong>User:</strong> " . htmlspecialchars($row['username']) . "<br>
                                        <strong>Pass:</strong> admin123
                                    </li>";
        }
    }
    catch (Exception $e) {
        echo "<li>Error cargando datos</li>";
    }
?>
                        </ul>
                    </div>

                    <!-- Leaders -->
                    <div
                        style="flex: 1; min-width: 200px; background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                        <h3 style="color: #333; margin-top: 0;"><i class="fas fa-users"></i> Líderes</h3>
                        <ul style="list-style: none; padding: 0; max-height: 300px; overflow-y: auto;">
                            <?php
    try {
        $stmtLeaders = $pdo->query("SELECT u.username, u.name, o.nombre_organizacion FROM users u JOIN organizaciones o ON u.organizacion_id = o.id WHERE u.role = 'lider' ORDER BY o.id, u.name");
        while ($row = $stmtLeaders->fetch(PDO::FETCH_ASSOC)) {
            echo "<li style='padding: 5px 0; border-bottom: 1px solid #eee;'>
                                        <strong>Org:</strong> " . htmlspecialchars($row['nombre_organizacion']) . "<br>
                                        <strong>Nombre:</strong> " . htmlspecialchars($row['name']) . "<br>
                                        <strong>User (Cédula):</strong> " . htmlspecialchars($row['username']) . "<br>
                                        <strong>Pass:</strong> 12345678
                                    </li>";
        }
    }
    catch (Exception $e) {
        echo "<li>Error cargando datos</li>";
    }
?>
                        </ul>
                    </div>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button onclick="document.getElementById('credsModal').style.display='none'" class="btn"
                        style="background-color: #ccc;">Cerrar</button>
                </div>
            </div>
        </div>
        <script>
            // Close modal when clicking outside
            window.onclick = function (event) {
                var modal = document.getElementById('credsModal');
                if (event.target == modal) {
                    modal.style.display = "none";
                }
            }
        </script>
        <?php
endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" name="username" id="username" class="form-control" required placeholder="admin">
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" name="password" id="password" class="form-control" required
                    placeholder="*******">
            </div>
            <button type="submit" class="btn btn-primary">Ingresar</button>
        </form>

        <div class="auth-footer" style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
            <a href="register_org.php"
                style="color: var(--primary-red); font-weight: bold; text-decoration: none;">Crear Nueva Campaña</a>
            <p>&copy;
                <?php echo date('Y'); ?>. Todos los derechos reservados.
            </p>
        </div>
    </div>
</body>

</html>