
<?php
// =====================================================
// BARANGAY GUINDACPAN
// RESIDENT LOGIN SYSTEM
// Complete PDO Version
// Database: barangay_db
// =====================================================

// =====================================================
// SESSION SECURITY
// =====================================================

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_RESIDENT_SESSION");

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

// =====================================================
// PREVENT CACHED LOGIN PAGE
// =====================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// =====================================================
// DATABASE CONNECTION
// =====================================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection is unavailable.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// =====================================================
// HELPER
// =====================================================

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$error = "";
$username = "";

// =====================================================
// REDIRECT IF ALREADY LOGGED IN
// =====================================================

if (!empty($_SESSION["resident_id"])) {

    header("Location: ../tracking/dashboard.php");
    exit;

}

// =====================================================
// LOGIN PROCESS
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        try {

            // FIND RESIDENT ACCOUNT
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    full_name,
                    username,
                    password,
                    status
                FROM residents
                WHERE username = :username
                LIMIT 1
            ");

            $stmt->execute([
                ":username" => $username
            ]);

            $resident = $stmt->fetch();

            // VERIFY PASSWORD
            if (
                $resident &&
                password_verify($password, $resident["password"])
            ) {

                // CHECK RESIDENT STATUS
                if (
                    strtolower(trim($resident["status"] ?? "")) !== "approved"
                ) {

                    $error = "Your account is not yet approved. Please contact the Barangay Administrator.";

                } else {

                    // REGENERATE SESSION ID
                    session_regenerate_id(true);

                    // CLEAR OLD SESSION DATA
                    $_SESSION = [];

                    // STORE RESIDENT INFORMATION
                    $_SESSION["resident_id"] = (int)$resident["id"];
                    $_SESSION["resident_name"] = $resident["full_name"];
                    $_SESSION["resident_username"] = $resident["username"];

                    $_SESSION["resident_logged_in"] = true;
                    $_SESSION["login_time"] = time();

                    // REDIRECT TO TRACKING DASHBOARD
                    header("Location: ../tracking/dashboard.php");
                    exit;

                }

            } else {

                $error = "Invalid username or password.";

            }

        } catch (PDOException $ex) {

            error_log("Resident Login Error: " . $ex->getMessage());

            $error = "Unable to process your login. Please try again.";

        }

    }

}

// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION["resident_login_token"])) {

    $_SESSION["resident_login_token"] = bin2hex(random_bytes(32));

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#052e16">

    <title>Resident Login | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background:
                radial-gradient(circle at top left, rgba(34,197,94,.15), transparent 35%),
                radial-gradient(circle at bottom right, rgba(16,185,129,.12), transparent 35%),
                #020617;
        }

        .login-card {
            animation: appear .6s ease;
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

        .input-field:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21,128,61,.12);
        }

    </style>

</head>

<body class="min-h-screen flex items-center justify-center p-5">

    <main class="w-full max-w-md">

        <!-- LOGIN CARD -->
        <div class="login-card bg-white rounded-3xl shadow-2xl overflow-hidden">

            <!-- HEADER -->
            <div class="bg-green-950 text-white text-center px-8 py-9">

                <div class="w-24 h-24 bg-white rounded-2xl mx-auto mb-5
                            flex items-center justify-center overflow-hidden shadow-lg">

                    <img
                        src="../asset/logo.jpg"
                        alt="Barangay Guindacpan Logo"
                        class="w-full h-full object-contain"
                    >

                </div>

                <h1 class="text-2xl font-extrabold tracking-wide">

                    BARANGAY GUINDACPAN

                </h1>

                <p class="text-green-200 text-sm mt-2">

                    Resident Portal

                </p>

                <div class="w-14 h-1 bg-green-400 rounded-full mx-auto mt-5"></div>

            </div>

            <!-- FORM CONTENT -->
            <div class="p-7 sm:p-9">

                <div class="mb-7">

                    <h2 class="text-2xl font-extrabold text-slate-900">

                        Welcome Back!

                    </h2>

                    <p class="text-sm text-slate-500 mt-2">

                        Sign in to access your resident tracking dashboard.

                    </p>

                </div>

                <!-- ERROR MESSAGE -->
                <?php if ($error !== ""): ?>

                    <div class="flex items-start gap-3 bg-red-50
                                border border-red-200 text-red-700
                                p-4 rounded-xl mb-6">

                        <i data-lucide="alert-circle"
                           class="w-5 h-5 flex-shrink-0 mt-0.5"></i>

                        <p class="text-sm font-medium">

                            <?= e($error) ?>

                        </p>

                    </div>

                <?php endif; ?>

                <!-- LOGIN FORM -->
                <form method="POST" action="" class="space-y-5">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION["resident_login_token"]) ?>"
                    >

                    <!-- USERNAME -->
                    <div>

                        <label class="block text-sm font-bold
                                      text-slate-700 mb-2">

                            Username

                        </label>

                        <div class="relative">

                            <i data-lucide="user"
                               class="absolute left-4 top-1/2
                               -translate-y-1/2 w-5 h-5 text-slate-400"></i>

                            <input
                                type="text"
                                name="username"
                                value="<?= e($username) ?>"
                                placeholder="Enter your username"
                                autocomplete="username"
                                maxlength="20"
                                required
                                class="input-field w-full border border-slate-300
                                rounded-xl pl-12 pr-4 py-3.5
                                outline-none transition text-sm"
                            >

                        </div>

                    </div>

                    <!-- PASSWORD -->
                    <div>

                        <div class="flex items-center justify-between mb-2">

                            <label class="block text-sm font-bold text-slate-700">

                                Password

                            </label>

                        </div>

                        <div class="relative">

                            <i data-lucide="lock"
                               class="absolute left-4 top-1/2
                               -translate-y-1/2 w-5 h-5 text-slate-400"></i>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                class="input-field w-full border border-slate-300
                                rounded-xl pl-12 pr-12 py-3.5
                                outline-none transition text-sm"
                            >

                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute right-4 top-1/2
                                -translate-y-1/2 text-slate-400
                                hover:text-green-700">

                                <i data-lucide="eye" id="eyeIcon"
                                   class="w-5 h-5"></i>

                            </button>

                        </div>

                    </div>

                    <!-- LOGIN BUTTON -->
                    <button
                        type="submit"
                        id="loginButton"
                        class="w-full bg-green-800 hover:bg-green-900
                        text-white font-bold py-4 rounded-xl
                        transition duration-200 shadow-lg
                        shadow-green-900/20 flex items-center
                        justify-center gap-2">

                        <i data-lucide="log-in" class="w-5 h-5"></i>

                        LOGIN TO PORTAL

                    </button>

                </form>

                <!-- REGISTER -->
                <div class="text-center mt-7">

                    <p class="text-sm text-slate-500">

                        Don't have an account?

                        <a href="../main.php"
                           class="text-green-800 font-bold hover:underline">

                            Register here

                        </a>

                    </p>

                </div>

                <!-- HOMEPAGE -->
                <div class="text-center mt-5">

                    <a href="../main.php"
                       class="inline-flex items-center gap-2
                       text-sm text-slate-400 hover:text-green-800">

                        <i data-lucide="arrow-left" class="w-4 h-4"></i>

                        Back to Homepage

                    </a>

                </div>

            </div>

        </div>

        <!-- FOOTER -->
        <p class="text-center text-xs text-slate-500 mt-6">

            © <?= date("Y") ?> Barangay Guindacpan.
            All Rights Reserved.

        </p>

    </main>

    <script>

        lucide.createIcons();

        // SHOW / HIDE PASSWORD
        function togglePassword() {

            const password = document.getElementById("password");
            const eyeIcon = document.getElementById("eyeIcon");

            if (password.type === "password") {

                password.type = "text";
                eyeIcon.setAttribute("data-lucide", "eye-off");

            } else {

                password.type = "password";
                eyeIcon.setAttribute("data-lucide", "eye");

            }

            lucide.createIcons();

        }

    </script>

</body>
</html>
