<?php
require_once 'config.php';

// Access Control: ONLY Superadmin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header("Location: index.php");
    exit;
}

$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- ARCHIVE / UNARCHIVE ORGANIZATION ---
    if (isset($_POST['action']) && $_POST['action'] === 'archive') {
        $org_id = $_POST['org_id'];
        $new_status = ($_POST['current_status'] === 'active') ? 'archived' : 'active';

        if ($org_id == 1) {
            $error = "La Organización Principal no puede ser archivada.";
        }
        else {
            try {
                $stmt = $pdo->prepare("UPDATE organizaciones SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $org_id]);
                $message = ($new_status === 'archived') ? "Organización archivada exitosamente." : "Organización reactivada.";

                // Log Action
                logSystemAction($pdo, $_SESSION['user_id'], $org_id, 'org_status_change', "Cambió estado de organización ID $org_id a '$new_status'");
            }
            catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }

    // --- EDIT ORGANIZATION ---
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $org_id = $_POST['org_id'];
        $new_name = trim($_POST['org_name']);

        if (!empty($new_name)) {
            try {
                $stmt = $pdo->prepare("UPDATE organizaciones SET nombre_organizacion = ? WHERE id = ?");
                $stmt->execute([$new_name, $org_id]);
                $message = "Organización actualizada.";

                // Log Action
                logSystemAction($pdo, $_SESSION['user_id'], $org_id, 'org_update_name', "Actualizó nombre de organización ID $org_id a '$new_name'");
            }
            catch (Exception $e) {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Fetch Organizations with Stats
$stmt = $pdo->query("
    SELECT o.*, 
    (SELECT COUNT(*) FROM users u WHERE u.organizacion_id = o.id) as total_users,
    (SELECT COUNT(*) FROM registros r WHERE r.organizacion_id = o.id AND r.tipo = 'votante') as total_voters,
    (SELECT COUNT(*) FROM registros r WHERE r.organizacion_id = o.id AND r.tipo = 'lider') as total_leaders,
    (SELECT SUM(sms_inscripcion + sms_citacion + sms_confirmacion) FROM registros r WHERE r.organizacion_id = o.id) as sms_credits
    FROM organizaciones o
    ORDER BY o.status ASC, o.id DESC
");
$orgs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Gestión de Organizaciones - Super Admin</title>
    <link rel="stylesheet" href="assets/css/superadmin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <a href="superadmin_organizations.php" class="super-nav-item active">
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
                <h1>Gestión de Organizaciones</h1>
                <div class="super-user-info">
                    <span>Sesión Admin: <strong>Dios</strong></span>
                    <a href="logout.php" class="btn-logout-super">Cerrar Sesión</a>
                </div>
            </div>

            <div class="super-content-area">
                <?php if ($message): ?>
                <div
                    style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border: 1px solid #bbf7d0;">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $message; ?>
                </div>
                <?php
endif; ?>

                <?php if ($error): ?>
                <div
                    style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border: 1px solid #fecaca;">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php
endif; ?>

                <div class="orgs-table-container">
                    <h2>Lista de Clientes / Organizaciones</h2>
                    <table class="orgs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Organización</th>
                                <th>Estado</th>
                                <th>Líderes</th>
                                <th>Votantes</th>
                                <th>SMS Consumidos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orgs as $org): ?>
                            <tr class="<?php echo ($org['status'] === 'archived') ? 'status-archived' : ''; ?>">
                                <td>#
                                    <?php echo $org['id']; ?>
                                </td>
                                <td><strong>
                                        <?php echo htmlspecialchars($org['nombre_organizacion']); ?>
                                    </strong></td>
                                <td>
                                    <span class="status-<?php echo $org['status']; ?>">
                                        <i
                                            class="fas <?php echo ($org['status'] === 'active') ? 'fa-check' : 'fa-archive'; ?>"></i>
                                        <?php echo ($org['status'] === 'active') ? 'Activa' : 'Archivada'; ?>
                                    </span>
                                </td>
                                <td><span class="metric-badge leaders">
                                        <?php echo $org['total_leaders']; ?>
                                    </span></td>
                                <td><span class="metric-badge voters">
                                        <?php echo $org['total_voters']; ?>
                                    </span></td>
                                <td><span class="metric-badge credits">
                                        <?php echo (int)$org['sms_credits']; ?>
                                    </span></td>
                                <td>
                                    <button
                                        onclick="openEditModal(<?php echo $org['id']; ?>, '<?php echo htmlspecialchars($org['nombre_organizacion']); ?>')"
                                        class="btn-super edit">
                                        <i class="fas fa-edit"></i>
                                    </button>

                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="archive">
                                        <input type="hidden" name="org_id" value="<?php echo $org['id']; ?>">
                                        <input type="hidden" name="current_status"
                                            value="<?php echo $org['status']; ?>">
                                        <button type="submit" class="btn-super archive">
                                            <i
                                                class="fas <?php echo ($org['status'] === 'active') ? 'fa-archive' : 'fa-box-open'; ?>"></i>
                                            <?php echo ($org['status'] === 'active') ? 'Archivar' : 'Reactivar'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php
endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Simple Edit Modal -->
    <div id="editModal"
        style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
        <div
            style="background:white; margin:10% auto; padding:2rem; width:400px; border-radius:0.5rem; position:relative;">
            <span onclick="closeEditModal()"
                style="position:absolute; right:1rem; top:1rem; cursor:pointer; font-size:1.5rem;">&times;</span>
            <h2>Editar Nombre</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="org_id" id="edit_org_id">
                <div style="margin-top:1rem;">
                    <label>Nombre de Organización</label>
                    <input type="text" name="org_name" id="edit_org_name"
                        style="width:100%; padding:0.5rem; margin-top:0.5rem; border:1px solid #ccc; border-radius:0.25rem;">
                </div>
                <div style="margin-top:1.5rem; display:flex; gap:1rem;">
                    <button type="submit" class="btn-super edit" style="flex:1;">Guardar Cambios</button>
                    <button type="button" onclick="closeEditModal()" class="btn-super" style="flex:1;">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, name) {
            document.getElementById('edit_org_id').value = id;
            document.getElementById('edit_org_name').value = name;
            document.getElementById('editModal').style.display = 'block';
        }
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>

</body>

</html>