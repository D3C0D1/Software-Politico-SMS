<?php
require_once 'config.php';

// IDs identified as extra leaders for 'admin_cholismo' (Org ID 4)
// These users exist in 'users' table but do not have a corresponding valid 'registro' or are considered extra by the user.
$idsToDelete = [14, 16, 18, 19, 26];

echo "Cleaning up extra leaders for admin_cholismo...\n";
echo "Targeting User IDs: " . implode(', ', $idsToDelete) . "\n";

try {
    // Check before delete
    $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
    $stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE id IN ($placeholders)");
    $stmt->execute($idsToDelete);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($users) . " users to delete:\n";
    foreach ($users as $u) {
        echo "- ID: {$u['id']} | Name: {$u['name']} | User: {$u['username']}\n";
    }

    if (count($users) > 0) {
        $stmtDelete = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmtDelete->execute($idsToDelete);
        echo "Successfully deleted " . $stmtDelete->rowCount() . " users.\n";
    }
    else {
        echo "No users found with those IDs.\n";
    }

    // Verify final count
    $orgId = 4; // admin_cholismo
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'lider' AND organizacion_id = ?");
    $stmtCount->execute([$orgId]);
    $finalCount = $stmtCount->fetchColumn();

    echo "---------------------------------\n";
    echo "Final Leader Count for Organization $orgId: $finalCount\n";

}
catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}