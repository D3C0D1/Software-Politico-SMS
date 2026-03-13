<?php
// Try variations of default passwords for AMPPS
$password_candidates = ['mysql', 'root', '', '123456'];
$host = '127.0.0.1';
$dbname = 'politica';
$user = 'root';

echo "Testing MySQL Connections...\n";

foreach ($password_candidates as $password) {
    try {
        echo "Trying User: $user, Pass: '$password' ... ";
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $password); // Connecting to server first
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "SUCCESS!\n";

        // Check database
        try {
            $pdo->exec("USE $dbname");
            echo " - Use DB '$dbname': SUCCESS\n";

            // Check if tables exist
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            if (empty($tables)) {
                echo " - Tables: NONE (Empty Database)\n";
            }
            else {
                echo " - Tables Found: " . implode(", ", $tables) . "\n";
            }

        }
        catch (PDOException $e) {
            echo " - Use DB '$dbname': FAILED (Creating it...)\n";
            $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
            echo " - DB Created.\n";
        }

        // Save working credential to a temp file
        file_put_contents('mysql_creds.json', json_encode(['host' => $host, 'user' => $user, 'pass' => $password, 'db' => $dbname]));
        exit(0);

    }
    catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}
echo "Could not connect with any common default password.\n";
exit(1);
?>