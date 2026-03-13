<?php
require_once 'config.php';

// Access Control: ONLY Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

// Global Stats (Real but aggregated)
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM organizaciones WHERE status = 'active'");
    $totalOrgs = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM registros WHERE tipo = 'votante'");
    $totalVoters = $stmt->fetchColumn();
}
catch (Exception $e) {
    $totalOrgs = 0;
    $totalUsers = 0;
    $totalVoters = 0;
}

// Top Organizations by Voters (All Time)
try {
    $stmt = $pdo->query("
        SELECT o.nombre_organizacion, COUNT(r.id) as total
        FROM organizaciones o
        LEFT JOIN registros r ON o.id = r.organizacion_id AND r.tipo = 'votante'
        WHERE o.status = 'active'
        GROUP BY o.id, o.nombre_organizacion
        ORDER BY total DESC
        LIMIT 5
    ");
    $topOrgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e) {
    $topOrgs = [];
}

// Recent Performance (Last 7 Days)
try {
    $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    $stmt = $pdo->prepare("
        SELECT o.nombre_organizacion, COUNT(r.id) as recent_total
        FROM organizaciones o
        JOIN registros r ON o.id = r.organizacion_id
        WHERE r.tipo = 'votante' AND r.created_at >= ?
        GROUP BY o.id, o.nombre_organizacion
        ORDER BY recent_total DESC
        LIMIT 5
    ");
    $stmt->execute([$weekAgo]);
    $recentOrgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e) {
    $recentOrgs = [];
}

// Chart Data (All Organizations)
try {
    $stmt = $pdo->query("
        SELECT o.nombre_organizacion, COUNT(r.id) as total
        FROM organizaciones o
        LEFT JOIN registros r ON o.id = r.organizacion_id AND r.tipo = 'votante'
        WHERE o.status = 'active'
        GROUP BY o.id, o.nombre_organizacion
        ORDER BY total DESC
    ");
    $chartDataRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orgNames = json_encode(array_column($chartDataRaw, 'nombre_organizacion'));
    $orgCounts = json_encode(array_column($chartDataRaw, 'total'));
}
catch (Exception $e) {
    $orgNames = '[]';
    $orgCounts = '[]';
}

// Metrics for Cards
$topOrgName = !empty($topOrgs) ? $topOrgs[0]['nombre_organizacion'] : 'N/A';
$topOrgCount = !empty($topOrgs) ? $topOrgs[0]['total'] : 0;

$bestRecentName = !empty($recentOrgs) ? $recentOrgs[0]['nombre_organizacion'] : 'N/A';
$bestRecentCount = !empty($recentOrgs) ? $recentOrgs[0]['recent_total'] : 0;

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Modo Dios</title>
    <link rel="stylesheet" href="assets/css/superadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>

    <div class="superadmin-layout">
        <aside class="super-sidebar">
            <div class="super-sidebar-header">
                <h2>SISTEMA ELITE</h2>
                <div style="font-size: 0.75rem; opacity: 0.6; margin-top: 0.5rem;">Gestión de Multi-Tenancy</div>
            </div>
            <nav class="super-nav">
                <a href="superadmin_dashboard.php" class="super-nav-item active">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="superadmin_organizations.php" class="super-nav-item">
                    <i class="fas fa-briefcase"></i> Organizaciones
                </a>
                <a href="superadmin_logs.php" class="super-nav-item">
                    <i class="fas fa-sms"></i> Logs SMS
                </a>
                <a href="superadmin_system_logs.php" class="super-nav-item">
                    <i class="fas fa-server"></i> Logs del Sistema
                </a>
                <a href="superadmin_config.php" class="super-nav-item">
                    <i class="fas fa-cog"></i> Configuración Global
                </a>
            </nav>
        </aside>

        <main class="super-main-content">
            <div class="super-topbar">
                <h1>Panel de Control Global</h1>
                <div class="super-user-info">
                    <span>Sesión Admin: <strong>Dios</strong></span>
                    <a href="logout.php" class="btn-logout-super">Cerrar Sesión</a>
                </div>
            </div>

            <div class="super-content-area">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-label">Organizaciones Activas</div>
                        <div class="stat-card-value">
                            <?php echo $totalOrgs; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Votantes Globales</div>
                        <div class="stat-card-value">
                            <?php echo number_format($totalVoters); ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Top Organización (Global)</div>
                        <div class="stat-card-value" style="font-size: 1.5rem; color: #10b981;">
                            <?php echo htmlspecialchars($topOrgName); ?>
                            <div style="font-size: 1rem; color: #6b7280; margin-top: 5px;">
                                <?php echo $topOrgCount; ?> votantes
                            </div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-card-label">Mejor Reciente (7 días)</div>
                        <div class="stat-card-value" style="font-size: 1.5rem; color: #3b82f6;">
                            <?php echo htmlspecialchars($bestRecentName); ?>
                            <div style="font-size: 1rem; color: #6b7280; margin-top: 5px;">
                                +
                                <?php echo $bestRecentCount; ?> nuevos
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                    <div class="stat-card">
                        <h3>Distribución de Votantes por Organización</h3>
                        <canvas id="mainChart" style="max-height: 300px;"></canvas>
                    </div>
                    <div class="stat-card">
                        <h3>Métricas Rapidas</h3>
                        <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                            <li style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f0f0f0;">
                                <strong>Total Votantes:</strong>
                                <?php echo number_format($totalVoters); ?>
                            </li>
                            <li style="margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid #f0f0f0;">
                                <strong>Organizaciones Activas:</strong>
                                <?php echo $totalOrgs; ?>
                            </li>
                            <li>
                                <strong>Promedio por Org:</strong>
                                <?php echo ($totalOrgs > 0) ? round($totalVoters / $totalOrgs) : 0; ?>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Nuevas Tablas de Ranking -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                    <div class="stat-card">
                        <h3>🏆 Top 5 Organizaciones (Total)</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                                    <th style="padding: 0.5rem;">Organización</th>
                                    <th style="padding: 0.5rem; text-align: right;">Votantes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topOrgs as $org): ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 0.5rem;">
                                        <?php echo htmlspecialchars($org['nombre_organizacion']); ?>
                                    </td>
                                    <td style="padding: 0.5rem; text-align: right;"><strong>
                                            <?php echo number_format($org['total']); ?>
                                        </strong></td>
                                </tr>
                                <?php
endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="stat-card">
                        <h3>🚀 Mejor Rendimiento (7 Días)</h3>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                            <thead>
                                <tr style="text-align: left; border-bottom: 2px solid #f3f4f6;">
                                    <th style="padding: 0.5rem;">Organización</th>
                                    <th style="padding: 0.5rem; text-align: right;">Nuevos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrgs as $org): ?>
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td style="padding: 0.5rem;">
                                        <?php echo htmlspecialchars($org['nombre_organizacion']); ?>
                                    </td>
                                    <td style="padding: 0.5rem; text-align: right; color: #10b981;"><strong>+
                                            <?php echo number_format($org['recent_total']); ?>
                                        </strong></td>
                                </tr>
                                <?php
endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        const ctx = document.getElementById('mainChart').getContext('2d');
        const orgNames = <? php echo $orgNames; ?>;
        const orgCounts = <? php echo $orgCounts; ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: orgNames,
                datasets: [{
                    label: 'Votantes Registrados',
                    data: orgCounts,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(139, 92, 246, 0.7)'
                    ],
                    borderColor: [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Comparativa de Votantes por Organización'
                    }
                }
            }
        });
    </script>

</body>

</html>