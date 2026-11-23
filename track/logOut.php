
<?php
// =============================================
// BARANGAY GUINDACPAN
// RESIDENT LOGIN
// Modern Responsive Design
// Database: barangay_db
// Connection: Existing db.php
// =============================================

$isHttps = !empty($_SERVER["HTTPS"]) &&
           $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_RESIDENT_SESSION");

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

// Already logged in
if (isset($_SESSION["resident_id"])) {
    header("Location: dashboard.php");
    exit;
}

// Existing database connection
require_once "../db.php";

$error = "";
$username = "";

// =============================================
// LOGIN PROCESS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    full_name,
                    username,
                    password,
                    status
                FROM residents
                WHERE username = :username
                LIMIT 1
            ");

            $stmt->execute([
                ":username" => $username
            ]);

            $resident = $stmt->fetch();

            if (
                $resident &&
                password_verify($password, $resident["password"])
            ) {

                if ($resident["status"] !== "approved") {

                    $error = "Your account is not yet approved. Please contact the barangay administrator.";

                } else {

                    session_regenerate_id(true);

                    $_SESSION["resident_id"] = (int)$resident["id"];
                    $_SESSION["resident_name"] = $resident["full_name"];
                    $_SESSION["resident_username"] = $resident["username"];

                    header("Location: dashboard.php");
                    exit;
                }

            } else {

                $error = "Invalid username or password.";

            }

        } catch (PDOException $e) {

            error_log("Resident Login Error: " . $e->getMessage());

            $error = "Unable to process your login. Please try again.";

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Resident Login | Barangay Guindacpan</title>

<script src="https://cdn.tailwindcss.com"></script>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
body {
    font-family: Arial, sans-serif;
}

.login-bg {
    background:
        radial-gradient(circle at 15% 20%, rgba(37,99,235,.22), transparent 35%),
        radial-gradient(circle at 85% 80%, rgba(14,165,233,.15), transparent 35%),
        #0a1220;
}

.input-field:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    outline: none;
}

.login-button {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

.login-button:hover {
    background: linear-gradient(135deg, #1d4ed8, #1e40af);
}
</style>

</head>

<body class="login-bg min-h-screen flex items-center
             justify-center p-4">

<div class="w-full max-w-md">

    <!-- LOGIN CARD -->

    <div class="bg-white rounded-3xl shadow-2xl
                overflow-hidden">

        <!-- HEADER -->

        <div class="bg-[#101827] text-white
                    text-center px-6 py-9">

            <div class="w-20 h-20 mx-auto mb-4
                        bg-white rounded-full
                        flex items-center justify-center
                        overflow-hidden">

                <img
                    src="../asset/logo.jpg"
                    alt="Barangay Guindacpan Logo"
                    class="w-full h-full object-contain"
                >

            </div>

            <h1 class="text-xl font-bold tracking-wide">

                BARANGAY GUINDACPAN

            </h1>

            <p class="text-blue-200 text-xs
                      tracking-widest mt-2">

                MANAGEMENT INFORMATION SYSTEM

            </p>

        </div>

        <!-- FORM -->

        <div class="p-7 sm:p-9">

            <div class="mb-7">

                <h2 class="text-2xl font-bold
                           text-gray-800">

                    Welcome Back

                </h2>

                <p class="text-gray-500 text-sm mt-2">

                    Sign in to access your resident account.

                </p>

            </div>

            <!-- ERROR -->

            <?php if ($error): ?>

                <div class="bg-red-50 border border-red-200
                            text-red-700 rounded-xl
                            p-4 mb-5 text-sm">

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </div>

            <?php endif; ?>

            <form method="POST" class="space-y-5">

                <!-- USERNAME -->

                <div>

                    <label class="block text-sm
                                  font-semibold text-gray-700
                                  mb-2">

                        Username

                    </label>

                    <div class="relative">

                        <i data-lucide="user"
                           class="absolute left-4 top-1/2
                                  -translate-y-1/2
                                  w-5 h-5 text-gray-400"></i>

                        <input
                            type="text"
                            name="username"
                            value="<?= htmlspecialchars(
                                $username,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            placeholder="Enter your username"
                            autocomplete="username"
                            required
                            class="input-field w-full
                                   border border-gray-300
                                   rounded-xl py-3.5 pl-12 pr-4
                                   text-gray-800 transition"
                        >

                    </div>

                </div>

                <!-- PASSWORD -->

                <div>

                    <label class="block text-sm
                                  font-semibold text-gray-700
                                  mb-2">

                        Password

                    </label>

                    <div class="relative">

                        <i data-lucide="lock"
                           class="absolute left-4 top-1/2
                                  -translate-y-1/2
                                  w-5 h-5 text-gray-400"></i>

                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            class="input-field w-full
                                   border border-gray-300
                                   rounded-xl py-3.5 pl-12 pr-12
                                   text-gray-800 transition"
                        >

                        <button
                            type="button"
                            onclick="togglePassword()"
                            class="absolute right-4 top-1/2
                                   -translate-y-1/2
                                   text-gray-400 hover:text-blue-600"
                            aria-label="Toggle password visibility">

                            <i data-lucide="eye"
                               id="eyeIcon"
                               class="w-5 h-5"></i>

                        </button>

                    </div>

                </div>

                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="login-button w-full text-white
                           font-semibold py-3.5 rounded-xl
                           shadow-lg transition
                           flex items-center justify-center gap-2">

                    <i data-lucide="log-in"
                       class="w-5 h-5"></i>

                    Sign In

                </button>

            </form>

            <!-- FOOTER -->

            <div class="mt-7 pt-6 border-t
                        border-gray-100 text-center">

                <p class="text-xs text-gray-500">

                    Secure Resident Access

                </p>

                <p class="text-xs text-gray-400 mt-2">

                    © <?= date("Y") ?> Barangay Guindacpan

                </p>

            </div>

        </div>

    </div>

</div>

<script>

lucide.createIcons();

function togglePassword() {

    const password = document.getElementById("password");
    const eyeIcon = document.getElementById("eyeIcon");

    if (password.type === "password") {

        password.type = "text";
        eyeIcon.setAttribute("data-lucide", "eye-off");

    } else {

        password.type = "password";
        eyeIcon.setAttribute("data-lucide", "eye");

    }

    lucide.createIcons();

}

</script>

</body>
</html>
