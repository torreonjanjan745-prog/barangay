
<?php
// =============================================
// ADMIN ACCOUNT CREATION - PDO VERSION
// File: administrator/CreatAdmin.php
// Database: barangay_db
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

// VERIFY ADMIN LOGIN
if (empty($_SESSION["admin_id"])) {
    header("Location: logIn.php");
    exit;
}

// DATABASE CONNECTION - PDO
require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("CreatAdmin.php: PDO connection is unavailable.");
    http_response_code(500);
    exit("Database connection error.");
}

$message = "";
$messageType = "";

// CSRF TOKEN
if (empty($_SESSION["admin_csrf"])) {
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}

// =============================================
// PROCESS ADMIN ACCOUNT CREATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $csrf = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION["admin_csrf"], $csrf)
    ) {
        $message = "Invalid form submission. Please refresh.";
        $messageType = "error";

    } elseif (
        $fullName === "" ||
        $username === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please complete all required fields.";
        $messageType = "error";

    } elseif (strlen($fullName) > 150) {
        $message = "Full name is too long.";
        $messageType = "error";

    } elseif (
        strlen($username) < 4 ||
        strlen($username) > 50
    ) {
        $message = "Username must be 4 to 50 characters.";
        $messageType = "error";

    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $message = "Username contains invalid characters.";
        $messageType = "error";

    } elseif (strlen($password) < 12) {
        $message = "Password must contain at least 12 characters.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "error";

    } else {

        try {
            // CHECK DUPLICATE USERNAME
            $check = $pdo->prepare(
                "SELECT id
                 FROM admin_account
                 WHERE username = :username
                 LIMIT 1"
            );

            $check->execute([
                ":username" => $username
            ]);

            $usernameExists = $check->fetchColumn();

            if ($usernameExists) {

                $message = "Username is already registered.";
                $messageType = "error";

            } else {

                // HASH PASSWORD
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {

                    $message = "Unable to secure password.";
                    $messageType = "error";

                } else {

                    // INSERT NEW ADMIN
                    $stmt = $pdo->prepare(
                        "INSERT INTO admin_account
                        (full_name, username, password)
                        VALUES
                        (:full_name, :username, :password)"
                    );

                    $stmt->execute([
                        ":full_name" => $fullName,
                        ":username" => $username,
                        ":password" => $hashedPassword
                    ]);

                    $message = "Administrator account created successfully!";
                    $messageType = "success";
                }
            }

        } catch (PDOException $e) {

            error_log("Admin Account Creation Error: " . $e->getMessage());

            if ($e->getCode() === "23000") {
                $message = "Username is already registered.";
            } else {
                $message = "Database error. Please check the admin_account table.";
            }

            $messageType = "error";
        }
    }

    // ROTATE CSRF TOKEN
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Create Admin Account | Barangay Guindacpan</title>

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
            background: #020617;
            font-family: Arial, sans-serif;
        }

        .container {
            width: 100%;
            max-width: 450px;
            padding: 35px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }

        .header {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo {
            width: 65px;
            height: 65px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 18px;
            background: #dcfce7;
            font-size: 30px;
        }

        h1 {
            color: #14532d;
            font-size: 25px;
            margin-bottom: 9px;
        }

        .subtitle {
            color: #64748b;
            font-size: 13px;
        }

        .message {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .form-group {
            margin-bottom: 19px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, 0.12);
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #166534;
            color: white;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #14532d;
        }

        .back {
            display: block;
            margin-top: 22px;
            text-align: center;
            color: #166534;
            font-size: 14px;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        .footer {
            margin-top: 22px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
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

    <div class="container">

        <div class="header">
            <div class="logo">🛡️</div>

            <h1>Create Admin Account</h1>

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

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["admin_csrf"], ENT_QUOTES, "UTF-8") ?>">

            <div class="form-group">
                <label for="full_name">Full Name</label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="150"
                    required
                    autocomplete="name"
                    placeholder="Enter full name">
            </div>

            <div class="form-group">
                <label for="username">Admin Username</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    minlength="4"
                    maxlength="50"
                    pattern="[a-zA-Z0-9_.-]+"
                    required
                    autocomplete="username"
                    placeholder="Create username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="12"
                    required
                    autocomplete="new-password"
                    placeholder="At least 12 characters">
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="12"
                    required
                    autocomplete="new-password"
                    placeholder="Confirm password">
            </div>

            <button type="submit">
                CREATE ADMIN ACCOUNT
            </button>

        </form>

        <a href="dashboard.php" class="back">
            ← Back to Admin Dashboard
        </a>

        <p class="footer">
            Authorized administrators only.
        </p>

    </div>

</body>
</html>
