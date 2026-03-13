<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    exit('Acceso denegado');
}

$view = $_GET['view'] ?? 'registros';
$filename = "exportacion_" . $view . "_" . date('Y-m-d') . ".xls";

// Configurar Headers para descarga Excel
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Lógica de Consulta Filtrada por Organización
$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'] ?? 1;
$role = $_SESSION['role'] ?? 'user';

$sql = "";
$params = [];

if ($view === 'lideres') {
    if ($role === 'lider') {
        // Un líder no debería ver este reporte de "líderes", pero si accede, ve solo lo suyo
        exit('Acceso denegado');
    }
    else {
        // Admin ve todos los líderes de SU organización
        $sql = "SELECT l.*, 
                (SELECT COUNT(*) FROM registros v WHERE v.user_id = (SELECT id FROM users WHERE username = l.cedula LIMIT 1) AND v.tipo = 'votante' AND v.organizacion_id = ?) as total_votantes
                FROM registros l 
                WHERE l.tipo = 'lider' AND l.organizacion_id = ?
                ORDER BY l.created_at DESC";
        $params = [$org_id, $org_id];
    }
}
elseif ($view === 'todos') {
    if ($role === 'lider')
        exit('Acceso denegado');
    // Admin ve todos los de su organización
    $sql = "SELECT r.*, u.username as leader_username, u.name as leader_name 
            FROM registros r 
            LEFT JOIN users u ON r.user_id = u.id 
            WHERE r.organizacion_id = ?";

    $ids = isset($_GET['ids']) ? filter_var_array(explode(',', $_GET['ids']), FILTER_VALIDATE_INT) : [];
    $ids = array_filter($ids);

    if (!empty($ids)) {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $sql .= " AND r.id IN ($inQuery)";
        $params = array_merge([$org_id], $ids);
    }
    else {
        $params = [$org_id];
    }

    $sql .= " ORDER BY r.created_at DESC";
}
else {
    // Vista Normal (Registros)
    if ($role === 'superadmin' || $role === 'admin') {
        // Admin: Export ALL VOTERS with Leader info to support re-import
        $sql = "SELECT r.*, u.username as leader_username, u.name as leader_name 
                FROM registros r 
                LEFT JOIN users u ON r.user_id = u.id 
                WHERE r.tipo = 'votante' AND r.organizacion_id = ? 
                ORDER BY r.created_at DESC";
        $params = [$org_id];
    }
    else {
        // Leader: Export only THEIR voters
        $sql = "SELECT * FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC";
        $params = [$user_id, $org_id];
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Determinar Base URL para Links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$scriptPath = str_replace('/' . basename($scriptName), '', $scriptName);
$baseUrl = $protocol . $domainName . $scriptPath;

?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns="http://www.w3.org/TR/REC-html40">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        /* Estilos para Excel */
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            border: 1px solid #000;
        }

        th {
            background-color: #E30613;
            color: #FFFFFF;
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            font-weight: bold;
        }

        td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }
    </style>
</head>

<body>
    <table>
        <thead>
            <tr>
                <!-- Encabezados Limpios -->
                <th>Nombres y Apellidos</th>
                <th>Cédula</th>
                <th>Lugar Votación</th>
                <th>Mesa</th>
                <th>Celular</th>
                <?php if ($view === 'lideres'): ?>
                <th>Total Votantes</th>
                <?php
endif; ?>
                <th>Estado Voto</th>
                <?php if ($view === 'todos' || (($role === 'admin' || $role === 'superadmin') && $view === 'registros')): ?>
                <th>Líder Responsable</th>
                <th>Cédula Líder</th>
                <?php
endif; ?>
                <th>Link Confirmación</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row):
    // Generar Link con Token MD5 (Consistente con AJAX)
    $token = md5($row['id'] . $row['cedula'] . 'voto2026');
    $link = $baseUrl . '/confirmar_voto.php?id=' . $row['id'] . '&token=' . $token;
?>
            <tr>
                <td class="text-left">
                    <?php echo htmlspecialchars($row['nombres_apellidos']); ?>
                </td>
                <td class="text-center" style="mso-number-format:'@'">
                    <?php echo htmlspecialchars($row['cedula']); ?>
                </td>
                <td class="text-left">
                    <?php echo htmlspecialchars($row['lugar_votacion']); ?>
                </td>
                <td class="text-center" style="mso-number-format:'@'">
                    <?php echo htmlspecialchars($row['mesa']); ?>
                </td>
                <td class="text-center" style="mso-number-format:'@'">
                    <?php echo htmlspecialchars($row['celular']); ?>
                </td>

                <?php if ($view === 'lideres'): ?>
                <td class="text-center">
                    <?php echo htmlspecialchars($row['total_votantes'] ?? 0); ?>
                </td>
                <?php
    endif; ?>

                <td class="text-center"
                    style="background-color: <?php echo ($row['estado_voto'] === 'voto') ? '#d4edda' : '#fff3cd'; ?>">
                    <?php echo ($row['estado_voto'] === 'voto' ? 'YA VOTÓ' : 'Pendiente'); ?>
                </td>

                <?php if ($view === 'todos' || (($role === 'admin' || $role === 'superadmin') && $view === 'registros')): ?>
                <td class="text-left">
                    <?php echo htmlspecialchars(!empty($row['leader_name']) ? $row['leader_name'] : ($row['leader_username'] ?? 'Oficina Central')); ?>
                </td>
                <td class="text-center" style="mso-number-format:'@'">
                    <?php echo htmlspecialchars($row['leader_username'] ?? ''); ?>
                </td>
                <?php
    endif; ?>

                <td><a href="<?php echo $link; ?>">
                        <?php echo $link; ?>
                    </a></td>
            </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>
</body>

</html>
<?php exit; ?>