<?php
$host = '127.0.0.1';
$dbname = 'politica';
$user = 'root';
$pass = 'mysql';

$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

echo "Checking MySQL Data ($dbname):\n";
$tables = ['users', 'registros', 'organizaciones'];
foreach ($tables as $t) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo " - $t: $count rows\n";
    }
    catch (Exception $e) {
        echo " - $t: MISSING or ERROR\n";
    }
}
?>