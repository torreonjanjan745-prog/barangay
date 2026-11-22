
<?php

$host = "localhost";
$port = "3306";
$dbname = "barangay_db";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    echo "DATABASE CONNECTION SUCCESSFUL!";
    
} catch (PDOException $e) {

    echo "DATABASE CONNECTION FAILED!";
    echo "<br><br>";
    echo "ERROR DETAILS: " . htmlspecialchars($e->getMessage());

}
