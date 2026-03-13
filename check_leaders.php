<?php
require_once 'config.php';

try {
    // 1. Find the admin user 'admin_cholismo'
    echo "Searching for user 'admin_cholismo'...\n";
    $stmt = $pdo->prepare("SELECT id, username, organizacion_id FROM users WHERE username = 'admin_cholismo'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo "User 'admin_cholismo' not found.\n";
        // Let's list all admins to see what's going on
        $stmt = $pdo->query("SELECT id, username, organizacion_id FROM users WHERE role = 'admin'");
        echo "Available admins:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "- " . $row['username'] . " (Org ID: " . $row['organizacion_id'] . ")\n";
        }
        exit;
    }

    echo "Admin Found: " . $admin['username'] . "\n";
    echo "Organization ID: " . $admin['organizacion_id'] . "\n";
    echo "--------------------------------------------------\n";

    $orgId = $admin['organizacion_id'];

    // 2. Count leaders
    $stmt = $pdo->prepare("SELECT count(*) FROM users WHERE role = 'lider' AND organizacion_id = ?");
    $stmt->execute([$orgId]);
    $count = $stmt->fetchColumn();

    echo "Total Leaders in Organization $orgId: $count\n";
    echo "--------------------------------------------------\n";

    // 3. List actual leaders
    $stmt = $pdo->prepare("SELECT id, username, name FROM users WHERE role = 'lider' AND organizacion_id = ?");
    $stmt->execute([$orgId]);
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($leaders) > 0) {
        echo "List of Leaders:\n";
        foreach ($leaders as $l) {
            echo "ID: " . $l['id'] . " | Name: " . $l['name'] . " | Username: " . $l['username'] . "\n";
        }
    }
    else {
        echo "No leaders found for this organization.\n";
    }

}
catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}