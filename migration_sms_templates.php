<?php
require_once 'config.php';

try {
    // Create SMS templates table
    $pdo->exec("CREATE TABLE IF NOT EXISTS sms_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        label TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Insert default templates if they don't exist
    $templates = [
        [
            'name' => 'inscripcion',
            'label' => 'SMS de Inscripción',
            'content' => 'Hola! Gracias por registrarte en nuestra campaña. Contamos con tu apoyo para el cambio.'
        ],
        [
            'name' => 'citacion',
            'label' => 'SMS de Citación',
            'content' => 'Recordatorio: Te esperamos el día de las elecciones. Tu voto es importante para el futuro de nuestra comunidad.'
        ],
        [
            'name' => 'confirmacion',
            'label' => 'SMS de Confirmación de Votación',
            'content' => 'Gracias por ejercer tu derecho al voto! Tu participación hace la diferencia.'
        ]
    ];

    foreach ($templates as $template) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_templates WHERE name = ?");
        $stmt->execute([$template['name']]);

        if ($stmt->fetchColumn() == 0) {
            $stmt = $pdo->prepare("INSERT INTO sms_templates (name, label, content) VALUES (?, ?, ?)");
            $stmt->execute([$template['name'], $template['label'], $template['content']]);
            echo "✓ Plantilla '{$template['label']}' creada\n";
        }
        else {
            echo "- Plantilla '{$template['label']}' ya existe\n";
        }
    }

    echo "\n✅ Migración completada exitosamente!\n";


}
catch (PDOException $e) {
    echo "❌ Error en la migración: " . $e->getMessage() . "\n";
}
?>
