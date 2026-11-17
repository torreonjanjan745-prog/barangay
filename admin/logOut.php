
<?php
// ============================================
// SECURE SESSION CONFIGURATION
// ============================================

$isHttps = (
    isset($_SERVER['HTTPS']) &&
    $_SERVER['HTTPS'] !== 'off'
);

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_trans_sid', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

// ============================================
// PREVENT CACHED LOGOUT PAGE
// ============================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// ============================================
// ONLY ALLOW POST REQUESTS
// ============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("Method not allowed.");
}

// ============================================
// CHECK ADMIN SESSION
// ============================================

if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// ============================================
// VERIFY CSRF TOKEN
// ============================================

$token = $_POST["csrf_token"] ?? "";
$expectedToken = $_SESSION["admin_logout_csrf"] ?? "";

if (
    !is_string($token) ||
    $expectedToken === "" ||
    !hash_equals($expectedToken, $token)
) {
    http_response_code(403);
    exit("Invalid logout request.");
}

// ============================================
// DESTROY ADMIN SESSION
// ============================================

// Clear all session data
$_SESSION = [];

// Expire session cookie
if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        [
            "expires" => time() - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => $params["samesite"] ?? "Strict"
        ]
    );
}

// Destroy server-side session
session_destroy();

// ============================================
// REDIRECT TO ADMIN LOGIN
// ============================================

header("Location: login.php?logged_out=1");
exit();
?>
