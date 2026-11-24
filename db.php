
<?php
// ==========================================================
// BARANGAY GUINDACPAN
// CENTRAL DATABASE CONNECTION
//
// Database: barangay_db
// Connection: PDO
// Variable: $pdo
// ==========================================================

$host = "localhost";
$dbname = "barangay_db";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log(
        "[BARANGAY DATABASE ERROR] " . $e->getMessage()
    );

    http_response_code(500);

    exit("Database connection failed. Please contact the administrator.");

}
