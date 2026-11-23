
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR LOGIN SYSTEM
// COMPLETE SECURE PDO VERSION
//
// Database: barangay_health_db
// Table: admin_account
// Authentication: Full Name + BCRYPT Password
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER["HTTPS"]) &&
           $_SERVER["HTTPS"] !== "off";

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
// ==================================================

try {

    $adminPDO = new PDO(
        "mysql:host=localhost;port=3306;dbname=barangay_health_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log("ADMIN DATABASE ERROR: " . $e->getMessage());

    http_response_code(500);

    exit("Administrator database connection failed. Please contact the system administrator.");

}


// ==================================================
// 4. REDIRECT IF ALREADY LOGGED IN
// ==================================================

if (!empty($_SESSION["admin_id"])) {

    header("Location: dashboard.php");
    exit;

}


// ==================================================
// 5. INITIAL VARIABLES
// ==================================================

$message = "";
$fullName = "";


// ==================================================
// 6. CSRF TOKEN
// ==================================================

if (
    empty($_SESSION["admin_login_csrf"]) ||
    !is_string($_SESSION["admin_login_csrf"])
) {

    $_SESSION["admin_login_csrf"] = bin2hex(
        random_bytes(32)
    );

}


// ==================================================
// 7. LOGIN PROCESS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullNameInput = $_POST["full_name"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $tokenInput = $_POST["csrf_token"] ?? "";

    $fullName = is_string($fullNameInput)
        ? trim($fullNameInput)
        : "";

    $password = is_string($passwordInput)
        ? $passwordInput
        : "";

    $token = is_string($tokenInput)
        ? $tokenInput
        : "";


    try {

        // ------------------------------------------
        // VALIDATE CSRF TOKEN
        // ------------------------------------------

        if (
            !isset($_SESSION["admin_login_csrf"]) ||
            !is_string($_SESSION["admin_login_csrf"]) ||
            !hash_equals(
                $_SESSION["admin_login_csrf"],
                $token
            )
        ) {

            $message = "Invalid request. Please refresh the page.";

        } elseif ($fullName === "" || $password === "") {

            $message = "Please enter your full name and password.";

        } elseif (
            strlen($fullName) > 150 ||
            strlen($password) > 4096
        ) {

            $message = "Invalid login information.";

        } else {

            // --------------------------------------
            // FIND ADMINISTRATOR
            // FULL NAME AUTHENTICATION ONLY
            // --------------------------------------

            $stmt = $adminPDO->prepare("
                SELECT
                    id,
                    full_name,
                    password,
                    status
                FROM admin_account
                WHERE TRIM(full_name) = :full_name
                LIMIT 1
            ");

            $stmt->execute([
                ":full_name" => $fullName
            ]);

            $admin = $stmt->fetch(PDO::FETCH_ASSOC);


            // --------------------------------------
            // CHECK ACCOUNT
            // --------------------------------------

            if (!$admin) {

                error_log(
                    "ADMIN LOGIN: No matching full name found."
                );

                $message = "Invalid full name or password.";

            } elseif (
                !isset(
                    $admin["id"],
                    $admin["full_name"],
                    $admin["password"],
                    $admin["status"]
                )
            ) {

                error_log(
                    "ADMIN LOGIN: Required account columns missing."
                );

                $message = "Administrator account configuration error.";

            } elseif (
                strtolower(trim((string)$admin["status"])) !== "active"
            ) {

                error_log(
                    "ADMIN LOGIN: Administrator account is inactive."
                );

                $message = "Your administrator account is inactive.";

            } else {

                // ----------------------------------
                // BCRYPT PASSWORD VERIFICATION
                // ----------------------------------

                $storedPassword = (string)$admin["password"];

                $passwordValid = password_verify(
                    $password,
                    $storedPassword
                );


                if (!$passwordValid) {

                    error_log(
                        "ADMIN LOGIN: Password verification failed. Admin ID: " .
                        (int)$admin["id"]
                    );

                    $message = "Invalid full name or password.";

                } else {

                    // --------------------------------
                    // OPTIONAL PASSWORD HASH UPGRADE
                    // --------------------------------

                    if (password_needs_rehash(
                        $storedPassword,
                        PASSWORD_DEFAULT
                    )) {

                        $newHash = password_hash(
                            $password,
                            PASSWORD_DEFAULT
                        );

                        if ($newHash === false) {
                            throw new RuntimeException(
                                "Password rehash failed."
                            );
                        }

                        $update = $adminPDO->prepare("
                            UPDATE admin_account
                            SET password = :password
                            WHERE id = :id
                        ");

                        $update->execute([
                            ":password" => $newHash,
                            ":id" => (int)$admin["id"]
                        ]);
                    }


                    // --------------------------------
                    // REGENERATE SESSION ID
                    // --------------------------------

                    if (!session_regenerate_id(true)) {

                        throw new RuntimeException(
                            "Session regeneration failed."
                        );

                    }


                    // --------------------------------
                    // STORE ADMIN SESSION
                    // --------------------------------

                    $_SESSION["admin_id"] =
                        (int)$admin["id"];

                    $_SESSION["admin_name"] =
                        $admin["full_name"];

                    // Optional profile fields
                    $_SESSION["admin_email"] =
                        $admin["email"] ?? "";

                    $_SESSION["admin_contact"] =
                        $admin["contact_number"] ?? "";

                    $_SESSION["admin_position"] =
                        $admin["position"] ?? "";

                    $_SESSION["admin_login_time"] = time();

                    $_SESSION["admin_last_activity"] = time();


                    // --------------------------------
                    // ROTATE CSRF TOKEN
                    // --------------------------------

                    $_SESSION["admin_login_csrf"] =
                        bin2hex(random_bytes(32));


                    // --------------------------------
                    // REDIRECT TO DASHBOARD
                    // --------------------------------

                    header("Location: dashboard.php");
                    exit;

                }

            }

        }

    } catch (PDOException $e) {

        error_log(
            "ADMIN LOGIN DATABASE ERROR: " . $e->getMessage()
        );

        $message = "Unable to process login. Please contact the administrator.";

    } catch (Throwable $e) {

        error_log(
            "ADMIN LOGIN SYSTEM ERROR: " . $e->getMessage()
        );

        $message = "An unexpected error occurred. Please try again.";

    }


    // ----------------------------------------------
    // ROTATE CSRF TOKEN AFTER POST
    // ----------------------------------------------

    $_SESSION["admin_login_csrf"] = bin2hex(
        random_bytes(32)
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Administrator Login | Barangay Guindacpan</title>

    <style>

       
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --primary: #2563eb;
    --primary-dark: #1d4ed8;
    --text-dark: #0f172a;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --surface: #ffffff;
}

body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 30px 20px;
    font-family: Arial, Helvetica, sans-serif;

    background:
        radial-gradient(
            ellipse at 15% 15%,
            rgba(37, 99, 235, .22),
            transparent 38%
        ),
        radial-gradient(
            ellipse at 85% 85%,
            rgba(16, 185, 129, .12),
            transparent 35%
        ),
        linear-gradient(135deg, #07111f, #101d32 55%, #0b1728);
}

/* MAIN LOGIN CARD */

.login-container {
    width: 100%;
    max-width: 460px;
    padding: 42px 42px 30px;

    background: var(--surface);
    border: 1px solid rgba(255,255,255,.7);
    border-radius: 24px;

    box-shadow:
        0 30px 80px rgba(0,0,0,.35),
        0 8px 25px rgba(0,0,0,.12);

    position: relative;
    overflow: hidden;

    animation: fadeUp .55s ease;
}

.login-container::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 5px;

    background: linear-gradient(
        90deg,
        #1d4ed8,
        #3b82f6,
        #10b981
    );
}

@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* HEADER */

.header {
    text-align: center;
    margin-bottom: 34px;
}

.logo {
    width: 108px;
    height: 108px;
    margin: 0 auto 22px;
    padding: 10px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 50%;

    box-shadow:
        0 8px 20px rgba(15,23,42,.07),
        inset 0 0 0 5px #ffffff;

    overflow: hidden;
}

.logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

h1 {
    color: var(--text-dark);
    font-size: 26px;
    font-weight: 800;
    letter-spacing: -.7px;
    margin-bottom: 10px;
}

.subtitle {
    max-width: 290px;
    margin: 0 auto;

    color: var(--text-muted);
    font-size: 13px;
    line-height: 1.7;
}

/* ERROR MESSAGE */

.error-message {
    display: flex;
    align-items: flex-start;
    gap: 10px;

    padding: 14px 15px;
    margin-bottom: 23px;

    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 11px;

    font-size: 13px;
    line-height: 1.6;
}

/* FORM */

form {
    width: 100%;
}

.form-group {
    margin-bottom: 23px;
}

label {
    display: block;
    margin-bottom: 10px;

    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

/* INPUT */

input {
    width: 100%;
    height: 52px;
    padding: 0 16px;

    color: #0f172a;
    background: #f8fafc;

    border: 1px solid #dbe3ee;
    border-radius: 11px;

    font-family: inherit;
    font-size: 14px;

    outline: none;
    transition: all .2s ease;
}

input::placeholder {
    color: #94a3b8;
}

input:hover {
    border-color: #94a3b8;
    background: #ffffff;
}

input:focus {
    background: #ffffff;
    border-color: var(--primary);

    box-shadow:
        0 0 0 4px rgba(37,99,235,.10);
}

/* PASSWORD */

.password-wrapper {
    position: relative;
}

.password-wrapper input {
    padding-right: 52px;
}

.toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);

    width: 34px;
    height: 34px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #64748b;
    background: transparent;
    border: none;
    border-radius: 7px;

    cursor: pointer;
    transition: .2s;
}

.toggle-password:hover {
    color: var(--primary);
    background: #eff6ff;
}

.toggle-password svg {
    width: 21px;
    height: 21px;
}

.toggle-password:focus-visible {
    outline: 3px solid rgba(37,99,235,.25);
    outline-offset: 2px;
}

/* LOGIN BUTTON */

.login-button {
    width: 100%;
    min-height: 53px;
    padding: 15px;

    border: none;
    border-radius: 11px;

    background: linear-gradient(
        135deg,
        #2563eb,
        #1d4ed8
    );

    color: #ffffff;

    font-size: 13px;
    font-weight: 800;
    letter-spacing: .7px;

    cursor: pointer;
    transition: all .25s ease;

    box-shadow:
        0 7px 16px rgba(37,99,235,.22);
}

.login-button:hover {
    background: linear-gradient(
        135deg,
        #1d4ed8,
        #1e40af
    );

    transform: translateY(-2px);

    box-shadow:
        0 10px 22px rgba(37,99,235,.30);
}

.login-button:active {
    transform: translateY(0);
}

.login-button:focus-visible {
    outline: 3px solid rgba(37,99,235,.3);
    outline-offset: 3px;
}

/* FOOTER */

.footer {
    margin-top: 30px;
    padding-top: 20px;

    border-top: 1px solid #edf0f4;

    text-align: center;
    color: #64748b;

    font-size: 12px;
    line-height: 1.9;
}

/* RESPONSIVE */

@media (max-width: 480px) {
    body {
        padding: 16px;
    }

    .login-container {
        padding: 34px 24px 25px;
        border-radius: 19px;
    }

    .logo {
        width: 90px;
        height: 90px;
    }

    h1 {
        font-size: 23px;
    }

    .header {
        margin-bottom: 29px;
    }
}

@media (max-width: 350px) {
    .login-container {
        padding: 30px 19px 22px;
    }

    h1 {
        font-size: 21px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        animation-duration: .01ms !important;
        transition-duration: .01ms !important;
    }
}


    </style>

</head>

<body>

    <main class="login-container">

        <!-- HEADER -->

        <div class="header">

            <div class="logo">

                <img
                    src="../asset/logo.png"
                    alt="Barangay Guindacpan Logo"
                >

            </div>

            <h1>Administrator Login</h1>

            <p class="subtitle">
                Sign in to access the administrator portal.
            </p>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($message !== ""): ?>

            <div class="error-message" role="alert">

                <?= htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["admin_login_csrf"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >


            <!-- FULL NAME -->

            <div class="form-group">

                <label for="full_name">
                    Full Name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    value="<?= htmlspecialchars(
                        $fullName,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    placeholder="Enter your full name"
                    maxlength="150"
                    autocomplete="name"
                    required
                    autofocus
                >

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="password-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password"
                        aria-pressed="false"
                    >

                        <svg
                            id="eyeIcon"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >

                            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>

                            <circle cx="12" cy="12" r="3"/>

                        </svg>

                    </button>

                </div>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-button"
            >
                SIGN IN TO ADMIN PORTAL
            </button>

        </form>


        <!-- FOOTER -->

        <div class="footer">

            Barangay Guindacpan Administrator Portal

            <br>

            Authorized personnel only.

        </div>

    </main>


    <!-- SHOW / HIDE PASSWORD -->

    <script>

        const passwordInput =
            document.getElementById("password");

        const togglePassword =
            document.getElementById("togglePassword");

        const eyeIcon =
            document.getElementById("eyeIcon");


        togglePassword.addEventListener("click", function () {

            const isHidden =
                passwordInput.type === "password";

            passwordInput.type =
                isHidden ? "text" : "password";

            togglePassword.setAttribute(
                "aria-label",
                isHidden ? "Hide password" : "Show password"
            );

            togglePassword.setAttribute(
                "aria-pressed",
                isHidden ? "true" : "false"
            );

            if (isHidden) {

                eyeIcon.innerHTML = `
                    <path d="M3 3l18 18"/>
                    <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"/>
                    <path d="M9.9 5.2A11 11 0 0 1 12 5c6.4 0 10 7 10 7a13 13 0 0 1-3.1 3.8"/>
                    <path d="M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3.1-.5"/>
                `;

            } else {

                eyeIcon.innerHTML = `
                    <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/>
                    <circle cx="12" cy="12" r="3"/>
                `;

            }

        });

    </script>

</body>

</html>
