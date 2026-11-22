
<?php
// =============================================
// DATABASE DIAGNOSTIC TEST
// Temporary local testing only
// =============================================

error_reporting(E_ALL);
ini_set("display_errors", "1");

echo "<h2>PHP Database Diagnostic</h2>";

// Check PDO
if (!class_exists("PDO")) {
    exit("ERROR: PDO extension is not enabled.");
}

echo "<p>PDO: ENABLED</p>";

// Check MySQL driver
$drivers = PDO::getAvailableDrivers();

echo "<p>Available PDO Drivers: ";
echo htmlspecialchars(implode(", ", $drivers));
echo "</p>";

if (!in_array("mysql", $drivers, true)) {
    exit("ERROR: PDO MySQL driver is not enabled.");
}

// Database settings
$host = "localhost";
$port = "3306";
$username = "root";
$password = "";

// Test both databases
$databases = [
    "barangay_db",
    "barangay_health_db"
];

foreach ($databases as $dbname) {

    echo "<hr>";
    echo "<h3>Testing: " . htmlspecialchars($dbname) . "</h3>";

    try {

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );

        echo "<p style='color:green;'>CONNECTED SUCCESSFULLY!</p>";

    } catch (PDOException $e) {

        echo "<p style='color:red;'>CONNECTION FAILED:</p>";

        echo "<pre>";
        echo htmlspecialchars($e->getMessage());
        echo "</pre>";
    }
}
