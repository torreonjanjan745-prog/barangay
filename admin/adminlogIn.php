
<?php
// ============================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR LOGIN SYSTEM
// PDO VERSION
// Database: barangay_db
// ============================================

// ============================================
// SESSION SECURITY
// ============================================

$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_ADMIN_SESSION");

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");

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
// SECURITY HEADERS
// ============================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// ============================================
// HTML ESCAPING
// ============================================

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// ============================================
// MAIN DATABASE CONNECTION
// ============================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Admin Login: PDO connection unavailable.");
    http_response_code(500);
    exit("Database connection error. Please contact the administrator.");
}

// ============================================
// ALREADY LOGGED IN
// ============================================

if (!empty($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

// ============================================
// CSRF TOKEN
// ============================================

if (empty($_SESSION["admin_csrf"])) {
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}

$error = "";

// ============================================
// LOGIN PROCESS
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usernameInput = $_POST["username"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $csrfInput = $_POST["csrf_token"] ?? "";

    $username = is_string($usernameInput)
        ? trim($usernameInput)
        : "";

    $password = is_string($passwordInput)
        ? $passwordInput
        : "";

    $csrf = is_string($csrfInput)
        ? $csrfInput
        : "";

    if (
        !hash_equals($_SESSION["admin_csrf"], $csrf)
    ) {

        $error = "Invalid form submission. Please refresh the page.";

    } elseif (
        $username === "" ||
        $password === "" ||
        strlen($username) > 100
    ) {

        $error = "Invalid admin username or password.";

    } else {

        try {

            // ====================================
            // CLEAN OLD LOGIN ATTEMPTS
            // ====================================

            $cleanup = $pdo->prepare("
                DELETE FROM admin_login_attempts
                WHERE attempted_at < NOW() - INTERVAL 15 MINUTE
            ");

            $cleanup->execute();

            $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";

            // ====================================
            // CHECK LOGIN ATTEMPTS
            // MAXIMUM 5 ATTEMPTS
            // ====================================

            $attemptStmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM admin_login_attempts
                WHERE username = :username
                AND ip_address = :ip_address
                AND attempted_at >= NOW() - INTERVAL 15 MINUTE
            ");

            $attemptStmt->execute([
                ":username" => $username,
                ":ip_address" => $ipAddress
            ]);

            $failedAttempts = (int)$attemptStmt->fetchColumn();

            if ($failedAttempts >= 5) {

                $error = "Too many failed attempts. Please try again after 15 minutes.";

            } else {

                // ====================================
                // FIND ADMIN ACCOUNT
                // ====================================

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        full_name,
                        username,
                        password
                    FROM admin_users
                    WHERE username = :username
                    LIMIT 1
                ");

                $stmt->execute([
                    ":username" => $username
                ]);

                $admin = $stmt->fetch();

                // ====================================
                // VERIFY PASSWORD
                // ====================================

                if (
                    $admin &&
                    password_verify($password, $admin["password"])
                ) {

                    // =================================
                    // UPDATE PASSWORD HASH IF NEEDED
                    // =================================

                    if (
                        password_needs_rehash(
                            $admin["password"],
                            PASSWORD_DEFAULT
                        )
                    ) {

                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        $updateStmt = $pdo->prepare("
                            UPDATE admin_users
                            SET password = :password
                            WHERE id = :id
                        ");

                        $updateStmt->execute([
                            ":password" => $newHash,
                            ":id" => (int)$admin["id"]
                        ]);
                    }

                    // =================================
                    // CLEAR FAILED ATTEMPTS
                    // =================================

                    $deleteStmt = $pdo->prepare("
                        DELETE FROM admin_login_attempts
                        WHERE username = :username
                        AND ip_address = :ip_address
                    ");

                    $deleteStmt->execute([
                        ":username" => $username,
                        ":ip_address" => $ipAddress
                    ]);

                    // =================================
                    // REGENERATE SESSION
                    // =================================

                    session_regenerate_id(true);

                    // =================================
                    // ADMIN SESSION
                    // =================================

                    $_SESSION["admin_id"] = (int)$admin["id"];
                    $_SESSION["admin_name"] = $admin["full_name"];
                    $_SESSION["admin_username"] = $admin["username"];
                    $_SESSION["admin_login_time"] = time();
                    $_SESSION["admin_last_activity"] = time();

                    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));

                    // =================================
                    // REDIRECT
                    // =================================

                    header("Location: dashboard.php");
                    exit;

                } else {

                    // =================================
                    // RECORD FAILED LOGIN
                    // =================================

                    $insertAttempt = $pdo->prepare("
                        INSERT INTO admin_login_attempts
                        (username, ip_address)
                        VALUES
                        (:username, :ip_address)
                    ");

                    $insertAttempt->execute([
                        ":username" => $username,
                        ":ip_address" => $ipAddress
                    ]);

                    $error = "Invalid admin username or password.";
                }
            }

        } catch (PDOException $e) {

            error_log("Admin Login PDO Error: " . $e->getMessage());

            $error = "Login is temporarily unavailable. Please check the administrator database tables.";
        }
    }

    // ROTATE CSRF TOKEN
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Login | Barangay Guindacpan</title>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: #020617;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.login-container {
    width: 100%;
    max-width: 420px;
    background: white;
    border-radius: 18px;
    padding: 38px;
    box-shadow: 0 20px 60px rgba(0,0,0,.3);
}

.logo-container {
    width: 85px;
    height: 85px;
    margin: 0 auto 20px;
    border-radius: 18px;
    background: #dcfce7;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.logo-container img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.header {
    text-align: center;
    margin-bottom: 30px;
}

.header h1 {
    color: #14532d;
    font-size: 25px;
    font-weight: 800;
    margin-bottom: 10px;
}

.header p {
    color: #64748b;
    font-size: 14px;
}

.form-group {
    margin-bottom: 22px;
}

.form-group label {
    display: block;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
    margin-bottom: 9px;
}

.form-group input {
    width: 100%;
    padding: 14px;
    border: 1px solid #d1d5db;
    border-radius: 9px;
    font-size: 15px;
    outline: none;
}

.form-group input:focus {
    border-color: #15803d;
    box-shadow: 0 0 0 3px rgba(21,128,61,.12);
}

.login-button {
    width: 100%;
    padding: 15px;
    background: #14532d;
    color: white;
    border: none;
    border-radius: 9px;
    font-size: 15px;
    font-weight: bold;
    cursor: pointer;
    transition: .2s;
}

.login-button:hover {
    background: #166534;
}

.error-message {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
    padding: 13px;
    border-radius: 9px;
    font-size: 13px;
    margin-bottom: 22px;
}

.footer {
    text-align: center;
    margin-top: 27px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.8;
}

.footer a {
    color: #166534;
    text-decoration: none;
    font-weight: bold;
}

.footer a:hover {
    text-decoration: underline;
}

@media(max-width: 480px) {
    .login-container {
        padding: 26px 20px;
    }
}

</style>

</head>

<body>

<div class="login-container">

    <div class="header">

        <div class="logo-container">
            <img
                src="../asset/logo.jpg"
                alt="Barangay Guindacpan Logo"
            >
        </div>

        <h1>SECRETARY PORTAL</h1>

        <p>Barangay Guindacpan Administration</p>

    </div>

    <?php if ($error !== ""): ?>

        <div class="error-message" role="alert">
            <?= e($error) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION["admin_csrf"]) ?>"
        >

        <div class="form-group">

            <label for="username">
                Admin Username
            </label>

            <input
                type="text"
                id="username"
                name="username"
                placeholder="Enter admin username"
                maxlength="100"
                autocomplete="username"
                required
            >

        </div>

        <div class="form-group">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                placeholder="Enter admin password"
                autocomplete="current-password"
                required
            >

        </div>

        <button type="submit" class="login-button">
            ADMIN LOGIN
        </button>

    </form>

    <div class="footer">

        <p>Authorized administrators only.</p>

        <p>
            <a href="../main.php">
                Back to Homepage
            </a>
        </p>

    </div>

</div>

</body>
</html>
