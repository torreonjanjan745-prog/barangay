
<?php

// =====================================
// DATABASE CONNECTION
// =====================================

$host = "localhost";
$dbname = "barangay_db";
$username = "root";
$password = "";
$port = 3306;

try {

    // Create PDO database connection
    $pdo = new PDO(
        "mysql:host=$host;$port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Enable PDO error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    // Log the actual error for debugging
    error_log($e->getMessage());

    // Display a user-friendly message
    die("Database connection failed. Please check your database settings.");

}

?>
