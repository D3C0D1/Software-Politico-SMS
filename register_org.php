<?php
require_once 'config.php';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $org_name = trim($_POST['org_name']);
    $admin_username = trim($_POST['admin_username']);
    $admin_password = trim($_POST['admin_password']);
    $admin_name = trim($_POST['admin_name']);

    if (empty($org_name) || empty($admin_username) || empty($admin_password) || empty($admin_name)) {
        $error = "Todos los campos son obligatorios.";
    }
    else {
        try {
            $pdo->beginTransaction();

            // 1. Create Organization
            $stmt = $pdo->prepare("INSERT INTO organizaciones (nombre_organizacion) VALUES (?)");
            $stmt->execute([$org_name]);
            $org_id = $pdo->lastInsertId();

            // 2. Create Admin User
            // Check username uniqueness (globally? or per org? usually globally per system)
            $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $check->execute([$admin_username]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("El nombre de usuario ya está en uso. Por favor elija otro.");
            }

            $hash = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id) VALUES (?, ?, ?, 'admin', ?)");
            $stmt->execute([$admin_username, $hash, $admin_name, $org_id]);

            // 3. Create Default App Config for this Org
            $defaults = [
                'app_title' => $org_name, // Default title is Organization Name
                'logo_path' => 'assets/img/placeholder_logo.png', // Default placeholder
                'profile_path' => 'assets/img/placeholder_profile.png',
                'primary_color' => '#333333' // Default Neutral Black/Grey
            ];

            $insert = $pdo->prepare("INSERT INTO app_config (setting_key, setting_value, organizacion_id) VALUES (?, ?, ?)");
            foreach ($defaults as $key => $value) {
                $insert->execute([$key, $value, $org_id]);
            }

            $pdo->commit();
            $success = "¡Organización creada exitosamente! Ahora puede iniciar sesión.";

        }
        catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Organización - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            /* Default Black/Dark for Registration Page */
            --primary-red: #333333;
            --primary-dark: #000000;
            --accent-color: #E30613;
        }

        /* Override auth container specifically for this page just in case */
        body.auth-container {
            background: #000000;
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
        }

        /* Make button red accent */
        .btn-primary {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
    </style>
</head>

<body class="auth-container">
    <div class="auth-box" style="max-width: 500px;">
        <div class="auth-header">
            <h1 style="font-size: 1.8rem;">Crear Organización</h1>
            <p style="color: #777;">Registra tu campaña política</p>
        </div>

        <?php if ($error): ?>
        <div class="error-msg">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php
endif; ?>

        <?php if ($success): ?>
        <div class="success-msg"
            style="padding: 10px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 20px;">
            <?php echo htmlspecialchars($success); ?>
            <br>
            <a href="index.php" style="font-weight: bold; color: #155724;">Ir al Login</a>
        </div>
        <?php
else: ?>

        <form action="register_org.php" method="POST">
            <div class="form-group">
                <label>Nombre de la Organización / Campaña</label>
                <input type="text" name="org_name" class="form-control" placeholder="Ej: Campaña Juan Perez" required>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            <p style="text-align: left; font-weight: bold; color: #555; margin-bottom: 15px;">Datos del Administrador
            </p>

            <div class="form-group">
                <label>Nombre Completo</label>
                <input type="text" name="admin_name" class="form-control" placeholder="Nombre Apellido" required>
            </div>

            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="admin_username" class="form-control" placeholder="admin_campana" required>
            </div>

            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="admin_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">Registrar Organización</button>
        </form>
        <?php
endif; ?>

        <div class="auth-footer" style="margin-top: 20px;">
            <a href="index.php" style="color: #666; text-decoration: none;">¿Ya tienes cuenta? Iniciar Sesión</a>
        </div>
    </div>
</body>

</html>