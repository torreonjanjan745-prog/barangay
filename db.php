
<?php
// ============================================
// MAIN DATABASE CONNECTION
// ============================================

$host = "localhost";
$dbname = "barangay_health_db";
$username = "root";
$password = "";
$port = 3306;

// CREATE MYSQLI CONNECTION
$conn = mysqli_connect(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

// CHECK CONNECTION
if (!$conn) {
    error_log("Database Connection Failed: " . mysqli_connect_error());
    die("Database connection error. Please contact the administrator.");
}

// SET CHARACTER ENCODING
mysqli_set_charset($conn, "utf8mb4");

?>
