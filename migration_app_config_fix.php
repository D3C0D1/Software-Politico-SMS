<?php
// migration_app_config_fix.php
// Fixes the UNIQUE constraint on setting_key to avoid multi-org conflicts
// New constraint will be UNIQUE(setting_key, organizacion_id)

require_once 'config.php';

try {
    echo "Starting Migration: Fix app_config UNIQUE constraint...\n";

    // 1. Rename old table
    $pdo->exec("ALTER TABLE app_config RENAME TO app_config_old");
    echo "Renamed app_config to app_config_old.\n";

    // 2. Create new table with correct schema
    // Note: We use UNIQUE(setting_key, organizacion_id) so multiple orgs can have 'app_title'
    $sqlCreate = "CREATE TABLE app_config (
        id INTEGER PRIMARY KEY AUTOINCREMENT, 
        setting_key VARCHAR(50) NOT NULL,
        setting_value TEXT,
        organizacion_id INTEGER DEFAULT 1,
        UNIQUE(setting_key, organizacion_id)
    )";
    $pdo->exec($sqlCreate);
    echo "Created new app_config table.\n";

    // 3. Copy data
    // We only copy distinct keys per org just in case there were duplicates somehow (unlikely due to previous constraint)
    $stmt = $pdo->query("SELECT * FROM app_config_old");
    $migratedCount = 0;

    $insert = $pdo->prepare("INSERT OR IGNORE INTO app_config (setting_key, setting_value, organizacion_id) VALUES (?, ?, ?)");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Fallback for organizacion_id if it was null or missing in old schema (though logic says it was defaulted to 1)
        $orgId = $row['organizacion_id'] ?? 1;
        $insert->execute([$row['setting_key'], $row['setting_value'], $orgId]);
        $migratedCount++;
    }
    echo "Migrated $migratedCount rows.\n";

    // 4. Drop old table
    $pdo->exec("DROP TABLE app_config_old");
    echo "Dropped app_config_old.\n";

    echo "Migration Successful!\n";

}
catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage() . "\n";
// Attempt to rollback if something broke mid-way? 
// SQLite DDL is not transactional in the way we might hope for table drops/renames in older versions, 
// but PHP PDO transaction might catch it.
}
?>