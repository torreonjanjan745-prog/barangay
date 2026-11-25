
<?php
// ==========================================================
// BARANGAY GUINDACPAN
// RESIDENT LOGOUT SYSTEM
//
// Session Variables:
// resident_id
// resident_username
//
// Redirect: LogIn.php
// ==========================================================


// ==========================================================
// 1. START SESSION
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ==========================================================
// 2. CLEAR RESIDENT SESSION DATA
// ==========================================================

$_SESSION = [];


// ==========================================================
// 3. DELETE SESSION COOKIE
// ==========================================================

if (ini_get("session.use_cookies")) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}


// ==========================================================
// 4. DESTROY SESSION
// ==========================================================

session_destroy();


// ==========================================================
// 5. PREVENT BROWSER CACHE
// ==========================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");


// ==========================================================
// 6. REDIRECT TO RESIDENT LOGIN
// ==========================================================

header("Location: LogIn.php");
exit;

?>
