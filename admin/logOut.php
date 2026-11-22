
<?php
// ============================================
// ADMIN LOGOUT - SECURE SESSION DESTRUCTION
// ============================================

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

session_name("BARANGAY_ADMIN_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

if (session_status() === PHP_SESSION_NONE) {

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Strict"
    ]);

    session_start();
}

// ============================================
// ONLY POST REQUEST
// ============================================

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: /Barangay%20System/admin/dashboard.php");
    exit;
}

// ============================================
// VERIFY CSRF TOKEN
// ============================================

$csrf = $_POST["csrf_token"] ?? "";

if (
    !is_string($csrf) ||
    empty($_SESSION["admin_logout_csrf"]) ||
    !hash_equals($_SESSION["admin_logout_csrf"], $csrf)
) {

    http_response_code(403);
    exit("Invalid logout request. Please refresh the dashboard.");
}

// ============================================
// CLEAR SESSION DATA
// ============================================

$_SESSION = [];

// ============================================
// DELETE SESSION COOKIE
// ============================================

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => "Strict"
    ]);
}

// ============================================
// DESTROY SESSION
// ============================================

session_destroy();

// ============================================
// REDIRECT TO LOGIN
// ============================================

header("Location: /Barangay System/admin/logOut.php");
exit;
