
<?php
// DATABASE CONNECTION

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "barangay_db";
$port = 3306;

$conn = mysqli_connect($host, $user, $pass, $dbname, $port);

if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

?>
