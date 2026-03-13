<aside class="sidebar">
    <div class="logo-area">
        <a href="dashboard.php" style="text-decoration: none;">
            <?php
$logo = !empty($app_config['logo_path']) ? $app_config['logo_path'] : 'assets/img/placeholder_logo.png';
// If it contains 'placeholder', make it less prominent
if (strpos($logo, 'placeholder') !== false) {
    echo '<img src="' . htmlspecialchars($logo) . '" alt="Logo" style="width: 80px; display: block; margin: 0 auto; opacity: 0.5; filter: grayscale(100%);">';
}
else {
    echo '<img src="' . htmlspecialchars($logo) . '" alt="Logo" style="width: 120px; display: block; margin: 0 auto; max-width: 100%;">';
}
?>
            <?php if (isset($app_config['app_title'])): ?>
            <div class="logo-text" style="font-size: 1rem; margin-top: 5px; color: #555;">
                <?php echo htmlspecialchars($app_config['app_title']); ?>
            </div>
            <?php
endif; ?>
        </a>
    </div>

    <ul class="nav-links">
        <?php if ($_SESSION['role'] !== 'lider'): ?>
        <li class="nav-item">
            <a href="dashboard.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i> Inicio
            </a>
        </li>
        <li class="nav-item">
            <a href="estadisticas.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'estadisticas.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Estadísticas
            </a>
        </li>
        <li class="nav-item">
            <a href="lideres.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'lideres.php' || (basename($_SERVER['PHP_SELF']) == 'registro_form.php' && isset($_GET['type']) && $_GET['type'] == 'lider') ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Líderes
            </a>
        </li>
        <?php
endif; ?>

        <?php if ($_SESSION['role'] === 'lider' || $_SESSION['role'] === 'leader'): ?>
        <li class="nav-item">
            <a href="registros.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'registros.php' || (basename($_SERVER['PHP_SELF']) == 'registro_form.php' && (!isset($_GET['type']) || $_GET['type'] == 'votante')) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Votantes
            </a>
            <?php
endif; ?>

            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin'): ?>
        <li class="nav-item">
            <a href="tus_votantes.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tus_votantes.php' ? 'active' : ''; ?>">
                <i class="fas fa-address-book"></i> Tus Votantes
            </a>
        </li>
        <?php
endif; ?>

        <?php if ($_SESSION['role'] !== 'lider'): ?>
        <li class="nav-item">
            <a href="todos_registros.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'todos_registros.php' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> Base de Datos
            </a>
        </li>
        <?php
endif; ?>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <li class="nav-item">
            <a href="usuarios.php"
                class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'usuarios.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Gestión Usuarios
            </a>
        </li>
        <?php
endif; ?>
    </ul>

    <div class="sidebar-footer">
        <p style="font-size: 0.8rem; color: #999; text-align: center;">&copy;
            <?php echo date('Y'); ?> Partido Liberal
        </p>
    </div>
</aside>