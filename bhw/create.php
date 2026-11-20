
<?php
// =============================================
// BHW CREATE ACCOUNT
// =============================================

session_name("BARANGAY_BHW_REGISTER");
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
session_start();

require_once __DIR__ . "/database.php";

// CSRF TOKEN
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "error";

$fullName = "";
$email = "";
$contact = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact = trim($_POST["contact_number"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    if (!is_string($token) ||
        !hash_equals($_SESSION["csrf_token"], $token)) {

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

    } elseif (strlen($password) < 8) {
        $message = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";

    } else {

        try {
            $check = $pdo->prepare(
                "SELECT id FROM bhw_account WHERE email = :email LIMIT 1"
            );

            $check->execute([":email" => $email]);

            if ($check->fetch()) {
                $message = "This email address is already registered.";

            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $insert = $pdo->prepare("
                    INSERT INTO bhw_account
                    (
                        full_name,
                        email,
                        password,
                        contact_number,
                        status
                    )
                    VALUES
                    (
                        :full_name,
                        :email,
                        :password,
                        :contact_number,
                        'Inactive'
                    )
                ");

                $insert->execute([
                    ":full_name" => $fullName,
                    ":email" => $email,
                    ":password" => $hashedPassword,
                    ":contact_number" => $contact
                ]);

                $message = "Registration successful! Your account is waiting for administrator approval.";
                $messageType = "success";

                $fullName = "";
                $email = "";
                $contact = "";

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
            }

        } catch (PDOException $e) {
            error_log("BHW Registration Error: " . $e->getMessage());

            if ($e->getCode() === "23000") {
                $message = "This email address is already registered.";
            } else {
                $message = "Unable to register your account. Please try again.";
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

    <title>BHW Create Account</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

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

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

<div class="w-full max-w-5xl bg-white rounded-3xl shadow-xl overflow-hidden grid md:grid-cols-2">

    <!-- LEFT PANEL -->
    <div class="hidden md:flex flex-col justify-between bg-gradient-to-br from-blue-900 to-blue-600 text-white p-12">

        <div>
            <div class="flex items-center gap-3 mb-16">
                <div class="bg-white/20 p-3 rounded-xl">
                    <i data-lucide="heart-pulse" class="w-8 h-8"></i>
                </div>

                <div>
                    <h1 class="font-bold text-xl">Barangay Health</h1>
                    <p class="text-blue-200 text-xs">Management System</p>
                </div>
            </div>

            <h2 class="text-4xl font-extrabold leading-tight mb-5">
                Join Our Healthcare Team.
            </h2>

            <p class="text-blue-100 leading-relaxed">
                Create your Barangay Health Worker account
                and help provide better health services
                to your community.
            </p>
        </div>

        <p class="text-xs text-blue-200 border-t border-white/20 pt-6">
            BHW Registration Portal
        </p>
    </div>

    <!-- FORM -->
    <div class="p-7 sm:p-12">

        <div class="md:hidden flex items-center gap-3 mb-8">
            <div class="bg-blue-600 text-white p-3 rounded-xl">
                <i data-lucide="heart-pulse"></i>
            </div>
            <div>
                <h1 class="font-bold text-slate-800">Barangay Health</h1>
                <p class="text-xs text-slate-500">Management System</p>
            </div>
        </div>

        <div class="mb-8">
            <span class="text-blue-600 text-xs font-bold uppercase tracking-widest">
                BHW Registration
            </span>

            <h2 class="text-3xl font-extrabold text-slate-800 mt-2">
                Create Account
            </h2>

            <p class="text-sm text-slate-500 mt-2">
                Enter your information to register.
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="mb-6 p-4 rounded-xl border text-sm
                <?= $messageType === "success"
                    ? "bg-green-50 border-green-200 text-green-700"
                    : "bg-red-50 border-red-200 text-red-700" ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create.php" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>"
            >

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    maxlength="150"
                    required
                    autocomplete="name"
                    value="<?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>"
                    placeholder="Enter your full name"
                    class="input-field w-full px-4 py-3.5 border border-slate-200 rounded-xl text-sm"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    required
                    autocomplete="email"
                    value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                    placeholder="example@email.com"
                    class="input-field w-full px-4 py-3.5 border border-slate-200 rounded-xl text-sm"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Contact Number
                </label>

                <input
                    type="tel"
                    name="contact_number"
                    maxlength="20"
                    required
                    autocomplete="tel"
                    value="<?= htmlspecialchars($contact, ENT_QUOTES, "UTF-8") ?>"
                    placeholder="09XXXXXXXXX"
                    class="input-field w-full px-4 py-3.5 border border-slate-200 rounded-xl text-sm"
                >
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Password
                </label>

                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        minlength="8"
                        required
                        autocomplete="new-password"
                        placeholder="At least 8 characters"
                        class="input-field w-full px-4 py-3.5 pr-12 border border-slate-200 rounded-xl text-sm"
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

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">
                    Confirm Password
                </label>

                <input
                    type="password"
                    name="confirm_password"
                    id="confirmPassword"
                    minlength="8"
                    required
                    autocomplete="new-password"
                    placeholder="Re-enter your password"
                    class="input-field w-full px-4 py-3.5 border border-slate-200 rounded-xl text-sm"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl transition shadow-lg shadow-blue-600/20"
            >
                Create BHW Account
            </button>

        </form>

        <div class="text-center mt-7">
            <p class="text-sm text-slate-500">
                Already have an account?
                <a href="login.php" class="text-blue-600 font-bold hover:text-blue-800">
                    Sign In
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


    

</body>
 <script src="bhw.js"></script>
</html>
