
<?php
// =============================================
// ADMINISTRATOR LOGIN SYSTEM - PDO
// BARANGAY GUINDACPAN
// =============================================

// SESSION CONFIGURATION
$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_ADMIN_SESSION");

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
}

// SECURITY HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// DATABASE CONNECTION
require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Admin Login: PDO connection unavailable.");
    http_response_code(500);
    exit("Database connection error. Please check db.php.");
}

// CHECK EXISTING ADMIN SESSION
if (isset($_SESSION["admin_id"])) {
    header("Location: dashboard.php");
    exit;
}

// INITIAL VALUES
$message = "";
$username = "";

// CSRF TOKEN
if (empty($_SESSION["admin_login_csrf"])) {
    $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));
}

// =============================================
// HANDLE ADMIN LOGIN
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usernameInput = $_POST["username"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $tokenInput = $_POST["csrf_token"] ?? "";

    $username = is_string($usernameInput)
        ? trim($usernameInput)
        : "";

    $password = is_string($passwordInput)
        ? $passwordInput
        : "";

    $token = is_string($tokenInput)
        ? $tokenInput
        : "";

    // CSRF VALIDATION
    if (
        !hash_equals(
            $_SESSION["admin_login_csrf"],
            $token
        )
    ) {

        $message = "Invalid request. Please refresh the page.";

    } elseif ($username === "" || $password === "") {

        $message = "Please enter your username and password.";

    } else {

        try {

            // FIND ADMIN ACCOUNT
            $stmt = $pdo->prepare(
                "SELECT
                    id,
                    full_name,
                    username,
                    password
                 FROM admin_users
                 WHERE username = :username
                 LIMIT 1"
            );

            $stmt->execute([
                ":username" => $username
            ]);

            $admin = $stmt->fetch();

            // VERIFY PASSWORD
            if (
                $admin &&
                password_verify($password, $admin["password"])
            ) {

                // REGENERATE SESSION ID
                session_regenerate_id(true);

                // ADMIN SESSION
                $_SESSION["admin_id"] = (int)$admin["id"];
                $_SESSION["admin_name"] = $admin["full_name"];
                $_SESSION["admin_username"] = $admin["username"];
                $_SESSION["admin_role"] = "Administrator";

                $_SESSION["admin_login_time"] = time();
                $_SESSION["admin_last_activity"] = time();

                // ROTATE CSRF TOKEN
                $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));

                // REDIRECT TO DASHBOARD
                header("Location: dashboard.php");
                exit;

            } else {

                $message = "Invalid username or password.";

            }

        } catch (PDOException $e) {

            error_log("Admin Login Database Error: " . $e->getMessage());

            $message = "Unable to process login. Please check the database table.";
        }
    }

    // ROTATE CSRF TOKEN
    $_SESSION["admin_login_csrf"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Login | Barangay Health System</title>

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
            background: #000000;
            font-family: Arial, sans-serif;
        }

        .login-container {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            padding: 38px;
            border-radius: 18px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.10);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 68px;
            height: 68px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #dbeafe;
            color: #1d4ed8;
            border-radius: 18px;
            font-size: 34px;
        }

        h1 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }

        .error-message {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 9px;
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
            font-size: 13px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 14px;
            font-weight: bold;
            color: #374151;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            background: #1d4ed8;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1e40af;
        }

        .footer {
            margin-top: 27px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 27px 22px;
            }

            h1 {
                font-size: 21px;
            }
        }
    </style>

</head>

<body>

    <main class="login-container">

        <div class="header">

            <div class="logo">
                🛡️
            </div>

            <h1>Administrator Login</h1>

            <p class="subtitle">
                Sign in to access the administrator portal.
            </p>

        </div>

        <?php if ($message !== ""): ?>

            <div class="error-message" role="alert">

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_login_csrf"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>">

            <div class="form-group">

                <label for="username">
                    Admin Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    value="<?= htmlspecialchars(
                        $username,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    placeholder="Enter your admin username"
                    maxlength="100"
                    autocomplete="username"
                    required>

            </div>

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required>

            </div>

            <button type="submit">
                SIGN IN TO ADMIN PORTAL
            </button>

        </form>

        <div class="footer">
            Barangay Guindacpan Administrator Portal
        </div>

    </main>

</body>
</html>
