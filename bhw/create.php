
<?php
// ==================================================
// BARANGAY GUINDACPAN
// BHW ACCOUNT REGISTRATION
// SECURE PDO VERSION
//
// Table: bhw_accounts
// ==================================================

// SESSION SECURITY
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DATABASE CONNECTION
// Palitan ang path kung iba ang location ng db.php.
require_once __DIR__ . "/../db.php";

// VERIFY PDO CONNECTION
if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Database connection failed. Please check db.php.");
}

// PDO SETTINGS
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

// INITIAL VALUES
$message = "";
$messageType = "";
$fullName = "";
$email = "";
$contactNumber = "";

// CSRF TOKEN
if (empty($_SESSION['bhw_register_token'])) {
    $_SESSION['bhw_register_token'] = bin2hex(random_bytes(32));
}

// PROCESS REGISTRATION
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        // VERIFY CSRF TOKEN
        $submittedToken = $_POST['csrf_token'] ?? '';

        if (
            empty($submittedToken) ||
            !hash_equals($_SESSION['bhw_register_token'], $submittedToken)
        ) {
            throw new RuntimeException("Invalid form request. Please refresh the page.");
        }

        // GET FORM DATA
        $fullName = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $contactNumber = trim($_POST['contact_number'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // VALIDATION
        if (
            $fullName === '' ||
            $email === '' ||
            $password === '' ||
            $confirmPassword === ''
        ) {
            throw new RuntimeException("Please complete all required fields.");
        }

        if (mb_strlen($fullName) > 150) {
            throw new RuntimeException("Full name must not exceed 150 characters.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Please enter a valid email address.");
        }

        if (strlen($email) > 150) {
            throw new RuntimeException("Email must not exceed 150 characters.");
        }

        if (strlen($contactNumber) > 20) {
            throw new RuntimeException("Contact number must not exceed 20 characters.");
        }

        if (strlen($password) < 8) {
            throw new RuntimeException("Password must contain at least 8 characters.");
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException("Passwords do not match.");
        }

        // CHECK DUPLICATE EMAIL
        $check = $pdo->prepare("
            SELECT id
            FROM bhw_accounts
            WHERE email = :email
            LIMIT 1
        ");

        $check->execute([
            ':email' => $email
        ]);

        if ($check->fetch(PDO::FETCH_ASSOC)) {
            throw new RuntimeException("This email address is already registered.");
        }

        // HASH PASSWORD
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {
            throw new RuntimeException("Unable to secure your password.");
        }

        // INSERT BHW ACCOUNT
        $sql = "
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
        ";

        $insert = $pdo->prepare($sql);

        // EXECUTE ONLY ONCE
        $insert->execute([
            ':full_name'      => $fullName,
            ':email'          => $email,
            ':password'       => $hashedPassword,
            ':contact_number' => $contactNumber !== '' ? $contactNumber : null,
            ':position'       => 'Barangay Health Worker',
            ':status'         => 'Inactive'
        ]);

        // SUCCESS
        $_SESSION['bhw_register_token'] = bin2hex(random_bytes(32));

        $message = "Registration successful! Your account is waiting for administrator approval.";
        $messageType = "success";

        // CLEAR FORM VALUES
        $fullName = "";
        $email = "";
        $contactNumber = "";

    } catch (RuntimeException $e) {

        $message = $e->getMessage();
        $messageType = "error";

    } catch (PDOException $e) {

        // LOG DATABASE ERROR
        error_log("BHW REGISTRATION ERROR: " . $e->getMessage());

        // HANDLE DUPLICATE EMAIL
        if (
            $e->getCode() === "23000" ||
            (
                isset($e->errorInfo[1]) &&
                (int)$e->errorInfo[1] === 1062
            )
        ) {
            $message = "This email address is already registered.";
        } else {
            $message = "Unable to process registration. Please check the database connection and table structure.";
        }

        $messageType = "error";

    } catch (Throwable $e) {

        error_log("BHW REGISTRATION SYSTEM ERROR: " . $e->getMessage());

        $message = "An unexpected error occurred. Please try again.";
        $messageType = "error";
    }
}

// ESCAPE OUTPUT
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>BHW Registration | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #080d18;
        }

        .input-field {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #f9fafb;
            color: #111827;
            outline: none;
            transition: .2s ease;
        }

        .input-field:focus {
            background: #fff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .register-btn {
            transition: .2s ease;
        }

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, .25);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center p-4 md:p-8">

    <main class="w-full max-w-6xl bg-white rounded-3xl overflow-hidden shadow-2xl grid md:grid-cols-2">

        <!-- LEFT PANEL -->
        <section class="relative bg-gradient-to-br from-blue-950 via-blue-900 to-slate-950 text-white p-8 md:p-12 flex flex-col justify-between min-h-[300px] md:min-h-[700px]">

            <div>
                <div class="flex items-center gap-3 mb-14">

                    <div class="w-12 h-12 rounded-xl bg-white/10 border border-white/20 flex items-center justify-center">
                        <svg class="w-7 h-7 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>

                    <div>
                        <h1 class="font-bold text-lg">Barangay Guindacpan</h1>
                        <p class="text-blue-200 text-xs">Health Services System</p>
                    </div>

                </div>

                <div class="max-w-md">

                    <span class="inline-flex items-center gap-2 bg-blue-400/10 border border-blue-300/20 text-blue-200 px-4 py-2 rounded-full text-xs font-semibold mb-6">
                        <span class="w-2 h-2 rounded-full bg-green-400"></span>
                        BHW ACCOUNT REGISTRATION
                    </span>

                    <h2 class="text-3xl md:text-4xl font-extrabold leading-tight mb-5">
                        Join Our Barangay Health Team.
                    </h2>

                    <p class="text-blue-100/80 leading-7 text-sm md:text-base">
                        Create your Barangay Health Worker account to access health services, manage appointments, and support the community.
                    </p>

                </div>
            </div>

            <div class="mt-12 border-t border-white/10 pt-6">

                <div class="flex items-center gap-3 mb-4">
                    <div class="w-9 h-9 rounded-lg bg-white/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 11c0-1.1.9-2 2-2s2 .9 2 2v2h-4v-2zm-2 2h8v6h-8zM5 5h14v14H5z"/>
                        </svg>
                    </div>

                    <div>
                        <p class="text-sm font-semibold">Secure Registration</p>
                        <p class="text-xs text-blue-200">Your account information is protected.</p>
                    </div>
                </div>

                <p class="text-xs text-blue-200/60">
                    © <?php echo date('Y'); ?> Barangay Guindacpan. All rights reserved.
                </p>

            </div>

        </section>

        <!-- RIGHT PANEL -->
        <section class="p-7 sm:p-10 md:p-12 flex flex-col justify-center">

            <div class="max-w-md w-full mx-auto">

                <div class="mb-8">

                    <p class="text-blue-600 font-bold text-xs uppercase tracking-widest mb-3">
                        Create Your Account
                    </p>

                    <h2 class="text-3xl font-extrabold text-gray-900 mb-2">
                        BHW Registration
                    </h2>

                    <p class="text-gray-500 text-sm">
                        Enter your information to register as a Barangay Health Worker.
                    </p>

                </div>

                <!-- MESSAGE -->
                <?php if ($message !== ""): ?>

                    <div class="mb-6 p-4 rounded-xl border
                        <?php echo $messageType === 'success'
                            ? 'bg-green-50 border-green-200 text-green-700'
                            : 'bg-red-50 border-red-200 text-red-700'; ?>">

                        <div class="flex items-start gap-3">

                            <span class="font-bold">
                                <?php echo $messageType === 'success' ? '✓' : '!'; ?>
                            </span>

                            <p class="text-sm leading-6">
                                <?php echo e($message); ?>
                            </p>

                        </div>

                    </div>

                <?php endif; ?>

                <!-- REGISTRATION FORM -->
                <form method="POST" action="" autocomplete="on" class="space-y-5">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo e($_SESSION['bhw_register_token']); ?>"
                    >

                    <!-- FULL NAME -->
                    <div>

                        <label for="full_name" class="block text-sm font-semibold text-gray-700 mb-2">
                            Full Name <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="text"
                            id="full_name"
                            name="full_name"
                            class="input-field"
                            placeholder="Enter your complete name"
                            maxlength="150"
                            value="<?php echo e($fullName); ?>"
                            autocomplete="name"
                            required
                        >

                    </div>

                    <!-- EMAIL -->
                    <div>

                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                            Email Address <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="input-field"
                            placeholder="example@email.com"
                            maxlength="150"
                            value="<?php echo e($email); ?>"
                            autocomplete="email"
                            required
                        >

                    </div>

                    <!-- CONTACT NUMBER -->
                    <div>

                        <label for="contact_number" class="block text-sm font-semibold text-gray-700 mb-2">
                            Contact Number
                        </label>

                        <input
                            type="tel"
                            id="contact_number"
                            name="contact_number"
                            class="input-field"
                            placeholder="09XXXXXXXXX"
                            maxlength="20"
                            value="<?php echo e($contactNumber); ?>"
                            autocomplete="tel"
                        >

                    </div>

                    <!-- PASSWORD -->
                    <div>

                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Password <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">

                            <input
                                type="password"
                                id="password"
                                name="password"
                                class="input-field pr-16"
                                placeholder="Create a password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                onclick="togglePassword('password', this)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-blue-600"
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

                        <label for="confirm_password" class="block text-sm font-semibold text-gray-700 mb-2">
                            Confirm Password <span class="text-red-500">*</span>
                        </label>

                        <div class="relative">

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                class="input-field pr-16"
                                placeholder="Confirm your password"
                                minlength="8"
                                autocomplete="new-password"
                                required
                            >

                            <button
                                type="button"
                                onclick="togglePassword('confirm_password', this)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-blue-600"
                            >
                                Show
                            </button>

                        </div>

                    </div>

                    <!-- SUBMIT -->
                    <button
                        type="submit"
                        class="register-btn w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-600/20"
                    >
                        Create BHW Account
                    </button>

                </form>

                <!-- LOGIN LINK -->
                <div class="mt-7 text-center">

                    <p class="text-sm text-gray-500">
                        Already have an account?

                        <a href="login.php" class="text-blue-600 font-bold hover:text-blue-800">
                            Sign In
                        </a>
                    </p>

                </div>

                <!-- APPROVAL NOTICE -->
                <div class="mt-7 p-4 bg-blue-50 border border-blue-100 rounded-xl">

                    <p class="text-xs text-blue-800 leading-6">
                        <strong>Important:</strong> Newly registered accounts are set to Inactive and require administrator approval before login access is granted.
                    </p>

                </div>

            </div>

        </section>

    </main>

    <script>
        function togglePassword(id, button) {

            const field = document.getElementById(id);

            if (field.type === "password") {
                field.type = "text";
                button.textContent = "Hide";
            } else {
                field.type = "password";
                button.textContent = "Show";
            }

        }
    </script>

</body>
</html>
