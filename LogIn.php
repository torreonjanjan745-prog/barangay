
<?php
// =============================================
// BARANGAY GUINDACPAN
// RESIDENT REGISTRATION - PDO VERSION
// Database: barangay_db
// =============================================

require_once __DIR__ . "/db.php";

$message = "";
$success = false;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("PDO connection missing in registration.php");
    http_response_code(500);
    exit("Database connection error. Please contact the administrator.");
}

// Only process registration submissions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["register"])) {

    $fullName = trim($_POST["fullName"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $birthday = trim($_POST["birthday"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    if (
        $fullName === "" ||
        $username === "" ||
        $address === "" ||
        $birthday === "" ||
        $password === "" ||
        $confirm === ""
    ) {
        $message = "Please complete all fields.";

    } elseif (!hash_equals($password, $confirm)) {
        $message = "Password and Confirm Password do not match.";

    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";

    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) ||
        !checkdate(
            (int) substr($birthday, 5, 2),
            (int) substr($birthday, 8, 2),
            (int) substr($birthday, 0, 4)
        )
    ) {
        $message = "Please enter a valid birthday.";

    } else {
        try {
            // Check duplicate username
            $check = $pdo->prepare(
                "SELECT id FROM residents
                 WHERE username = :username
                 LIMIT 1"
            );

            $check->execute([
                ":username" => $username
            ]);

            if ($check->fetch()) {
                $message = "Username already exists. Please choose another.";

            } else {
                // Hash password
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    $message = "Unable to secure your password.";

                } else {
                    // Insert resident account
                    $stmt = $pdo->prepare(
                        "INSERT INTO residents
                        (
                            full_name,
                            username,
                            purok_address,
                            birthday,
                            password
                        )
                        VALUES
                        (
                            :full_name,
                            :username,
                            :address,
                            :birthday,
                            :password
                        )"
                    );

                    $stmt->execute([
                        ":full_name" => $fullName,
                        ":username" => $username,
                        ":address" => $address,
                        ":birthday" => $birthday,
                        ":password" => $hashedPassword
                    ]);

                    $success = true;
                    $message = "Registration successful! Your account is pending approval.";
                }
            }

        } catch (PDOException $e) {
            error_log("Resident registration error: " . $e->getMessage());

            if ($e->getCode() === "23000") {
                $message = "Username already exists. Please choose another.";
            } else {
                $message = "Registration failed. Please try again.";
            }
        }
    }

} else {
    http_response_code(405);
    $message = "Please submit the registration form from the homepage.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resident Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-5">

    <main class="w-full max-w-lg rounded-2xl bg-white p-8 shadow-lg">

        <h1 class="text-2xl font-bold text-slate-900">
            <?= $success ? "Account Created" : "Registration Notice"; ?>
        </h1>

        <p class="mt-4 text-slate-700">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </p>

        <div class="mt-6 flex flex-wrap gap-3">

            <?php if ($success): ?>

                <a href="resident/login.php"
                   class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">
                    Go to Resident Login
                </a>

            <?php else: ?>

                <a href="main.php#residentLogin"
                   class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white hover:bg-blue-700">
                    Back to Create Account
                </a>

            <?php endif; ?>

            <a href="main.php"
               class="rounded-lg border border-slate-300 px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50">
                Home
            </a>

        </div>
    </main>

</body>
</html>
