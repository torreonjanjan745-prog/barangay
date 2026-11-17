
<?php
// =============================================
// RESIDENT INFORMATION
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DATABASE CONNECTION
require_once __DIR__ . '/../db.php';

// CHECK IF RESIDENT IS LOGGED IN
if (!isset($_SESSION['resident_id'])) {
    header("Location: ../main.php");
    exit();
}

// GET RESIDENT ID
$residentId = $_SESSION['resident_id'];

// INITIALIZE VARIABLES
$resident = [];
$residentName = "Resident";

$totalRequests = 0;
$pendingRequests = 0;
$approvedRequests = 0;

try {

    // GET RESIDENT INFORMATION
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM residents WHERE id = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $residentId);

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $residentData = mysqli_fetch_assoc($result);

    if (!$residentData) {

        session_unset();
        session_destroy();

        header("Location: ../main.php");
        exit();
    }

    $resident = $residentData;

    $residentName = $resident['full_name']
        ?? $resident['fullName']
        ?? 'Resident';

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {

    error_log("Resident Information Error: " . $e->getMessage());

    die("Unable to load resident information. Please check your database connection.");
}
?>
