
<?php
// =============================================
// BHW PROFILE MANAGEMENT
// =============================================

session_name("BARANGAY_BHW_SESSION");
session_start();

if (!isset($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../db.php";

$bhwId = (int) $_SESSION["bhw_id"];

if ($bhwId <= 0) {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}
$message = "";
$messageType = "";

// =============================================
// FETCH BHW PROFILE
// =============================================

$stmt = $pdo->prepare("
    SELECT id, full_name, email, contact_number,
           position, status, created_at, password
    FROM bhw_accounts
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([":id" => $bhwId]);

$bhw = $stmt->fetch();

if (!$bhw || $bhw["status"] !== "Active") {
    $_SESSION = [];
    session_destroy();
    header("Location: login.php");
    exit;
}

// =============================================
// UPDATE PROFILE
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_profile"])) {

    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contact = trim($_POST["contact_number"] ?? "");

    if ($fullName === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please provide a valid name and email.";
        $messageType = "error";

    } else {

        try {

            $update = $pdo->prepare("
                UPDATE bhw_accounts
                SET full_name = :name,
                    email = :email,
                    contact_number = :contact
                WHERE id = :id
            ");

            $update->execute([
                ":name" => $fullName,
                ":email" => $email,
                ":contact" => $contact,
                ":id" => $bhwId
            ]);

            $_SESSION["bhw_name"] = $fullName;

            header("Location: profile.php?updated=1");
            exit;

        } catch (PDOException $e) {

            if ($e->getCode() === "23000") {
                $message = "This email address is already registered.";
            } else {
                error_log($e->getMessage());
                $message = "Unable to update profile.";
            }

            $messageType = "error";
        }
    }
}

// =============================================
// CHANGE PASSWORD
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["change_password"])) {

    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (!password_verify($currentPassword, $bhw["password"])) {

        $message = "Your current password is incorrect.";
        $messageType = "error";

    } elseif (strlen($newPassword) < 8) {

        $message = "New password must be at least 8 characters.";
        $messageType = "error";

    } elseif ($newPassword !== $confirmPassword) {

        $message = "New passwords do not match.";
        $messageType = "error";

    } else {

        try {

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $updatePassword = $pdo->prepare("
                UPDATE bhw_accounts
                SET password = :password
                WHERE id = :id
            ");

            $updatePassword->execute([
                ":password" => $hashedPassword,
                ":id" => $bhwId
            ]);

            header("Location: profile.php?password_changed=1");
            exit;

        } catch (PDOException $e) {

            error_log($e->getMessage());
            $message = "Unable to change password. Please try again.";
            $messageType = "error";
        }
    }
}

// =============================================
// REFRESH PROFILE DATA
// =============================================

$stmt = $pdo->prepare("
    SELECT id, full_name, email, contact_number,
           position, status, created_at
    FROM bhw_accounts
    WHERE id = :id
");

$stmt->execute([":id" => $bhwId]);

$bhw = $stmt->fetch();

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BHW Profile</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-slate-100 min-h-screen">

<div class="flex min-h-screen">

<!-- SIDEBAR -->

<aside class="w-64 bg-slate-900 text-white p-6 hidden md:block">

    <h1 class="text-2xl font-bold mb-10">
        BHW Portal
    </h1>

    <nav class="space-y-3">

        <a href="dashboard.php"
        class="block p-3 rounded-lg hover:bg-slate-700">
            Dashboard
        </a>

        <a href="residents.php"
        class="block p-3 rounded-lg hover:bg-slate-700">
            Patients
        </a>

        <a href="appointments.php"
        class="block p-3 rounded-lg hover:bg-slate-700">
            Appointments
        </a>

        <a href="health_records.php"
        class="block p-3 rounded-lg hover:bg-slate-700">
            Health Records
        </a>

        <a href="reports.php"
        class="block p-3 rounded-lg hover:bg-slate-700">
            Reports
        </a>

        <a href="profile.php"
        class="block p-3 rounded-lg bg-blue-600">
            My Profile
        </a>

        <a href="logout.php"
        class="block p-3 rounded-lg text-red-400 hover:bg-slate-700">
            Logout
        </a>

    </nav>

</aside>


<!-- MAIN CONTENT -->

<main class="flex-1 p-6 md:p-10">

    <div class="mb-8">

        <h1 class="text-3xl font-bold text-slate-800">
            My Profile
        </h1>

        <p class="text-slate-500 mt-2">
            Manage your BHW account information.
        </p>

    </div>


    <!-- SUCCESS MESSAGES -->

    <?php if (isset($_GET["updated"])): ?>

        <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6">
            Profile updated successfully!
        </div>

    <?php endif; ?>

    <?php if (isset($_GET["password_changed"])): ?>

        <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6">
            Password changed successfully!
        </div>

    <?php endif; ?>

    <?php if ($message): ?>

        <div class="<?= $messageType === "error"
            ? "bg-red-100 text-red-700"
            : "bg-green-100 text-green-700" ?> p-4 rounded-xl mb-6">

            <?= e($message) ?>

        </div>

    <?php endif; ?>


    <!-- PROFILE CARD -->

    <div class="bg-white rounded-2xl shadow-sm p-8 mb-8">

        <div class="flex flex-wrap items-center gap-5">

            <div class="w-20 h-20 rounded-full bg-blue-100
                        flex items-center justify-center
                        text-3xl font-bold text-blue-600">

                <?= strtoupper(substr((string) ($bhw["full_name"] ?? ""), 0, 1)) ?>

            </div>

            <div>

                <h2 class="text-2xl font-bold text-slate-800">
                    <?= e($bhw["full_name"]) ?>
                </h2>

                <p class="text-slate-500">
                    <?= e($bhw["position"]) ?>
                </p>

                <span class="inline-block mt-2 px-3 py-1
                    rounded-full text-xs font-semibold
                    bg-green-100 text-green-700">

                    <?= e($bhw["status"]) ?>

                </span>

            </div>

        </div>

        <hr class="my-6">

        <div class="grid md:grid-cols-2 gap-6">

            <div>
                <p class="text-sm text-slate-500">Email Address</p>
                <p class="font-semibold mt-1">
                    <?= e($bhw["email"]) ?>
                </p>
            </div>

            <div>
                <p class="text-sm text-slate-500">Contact Number</p>
                <p class="font-semibold mt-1">
                    <?= e($bhw["contact_number"] ?: "Not provided") ?>
                </p>
            </div>

            <div>
                <p class="text-sm text-slate-500">Account ID</p>
                <p class="font-semibold mt-1">
                    <?= e($bhw["id"]) ?>
                </p>
            </div>

            <div>
                <p class="text-sm text-slate-500">Date Registered</p>
                <p class="font-semibold mt-1">
                    <?= date("F d, Y", strtotime($bhw["created_at"])) ?>
                </p>
            </div>

        </div>

    </div>


    <!-- EDIT PROFILE -->

    <div class="bg-white rounded-2xl shadow-sm p-8 mb-8">

        <h2 class="text-xl font-bold text-slate-800 mb-6">
            Edit Profile Information
        </h2>

        <form method="POST" class="space-y-5">

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Full Name
                </label>

                <input type="text"
                name="full_name"
                value="<?= e($bhw["full_name"]) ?>"
                required
                maxlength="150"
                class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Email Address
                </label>

                <input type="email"
                name="email"
                value="<?= e($bhw["email"]) ?>"
                required
                maxlength="150"
                class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Contact Number
                </label>

                <input type="text"
                name="contact_number"
                value="<?= e($bhw["contact_number"]) ?>"
                maxlength="20"
                class="w-full border border-slate-300 rounded-xl p-3 focus:ring-2 focus:ring-blue-500 outline-none">

            </div>

            <button type="submit"
            name="update_profile"
            class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 font-semibold">

                Save Changes

            </button>

        </form>

    </div>


    <!-- CHANGE PASSWORD -->

    <div class="bg-white rounded-2xl shadow-sm p-8">

        <h2 class="text-xl font-bold text-slate-800 mb-6">
            Change Password
        </h2>

        <form method="POST" class="space-y-5">

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Current Password
                </label>

                <input type="password"
                name="current_password"
                required
                autocomplete="current-password"
                class="w-full border border-slate-300 rounded-xl p-3">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    New Password
                </label>

                <input type="password"
                name="new_password"
                minlength="8"
                required
                autocomplete="new-password"
                class="w-full border border-slate-300 rounded-xl p-3">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Confirm New Password
                </label>

                <input type="password"
                name="confirm_password"
                minlength="8"
                required
                autocomplete="new-password"
                class="w-full border border-slate-300 rounded-xl p-3">

            </div>

            <button type="submit"
            name="change_password"
            class="bg-slate-900 text-white px-6 py-3 rounded-xl hover:bg-slate-800 font-semibold">

                Update Password

            </button>

        </form>

    </div>

</main>

</div>

</body>
<script src="bhw.js"></script>
</html>
