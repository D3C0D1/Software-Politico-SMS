<?php
// Configuración y Sesión
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    exit('Acceso denegado');
}

$user_id = $_SESSION['user_id'];
$org_id = $_SESSION['organizacion_id'] ?? 1;

// Aumentar memoria y tiempo para archivos grandes
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);

// Helpers
function redirect($msg, $url)
{
    echo "<script>alert('$msg'); window.location.href='$url';</script>";
    exit;
}

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

// Clase Minimalista para leer XLSX (Solo necesita extensión Zip de PHP)
class MiniXLSXReader
{
    public static function parse($filename)
    {
        $rows = [];
        $zip = new ZipArchive;
        if ($zip->open($filename) === TRUE) {

            // 1. Leer Shared Strings (Diccionario de textos)
            $strings = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $dom = new DOMDocument;
                @$dom->loadXML($xml);
                $si = $dom->getElementsByTagName('si');
                foreach ($si as $node) {
                    // Try to get direct t child
                    $t = $node->getElementsByTagName('t')->item(0);
                    if ($t) {
                        $strings[] = $t->nodeValue;
                    }
                    else {
                        // Sometimes it's inside r > t
                        $strings[] = $node->textContent;
                    }
                }
            }

            // 2. Leer Hoja 1 (Sheet1)
            // Intentar varios nombres comunes
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if (!$sheetXml)
                $sheetXml = $zip->getFromName('xl/worksheets/Sheet1.xml');

            if ($sheetXml) {
                $dom = new DOMDocument;
                @$dom->loadXML($sheetXml);
                $rowsXml = $dom->getElementsByTagName('row');

                foreach ($rowsXml as $rowNode) {
                    $row = [];
                    $cells = $rowNode->getElementsByTagName('c');
                    // Necesitamos manejar índices de columnas (A, B, C...) si hay huecos
                    // Por simplicidad, asumiremos tabla continua, pero extraeremos valores
                    foreach ($cells as $cell) {
                        $type = $cell->getAttribute('t');
                        $vNode = $cell->getElementsByTagName('v')->item(0);
                        $val = $vNode ? $vNode->nodeValue : '';

                        // Si es compartido (s), buscar en diccionario
                        if ($type == 's' && isset($strings[$val])) {
                            $val = $strings[$val];
                        }
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
    $view = $_POST['view'] ?? 'registros';

    // Validar archivo
    if (!is_uploaded_file($file)) {
        redirect("Error al subir el archivo.", "registros.php");
    }

    $rawRows = [];
    $content = file_get_contents($file);

    // Detección de Formato
    $isZip = (strpos($content, "PK\x03\x04") === 0);
    $isHtml = (stripos($content, '<html') !== false || stripos($content, '<table') !== false);
    $isXlsBinary = (strpos($content, "\xD0\xCF\x11\xE0") === 0); // Excel 97-2003 .xls

    if ($isXlsBinary) {
        redirect(
            "El archivo es un Excel antiguo (.xls) que no puede importarse directamente.\n\n" .
            "Pasos para solucionarlo:\n" .
            "1. Abra el archivo en Excel o Google Sheets.\n" .
            "2. Archivo → Guardar como → Excel (.xlsx) o CSV UTF-8.\n" .
            "3. Suba el nuevo archivo.\n\n" .
            "¿Dudas? Contacte: 3184483187",
            "registros.php"
        );
    }

    if ($isZip) {
        // Es un XLSX Real
        if (!class_exists('ZipArchive')) {
            redirect("Su servidor no soporta archivos XLSX (falta extensión Zip). Use CSV.", "registros.php");
        }
        $rawRows = MiniXLSXReader::parse($file);
        if (empty($rawRows)) {
            redirect("No se pudieron leer datos del archivo Excel (.xlsx). Intente guardar como CSV.", "registros.php");
        }
    }
    elseif ($isHtml) {
        // Es nuestro "Excel" HTML
        $dom = new DOMDocument();
        // Supress errors for malformed HTML
        libxml_use_internal_errors(true);
        @$dom->loadHTML($content);
        libxml_clear_errors();

        $tables = $dom->getElementsByTagName('table');
        if ($tables->length > 0) {
            $trNodes = $tables->item(0)->getElementsByTagName('tr');
            foreach ($trNodes as $tr) {
                $row = [];
                $cells = $tr->getElementsByTagName('td');
                if ($cells->length == 0)
                    $cells = $tr->getElementsByTagName('th'); // Header row

                foreach ($cells as $cell) {
                    $row[] = trim($cell->nodeValue);
                }
                if (!empty($row))
                    $rawRows[] = $row;
            }
        }
    }
    else {
        // Asumir CSV
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Check BOM
            $bom = fread($handle, 3);
            if ($bom != "\xEF\xBB\xBF")
                rewind($handle);

            while (($data = fgetcsv($handle, 2000, ",")) !== FALSE) {
                // Fix CSV europeo (punto y coma)
                if (count($data) == 1 && strpos($data[0], ';') !== false) {
                    $data = explode(';', $data[0]);
                }
                $rawRows[] = $data;
            }
            fclose($handle);
        }
    }

    if (empty($rawRows)) {
        redirect("El archivo parece estar vacío o tiene un formato no reconocido.", "registros.php");
    }

    // --- PROCESAMIENTO DE DATOS ---

    // Extraer Headers (Primera fila)
    $headers = array_map('normalizeHeader', array_shift($rawRows));

    $success = 0;
    $errors = 0;

    // Mapeo Inteligente
    $map = [
        'nombres' => -1,
        'cedula' => -1,
        'lugar' => -1,
        'mesa' => -1,
        'celular' => -1,
        'tipo' => -1
    ];

    foreach ($headers as $idx => $h) {
        if (strpos($h, 'nombre') !== false)
            $map['nombres'] = $idx;
        else if (strpos($h, 'apellido') !== false && $map['nombres'] === -1)
            $map['nombres'] = $idx; // Fallback

        if (strpos($h, 'cedula') !== false || strpos($h, 'documento') !== false)
            $map['cedula'] = $idx;
        if (strpos($h, 'lugar') !== false || strpos($h, 'puesto') !== false)
            $map['lugar'] = $idx;
        if (strpos($h, 'mesa') !== false)
            $map['mesa'] = $idx;
        if (strpos($h, 'celular') !== false || strpos($h, 'telefono') !== false || strpos($h, 'movil') !== false)
            $map['celular'] = $idx;
        if (strpos($h, 'tipo') !== false || strpos($h, 'rol') !== false)
            $map['tipo'] = $idx;
    }

    if ($map['cedula'] === -1) {
        redirect("No se encontró la columna 'Cedula' en el archivo. Verifique los encabezados.", "registros.php");
    }

    $stmtInsert = $pdo->prepare("INSERT INTO registros (nombres_apellidos, cedula, lugar_votacion, mesa, celular, tipo, user_id, organizacion_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($rawRows as $row) {
        // Extraer segun indices mapeados
        $nombres = ($map['nombres'] !== -1 && isset($row[$map['nombres']])) ? $row[$map['nombres']] : '';
        $cedula = ($map['cedula'] !== -1 && isset($row[$map['cedula']])) ? $row[$map['cedula']] : '';
        $lugar = ($map['lugar'] !== -1 && isset($row[$map['lugar']])) ? $row[$map['lugar']] : '';
        $mesa = ($map['mesa'] !== -1 && isset($row[$map['mesa']])) ? $row[$map['mesa']] : '';
        $celular = ($map['celular'] !== -1 && isset($row[$map['celular']])) ? $row[$map['celular']] : '';

        // Limpiar Cédula
        $cedulaLimpia = preg_replace('/[^0-9]/', '', $cedula);
        if (empty($cedulaLimpia))
            continue;

        // Determinar Tipo
        $tipoRegistro = ($view === 'lideres') ? 'lider' : 'votante';
        // Si hay columna tipo, usarla
        if ($map['tipo'] !== -1 && isset($row[$map['tipo']])) {
            $val = normalizeHeader($row[$map['tipo']]);
            if (strpos($val, 'lider') !== false)
                $tipoRegistro = 'lider';
        }

        try {
            $stmtInsert->execute([
                $nombres,
                $cedulaLimpia,
                $lugar,
                $mesa,
                $celular,
                $tipoRegistro,
                $user_id,
                $org_id
            ]);
            $success++;

            // --- AUTO-CREAR USUARIO PARA LÍDERES ---
            if ($tipoRegistro === 'lider') {
                try {
                    // Verificar si ya existe usuario con esa cédula (username)
                    $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmtCheck->execute([$cedulaLimpia]);

                    if (!$stmtCheck->fetchColumn()) {
                        // Crear Usuario Líder
                        // Username: Cédula
                        // Password: Hash(Cédula)
                        $passHash = password_hash($cedulaLimpia, PASSWORD_DEFAULT);
                        $stmtUser = $pdo->prepare("INSERT INTO users (username, password, name, role, organizacion_id, created_at) VALUES (?, ?, ?, 'lider', ?, NOW())");
                        $stmtUser->execute([$cedulaLimpia, $passHash, $nombres, $org_id]);
                    }
                }
                catch (Exception $e) {
                // Silently fail user creation to not stop import (maybe duplicate key that wasn't caught)
                }
            }
        // ---------------------------------------

        }
        catch (PDOException $e) {
            $errors++;
        }
    }

    $redirectUrl = ($view === 'lideres') ? 'lideres.php' : (($view === 'todos') ? 'todos_registros.php' : 'registros.php');
    redirect("Importacion finalizada.\\n\\nAgregados: $success\\nDuplicados: $errors", $redirectUrl);

}
else {
    redirect("Método no permitido.", "registros.php");
}
?>