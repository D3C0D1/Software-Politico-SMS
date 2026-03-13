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
    <title>Gestión de Líderes - Partido Liberal</title>
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
            <h2 class="page-title">Gestión de Líderes</h2>
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
// Show all Lideres (Since only Admin/Operador can access this page)
// Show all Lideres with their voter count
// We link Lider (registros) -> User (users, via cedula=username) -> Votantes (registros, via user_id)
// Show all Lideres of current organization
// We link Lider (registros) -> User (users, via cedula=username) -> Votantes (registros, via user_id)
$sql = "
    SELECT l.*, 
           (SELECT COUNT(*) 
            FROM registros v 
            JOIN users u ON v.user_id = u.id 
            WHERE u.username = l.cedula AND v.tipo = 'votante' AND v.organizacion_id = :org_id_inner) as total_votantes
    FROM registros l 
    WHERE l.tipo = 'lider' AND l.organizacion_id = :org_id_outer
    ORDER BY l.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'org_id_inner' => $_SESSION['organizacion_id'],
    'org_id_outer' => $_SESSION['organizacion_id']
]);
$lideres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats for Lideres
$totalLideres = count($lideres);
?>

            <!-- Stats Cards for Lideres Page -->
            <div class="dashboard-cards" style="margin-bottom: 30px;">
                <div class="card-stat">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-crown"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $totalLideres; ?>
                        </h3>
                        <p>Total Líderes</p>
                    </div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="registro_form.php?type=lider" class="btn btn-add">
                    <i class="fas fa-plus"></i> Nuevo Líder
                </a>



                <a href="enviar_sms.php?type=all" class="btn"
                    style="background-color: var(--primary-red); color: white;">
                    <i class="fas fa-comment-dots"></i> Enviar SMS
                </a>

                <button onclick="deleteAllLeaders()" class="btn"
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
                    <h3 style="margin: 0;"><i class="fas fa-users-crown"></i> Listado de Líderes</h3>
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
                            placeholder="Buscar por Nombre, Cédula, Lugar, Mesa o Celular..." class="form-control"
                            style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box;">
                    </div>

                    <!-- Filtro por Estado de Voto -->
                    <div style="min-width: 200px; flex-grow: 1;">
                        <div style="position: relative;">
                            <i class="fas fa-vote-yea"
                                style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                            <select id="filterVoto" onchange="filterTable()" class="form-control"
                                style="width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; background-color: white; box-sizing: border-box;">
                                <option value="">Cualquier Estado de Voto</option>
                                <option value="VOTO">Votó</option>
                                <option value="PENDIENTE">Pendiente</option>
                            </select>
                        </div>
                    </div>
                </div>

                <style>
                    #searchInput:focus {
                        border-color: var(--primary-red);
                        box-shadow: 0 4px 12px rgba(227, 6, 19, 0.15);
                        outline: none;
                    }
                </style>

                <table id="lideresTable">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;" title="Seleccionar Todos">
                                <input type="checkbox" id="selectAll" onclick="toggleAll(this)"
                                    title="Seleccionar Todos">
                            </th>
                            <th>Número</th>
                            <th>Nombres y Apellidos</th>
                            <th>Cédula</th>
                            <th>Lugar de Votación</th>
                            <th>Mesa</th>
                            <th>Celular</th>
                            <th>Estado Voto</th>
                            <th>Votantes</th>
                            <th>SMS Inscripción</th>
                            <th>SMS Citación</th>
                            <th>SMS Confirmación</th>
                            <th>Planilla</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($lideres) > 0): ?>
                        <?php foreach ($lideres as $lider): ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" class="row-checkbox" value="<?php echo $lider['id']; ?>">
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['id']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['nombres_apellidos']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['cedula']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['lugar_votacion']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['mesa']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($lider['celular']); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php
        if (isset($lider['estado_voto'])) {
            if ($lider['estado_voto'] === 'voto') {
                echo '<span style="color: #28a745; font-weight: bold;"><i class="fas fa-check"></i> VOTÓ</span>';
            }
            else {
                echo '<span style="color: #dc3545; font-weight: bold;"><i class="fas fa-clock"></i> PENDIENTE</span>';
            }
        }
        else {
            echo '-';
        }
?>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge"
                                    style="background: #007bff; color: white; padding: 5px 10px; border-radius: 20px; font-weight: bold;">
                                    <?php echo $lider['total_votantes'] ?? 0; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if (isset($lider['sms_inscripcion']) && $lider['sms_inscripcion'] == 1): ?>
                                <i class="fas fa-check-circle" style="color: #28a745; font-size: 1.2em;"></i>
                                <?php
        else: ?>
                                <i class="fas fa-times-circle" style="color: #dc3545; font-size: 1.2em;"></i>
                                <?php
        endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (isset($lider['sms_citacion']) && $lider['sms_citacion'] == 1): ?>
                                <i class="fas fa-check-circle" style="color: #28a745; font-size: 1.2em;"></i>
                                <?php
        else: ?>
                                <i class="fas fa-times-circle" style="color: #dc3545; font-size: 1.2em;"></i>
                                <?php
        endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <?php if (isset($lider['sms_confirmacion']) && $lider['sms_confirmacion'] == 1): ?>
                                <i class="fas fa-check-circle" style="color: #28a745; font-size: 1.2em;"></i>
                                <?php
        else: ?>
                                <i class="fas fa-times-circle" style="color: #dc3545; font-size: 1.2em;"></i>
                                <?php
        endif; ?>
                            </td>
                            <td style="text-align: center;">
                                <a href="generar_planilla_pdf.php?cedula=<?php echo urlencode($lider['cedula']); ?>"
                                    class="btn btn-sm" style="background-color: #dc3545; color: white;"
                                    title="Descargar Planilla PDF" target="_blank">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </a>
                            </td>
                            <td>
                                <a href="registro_form.php?id=<?php echo $lider['id']; ?>&type=lider"
                                    class="btn btn-edit btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="eliminar_registro.php?id=<?php echo $lider['id']; ?>&return=lideres"
                                    class="btn btn-delete btn-sm"
                                    onclick="event.preventDefault(); window.confirmDelete ? confirmDelete(this.href) : window.location.href=this.href;"
                                    title="Eliminar"><i class="fas fa-trash"></i></a>
                                <button class="btn btn-secondary btn-sm"
                                    onclick="sendSmsIndividual('<?php echo $lider['id']; ?>', '<?php echo $lider['celular']; ?>', '<?php echo htmlspecialchars($lider['nombres_apellidos'], ENT_QUOTES); ?>')"
                                    title="Enviar SMS"><i class="fas fa-sms"></i></button>
                            </td>
                        </tr>
                        <?php
    endforeach; ?>
                        <?php
else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No hay líderes registrados.</td>
                        </tr>
                        <?php
endif; ?>
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

    <!-- PDF Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

    <script>
        window.jsPDF = window.jspdf.jsPDF;

        // PDF Export with Logo
        function exportTableToPDF() {
            var doc = new jsPDF('l', 'mm', 'a4'); // Landscape for better fit

            // Load logo and generate PDF
            var img = new Image();
            img.src = '<?php echo htmlspecialchars($app_config['logo_path']); ?>';

            img.onload = function () {
                // Add logo (top left)
                doc.addImage(img, 'PNG', 14, 10, 30, 30);

                // Add title with red color
                doc.setTextColor(227, 6, 19); // Party red color
                doc.setFontSize(20);
                doc.setFont(undefined, 'bold');
                doc.text('<?php echo htmlspecialchars(strtoupper($app_config['app_title'])); ?>', 50, 20);

                // Subtitle
                doc.setFontSize(16);
                doc.text('Reporte de Líderes', 50, 28);

                // Date in black
                doc.setTextColor(0, 0, 0);
                doc.setFontSize(10);
                doc.setFont(undefined, 'normal');
                doc.text('Generado el: ' + new Date().toLocaleDateString('es-CO', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }), 50, 35);

                // Add table
                doc.autoTable({
                    html: '#lideresTable',
                    startY: 45,
                    theme: 'grid',
                    styles: {
                        fontSize: 9,
                        cellPadding: 3,
                        lineColor: [200, 200, 200],
                        lineWidth: 0.1
                    },
                    headStyles: {
                        fillColor: [227, 6, 19], // Party red
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    alternateRowStyles: {
                        fillColor: [245, 245, 245]
                    },
                    // Export only relevant info: Num, Name, ID, Place, Table, Phone, Voter Count
                    columns: [1, 2, 3, 4, 5, 6, 8],
                    columnStyles: {
                        1: { halign: 'center', cellWidth: 15 },
                        3: { halign: 'center' },
                        5: { halign: 'center' },
                        8: { halign: 'center' }
                    }
                });

                // Footer
                var pageCount = doc.internal.getNumberOfPages();
                for (var i = 1; i <= pageCount; i++) {
                    doc.setPage(i);
                    doc.setFontSize(8);
                    doc.setTextColor(128, 128, 128);
                    doc.text('Página ' + i + ' de ' + pageCount, doc.internal.pageSize.getWidth() / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
                    doc.text('Sistema de Gestión Electoral - Partido Liberal', 14, doc.internal.pageSize.getHeight() - 10);
                }

                doc.save('lideres_reporte.pdf');
            };

            img.onerror = function () {
                // If logo fails to load, generate PDF without it
                console.warn('Logo no disponible, generando PDF sin logo');
                generatePDFWithoutLogo();
            };
        }

        // Fallback function without logo
        function generatePDFWithoutLogo() {
            var doc = new jsPDF('l', 'mm', 'a4');
            doc.setTextColor(227, 6, 19);
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('<?php echo htmlspecialchars($app_config['app_title']); ?> - Reporte de Líderes', 14, 22);
            doc.setTextColor(0, 0, 0);
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text('Generado el: ' + new Date().toLocaleDateString(), 14, 30);

            doc.autoTable({
                html: '#lideresTable',
                startY: 40,
                theme: 'grid',
                styles: { fontSize: 10, cellPadding: 2 },
                headStyles: { fillColor: [227, 6, 19] },
                columns: [1, 2, 3, 4, 5, 6, 8]
            });
            doc.save('lideres_reporte.pdf');
        }

        // Checkbox & Export Logic
        function toggleAll(source) {
            let checkboxes = document.querySelectorAll('.row-checkbox');
            for (let i = 0; i < checkboxes.length; i++) {
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

        function exportSelectedExcel() {
            let ids = getSelectedIds();
            if (ids.length === 0) {
                window.location.href = "export_excel.php?view=lideres";
            } else {
                window.location.href = "export_excel.php?view=lideres&ids=" + ids.join(',');
            }
        }

        function exportSelectedPDF() {
            let ids = getSelectedIds();

            // Backup table
            let backupTable = document.getElementById('lideresTable').cloneNode(true);

            // If checkboxes selected, hide rows not checked before PDF generation
            if (ids.length > 0) {
                let rows = document.getElementById('lideresTable').querySelectorAll('tbody tr');
                rows.forEach(function (row) {
                    let cb = row.querySelector('.row-checkbox');
                    if (!cb || !cb.checked) {
                        row.style.display = 'none';
                    }
                });
            }

            exportTableToPDF(); // Uses original function pointing to '#lideresTable'

            // Restore table rows after generation
            setTimeout(() => {
                document.getElementById('lideresTable').innerHTML = backupTable.innerHTML;
                filterTable(); // Reapply filters
            }, 500);
        }

        // --- SISTEMA DE SELECCIÓN POR ARRASTRE (DRAG TO SELECT) ---
        let isDragging = false;
        let dragCheckState = false;

        document.addEventListener('mousedown', function (e) {
            if (e.target.classList.contains('row-checkbox')) {
                isDragging = true;
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

        function filterTable() {
            var input, filter, selectVoto, table, tr, td, i, txtValue;
            input = document.getElementById("searchInput");
            filter = input.value.toUpperCase();
            selectVoto = document.getElementById("filterVoto").value.toUpperCase();

            table = document.getElementById("lideresTable");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                var visibleText = false;
                var visibleVoto = true;

                // Columns for Text: 2: Name, 3: ID, 4: Place, 5: Mesa, 6: Celular
                var columnsToCheck = [2, 3, 4, 5, 6];
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
                                break;
                            }
                        }
                    }
                }

                if (selectVoto !== "") {
                    td = tr[i].getElementsByTagName("td")[7]; // Columna 7: Estado
                    if (td) {
                        txtValue = (td.textContent || td.innerText).toUpperCase().trim();
                        if (selectVoto === "VOTO" && txtValue.indexOf("VOT") === -1) {
                            visibleVoto = false;
                        } else if (selectVoto === "PENDIENTE" && txtValue.indexOf("PENDIENTE") === -1) {
                            visibleVoto = false;
                        }
                    }
                }

                if (visibleText && visibleVoto) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
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
            if (confirm("¿Estás seguro de enviar SMS a TODOS los líderes? Esta acción no se puede deshacer.")) {
                window.location.href = "enviar_sms.php?type=all&target=lider";
            }
        }

        function sendSmsIndividual(id, celular, nombre) {
            if (arguments.length === 1) {
                if (confirm("¿Enviar SMS a " + id + "?")) {
                    window.location.href = "enviar_sms.php?type=single&phone=" + id;
                }
                return;
            }

            if (confirm("¿Enviar SMS a " + nombre + " (" + celular + ")?")) {
                window.location.href = "enviar_sms.php?type=single&id=" + id + "&phone=" + celular + "&name=" + encodeURIComponent(nombre);
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
                title = '¡Líder Creado!';
                text = 'El líder ha sido registrado exitosamente.';
            } else if (msg === 'editado') {
                title = '¡Actualizado!';
                text = 'La información del líder se ha guardado correctamente.';
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

        // --- IMPORT LOGIC ---
        let currentTempFile = '';

        function handleImport(input) {
            if (input.files.length === 0) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('view', 'lideres');

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

                    // Automatically proceed with 'new_only' (Skip duplicates)
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
                body: JSON.stringify({ temp_file: currentTempFile, mode: mode, view: 'lideres' })
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

            // Also update summary text
            document.getElementById('reportSummary').innerText = `Se importaron ${imported} registros. Se omitieron ${skipped} registros que ya existen o tienen errores.`;
        }

        function closeReportModal() {
            document.getElementById('importReportModal').style.display = 'none';
            document.getElementById('importFile').value = '';
            location.reload();
        }

        function deleteAllLeaders() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción ELIMINARÁ TODOS LOS LÍDERES de la organización. ¡No podrás revertir esto!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Double check
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
                            window.location.href = 'eliminar_todo.php?type=lider';
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

</body>

</html>