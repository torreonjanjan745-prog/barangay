
<?php
// ============================================
// MAIN DATABASE CONNECTION
// ============================================

$host = "localhost";
$dbname = "barangay_db";
$username = "root";
$password = "";
$port = 3306;

// Create MySQLi connection
$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

// Check connection
if (!$conn) {

    error_log("Database Connection Failed: " . mysqli_connect_error());

    die("Database connection error. Please contact the administrator.");

}

// Set character encoding
mysqli_set_charset($conn, "utf8mb4");

?>
