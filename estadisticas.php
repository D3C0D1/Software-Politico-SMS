<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control
if ($_SESSION['role'] === 'lider') {
    header("Location: registros.php");
    exit;
}

// 1. Data for Pie Chart (Leaders with most voters)
// We group by user_id and count voters.
// We only consider 'votante' type records.
// FILTRADO POR ORGANIZACIÓN ACTUAL
$current_org_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("
    SELECT u.name, COUNT(r.id) as total_voters 
    FROM registros r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.tipo = 'votante' AND r.organizacion_id = ? AND u.organizacion_id = ?
    GROUP BY r.user_id 
    ORDER BY total_voters DESC 
    LIMIT 10
");
$stmt->execute([$current_org_id, $current_org_id]);
$leaderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

$leaderLabels = [];
$leaderData = [];
$leaderColors = [];

// Generate random bright colors for the chart
function randomColor()
{
    return 'rgba(' . rand(50, 255) . ', ' . rand(50, 255) . ', ' . rand(50, 255) . ', 0.7)';
}

foreach ($leaderStats as $stat) {
    // Escape single quotes in names to prevent JS errors
    $name = $stat['name'] ? $stat['name'] : 'Sin Nombre';
    $leaderLabels[] = $name;
    $leaderData[] = $stat['total_voters'];
    $leaderColors[] = randomColor();
}

// 2. Data for Line Chart (Voters registered in last 7 days)
// Detect DB Driver to use correct SQL syntax (SQLite vs MySQL)
// FILTRADO POR ORGANIZACIÓN ACTUAL
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'sqlite') {
    // SQLite Syntax
    $sqlTrend = "SELECT date(created_at) as fecha, COUNT(*) as count 
                 FROM registros 
                 WHERE tipo = 'votante' AND organizacion_id = ? AND created_at >= date('now', '-7 days', 'localtime') 
                 GROUP BY date(created_at) 
                 ORDER BY fecha ASC";
}
else {
    // MySQL Syntax (Hostinger)
    $sqlTrend = "SELECT DATE(created_at) as fecha, COUNT(*) as count 
                 FROM registros 
                 WHERE tipo = 'votante' AND organizacion_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                 GROUP BY DATE(created_at) 
                 ORDER BY fecha ASC";
}

$stmt = $pdo->prepare($sqlTrend);
$stmt->execute([$current_org_id]);
$trendStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare last 7 days structure to fill gaps with 0
$trendLabels = [];
$trendData = [];

// Set the default timezone to match the 'localtime' used in SQLite for consistency
date_default_timezone_set(date_default_timezone_get());

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $trendLabels[] = $date;

    // Find count for this date
    $count = 0;
    foreach ($trendStats as $stat) {
        if ($stat['fecha'] == $date) {
            $count = $stat['count'];
            break;
        }
    }
    $trendData[] = $count;
}

// 3. Data for Duplicates (New Metric)
// Count total duplicates (records that share a cedula)
$sqlDupeCount = "SELECT COUNT(*) FROM registros WHERE tipo = 'votante' AND organizacion_id = ? AND cedula IN (
    SELECT cedula FROM registros WHERE tipo = 'votante' AND organizacion_id = ? GROUP BY cedula HAVING COUNT(*) > 1
)";
$stmt = $pdo->prepare($sqlDupeCount);
$stmt->execute([$current_org_id, $current_org_id]);
$totalDuplicates = $stmt->fetchColumn();

// Leaders with duplicates
$sqlDupeLeaders = "SELECT u.id as user_id, u.name, COUNT(r.id) as dupe_count 
                   FROM registros r 
                   JOIN users u ON r.user_id = u.id 
                   WHERE r.tipo = 'votante' AND r.organizacion_id = ? 
                   AND r.cedula IN (
                       SELECT cedula FROM registros WHERE tipo = 'votante' AND organizacion_id = ? GROUP BY cedula HAVING COUNT(*) > 1
                   ) 
                   GROUP BY r.user_id 
                   ORDER BY dupe_count DESC";
$stmt = $pdo->prepare($sqlDupeLeaders);
$stmt->execute([$current_org_id, $current_org_id]);
$dupeLeadersStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/sidebar-toggle.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-red: <?php echo htmlspecialchars($app_config['primary_color']);
?>;
            --primary-dark: <?php echo htmlspecialchars($app_config['primary_color']);
?>;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .chart-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--box-shadow);
        }

        .chart-header {
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            color: var(--primary-red);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .leaderboard-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .leaderboard-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
        }

        .leaderboard-item:last-child {
            border-bottom: none;
        }

        .rank-circle {
            width: 30px;
            height: 30px;
            background: #f0f0f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #555;
            margin-right: 15px;
        }

        .rank-1 .rank-circle {
            background: #FFD700;
            color: #fff;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .rank-2 .rank-circle {
            background: #C0C0C0;
            color: #fff;
        }

        .rank-3 .rank-circle {
            background: #CD7F32;
            color: #fff;
        }

        .leader-name {
            flex-grow: 1;
            font-weight: 500;
        }

        .vote-count {
            font-weight: 800;
            color: var(--primary-red);
            font-size: 1.1em;
        }
    </style>
</head>

<body class="dashboard-layout">

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <div class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="page-title">Estadísticas y Métricas</h2>
            <div class="user-profile">
                <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>
                </strong></span>
                <div class="profile-dropdown">
                    <button onclick="document.getElementById('profileDropdown').classList.toggle('show-dropdown')"
                        class="profile-btn">
                        <img src="<?php echo htmlspecialchars($app_config['profile_path']); ?>" alt="Profile"
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

            <div class="charts-grid">
                <!-- Pie Chart: Leaders Performance -->
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-pie"></i> Desempeño de Líderes
                    </div>
                    <div style="height: 300px; display: flex; justify-content: center;">
                        <canvas id="leadersChart"></canvas>
                    </div>
                </div>

                <!-- Line Chart: Trend -->
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-chart-line"></i> Votantes Registrados (Últimos 7 días)
                    </div>
                    <div style="height: 300px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <!-- Leaderboard List -->
                <div class="chart-card">
                    <div class="chart-header">
                        <i class="fas fa-trophy"></i> Top Líderes
                    </div>
                    <ul class="leaderboard-list">
                        <?php foreach ($leaderStats as $index => $stat): ?>
                        <li class="leaderboard-item rank-<?php echo $index + 1; ?>">
                            <div style="display:flex; align-items:center;">
                                <div class="rank-circle">
                                    <?php echo $index + 1; ?>
                                </div>
                                <span class="leader-name">
                                    <?php echo htmlspecialchars($stat['name'] ? $stat['name'] : 'Desconocido'); ?>
                                </span>
                            </div>
                            <span class="vote-count">
                                <?php echo $stat['total_voters']; ?>
                            </span>
                        </li>
                        <?php
endforeach; ?>
                        <?php if (count($leaderStats) == 0): ?>
                        <li style="text-align:center; color:#999; padding:20px;">No hay datos disponibles.</li>
                        <?php
endif; ?>
                    </ul>
                </div>
                <!-- Additional Info or Future chart -->
                <div class="chart-card"
                    style="display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-dark) 100%); color: white; text-align: center;">
                    <div>
                        <i class="fas fa-rocket" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.8;"></i>
                        <h3 style="margin: 0;">¡Vamos por la Victoria!</h3>
                        <p style="margin-top: 10px; opacity: 0.9;">Sigamos sumando esfuerzos.</p>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <!-- Duplicate Data Card -->
                <div class="chart-card">
                    <div class="chart-header" style="color: #dc3545;">
                        <i class="fas fa-exclamation-triangle"></i> Votantes Duplicados
                    </div>
                    <div
                        style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 200px;">
                        <h1 style="font-size: 5rem; margin: 0; color: #dc3545; font-weight: 800;">
                            <?php echo $totalDuplicates; ?>
                        </h1>
                        <p style="color: #666; font-size: 1.1em;">Registros con cédula repetida</p>
                    </div>
                </div>

                <!-- Leaders with Duplicates List -->
                <div class="chart-card">
                    <div class="chart-header" style="color: #dc3545;">
                        <i class="fas fa-user-times"></i> Líderes con Duplicados
                    </div>
                    <ul class="leaderboard-list">
                        <?php foreach ($dupeLeadersStats as $stat): ?>
                        <li class="leaderboard-item">
                            <span class="leader-name">
                                <?php echo htmlspecialchars($stat['name'] ? $stat['name'] : 'Desconocido'); ?>
                            </span>
                            <span class="vote-count" style="color: #dc3545; margin-right: 15px;">
                                <?php echo $stat['dupe_count']; ?>
                            </span>
                            <button
                                onclick="verRegistrosDuplicados(<?php echo $stat['user_id'] ?? 0; ?>, <?php echo htmlspecialchars(json_encode($stat['name'] ? $stat['name'] : 'Desconocido'), ENT_QUOTES, 'UTF-8'); ?>)"
                                class="btn btn-sm"
                                style="background:var(--primary-red); color:white; border:none; border-radius:4px; padding:5px 10px; cursor:pointer;">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                        </li>
                        <?php
endforeach; ?>
                        <?php if (count($dupeLeadersStats) == 0): ?>
                        <li style="text-align:center; color:#999; padding:20px;">No hay duplicados encontrados.</li>
                        <?php
endif; ?>
                    </ul>

                    <?php if ($totalDuplicates > 0): ?>
                    <!-- Botón eliminar duplicados: solo aparece cuando hay duplicados -->
                    <div
                        style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f0; text-align: center;">
                        <p style="color: #666; font-size: 0.9em; margin-bottom: 12px;">
                            <i class="fas fa-info-circle" style="color:#dc3545;"></i>
                            Se encontraron <strong style="color:#dc3545;">
                                <?php echo $totalDuplicates; ?>
                            </strong> registros duplicados.
                            Se conservará el registro más antiguo de cada cédula.
                        </p>
                        <button id="btnEliminarDuplicados" onclick="confirmarEliminarDuplicados()" style="background: #dc3545; color: white; border: none; border-radius: 8px;
                                   padding: 10px 24px; font-size: 0.95em; font-weight: 600;
                                   cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
                                   box-shadow: 0 2px 8px rgba(220,53,69,0.3); transition: all 0.2s;"
                            onmouseover="this.style.background='#b02a37'" onmouseout="this.style.background='#dc3545'">
                            <i class="fas fa-trash-alt"></i>
                            ¿Deseas eliminar los duplicados?
                        </button>
                    </div>
                    <?php
endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- Edit Profile Modal (Standard) -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Editar Perfil</h2>
            <form action="actualizar_perfil.php" method="POST" class="modal-form">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="name"
                        value="<?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : ''; ?>"
                        required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Duplicate Details Modal -->
    <div id="dupeModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <span class="close" onclick="closeDupeModal()">&times;</span>
            <h2 id="dupeModalTitle" style="color: var(--primary-red);">Registro de Líder</h2>
            <div id="dupeModalBody">
                <p style="text-align:center;"><i class="fas fa-spinner fa-spin"></i> Cargando...</p>
            </div>
        </div>
    </div>

    <script>
        // Modal & Dropdown Scripts
        var modal = document.getElementById("profileModal");
        var dupeModal = document.getElementById("dupeModal");

        function openModal() {
            modal.style.display = "block";
            document.getElementById('profileDropdown').classList.remove('show-dropdown');
        }

        function closeModal() {
            modal.style.display = "none";
        }

        function closeDupeModal() {
            dupeModal.style.display = "none";
        }

        function verRegistrosDuplicados(liderId, liderNombre) {
            dupeModal.style.display = "block";
            document.getElementById("dupeModalTitle").innerText = "REGISTRO DE " + liderNombre.toUpperCase();
            document.getElementById("dupeModalBody").innerHTML = '<p style="text-align:center; padding: 20px;"><i class="fas fa-spinner fa-spin fa-2x"></i><br>Cargando registros...</p>';

            fetch('ajax_registros_duplicados.php?lider_id=' + liderId)
                .then(response => response.text())
                .then(html => {
                    document.getElementById("dupeModalBody").innerHTML = html;
                })
                .catch(err => {
                    document.getElementById("dupeModalBody").innerHTML = '<p style="color:red; text-align:center;">Error al cargar datos.</p>';
                    console.error(err);
                });
        }

        function confirmarEliminarDuplicados() {
            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar duplicados?',
                html: 'Se eliminarán los registros duplicados.<br><strong>Se conservará el registro más antiguo</strong> de cada cédula.<br><br>Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt"></i> Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    const btn = document.getElementById('btnEliminarDuplicados');
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...';

                    fetch('ajax_eliminar_duplicados.php', { method: 'POST' })
                        .then(r => r.json())
                        .then(data => {
                            if (data.status === 'ok') {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Listo!',
                                    text: data.message,
                                    confirmButtonColor: '#dc3545'
                                }).then(() => location.reload());
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: data.error || 'No se pudieron eliminar los duplicados.',
                                    confirmButtonColor: '#dc3545'
                                });
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-trash-alt"></i> ¿Deseas eliminar los duplicados?';
                            }
                        })
                        .catch(() => {
                            Swal.fire('Error', 'Error de conexión.', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fas fa-trash-alt"></i> ¿Deseas eliminar los duplicados?';
                        });
                }
            });
        }

        window.onclick = function (event) {
            if (event.target == modal) closeModal();
            if (event.target == dupeModal) closeDupeModal();
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

        // --- Charts Initialization ---

        // 1. Pie Chart: Leaders
        const ctxLeaders = document.getElementById('leadersChart').getContext('2d');
        new Chart(ctxLeaders, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($leaderLabels); ?>,
            datasets: [{
                data: <?php echo json_encode($leaderData); ?>,
                backgroundColor: <?php echo json_encode($leaderColors); ?>,
            borderWidth: 0
                }]
            },
            options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            },
            cutout: '70%'
        }
        });

        // 2. Line Chart: Trend (Last 7 Days)
        const ctxTrend = document.getElementById('trendChart').getContext('2d');
        new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trendLabels); ?>,
            datasets: [{
                label: 'Nuevos Votantes',
                data: <?php echo json_encode($trendData); ?>,
                borderColor: '#E30613', // Primary Red
                backgroundColor: 'rgba(227, 6, 19, 0.1)',
                borderWidth: 3,
                tension: 0.4,
                fill: true,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#E30613',
                pointRadius: 5
                }]
            },
            options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
        });

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