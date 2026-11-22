
<?php
// =============================================
// BHW CREATE ACCOUNT
// PDO DATABASE CONNECTION
// Database: barangay_db
// =============================================

// SESSION SECURITY
$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

session_name("BARANGAY_BHW_REGISTER");

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

// SECURITY HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// ESCAPE OUTPUT
function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// =============================================
// DATABASE CONNECTION
// Main file: Barangay System/db.php
// =============================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection error. Please check the main db.php file.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// =============================================
// INITIAL VALUES
// =============================================

$message = "";
$messageType = "error";

$fullName = "";
$email = "";
$contact = "";

// =============================================
// PROCESS REGISTRATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullNameInput = $_POST["full_name"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $contactInput = $_POST["contact_number"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $confirmInput = $_POST["confirm_password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $fullName = is_string($fullNameInput) ? trim($fullNameInput) : "";
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : "";
    $contact = is_string($contactInput) ? trim($contactInput) : "";

    $password = is_string($passwordInput) ? $passwordInput : "";
    $confirmPassword = is_string($confirmInput) ? $confirmInput : "";

    // CSRF CHECK
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

        $message = "Please complete all required fields.";

    } elseif (strlen($fullName) > 150) {

        $message = "Full name must not exceed 150 characters.";

    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {

        $message = "Please enter a valid email address.";

    } elseif (
        strlen($contact) > 20 ||
        !preg_match('/^[0-9+\-\s()]+$/', $contact)
    ) {

        $message = "Please enter a valid contact number.";

    } elseif (strlen($password) < 8) {

        $message = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";

    } else {

        try {

            // CHECK DUPLICATE EMAIL
            $check = $pdo->prepare("
                SELECT id
                FROM bhw_accounts
                WHERE email = :email
                LIMIT 1
            ");

            $check->execute([
                ":email" => $email
            ]);

            if ($check->fetch()) {

                $message = "This email address is already registered.";

            } else {

                // HASH PASSWORD
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException("Password hashing failed.");
                }

                // INSERT NEW BHW ACCOUNT
                $insert = $pdo->prepare("
                    INSERT INTO bhw_accounts
                    (
                        full_name,
                        email,
                        password,
                        contact_number,
                        position,
                        status
                    )
                    VALUES
                    (
                        :full_name,
                        :email,
                        :password,
                        :contact_number,
                        :position,
                        :status
                    )
                ");

                $insert->execute([
                    ":full_name" => $fullName,
                    ":email" => $email,
                    ":password" => $hashedPassword,
                    ":contact_number" => $contact,
                    ":position" => "Barangay Health Worker",
                    ":status" => "Inactive"
                ]);

                $message = "Registration successful! Your account is waiting for administrator approval.";
                $messageType = "success";

                // CLEAR FORM
                $fullName = "";
                $email = "";
                $contact = "";

                // NEW CSRF TOKEN
                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {

            error_log("BHW Registration Error: " . $e->getMessage());

            if (
                $e->getCode() === "23000" ||
                (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1062)
            ) {
                $message = "This email address is already registered.";
            } else {
                $message = "Unable to process registration. Please check your database table and columns.";
            }

        } catch (RuntimeException $e) {

            error_log("BHW Registration Error: " . $e->getMessage());

            $message = "Unable to secure your account. Please try again.";
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

<style>
body {
    font-family: Arial, sans-serif;
}

.input-field {
    width: 100%;
    padding: 13px 15px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    outline: none;
    background: #fff;
    transition: border-color .2s, box-shadow .2s;
}

.input-field:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
}

label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: white;
    margin-bottom: 8px;
}
</style>

</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

<div class="w-full max-w-5xl bg-black rounded-2xl shadow-xl overflow-hidden grid md:grid-cols-2">

    <!-- LEFT PANEL -->

    <div class="hidden md:flex flex-col justify-center bg-blue-700 text-white p-10">

        <div class="mb-8">

            <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center mb-6 text-3xl font-bold">
                 + 
            </div>

            <h1 class="text-3xl font-bold mb-4">
                Barangay Health System
            </h1>

            <p class="text-blue-100 leading-relaxed">
                BHW Account Registration Portal.
                Register your account to access the
                Barangay Health Worker Management System.
            </p>

        </div>

        <div class="space-y-6 mt-6">

            <div class="flex items-center gap-4">
                <span class="text-2xl">👥</span>
                <span>Manage patient information</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-2xl">📅</span>
                <span>Manage health appointments</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-2xl">📋</span>
                <span>Maintain health records</span>
            </div>

            <div class="flex items-center gap-4">
                <span class="text-2xl">🏥</span>
                <span>Support barangay healthcare services</span>
            </div>

        </div>

        <p class="text-sm text-blue-200 mt-16">
            Barangay Health Worker Management System
        </p>

    </div>

    <!-- RIGHT PANEL -->

    <div class="p-6 sm:p-10">

        <div class="mb-7">

            <div class="md:hidden w-12 h-12 bg-blue-100 text-blue-700 rounded-xl flex items-center justify-center mb-4 text-2xl font-bold">
                +
            </div>

            <h2 class="text-3xl font-bold text-white">
                Create BHW Account
            </h2>

            <p class="text-white text-sm">
                Fill in your information to register.
            </p>

        </div>

        <!-- ALERT MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="mb-5 p-4 rounded-xl text-sm border
                <?= $messageType === "success"
                    ? "bg-green-50 text-green-700 border-green-200"
                    : "bg-red-50 text-red-700 border-red-200"; ?>">

                <?= e($message) ?>

            </div>

        <?php endif; ?>

        <!-- REGISTRATION FORM -->

        <form method="POST" action="" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["csrf_token"]) ?>"
            >

            <div>

                <label for="full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    class="input-field"
                    placeholder="Enter your full name"
                    value="<?= e($fullName) ?>"
                    maxlength="150"
                    autocomplete="name"
                    required
                >

            </div>

            <div>

                <label for="email">
                    Email Address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="input-field"
                    placeholder="Enter your email address"
                    value="<?= e($email) ?>"
                    maxlength="150"
                    autocomplete="email"
                    required
                >

            </div>

            <div>

                <label for="contact_number">
                    Contact Number
                </label>

                <input
                    type="tel"
                    id="contact_number"
                    name="contact_number"
                    class="input-field"
                    placeholder="Enter your contact number"
                    value="<?= e($contact) ?>"
                    maxlength="20"
                    autocomplete="tel"
                    required
                >

            </div>

            <div>

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    class="input-field"
                    placeholder="Create a password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <p class="text-xs text-gray-400 mt-2">
                    Password must contain at least 8 characters.
                </p>

            </div>

            <div>

                <label for="confirm_password">
                    Confirm Password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    class="input-field"
                    placeholder="Confirm your password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>

            <button
                type="submit"
                class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl transition duration-200"
            >
                Create Account
            </button>

        </form>

        <!-- LOGIN LINK -->

        <div class="text-center mt-6 text-sm text-white">

            Already have an account

            <a
                href="logIn.php"
                class="text-blue-700 font-semibold hover:underline"
            >
                Sign In
            </a>

        </div>

        <p class="text-center text-xs text-gray-400 mt-7">
            Your account requires administrator approval before login.
        </p>

    </div>

</div>

</body>
</html>
s