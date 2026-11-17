
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// Prevent cached login page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // CLEAR ANY PREVIOUS RESIDENT LOGIN SESSION
    unset($_SESSION["resident_id"]);
    unset($_SESSION["resident_username"]);

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {

        $error = "Please enter your username and password.";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "SELECT id, username, password FROM residents WHERE username = ? LIMIT 1"
        );

        if ($stmt) {

            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);
            $resident = mysqli_fetch_assoc($result);

            if (
                $resident &&
                password_verify($password, $resident["password"])
            ) {

                // CREATE A NEW SESSION AFTER VALID LOGIN
                session_regenerate_id(true);

                $_SESSION["resident_id"] = $resident["id"];
                $_SESSION["resident_username"] = $resident["username"];

                header("Location: dashboard.php");
                exit();

            } else {

                $error = "Invalid username or password.";

            }

            mysqli_stmt_close($stmt);

        } else {

            $error = "Unable to process login. Please try again.";
            error_log("Login query error: " . mysqli_error($conn));

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Login | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-black flex items-center justify-center p-5">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-8">

        <div class="text-center mb-8">

            <h1 class="text-2xl font-bold text-green-900">
                BARANGAY GUINDACPAN
            </h1>

            <p class="text-gray-500 mt-2">
                Resident Portal Login
            </p>

        </div>

        <?php if ($error !== ""): ?>

            <div class="bg-red-100 text-red-700 p-3 rounded-lg mb-5 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>

        <form method="POST" action="" id="loginForm" class="space-y-5">

            <div>

                <label class="block text-gray-700 font-semibold mb-2">
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    required
                    autocomplete="username"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-700"
                    placeholder="Enter your username"
                >

            </div>

            <div>

                <label class="block text-gray-700 font-semibold mb-2">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-700"
                    placeholder="Enter your password"
                >

            </div>

            <button
                type="submit"
                id="loginButton"
                class="w-full bg-green-900 hover:bg-green-800 text-white font-semibold py-3 rounded-lg transition">

                LOGIN

            </button>

        </form>

        <p class="text-center text-sm text-gray-600 mt-6">
            Don't have an account?

            <a href="../main.php" class="text-green-800 font-semibold hover:underline">
                Register here
            </a>
        </p>

        <div class="text-center mt-5">

            <a href="../main.php" class="text-sm text-gray-500 hover:underline">
                Back to Homepage
            </a>

        </div>

    </div>

</body>
 <script src="logIn.js"></script>
</html>
