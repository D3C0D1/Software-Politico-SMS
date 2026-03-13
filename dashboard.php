<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control: Leaders restricted to registros.php
if ($_SESSION['role'] === 'lider') {
    header("Location: registros.php");
    exit;
}

// Fetch basic stats
try {
    $user_id = $_SESSION['user_id'];


    // Total Líderes Registrados (Por Organización)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE tipo = 'lider' AND organizacion_id = ?");
    $stmt->execute([$_SESSION['organizacion_id']]);
    $totalLideres = $stmt->fetchColumn();

    // Total de TODOS los Votantes (Por Organización)
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $sqlToday = "SELECT COUNT(*) FROM registros WHERE date(created_at) = date('now', 'localtime') AND organizacion_id = ?";
    }
    else {
        $sqlToday = "SELECT COUNT(*) FROM registros WHERE DATE(created_at) = CURDATE() AND organizacion_id = ?";
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE tipo = 'votante' AND organizacion_id = ?");
    $stmt->execute([$_SESSION['organizacion_id']]);
    $totalVotantesGlobal = $stmt->fetchColumn();

    // Votaron (Por Organización)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE estado_voto = 'voto' AND tipo = 'votante' AND organizacion_id = ?");
    $stmt->execute([$_SESSION['organizacion_id']]);
    $votaron = $stmt->fetchColumn();

    // Pendientes (Por Organización)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM registros WHERE (estado_voto != 'voto' OR estado_voto IS NULL) AND tipo = 'votante' AND organizacion_id = ?");
    $stmt->execute([$_SESSION['organizacion_id']]);
    $pendientes = $stmt->fetchColumn();

}
catch (PDOException $e) {
    $totalLideres = 0;
    $totalVotantesGlobal = 0;
    $votaron = 0;
    $pendientes = 0;
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mobile-dashboard.css">
    <link rel="stylesheet" href="assets/css/sidebar-toggle.css">
    <style>
        :root {
            --primary-red: <?php echo htmlspecialchars($app_config['primary_color']);
            ?>;
            --primary-dark: <?php echo htmlspecialchars($app_config['primary_color']);
            ?>;
        }
    </style>
</head>

<body class="dashboard-layout">

    <!-- MOBILE DASHBOARD VIEW -->
    <div class="mobile-dashboard-view">
        <div class="mobile-bg-animation"></div>

        <!-- Animated Header Section -->
        <div class="orbit-header">
            <div class="orbit-system">
                <div class="orbit-center">
                    <img src="<?php echo htmlspecialchars($app_config['logo_path']); ?>" alt="Logo Central"
                        class="pulse-logo" style="max-width: 100%;">
                </div>

                <!-- Orbiting Bubbles -->
                <?php
$prof = !empty($app_config['profile_path']) ? $app_config['profile_path'] : 'assets/img/placeholder_profile.png';
$style = (strpos($prof, 'placeholder') !== false) ? 'opacity: 0.7; filter: grayscale(100%);' : '';
?>
                <div class="orbit-track track-1">
                    <div class="orbit-bubble bubble-1"><img src="<?php echo htmlspecialchars($prof); ?>"
                            style="<?php echo $style; ?>"></div>
                </div>
                <div class="orbit-track track-2">
                    <div class="orbit-bubble bubble-2"><img src="<?php echo htmlspecialchars($prof); ?>"
                            style="<?php echo $style; ?>"></div>
                </div>
                <div class="orbit-track track-3">
                    <div class="orbit-bubble bubble-3"><img src="<?php echo htmlspecialchars($prof); ?>"
                            style="<?php echo $style; ?>"></div>
                </div>

                <h1 class="mobile-app-title">
                    <?php echo htmlspecialchars($app_config['app_title']); ?>
                </h1>
            </div>
        </div>

        <!-- Dashboard Grid -->
        <div class="mobile-grid-menu">
            <a href="dashboard.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-home"></i></div>
                <span>Inicio</span>
            </a>
            <a href="estadisticas.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-chart-pie"></i></div>
                <span>Estadísticas</span>
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="lideres.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-user-tie"></i></div>
                <span>Líderes</span>
            </a>
            <?php
endif; ?>
            <a href="registros.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-users"></i></div>
                <span>Votantes</span>
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
            <a href="todos_registros.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-database"></i></div>
                <span>Base Datos</span>
            </a>
            <a href="usuarios.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-users-cog"></i></div>
                <span>Usuarios</span>
            </a>
            <?php
endif; ?>
            <a href="enviar_sms.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-sms"></i></div>
                <span>SMS</span>
            </a>
            <a href="configuraciones.php" class="grid-item">
                <div class="grid-icon"><i class="fas fa-cogs"></i></div>
                <span>Ajustes</span>
            </a>
            <a href="logout.php" class="grid-item logout-item">
                <div class="grid-icon"><i class="fas fa-sign-out-alt"></i></div>
                <span>Salir</span>
            </a>
        </div>
    </div>
    <!-- END MOBILE VIEW -->

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Overlay for mobile sidebar (desktop mode on small screen) -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <div class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="page-title">Bienvenido al Dashboard</h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>
                    </strong></span>
                <div class="profile-dropdown">
                    <button onclick="document.getElementById('profileDropdown').classList.toggle('show-dropdown')"
                        class="profile-btn">
                        <?php
$headerProf = !empty($app_config['profile_path']) ? $app_config['profile_path'] : 'assets/img/placeholder_profile.png';
$headerStyle = (strpos($headerProf, 'placeholder') !== false) ? 'opacity: 0.7; filter: grayscale(100%);' : '';
?>
                        <img src="<?php echo htmlspecialchars($headerProf); ?>" alt="Profile"
                            style="width: 35px; height: 35px; border-radius: 50%; object-fit: cover; <?php echo $headerStyle; ?>">
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

            <div class="dashboard-cards">

                <div class="card-stat">
                    <div class="card-icon icon-purple">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $totalLideres; ?>
                        </h3>
                        <p>Líderes Registrados</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $votaron; ?>
                        </h3>
                        <p>Votaron</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $pendientes; ?>
                        </h3>
                        <p>Pendientes</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $totalVotantesGlobal; ?>
                        </h3>
                        <p>Total Votantes</p>
                    </div>
                </div>
            </div>

            <h3 style="margin-bottom: 20px;">Accesos Rápidos</h3>
            <div class="quick-actions">
                <a href="registros.php" class="action-card">
                    <i class="fas fa-list-alt"></i>
                    <span>Ver Registros</span>
                </a>
                <a href="registro_form.php" class="action-card">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Registro</span>
                </a>
                <a href="usuarios.php" class="action-card">
                    <i class="fas fa-users-cog"></i>
                    <span>Gestionar Usuarios</span>
                </a>
                <a href="configuraciones.php" class="action-card">
                    <i class="fas fa-cogs"></i>
                    <span>Configuraciones</span>
                </a>
            </div>

        </div>
    </div>

    <!-- Edit Profile Modal (Same as other pages for consistency) -->
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

        // Auto-refresh every 30 seconds
        setInterval(function () {
            location.reload();
        }, 30000);
        // Sidebar Toggle Logic
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-closed');
            document.body.classList.toggle('sidebar-open');
        }
    </script>

</body>

</html>