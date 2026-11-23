
<?php
// ==================================================
// BARANGAY GUINDACPAN
// RESIDENT - SUBMIT REQUEST
// SECURE PDO VERSION
//
// Database: barangay_db
// Table: requests
// Activity Table: activity_logs
// Session: Resident account
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER['HTTPS']) &&
           $_SERVER['HTTPS'] !== 'off';

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}


// ==================================================
// 2. CHECK RESIDENT LOGIN
// ==================================================

if (
    !isset($_SESSION['resident_id']) ||
    !isset($_SESSION['full_name'])
) {
    header('Location: login.php');
    exit;
}


// ==================================================
// 3. POST REQUEST ONLY
// ==================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: request.php');
    exit;
}


// ==================================================
// 4. DATABASE CONNECTION
// ==================================================

require_once __DIR__ . '/../config/database.php';

// Make sure database.php provides a PDO connection
// named $pdo.

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log('Database connection error.');
    http_response_code(500);
    exit('Database connection is unavailable.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);


// ==================================================
// 5. ACTIVITY LOGGER
// ==================================================

require_once __DIR__ . '/../activity_logger.php';


// ==================================================
// 6. CSRF VALIDATION
// ==================================================

if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $_POST['csrf_token']
    )
) {
    $_SESSION['error'] = 'Invalid request. Please try again.';
    header('Location: request.php');
    exit;
}


// ==================================================
// 7. GET FORM DATA
// ==================================================

$residentId = (int) $_SESSION['resident_id'];

$fullName = trim($_SESSION['full_name']);

$requestType = trim($_POST['request_type'] ?? '');

$purpose = trim($_POST['purpose'] ?? '');


// ==================================================
// 8. VALIDATION
// ==================================================

if ($requestType === '' || $purpose === '') {

    $_SESSION['error'] = 'Please complete all required fields.';

    header('Location: request.php');
    exit;
}

if (mb_strlen($requestType) > 100) {

    $_SESSION['error'] = 'Invalid request type.';

    header('Location: request.php');
    exit;
}

if (mb_strlen($purpose) > 1000) {

    $_SESSION['error'] = 'Purpose is too long.';

    header('Location: request.php');
    exit;
}


// ==================================================
// 9. GENERATE TRACKING CODE
// ==================================================

function generateTrackingCode(): string
{
    return 'BRG-' .
           date('Ymd') . '-' .
           strtoupper(bin2hex(random_bytes(4)));
}


// ==================================================
// 10. SAVE REQUEST
// ==================================================

try {

    $pdo->beginTransaction();

    $trackingCode = generateTrackingCode();

    $sql = "
        INSERT INTO requests (
            resident_id,
            tracking_code,
            request_type,
            purpose,
            status,
            admin_remarks,
            submitted_at,
            updated_at
        ) VALUES (
            :resident_id,
            :tracking_code,
            :request_type,
            :purpose,
            'Pending',
            NULL,
            NOW(),
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        ':resident_id' => $residentId,

        ':tracking_code' => $trackingCode,

        ':request_type' => $requestType,

        ':purpose' => $purpose

    ]);

    $requestId = (int) $pdo->lastInsertId();


    // ==================================================
    // 11. RECORD ACTIVITY LOG
    // ==================================================

    $logged = logActivity(
        $pdo,
        $residentId,
        $fullName,
        'Resident',
        'Submit Request',
        'Submitted a ' .
        $requestType .
        ' request. Tracking Code: ' .
        $trackingCode .
        '. Request ID: ' .
        $requestId
    );

    if (!$logged) {
        throw new RuntimeException('Unable to record activity log.');
    }


    // ==================================================
    // 12. COMMIT TRANSACTION
    // ==================================================

    $pdo->commit();

    // Regenerate CSRF token after successful submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    $_SESSION['success'] =
        'Your request was submitted successfully! Tracking Code: ' .
        $trackingCode;

    $_SESSION['tracking_code'] = $trackingCode;

    header('Location: request.php');
    exit;


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Submit Request Error: ' . $e->getMessage());

    $_SESSION['error'] =
        'Unable to submit your request. Please try again.';

    header('Location: request.php');
    exit;
}
