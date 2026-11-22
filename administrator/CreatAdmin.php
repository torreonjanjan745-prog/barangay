
<?php
// =============================================
// CREATE FIRST ADMINISTRATOR ACCOUNT
// BARANGAY GUINDACPAN
// PDO VERSION
// =============================================

// SESSION CONFIGURATION
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

// SECURITY HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// DATABASE CONNECTION - PDO
require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Admin setup: PDO connection unavailable.");
    http_response_code(500);
    exit("Database connection error.");
}

// INITIAL VALUES
$message = "";
$messageType = "error";
$fullName = "";
$username = "";
$adminExists = false;

// =============================================
// CHECK EXISTING ADMINISTRATOR
// =============================================

try {
    $countStmt = $pdo->query(
        "SELECT COUNT(*) FROM admin_users"
    );

    $adminExists = ((int)$countStmt->fetchColumn() > 0);

} catch (PDOException $e) {
    error_log("Admin setup count error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to check administrator account. Please verify the admin_users table.");
}

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["admin_setup_csrf"])) {
    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));
}

// =============================================
// HANDLE CREATE ADMIN FORM
// =============================================

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
        !hash_equals($_SESSION["admin_setup_csrf"], $token)
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
            // CHECK DUPLICATE USERNAME
            $check = $pdo->prepare(
                "SELECT id
                 FROM admin_users
                 WHERE username = :username
                 LIMIT 1"
            );

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

                    // INSERT ADMINISTRATOR
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

                    $message = "Administrator account created successfully! You can now log in.";
                    $messageType = "success";

                    $fullName = "";
                    $username = "";

                    // LOCK FIRST-ADMIN SETUP
                    $adminExists = true;
                }
            }

        } catch (PDOException $e) {

            error_log("Admin setup insert error: " . $e->getMessage());

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

    <title>Create Administrator | Barangay Guindacpan</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 25px;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: #ffffff;
            padding: 36px;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.10);
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo {
            width: 68px;
            height: 68px;
            margin: 0 auto 15px;
            border-radius: 17px;
            overflow: hidden;
            background: #dcfce7;
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        h1 {
            color: #111827;
            font-size: 25px;
            margin-bottom: 9px;
        }

        .subtitle {
            color: #64748b;
            font-size: 13px;
        }

        .message {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success {
            color: #166534;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
        }

        .error {
            color: #991b1b;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .notice {
            padding: 22px;
            border-radius: 12px;
            text-align: center;
            color: #1e40af;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .notice h2 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .notice p {
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
        }

        button,
        .login-link {
            display: block;
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #14532d;
            color: #ffffff;
            text-align: center;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover,
        .login-link:hover {
            background: #166534;
        }

        .footer {
            margin-top: 25px;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }

        .back {
            display: block;
            margin-top: 18px;
            text-align: center;
            color: #166534;
            font-size: 13px;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }

            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>

<body>

    <main class="container">

        <div class="header">

            <div class="logo">
                <img
                    src="../asset/logo.jpg"
                    alt="Barangay Guindacpan Logo">
            </div>

            <h1>Create Administrator</h1>

            <p class="subtitle">
                Barangay Guindacpan Administration
            </p>

        </div>

        <?php if ($message !== ""): ?>

            <div
                class="message <?= $messageType === "success" ? "success" : "error" ?>"
                role="alert">

                <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>

            </div>

        <?php endif; ?>

        <?php if ($adminExists): ?>

            <div class="notice">

                <h2>Administrator Already Exists</h2>

                <p>
                    An administrator account has already been created.
                    You can now proceed to the administrator login page.
                </p>

                <a href="adminlogIn.php" class="login-link">
                    Go to Admin Login
                </a>

            </div>

        <?php else: ?>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION["admin_setup_csrf"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>">

                <div class="form-group">

                    <label for="full_name">Full Name</label>

                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="150"
                        value="<?= htmlspecialchars($fullName, ENT_QUOTES, "UTF-8") ?>"
                        placeholder="Administrator full name"
                        autocomplete="name"
                        required>

                </div>

                <div class="form-group">

                    <label for="username">Admin Username</label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        minlength="4"
                        maxlength="100"
                        pattern="[a-zA-Z0-9_.@-]+"
                        value="<?= htmlspecialchars($username, ENT_QUOTES, "UTF-8") ?>"
                        placeholder="Create admin username"
                        autocomplete="username"
                        required>

                </div>

                <div class="form-group">

                    <label for="password">Password</label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="12"
                        placeholder="At least 12 characters"
                        autocomplete="new-password"
                        required>

                </div>

                <div class="form-group">

                    <label for="confirm_password">Confirm Password</label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="12"
                        placeholder="Confirm your password"
                        autocomplete="new-password"
                        required>

                </div>

                <button type="submit">
                    Create Administrator Account
                </button>

            </form>

        <?php endif; ?>

        <p class="footer">
            Authorized administrators only.
        </p>

        <a href="../main.php" class="back">
            ← Back to Homepage
        </a>

    </main>

</body>
</html>
