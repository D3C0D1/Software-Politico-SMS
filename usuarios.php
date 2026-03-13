<?php
require_once 'config.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Access Control
if ($_SESSION['role'] === 'lider') {
    header("Location: registros.php");
    exit;
}

$error = '';
$success = '';

// Handle POST actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (isset($_POST['action'])) {
            $action = $_POST['action'];

            // Handle User Creation
            if ($action === 'add') { // Changed from 'create' to 'add' to match existing form action
                $username = trim($_POST['username'] ?? '');
                $password = trim($_POST['password'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $role = $_POST['role'] ?? 'votante';

                // Validation
                if (empty($username) || empty($password)) {
                    throw new Exception("Usuario y contraseña son obligatorios.");
                }
                else {
                    // Check duplicate
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if ($stmt->fetchColumn() > 0) {
                        throw new Exception("El usuario ya existe.");
                    }
                    else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $organizacion_id = $_SESSION['organizacion_id'] ?? 1;
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, name, organizacion_id) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$username, $hash, $role, $name, $organizacion_id]);
                        $new_user_id = $pdo->lastInsertId();

                        // --- AUTO-CREATE REGISTRO FOR LIDER ---
                        if ($role === 'lider') {
                            // Verify if a registro with this cedula (username) already exists to avoid duplicates
                            $stmtCheckReg = $pdo->prepare("SELECT id FROM registros WHERE cedula = ? AND organizacion_id = ?");
                            $stmtCheckReg->execute([$username, $organizacion_id]);

                            if ($stmtCheckReg->rowCount() == 0) {
                                $stmtReg = $pdo->prepare("INSERT INTO registros (nombres_apellidos, cedula, tipo, user_id, organizacion_id, created_at) VALUES (?, ?, 'lider', ?, ?, NOW())");
                                $stmtReg->execute([$name, $username, $new_user_id, $organizacion_id]);
                            }
                        }
                        // ---------------------------------------

                        header("Location: usuarios.php?msg=creado");
                        exit;
                    }
                }
            }
            elseif ($action === 'edit') {
                $id = $_POST['user_id'];
                $username = trim($_POST['username'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $role = $_POST['role'] ?? 'votante';
                $password = trim($_POST['password'] ?? '');

                if (empty($username)) {
                    throw new Exception("El nombre de usuario es requerido.");
                }

                $organizacion_id = $_SESSION['organizacion_id'] ?? 1;

                // Update with password - MUST include organizacion_id filter for safety
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ?, password = ? WHERE id = ? AND organizacion_id = ?");
                    $stmt->execute([$username, $name, $role, $hash, $id, $organizacion_id]);
                }
                else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, name = ?, role = ? WHERE id = ? AND organizacion_id = ?");
                    $stmt->execute([$username, $name, $role, $id, $organizacion_id]);
                }
                header("Location: usuarios.php?msg=editado");
                exit;
            }
            elseif ($action === 'delete') {
                $id = $_POST['user_id'];
                $organizacion_id = $_SESSION['organizacion_id'] ?? 1;

                if ($id == $_SESSION['user_id']) {
                    throw new Exception("No puedes eliminar tu propio usuario.");
                }

                // Obtener datos del usuario antes de eliminar
                $stmtUser = $pdo->prepare("SELECT username, role FROM users WHERE id = ? AND organizacion_id = ?");
                $stmtUser->execute([$id, $organizacion_id]);
                $userToDelete = $stmtUser->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND organizacion_id = ?");
                $stmt->execute([$id, $organizacion_id]);

                if ($stmt->rowCount() > 0) {
                    // Si era un líder, eliminar también su registro en la tabla registros
                    if ($userToDelete && $userToDelete['role'] === 'lider' && !empty($userToDelete['username'])) {
                        $stmtDelReg = $pdo->prepare("DELETE FROM registros WHERE cedula = ? AND tipo = 'lider' AND organizacion_id = ?");
                        $stmtDelReg->execute([$userToDelete['username'], $organizacion_id]);
                    }
                    header("Location: usuarios.php?msg=eliminado");
                    exit;
                }
                else {
                    throw new Exception("No tienes permisos para eliminar este usuario o el usuario no existe.");
                }
            }
        }
    }
    catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch users - only from same organization
$organizacion_id = $_SESSION['organizacion_id'] ?? 1;
$stmt = $pdo->prepare("SELECT * FROM users WHERE organizacion_id = ? ORDER BY id DESC");
$stmt->execute([$organizacion_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="assets/css/style.css">
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

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Overlay for mobile sidebar -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

        <div class="top-bar">
            <button class="menu-toggle" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>
            <h2 class="page-title">Gestión de Usuarios</h2>
            <div class="user-profile">
                <span>Hola, <strong>
                        <?php echo htmlspecialchars($_SESSION['name'] ?? $_SESSION['username']); ?>
                    </strong></span>
                <a href="logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>

        <div class="content-area">
            <?php if ($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php
endif; ?>

            <?php if ($success): ?>
            <div class="success-msg"
                style="padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
                <i class="fas fa-check"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
            <?php
endif; ?>

            <button class="btn btn-add" onclick="openModal('add')">
                <i class="fas fa-plus"></i> Nuevo Usuario
            </button>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($u['name'] ?? ''); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($u['username']); ?>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($u['role']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($u['role'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-edit btn-sm"
                                    onclick="openEditModal('<?php echo $u['id']; ?>', '<?php echo $u['username']; ?>', '<?php echo $u['role']; ?>', '<?php echo htmlspecialchars($u['name']); ?>')"><i
                                        class="fas fa-edit"></i></button>

                                <form method="POST" style="display:inline;"
                                    onsubmit="return confirm('¿Eliminar usuario?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button class="btn btn-sm btn-delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php
endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2 style="color: var(--primary-red);">Nuevo Usuario</h2>

            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="name" id="add-name" class="form-control">
                </div>

                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" name="username" id="add-username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Rol *</label>
                    <select name="role" class="form-control">
                        <option value="lider">Líder</option>
                        <option value="admin">Administrador</option>
                        <option value="operador">Operador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Contraseña *</label>
                    <input type="password" name="password" id="passwordInput" class="form-control" required>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#ddd; color:#333; margin-right: 10px;"
                        onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2 style="color: var(--primary-red);">Editar Usuario</h2>
            <form method="POST" class="modal-form">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="user_id" id="edit-id">

                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="name" id="edit-name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Usuario</label>
                    <input type="text" name="username" id="edit-username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select name="role" id="edit-role" class="form-control">
                        <option value="lider">Líder</option>
                        <option value="admin">Administrador</option>
                        <option value="operador">Operador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Contraseña (Opcional)</label>
                    <input type="password" name="password" class="form-control"
                        placeholder="Dejar vacía para no cambiar">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#ddd; color:#333; margin-right: 10px;"
                        onclick="closeEditModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add Modal
        var addModal = document.getElementById("addModal");
        function openModal(type) {
            if (type === 'add') {
                addModal.style.display = "block";
            }
        }
        function closeAddModal() {
            addModal.style.display = "none";
        }
        function closeModal() { // Alias for cancel button
            closeAddModal();
        }

        // Edit Modal
        var editModal = document.getElementById("editModal");
        function openEditModal(id, username, role, name) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-username').value = username;
            document.getElementById('edit-name').value = name;
            document.getElementById('edit-role').value = role;
            editModal.style.display = "block";
        }
        function closeEditModal() {
            editModal.style.display = "none";
        }

        // Close on outside click
        window.onclick = function (event) {
            if (event.target == addModal) {
                closeAddModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
        }

        // Check for URL parameters for SweetAlert
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');

        if (msg) {
            let title = '¡Éxito!';
            let text = 'Operación realizada correctamente';
            let icon = 'success';

            if (msg === 'creado') {
                title = '¡Usuario Creado!';
                text = 'El usuario ha sido registrado exitosamente.';
            } else if (msg === 'editado') {
                title = '¡Actualizado!';
                text = 'La información del usuario se ha guardado correctamente.';
            } else if (msg === 'eliminado') {
                title = '¡Eliminado!';
                text = 'El usuario ha sido eliminado correctamente.';
            }

            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonColor: '#E30613'
            }).then(() => {
                // Clean URL
                const newUrl = window.location.pathname;
                window.history.replaceState({}, document.title, newUrl);
            });
        }

        // Sidebar Toggle Logic
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-closed');
            document.body.classList.toggle('sidebar-open');
        }
    </script>
</body>

</html>