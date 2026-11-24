
<?php
// ==========================================================
// BARANGAY GUINDACPAN
// RESIDENT DOCUMENT REQUEST SUBMISSION
//
// Database: barangay_db
// Table: requests
// Connection: ../db.php
// Connection Type: PDO
// ==========================================================

// SESSION SECURITY
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// DATABASE CONNECTION
require_once __DIR__ . '/../db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection is unavailable.');
}

// ONLY LOGGED-IN RESIDENTS
if (!isset($_SESSION['resident_id'])) {
    header('Location: ../main.php');
    exit();
}

$residentId = (int) $_SESSION['resident_id'];

// ONLY POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: request.php');
    exit();
}

// FLASH MESSAGE HELPER
function redirectWithError($message) {
    $_SESSION['request_error'] = $message;
    header('Location: request.php');
    exit();
}

// VERIFY CSRF TOKEN
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    redirectWithError('Your session has expired. Please try submitting your request again.');
}

// GET FORM DATA
$requestType = trim($_POST['request_type'] ?? '');
$purpose = trim($_POST['purpose'] ?? '');

// ALLOWED DOCUMENT TYPES
$allowedTypes = [
    'Barangay Clearance',
    'Certificate of Residency',
    'Certificate of Indigency',
    'Business Clearance',
    'Certificate of Good Moral',
    'Other'
];

// VALIDATE DOCUMENT TYPE
if (!in_array($requestType, $allowedTypes, true)) {
    redirectWithError('Please select a valid document type.');
}

// VALIDATE PURPOSE
if ($purpose === '') {
    redirectWithError('Please provide the purpose of your request.');
}

if (mb_strlen($purpose, 'UTF-8') > 2000) {
    redirectWithError('The purpose must not exceed 2,000 characters.');
}

try {

    // VERIFY RESIDENT ACCOUNT
    $residentStmt = $pdo->prepare("
        SELECT id, full_name, status
        FROM residents
        WHERE id = :resident_id
        LIMIT 1
    ");

    $residentStmt->execute([
        ':resident_id' => $residentId
    ]);

    $resident = $residentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        session_unset();
        session_destroy();

        header('Location: ../main.php');
        exit();
    }

    // GENERATE UNIQUE TRACKING CODE
    $trackingCode = '';

    for ($attempt = 0; $attempt < 5; $attempt++) {

        $trackingCode = 'BG-' .
            date('Ymd') . '-' .
            strtoupper(bin2hex(random_bytes(4)));

        $checkStmt = $pdo->prepare("
            SELECT id
            FROM requests
            WHERE tracking_code = :tracking_code
            LIMIT 1
        ");

        $checkStmt->execute([
            ':tracking_code' => $trackingCode
        ]);

        if (!$checkStmt->fetch()) {
            break;
        }

        $trackingCode = '';
    }

    if ($trackingCode === '') {
        throw new RuntimeException('Unable to generate a unique tracking code.');
    }

    // INSERT REQUEST
    $insertStmt = $pdo->prepare("
        INSERT INTO requests (
            resident_id,
            tracking_code,
            request_type,
            purpose,
            status,
            submitted_at,
            updated_at
        ) VALUES (
            :resident_id,
            :tracking_code,
            :request_type,
            :purpose,
            'Pending',
            NOW(),
            NOW()
        )
    ");

    $insertStmt->execute([
        ':resident_id' => $residentId,
        ':tracking_code' => $trackingCode,
        ':request_type' => $requestType,
        ':purpose' => $purpose
    ]);

    // SAVE SUCCESS MESSAGE
    $_SESSION['request_success'] = 'Your document request has been submitted successfully.';
    $_SESSION['request_tracking_code'] = $trackingCode;

    // REGENERATE CSRF TOKEN
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // REDIRECT TO REQUEST PAGE
    header('Location: request.php');
    exit();

} catch (PDOException $e) {

    error_log('[RESIDENT REQUEST SUBMISSION ERROR] ' . $e->getMessage());

    redirectWithError(
        'Unable to submit your request right now. Please try again later.'
    );

} catch (Throwable $e) {

    error_log('[RESIDENT REQUEST ERROR] ' . $e->getMessage());

    redirectWithError(
        'Something went wrong while processing your request.'
    );
}
