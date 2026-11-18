<?php
// =============================================
// DATABASE CONNECTION
// =============================================

require_once __DIR__ . "/db.php";

$message = "";
$success = false;

// Make sure db.php created a valid MySQLi connection.
if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("Database connection is missing or invalid in db.php.");
    http_response_code(500);
    exit("Database connection error. Please contact the administrator.");
}

// Only process the registration form.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["register"])) {

    // =============================================
    // GET FORM DATA
    // =============================================

    $fullName = trim($_POST["fullName"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $birthday = trim($_POST["birthday"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm = $_POST["confirm"] ?? "";

    // =============================================
    // VALIDATE REQUIRED FIELDS
    // =============================================

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

        // =============================================
        // CHECK IF USERNAME ALREADY EXISTS
        // =============================================

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM residents WHERE username = ? LIMIT 1"
        );

        if (!$check) {
            error_log("Username check prepare failed: " . mysqli_error($conn));
            $message = "Something went wrong. Please try again.";
        } else {
            mysqli_stmt_bind_param($check, "s", $username);

            if (!mysqli_stmt_execute($check)) {
                error_log("Username check failed: " . mysqli_stmt_error($check));
                $message = "Something went wrong. Please try again.";
            } else {
                mysqli_stmt_store_result($check);

                if (mysqli_stmt_num_rows($check) > 0) {
                    $message = "Username already exists. Please choose another.";

                } else {

                    // =============================================
                    // HASH PASSWORD
                    // =============================================

                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    if ($hashedPassword === false) {
                        $message = "Unable to secure your password. Please try again.";

                    } else {

                        // =============================================
                        // INSERT RESIDENT ACCOUNT
                        // =============================================

                        $stmt = mysqli_prepare(
                            $conn,
                            "INSERT INTO residents
                            (full_name, username, purok_address, birthday, password)
                            VALUES (?, ?, ?, ?, ?)"
                        );

                        if (!$stmt) {
                            error_log("Registration prepare failed: " . mysqli_error($conn));
                            $message = "Unable to create your account. Please try again.";
                        } else {
                            mysqli_stmt_bind_param(
                                $stmt,
                                "sssss",
                                $fullName,
                                $username,
                                $address,
                                $birthday,
                                $hashedPassword
                            );

                            if (mysqli_stmt_execute($stmt)) {
                                $success = true;
                                $message = "Registration successful! Your account is pending approval.";
                            } else {
                                error_log("Registration insert failed: " . mysqli_stmt_error($stmt));

                                // Handles a duplicate username if the database has a UNIQUE index.
                                if (mysqli_stmt_errno($stmt) === 1062) {
                                    $message = "Username already exists. Please choose another.";
                                } else {
                                    $message = "Registration failed. Please check your information and try again.";
                                }
                            }

                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }

            mysqli_stmt_close($check);
        }
    }
} else {
    // Do not allow someone to open this handler without submitting the form.
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
            <?php echo $success ? "Account Created" : "Registration Notice"; ?>
        </h1>

        <p class="mt-4 text-slate-700">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
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
