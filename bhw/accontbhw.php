<?php
session_name("BARANGAY_BHW_SESSION");
session_start();

if (!isset($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../db.php";

$bhwId = (int) $_SESSION["bhw_id"];
$success = "";
$error = "";

// Get BHW account
$stmt = $pdo->prepare("
    SELECT id, full_name, email, contact_number, position, status, created_at
    FROM bhw_account
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$bhwId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    die("BHW account not found.");
}

// Update profile
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_profile"])) {
    $fullName = trim($_POST["full_name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $contactNumber = trim($_POST["contact_number"] ?? "");

    if ($fullName === "" || $email === "" || $contactNumber === "") {
        $error = "Please complete all profile fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            $check = $pdo->prepare("
                SELECT id FROM bhw_account
                WHERE email = ? AND id != ?
                LIMIT 1
            ");
            $check->execute([$email, $bhwId]);

            if ($check->fetch()) {
                $error = "Email address is already being used.";
            } else {
                $update = $pdo->prepare("
                    UPDATE bhw_account
                    SET full_name = ?, email = ?, contact_number = ?
                    WHERE id = ?
                ");
                $update->execute([
                    $fullName,
                    $email,
                    $contactNumber,
                    $bhwId
                ]);

                $success = "Profile updated successfully.";

                $stmt->execute([$bhwId]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                $_SESSION["bhw_name"] = $account["full_name"];
                $_SESSION["bhw_email"] = $account["email"];
            }
        } catch (PDOException $e) {
            $error = "Unable to update your profile.";
        }
    }
}

// Change password
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["change_password"])) {
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    $passwordStmt = $pdo->prepare("
        SELECT password FROM bhw_account WHERE id = ? LIMIT 1
    ");
    $passwordStmt->execute([$bhwId]);
    $storedPassword = $passwordStmt->fetchColumn();

    if (!$storedPassword || !password_verify($currentPassword, $storedPassword)) {
        $error = "Your current password is incorrect.";
    } elseif (strlen($newPassword) < 8) {
        $error = "New password must be at least 8 characters.";
    } elseif ($newPassword !== $confirmPassword) {
        $error = "New passwords do not match.";
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $updatePassword = $pdo->prepare("
            UPDATE bhw_account SET password = ? WHERE id = ?
        ");
        $updatePassword->execute([$hashedPassword, $bhwId]);

        $success = "Password changed successfully.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BHW Account | Barangay Health System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="min-h-screen bg-slate-100 text-slate-800">

<div class="flex min-h-screen">

    <!-- Mobile overlay -->
    <div id="overlay" class="fixed inset-0 z-40 hidden bg-slate-950/60 md:hidden"></div>

    <!-- Sidebar -->
    <aside
        id="sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full overflow-y-auto bg-slate-900 p-6 text-white transition-transform duration-300 md:static md:translate-x-0 md:flex-shrink-0"
    >
        <div class="mb-10 flex items-center justify-between">
            <h1 class="text-xl font-bold">BHW Portal</h1>
            <button
                type="button"
                id="closeMenuBtn"
                class="rounded-lg p-2 text-slate-300 hover:bg-slate-800 md:hidden"
                aria-label="Close menu"
            >
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <nav class="space-y-2 text-sm">
            <a href="dashboard.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Dashboard</a>
            <a href="patients.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Patients</a>
            <a href="appointments.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Appointments</a>
            <a href="health_records.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Health Records</a>
            <a href="immunization.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Immunization</a>
            <a href="prenatal.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Prenatal</a>
            <a href="activity_logs.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Activity Logs</a>
            <a href="reports.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Reports</a>
            <a href="notifications.php" class="block rounded-lg px-4 py-3 hover:bg-slate-800">Notifications</a>
            <a href="account.php" class="block rounded-lg bg-blue-600 px-4 py-3 font-semibold">My Account</a>
            <a href="logout.php" class="block rounded-lg px-4 py-3 text-red-300 hover:bg-red-500/10">Logout</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="min-w-0 flex-1 p-5 md:p-10">

        <!-- Mobile top bar -->
        <div class="mb-6 flex items-center gap-3 md:hidden">
            <button
                type="button"
                id="menuBtn"
                class="rounded-xl bg-white p-3 shadow-sm hover:bg-slate-50"
                aria-label="Open menu"
            >
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <span class="font-semibold text-slate-900">BHW Portal</span>
        </div>

        <div class="mb-8">
            <p class="text-sm font-medium text-blue-600">ACCOUNT MANAGEMENT</p>
            <h2 class="mt-1 text-3xl font-bold text-slate-900">My Account</h2>
            <p class="mt-2 text-slate-500">Manage your personal information and account security.</p>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-700">
                <?= htmlspecialchars($success, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>
        <?php endif; ?>

        <!-- Account Overview -->
        <section class="mb-8 rounded-2xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-blue-100 text-2xl font-bold text-blue-700">
                    <?= htmlspecialchars(strtoupper(substr($account["full_name"], 0, 1)), ENT_QUOTES, "UTF-8") ?>
                </div>

                <div class="min-w-0 flex-1">
                    <h3 class="text-xl font-bold">
                        <?= htmlspecialchars($account["full_name"], ENT_QUOTES, "UTF-8") ?>
                    </h3>
                    <p class="text-sm text-slate-500">
                        <?= htmlspecialchars($account["position"], ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>

                <span class="rounded-full px-4 py-2 text-sm font-semibold <?= $account["status"] === "Active" ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700" ?>">
                    <?= htmlspecialchars($account["status"], ENT_QUOTES, "UTF-8") ?>
                </span>
            </div>

            <div class="mt-6 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">
                <div>
                    <p class="text-xs text-slate-400">ACCOUNT ID</p>
                    <p class="mt-1 font-semibold">#<?= (int) $account["id"] ?></p>
                </div>
                <div>
                    <p class="text-xs text-slate-400">DATE CREATED</p>
                    <p class="mt-1 font-semibold">
                        <?= htmlspecialchars($account["created_at"], ENT_QUOTES, "UTF-8") ?>
                    </p>
                </div>
            </div>
        </section>

        <!-- Edit Profile -->
        <section class="mb-8 rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold">Personal Information</h3>
            <p class="mb-6 mt-1 text-sm text-slate-500">Update your account details.</p>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold" for="full_name">Full Name</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        required
                        value="<?= htmlspecialchars($account["full_name"], ENT_QUOTES, "UTF-8") ?>"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold" for="email">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        value="<?= htmlspecialchars($account["email"], ENT_QUOTES, "UTF-8") ?>"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold" for="contact_number">Contact Number</label>
                    <input
                        type="text"
                        id="contact_number"
                        name="contact_number"
                        required
                        value="<?= htmlspecialchars($account["contact_number"], ENT_QUOTES, "UTF-8") ?>"
                        class="w-full rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-blue-500"
                    >
                </div>

                <button
                    type="submit"
                    name="update_profile"
                    value="1"
                    class="rounded-xl bg-blue-600 px-6 py-3 font-semibold text-white hover:bg-blue-700"
                >
                    Save Changes
                </button>
            </form>
        </section>

        <!-- Change Password -->
        <section class="rounded-2xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold">Change Password</h3>
            <p class="mb-6 mt-1 text-sm text-slate-500">Keep your account secure with a strong password.</p>

            <form method="POST" id="passwordForm" class="space-y-5">
                <div>
                    <label class="mb-2 block text-sm font-semibold" for="current_password">Current Password</label>
                    <div class="flex items-center rounded-xl border border-slate-200 focus-within:border-blue-500">
                        <input
                            type="password"
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            class="min-w-0 flex-1 rounded-xl px-4 py-3 outline-none"
                        >
                        <button type="button" class="password-toggle px-4 text-sm font-semibold text-blue-600" aria-label="Show or hide current password">Show</button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold" for="new_password">New Password</label>
                    <div class="flex items-center rounded-xl border border-slate-200 focus-within:border-blue-500">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="min-w-0 flex-1 rounded-xl px-4 py-3 outline-none"
                        >
                        <button type="button" class="password-toggle px-4 text-sm font-semibold text-blue-600" aria-label="Show or hide new password">Show</button>
                    </div>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold" for="confirm_password">Confirm New Password</label>
                    <div class="flex items-center rounded-xl border border-slate-200 focus-within:border-blue-500">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            required
                            minlength="8"
                            autocomplete="new-password"
                            class="min-w-0 flex-1 rounded-xl px-4 py-3 outline-none"
                        >
                        <button type="button" class="password-toggle px-4 text-sm font-semibold text-blue-600" aria-label="Show or hide confirm password">Show</button>
                    </div>
                </div>

                <button
                    type="submit"
                    name="change_password"
                    value="1"
                    class="rounded-xl bg-slate-900 px-6 py-3 font-semibold text-white hover:bg-slate-800"
                >
                    Update Password
                </button>
            </form>
        </section>

        <p class="mt-8 text-center text-sm text-slate-400">
            Barangay Health Worker Management System
        </p>

    </main>
</div>



</body>
 <script src="bhw.js"></script>
</html>
