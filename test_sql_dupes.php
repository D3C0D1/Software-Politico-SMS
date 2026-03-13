<?php
require_once 'config.php';

// Mock session
$_SESSION['user_id'] = 1; // Assuming 1 exists
$_SESSION['organizacion_id'] = 1;
$current_org_id = 1;

try {
    echo "Testing Dupe Count Query...\n";
    $sqlDupeCount = "SELECT COUNT(*) FROM registros WHERE tipo = 'votante' AND organizacion_id = ? AND cedula IN (
        SELECT cedula FROM registros WHERE tipo = 'votante' AND organizacion_id = ? GROUP BY cedula HAVING COUNT(*) > 1
    )";
    $stmt = $pdo->prepare($sqlDupeCount);
    $stmt->execute([$current_org_id, $current_org_id]);
    $totalDuplicates = $stmt->fetchColumn();
    echo "Total Duplicates: $totalDuplicates\n";

    echo "Testing Dupe Leaders Query...\n";
    $sqlDupeLeaders = "SELECT u.id as user_id, u.name, COUNT(r.id) as dupe_count 
                       FROM registros r 
                       JOIN users u ON r.user_id = u.id 
                       WHERE r.tipo = 'votante' AND r.organizacion_id = ? 
                       AND r.cedula IN (
                           SELECT cedula FROM registros WHERE tipo = 'votante' AND organizacion_id = ? GROUP BY cedula HAVING COUNT(*) > 1
                       ) 
                       GROUP BY r.user_id 
                       ORDER BY dupe_count DESC";
    $stmt = $pdo->prepare($sqlDupeLeaders);
    $stmt->execute([$current_org_id, $current_org_id]);
    $dupeLeadersStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Leaders found: " . count($dupeLeadersStats) . "\n";
    print_r($dupeLeadersStats);

}
catch (PDOException $e) {
    echo "PDO Error: " . $e->getMessage();
}
catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
}
?>