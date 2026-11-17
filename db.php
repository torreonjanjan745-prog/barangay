

<?php

// =====================================
// DATABASE CONNECTION
// =====================================

$host = "localhost";
$user = "root";
$pass = "";
$dbname   = "barangay_db";
$port= 3306;

// CONNECT TO MYSQL DATABASE
$conn = mysqli_connect($host, $user, $pass, $dbname);

// CHECK DATABASE CONNECTION
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
   
}
echo "Connected";

// SET CHARACTER ENCODING
mysqli_set_charset($conn, "utf8mb4");

?>
