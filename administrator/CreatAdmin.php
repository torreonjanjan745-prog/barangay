
<?php
// =============================================
// CREATE FIRST ADMIN ACCOUNT
// =============================================

session_name("BARANGAY_ADMIN_SETUP");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . "/../db.php";

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    exit("Database connection error.");
}

$conn->set_charset("utf8mb4");

// CHECK IF ADMIN ALREADY EXISTS
$countSql = "SELECT COUNT(*) FROM admin_account";
$countResult = mysqli_query($conn, $countSql);

if (!$countResult) {
    error_log("Admin Setup Error: " . mysqli_error($conn));
    exit("Unable to check administrator account.");
}

$countRow = mysqli_fetch_row($countResult);
$adminExists = (int)$countRow[0] > 0;

$message = "";
$messageType = "error";

$fullName = "";
$email = "";
$contact = "";

// CSRF TOKEN
if (empty($_SESSION["admin_setup_csrf"])) {
    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));
}

// HANDLE FORM
if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $token = $_POST["csrf_token"] ?? "";

    $nameInput = $_POST["full_name"] ?? "";
    $emailInput = $_POST["email"] ?? "";
    $contactInput = $_POST["contact_number"] ?? "";
    $passwordInput = $_POST["password"] ?? "";
    $confirmInput = $_POST["confirm_password"] ?? "";

    $fullName = is_string($nameInput) ? trim($nameInput) : "";
    $email = is_string($emailInput) ? trim($emailInput) : "";
    $contact = is_string($contactInput) ? trim($contactInput) : "";

    $password = is_string($passwordInput) ? $passwordInput : "";
    $confirmPassword = is_string($confirmInput) ? $confirmInput : "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["admin_setup_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";

    } elseif (
        $fullName === "" ||
        $email === "" ||
        $contact === "" ||
        $password === "" ||
        $confirmPassword === ""
    ) {
        $message = "Please complete all fields.";

    } elseif (strlen($fullName) > 150) {
        $message = "Full name must not exceed 150 characters.";

    } elseif (
        strlen($email) > 150 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $message = "Please enter a valid email address.";

    } elseif (
        strlen($contact) > 20 ||
        !preg_match('/^[0-9+\-\s()]+$/', $contact)
    ) {
        $message = "Please enter a valid contact number.";

    } elseif (strlen($password) < 12) {
        $message = "Administrator password must contain at least 12 characters.";

    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";

    } else {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        if ($hashedPassword === false) {

            $message = "Unable to secure your password.";

        } else {

            $sql = "
                INSERT INTO admin_account
                (
                    full_name,
                    email,
                    password,
                    contact_number,
                    position,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {

                error_log("Admin Insert Prepare Error: " . mysqli_error($conn));
                $message = "Unable to create administrator account.";

            } else {

                $position = "Administrator";
                $status = "Active";

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssss",
                    $fullName,
                    $email,
                    $hashedPassword,
                    $contact,
                    $position,
                    $status
                );

                if (mysqli_stmt_execute($stmt)) {

                    $message = "Administrator account created successfully! You can now log in.";
                    $messageType = "success";

                    $fullName = "";
                    $email = "";
                    $contact = "";

                    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));

                    $adminExists = true;

                } else {

                    error_log("Admin Insert Error: " . mysqli_stmt_error($stmt));

                    if (mysqli_stmt_errno($stmt) === 1062) {
                        $message = "This email address is already registered.";
                    } else {
                        $message = "Unable to create administrator account. Please check the database.";
                    }
                }

                mysqli_stmt_close($stmt);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Administrator Account</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

<div class="w-full max-w-lg bg-white rounded-2xl shadow-xl p-6 sm:p-9">

    <div class="text-center mb-7">
        <div class="w-16 h-16 bg-blue-100 text-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-4 text-3xl">
            🛡️
        </div>

        <h1 class="text-2xl font-bold text-gray-900">
            Create Administrator
        </h1>

        <p class="text-sm text-gray-500 mt-2">
            Set up the first administrator account.
        </p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="mb-5 p-4 rounded-xl text-sm
            <?php echo $messageType === "success"
                ? "bg-green-50 text-green-700 border border-green-200"
                : "bg-red-50 text-red-700 border border-red-200"; ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <?php if ($adminExists): ?>

        <div class="bg-blue-50 border border-blue-200 text-blue-800 p-5 rounded-xl text-center">
            <h2 class="font-bold mb-2">Administrator Already Exists</h2>
            <p class="text-sm mb-4">
                The first administrator account has already been created.
            </p>

            <a href="adminlogIn.php"
               class="inline-block bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-semibold">
                Go to Admin Login
            </a>
        </div>

    <?php else: ?>

        <form method="POST" action="" class="space-y-4">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION["admin_setup_csrf"], ENT_QUOTES, "UTF-8"); ?>"
            >

            <div>
                <label class="block text-sm font-semibold mb-2">Full Name</label>
                <input
                    type="text"
                    name="full_name"
                    maxlength="150"
                    value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, "UTF-8"); ?>"
                    placeholder="Administrator full name"
                    class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Email Address</label>
                <input
                    type="email"
                    name="email"
                    maxlength="150"
                    value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>"
                    placeholder="admin@example.com"
                    class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Contact Number</label>
                <input
                    type="tel"
                    name="contact_number"
                    maxlength="20"
                    value="<?php echo htmlspecialchars($contact, ENT_QUOTES, "UTF-8"); ?>"
                    placeholder="Contact number"
                    class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Password</label>
                <input
                    type="password"
                    name="password"
                    minlength="12"
                    placeholder="At least 12 characters"
                    class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-semibold mb-2">Confirm Password</label>
                <input
                    type="password"
                    name="confirm_password"
                    minlength="12"
                    placeholder="Confirm your password"
                    class="w-full border rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"
                    required
                >
            </div>

            <button
                type="submit"
                class="w-full bg-blue-700 hover:bg-blue-800 text-white font-semibold py-3 rounded-xl transition"
            >
                Create Administrator Account
            </button>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
