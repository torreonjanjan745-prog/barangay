
<?php
// =============================================
// BHW LOGIN SYSTEM - MYSQLI
// =============================================

// SESSION CONFIGURATION
session_name("BARANGAY_BHW_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

// DATABASE CONNECTION
require_once __DIR__ . "/database.php";

mysqli_report(MYSQLI_REPORT_OFF);

// CHECK DATABASE CONNECTION
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("BHW Login: Invalid MySQLi database connection.");
    http_response_code(500);
    exit("Database connection error. Please check database.php.");
}

// ALREADY LOGGED IN
if (!empty($_SESSION["bhw_id"])) {
    header("Location: dashboard.php");
    exit;
}

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["bhw_login_csrf"])) {
    $_SESSION["bhw_login_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$email = "";

// =============================================
// HANDLE LOGIN
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $emailInput = $_POST["email"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $email = is_string($emailInput) ? trim($emailInput) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["bhw_login_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";

    } elseif ($email === "" || $password === "") {
        $message = "Please enter your email and password.";

    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($email) > 150
    ) {
        $message = "Invalid email or password.";

    } else {

        // =============================================
        // FETCH BHW ACCOUNT
        // TABLE: bhw_accounts
        // =============================================

        $sql = "
            SELECT
                id,
                full_name,
                email,
                password,
                status
            FROM bhw_accounts
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            error_log("BHW Login Prepare Error: " . mysqli_error($conn));
            $message = "Unable to process login. Please check the database structure.";

        } else {

            mysqli_stmt_bind_param($stmt, "s", $email);

            if (!mysqli_stmt_execute($stmt)) {

                error_log("BHW Login Execute Error: " . mysqli_stmt_error($stmt));
                $message = "Unable to process login. Please try again.";

            } else {

                mysqli_stmt_bind_result(
                    $stmt,
                    $accountId,
                    $fullName,
                    $accountEmail,
                    $hashedPassword,
                    $accountStatus
                );

                if (mysqli_stmt_fetch($stmt)) {

                    // VERIFY PASSWORD
                    if (
                        is_string($hashedPassword) &&
                        password_verify($password, $hashedPassword)
                    ) {

                        // CHECK ADMIN APPROVAL
                        if (strcasecmp(trim((string)$accountStatus), "Active") !== 0) {

                            $message = "Your account is pending administrator approval. Please contact your administrator.";

                        } else {

                            // REGENERATE SESSION ID
                            session_regenerate_id(true);

                            // STORE BHW SESSION
                            $_SESSION["bhw_id"] = (int)$accountId;
                            $_SESSION["bhw_name"] = $fullName;
                            $_SESSION["bhw_email"] = $accountEmail;

                            // REMOVE LOGIN CSRF TOKEN
                            unset($_SESSION["bhw_login_csrf"]);

                            mysqli_stmt_close($stmt);

                            // REDIRECT TO DASHBOARD
                            header("Location: dashboard.php");
                            exit;
                        }

                    } else {

                        $message = "Invalid email or password.";
                    }

                } else {

                    $message = "Invalid email or password.";
                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}

// =============================================
// GENERATE NEW CSRF TOKEN IF NECESSARY
// =============================================

if (empty($_SESSION["bhw_login_csrf"])) {
    $_SESSION["bhw_login_csrf"] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Login | Barangay Health System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .input-field:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }
    </style>

</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-5">

<div class="w-full max-w-5xl bg-white rounded-3xl shadow-xl overflow-hidden grid md:grid-cols-2">

    <!-- LEFT PANEL -->

    <div class="hidden md:flex flex-col justify-between bg-gradient-to-br from-blue-900 to-blue-600 text-white p-12">

        <div>

            <div class="flex items-center gap-3 mb-16">

                <div class="bg-white/20 p-3 rounded-xl">
                    <i data-lucide="heart-pulse" class="w-8 h-8"></i>
                </div>

                <div>
                    <h1 class="font-bold text-xl">
                        Barangay Health
                    </h1>

                    <p class="text-blue-200 text-xs">
                        Management System
                    </p>
                </div>

            </div>

            <h2 class="text-4xl font-extrabold leading-tight mb-5">
                Welcome Back.
                <br>
                Your Community Needs You.
            </h2>

            <p class="text-blue-100 leading-relaxed">
                Access your BHW workspace to manage
                patients, appointments, health records,
                prenatal monitoring and immunization.
            </p>

        </div>

        <div class="border-t border-white/20 pt-6">
            <p class="text-xs text-blue-200">
                Barangay Health Worker Portal
            </p>
        </div>

    </div>

    <!-- LOGIN FORM -->

    <div class="p-7 sm:p-12 flex flex-col justify-center">

        <div class="md:hidden mb-10 flex items-center gap-3">

            <div class="bg-blue-600 text-white p-3 rounded-xl">
                <i data-lucide="heart-pulse"></i>
            </div>

            <div>
                <h1 class="font-bold text-slate-800">
                    Barangay Health
                </h1>

                <p class="text-xs text-slate-500">
                    Management System
                </p>
            </div>

        </div>

        <div class="mb-8">

            <span class="text-blue-600 text-xs font-bold tracking-widest uppercase">
                BHW Portal
            </span>

            <h2 class="text-3xl font-extrabold text-slate-800 mt-2">
                Sign In
            </h2>

            <p class="text-sm text-slate-500 mt-2">
                Enter your account credentials to continue.
            </p>

        </div>

        <!-- ERROR MESSAGE -->

        <?php if ($message !== ""): ?>

            <div
                class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-6 text-sm flex gap-3"
                role="alert"
            >

                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>

                <span>
                    <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
                </span>

            </div>

        <?php endif; ?>

        <form method="POST" action="" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["bhw_login_csrf"], ENT_QUOTES, "UTF-8") ?>"
            >

            <!-- EMAIL -->

            <div>

                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Email Address
                </label>

                <div class="relative">

                    <i
                        data-lucide="mail"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"
                    ></i>

                    <input
                        type="email"
                        name="email"
                        required
                        autocomplete="username"
                        maxlength="150"
                        value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                        placeholder="Enter your email"
                        class="input-field w-full pl-12 pr-4 py-3.5 border border-slate-200 rounded-xl text-sm"
                    >

                </div>

            </div>

            <!-- PASSWORD -->

            <div>

                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Password
                </label>

                <div class="relative">

                    <i
                        data-lucide="lock"
                        class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 w-5 h-5"
                    ></i>

                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        class="input-field w-full pl-12 pr-12 py-3.5 border border-slate-200 rounded-xl text-sm"
                    >

                    <button
                        type="button"
                        id="togglePassword"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400"
                        aria-label="Show password"
                    >
                        <i data-lucide="eye" class="w-5 h-5"></i>
                    </button>

                </div>

            </div>

            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition shadow-lg shadow-blue-600/20 flex justify-center items-center gap-2"
            >

                <i data-lucide="log-in" class="w-5 h-5"></i>

                Sign In to Dashboard

            </button>

        </form>

        <!-- FOOTER LINKS -->

        <div class="text-center mt-7">

            <p class="text-sm text-slate-500">

                Don't have an account?

                <a
                    href="create.php"
                    class="text-blue-600 font-bold hover:text-blue-800"
                >
                    Create Account
                </a>

            </p>

            <a
                href="../index.php"
                class="inline-flex items-center gap-2 text-xs text-slate-400 mt-6 hover:text-blue-600"
            >

                <i data-lucide="arrow-left" class="w-4 h-4"></i>

                Back to Main Website

            </a>

        </div>

    </div>

</div>

<script src="bhw.js"></script>

</body>
</html>
