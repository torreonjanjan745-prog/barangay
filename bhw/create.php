
<?php
// =============================================
// BHW CREATE ACCOUNT - MYSQLI
// =============================================

// SESSION CONFIGURATION
session_name("BARANGAY_BHW_REGISTER");

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
require_once __DIR__ . "/database.php";

// VERIFY CONNECTION
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    error_log("BHW Registration: Database connection unavailable.");
    http_response_code(500);
    exit("Database connection error. Please check database.php.");
}

$conn->set_charset("utf8mb4");

// CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// INITIAL VALUES
$message = "";
$messageType = "error";

$fullName = "";
$email = "";
$contact = "";

// =============================================
// HANDLE REGISTRATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullNameInput = $_POST["full_name"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $contactInput = $_POST["contact_number"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $confirmInput = $_POST["confirm_password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $fullName = is_string($fullNameInput) ? trim($fullNameInput) : "";
    $email = is_string($emailInput) ? trim($emailInput) : "";
    $contact = is_string($contactInput) ? trim($contactInput) : "";

    $password = is_string($passwordInput) ? $passwordInput : "";
    $confirmPassword = is_string($confirmInput) ? $confirmInput : "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)
    ) {

        $message = "Invalid request. Please refresh the page.";

    } elseif (
        $fullName === "" ||
        $email === "" ||
        $contact === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $message = "Please complete all fields.";

    } elseif (strlen($fullName) > 150) {

        $message = "Full name must not exceed 150 characters.";

    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $message = "Please enter a valid email address.";

    } elseif (strlen($contact) > 20) {

        $message = "Contact number must not exceed 20 characters.";

    } elseif (!preg_match('/^[0-9+\-\s()]+$/', $contact)) {

        $message = "Please enter a valid contact number.";

    } elseif (strlen($password) < 8) {

        $message = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";

    } else {

        // CHECK EXISTING EMAIL
        $checkSql = "
            SELECT id
            FROM bhw_accounts
            WHERE email = ?
            LIMIT 1
        ";

        $check = mysqli_prepare($conn, $checkSql);

        if (!$check) {

            error_log("BHW Check Error: " . mysqli_error($conn));
            $message = "Unable to process registration. Please check the database.";

        } else {

            mysqli_stmt_bind_param($check, "s", $email);

            if (!mysqli_stmt_execute($check)) {

                error_log("BHW Check Error: " . mysqli_stmt_error($check));
                $message = "Unable to verify your account. Please try again.";
                mysqli_stmt_close($check);

            } else {

                mysqli_stmt_bind_result($check, $existingId);
                $emailExists = mysqli_stmt_fetch($check);

                mysqli_stmt_close($check);

                if ($emailExists) {

                    $message = "This email address is already registered.";

                } else {

                    // HASH PASSWORD
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    if ($hashedPassword === false) {

                        $message = "Unable to secure your password. Please try again.";

                    } else {

                        // INSERT ACCOUNT
                        $insertSql = "
                            INSERT INTO bhw_accounts
                            (
                                full_name,
                                email,
                                password,
                                contact_number,
                                position,
                                status
                            )
                            VALUES (?, ?, ?, ?, ?, ?)
                        ";

                        $insert = mysqli_prepare($conn, $insertSql);

                        if (!$insert) {

                            error_log("BHW Insert Error: " . mysqli_error($conn));
                            $message = "Unable to register your account. Please check the database structure.";

                        } else {

                            $position = "Barangay Health Worker";
                            $status = "Inactive";

                            mysqli_stmt_bind_param(
                                $insert,
                                "ssssss",
                                $fullName,
                                $email,
                                $hashedPassword,
                                $contact,
                                $position,
                                $status
                            );

                            if (mysqli_stmt_execute($insert)) {

                                $message = "Registration successful! Your account is waiting for administrator approval.";
                                $messageType = "success";

                                $fullName = "";
                                $email = "";
                                $contact = "";

                                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

                            } else {

                                $errorCode = mysqli_stmt_errno($insert);

                                error_log("BHW Insert Error: " . mysqli_stmt_error($insert));

                                if ($errorCode === 1062) {
                                    $message = "This email address is already registered.";
                                } else {
                                    $message = "Unable to register your account. Please try again.";
                                }
                            }

                            mysqli_stmt_close($insert);
                        }
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Registration | Barangay Health System</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .input-field {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            outline: none;
            transition: .2s;
        }

        .input-field:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper input {
            padding-right: 45px;
        }

        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
        }
    </style>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-5xl bg-white rounded-2xl shadow-xl overflow-hidden grid md:grid-cols-2">

        <!-- LEFT SIDE -->
        <div class="hidden md:flex flex-col justify-center bg-blue-700 text-white p-10">

            <div class="mb-8">
                <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6">
                    <i data-lucide="heart-pulse" class="w-9 h-9"></i>
                </div>

                <h1 class="text-3xl font-bold mb-4">
                    Barangay Health System
                </h1>

                <p class="text-blue-100 leading-relaxed">
                    BHW Account Registration Portal. Create your account
                    to access the Barangay Health Worker system.
                </p>
            </div>

            <div class="space-y-5 mt-6">

                <div class="flex items-center gap-3">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span>Manage patient information</span>
                </div>

                <div class="flex items-center gap-3">
                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                    <span>Manage health appointments</span>
                </div>

                <div class="flex items-center gap-3">
                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    <span>Access health records</span>
                </div>

            </div>

            <p class="text-sm text-blue-200 mt-16">
                Barangay Health Worker Management System
            </p>

        </div>

        <!-- RIGHT SIDE: REGISTRATION FORM -->
        <div class="p-6 sm:p-10">

            <div class="mb-7">

                <div class="md:hidden w-12 h-12 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center mb-4">
                    <i data-lucide="heart-pulse" class="w-7 h-7"></i>
                </div>

                <h2 class="text-2xl font-bold text-gray-900">
                    Create BHW Account
                </h2>

                <p class="text-gray-500 mt-2 text-sm">
                    Fill in your information to register.
                </p>

            </div>

            <!-- MESSAGE -->
            <?php if ($message !== ""): ?>

                <div class="mb-5 p-4 rounded-xl text-sm
                    <?php echo $messageType === "success"
                        ? "bg-green-50 text-green-700 border border-green-200"
                        : "bg-red-50 text-red-700 border border-red-200"; ?>">

                    <div class="flex items-start gap-2">
                        <?php if ($messageType === "success"): ?>
                            <i data-lucide="check-circle" class="w-5 h-5 shrink-0"></i>
                        <?php else: ?>
                            <i data-lucide="alert-circle" class="w-5 h-5 shrink-0"></i>
                        <?php endif; ?>

                        <span>
                            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
                        </span>
                    </div>

                </div>

            <?php endif; ?>

            <!-- REGISTRATION FORM -->
            <form method="POST" action="" class="space-y-5">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>"
                >

                <!-- FULL NAME -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name
                    </label>

                    <div class="relative">
                        <i data-lucide="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>

                        <input
                            type="text"
                            name="full_name"
                            class="input-field pl-11"
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="150"
                            autocomplete="name"
                            required
                        >
                    </div>
                </div>

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
                            class="input-field pl-11"
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="150"
                            autocomplete="email"
                            required
                        >
                    </div>
                </div>

                <!-- CONTACT NUMBER -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Contact Number
                    </label>

                    <div class="relative">
                        <i data-lucide="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"></i>

                        <input
                            type="tel"
                            name="contact_number"
                            class="input-field pl-11"
                            placeholder="Enter your contact number"
                            value="<?php echo htmlspecialchars($contact, ENT_QUOTES, "UTF-8"); ?>"
                            maxlength="20"
                            autocomplete="tel"
                            required
                        >
                    </div>
                </div>

                <!-- PASSWORD -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Password
                    </label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="input-field"
                            placeholder="Create a password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            onclick="togglePassword('password', 'eyePassword')"
                            aria-label="Show or hide password"
                        >
                            <i data-lucide="eye" id="eyePassword" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <p class="text-xs text-gray-400 mt-1">
                        Password must contain at least 8 characters.
                    </p>
                </div>

                <!-- CONFIRM PASSWORD -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Confirm Password
                    </label>

                    <div class="password-wrapper">
                        <input
                            type="password"
                            name="confirm_password"
                            id="confirm_password"
                            class="input-field"
                            placeholder="Confirm your password"
                            minlength="8"
                            autocomplete="new-password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            onclick="togglePassword('confirm_password', 'eyeConfirm')"
                            aria-label="Show or hide confirm password"
                        >
                            <i data-lucide="eye" id="eyeConfirm" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <!-- SUBMIT -->
                <button
                    type="submit"
                    class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl transition duration-200 flex items-center justify-center gap-2"
                >
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                    Create Account
                </button>

            </form>

            <!-- LOGIN LINK -->
            <div class="text-center mt-6 text-sm text-gray-500">
                Already have an account?

                <a href="logIn.php" class="text-blue-700 font-semibold hover:underline">
                    Sign In
                </a>
            </div>

            <p class="text-center text-xs text-gray-400 mt-7">
                Your account requires administrator approval before login.
            </p>

        </div>

    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (!input || !icon) return;

            if (input.type === "password") {
                input.type = "text";
                icon.setAttribute("data-lucide", "eye-off");
            } else {
                input.type = "password";
                icon.setAttribute("data-lucide", "eye");
            }

            if (window.lucide) {
                lucide.createIcons();
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            if (window.lucide) {
                lucide.createIcons();
            }
        });
    </script>

</body>
</html>
