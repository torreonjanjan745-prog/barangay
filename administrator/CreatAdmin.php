
<?php
// ==================================================
// BARANGAY GUINDACPAN
// CREATE FIRST ADMINISTRATOR ACCOUNT
// COMPLETE SECURE PDO VERSION
//
// Database: barangay_db
// Table: admin_users
// Columns:
// id, full_name, username, password, created_at
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_ADMIN_SETUP");

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Strict"
    ]);

    session_start();
}


// ==================================================
// 2. SECURITY HEADERS
// ==================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");


// ==================================================
// 3. DATABASE CONNECTION
// DIRECT CONNECTION TO barangay_db
// ==================================================

$host = "localhost";
$dbname = "barangay_db";
$dbUsername = "root";
$dbPassword = "";

try {

    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log("Admin Setup Database Error: " . $e->getMessage());

    http_response_code(500);

    exit("Database connection failed. Please check your database configuration.");
}


// ==================================================
// 4. INITIAL VALUES
// ==================================================

$message = "";
$messageType = "error";

$fullName = "";
$username = "";

$adminExists = false;


// ==================================================
// 5. CHECK ADMINISTRATOR TABLE
// ==================================================

try {

    $tableCheck = $pdo->query("
        SHOW TABLES LIKE 'admin_users'
    ");

    if (!$tableCheck->fetchColumn()) {

        http_response_code(500);

        exit(
            "The admin_users table does not exist in barangay_db. " .
            "Please create the required database table first."
        );
    }

    $countStmt = $pdo->query("
        SELECT COUNT(*)
        FROM admin_users
    ");

    $adminExists = ((int) $countStmt->fetchColumn() > 0);

} catch (PDOException $e) {

    error_log("Admin Setup Table Error: " . $e->getMessage());

    http_response_code(500);

    exit("Unable to check administrator account. Please verify the admin_users table.");
}


// ==================================================
// 6. CSRF TOKEN
// ==================================================

if (empty($_SESSION["admin_setup_csrf"])) {

    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));
}


// ==================================================
// 7. HANDLE CREATE ADMINISTRATOR
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $tokenInput = $_POST["csrf_token"] ?? "";

    $nameInput = $_POST["full_name"] ?? "";
    $usernameInput = $_POST["username"] ?? "";

    $passwordInput = $_POST["password"] ?? "";
    $confirmInput = $_POST["confirm_password"] ?? "";

    $token = is_string($tokenInput) ? $tokenInput : "";

    $fullName = is_string($nameInput) ? trim($nameInput) : "";

    $username = is_string($usernameInput) ? trim($usernameInput) : "";

    $password = is_string($passwordInput) ? $passwordInput : "";

    $confirmPassword = is_string($confirmInput) ? $confirmInput : "";


    if (
        !hash_equals(
            $_SESSION["admin_setup_csrf"],
            $token
        )
    ) {

        $message = "Invalid request. Please refresh the page.";

    } elseif (
        $fullName === "" ||
        $username === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {

        $message = "Please complete all fields.";

    } elseif (strlen($fullName) > 150) {

        $message = "Full name must not exceed 150 characters.";

    } elseif (
        strlen($username) < 4 ||
        strlen($username) > 100 ||
        !preg_match('/^[a-zA-Z0-9_.@-]+$/', $username)
    ) {

        $message = "Username must be 4 to 100 characters and contain only letters, numbers, dots, underscores, @ or hyphens.";

    } elseif (strlen($password) < 12) {

        $message = "Password must contain at least 12 characters.";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";

    } else {

        try {

            // CHECK EXISTING ADMIN USERNAME

            $check = $pdo->prepare("
                SELECT id
                FROM admin_users
                WHERE username = :username
                LIMIT 1
            ");

            $check->execute([
                ":username" => $username
            ]);


            if ($check->fetch()) {

                $message = "This admin username is already registered.";

            } else {

                // HASH PASSWORD

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {

                    $message = "Unable to secure your password.";

                } else {

                    // CREATE ADMIN ACCOUNT

                    $stmt = $pdo->prepare("
                        INSERT INTO admin_users
                        (
                            full_name,
                            username,
                            password
                        )
                        VALUES
                        (
                            :full_name,
                            :username,
                            :password
                        )
                    ");

                    $stmt->execute([
                        ":full_name" => $fullName,
                        ":username" => $username,
                        ":password" => $hashedPassword
                    ]);

                    $message = "Administrator account created successfully! You can now log in.";

                    $messageType = "success";

                    $fullName = "";
                    $username = "";

                    $adminExists = true;
                }
            }

        } catch (PDOException $e) {

            error_log("Admin Setup Insert Error: " . $e->getMessage());

            if ($e->getCode() === "23000") {

                $message = "This admin username is already registered.";

            } else {

                $message = "Unable to create administrator account. Please check the database table.";
            }
        }
    }

    // ROTATE CSRF TOKEN

    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Create Administrator | Barangay Guindacpan</title>

    <link rel="icon" type="image/png" href="../asset/logo.png">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .input-field {
            width: 100%;
            padding: 13px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            color: #0f172a;
            font-size: 14px;
            outline: none;
            transition: 0.2s ease;
        }

        .input-field:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.10);
        }

        .primary-button {
            background: #166534;
            transition: 0.2s ease;
        }

        .primary-button:hover {
            background: #14532d;
            transform: translateY(-1px);
        }

        .primary-button:active {
            transform: translateY(0);
        }

    </style>

</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4 py-10">

    <main class="w-full max-w-md">

        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">

            <!-- HEADER -->

            <div class="px-8 pt-9 pb-7 text-center">

                <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-green-50 border border-green-100 flex items-center justify-center overflow-hidden">

                    <img
                        src="../asset/logo.png"
                        alt="Barangay Guindacpan Logo"
                        class="w-full h-full object-contain"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">

                    <div class="hidden w-full h-full items-center justify-center text-green-800">
                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                            <path d="M12 2 3 7v5c0 5.5 3.8 9 9 10 5.2-1 9-4.5 9-10V7l-9-5Z"/>
                            <path d="m9 12 2 2 4-4"/>
                        </svg>
                    </div>

                </div>

                <p class="text-xs font-bold uppercase tracking-[0.2em] text-green-700 mb-2">
                    Barangay Guindacpan
                </p>

                <h1 class="text-2xl font-extrabold text-slate-900">
                    Create Administrator
                </h1>

                <p class="text-sm text-slate-500 mt-2">
                    Administrator Account Setup
                </p>

            </div>


            <div class="px-8 pb-8">

                <!-- MESSAGES -->

                <?php if ($message !== ""): ?>

                    <div
                        class="mb-6 p-4 rounded-xl text-sm leading-relaxed
                        <?= $messageType === "success"
                            ? "bg-green-50 text-green-800 border border-green-200"
                            : "bg-red-50 text-red-800 border border-red-200" ?>"
                        role="alert">

                        <div class="flex items-start gap-3">

                            <span class="font-bold">
                                <?= $messageType === "success" ? "✓" : "!" ?>
                            </span>

                            <p>
                                <?= htmlspecialchars(
                                    $message,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </p>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- ADMIN ALREADY EXISTS -->

                <?php if ($adminExists): ?>

                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">

                        <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-blue-100 flex items-center justify-center text-blue-700">

                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 6 9 17l-5-5"/>
                            </svg>

                        </div>

                        <h2 class="text-lg font-bold text-blue-900 mb-2">
                            Administrator Already Exists
                        </h2>

                        <p class="text-sm text-blue-800 leading-relaxed mb-5">
                            An administrator account has already been created. Proceed to the administrator login page.
                        </p>

                        <a
                            href="adminlogIn.php"
                            class="block w-full bg-green-800 hover:bg-green-900 text-white font-semibold text-sm py-3.5 rounded-xl transition">

                            Go to Admin Login

                        </a>

                    </div>

                <?php else: ?>


                    <!-- CREATE ADMIN FORM -->

                    <form method="POST" action="" autocomplete="off">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                $_SESSION["admin_setup_csrf"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>">


                        <!-- FULL NAME -->

                        <div class="mb-5">

                            <label
                                for="full_name"
                                class="block text-sm font-semibold text-slate-700 mb-2">

                                Full Name

                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="150"
                                value="<?= htmlspecialchars(
                                    $fullName,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                class="input-field"
                                placeholder="Enter administrator full name"
                                autocomplete="name"
                                required>

                        </div>


                        <!-- USERNAME -->

                        <div class="mb-5">

                            <label
                                for="username"
                                class="block text-sm font-semibold text-slate-700 mb-2">

                                Admin Username

                            </label>

                            <input
                                type="text"
                                id="username"
                                name="username"
                                minlength="4"
                                maxlength="100"
                                pattern="[a-zA-Z0-9_.@-]+"
                                value="<?= htmlspecialchars(
                                    $username,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                class="input-field"
                                placeholder="Create your username"
                                autocomplete="username"
                                required>

                        </div>


                        <!-- PASSWORD -->

                        <div class="mb-5">

                            <label
                                for="password"
                                class="block text-sm font-semibold text-slate-700 mb-2">

                                Password

                            </label>

                            <input
                                type="password"
                                id="password"
                                name="password"
                                minlength="12"
                                class="input-field"
                                placeholder="Enter at least 12 characters"
                                autocomplete="new-password"
                                required>

                            <p class="text-xs text-slate-400 mt-2">
                                Use at least 12 characters for better security.
                            </p>

                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="mb-7">

                            <label
                                for="confirm_password"
                                class="block text-sm font-semibold text-slate-700 mb-2">

                                Confirm Password

                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="12"
                                class="input-field"
                                placeholder="Re-enter your password"
                                autocomplete="new-password"
                                required>

                        </div>


                        <!-- SUBMIT BUTTON -->

                        <button
                            type="submit"
                            class="primary-button w-full text-white font-bold text-sm py-4 rounded-xl shadow-md">

                            Create Administrator Account

                        </button>

                    </form>

                <?php endif; ?>


                <!-- FOOTER -->

                <div class="border-t border-slate-100 mt-7 pt-6">

                    <p class="text-center text-xs text-slate-400">
                        <span class="font-semibold text-slate-600">
                            Authorized Administrators Only
                        </span>
                    </p>

                    <a
                        href="../main.php"
                        class="block text-center text-sm font-semibold text-green-800 hover:text-green-950 mt-5 transition">

                        ← Back to Homepage

                    </a>

                </div>

            </div>

        </div>


        <p class="text-center text-xs text-slate-400 mt-6">
            © <?= date("Y") ?> Barangay Guindacpan. All rights reserved.
        </p>

    </main>

</body>
</html>
