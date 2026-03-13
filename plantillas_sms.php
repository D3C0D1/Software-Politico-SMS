<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    if (empty($content)) {
        $error = "El contenido del mensaje no puede estar vacío.";
    }
    else {
        try {
            $org_id = $_SESSION['organizacion_id'] ?? 1;
            // Verification: Ensure the template belongs to the organization
            $stmt = $pdo->prepare("UPDATE sms_templates SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND organizacion_id = ?");
            $stmt->execute([$content, $template_id, $org_id]);

            if ($stmt->rowCount() > 0) {
                $message = "Plantilla actualizada correctamente.";
            }
            else {
                $error = "No tienes permisos para actualizar esta plantilla o no existe.";
            }
        }
        catch (PDOException $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}

// Fetch all templates for THIS organization
try {
    $org_id = $_SESSION['organizacion_id'] ?? 1;
    $stmt = $pdo->prepare("SELECT * FROM sms_templates WHERE organizacion_id = ? ORDER BY id ASC");
    $stmt->execute([$org_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    $error = "Error al cargar plantillas: " . $e->getMessage();
    $templates = [];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantillas de SMS - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="dashboard-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="top-bar">
            <h2 class="page-title">Plantillas de SMS</h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>
                    </strong></span>
                <div class="profile-dropdown">
                    <button onclick="document.getElementById('profileDropdown').classList.toggle('show-dropdown')"
                        class="profile-btn">
                        <img src="assets/img/liberal.png" alt="Profile"
                            style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover;">
                    </button>
                    <div id="profileDropdown" class="dropdown-content">
                        <a href="#" onclick="openModal()">Editar Perfil</a>
                        <a href="configuraciones.php">Configuraciones</a>
                        <a href="logout.php">Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-area">
            <?php if ($message): ?>
            <div
                style="background-color: #d4edda; color: #155724; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <i class="fas fa-check-circle"></i>
                <?php echo $message; ?>
            </div>
            <?php
endif; ?>

            <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
            <?php
endif; ?>

            <div style="margin-bottom: 20px;">
                <a href="configuraciones.php" class="btn" style="background: #6c757d; color: white;">
                    <i class="fas fa-arrow-left"></i> Volver a Configuraciones
                </a>
            </div>

            <div class="card">
                <h3><i class="fas fa-envelope"></i> Gestión de Plantillas de SMS</h3>
                <p style="color: #666; margin-bottom: 30px;">Edite las plantillas de mensajes SMS que se utilizan en el
                    sistema.</p>

                <?php foreach ($templates as $template): ?>
                <div
                    style="background: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px; border-left: 4px solid var(--primary-red);">
                    <form method="POST" action="">
                        <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">

                        <div class="form-group">
                            <label style="font-weight: bold; color: var(--primary-red); font-size: 1.1em;">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($template['label']); ?>
                            </label>
                            <?php if ($template['name'] === 'confirmacion'): ?>
                            <div
                                style="font-size: 0.9em; color: #155724; background: #d4edda; padding: 5px 10px; border-radius: 4px; display: inline-block; margin-left: 10px;">
                                <i class="fas fa-info-circle"></i> Use <strong>{LINK_CONFIRMACION}</strong> para
                                insertar el link único
                            </div>
                            <?php
    endif; ?>
                            <textarea name="content" class="form-control" rows="3" required style="margin-top: 10px;"
                                maxlength="160"
                                onkeyup="updateCharCount(this, 'char-count-<?php echo $template['id']; ?>')"><?php echo htmlspecialchars($template['content']); ?></textarea>
                            <small style="color: #666; display: block; margin-top: 5px;">
                                <span id="char-count-<?php echo $template['id']; ?>">
                                    <?php echo strlen($template['content']); ?>
                                </span>/160 caracteres
                            </small>
                        </div>

                        <div style="text-align: right; margin-top: 15px;">
                            <small style="color: #999; margin-right: 15px;">
                                <i class="fas fa-clock"></i> Última actualización:
                                <?php echo date('d/m/Y H:i', strtotime($template['updated_at'])); ?>
                            </small>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Cambios
                            </button>
                        </div>
                    </form>
                </div>
                <?php
endforeach; ?>

                <?php if (empty($templates)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 20px;"></i>
                    <p>No hay plantillas disponibles.</p>
                </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Editar Perfil</h2>
            <form action="actualizar_perfil.php" method="POST" class="modal-form">
                <div class="form-group">
                    <label for="name">Nombre Completo</label>
                    <input type="text" id="name" name="name"
                        value="<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?>"
                        required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Character counter
        function updateCharCount(textarea, counterId) {
            const count = textarea.value.length;
            const counter = document.getElementById(counterId);
            counter.textContent = count;

            if (count > 160) {
                counter.style.color = '#dc3545';
                counter.style.fontWeight = 'bold';
            } else if (count > 140) {
                counter.style.color = '#ffc107';
            } else {
                counter.style.color = '#666';
                counter.style.fontWeight = 'normal';
            }
        }

        // Modal Logic
        var modal = document.getElementById("profileModal");
        function openModal() {
            modal.style.display = "block";
            document.getElementById('profileDropdown').classList.remove('show-dropdown');
        }
        function closeModal() {
            modal.style.display = "none";
        }
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
            if (!event.target.matches('.profile-btn') && !event.target.matches('.profile-btn *')) {
                var dropdowns = document.getElementsByClassName("dropdown-content");
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show-dropdown')) {
                        openDropdown.classList.remove('show-dropdown');
                    }
                }
            }
        }
    </script>
</body>

</html>