<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    exit('<tr><td colspan="10">No autorizado</td></tr>');
}

try {
    $organizacion_id = $_SESSION['organizacion_id'] ?? 1;
    $stmt = $pdo->prepare("
        SELECT r.*, u.username as leader_username, u.name as leader_name 
        FROM registros r 
        LEFT JOIN users u ON r.user_id = u.id 
        WHERE r.organizacion_id = :org_id
        ORDER BY r.created_at DESC
    ");
    $stmt->execute(['org_id' => $organizacion_id]);
    $todos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (PDOException $e) {
    echo '<tr><td colspan="10" style="color:red; text-align:center; padding: 20px;">
            <i class="fas fa-exclamation-triangle"></i> Error al cargar datos: ' . htmlspecialchars($e->getMessage()) . '
          </td></tr>';
    exit;
}

if (count($todos) > 0):
    foreach ($todos as $registro):

?>
<tr>
    <td style="text-align: center;"><input type="checkbox" class="row-checkbox"
            value="<?php echo htmlspecialchars($registro['id']); ?>"></td>
    <td>
        <?php echo htmlspecialchars($registro['id']); ?>
    </td>
    <td>
        <strong>
            <?php echo htmlspecialchars($registro['leader_name'] ? $registro['leader_name'] : ($registro['leader_username'] ? $registro['leader_username'] : 'Directo')); ?>
        </strong>
    </td>
    <td>
        <?php if (isset($registro['tipo']) && $registro['tipo'] == 'lider'): ?>
        <span class="badge badge-lider">LÍDER</span>
        <?php
        else: ?>
        <span class="badge badge-operador">VOTANTE</span>
        <?php
        endif; ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['nombres_apellidos']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['cedula']); ?>
    </td>
    <td>
        <?php echo htmlspecialchars($registro['celular']); ?>
    </td>
    <td>
        <?php if (isset($registro['estado_voto']) && $registro['estado_voto'] == 'voto'): ?>
        <span class="badge badge-votante"><i class="fas fa-check"></i> Votó</span>
        <?php
        else: ?>
        <span class="badge badge-admin"><i class="fas fa-clock"></i> Pendiente</span>
        <?php
        endif; ?>
    </td>
    <td style="text-align: center;">
        <?php if (isset($registro['ya_voto']) && $registro['ya_voto'] == 1): ?>
        <span class="badge badge-votante"><i class="fas fa-check-circle"></i> Sí</span>
        <?php
        elseif (isset($registro['ya_voto']) && $registro['ya_voto'] === 0): ?>
        <span class="badge badge-delete" style="background:#dc3545; color:white;"><i class="fas fa-times-circle"></i>
            No</span>
        <?php
        else: ?>
        <span class="badge badge-warning" style="background:#ffc107; color:black;"><i
                class="fas fa-question-circle"></i> Pendiente</span>
        <?php
        endif; ?>
    </td>
    <td style="text-align: center;">
        <?php
        $token = md5($registro['id'] . $registro['cedula'] . 'voto2026');

        // Detectar protocolo
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST'];

        // Obtener path base limpio desde SCRIPT_NAME para preservar caracteres especiales (ej. 'ñ')
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $scriptPath = str_replace('/' . basename($scriptName), '', $scriptName);

        $confirmLink = $protocol . $domainName . $scriptPath . '/confirmar_voto.php?id=' . $registro['id'] . '&token=' . $token;
?>
        <button
            onclick="sendSmsLink('<?php echo $registro['id']; ?>', '<?php echo $registro['celular']; ?>', '<?php echo $confirmLink; ?>')"
            class="btn btn-sm" style="background: #28a745; color: white;" title="Enviar Link Confirmación por SMS">
            <i class="fas fa-paper-plane"></i> <i class="fas fa-sms"></i>
    </td>
    <td>
        <a href="registro_form.php?id=<?php echo $registro['id']; ?>&type=<?php echo isset($registro['tipo']) ? $registro['tipo'] : 'votante'; ?>"
            class="btn btn-edit btn-sm" title="Editar"><i class="fas fa-edit"></i></a>
        <a href="eliminar_registro.php?id=<?php echo $registro['id']; ?>&return=todos" class="btn btn-delete btn-sm"
            onclick="event.preventDefault(); window.confirmDelete ? confirmDelete(this.href) : window.location.href=this.href;"
            title="Eliminar"><i class="fas fa-trash"></i></a>
    </td>
</tr>
<?php
    endforeach;

else:

?>
<tr>
    <td colspan="11" style="text-align: center;">No hay registros encontrados.</td>
</tr>
<?php
endif; ?>