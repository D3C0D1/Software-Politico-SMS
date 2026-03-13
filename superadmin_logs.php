<?php
require_once 'config.php';

// Access Control: ONLY Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filters
$filter_org = isset($_GET['org_id']) ? $_GET['org_id'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

$whereClauses = [];
$params = [];

if ($filter_org) {
    $whereClauses[] = "l.organizacion_id = ?";
    $params[] = $filter_org;
}

if ($filter_status) {
    $whereClauses[] = "l.status = ?";
    $params[] = $filter_status;
}

$whereSQL = "";
if (count($whereClauses) > 0) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

// Fetch Logs
try {
    // Count total for pagination
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM sms_logs l $whereSQL");
    $stmtCount->execute($params);
    $totalLogs = $stmtCount->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    // Fetch Data
    // Join with organizations table to get names
    $sql = "
        SELECT l.*, o.nombre_organizacion, u.username 
        FROM sms_logs l
        LEFT JOIN organizaciones o ON l.organizacion_id = o.id
        LEFT JOIN users u ON l.user_id = u.id
        $whereSQL
        ORDER BY l.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Orgs for Filter Dropdown
    $orgs = $pdo->query("SELECT id, nombre_organizacion FROM organizaciones ORDER BY nombre_organizacion ASC")->fetchAll(PDO::FETCH_ASSOC);

}
catch (Exception $e) {
    $error = "Error al cargar logs: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Logs de SMS - Super Admin</title>
    <link rel="stylesheet" href="assets/css/superadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .log-table th,
        .log-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .log-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: bold;
        }

        .status-success {
            background: #dcfce7;
            color: #166534;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .filter-bar select,
        .filter-bar button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .filter-bar button {
            background: #2563eb;
            color: white;
            border: none;
            cursor: pointer;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .page-link {
            padding: 5px 10px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }

        .page-link.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
    </style>
</head>

<body>

    <div class="superadmin-layout">
        <aside class="super-sidebar">
            <div class="super-sidebar-header">
                <h2>SISTEMA ELITE</h2>
                <div style="font-size: 0.75rem; opacity: 0.6; margin-top: 0.5rem;">Gestión de Multi-Tenancy</div>
            </div>
            <nav class="super-nav">
                <a href="superadmin_dashboard.php" class="super-nav-item">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="superadmin_organizations.php" class="super-nav-item">
                    <i class="fas fa-briefcase"></i> Organizaciones
                </a>
                <a href="superadmin_logs.php" class="super-nav-item active">
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
                <h1>Historial de Envíos SMS</h1>
                <div class="super-user-info">
                    <span>Sesión Admin: <strong>Dios</strong></span>
                    <a href="logout.php" class="btn-logout-super">Cerrar Sesión</a>
                </div>
            </div>

            <div class="super-content-area">

                <!-- Filters -->
                <form class="filter-bar" method="GET">
                    <select name="org_id">
                        <option value="">Todas las Organizaciones</option>
                        <?php foreach ($orgs as $o): ?>
                        <option value="<?php echo $o['id']; ?>" <?php echo ($filter_org==$o['id']) ? 'selected' : '' ;
                            ?>>
                            <?php echo htmlspecialchars($o['nombre_organizacion']); ?>
                        </option>
                        <?php
endforeach; ?>
                    </select>

                    <select name="status">
                        <option value="">Todos los Estados</option>
                        <option value="success" <?php echo ($filter_status=='success' ) ? 'selected' : '' ; ?>>Exitosos
                        </option>
                        <option value="failed" <?php echo ($filter_status=='failed' ) ? 'selected' : '' ; ?>>Fallidos
                        </option>
                    </select>

                    <button type="submit"><i class="fas fa-filter"></i> Filtrar</button>
                    <?php if ($filter_org || $filter_status): ?>
                    <a href="superadmin_logs.php"
                        style="color: #666; text-decoration: underline; font-size: 0.9rem;">Limpiar</a>
                    <?php
endif; ?>
                </form>

                <div class="orgs-table-container">
                    <?php if (empty($logs)): ?>
                    <div style="padding: 20px; text-align: center; color: #666;">No hay registros de SMS encontrados.
                    </div>
                    <?php
else: ?>
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Organización</th>
                                <th>Usuario</th>
                                <th>Destinatario</th>
                                <th>Mensaje</th>
                                <th>Estado</th>
                                <th>Respuesta API</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td><strong>
                                        <?php echo htmlspecialchars($log['nombre_organizacion'] ?? 'N/A'); ?>
                                    </strong></td>
                                <td>
                                    <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['recipient_phone']); ?>
                                </td>
                                <td title="<?php echo htmlspecialchars($log['message']); ?>">
                                    <?php echo htmlspecialchars(substr($log['message'], 0, 50)) . (strlen($log['message']) > 50 ? '...' : ''); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $log['status']; ?>">
                                        <?php echo $log['status']; ?>
                                    </span>
                                </td>
                                <td style="font-family: monospace; font-size: 0.8rem; color: #666;">
                                    <?php echo htmlspecialchars(substr($log['response_data'], 0, 30)); ?>...
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&org_id=<?php echo $filter_org; ?>&status=<?php echo $filter_status; ?>"
                            class="page-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php
        endfor; ?>
                    </div>
                    <?php
    endif; ?>

                    <?php
endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>

</html>