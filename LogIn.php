
<?php
// ==========================================================
// BARANGAY GUINDACPAN
// RESIDENT LOGIN SYSTEM
//
// Database: barangay_db
// Table: residents
// Connection: db.php
// PDO Variable: $pdo
//
// APPROVED  -> resident/dashboard.php
// PENDING   -> track/tracking.php
// REJECTED  -> Login denied
// ==========================================================


// ==========================================================
// 1. SESSION SECURITY
// ==========================================================

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();
}


// ==========================================================
// 2. DATABASE CONNECTION
// ==========================================================

require_once __DIR__ . '/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {

    error_log('[RESIDENT LOGIN] PDO connection unavailable.');

    http_response_code(500);

    exit('Database connection error. Please contact the administrator.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);


// ==========================================================
// 3. VARIABLES
// ==========================================================

$error = '';
$username = '';


// ==========================================================
// 4. REDIRECT IF ALREADY LOGGED IN
// ==========================================================

if (!empty($_SESSION['resident_id'])) {

    $currentStatus = strtolower(trim($_SESSION['resident_status'] ?? ''));

    if ($currentStatus === 'approved') {

        header('Location: resident/dashboard.php');
        exit;

    } elseif ($currentStatus === 'pending') {

        header('Location: track/tracking.php');
        exit;

    } else {

        // Clear invalid or inactive session.
        unset(
            $_SESSION['resident_id'],
            $_SESSION['resident_username'],
            $_SESSION['resident_name'],
            $_SESSION['resident_status'],
            $_SESSION['resident_login_time']
        );
    }
}


// ==========================================================
// 5. PROCESS LOGIN
// ==========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter your username and password.';

    } elseif (strlen($username) > 100 || strlen($password) > 4096) {

        $error = 'Invalid username or password.';

    } else {

        try {

            // FIND RESIDENT ACCOUNT

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
                ':username' => $username
            ]);

            $resident = $stmt->fetch();


            // VERIFY PASSWORD

            if (
                $resident &&
                isset($resident['password']) &&
                password_verify($password, $resident['password'])
            ) {

                $accountStatus = strtolower(
                    trim((string) $resident['status'])
                );


                // CHECK ACCOUNT STATUS

                if ($accountStatus === 'rejected' ||
                    $accountStatus === 'inactive' ||
                    $accountStatus === 'disabled') {

                    $error = 'Your account is not active. Please contact the barangay administrator.';

                } elseif (
                    $accountStatus !== 'approved' &&
                    $accountStatus !== 'pending'
                ) {

                    $error = 'Your account status is currently unavailable. Please contact the barangay administrator.';

                } else {

                    // REGENERATE SESSION ID

                    session_regenerate_id(true);


                    // STORE RESIDENT SESSION

                    $_SESSION['resident_id'] = (int) $resident['id'];

                    $_SESSION['resident_username'] = $resident['username'];

                    $_SESSION['resident_name'] = $resident['full_name'];

                    $_SESSION['resident_status'] = $accountStatus;

                    $_SESSION['resident_login_time'] = time();


                    // REDIRECT BASED ON ACCOUNT STATUS

                    if ($accountStatus === 'pending') {

                        header('Location: track/tracking.php');
                        exit;

                    }

                    if ($accountStatus === 'approved') {

                        header('Location: resident/dashboard.php');
                        exit;

                    }

                }

            } else {

                $error = 'Incorrect username or password. Please try again.';

            }

        } catch (PDOException $e) {

            error_log(
                '[RESIDENT LOGIN PDO ERROR] ' .
                $e->getMessage()
            );

            $error = 'Unable to process login. Please try again later.';

        } catch (Throwable $e) {

            error_log(
                '[RESIDENT LOGIN SYSTEM ERROR] ' .
                $e->getMessage()
            );

            $error = 'A system error occurred. Please try again later.';

        }

    }

}


// ==========================================================
// 6. HTML ESCAPE FUNCTION
// ==========================================================

function loginEscape($value): string {

    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Resident Login | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
        }

        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #dbe3ea;
            border-radius: 12px;
            outline: none;
            background: #fff;
            color: #1e293b;
            transition: .2s;
        }

        .form-input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21,128,61,.12);
        }

        .login-button {
            transition: all .2s ease;
        }

        .login-button:hover {
            transform: translateY(-1px);
        }

    </style>

</head>


<body class="min-h-screen bg-slate-100 flex items-center justify-center p-5">


    <main class="w-full max-w-md">


        <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden">


            <!-- HEADER -->

            <div class="bg-green-950 px-8 pt-9 pb-8 text-center">

                <div class="inline-flex items-center justify-center bg-black rounded-2xl p-3 mb-5">

                    <img src="asset/logo.png"
                         alt="Barangay Guindacpan Logo"
                         class="h-24 w-auto object-contain">

                </div>


                <h1 class="text-2xl font-extrabold text-white tracking-wide">

                    BARANGAY GUINDACPAN

                </h1>


                <p class="text-green-200 text-sm mt-2">

                    Talibon, Bohol • Philippines

                </p>


                <div class="mt-5">

                    <span class="inline-flex items-center gap-2 text-xs font-bold bg-white/10 text-green-100 px-4 py-2 rounded-full">

                        <i class="fas fa-user-shield"></i>

                        RESIDENT PORTAL

                    </span>

                </div>

            </div>


            <!-- FORM SECTION -->

            <div class="p-7 md:p-8">


                <div class="mb-7">

                    <h2 class="text-2xl font-extrabold text-slate-900">

                        Welcome Back!

                    </h2>


                    <p class="text-sm text-slate-500 mt-2">

                        Sign in to access your resident account.

                    </p>

                </div>


                <!-- ERROR MESSAGE -->

                <?php if ($error !== ''): ?>

                    <div class="mb-5 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 rounded-xl p-4">

                        <i class="fas fa-circle-exclamation mt-1"></i>

                        <p class="text-sm font-medium">

                            <?= loginEscape($error) ?>

                        </p>

                    </div>

                <?php endif; ?>


                <!-- LOGIN FORM -->

                <form method="POST"
                      action="LogIn.php"
                      class="space-y-5">


                    <!-- USERNAME -->

                    <div>

                        <label for="username"
                               class="block text-sm font-bold text-slate-700 mb-2">

                            Username

                        </label>


                        <div class="relative">

                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">

                                <i class="fas fa-user"></i>

                            </span>


                            <input
                                type="text"
                                name="username"
                                id="username"
                                value="<?= loginEscape($username) ?>"
                                placeholder="Enter your username"
                                maxlength="100"
                                autocomplete="username"
                                required
                                class="form-input pl-11"
                            >

                        </div>

                    </div>


                    <!-- PASSWORD -->

                    <div>

                        <label for="password"
                               class="block text-sm font-bold text-slate-700 mb-2">

                            Password

                        </label>


                        <div class="relative">

                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">

                                <i class="fas fa-lock"></i>

                            </span>


                            <input
                                type="password"
                                name="password"
                                id="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                required
                                class="form-input pl-11 pr-12"
                            >


                            <button
                                type="button"
                                onclick="togglePassword()"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-green-800"
                                aria-label="Show or hide password"
                            >

                                <i class="fas fa-eye" id="eyeIcon"></i>

                            </button>

                        </div>

                    </div>


                    <!-- LOGIN BUTTON -->

                    <button
                        type="submit"
                        class="login-button w-full bg-green-800 hover:bg-green-900 text-white font-bold py-4 rounded-xl shadow-lg transition duration-200"
                    >

                        <i class="fas fa-right-to-bracket mr-2"></i>

                        Sign In

                    </button>


                </form>


                <!-- REGISTER LINK -->

                <div class="text-center mt-7">

                    <p class="text-sm text-slate-500">

                        Don't have a resident account?

                        <a href="main.php#residentLogin"
                           class="font-bold text-green-800 hover:text-green-950">

                            Create Account

                        </a>

                    </p>

                </div>


                <!-- HOME LINK -->

                <div class="text-center mt-5">

                    <a href="main.php"
                       class="text-sm text-slate-400 hover:text-green-800">

                        <i class="fas fa-arrow-left mr-1"></i>

                        Back to Homepage

                    </a>

                </div>


            </div>

        </div>


        <!-- FOOTER -->

        <p class="text-center text-xs text-slate-400 mt-7">

            © <?= date('Y') ?> Barangay Guindacpan.

            All Rights Reserved.

        </p>


    </main>


    <!-- PASSWORD TOGGLE -->

    <script>

        function togglePassword() {

            const password = document.getElementById('password');

            const eyeIcon = document.getElementById('eyeIcon');

            if (password.type === 'password') {

                password.type = 'text';

                eyeIcon.classList.remove('fa-eye');

                eyeIcon.classList.add('fa-eye-slash');

            } else {

                password.type = 'password';

                eyeIcon.classList.remove('fa-eye-slash');

                eyeIcon.classList.add('fa-eye');

            }

        }

    </script>


</body>

</html>
