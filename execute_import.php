<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Helper function
function normalizeHeader($h)
{
    if (!$h)
        return '';
    $h = mb_strtolower(trim($h), 'UTF-8');
    $h = str_replace(
    ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
    ['a', 'e', 'i', 'o', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'n'],
        $h
    );
    return $h;
}

// MiniXLSXReader Class (Embedded for portability)
class MiniXLSXReader
{
    public static function parse($filename)
    {
        $rows = [];
        $zip = new ZipArchive;
        if ($zip->open($filename) === TRUE) {
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $dom = new DOMDocument;
                @$dom->loadXML($xml);
                $si = $dom->getElementsByTagName('si');
                foreach ($si as $node) {
                    $t = $node->getElementsByTagName('t')->item(0);
                    $strings[] = $t ? $t->nodeValue : $node->textContent;
                }
            }
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml') ?: $zip->getFromName('xl/worksheets/Sheet1.xml');
            if ($sheetXml) {
                $dom = new DOMDocument;
                @$dom->loadXML($sheetXml);
                $rowsXml = $dom->getElementsByTagName('row');
                foreach ($rowsXml as $rowNode) {
                    $row = [];
                    $cells = $rowNode->getElementsByTagName('c');
                    foreach ($cells as $cell) {
                        $type = $cell->getAttribute('t');
                        $vNode = $cell->getElementsByTagName('v')->item(0);
                        $val = $vNode ? $vNode->nodeValue : '';
                        if ($type == 's' && isset($strings[$val]))
                            $val = $strings[$val];
                        $row[] = trim($val);
                    }
                    if (!empty($row))
                        $rows[] = $row;
                }
            }
            $zip->close();
        }
        return $rows;
    }
}

// --- Execution Logic ---

$data = json_decode(file_get_contents('php://input'), true);
$tempFile = $data['temp_file'] ?? '';
$mode = $data['mode'] ?? 'new_only'; // 'all' or 'new_only'
$view = $data['view'] ?? 'todos';

if (empty($tempFile) || !file_exists($tempFile)) {
    echo json_encode(['success' => false, 'message' => 'Archivo temporal no encontrado.']);
    exit;
}

// Parse File
$rawRows = [];
$content = file_get_contents($tempFile);
$isZip = (strpos($content, "PK\x03\x04") === 0);
$isHtml = (stripos($content, '<html') !== false || stripos($content, '<table') !== false);

if ($isZip) {
    if (!class_exists('ZipArchive')) {
        echo json_encode(['success' => false, 'message' => 'Falta extensión ZipArchive.']);
        exit;
    }
    $rawRows = MiniXLSXReader::parse($tempFile);
}
elseif ($isHtml) {
    // Parse HTML Table (Fake Excel)
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    // Hint UTF-8 to avoid encoding issues with accents
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
    libxml_clear_errors();

    $tables = $dom->getElementsByTagName('table');
    if ($tables->length > 0) {
        $trNodes = $tables->item(0)->getElementsByTagName('tr');
        foreach ($trNodes as $tr) {
            // Collect ALL cells (th and td) in order of appearance
            $cells = $tr->childNodes;
            $row = [];
            foreach ($cells as $cell) {
                if ($cell->nodeName === 'td' || $cell->nodeName === 'th') {
                    $row[] = trim($cell->nodeValue);
                }
            }
            if (!empty($row)) {
                $rawRows[] = $row;
            }
        }
    }
}
else {
    // CSV Fallback
    if (($handle = fopen($tempFile, "r")) !== FALSE) {
        $bom = fread($handle, 3);
        if ($bom != "\xEF\xBB\xBF")
            rewind($handle);
        while (($row = fgetcsv($handle, 2000, ",")) !== FALSE) {
            if (count($row) == 1 && strpos($row[0], ';') !== false)
                $row = explode(';', $row[0]);
            $rawRows[] = $row;
        }
        fclose($handle);
    }
}

if (empty($rawRows)) {
    echo json_encode(['success' => false, 'message' => 'Archivo vacío.']);
    exit;
}

// --- SEARCH FOR HEADER ROW ---
$headerRowIndex = -1;
$foundHeaders = false;
$headers = [];

foreach ($rawRows as $idx => $row) {
    if ($idx > 10)
        break;

    $tempHeaders = array_map('normalizeHeader', $row);

    foreach ($tempHeaders as $colIdx => $h) {
        if (strpos($h, 'cedula') !== false || strpos($h, 'cédula') !== false || strpos($h, 'documento') !== false) {
            $headerRowIndex = $idx;
            $headers = $tempHeaders;
            $foundHeaders = true;
            break 2;
        }
    }
}

if (!$foundHeaders) {
    echo json_encode(['success' => false, 'message' => 'No se encontró columna Cédula.']);
    exit;
}

// Process only data rows
$rawRows = array_slice($rawRows, $headerRowIndex + 1);

// Continue with mapping
$map = [
    'nombres' => -1, 'cedula' => -1, 'lugar' => -1,
    'mesa' => -1, 'celular' => -1, 'tipo' => -1, 'lider' => -1
];

foreach ($headers as $idx => $h) {
    if (strpos($h, 'nombre') !== false)
        $map['nombres'] = $idx;
    else if (strpos($h, 'apellido') !== false && $map['nombres'] === -1)
        $map['nombres'] = $idx;

    if (strpos($h, 'cedula') !== false || strpos($h, 'cédula') !== false || strpos($h, 'documento') !== false) {
        // If headers have "Cedula Lider", avoid collision with main Cedula
        // But if we encounter "Cedula Lider" separately...
        // Simplest: If h contains 'lider', it's leader stuff.
        if (strpos($h, 'lider') === false && strpos($h, 'líder') === false)
            $map['cedula'] = $idx;
    }

    if (strpos($h, 'lugar') !== false || strpos($h, 'puesto') !== false)
        $map['lugar'] = $idx;
    if (strpos($h, 'mesa') !== false)
        $map['mesa'] = $idx;
    if (strpos($h, 'celular') !== false || strpos($h, 'movil') !== false)
        $map['celular'] = $idx;
    if (strpos($h, 'tipo') !== false || strpos($h, 'rol') !== false)
        $map['tipo'] = $idx;

    if (strpos($h, 'lider') !== false || strpos($h, 'líder') !== false || strpos($h, 'responsable') !== false) {
        // Priority logic: "Cedula Lider" > "Lider Responsable" (Name)
        // If we already found a 'lider' column (e.g. name), but now find 'cedula', override it?
        if (strpos($h, 'cedula') !== false)
            $map['lider'] = $idx;
        else if ($map['lider'] == -1)
            $map['lider'] = $idx;
    }
}

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'] ?? 1;

$stmtInsert = $pdo->prepare("INSERT INTO registros (nombres_apellidos, cedula, lugar_votacion, mesa, celular, tipo, user_id, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmtCheck = $pdo->prepare("SELECT id FROM registros WHERE cedula = ? AND organizacion_id = ?");

$successCount = 0;
$skippedCount = 0;
$errorCount = 0;

foreach ($rawRows as $row) {
    $nombres = ($map['nombres'] !== -1 && isset($row[$map['nombres']])) ? $row[$map['nombres']] : '';
    $cedula = ($map['cedula'] !== -1 && isset($row[$map['cedula']])) ? $row[$map['cedula']] : '';
    $lugar = ($map['lugar'] !== -1 && isset($row[$map['lugar']])) ? $row[$map['lugar']] : '';
    $mesa = ($map['mesa'] !== -1 && isset($row[$map['mesa']])) ? $row[$map['mesa']] : '';
    $celular = ($map['celular'] !== -1 && isset($row[$map['celular']])) ? $row[$map['celular']] : '';

    $cedulaLimpia = preg_replace('/[^0-9]/', '', $cedula);
    if (empty($cedulaLimpia))
        continue;

    // Check duplicates if mode is new_only
    if ($mode === 'new_only') {
        // GLOBAL CHECK: Check if cedula exists in ANY organization
        try {
            // We want to know WHERE it exists to report it
            $stmtGlobalCheck = $pdo->prepare("
                SELECT o.nombre_organizacion, u.name as leader_name, u.username as leader_user
                FROM registros r 
                JOIN organizaciones o ON r.organizacion_id = o.id 
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.cedula = ? 
                LIMIT 1
            ");
            $stmtGlobalCheck->execute([$cedulaLimpia]);
            $existing = $stmtGlobalCheck->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $skippedCount++;
                $orgName = $existing['nombre_organizacion'];
                $leaderName = $existing['leader_name'] ? $existing['leader_name'] : ($existing['leader_user'] ?? 'Desconocido');

                $skippedDetails[] = [
                    'cedula' => $cedulaLimpia,
                    'nombre' => $nombres,
                    'reason' => "Ya existe en: $orgName (Líder: $leaderName)"
                ];
                continue;
            }
        }
        catch (PDOException $e) {
        // If error in check, likely safe to try insert or skip
        }
    }

    // Determine type
    $tipoRegistro = ($view === 'lideres') ? 'lider' : 'votante';
    if ($map['tipo'] !== -1 && isset($row[$map['tipo']])) {
        $val = normalizeHeader($row[$map['tipo']]);
        if (strpos($val, 'lider') !== false)
            $tipoRegistro = 'lider';
    }

    // Determine Owner (User ID)
    $row_user_id = $user_id; // Default to importer (Admin)

    // Attempt to map from LIDER column if exists
    if ($map['lider'] !== -1 && isset($row[$map['lider']])) {
        $liderVal = trim($row[$map['lider']]);
        if (!empty($liderVal)) {
            // 1. Try exact username match (Cedula) - Most reliable
            $stmtL = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
            $stmtL->execute([$liderVal]);
            $foundId = $stmtL->fetchColumn();

            if ($foundId) {
                $row_user_id = $foundId;
            }
            else {
                // 2. Try Name match
                $stmtL = $pdo->prepare("SELECT id FROM users WHERE name LIKE ? LIMIT 1");
                $stmtL->execute([$liderVal]);
                $foundId = $stmtL->fetchColumn();
                if ($foundId)
                    $row_user_id = $foundId;
            }
        }
    }

    try {
        $stmtInsert->execute([$nombres, $cedulaLimpia, $lugar, $mesa, $celular, $tipoRegistro, $row_user_id, $org_id]);
        $successCount++;

        // Auto-create User for Lider
        if ($tipoRegistro === 'lider') {
            try {
                $stmtUserCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmtUserCheck->execute([$cedulaLimpia]);
                if (!$stmtUserCheck->fetchColumn()) {
                    $passHash = password_hash($cedulaLimpia, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id, created_at) VALUES (?, ?, ?, 'lider', ?, NOW())");
                    $stmtUser->execute([$cedulaLimpia, $passHash, $nombres, $org_id]);
                }
            }
            catch (Exception $e) {
            // Ignore user creation errors
            }
        }
    }
    catch (PDOException $e) {
        $errorCount++;
        // Likely duplicate key if mode was 'all' or race condition
        $skippedDetails[] = [
            'cedula' => $cedulaLimpia,
            'nombre' => $nombres,
            'reason' => "Error de base de datos (posible duplicado)"
        ];
    }
}

// Clean up
@unlink($tempFile);

echo json_encode([
    'success' => true,
    'imported' => $successCount,
    'skipped' => $skippedCount,
    'errors' => $errorCount,
    'skipped_details' => $skippedDetails ?? []
]);