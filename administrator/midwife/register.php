
<?php
// ======================================================
// BARANGAY GUINDACPAN
// MIDWIFE REGISTRATION SYSTEM
//
// Database: barangay_health_db
// Table: midwife_accounts
// Connection: ../db.php
// PDO Variable: $pdo
// ======================================================

// 1. SESSION SECURITY
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// 2. DATABASE CONNECTION
require_once __DIR__ . '/../db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection error. Please check the root db.php file.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error = '';
$success = '';

// 3. CSRF TOKEN
if (empty($_SESSION['midwife_register_token'])) {
    $_SESSION['midwife_register_token'] = bin2hex(random_bytes(32));
}

// 4. REGISTRATION PROCESS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['midwife_register_token'], $token)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";

    } else {

        $full_name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $contact_number = trim($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // VALIDATION
        if (
            $full_name === '' ||
            $email === '' ||
            $contact_number === '' ||
            $password === '' ||
            $confirm_password === ''
        ) {
            $error = "Please complete all required fields.";

        } elseif (mb_strlen($full_name) < 3 || mb_strlen($full_name) > 150) {
            $error = "Full name must be between 3 and 150 characters.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";

        } elseif (strlen($email) > 150) {
            $error = "Email address is too long.";

        } elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $contact_number)) {
            $error = "Please enter a valid contact number.";

        } elseif (strlen($password) < 8) {
            $error = "Password must contain at least 8 characters.";

        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match.";

        } else {

            try {

                // CHECK DUPLICATE EMAIL
                $check = $pdo->prepare("
                    SELECT id
                    FROM midwife_accounts
                    WHERE email = :email
                    LIMIT 1
                ");

                $check->execute([
                    ':email' => $email
                ]);

                if ($check->fetch(PDO::FETCH_ASSOC)) {

                    $error = "This email address is already registered.";

                } else {

                    // HASH PASSWORD
                    $hashed_password = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // INSERT MIDWIFE ACCOUNT
                    $sql = "
                        INSERT INTO midwife_accounts (
                            full_name,
                            email,
                            password,
                            contact_number,
                            position,
                            status,
                            created_at,
                            updated_at
                        ) VALUES (
                            :full_name,
                            :email,
                            :password,
                            :contact_number,
                            :position,
                            :status,
                            NOW(),
                            NOW()
                        )
                    ";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':password' => $hashed_password,
                        ':contact_number' => $contact_number,
                        ':position' => 'Midwife',
                        ':status' => 'Pending'
                    ]);

                    $success = "Registration successful! Your account is now waiting for administrator approval.";

                    // CLEAR FORM TOKEN
                    $_SESSION['midwife_register_token'] = bin2hex(random_bytes(32));
                }

            } catch (PDOException $e) {

                error_log("Midwife Registration Error: " . $e->getMessage());

                if ($e->getCode() === '23000') {
                    $error = "This email address is already registered.";
                } else {
                    $error = "Unable to process registration. Please check your database table and columns.";
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

    <title>Midwife Registration | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f1f5f2;
        }

        .main-card {
            box-shadow: 0 20px 60px rgba(0,0,0,.08);
        }

        .input-field {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #dce5de;
            border-radius: 10px;
            outline: none;
            background: #fff;
            transition: .2s;
            font-size: 14px;
        }

        .input-field:focus {
            border-color: #14532d;
            box-shadow: 0 0 0 3px rgba(20,83,45,.09);
        }

        .register-btn {
            background: #14532d;
            transition: .2s;
        }

        .register-btn:hover {
            background: #0b3d2e;
            transform: translateY(-1px);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center px-4 py-10">

<div class="w-full max-w-xl">

    <!-- BRANDING -->
    <div class="text-center mb-7">

        <img
            src="../asset/logo.jpg"
            alt="Barangay Guindacpan Logo"
            class="w-20 h-20 object-contain mx-auto mb-3"
        >

        <h1 class="text-xl font-extrabold text-green-900">
            BARANGAY GUINDACPAN
        </h1>

        <p class="text-sm text-gray-500 mt-1">
            Talibon, Bohol • Philippines
        </p>

        <p class="text-xs font-semibold tracking-widest text-green-700 mt-3 uppercase">
            Midwife Management System
        </p>

    </div>

    <!-- REGISTRATION CARD -->
    <div class="main-card bg-white rounded-2xl p-7 sm:p-9">

        <div class="mb-7">

            <h2 class="text-2xl font-extrabold text-gray-800">
                Create Midwife Account
            </h2>

            <p class="text-sm text-gray-500 mt-2">
                Register your account to access the Midwife Management System.
            </p>

        </div>

        <!-- ERROR MESSAGE -->
        <?php if ($error): ?>

            <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <!-- SUCCESS MESSAGE -->
        <?php if ($success): ?>

            <div class="mb-5 p-4 rounded-xl bg-green-50 border border-green-200">

                <p class="text-green-800 text-sm font-semibold">
                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                </p>

                <p class="text-green-700 text-xs mt-2">
                    Please wait for the administrator to activate your account.
                </p>

                <a href="login.php"
                   class="inline-block mt-3 text-sm font-bold text-green-900 underline">
                    Go to Midwife Login
                </a>

            </div>

        <?php endif; ?>

        <!-- REGISTRATION FORM -->
        <?php if (!$success): ?>

        <form method="POST" action="" class="space-y-5">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION['midwife_register_token'], ENT_QUOTES, 'UTF-8') ?>"
            >

            <!-- FULL NAME -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Full Name
                </label>

                <input
                    type="text"
                    name="full_name"
                    class="input-field"
                    placeholder="Enter your complete name"
                    maxlength="150"
                    value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="name"
                    required
                >

            </div>

            <!-- EMAIL -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Email Address
                </label>

                <input
                    type="email"
                    name="email"
                    class="input-field"
                    placeholder="example@email.com"
                    maxlength="150"
                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="email"
                    required
                >

            </div>

            <!-- CONTACT NUMBER -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Contact Number
                </label>

                <input
                    type="tel"
                    name="contact_number"
                    class="input-field"
                    placeholder="09XXXXXXXXX"
                    maxlength="20"
                    value="<?= htmlspecialchars($_POST['contact_number'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="tel"
                    required
                >

            </div>

            <!-- POSITION -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Position
                </label>

                <input
                    type="text"
                    class="input-field bg-gray-50 text-gray-500"
                    value="Midwife"
                    readonly
                >

            </div>

            <!-- PASSWORD -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Password
                </label>

                <div class="relative">

                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="input-field pr-20"
                        placeholder="Create a password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        onclick="togglePassword('password', this)"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-green-800"
                    >
                        Show
                    </button>

                </div>

                <p class="text-xs text-gray-400 mt-2">
                    Minimum of 8 characters.
                </p>

            </div>

            <!-- CONFIRM PASSWORD -->
            <div>

                <label class="block text-sm font-semibold text-gray-700 mb-2">
                    Confirm Password
                </label>

                <div class="relative">

                    <input
                        type="password"
                        name="confirm_password"
                        id="confirm_password"
                        class="input-field pr-20"
                        placeholder="Confirm your password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <button
                        type="button"
                        onclick="togglePassword('confirm_password', this)"
                        class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-green-800"
                    >
                        Show
                    </button>

                </div>

            </div>

            <!-- ACCOUNT STATUS NOTICE -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">

                <div class="flex gap-3">

                    <span class="text-xl">ℹ️</span>

                    <div>

                        <p class="text-sm font-bold text-amber-900">
                            Administrator Approval Required
                        </p>

                        <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                            Your account will remain Pending after registration.
                            You can log in once the administrator approves and
                            activates your account.
                        </p>

                    </div>

                </div>

            </div>

            <!-- SUBMIT -->
            <button
                type="submit"
                class="register-btn w-full py-3.5 rounded-xl text-white font-bold text-sm shadow-lg"
            >
                Create Midwife Account
            </button>

        </form>

        <?php endif; ?>

        <!-- LOGIN LINK -->
        <div class="text-center mt-7 pt-6 border-t border-gray-100">

            <p class="text-sm text-gray-500">
                Already have an account?

                <a href="login.php"
                   class="font-bold text-green-800 hover:underline">
                    Sign In
                </a>
            </p>

        </div>

    </div>

    <!-- FOOTER -->
    <p class="text-center text-xs text-gray-400 mt-6">
        © <?= date('Y') ?> Barangay Guindacpan. All Rights Reserved.
    </p>

</div>

<script>
function togglePassword(id, button) {

    const field = document.getElementById(id);

    if (field.type === 'password') {
        field.type = 'text';
        button.textContent = 'Hide';
    } else {
        field.type = 'password';
        button.textContent = 'Show';
    }
}
</script>

</body>
</html>
