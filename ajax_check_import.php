<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Helper function to normalize headers
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file']['tmp_name'];
    $originalName = $_FILES['file']['name'];

    // Save temp file for later execution
    $tempDir = 'uploads/temp/';
    if (!file_exists($tempDir))
        mkdir($tempDir, 0777, true);
    $tempFile = $tempDir . 'import_' . time() . '_' . preg_replace('/[^a-zA-Z0-9_\.]/', '', $originalName);

    if (!move_uploaded_file($file, $tempFile)) {
        echo json_encode(['error' => 'No se pudo guardar el archivo temporalmente.']);
        exit;
    }

    $rawRows = [];
    $content = file_get_contents($tempFile);
    $isZip = (strpos($content, "PK\x03\x04") === 0);
    $isHtml = (stripos($content, '<html') !== false || stripos($content, '<table') !== false);
    // Detectar XLS binario (Excel 97-2003) por su firma magic D0CF11E0
    $isXlsBinary = (strpos($content, "\xD0\xCF\x11\xE0") === 0);

    if ($isXlsBinary) {
        // Formato .xls antiguo — no soportado sin librería externa
        echo json_encode([
            'error' =>
            "El archivo es un Excel antiguo (.xls) que no puede leerse directamente.\n\n" .
            "Por favor siga estos pasos:\n" .
            "1. Abra el archivo en Excel o Google Sheets.\n" .
            "2. Vaya a Archivo → Guardar como.\n" .
            "3. Elija el formato: Excel (.xlsx) o CSV UTF-8.\n" .
            "4. Suba el nuevo archivo aquí.\n\n" .
            "¿Dudas? Contacte al soporte: 3184483187"
        ]);
        @unlink($tempFile);
        exit;
    }

    if ($isZip) {
        if (!class_exists('ZipArchive')) {
            echo json_encode(['error' => 'Falta extensión ZipArchive en PHP.']);
            exit;
        }
        $rawRows = MiniXLSXReader::parse($tempFile);
    }
    elseif ($isHtml) {
        // Parse HTML Table (Fake Excel)
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length > 0) {
            $trNodes = $tables->item(0)->getElementsByTagName('tr');
            foreach ($trNodes as $tr) {
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
            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                if (count($data) == 1 && strpos($data[0], ';') !== false)
                    $data = explode(';', $data[0]);
                $rawRows[] = $data;
            }
            fclose($handle);
        }
    }

    if (empty($rawRows)) {
        echo json_encode(['error' => 'Archivo vacío o formato no reconocido.']);
        exit;
    }

    // Search for the header row in the first 10 rows
    $foundHeaders = false;
    $headers = [];
    $rawRowsCopy = $rawRows; // Keep copy to not destroy data for finding duplicates later? 
    // actually we just need to identify the header row index to start processing data from there.

    $headerRowIndex = -1;
    $map = ['cedula' => -1, 'lider' => -1];

    foreach ($rawRows as $idx => $row) {
        if ($idx > 10)
            break; // Give up after 10 rows

        $tempHeaders = array_map('normalizeHeader', $row);
        $tempMap = ['cedula' => -1];

        foreach ($tempHeaders as $colIdx => $h) {
            if (strpos($h, 'cedula') !== false || strpos($h, 'cédula') !== false || strpos($h, 'documento') !== false)
                $tempMap['cedula'] = $colIdx;
        }

        if ($tempMap['cedula'] !== -1) {
            $headerRowIndex = $idx;
            $headers = $tempHeaders;
            $map = $tempMap; // Start with this identification
            $foundHeaders = true;
            break;
        }
    }

    if (!$foundHeaders) {
        // Mostrar mensaje claro sin basura binaria
        $firstRow = '';
        if (isset($rawRows[0])) {
            // Filtrar solo texto legible (ASCII imprimible)
            $legibles = array_filter(array_map(function ($v) {
                $clean = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $v);
                return mb_strlen(trim($clean)) > 0 ? trim($clean) : null;
            }, $rawRows[0]));
            $firstRow = implode(', ', array_slice($legibles, 0, 8));
        }

        $hint = !empty($firstRow)
            ? "Encabezados detectados: [$firstRow]"
            : "No se pudo leer ningún encabezado legible (posible archivo corrupto o formato incorrecto).";

        echo json_encode([
            'error' =>
            "No se encontró la columna 'Cédula' o 'Documento' en el archivo.\n\n" .
            "Verifique que la primera fila del Excel tenga exactamente estos encabezados:\n" .
            "  • Nombres y Apellidos\n" .
            "  • Cédula\n" .
            "  • Lugar Votación\n" .
            "  • Mesa\n" .
            "  • Celular\n\n" .
            $hint . "\n\n" .
            "¿Dudas? Contacte al soporte: 3184483187"
        ]);
        @unlink($tempFile);
        exit;
    }

    // Slice data to start AFTER the header row
    // We already have the full $rawRows. We should effectively ignore rows <= $headerRowIndex when looking for duplicates later?
    // Actually duplication check iterates all $rawRows logic below. We should iterate only data rows.
    $dataRows = array_slice($rawRows, $headerRowIndex + 1);

    // Re-map other columns based on the found header row
    foreach ($headers as $idx => $h) {
        if (strpos($h, 'nombre') !== false)
            $map['nombres'] = $idx;
        else if (strpos($h, 'apellido') !== false && $map['nombres'] === -1)
            $map['nombres'] = $idx;

        // Check cedula again just in case but we found it
        if (strpos($h, 'cedula') !== false || strpos($h, 'cédula') !== false || strpos($h, 'documento') !== false)
            $map['cedula'] = $idx;
        if (strpos($h, 'lugar') !== false || strpos($h, 'puesto') !== false)
            $map['lugar'] = $idx;
        if (strpos($h, 'mesa') !== false)
            $map['mesa'] = $idx;
        if (strpos($h, 'celular') !== false || strpos($h, 'movil') !== false)
            $map['celular'] = $idx;
        if (strpos($h, 'tipo') !== false || strpos($h, 'rol') !== false)
            $map['tipo'] = $idx;
        if (strpos($h, 'lider') !== false || strpos($h, 'líder') !== false || strpos($h, 'responsable') !== false) {
            // Prefer 'cedula lider' if simplified match
            if (strpos($h, 'cedula') !== false)
                $map['lider'] = $idx; // Highest priority if "Cedula Lider"
            else if ($map['lider'] == -1)
                $map['lider'] = $idx;
        }
    }

    // --- VALIDATION: Check for empty required fields ---
    $valErrors = [];
    foreach ($dataRows as $idx => $row) {
        $realRow = $idx + $headerRowIndex + 2; // +1 because slice is 0-indexed, but it starts after header. +1 for 1-based.

        // Critical: Cedula
        if (!isset($row[$map['cedula']]) || trim($row[$map['cedula']]) === '') {
            $valErrors[] = "Fila $realRow: Columna 'Cédula' vacía.";
        }

        // Critical: Nombre
        if ($map['nombres'] !== -1) {
            if (!isset($row[$map['nombres']]) || trim($row[$map['nombres']]) === '') {
                $valErrors[] = "Fila $realRow: Columna 'Nombre' vacía.";
            }
        }

        if (count($valErrors) >= 5) {
            $valErrors[] = "... y más errores.";
            break;
        }
    }

    if (!empty($valErrors)) {
        echo json_encode(['error' => "No se puede importar. Se encontraron datos faltantes:\n" . implode("\n", $valErrors)]);
        exit;
    }

    $duplicates = [];
    $newCount = 0;

    $stmtCheck = $pdo->prepare("
        SELECT r.nombres_apellidos, u.name as lider_name, o.nombre_organizacion 
        FROM registros r 
        LEFT JOIN users u ON r.user_id = u.id 
        LEFT JOIN organizaciones o ON r.organizacion_id = o.id 
        WHERE r.cedula = ?
    ");

    foreach ($rawRows as $row) {
        $cedula = isset($row[$map['cedula']]) ? preg_replace('/[^0-9]/', '', $row[$map['cedula']]) : '';
        if (empty($cedula))
            continue;

        $stmtCheck->execute([$cedula]);
        $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $duplicates[] = [
                'cedula' => $cedula,
                'lider' => $existing['lider_name'] ?? 'Desconocido',
                'organizacion' => $existing['nombre_organizacion'] ?? 'Desconocida'
            ];
        }
        else {
            $newCount++;
        }
    }

    echo json_encode([
        'status' => 'ok',
        'duplicates' => $duplicates,
        'new_count' => $newCount,
        'temp_file' => $tempFile
    ]);
}
?>