
<?php
// =============================================
// ADMINISTRATOR LOGIN SYSTEM - MYSQLI
// =============================================

// SESSION CONFIGURATION
session_name("BARANGAY_ADMIN_SESSION");

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

mysqli_report(MYSQLI_REPORT_OFF);

// DATABASE CONNECTION
require_once __DIR__ . "/../db.php";

// VERIFY DATABASE CONNECTION
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    error_log("Admin Login: Database connection unavailable.");
    http_response_code(500);
    exit("Database connection error. Please check db.php.");
}

$conn->set_charset("utf8mb4");

// CHECK EXISTING ADMIN SESSION
if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

// INITIAL VALUES
$message = "";
$email = "";

// CSRF TOKEN
if (empty($_SESSION["admin_login_csrf"])) {
    $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));
}

// =============================================
// HANDLE ADMIN LOGIN
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $emailInput = $_POST["email"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $email = is_string($emailInput) ? trim($emailInput) : "";
    $password = is_string($passwordInput) ? $passwordInput : "";

    // CSRF VALIDATION
    if (
        !is_string($token) ||
        !hash_equals($_SESSION["admin_login_csrf"], $token)
    ) {

        $message = "Invalid request. Please refresh the page.";

    } elseif (
        $email === "" ||
        $password === "" ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $message = "Please enter a valid email and password.";

    } else {

        // FIND ADMIN ACCOUNT
        $sql = "
            SELECT
                id,
                full_name,
                email,
                password,
                status
            FROM admin_account
            WHERE email = ?
            LIMIT 1
        ";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            error_log("Admin Login Prepare Error: " . mysqli_error($conn));

            $message = "Unable to process login. Please check the database.";

        } else {

            mysqli_stmt_bind_param($stmt, "s", $email);

            if (!mysqli_stmt_execute($stmt)) {

                error_log("Admin Login Execute Error: " . mysqli_stmt_error($stmt));

                $message = "Unable to process login. Please try again.";

            } else {

                mysqli_stmt_bind_result(
                    $stmt,
                    $adminId,
                    $adminName,
                    $adminEmail,
                    $hashedPassword,
                    $adminStatus
                );

                if (mysqli_stmt_fetch($stmt)) {

                    // VERIFY PASSWORD AND ACCOUNT STATUS
                    if (
                        password_verify($password, $hashedPassword) &&
                        strcasecmp(trim((string)$adminStatus), "Active") === 0
                    ) {

                        // REGENERATE SESSION ID
                        session_regenerate_id(true);

                        // ADMIN SESSION
                        $_SESSION["admin_id"] = $adminId;
                        $_SESSION["admin_name"] = $adminName;
                        $_SESSION["admin_email"] = $adminEmail;
                        $_SESSION["admin_role"] = "Administrator";

                        // REGENERATE CSRF TOKEN
                        $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));

                        mysqli_stmt_close($stmt);

                        // REDIRECT TO ADMIN DASHBOARD
                        header("Location: dashboard.php");
                        exit;

                    } else {

                        $message = "Invalid email or password, or your account is inactive.";

                    }

                } else {

                    $message = "Invalid email or password.";

                }
            }

            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Login | Barangay Health System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-7 sm:p-9">

        <!-- HEADER -->
        <div class="text-center mb-8">

            <div class="w-16 h-16 bg-blue-100 text-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-5">
                <i data-lucide="shield-check" class="w-9 h-9"></i>
            </div>

            <h1 class="text-2xl font-bold text-gray-900">
                Administrator Login
            </h1>

            <p class="text-gray-500 text-sm mt-2">
                Sign in to access the administrator portal.
            </p>

        </div>

        <!-- ERROR MESSAGE -->
        <?php if ($message !== ""): ?>

            <div class="mb-5 p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-sm flex items-start gap-2">

                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>

                <span>
                    <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
                </span>

            </div>

        <?php endif; ?>

        <!-- LOGIN FORM -->
        <form method="POST" action="" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["admin_login_csrf"], ENT_QUOTES, "UTF-8"); ?>"
            >

            <!-- EMAIL -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Email Address
                </label>

                <div class="relative">

                    <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>

                    <input
                        type="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                        placeholder="Enter your email"
                        maxlength="150"
                        autocomplete="username"
                        class="w-full border border-gray-300 rounded-xl pl-11 pr-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >

                </div>

            </div>

            <!-- PASSWORD -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Password
                </label>

                <div class="relative">

                    <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>

                    <input
                        type="password"
                        name="password"
                        id="adminPassword"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        class="w-full border border-gray-300 rounded-xl pl-11 pr-12 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                        required
                    >

                    <button
                        type="button"
                        onclick="toggleAdminPassword()"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500"
                        aria-label="Show or hide password"
                    >
                        <i data-lucide="eye" id="adminEye" class="w-5 h-5"></i>
                    </button>

                </div>

            </div>

            <!-- LOGIN BUTTON -->
            <button
                type="submit"
                class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2"
            >

                <i data-lucide="log-in" class="w-5 h-5"></i>

                Sign In

            </button>

        </form>

        <!-- FOOTER -->
        <div class="text-center mt-7 text-sm text-gray-500">
            Administrator Portal
        </div>

    </div>

    <!-- JAVASCRIPT -->
    <script>

        function toggleAdminPassword() {

            const input = document.getElementById("adminPassword");
            const icon = document.getElementById("adminEye");

            if (!input || !icon) return;

            if (input.type === "password") {

                input.type = "text";
                icon.setAttribute("data-lucide", "eye-off");

            } else {

                input.type = "password";
                icon.setAttribute("data-lucide", "eye");

            }

            lucide.createIcons();
        }

        document.addEventListener("DOMContentLoaded", function () {

            if (window.lucide) {
                lucide.createIcons();
            }

        });

    </script>

</body>
</html>
