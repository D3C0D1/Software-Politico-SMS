<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>No autorizado</p>";
    exit;
}

$lider_id = $_GET['lider_id'] ?? null;
$org_id = $_SESSION['organizacion_id'] ?? 1;

if (!$lider_id) {
    echo "<p>Falta ID de líder</p>";
    exit;
}

// Get duplicates for this specific leader
// We find records for this leader where the cedula appears > 1 time in the organization
$stmt = $pdo->prepare("
    SELECT r.id, r.nombres_apellidos, r.cedula, r.celular, r.created_at, u.name as registrado_por
    FROM registros r
    JOIN users u ON r.user_id = u.id
    WHERE r.tipo = 'votante' 
    AND r.organizacion_id = ? 
    AND r.user_id = ?
    AND r.cedula IN (
        SELECT cedula 
        FROM registros 
        WHERE tipo = 'votante' AND organizacion_id = ? 
        GROUP BY cedula 
        HAVING COUNT(*) > 1
    )
    ORDER BY r.cedula ASC
");

$stmt->execute([$org_id, $lider_id, $org_id]);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($registros) === 0) {
    echo "<p>No se encontraron registros duplicados para este líder.</p>";
    exit;
}
?>

<div class="table-responsive">
    <table class="table" style="width:100%">
        <thead>
            <tr>
                <th style="padding:8px">Nombre</th>
                <th style="padding:8px">Cédula</th>
                <th style="padding:8px">Celular</th>
                <th style="padding:8px">Acción</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($registros as $r): ?>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding:8px">
                    <?php echo htmlspecialchars($r['nombres_apellidos']); ?>
                </td>
                <td style="padding:8px; font-weight:bold; color:#dc3545;">
                    <?php echo htmlspecialchars($r['cedula']); ?>
                </td>
                <td style="padding:8px">
                    <?php echo htmlspecialchars($r['celular']); ?>
                </td>
                <td style="padding:8px">
                    <a href="eliminar_registro.php?id=<?php echo $r['id']; ?>&return=estadisticas"
                        onclick="return confirm('¿Eliminar este registro duplicado?');" class="btn btn-sm"
                        style="background-color: #dc3545; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; font-size: 0.8em;">
                        <i class="fas fa-trash"></i> Eliminar
                    </a>
                </td>
            </tr>
            <?php
endforeach; ?>
        </tbody>
    </table>
</div>