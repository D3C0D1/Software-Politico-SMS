<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    die('Acceso denegado');
}

// Get leader's cedula from URL
$cedula = $_GET['cedula'] ?? '';

if (empty($cedula)) {
    die('Cédula de líder no especificada');
}

// Get leader info - MUST belong to the current user's organization
$org_id = $_SESSION['organizacion_id'] ?? 1;
$stmtLider = $pdo->prepare("SELECT * FROM registros WHERE cedula = ? AND tipo = 'lider' AND organizacion_id = ? LIMIT 1");
$stmtLider->execute([$cedula, $org_id]);
$lider = $stmtLider->fetch(PDO::FETCH_ASSOC);

if (!$lider) {
    die('Líder no encontrado o no pertenece a su organización');
}

// Get leader's user_id to find their voters
$stmtUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmtUser->execute([$cedula]);
$liderUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

$votantes = [];
if ($liderUser) {
    // Get all voters registered by this leader
    $stmtVotantes = $pdo->prepare("
        SELECT * FROM registros 
        WHERE user_id = ? AND tipo = 'votante' 
        ORDER BY nombres_apellidos ASC
    ");
    $stmtVotantes->execute([$liderUser['id']]);
    $votantes = $stmtVotantes->fetchAll(PDO::FETCH_ASSOC);
}

$totalVotantes = count($votantes);
$fechaGeneracion = date('d/m/Y H:i');

// Get organization configuration for the leader
$organizacion_id = $lider['organizacion_id'] ?? 1;

// Load app configuration for this organization
$org_config = [
    'app_title' => 'Partido Liberal',
    'logo_path' => 'assets/img/logo.png',
    'primary_color' => '#E30613'
];

try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM app_config WHERE organizacion_id = ?");
    $stmt->execute([$organizacion_id]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $org_config[$row['setting_key']] = $row['setting_value'];
    }
}
catch (Exception $e) {
// Fallback to defaults if table doesn't exist
}

// Helper function to convert hex to RGB
function hex2rgb_pdf($hex)
{
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) == 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    }
    else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

$primary_rgb = hex2rgb_pdf($org_config['primary_color']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planilla -
        <?php echo htmlspecialchars($lider['nombres_apellidos']); ?>
    </title>
    <style>
        @page {
            size: letter;
            margin: 1cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #000;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 3px solid <?php echo $org_config['primary_color'];
            ?>;
        }

        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 8px;
        }

        .header h1 {
            font-size: 18pt;
            color: <?php echo $org_config['primary_color'];
            ?>;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 14pt;
            color: #333;
            margin-bottom: 3px;
        }

        .info-box {
            background: #f5f5f5;
            padding: 10px;
            margin-bottom: 15px;
            border-left: 4px solid <?php echo $org_config['primary_color'];
            ?>;
        }

        .info-box p {
            margin: 3px 0;
            font-size: 10pt;
        }

        .info-box strong {
            color: <?php echo $org_config['primary_color'];
            ?>;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        thead {
            background-color: <?php echo $org_config['primary_color'];
            ?>;
            color: white;
        }

        th {
            padding: 8px 5px;
            text-align: left;
            font-size: 9pt;
            font-weight: bold;
        }

        td {
            padding: 6px 5px;
            border-bottom: 1px solid #ddd;
            font-size: 9pt;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f0f0f0;
        }

        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .estado-voto {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }

        .estado-voto.votado {
            background-color: #28a745;
            color: white;
        }

        .estado-voto.pendiente {
            background-color: #ffc107;
            color: #000;
        }

        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="header">
        <h2>Planilla de Votantes</h2>
    </div>

    <div class="info-box">
        <p><strong>Líder Responsable:</strong>
            <?php echo htmlspecialchars($lider['nombres_apellidos']); ?>
        </p>
        <p><strong>Cédula:</strong>
            <?php echo htmlspecialchars($lider['cedula']); ?>
        </p>
        <p><strong>Celular:</strong>
            <?php echo htmlspecialchars($lider['celular']); ?>
        </p>
        <p><strong>Total Votantes:</strong>
            <?php echo $totalVotantes; ?>
        </p>
        <p><strong>Fecha de Generación:</strong>
            <?php echo $fechaGeneracion; ?>
        </p>
    </div>

    <?php if ($totalVotantes > 0): ?>
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 30%;">Nombres y Apellidos</th>
                <th style="width: 12%;">Cédula</th>
                <th style="width: 25%;">Lugar de Votación</th>
                <th style="width: 8%;">Mesa</th>
                <th style="width: 12%;">Celular</th>
                <th style="width: 8%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php
    $contador = 1;
    foreach ($votantes as $votante):
        $estadoVoto = ($votante['estado_voto'] === 'voto' || $votante['ya_voto'] == 1) ? 'votado' : 'pendiente';
        $estadoTexto = ($estadoVoto === 'votado') ? 'Votó' : 'Pendiente';
?>
            <tr>
                <td>
                    <?php echo $contador++; ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($votante['nombres_apellidos']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($votante['cedula']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($votante['lugar_votacion']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($votante['mesa']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($votante['celular']); ?>
                </td>
                <td>
                    <span class="estado-voto <?php echo $estadoVoto; ?>">
                        <?php echo $estadoTexto; ?>
                    </span>
                </td>
            </tr>
            <?php
    endforeach; ?>
        </tbody>
    </table>
    <?php
else: ?>
    <p style="text-align: center; padding: 30px; color: #999; font-size: 12pt;">
        Este líder aún no tiene votantes registrados.
    </p>
    <?php
endif; ?>

    <div class="footer">
        <p>Documento generado automáticamente por el Sistema de Gestión Electoral</p>
        <p>Este documento es confidencial y de uso exclusivo para fines electorales</p>
    </div>

    <script>
        // Auto-print when page loads
        window.onload = function () {
            window.print();
        };
    </script>
</body>

</html>