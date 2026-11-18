
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

// Check database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("Admin login: Database connection unavailable.");
    die("Database connection error. Please contact the administrator.");
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

    $usernameInput = $_POST["username"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $csrfInput = $_POST["csrf_token"] ?? "";

    // Reject unexpected array values before processing.
    $username = is_string($usernameInput) ? trim($usernameInput) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";
    $csrf = is_string($csrfInput) ? $csrfInput : "";

    // ========================================
    // VALIDATE CSRF TOKEN
    // ========================================

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

        // ====================================
        // CLEAN OLD LOGIN ATTEMPTS
        // ====================================

        $cleanup = mysqli_query(
            $conn,
            "DELETE FROM admin_login_attempts
             WHERE attempted_at < NOW() - INTERVAL 15 MINUTE"
        );

        if (!$cleanup) {
            error_log("Login attempt cleanup failed: " . mysqli_error($conn));
        }

        $ipAddress = $_SERVER["REMOTE_ADDR"] ?? "unknown";

        // ====================================
        // CHECK LOGIN ATTEMPT LIMIT
        // MAXIMUM 5 ATTEMPTS / 15 MINUTES
        // ====================================

        $attemptStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*)
             FROM admin_login_attempts
             WHERE username = ?
             AND ip_address = ?
             AND attempted_at >= NOW() - INTERVAL 15 MINUTE"
        );

        if (!$attemptStmt) {

            error_log("Rate limit query failed: " . mysqli_error($conn));

            $error = "Login is temporarily unavailable.";

        } else {

            mysqli_stmt_bind_param(
                $attemptStmt,
                "ss",
                $username,
                $ipAddress
            );

            if (!mysqli_stmt_execute($attemptStmt)) {
                error_log("Rate limit execution failed: " . mysqli_stmt_error($attemptStmt));
                mysqli_stmt_close($attemptStmt);
                $error = "Login is temporarily unavailable.";
            } else {
                mysqli_stmt_bind_result($attemptStmt, $failedAttempts);
                $fetchResult = mysqli_stmt_fetch($attemptStmt);
                mysqli_stmt_close($attemptStmt);

                if ($fetchResult !== true) {
                    error_log("Could not read admin login attempt count.");
                    $error = "Login is temporarily unavailable.";
                    $failedAttempts = 5;
                } else {
                    $failedAttempts = (int)$failedAttempts;
                }
            }

            // =================================
            // TOO MANY FAILED ATTEMPTS?
            // =================================

            if ($error !== "") {
                // Keep the generic error already assigned above.
            } elseif ($failedAttempts >= 5) {

                $error = "Too many failed attempts. Please try again after 15 minutes.";

            } else {

                // =================================
                // FIND ADMIN ACCOUNT
                // =================================

                $stmt = mysqli_prepare(
                    $conn,
                    "SELECT id, full_name, username, password
                     FROM admin_users
                     WHERE username = ?
                     LIMIT 1"
                );

                if (!$stmt) {

                    error_log("Admin query failed: " . mysqli_error($conn));

                    $error = "Login is temporarily unavailable.";

                } else {

                    mysqli_stmt_bind_param(
                        $stmt,
                        "s",
                        $username
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        error_log("Admin login query execution failed: " . mysqli_stmt_error($stmt));
                        mysqli_stmt_close($stmt);
                        $error = "Login is temporarily unavailable.";
                        $adminFound = false;
                    } else {
                        mysqli_stmt_bind_result(
                            $stmt,
                            $adminId,
                            $adminName,
                            $adminUsername,
                            $adminPassword
                        );

                        $adminFound = mysqli_stmt_fetch($stmt);
                        mysqli_stmt_close($stmt);
                    }

                    // =================================
                    // VERIFY ADMIN PASSWORD
                    // =================================

                    if (
                        $error === "" &&
                        $adminFound &&
                        password_verify($password, $adminPassword)
                    ) {

                        // =================================
                        // UPGRADE OLD PASSWORD HASH
                        // =================================

                        if (
                            password_needs_rehash(
                                $adminPassword,
                                PASSWORD_DEFAULT
                            )
                        ) {

                            $newHash = password_hash(
                                $password,
                                PASSWORD_DEFAULT
                            );

                            $updateStmt = mysqli_prepare(
                                $conn,
                                "UPDATE admin_users
                                 SET password = ?
                                 WHERE id = ?"
                            );

                            if ($updateStmt) {

                                mysqli_stmt_bind_param(
                                    $updateStmt,
                                    "si",
                                    $newHash,
                                    $adminId
                                );

                                mysqli_stmt_execute($updateStmt);
                                mysqli_stmt_close($updateStmt);
                            }
                        }

                        // =================================
                        // CLEAR FAILED LOGIN ATTEMPTS
                        // =================================

                        $deleteStmt = mysqli_prepare(
                            $conn,
                            "DELETE FROM admin_login_attempts
                             WHERE username = ?
                             AND ip_address = ?"
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

                        // =================================
                        // REGENERATE SESSION ID
                        // =================================

                        session_regenerate_id(true);

                        // =================================
                        // SET ADMIN SESSION
                        // =================================

                        $_SESSION["admin_id"] = (int)$adminId;
                        $_SESSION["admin_name"] = $adminName;
                        $_SESSION["admin_username"] = $adminUsername;
                        $_SESSION["admin_login_time"] = time();

                        // =================================
                        // ROTATE CSRF TOKEN
                        // =================================

                        $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));

                        // =================================
                        // REDIRECT TO ADMIN DASHBOARD
                        // =================================

                        header("Location: dashboard.php");
                        exit();

                    } elseif ($error === "") {

                        // =================================
                        // RECORD FAILED LOGIN ATTEMPT
                        // =================================

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

                            if (!mysqli_stmt_execute($insertAttempt)) {
                                error_log("Could not record failed admin login attempt: " . mysqli_stmt_error($insertAttempt));
                            }
                            mysqli_stmt_close($insertAttempt);
                        }

                        $error = "Invalid admin username or password.";
                    }
                }
            }
        }
    }

    // ========================================
    // ROTATE CSRF TOKEN AFTER POST
    // ========================================

    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Barangay Guindacpan</title>

    <!-- TAILWIND CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-5">

    <!-- =====================================
         ADMIN LOGIN CONTAINER
    ====================================== -->

    <div class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">

        <!-- HEADER -->

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

        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($error !== ""): ?>

            <div
                role="alert"
                class="bg-red-100 border border-red-200
                text-red-700 p-3 rounded-lg mb-5 text-sm">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>

        <!-- =====================================
             LOGIN FORM
        ====================================== -->

        <form
            method="POST"
            action=""
            id="adminLoginForm"
            class="space-y-5">

            <!-- CSRF TOKEN -->

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_csrf"],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <!-- USERNAME -->

            <div>

                <label
                    for="username"
                    class="block font-semibold text-gray-700 mb-2">

                    Admin Username

                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    required
                    maxlength="100"
                    autocomplete="username"
                    placeholder="Enter admin username"
                    class="w-full border border-gray-300 rounded-lg
                    px-4 py-3 focus:outline-none
                    focus:ring-2 focus:ring-green-700"
                >

            </div>

            <!-- PASSWORD -->

            <div>

                <label
                    for="password"
                    class="block font-semibold text-gray-700 mb-2">

                    Password

                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter admin password"
                    class="w-full border border-gray-300 rounded-lg
                    px-4 py-3 focus:outline-none
                    focus:ring-2 focus:ring-green-700"
                >

            </div>

            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                id="loginButton"
                class="w-full bg-green-900 hover:bg-green-800
                text-white font-bold py-3 rounded-lg
                transition duration-200">

                ADMIN LOGIN

            </button>

        </form>

        <!-- FOOTER -->

        <p class="text-center text-sm text-gray-500 mt-7">

            Authorized administrators only.

        </p>

        <!-- BACK TO HOMEPAGE -->

        <div class="text-center mt-4">

            <a
                href="../main.php"
                class="text-sm text-green-800 hover:underline">

                Back to Homepage

            </a>

        </div>

    </div>

    <!-- ADMIN JAVASCRIPT -->

    <script src="admin.js"></script>

</body>
</html>
