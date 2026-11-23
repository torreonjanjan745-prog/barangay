
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMIN ACCOUNT SETUP
// PDO VERSION
// Database: barangay_db
// ==================================================

if (session_status() === PHP_SESSION_NONE) {
    session_name("BARANGAY_ADMIN_SETUP_SESSION");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "httponly" => true,
        "samesite" => "Strict"
    ]);

    session_start();
}

// Prevent browser caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("PDO connection missing in admin/setUpAdmin.php");
    http_response_code(500);
    exit("Database connection error. Please contact the administrator.");
}

$message = "";
$messageType = "";

// IMPORTANT:
// Change this setup key to a new private secret.
// Do not share it publicly.
$setupKey = "GuindacpanAdmin2026!SecureKey";

// Generate CSRF token
if (empty($_SESSION["admin_setup_token"])) {
    $_SESSION["admin_setup_token"] = bin2hex(random_bytes(32));
}

// ==================================================
// CHECK EXISTING ADMIN ACCOUNT
// ==================================================

try {
    $checkAdmin = $pdo->query(
        "SELECT id FROM admin_users LIMIT 1"
    );

    $adminExists = (bool) $checkAdmin->fetch();

} catch (PDOException $e) {
    error_log("Admin setup check error: " . $e->getMessage());

    http_response_code(500);
    exit("Database error. Please check the admin_users table.");
}

// ==================================================
// PROCESS ADMIN REGISTRATION
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $enteredKey = $_POST["setup_key"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        empty($_SESSION["admin_setup_token"]) ||
        !hash_equals($_SESSION["admin_setup_token"], $csrfToken)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (!hash_equals($setupKey, $enteredKey)) {
        $message = "Invalid setup key.";
        $messageType = "error";

    } elseif ($fullName === "" || $username === "" || $password === "") {
        $message = "Please complete all required fields.";
        $messageType = "error";

    } elseif (strlen($fullName) > 150) {
        $message = "Full name must not exceed 150 characters.";
        $messageType = "error";

    } elseif (strlen($username) < 4 || strlen($username) > 50) {
        $message = "Username must be 4 to 50 characters.";
        $messageType = "error";

    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $message = "Username can only contain letters, numbers, dots, underscores, and hyphens.";
        $messageType = "error";

    } elseif (strlen($password) < 12) {
        $message = "Password must be at least 12 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        try {
            // Check duplicate username
            $checkUsername = $pdo->prepare(
                "SELECT id
                 FROM admin_users
                 WHERE username = :username
                 LIMIT 1"
            );

            $checkUsername->execute([
                ":username" => $username
            ]);

            if ($checkUsername->fetch()) {

                $message = "Username already exists. Please choose another.";
                $messageType = "error";

            } else {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {

                    $message = "Unable to secure the password.";
                    $messageType = "error";

                } else {

                    // Insert administrator
                    $stmt = $pdo->prepare(
                        "INSERT INTO admin_users
                            (full_name, username, password)
                         VALUES
                            (:full_name, :username, :password)"
                    );

                    $stmt->execute([
                        ":full_name" => $fullName,
                        ":username" => $username,
                        ":password" => $hashedPassword
                    ]);

                    $message = "Admin account created successfully! You can now log in.";
                    $messageType = "success";
                    $adminExists = true;

                    // Invalidate setup token after successful registration
                    unset($_SESSION["admin_setup_token"]);
                }
            }

        } catch (PDOException $e) {

            error_log("Admin registration error: " . $e->getMessage());

            if ($e->getCode() === "23000") {
                $message = "Username already exists. Please choose another.";
            } else {
                $message = "Unable to create account. Please check your database table.";
            }

            $messageType = "error";
        }
    }
}

// Refresh token if still needed
if (!$adminExists && empty($_SESSION["admin_setup_token"])) {
    $_SESSION["admin_setup_token"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Admin Account | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-950 flex items-center justify-center p-5">

    <div class="w-full max-w-md bg-slate-900 border border-slate-700 rounded-2xl p-8 shadow-2xl">

        <div class="text-center mb-8">
            <div class="text-4xl mb-3">🛡️</div>

            <h1 class="text-2xl font-bold text-white">
                Create Admin Account
            </h1>

            <p class="text-slate-400 text-sm mt-2">
                Barangay Guindacpan Management System
            </p>
        </div>

        <?php if ($message !== ""): ?>
            <div class="mb-5 p-3 rounded-lg text-sm
                <?= $messageType === "success"
                    ? "bg-green-900/40 text-green-300 border border-green-700"
                    : "bg-red-900/40 text-red-300 border border-red-700" ?>">

                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

            </div>
        <?php endif; ?>

        <?php if ($adminExists): ?>

            <div class="text-center">

                <p class="text-green-400 mb-5">
                    An admin account already exists. Admin setup is closed.
                </p>

                <a href="login.php"
                   class="block w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-lg transition">
                    Go to Admin Login
                </a>

            </div>

        <?php else: ?>

            <form method="POST" action="" class="space-y-4">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($_SESSION["admin_setup_token"], ENT_QUOTES, "UTF-8") ?>"
                >

                <div>
                    <label class="block text-sm text-slate-300 mb-2">
                        Full Name
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        required
                        autocomplete="name"
                        class="w-full p-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:border-green-500"
                        placeholder="Enter your full name">
                </div>

                <div>
                    <label class="block text-sm text-slate-300 mb-2">
                        Username
                    </label>

                    <input
                        type="text"
                        name="username"
                        minlength="4"
                        maxlength="50"
                        required
                        autocomplete="username"
                        class="w-full p-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:border-green-500"
                        placeholder="Create admin username">
                </div>

                <div>
                    <label class="block text-sm text-slate-300 mb-2">
                        Password
                    </label>

                    <input
                        type="password"
                        name="password"
                        minlength="12"
                        required
                        autocomplete="new-password"
                        class="w-full p-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:border-green-500"
                        placeholder="At least 12 characters">
                </div>

                <div>
                    <label class="block text-sm text-slate-300 mb-2">
                        Confirm Password
                    </label>

                    <input
                        type="password"
                        name="confirm_password"
                        minlength="12"
                        required
                        autocomplete="new-password"
                        class="w-full p-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:border-green-500"
                        placeholder="Confirm your password">
                </div>

                <div>
                    <label class="block text-sm text-slate-300 mb-2">
                        Setup Secret Key
                    </label>

                    <input
                        type="password"
                        name="setup_key"
                        required
                        autocomplete="off"
                        class="w-full p-3 rounded-lg bg-slate-800 border border-slate-700 text-white focus:outline-none focus:border-green-500"
                        placeholder="Enter your private setup key">
                </div>

                <button
                    type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition">
                    Create Admin Account
                </button>

            </form>

        <?php endif; ?>

        <p class="text-center text-xs text-slate-500 mt-6">
            Authorized administrator setup only.
        </p>

    </div>

</body>
</html>
