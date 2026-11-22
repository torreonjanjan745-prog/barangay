
<?php
// =============================================
// BHW DATABASE CONNECTION - PDO
// Database: barangay_health_db
// =============================================

$host = "localhost";
$port = "3306";
$dbname = "barangay_health_db";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log("BHW Database Error: " . $e->getMessage());

    http_response_code(500);
    exit("BHW database connection failed.");
}
