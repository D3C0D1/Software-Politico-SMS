<?php
require_once 'config.php';

// Mock session for testing script directly
if (!isset($_SESSION['user_id'])) {
    // Try to find admin_cholismo
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = 'admin_cholismo'");
    $stmt->execute();
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['organizacion_id'] = 1; // Default or fetch from somewhere if applicable
        echo "Simulating user: " . $user['username'] . " (ID: " . $user['id'] . ")\n";
    }
    else {
        echo "User admin_cholismo not found.\n";
    }
}

$current_org_id = $_SESSION['organizacion_id'] ?? 1;
echo "Organization ID: $current_org_id\n";

// 1. Leader Stats
$stmt = $pdo->prepare("
    SELECT u.name, COUNT(r.id) as total_voters 
    FROM registros r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.tipo = 'votante' AND r.organizacion_id = ?
    GROUP BY r.user_id 
    ORDER BY total_voters DESC 
    LIMIT 10
");
$stmt->execute([$current_org_id]);
$leaderStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Leader Stats: " . count($leaderStats) . " rows found.\n";
print_r($leaderStats);

// 2. Trend Stats
$sqlTrend = "SELECT DATE(created_at) as fecha, COUNT(*) as count 
                FROM registros 
                WHERE tipo = 'votante' AND organizacion_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                GROUP BY DATE(created_at) 
                ORDER BY fecha ASC";
$stmt = $pdo->prepare($sqlTrend);
$stmt->execute([$current_org_id]);
$trendStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Trend Stats: " . count($trendStats) . " rows found.\n";
print_r($trendStats);

// 3. Duplicates
$sqlDupeCount = "SELECT COUNT(*) FROM registros WHERE tipo = 'votante' AND organizacion_id = ? AND cedula IN (
    SELECT cedula FROM registros WHERE tipo = 'votante' AND organizacion_id = ? GROUP BY cedula HAVING COUNT(*) > 1
)";
$stmt = $pdo->prepare($sqlDupeCount);
$stmt->execute([$current_org_id, $current_org_id]);
$totalDuplicates = $stmt->fetchColumn();
echo "Total Duplicates: $totalDuplicates\n";

?>