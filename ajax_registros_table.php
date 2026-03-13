<?php
require_once 'config.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    exit('<tr><td colspan="13">No autorizado / Sesión expirada</td></tr>');
}

$user_id = $_SESSION['user_id'];

try {
    if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'superadmin')) {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'votante' AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['organizacion_id']]);
    }
    else {
        $stmt = $pdo->prepare("SELECT * FROM registros WHERE tipo = 'votante' AND user_id = ? AND organizacion_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user_id, $_SESSION['organizacion_id']]);
    }
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    echo '<tr><td colspan="13" style="color:red; text-align:center; padding: 20px;"><i class="fas fa-exclamation-triangle"></i> Error al cargar datos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    exit;
}

if (count($registros) > 0):
    foreach ($registros as $registro):
        $token = md5($registro['id'] . $registro['cedula'] . 'voto2026');
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $scriptPath = str_replace('/' . basename($scriptName), '', $scriptName);
        $confirmLink = $protocol . $domainName . $scriptPath . '/confirmar_voto.php?id=' . $registro['id'] . '&token=' . $token;
        $estado = $registro['estado_voto'] ?? 'pendiente';
        $yaVoto = $registro['ya_voto'] ?? 0;
?>
<tr>
    <td>
        <?php echo htmlspecialchars($registro['id']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['nombres_apellidos']); ?>
    </td>
    <td style="text-align:center;">
        <button
            onclick="sendSmsLink('<?php echo $registro['id']; ?>', '<?php echo $registro['celular']; ?>', '<?php echo $confirmLink; ?>')"
            class="btn btn-sm" style="background:#28a745; color:white;" title="Enviar Link Confirmación por SMS">
            <i class="fas fa-paper-plane"></i> <i class="fas fa-sms"></i>
        </button>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['cedula']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['lugar_votacion']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['mesa']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['celular']); ?>
    </td>

    <!-- Estado: automático desde confirmar_voto.php -->
    <td>
        <?php if ($estado === 'voto'): ?>
        <span class="badge" style="background:#dc3545; color:white;"><i class="fas fa-check"></i> Votó</span>
        <?php
        else: ?>
        <span class="badge" style="background:#ff9800; color:white;"><i class="fas fa-clock"></i> Pendiente</span>
        <?php
        endif; ?>
    </td>

    <!-- Ya Votó: el líder lo cambia manualmente a Revisado -->
    <td style="text-align:center;">
        <?php if ($yaVoto == 1): ?>
        <span class="badge" style="background:#28a745; color:white;"><i class="fas fa-check-double"></i> Revisado</span>
        <?php
        else: ?>
        <span class="badge" style="background:#ffc107; color:#333;"><i class="fas fa-clock"></i> Pendiente</span>
        <?php
        endif; ?>
    </td>

    <td style="text-align:center;">
        <?php if (!empty($registro['sms_inscripcion']) && $registro['sms_inscripcion'] == 1): ?>
        <i class="fas fa-check-circle" style="color:#28a745; font-size:1.2em;"></i>
        <?php
        else: ?>
        <i class="fas fa-times-circle" style="color:#dc3545; font-size:1.2em;"></i>
        <?php
        endif; ?>
    </td>
    <td style="text-align:center;">
        <?php if (!empty($registro['sms_citacion']) && $registro['sms_citacion'] == 1): ?>
        <i class="fas fa-check-circle" style="color:#28a745; font-size:1.2em;"></i>
        <?php
        else: ?>
        <i class="fas fa-times-circle" style="color:#dc3545; font-size:1.2em;"></i>
        <?php
        endif; ?>
    </td>
    <td style="text-align:center;">
        <?php if (!empty($registro['sms_confirmacion']) && $registro['sms_confirmacion'] == 1): ?>
        <i class="fas fa-check-circle" style="color:#28a745; font-size:1.2em;"></i>
        <?php
        else: ?>
        <i class="fas fa-times-circle" style="color:#dc3545; font-size:1.2em;"></i>
        <?php
        endif; ?>
    </td>
    <td>
        <!-- Botón Ya Votó: líder marca manualmente Revisado o vuelve a Pendiente -->
        <?php if ($yaVoto == 1): ?>
        <a href="toggle_voto.php?id=<?php echo $registro['id']; ?>&field=yavoto" class="btn btn-sm"
            style="background:#ff9800; color:white;" title="Desmarcar Revisado">
            <i class="fas fa-undo"></i>
        </a>
        <?php
        else: ?>
        <a href="toggle_voto.php?id=<?php echo $registro['id']; ?>&field=yavoto" class="btn btn-sm"
            style="background:#28a745; color:white;" title="Marcar como Revisado">
            <i class="fas fa-check-double"></i>
        </a>
        <?php
        endif; ?>
        <!-- Toggle Estado Voto -->
        <a href="toggle_voto.php?id=<?php echo $registro['id']; ?>&field=estado" class="btn btn-sm"
            style="background:#4CAF50; color:white;" title="Cambiar Estado Voto">
            <i class="fas fa-vote-yea"></i>
        </a>
        <a href="registro_form.php?id=<?php echo $registro['id']; ?>&type=votante" class="btn btn-edit btn-sm"
            title="Editar">
            <i class="fas fa-edit"></i>
        </a>
        <a href="eliminar_registro.php?id=<?php echo $registro['id']; ?>" class="btn btn-delete btn-sm"
            onclick="event.preventDefault(); confirmDelete(this.href);" title="Eliminar">
            <i class="fas fa-trash"></i>
        </a>
        <button class="btn btn-secondary btn-sm"
            onclick="sendSmsIndividual('<?php echo $registro['id']; ?>', '<?php echo $registro['celular']; ?>', '<?php echo htmlspecialchars($registro['nombres_apellidos'], ENT_QUOTES); ?>')"
            title="Enviar SMS">
            <i class="fas fa-sms"></i>
        </button>
    </td>
</tr>
<?php
    endforeach;
else:
?>
<tr>
    <td colspan="13" style="text-align:center;">No hay registros encontrados.</td>
</tr>
<?php
endif;
?>