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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registros Totales - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
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
            <h2 class="page-title">Base de Datos Completa</h2>
            <div class="user-profile">
                <span>Hola, <strong>
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
            <?php
// Filter for ALL, join with users to get leader name
// Filter for ALL in organization
$stmt = $pdo->prepare("
    SELECT r.*, u.username as leader_username, u.name as leader_name 
    FROM registros r 
    LEFT JOIN users u ON r.user_id = u.id 
    WHERE r.organizacion_id = :org_id
    ORDER BY r.created_at DESC
");
$stmt->execute(['org_id' => $_SESSION['organizacion_id']]);
$todos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats
$totalTotal = count($todos);
$lideresCount = 0;
$votantesCount = 0;
$hanVotado = 0;
$pendientesCount = 0;

foreach ($todos as $t) {
    // Count Types
    if (isset($t['tipo']) && $t['tipo'] === 'lider') {
        $lideresCount++;
    }
    else {
        $votantesCount++;
    }

    // Count Votes
    if (isset($t['estado_voto']) && $t['estado_voto'] === 'voto') {
        $hanVotado++;
    }
    else {
        $pendientesCount++;
    }
}
?>

            <!-- Stats Cards for Lideres Page -->
            <div class="dashboard-cards five-cols" style="margin-bottom: 30px;">
                <div class="card-stat">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $totalTotal; ?>
                        </h3>
                        <p>Total Registros</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-orange">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $lideresCount; ?>
                        </h3>
                        <p>Líderes</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $votantesCount; ?>
                        </h3>
                        <p>Votantes</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-green" style="background: #28a745; color: white;">
                        <i class="fas fa-vote-yea"></i>
                    </div>
                    <div class="card-info">
                        <h3 id="statHanVotado">
                            <?php echo $hanVotado; ?>
                        </h3>
                        <p>Ya Votaron</p>
                    </div>
                </div>

                <div class="card-stat">
                    <div class="card-icon icon-red" style="background: #dc3545; color: white;">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="card-info">
                        <h3 id="statPendientes">
                            <?php echo $pendientesCount; ?>
                        </h3>
                        <p>Pendientes</p>
                    </div>
                </div>
            </div>

            <div class="action-buttons" style="display: flex; align-items: center; flex-wrap: wrap;">
                <!-- No 'Add' button here typically, as it's a view-all page -->



                <!-- Import Form with Smart Check -->


                <a href="enviar_sms.php?type=all" class="btn"
                    style="background-color: var(--primary-red); color: white;">
                    <i class="fas fa-comment-dots"></i> Enviar SMS Global
                </a>
                <a href="enviar_sms.php?type=pending" class="btn"
                    style="background-color: #ff9800; color: white; margin-left: 10px;">
                    <i class="fas fa-comment-slash"></i> Enviar SMS a Pendientes
                </a>

                <button onclick="deleteAllRecords()" class="btn"
                    style="background-color: #dc3545; color: white; margin-left: 10px;">
                    <i class="fas fa-trash-alt"></i> Eliminar Todos
                </button>

                <!-- Botones Flotantes de Exportar (Circulares a la derecha) -->
                <div
                    style="position: fixed; right: 20px; top: 50%; transform: translateY(-50%); display: flex; flex-direction: column; gap: 15px; z-index: 1000;">
                    <button onclick="exportSelectedExcel()" class="btn" title="Exportar Excel"
                        style="background-color: #1D6F42; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); padding: 0; font-size: 24px; border: none; cursor: pointer; transition: transform 0.2s;">
                        <i class="fas fa-file-excel"></i>
                    </button>
                    <button onclick="exportSelectedPDF()" class="btn" title="Exportar PDF"
                        style="background-color: #dc3545; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); padding: 0; font-size: 24px; border: none; cursor: pointer; transition: transform 0.2s;">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <h3 style="margin: 0;"><i class="fas fa-globe"></i> Base de Datos Completa</h3>
                    <div style="display: flex; gap: 10px;">
                        <button onclick="exportSelectedExcel()" class="btn"
                            style="background-color: #1D6F42; color: white; padding: 8px 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </button>
                        <button onclick="exportSelectedPDF()" class="btn"
                            style="background-color: #dc3545; color: white; padding: 8px 15px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-pdf"></i> Exportar PDF
                        </button>
                    </div>
                </div>





                <!-- BUSCADOR EN TIEMPO REAL Y FILTROS -->
                <div class="filters-container"
                    style="display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;">

                    <!-- Buscador de texto -->
                    <div style="position: relative; flex-grow: 1; min-width: 280px;">
                        <i class="fas fa-search"
                            style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                        <input type="text" id="searchInput" onkeyup="filterTable()"
                            placeholder="Buscar por Nombre, Cédula, Celular o Líder..." class="form-control"
                            style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box;">
                    </div>

                    <!-- Filtro por Líder -->
                    <div style="min-width: 220px; flex-grow: 1;">
                        <div style="position: relative;">
                            <i class="fas fa-user-tie"
                                style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                            <select id="filterLider" onchange="filterTable()" class="form-control"
                                style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; background-color: white; box-sizing: border-box;">
                                <option value="">Filtrar líderes y votantes (Todos)</option>
                                <option value="ONLY_LIDERES">Filtrar todos los líderes</option>
                                <optgroup label="Filtrar líder específico">
                                    <?php
$lideresDropdown = [];
foreach ($todos as $t) {
    $leaderName = trim($t['leader_name'] ? $t['leader_name'] : ($t['leader_username'] ? $t['leader_username'] : 'Directo'));
    if (!empty($leaderName) && !in_array($leaderName, $lideresDropdown)) {
        $lideresDropdown[] = $leaderName;
    }
}
sort($lideresDropdown);
foreach ($lideresDropdown as $ld) {
    echo '<option value="' . htmlspecialchars($ld) . '">' . htmlspecialchars($ld) . '</option>';
}
?>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- Filtro por Estado de Voto -->
                    <div style="min-width: 200px; flex-grow: 1;">
                        <div style="position: relative;">
                            <i class="fas fa-vote-yea"
                                style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                            <select id="filterVoto" onchange="filterTable()" class="form-control"
                                style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; background-color: white; box-sizing: border-box;">
                                <option value="">Cualquier Estado</option>
                                <option value="VOTO">Votó</option>
                                <option value="PENDIENTE">Pendiente</option>
                            </select>
                        </div>
                    </div>
                </div>

                <table id="todosTable">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;" title="Seleccionar Todos">
                                <input type="checkbox" id="selectAll" onclick="toggleAll(this)"
                                    title="Seleccionar Todos">
                            </th>
                            <th>Número</th>
                            <th>Líder Resp.</th>
                            <th>Tipo</th>
                            <th>Nombres y Apellidos</th>
                            <th>Cédula</th>
                            <th>Celular</th>
                            <th>Estado</th>
                            <th>Ya Votó</th>
                            <th>Link Confirmación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="todosTableBody">
                        <?php include 'ajax_todos_registros_table.php'; ?>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button onclick="exportSelectedExcel()" class="btn"
                    style="background-color: #1D6F42; color: white; padding: 8px 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button onclick="exportSelectedPDF()" class="btn"
                    style="background-color: #dc3545; color: white; padding: 8px 15px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
            </div>
        </div>
    </div>

    <!-- PDF Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

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
        window.jsPDF = window.jspdf.jsPDF;

        // Selección de filas
        function toggleAll(source) {
            let checkboxes = document.querySelectorAll('.row-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
                // Solo seleccionar si la fila está visible (no filtrada por búsqueda)
                let row = checkboxes[i].closest('tr');
                if (row && row.offsetHeight > 0) {
                    checkboxes[i].checked = source.checked;
                }
            }
        }

        function getSelectedIds() {
            let ids = [];
            document.querySelectorAll('.row-checkbox:checked').forEach(function (cb) {
                ids.push(cb.value);
            });
            return ids;
        }

        // Excel Export (Seleccionados)
        function exportSelectedExcel() {
            let ids = getSelectedIds();
            if (ids.length === 0) {
                window.location.href = "export_excel.php?view=todos";
            } else {
                window.location.href = "export_excel.php?view=todos&ids=" + ids.join(',');
            }
        }

        // PDF Export (Seleccionados)
        function exportSelectedPDF() {
            var doc = new jsPDF('l', 'mm', 'a4'); // Landscape for more columns
            doc.setFontSize(18);
            doc.text('Base de Datos Completa - <?php echo htmlspecialchars($app_config['app_title'] ?? 'Partido Liberal'); ?>', 14, 22);
            doc.setFontSize(11);
            doc.text('Generado el: ' + new Date().toLocaleDateString(), 14, 30);

            let ids = getSelectedIds();
            let sourceHtml = '#todosTable';

            // Si hay seleccionados, clonamos la tabla para filtrar visualmente el PDF
            if (ids.length > 0) {
                let tempTable = document.createElement('table');
                tempTable.innerHTML = document.getElementById('todosTable').innerHTML;
                let rows = tempTable.querySelectorAll('tbody tr');
                rows.forEach(function (row) {
                    let cb = row.querySelector('.row-checkbox');
                    if (!cb || !cb.checked) {
                        row.remove();
                    }
                });
                sourceHtml = tempTable;
            }

            doc.autoTable({
                html: sourceHtml,
                startY: 40,
                theme: 'grid',
                styles: { fontSize: 7 },
                headStyles: { fillColor: [227, 6, 19] }
            });
            doc.save('base_datos_completa.pdf');
        }

        // --- SISTEMA DE SELECCIÓN POR ARRASTRE (DRAG TO SELECT) ---
        let isDragging = false;
        let dragCheckState = false;

        document.addEventListener('mousedown', function (e) {
            if (e.target.classList.contains('row-checkbox')) {
                isDragging = true;
                // Si el usuario da click, e.target.checked ya ha cambiado de estado por el propio click,
                // por lo que debemos asumir ese nuevo estado para el resto del arrastre.
                dragCheckState = e.target.checked;
            }
        });

        document.addEventListener('mouseover', function (e) {
            if (isDragging && e.target.classList.contains('row-checkbox')) {
                let row = e.target.closest('tr');
                if (row.style.display !== "none") {
                    e.target.checked = dragCheckState;
                }
            }
        });

        document.addEventListener('mouseup', function () {
            isDragging = false;
        });
        // ------------------------------------------------------------





        // Copy link to clipboard
        function copyLink(link) {
            navigator.clipboard.writeText(link).then(function () {
                alert('✓ Link copiado al portapapeles!\n\nPuede enviarlo por WhatsApp o SMS al votante.');
            }, function (err) {
                // Fallback for older browsers
                const textArea = document.createElement("textarea");
                textArea.value = link;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('✓ Link copiado al portapapeles!');
            });
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

        function sendSmsAll() {
            if (confirm("¿Estás seguro de enviar SMS a TODOS los registros de la base de datos?")) {
                window.location.href = "enviar_sms.php?type=all&target=all";
            }
        }

        // Función SweetAlert para confirmar eliminación
        window.confirmDelete = function (url) {
            Swal.fire({
                title: '¿Está seguro?',
                text: "¡No podrá revertir esta acción!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#E30613',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        };

        // Check for URL parameters for SweetAlert
        const urlParams = new URLSearchParams(window.location.search);
        const msg = urlParams.get('msg');

        if (msg) {
            let title = '¡Éxito!';
            let text = 'Operación realizada correctamente';
            let icon = 'success';

            if (msg === 'creado') {
                title = '¡Registro Creado!';
                text = 'Se ha añadido un nuevo registro a la base de datos.';
            } else if (msg === 'editado') {
                title = '¡Actualizado!';
                text = 'La información del registro se ha guardado correctamente.';
            } else if (msg === 'eliminado') {
                title = '¡Eliminado!';
                text = 'El registro ha sido eliminado correctamente.';
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

        // Function to send confirmation link via SMS
        function sendSmsLink(id, phone, linkToSent) {
            if (!phone) {
                Swal.fire('Error', 'No hay número de celular asociado.', 'error');
                return;
            }

            Swal.fire({
                title: 'Enviar Enlace de Confirmación',
                text: "¿Enviar SMS con el link al número: " + phone + "?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Sí, enviar SMS',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading()
                    });

                    fetch('ajax_send_link_sms.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, phone: phone, link: linkToSent })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Enviado', data.message, 'success');
                                // Refresh table or reload to show checkmark
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
                        });
                }
            });
        }

        // Start polling
        // Start polling
        // setInterval(updateAllData, 30000); // 30 seconds - Commope
    </script>



    <script>
        // --- IMPORT LOGIC ---
        let currentTempFile = '';

        function handleImport(input) {
            if (input.files.length === 0) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('view', 'todos');

            Swal.fire({ title: 'Analizando archivo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            fetch('ajax_check_import.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        input.value = '';
                        return;
                    }

                    currentTempFile = data.temp_file;

                    // Automatically proceed with 'new_only' (Skip duplicates) as per requirement "no permita importar"
                    executeImport('new_only');
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Error de conexión', 'error');
                    input.value = '';
                });
        }

        function executeImport(mode) {
            fetch('execute_import.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ temp_file: currentTempFile, mode: mode, view: 'todos' })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (data.skipped > 0 || (data.skipped_details && data.skipped_details.length > 0)) {
                            // Show Report Modal
                            showSkippedReport(data.imported, data.skipped, data.skipped_details);
                        } else {
                            Swal.fire({
                                icon: 'success',
                                title: 'Importación Finalizada',
                                text: `Se importaron ${data.imported} registros correctamente.`
                            }).then(() => location.reload());
                        }
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Error al ejecutar importación', 'error');
                });
        }

        function showSkippedReport(imported, skipped, details) {
            const tbody = document.getElementById('skippedTableBody');
            tbody.innerHTML = '';

            if (details && details.length > 0) {
                details.forEach(d => {
                    tbody.innerHTML += `
                        <tr>
                            <td style="padding:8px; border-bottom:1px solid #eee;">${d.cedula}</td>
                            <td style="padding:8px; border-bottom:1px solid #eee;">${d.nombre}</td>
                            <td style="padding:8px; border-bottom:1px solid #eee; color: #dc3545;">${d.reason}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="3">Varios registros fueron omitidos por duplicidad.</td></tr>';
            }

            document.getElementById('importReportModal').style.display = 'block';
            document.getElementById('reportSummary').innerText = `Se importaron ${imported} registros. Se omitieron ${skipped} registros que ya existen o tienen errores.`;
        }

        function closeReportModal() {
            document.getElementById('importReportModal').style.display = 'none';
            document.getElementById('importFile').value = '';
            location.reload();
        }

        function deleteAllRecords() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción ELIMINARÁ TODOS LOS REGISTROS (Líderes y Votantes) de la organización. ¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confuttonText: 'Sí, eliminar todo',
                cance 'Cancelar'
            }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Confirmación final',
                            text: "Escribe 'ELIMINAR' para confirmar.",
                            input: 'text',
                            showCancelButton: true,
                            confirmButtonText: 'Confirmar',
                            showLoaderOnConfirm: true,
                            preConfirm: (text) => {
                                if (text !== 'ELIMINAR') {
                                    Swal.showValidationMessage('Debes escribir ELIMINAR para continuar')
                                }
                                return text === 'ELIMINAR';
                            }
                        }).then((result2) => {
                            if (result2.isConfirmed) {
                                window.location.href = 'eliminar_todo.php?type=todos';
                            }
                        });
                    }
                })
        }
    </script>

    <!-- Import Report Modal -->
    <div id="importReportModal" class="modal"
        style="display:none; position:fixed; z-index:9999; top:0; left:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5);">
        <div class="modal-content"
            style="background-color:#fefefe; margin:5% auto; padding:20px; border:1px solid #888; width:80%; max-width:800px; border-radius:10px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); animation: fadeIn 0.3s;">
            <span class="close" onclick="closeReportModal()"
                style="float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
            <h2 style="color:var(--primary-red); margin-bottom: 10px;">
                <i class="fas fa-info-circle"></i> Reporte de Importación
            </h2>
            <p id="reportSummary" style="font-size: 1.1em; margin-bottom: 15px;"></p>

            <h4 style="color: #555;">Registros Omitidos (Duplicados / Errores):</h4>
            <div
                style="max-height:300px; overflow-y:auto; border:1px solid #ddd; margin-bottom:20px; border-radius: 5px;">
                <table class="table" style="width:100%; border-collapse:collapse;">
                    <thead style="background:#f8f9fa;">
                        <tr>
                            <th style="padding:10px; border-bottom:2px solid #ddd; text-align: left;">Cédula</th>
                            <th style="padding:10px; border-bottom:2px solid #ddd; text-align: left;">Nombre</th>
                            <th style="padding:10px; border-bottom:2px solid #ddd; text-align: left;">Razón</th>
                        </tr>
                    </thead>
                    <tbody id="skippedTableBody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>

            <div style="text-align:right;">
                <button onclick="closeReportModal()" class="btn"
                    style="background:var(--primary-red); color:white; padding: 10px 20px; border-radius: 5px;">Cerrar y
                    Actualizar</button>
            </div>
        </div>
    </div>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <script>
        function filterTable() {
            var input, filter, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();

            var selectLider = document.getElementById("filterLider").value.toUpperCase();
            var selectVoto = document.getElementById("filterVoto").value.toUpperCase();

            table = document.getElementById("todosTable");
            tr = table.getElementsByTagName("tr");

            // Loop through all table rows, and hide those who don't match the search query
            for (i = 1; i < tr.length; i++) { // Start at 1 to skip header
                var visibleText = false;
                var visibleLider = true;
                var visibleVoto = true;

                // 1. Check Text Filter
                var columnsToCheck = [2, 4, 5, 6];
                if (filter === "") {
                    visibleText = true;
                } else {
                    for (var j = 0; j < columnsToCheck.length; j++) {
                        var colIndex = columnsToCheck[j];
                        td = tr[i].getElementsByTagName("td")[colIndex];
                        if (td) {
                            txtValue = td.textContent || td.innerText;
                            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                                visibleText = true;
                                break; // If match found in one column, show row and stop checking other columns
                            }
                        }
                    }
                }

                // 2. Check Lider Filter
                if (selectLider !== "") {
                    if (selectLider === "ONLY_LIDERES") {
                        var tdTipo = tr[i].getElementsByTagName("td")[3]; // Columna 3: Tipo
                        if (tdTipo) {
                            var tipoText = (tdTipo.textContent || tdTipo.innerText).toUpperCase().trim();
                            // El tipo es 'Líder' pero puede tener acentos, busquemos substring
                            if (tipoText.indexOf("LÍDER") === -1 && tipoText.indexOf("LIDER") === -1) {
                                visibleLider = false;
                            }
                        }
                    } else {
                        td = tr[i].getElementsByTagName("td")[2]; // Columna 2: Lider Resp
                        if (td) {
                            txtValue = (td.textContent || td.innerText).toUpperCase().trim();
                            if (txtValue.indexOf(selectLider) === -1) {
                                visibleLider = false;
                            } else {
                                // Si hizo match con el líder específico, ocultar si la fila en sí misma es un LÍDER (solo queremos ver sus votantes)
                                var tdTipoInfo = tr[i].getElementsByTagName("td")[3]; // Columna 3: Tipo
                                if (tdTipoInfo) {
                                    var tipoTextInfo = (tdTipoInfo.textContent || tdTipoInfo.innerText).toUpperCase().trim();
                                    if (tipoTextInfo.indexOf("LÍDER") !== -1 || tipoTextInfo.indexOf("LIDER") !== -1) {
                                        visibleLider = false;
                                    }
                                }
                            }
                        }
                    }
                }

                // 3. Check Voto Filter
                if (selectVoto !== "") {
                    td = tr[i].getElementsByTagName("td")[7]; // Coa 7o
                    if (td) {
                        txtValue = (td.textContent || td.innerText).toUpperCase().trim();
                        if (selectVoto === "VOTO" && txtValue.indexOf("VOT") === -1) {
                            visibleVoto = false;
                        } else if (selectVoto === "PENDIENTE" && txtValue.indexOf("PENDIENTE") === -1) {
                            visibleVoto = false;
                        }
                    }
                }

                if (visibleText && visibleLider && visibleVoto) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }
    </script>
</body>

</html>