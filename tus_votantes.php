<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Force Cache Busting
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Tus Votantes - Partido Liberal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/sidebar-toggle.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-red: <?php echo htmlspecialchars($app_config['primary_color']);
            ?>;
            --primary-dark: <?php echo htmlspecialchars($app_config['primary_color']);
            ?>;
            --primary-rgb: <?php echo htmlspecialchars($app_config['primary_rgb']);
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
            <h2 class="page-title">Bienvenido al Dashboard</h2>
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
// Only Admins can access "Tus Votantes"
$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'];
$user_role = $_SESSION['role'] ?? 'votante';

if (!in_array($user_role, ['admin', 'superadmin'])) {
    header("Location: dashboard.php");
    exit;
}

// Admins see ONLY their created records in this specific view
$stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id, $org_id]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate Stats for Votantes
$totalVotantes = count($registros);
$votaron = 0;
$pendientes = 0;

foreach ($registros as $r) {
    if (isset($r['estado_voto']) && $r['estado_voto'] === 'voto') {
        $votaron++;
    }
    else {
        $pendientes++;
    }
}
?>

            <!-- Stats Cards for Registros Page -->
            <div class="dashboard-cards" style="margin-bottom: 30px;">
                <div class="card-stat">
                    <div class="card-icon icon-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="card-info">
                        <h3>
                            <?php echo $totalVotantes; ?>
                        </h3>
                        <p>Total Votantes</p>
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
            </div>

            <div class="action-buttons">
                <?php if (in_array($_SESSION['role'], ['admin', 'superadmin'])): ?>
                <button onclick="openCreateModal()" class="btn btn-add">
                    <i class="fas fa-plus"></i> Nuevo Registro
                </button>
                <?php
endif; ?>

                <?php if (in_array($_SESSION['role'], ['admin', 'superadmin'])): ?>
                <form id="importForm" style="display: inline;">
                    <input type="hidden" name="view" value="registros">
                    <label class="btn"
                        style="cursor: pointer; margin-bottom: 0; background-color: #1D6F42; color: white;">
                        <i class="fas fa-file-excel"></i> Importar Excel/CSV
                        <input type="file" name="file" id="importFile" accept=".csv, .xls, .xlsx" style="display: none;"
                            onchange="handleImport(this)">
                    </label>
                </form>
                <?php
endif; ?>

                <a href="enviar_sms.php?type=all" class="btn"
                    style="background-color: var(--primary-red); color: white;">
                    <i class="fas fa-comment-dots"></i> Enviar SMS Global
                </a>
                <a href="enviar_sms.php?type=pending" class="btn" style="background-color: #ff9800; color: white;">
                    <i class="fas fa-comment-slash"></i> Enviar SMS a Pendientes
                </a>

                <button onclick="deleteAllVoters()" class="btn"
                    style="background-color: #dc3545; color: white; margin-left: 10px;">
                    <i class="fas fa-trash-alt"></i> Eliminar Todos
                </button>
            </div>
            <div class="table-responsive">
                <h3><i class="fas fa-list"></i> Tus Votantes</h3>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
                    <div style="display: flex; gap: 10px; width: 100%; max-width: 600px;">
                        <div style="position: relative; flex: 1;">
                            <i class="fas fa-search"
                                style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #999; font-size: 1.1em;"></i>
                            <input type="text" id="searchInput" class="form-control"
                                placeholder="Buscar por nombre, cédula..." onkeyup="filterTable()"
                                style="padding-left: 45px; border-radius: 25px; border: 2px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transition: all 0.3s ease;">
                        </div>
                        <div style="position: relative; width: 200px;">
                            <select id="filterVoto" class="form-control" onchange="filterTable()"
                                style="border-radius: 25px; border: 2px solid #e0e0e0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding-left: 15px; cursor: pointer; background-color: white;">
                                <option value="">Cualquier Estado</option>
                                <option value="VOTO">Estado: Votó</option>
                                <option value="PENDIENTE">Estado: Pendiente</option>
                                <option value="REVISADO">Ya Votó: Revisado</option>
                                <option value="NO REVISADO">Ya Votó: Pendiente</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <button onclick="exportSelectedExcel()" class="btn"
                            style="background-color: #1D6F42; color: white;" title="Exportar Seleccionados a Excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button onclick="exportSelectedPDF()" class="btn"
                            style="background-color: #dc3545; color: white;" title="Exportar Seleccionados a PDF">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                </div>

                <style>
                    #searchInput:focus {
                        border-color: var(--primary-red);
                        box-shadow: 0 4px 12px rgba(var(--primary-rgb), 0.15);
                        outline: none;
                    }
                </style>

                <table id="votantesTable">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;">
                                <input type="checkbox" id="selectAll" onclick="toggleAll(this)"
                                    title="Seleccionar Todos">
                            </th>
                            <th>Número</th>
                            <th>Nombres y Apellidos</th>
                            <th>Link Confirmación</th>
                            <th>Cédula</th>
                            <th>Lugar de Votación</th>
                            <th>Mesa</th>
                            <th>Celular</th>
                            <th>Estado</th>
                            <th>Ya Votó</th>
                            <th>SMS Inscripción</th>
                            <th>SMS Citación</th>
                            <th>SMS Confirmación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="registrosTableBody">
                        <?php include 'ajax_tus_votantes_table.php'; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button onclick="exportSelectedExcel()" class="btn" style="background-color: #1D6F42; color: white;">
                    <i class="fas fa-file-excel"></i> Exportar Excel
                </button>
                <button onclick="exportSelectedPDF()" class="btn" style="background-color: #dc3545; color: white;">
                    <i class="fas fa-file-pdf"></i> Exportar PDF
                </button>
            </div>

            <!-- FLOATING EXPORT BUTTONS -->
            <div
                style="position: fixed; right: 20px; bottom: 80px; display: flex; flex-direction: column; gap: 10px; z-index: 1000;">
                <button onclick="exportSelectedExcel()" class="btn"
                    style="background-color: #1D6F42; color: white; border-radius: 50%; width: 50px; height: 50px; padding: 0; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.3);"
                    title="Exportar Seleccionados a Excel">
                    <i class="fas fa-file-excel" style="font-size: 1.5rem; margin:0;"></i>
                </button>
                <button onclick="exportSelectedPDF()" class="btn"
                    style="background-color: #dc3545; color: white; border-radius: 50%; width: 50px; height: 50px; padding: 0; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 8px rgba(0,0,0,0.3);"
                    title="Exportar Seleccionados a PDF">
                    <i class="fas fa-file-pdf" style="font-size: 1.5rem; margin:0;"></i>
                </button>
            </div>

        </div>
    </div>

    <!-- PDF Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>

    <script>
        window.jsPDF = window.jspdf.jsPDF;

        function exportTableToPDF() {
            var doc = new jsPDF('l', 'mm', 'a4'); // Landscape orientation

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
                doc.text('Tus Votantes', 50, 28);

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

                // Export Table
                doc.autoTable({
                    html: '#votantesTable',
                    startY: 45,
                    theme: 'grid',
                    styles: {
                        fontSize: 8,
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
                    // Exclude SMS columns (8,9,10), Link (11) and Actions (12)
                    columns: [0, 1, 2, 3, 4, 5, 6, 7],
                    columnStyles: {
                        0: { halign: 'center', cellWidth: 12 },
                        2: { halign: 'center' },
                        4: { halign: 'center' },
                        7: { halign: 'center' }
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

                doc.save('votantes_reporte.pdf');
            };

            img.onerror = function () {
                // If logo fails to load, generate PDF without it
                console.warn('Logo no disponible, generando PDF sin logo');
                generateVotantesPDFWithoutLogo();
            };
        }

        // Fallback function without logo
        function generateVotantesPDFWithoutLogo() {
            var doc = new jsPDF('l', 'mm', 'a4');
            doc.setTextColor(227, 6, 19);
            doc.setFontSize(18);
            doc.setFont(undefined, 'bold');
            doc.text('<?php echo htmlspecialchars($app_config['app_title']); ?> - Tus Votantes', 14, 22);
            doc.setTextColor(0, 0, 0);
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            doc.text('Generado el: ' + new Date().toLocaleDateString(), 14, 30);

            doc.autoTable({
                html: '#votantesTable',
                startY: 40,
                theme: 'grid',
                styles: { fontSize: 8 },
                headStyles: { fillColor: [227, 6, 19] },
                columns: [0, 1, 2, 3, 4, 5, 6, 7]
            });

            doc.save('votantes_reporte.pdf');
        }

        // --- EXPERIMENTAL: EXPORT AND FILTERS ---
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
                window.location.href = "export_excel.php?view=registros"; // Using registros view for simple generic exports
            } else {
                window.location.href = "export_excel.php?view=registros&ids=" + ids.join(',');
            }
        }

        // PDF Export (Seleccionados)
        function exportSelectedPDF() {
            var doc = new jsPDF('l', 'mm', 'a4'); // Landscape for more columns
            doc.setFontSize(18);
            doc.text('Tus Votantes - <?php echo htmlspecialchars($app_config['app_title'] ?? 'Partido Liberal'); ?>', 14, 22);
            doc.setFontSize(11);
            doc.text('Generado el: ' + new Date().toLocaleDateString(), 14, 30);

            let ids = getSelectedIds();
            let sourceHtml = '#votantesTable';

            // Si hay seleccionados, clonamos la tabla para filtrar visualmente el PDF
            if (ids.length > 0) {
                let tempTable = document.createElement('table');
                tempTable.innerHTML = document.getElementById('votantesTable').innerHTML;
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
                styles: { fontSize: 7, cellPadding: 2 },
                headStyles: { fillColor: [227, 6, 19] },
                // Ignorar la primera columna (checkbox) y las últimas (SMS, Link, Acciones) si aplica
                // La exportación manual ya se maneja en autoTable, adaptaremos columnas en PDF si es requerido
            });
            doc.save('tus_votantes_reporte.pdf');
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
                if (row && row.offsetHeight > 0) {
                    e.target.checked = dragCheckState;
                }
            }
        });

        document.addEventListener('mouseup', function () {
            isDragging = false;
        });
        // ------------------------------------------------------------

        function filterTable() {
            var input = document.getElementById("searchInput");
            var filter = input.value.toUpperCase();
            var selectVoto = document.getElementById("filterVoto").value.toUpperCase();

            var table = document.getElementById("votantesTable");
            var tr = table.getElementsByTagName("tbody")[0].getElementsByTagName("tr");

            for (var i = 0; i < tr.length; i++) {
                var visibleText = false;
                var visibleVoto = true;

                // 1. Text Search across relevant columns (Nombres 3, Cedula 4, Lugar 5, Mesa 6, Celular 7)
                var columnsToCheck = [2, 4, 5, 6, 7];
                if (filter === "") {
                    visibleText = true;
                } else {
                    for (var j = 0; j < columnsToCheck.length; j++) {
                        var colIndex = columnsToCheck[j];
                        var td = tr[i].getElementsByTagName("td")[colIndex];
                        if (td) {
                            var txtValue = td.textContent || td.innerText;
                            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                                visibleText = true;
                                break;
                            }
                        }
                    }
                }

                // 2. Voto Filter
                if (selectVoto !== "") {
                    // Check Estado Voto (Column 8: Votó o Pendiente)
                    // Check Ya Voto (Column 9: Revisado o Pendiente)
                    var tdEstado = tr[i].getElementsByTagName("td")[8];
                    var tdYaVoto = tr[i].getElementsByTagName("td")[9];

                    if (tdEstado && tdYaVoto) {
                        var txtEstado = (tdEstado.textContent || tdEstado.innerText).toUpperCase().trim();
                        var txtYaVoto = (tdYaVoto.textContent || tdYaVoto.innerText).toUpperCase().trim();

                        if (selectVoto === "VOTO" && txtEstado.indexOf("VOT") === -1) {
                            visibleVoto = false;
                        } else if (selectVoto === "PENDIENTE" && txtEstado.indexOf("PENDIENTE") === -1) {
                            visibleVoto = false;
                        } else if (selectVoto === "REVISADO" && txtYaVoto.indexOf("REVISADO") === -1) {
                            visibleVoto = false;
                        } else if (selectVoto === "NO REVISADO" && txtYaVoto.indexOf("PENDIENTE") === -1) {
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

        // Auto-refresh control - pause when searching
        let isSearching = false;
        let lastSearchTime = 0;
        const searchInput = document.getElementById('searchInput');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                isSearching = true;
                lastSearchTime = Date.now();
            });

            searchInput.addEventListener('focus', function () {
                isSearching = true;
            });

            searchInput.addEventListener('blur', function () {
                setTimeout(function () {
                    if (Date.now() - lastSearchTime > 4000) {
                        isSearching = false;
                    }
                }, 4000);
            });
        }



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
                title = '¡Votante Creado!';
                text = 'El votante ha sido registrado exitosamente.';
            } else if (msg === 'editado') {
                title = '¡Actualizado!';
                text = 'La información del votante se ha guardado correctamente.';
            } else if (msg === 'eliminado') {
                title = '¡Eliminado!';
                text = 'El registro ha sido eliminado correctamente.';
            }

            Swal.fire({
                icon: icon,
                title: title,
                text: text,
                confirmButtonColor: '<?php echo $app_config['primary_color']; ?>'
            }).then(() => {
                    // Clean URL
                    const newUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, newUrl);
                });
        }

        // Sidebar Toggle Logic
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-closed'); // For desktop collapse
            document.body.classList.toggle('sidebar-open');   // For mobile open
        }

        // Real-time Update (Polling every 3 seconds)
        let isUpdating = false;
        function updateRegistrosTable() {
            const hasSearchTerm = searchInput && searchInput.value.trim() !== '';
            if (isUpdating || isSearching || hasSearchTerm) return; // Prevent overlapping requests or updates while searching
            isUpdating = true;

            fetch('ajax_tus_votantes_table.php')
                .then(response => response.text())
                .then(html => {
                    const tbody = document.getElementById('registrosTableBody');
                    if (tbody.innerHTML !== html) {
                        // Only update if content changed (simple check, could be optimized)
                        tbody.innerHTML = html;
                    }
                    isUpdating = false;
                })
                .catch(err => {
                    console.error('Error auto-updating table:', err);
                    isUpdating = false;
                });
        }

        // Start polling every 10 seconds
        Interval(updateRegistrosTable, 10000);
    </script>

    <!-- Create Voter Modal -->
    <div id="createVoterModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h2 style="color: var(--primary-red);">Nuevo Votante</h2>
            <form id="createVoterForm" class="modal-form" onsubmit="handleCreateVoter(event)">
                <div class="form-group">
                    <label>Nombres y Apellidos</label>
                    <input type="text" name="nombres_apellidos" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Cédula</label>
                    <input type="number" name="cedula" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Lugar de Votación</label>
                    <input type="text" name="lugar_votacion" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Mesa</label>
                    <input type="text" name="mesa" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Celular</label>
                    <input type="tel" name="celular" class="form-control" required pattern="[0-9]{10}"
                        title="Número de 10 dígitos">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#ddd; color:#333; margin-right: 10px;"
                        onclick="closeCreateModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnSaveVoter">Guardar Registro</button>
                </div>
            </form>
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
                <!-- Add other fields as needed -->
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
        // Create Voter Modal Logic
        const createModal = document.getElementById("createVoterModal");

        function openCreateModal() {
            createModal.style.display = "block";
            document.getElementById("createVoterForm").reset();
        }

        function closeCreateModal() {
            createModal.style.display = "none";
        }

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == createModal) {
                closeCreateModal();
            }
            // ... (keep existing dropdown closing logic if needed, or rely on existing handler)
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
                    Swal.fire({ title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

                    fetch('ajax_send_link_sms.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: id, phone: phone, link: linkToSent })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Enviado', data.message, 'success');
                                updateRegistrosTable(); // Refresh to show green check
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

        function sendSmsAll() {
            if (confirm("¿Estás seguro de enviar SMS a TODOS los registros? Esta acción no se puede deshacer.")) {
                window.location.href = "enviar_sms.php?type=all";
            }
        }

        function handleCreateVoter(e) {
            e.preventDefault();

            const form = document.getElementById('createVoterForm');
            const formData = new FormData(form);
            const btn = document.getElementById('btnSaveVoter');

            // Send Checkbox state explicitly if needed, but FormData handles named checkboxes if checked.
            // If unchecked, it's missing. ajax_crear_votante handles logic.

            // Loading state
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            fetch('ajax_crear_votante.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar Registro';

                    if (data.success) {
                        closeCreateModal();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Registro Creado!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        // Refresh table immediately
                        updateRegistrosTable();
                        // Reload page after delay to ensure stats update fully or just rely on updateRegistrosTable logic + stats logic (if separate)
                        // The user wants "no note cuanto se recarga", so let's rely on the smooth AJAX updateRegistrosTable() 
                        // But we might need to update stats too.
                        location.reload(); // Simplest way to update stats + table 100% correctly without complex DOM manipulation for now, or trust the interval.
                        // Actually, let's trust the interval or manually trigger updates.
                        // updateRegistrosTable() only updates the table.
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btn.innerHTML = 'Guardar Registro';
                    Swal.fire('Error', 'Error de conexión', 'error');
                });
        }

        function sendSmsIndividual(id, celular, nombre) {
            // Manejar sobrecarga antigua (solo celular) si por alguna razón no se actualizó el botón
            if (arguments.length === 1) {
                if (confirm("¿Enviar SMS a " + celular + "?")) {
                    window.location.href = "enviar_sms.php?type=single&phone=" + celular;
                }
                return;
            }

            if (confirm("¿Enviar SMS a " + nombre + " (" + celular + ")?")) {
                window.location.href = "enviar_sms.php?type=single&id=" + id + "&phone=" + celular + "&name=" + encodeURIComponent(nombre);
            }
        }

        // --- IMPORT LOGIC ---
        let currentTempFile = '';

        function handleImport(input) {
            if (input.files.length === 0) return;
            const file = input.files[0];
            const formData = new FormData();
            formData.append('file', file);
            formData.append('view', 'registros');

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
                body: JSON.stringify({ temp_file: currentTempFile, mode: mode, view: 'registros' })
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

        function deleteAllVoters() {
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción ELIMINARÁ TODOS LOS VOTANTES asociados a ti. ¡No podrás revertir esto!",
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
                            window.location.href = 'eliminar_todo.php?type=votante';
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