
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR LOGIN
// PDO VERSION
// Database: barangay_db
// ==================================================

// ==================================================
// SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

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
        "samesite" => "Lax"
    ]);

    session_start();
}

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

// ==================================================
// DATABASE CONNECTION
// ==================================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Admin login: PDO connection unavailable.");
    http_response_code(500);
    exit("Database connection error. Please contact the administrator.");
}

// ==================================================
// REDIRECT IF ALREADY LOGGED IN
// ==================================================

if (!empty($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

// ==================================================
// INITIAL VALUES
// ==================================================

$message = "";
$messageType = "";

// CSRF token
if (empty($_SESSION["admin_login_token"])) {
    $_SESSION["admin_login_token"] = bin2hex(random_bytes(32));
}

// Simple session-based login attempt limit
if (!isset($_SESSION["admin_login_attempts"])) {
    $_SESSION["admin_login_attempts"] = 0;
}

// ==================================================
// PROCESS LOGIN
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["admin_login_token"]) ||
        !hash_equals($_SESSION["admin_login_token"], $csrfToken)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (
        $_SESSION["admin_login_attempts"] >= 5
    ) {
        $message = "Too many login attempts. Please close and reopen your browser session or contact the administrator.";
        $messageType = "error";

    } elseif ($username === "" || $password === "") {
        $message = "Please enter your username and password.";
        $messageType = "error";

    } else {

        try {
            // Find administrator account
            $stmt = $pdo->prepare(
                "SELECT id, full_name, username, password
                 FROM admin_users
                 WHERE username = :username
                 LIMIT 1"
            );

            $stmt->execute([
                ":username" => $username
            ]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                $admin &&
                password_verify($password, $admin["password"])
            ) {

                // Regenerate session ID after successful login
                session_regenerate_id(true);

                // Set administrator session
                $_SESSION["admin_id"] = (int) $admin["id"];
                $_SESSION["admin_name"] = $admin["full_name"];
                $_SESSION["admin_username"] = $admin["username"];
                $_SESSION["admin_logged_in"] = true;
                $_SESSION["admin_login_time"] = time();

                // Reset attempts
                $_SESSION["admin_login_attempts"] = 0;

                // Remove login token
                unset($_SESSION["admin_login_token"]);

                // Redirect to admin dashboard
                header("Location: dashboard.php");
                exit;

            } else {

                $_SESSION["admin_login_attempts"]++;

                $message = "Incorrect username or password.";
                $messageType = "error";
            }

        } catch (PDOException $e) {

            error_log("Admin login database error: " . $e->getMessage());

            $message = "Unable to process your login. Please try again.";
            $messageType = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .login-card {
            animation: appear .5s ease;
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-5">

    <main class="w-full max-w-md">

        <div class="login-card bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden">

            <!-- HEADER -->
            <div class="text-center px-8 pt-10 pb-7">

                <div class="mx-auto w-20 h-20 rounded-2xl bg-green-500/10 border border-green-500/20 flex items-center justify-center mb-5">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="38"
                         height="38"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="#22c55e"
                         stroke-width="1.7"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                        <path d="m9 12 2 2 4-4"/>

                    </svg>

                </div>

                <h1 class="text-2xl font-bold text-white">
                    Administrator Login
                </h1>

                <p class="text-slate-400 text-sm mt-2">
                    Barangay Guindacpan Management System
                </p>

            </div>

            <!-- LOGIN FORM -->
            <div class="px-8 pb-9">

                <?php if ($message !== ""): ?>

                    <div class="mb-5 p-4 rounded-xl text-sm
                        <?= $messageType === "success"
                            ? "bg-green-500/10 border border-green-500/30 text-green-400"
                            : "bg-red-500/10 border border-red-500/30 text-red-400" ?>">

                        <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

                    </div>

                <?php endif; ?>

                <form method="POST" action="" class="space-y-5">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION["admin_login_token"], ENT_QUOTES, "UTF-8") ?>"
                    >

                    <!-- USERNAME -->
                    <div>

                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Username
                        </label>

                        <div class="relative">

                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     width="19"
                                     height="19"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>

                                </svg>

                            </span>

                            <input
                                type="text"
                                name="username"
                                required
                                maxlength="50"
                                autocomplete="username"
                                value="<?= htmlspecialchars($_POST["username"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                                placeholder="Enter admin username"
                                class="w-full pl-12 pr-4 py-3.5 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/10 transition">

                        </div>

                    </div>

                    <!-- PASSWORD -->
                    <div>

                        <label class="block text-sm font-medium text-slate-300 mb-2">
                            Password
                        </label>

                        <div class="relative">

                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">

                                <svg xmlns="http://www.w3.org/2000/svg"
                                     width="19"
                                     height="19"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <rect width="18" height="11" x="3" y="11" rx="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>

                                </svg>

                            </span>

                            <input
                                type="password"
                                name="password"
                                id="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                class="w-full pl-12 pr-14 py-3.5 rounded-xl bg-slate-800 border border-slate-700 text-white placeholder-slate-500 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/10 transition">

                            <button
                                type="button"
                                id="togglePassword"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white"
                                aria-label="Show password">

                                <svg id="eyeIcon"
                                     xmlns="http://www.w3.org/2000/svg"
                                     width="20"
                                     height="20"
                                     viewBox="0 0 24 24"
                                     fill="none"
                                     stroke="currentColor"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>

                                </svg>

                            </button>

                        </div>

                    </div>

                    <!-- SUBMIT -->
                    <button
                        type="submit"
                        class="w-full bg-green-600 hover:bg-green-700 active:scale-[0.99] text-white font-bold py-3.5 rounded-xl transition shadow-lg shadow-green-900/20">

                        Sign In to Dashboard

                    </button>

                </form>

                <div class="mt-7 pt-6 border-t border-slate-800 text-center">

                    <p class="text-xs text-slate-500">
                        Authorized administrator access only.
                    </p>

                    <a href="../main.php"
                       class="inline-block mt-4 text-sm text-slate-400 hover:text-green-400 transition">

                        ← Back to Homepage

                    </a>

                </div>

            </div>

        </div>

        <p class="text-center text-xs text-slate-600 mt-6">
            © <?= date("Y") ?> Barangay Guindacpan. All rights reserved.
        </p>

    </main>

    <script>
        const password = document.getElementById("password");
        const togglePassword = document.getElementById("togglePassword");

        togglePassword.addEventListener("click", function () {

            const isPassword = password.type === "password";

            password.type = isPassword ? "text" : "password";

            togglePassword.setAttribute(
                "aria-label",
                isPassword ? "Hide password" : "Show password"
            );

        });
    </script>

</body>
</html>
