<?php
require_once 'config.php';

try {
    // Update the confirmacion template to include the confirmation link
    $newContent = "Gracias por ejercer tu derecho al voto! Tu participación hace la diferencia. Confirma aquí: {LINK_CONFIRMACION}";

    $stmt = $pdo->prepare("UPDATE sms_templates SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE name = 'confirmacion'");
    $stmt->execute([$newContent]);

    echo "✅ Plantilla de confirmación actualizada exitosamente!\n\n";
    echo "Nueva plantilla:\n";
    echo $newContent . "\n\n";
    echo "Nota: {LINK_CONFIRMACION} será reemplazado automáticamente con el link único del votante.\n";


}
catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
