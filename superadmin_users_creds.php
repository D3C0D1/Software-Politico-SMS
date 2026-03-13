<?php
require_once 'config.php';

// Access Control: ONLY Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

// Fetch all users with role 'admin' (Lideres) and their organization
// We focus on users who have 'onurix_client' and 'onurix_key' fields in users table.
// If your schema uses separate table for credentials, adjust accordingly.
// Based on configuraciones.php, credentials are stored in 'users' table.

$leaders = [];
try {
    // Join with organizaciones to get org name
    // Filter by role='admin' or just check if credentials are not empty
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.nombre, u.apellido, u.onurix_client, u.onurix_key, o.nombre_organizacion 
        FROM users u 
        LEFT JOIN organizaciones o ON u.organizacion_id = o.id
        WHERE u.role = 'admin' AND u.onurix_client IS NOT NULL AND u.onurix_client != ''
        ORDER BY o.nombre_organizacion ASC
    ");
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Also fetch those who might not have credentials set but are admins, to show empty state if needed?
// User asked for "ver credenciales demo me meuestre todas las credenciales en tiempo real"
// "quiero que me muestre las credenciales de los lideres de cada organizacion"
}
catch (Exception $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}

// Function to check balance for a specific user
function checkBalance($client, $key)
{
    if (empty($client) || empty($key))
        return 'N/A';

    $url = "https://www.onurix.com/api/v1/balance?client=" . $client . "&key=" . $key;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $json = json_decode($response, true);
        return isset($json['balance']) ? number_format($json['balance']) : "Error API";
    }
    return "Error ($httpCode)";
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Credenciales de Líderes - Super Admin</title>
    <link rel="stylesheet" href="assets/css/superadmin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .creds-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .creds-table th,
        .creds-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .creds-table th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
        }

        .creds-table tr:hover {
            background-color: #f1f5f9;
        }

        .sensitive-data {
            font-family: monospace;
            background: #f1f5f9;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #334155;
            display: inline-block;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
        }

        .sensitive-data:hover {
            max-width: none;
            position: relative;
            z-index: 10;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .badge-balance {
            background: #e0f2fe;
            color: #0284c7;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.85rem;
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
                <a href="superadmin_logs.php" class="super-nav-item">
                    <i class="fas fa-sms"></i> Logs SMS
                </a>
                <a href="superadmin_system_logs.php" class="super-nav-item">
                    <i class="fas fa-server"></i> Logs del Sistema
                </a>
                <a href="superadmin_config.php" class="super-nav-item active">
                    <i class="fas fa-cog"></i> Configuración Global
                </a>
            </nav>
        </aside>

        <main class="super-main-content">
            <div class="super-topbar">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <a href="superadmin_config.php" style="color: #64748b; text-decoration: none;"><i
                            class="fas fa-arrow-left"></i> Volver</a>
                    <h1 style="margin: 0; font-size: 1.125rem;">Credenciales de Líderes (Onurix)</h1>
                </div>
                <div class="super-user-info">
                    <span>Sesión Admin: <strong>Dios</strong></span>
                    <a href="logout.php" class="btn-logout-super">Cerrar Sesión</a>
                </div>
            </div>

            <div class="super-content-area">
                <div
                    style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-info-circle"></i> Aquí se muestran las credenciales configuradas por los
                    administradores (Líderes) de cada organización.
                    La consulta de saldo se realiza en tiempo real al cargar esta página.
                </div>

                <div class="table-responsive">
                    <table class="creds-table">
                        <thead>
                            <tr>
                                <th>Organización</th>
                                <th>Líder (Admin)</th>
                                <th>Client ID</th>
                                <th>Client Key</th>
                                <th>Saldo Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($leaders) > 0): ?>
                            <?php foreach ($leaders as $leader): ?>
                            <?php $balance = checkBalance($leader['onurix_client'], $leader['onurix_key']); ?>
                            <tr>
                                <td><strong>
                                        <?php echo htmlspecialchars($leader['nombre_organizacion'] ?? 'Sin Organización'); ?>
                                    </strong></td>
                                <td>
                                    <?php echo htmlspecialchars($leader['nombre'] . ' ' . $leader['apellido']); ?><br>
                                    <small style="color: #94a3b8;">@
                                        <?php echo htmlspecialchars($leader['username']); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="sensitive-data" title="Clic para copiar"
                                        onclick="navigator.clipboard.writeText(this.innerText); alert('Copiado');">
                                        <?php echo htmlspecialchars($leader['onurix_client']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="sensitive-data" title="Clic para copiar"
                                        onclick="navigator.clipboard.writeText(this.innerText); alert('Copiado');">
                                        <?php echo htmlspecialchars($leader['onurix_key']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-balance">
                                        <?php echo $balance; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php
    endforeach; ?>
                            <?php
else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 30px; color: #94a3b8;">
                                    No hay líderes con credenciales de Onurix configuradas.
                                </td>
                            </tr>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>

</html>