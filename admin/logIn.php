
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
// DATABASE CONNECTION
// ============================================

require_once __DIR__ . '/../db.php';

// Prevent cached login pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// ============================================
// ALREADY LOGGED IN?
// ============================================

if (!empty($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit();
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

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $csrf = $_POST["csrf_token"] ?? "";

    // Check CSRF token
    if (
        !is_string($csrf) ||
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

        // ====================================
        // CLEAN OLD LOGIN ATTEMPTS
        // ====================================

        mysqli_query(
            $conn,
            "DELETE FROM admin_login_attempts
             WHERE attempted_at < NOW() - INTERVAL 15 MINUTE"
        );

        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";

        // ====================================
        // CHECK LOGIN ATTEMPT LIMIT
        // 5 FAILED ATTEMPTS / 15 MINUTES
        // ====================================

        $attemptStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM admin_login_attempts
             WHERE username = ?
             AND ip_address = ?
             AND attempted_at >= NOW() - INTERVAL 15 MINUTE"
        );

        if (!$attemptStmt) {

            error_log("Admin rate-limit query failed: " . mysqli_error($conn));
            $error = "Login is temporarily unavailable. Please try again later.";

        } else {

            mysqli_stmt_bind_param(
                $attemptStmt,
                "ss",
                $username,
                $ipAddress
            );

            mysqli_stmt_execute($attemptStmt);

            $attemptResult = mysqli_stmt_get_result($attemptStmt);
            $attemptData = mysqli_fetch_assoc($attemptResult);

            $failedAttempts = (int)($attemptData["total"] ?? 0);

            mysqli_stmt_close($attemptStmt);

            if ($failedAttempts >= 5) {

                $error = "Too many failed attempts. Please try again after 15 minutes.";

            } else {

                // ====================================
                // FIND ADMIN ACCOUNT
                // ====================================

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id, full_name, username, password
                     FROM admin_users
                     WHERE username = ?
                     LIMIT 1"
                );

                if (!$stmt) {

                    error_log("Admin login query failed: " . mysqli_error($conn));
                    $error = "Login is temporarily unavailable.";

                } else {

                    mysqli_stmt_bind_param($stmt, "s", $username);
                    mysqli_stmt_execute($stmt);

                    $result = mysqli_stmt_get_result($stmt);
                    $admin = mysqli_fetch_assoc($result);

                    // ====================================
                    // VERIFY PASSWORD
                    // ====================================

                    if (
                        $admin &&
                        password_verify($password, $admin["password"])
                    ) {

                        // Upgrade old password hashes if needed
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

                            $updateStmt = mysqli_prepare(
                                $conn,
                                "UPDATE admin_users SET password = ? WHERE id = ?"
                            );

                            if ($updateStmt) {
                                mysqli_stmt_bind_param(
                                    $updateStmt,
                                    "si",
                                    $newHash,
                                    $admin["id"]
                                );

                                mysqli_stmt_execute($updateStmt);
                                mysqli_stmt_close($updateStmt);
                            }
                        }

                        // Clear failed attempts after successful login
                        $deleteStmt = mysqli_prepare(
                            $conn,
                            "DELETE FROM admin_login_attempts
                             WHERE username = ? AND ip_address = ?"
                        );

                        if ($deleteStmt) {
                            mysqli_stmt_bind_param(
                                $deleteStmt,
                                "ss",
                                $username,
                                $ipAddress
                            );

                            mysqli_stmt_execute($deleteStmt);
                            mysqli_stmt_close($deleteStmt);
                        }

                        // Regenerate session ID
                        session_regenerate_id(true);

                        // Set authenticated admin session
                        $_SESSION["admin_id"] = (int)$admin["id"];
                        $_SESSION["admin_name"] = $admin["full_name"];

                        // Rotate CSRF token
                        $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));

                        // Record login time
                        $_SESSION["admin_login_time"] = time();

                        mysqli_stmt_close($stmt);

                        header("Location: dashboard.php");
                        exit();

                    } else {

                        // Record failed login attempt
                        $insertAttempt = mysqli_prepare(
                            $conn,
                            "INSERT INTO admin_login_attempts
                             (username, ip_address)
                             VALUES (?, ?)"
                        );

                        if ($insertAttempt) {
                            mysqli_stmt_bind_param(
                                $insertAttempt,
                                "ss",
                                $username,
                                $ipAddress
                            );

                            mysqli_stmt_execute($insertAttempt);
                            mysqli_stmt_close($insertAttempt);
                        }

                        $error = "Invalid admin username or password.";

                    }

                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    // Rotate CSRF token after the POST attempt
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-5">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">

        <div class="text-center mb-8">

            <div class="w-16 h-16 bg-green-100 rounded-2xl mx-auto
                flex items-center justify-center text-3xl">
                🏛️
            </div>

            <h1 class="text-2xl font-bold text-green-950 mt-5">
                ADMIN PORTAL
            </h1>

            <p class="text-gray-500 mt-2">
                Barangay Guindacpan Administration
            </p>

        </div>

        <?php if ($error !== ""): ?>

            <div
                role="alert"
                class="bg-red-100 text-red-700 p-3 rounded-lg mb-5 text-sm">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form method="POST" id="adminLoginForm" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_csrf"],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div>

                <label class="block font-semibold text-gray-700 mb-2">
                    Admin Username
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    maxlength="100"
                    autocomplete="username"
                    placeholder="Enter admin username"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3
                    focus:outline-none focus:ring-2 focus:ring-green-700"
                >

            </div>

            <div>

                <label class="block font-semibold text-gray-700 mb-2">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter admin password"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3
                    focus:outline-none focus:ring-2 focus:ring-green-700"
                >

            </div>

            <button
                type="submit"
                id="loginButton"
                class="w-full bg-green-900 hover:bg-green-800
                text-white font-bold py-3 rounded-lg transition">

                ADMIN LOGIN

            </button>

        </form>

        <p class="text-center text-sm text-gray-500 mt-7">
            Authorized administrators only.
        </p>

        <div class="text-center mt-4">

            <a
                href="../main.php"
                class="text-sm text-green-800 hover:underline">
                Back to Homepage
            </a>

        </div>

    </div>

 
    </script>

</body>
<script src="admin.js"></script>
</html>
