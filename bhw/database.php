
<?php
// ============================================
// BHW DATABASE CONNECTION
// ============================================

require_once __DIR__ . "/../db.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("BHW Database Error: MySQLi connection is unavailable.");
}

?>
