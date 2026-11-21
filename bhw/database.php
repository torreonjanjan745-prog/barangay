
<?php
// ============================================
// BHW DATABASE CONNECTION - MYSQLI
// ============================================

// LOAD MAIN DATABASE CONNECTION
require_once __DIR__ . "/../db.php";

// VERIFY MYSQLI CONNECTION
if (
    !isset($conn) ||
    !($conn instanceof mysqli) ||
    $conn->connect_errno
) {
    error_log("BHW Database Error: MySQLi connection is unavailable.");

    http_response_code(500);
    die("Database connection error. Please check the main db.php file.");
}

// SET DATABASE CHARACTER SET
if (!$conn->set_charset("utf8mb4")) {

    error_log("BHW Charset Error: " . $conn->error);

    http_response_code(500);
    die("Database configuration error.");
}

?>
