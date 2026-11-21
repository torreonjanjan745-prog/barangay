
<?php
// =============================================
// ADMIN DASHBOARD
// =============================================

session_name("BARANGAY_ADMIN_SESSION");

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

// CHECK ADMIN LOGIN
if (!isset($_SESSION["admin_id"])) {
    header("Location: adminlogIn.php");
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . "/../db.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log("Admin Dashboard: Database connection unavailable.");
    die("Database connection error. Please check the main db.php file.");
}

$conn->set_charset("utf8mb4");

// CSRF TOKEN
if (empty($_SESSION["admin_dashboard_csrf"])) {
    $_SESSION["admin_dashboard_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["admin_dashboard_csrf"];

$message = "";
$messageType = "success";

// =============================================
// HANDLE ADMIN ACTIONS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($csrfToken, $submittedToken)) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // LOGOUT
        if ($action === "logout") {
            $_SESSION = [];

            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    "",
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();

            header("Location: adminlogIn.php");
            exit;
        }

        // APPROVE BHW ACCOUNT
        if ($action === "approve") {

            $bhwId = filter_input(
                INPUT_POST,
                "bhw_id",
                FILTER_VALIDATE_INT
            );

            if (!$bhwId || $bhwId <= 0) {
                $message = "Invalid BHW account.";
                $messageType = "error";

            } else {

                $stmt = $conn->prepare("
                    UPDATE bhw_accounts
                    SET status = 'Active'
                    WHERE id = ?
                    AND status = 'Inactive'
                ");

                if ($stmt) {
                    $stmt->bind_param("i", $bhwId);

                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $message = "BHW account approved successfully.";
                        } else {
                            $message = "Account not found or already approved.";
                            $messageType = "error";
                        }
                    } else {
                        error_log("BHW approval error: " . $stmt->error);
                        $message = "Unable to approve the account.";
                        $messageType = "error";
                    }

                    $stmt->close();

                } else {
                    error_log("BHW approval prepare error: " . $conn->error);
                    $message = "Database error. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

// =============================================
// DASHBOARD STATISTICS
// =============================================

function getCount($conn, $sql) {
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Dashboard count error: " . $conn->error);
        return 0;
    }

    $row = $result->fetch_row();
    return (int)($row[0] ?? 0);
}

$totalBHW = getCount(
    $conn,
    "SELECT COUNT(*) FROM bhw_accounts"
);

$pendingBHW = getCount(
    $conn,
    "SELECT COUNT(*) FROM bhw_accounts WHERE status = 'Inactive'"
);

$activeBHW = getCount(
    $conn,
    "SELECT COUNT(*) FROM bhw_accounts WHERE status = 'Active'"
);

$totalPatients = getCount(
    $conn,
    "SELECT COUNT(*) FROM bhw_patients"
);

$totalAppointments = getCount(
    $conn,
    "SELECT COUNT(*) FROM bhw_appointments"
);

// =============================================
// GET RECENT BHW ACCOUNTS
// =============================================

$bhwAccounts = [];

$result = $conn->query("
    SELECT id, full_name, email, contact_number, position, status
    FROM bhw_accounts
    ORDER BY id DESC
    LIMIT 10
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $bhwAccounts[] = $row;
    }
} else {
    error_log("BHW list error: " . $conn->error);
}

$adminName = $_SESSION["admin_name"] ?? "Administrator";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Barangay Health System</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            transition: transform 0.3s ease;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="text-slate-800">

<div class="min-h-screen flex">

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar fixed md:static inset-y-0 left-0 z-40
        w-64 bg-slate-900 text-white flex flex-col">

        <div class="px-6 py-6 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="bg-blue-600 p-2 rounded-xl">
                    <i data-lucide="heart-pulse" class="w-6 h-6"></i>
                </div>

                <div>
                    <h1 class="font-bold text-lg">Barangay Health</h1>
                    <p class="text-xs text-slate-400">Admin Portal</p>
                </div>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2">

            <a href="dashboard.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl
                bg-blue-600 text-white">
                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>
            </a>

            <a href="bhwAccounts.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl
                text-slate-300 hover:bg-white/10">
                <i data-lucide="users" class="w-5 h-5"></i>
                <span>BHW Accounts</span>
            </a>

            <a href="patients.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl
                text-slate-300 hover:bg-white/10">
                <i data-lucide="user-round" class="w-5 h-5"></i>
                <span>Patients</span>
            </a>

            <a href="appointments.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl
                text-slate-300 hover:bg-white/10">
                <i data-lucide="calendar-days" class="w-5 h-5"></i>
                <span>Appointments</span>
            </a>

            <a href="reports.php"
                class="flex items-center gap-3 px-4 py-3 rounded-xl
                text-slate-300 hover:bg-white/10">
                <i data-lucide="chart-column" class="w-5 h-5"></i>
                <span>Reports</span>
            </a>

        </nav>

        <div class="p-4 border-t border-white/10">

            <div class="flex items-center gap-3 mb-4 px-2">
                <div class="w-10 h-10 rounded-full bg-blue-600
                    flex items-center justify-center">
                    <i data-lucide="shield-check"></i>
                </div>

                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate">
                        <?= e($adminName) ?>
                    </p>
                    <p class="text-xs text-slate-400">
                        Administrator
                    </p>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token"
                    value="<?= e($csrfToken) ?>">

                <input type="hidden" name="action" value="logout">

                <button type="submit"
                    class="w-full flex items-center gap-3 px-4 py-3
                    rounded-xl text-red-300 hover:bg-red-500/10">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span>Logout</span>
                </button>
            </form>

        </div>
    </aside>

    <!-- MOBILE OVERLAY -->
    <div id="overlay"
        class="hidden fixed inset-0 bg-black/50 z-30 md:hidden">
    </div>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0">

        <!-- TOPBAR -->
        <header class="bg-white border-b border-slate-200
            px-5 md:px-8 py-5 flex items-center justify-between">

            <div class="flex items-center gap-4">

                <button id="menuBtn"
                    class="md:hidden p-2 rounded-lg hover:bg-slate-100">
                    <i data-lucide="menu"></i>
                </button>

                <div>
                    <h2 class="text-xl md:text-2xl font-bold">
                        Admin Dashboard
                    </h2>

                    <p class="text-sm text-slate-500">
                        Barangay Health Management System
                    </p>
                </div>

            </div>

            <div class="hidden sm:flex items-center gap-2 text-sm
                text-slate-500">
                <i data-lucide="shield-check" class="w-5 h-5 text-blue-600"></i>
                Admin Portal
            </div>

        </header>

        <!-- PAGE CONTENT -->
        <div class="p-5 md:p-8">

            <!-- MESSAGE -->
            <?php if ($message !== ""): ?>

                <div class="mb-6 p-4 rounded-xl border
                    <?= $messageType === "success"
                        ? "bg-green-50 border-green-200 text-green-700"
                        : "bg-red-50 border-red-200 text-red-700" ?>">
                    <?= e($message) ?>
                </div>

            <?php endif; ?>

            <!-- WELCOME -->
            <div class="mb-8">
                <h3 class="text-2xl font-bold">
                    Welcome, <?= e($adminName) ?>!
                </h3>

                <p class="text-slate-500 mt-1">
                    Here's an overview of your barangay health system.
                </p>
            </div>

            <!-- STATISTICS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4
                gap-5 mb-8">

                <!-- TOTAL BHW -->
                <div class="bg-white p-6 rounded-2xl border
                    border-slate-200 shadow-sm">

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">
                                Total BHW Accounts
                            </p>

                            <h3 class="text-3xl font-bold mt-2">
                                <?= $totalBHW ?>
                            </h3>
                        </div>

                        <div class="p-3 rounded-xl bg-blue-100 text-blue-600">
                            <i data-lucide="users" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- PENDING -->
                <div class="bg-white p-6 rounded-2xl border
                    border-slate-200 shadow-sm">

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">
                                Pending Approvals
                            </p>

                            <h3 class="text-3xl font-bold mt-2">
                                <?= $pendingBHW ?>
                            </h3>
                        </div>

                        <div class="p-3 rounded-xl bg-yellow-100 text-yellow-600">
                            <i data-lucide="clock" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- ACTIVE -->
                <div class="bg-white p-6 rounded-2xl border
                    border-slate-200 shadow-sm">

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">
                                Active BHW Accounts
                            </p>

                            <h3 class="text-3xl font-bold mt-2">
                                <?= $activeBHW ?>
                            </h3>
                        </div>

                        <div class="p-3 rounded-xl bg-green-100 text-green-600">
                            <i data-lucide="user-check" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

                <!-- PATIENTS -->
                <div class="bg-white p-6 rounded-2xl border
                    border-slate-200 shadow-sm">

                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-slate-500">
                                Total Patients
                            </p>

                            <h3 class="text-3xl font-bold mt-2">
                                <?= $totalPatients ?>
                            </h3>
                        </div>

                        <div class="p-3 rounded-xl bg-purple-100 text-purple-600">
                            <i data-lucide="heart-pulse" class="w-6 h-6"></i>
                        </div>
                    </div>
                </div>

            </div>

            <!-- APPOINTMENT SUMMARY -->
            <div class="bg-white p-6 rounded-2xl border
                border-slate-200 shadow-sm mb-8">

                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-500">
                            Total Appointments
                        </p>

                        <h3 class="text-3xl font-bold mt-2">
                            <?= $totalAppointments ?>
                        </h3>
                    </div>

                    <div class="p-3 rounded-xl bg-orange-100 text-orange-600">
                        <i data-lucide="calendar-check" class="w-7 h-7"></i>
                    </div>
                </div>

            </div>

            <!-- BHW ACCOUNTS TABLE -->
            <section class="bg-white rounded-2xl border
                border-slate-200 shadow-sm overflow-hidden">

                <div class="p-6 border-b border-slate-200
                    flex flex-wrap items-center justify-between gap-3">

                    <div>
                        <h3 class="text-lg font-bold">
                            BHW Account Management
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Review and approve newly registered BHW accounts.
                        </p>
                    </div>

                    <a href="bhwAccounts.php"
                        class="text-sm font-semibold text-blue-600
                        hover:text-blue-800">
                        View All Accounts →
                    </a>

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-6 py-4 font-semibold">Name</th>
                                <th class="px-6 py-4 font-semibold">Email</th>
                                <th class="px-6 py-4 font-semibold">Contact</th>
                                <th class="px-6 py-4 font-semibold">Position</th>
                                <th class="px-6 py-4 font-semibold">Status</th>
                                <th class="px-6 py-4 font-semibold">Action</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">

                            <?php if (count($bhwAccounts) > 0): ?>

                                <?php foreach ($bhwAccounts as $bhw): ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-6 py-4 font-semibold">
                                            <?= e($bhw["full_name"]) ?>
                                        </td>

                                        <td class="px-6 py-4 text-slate-500">
                                            <?= e($bhw["email"]) ?>
                                        </td>

                                        <td class="px-6 py-4 text-slate-500">
                                            <?= e($bhw["contact_number"] ?? "") ?>
                                        </td>

                                        <td class="px-6 py-4 text-slate-500">
                                            <?= e($bhw["position"] ?? "Barangay Health Worker") ?>
                                        </td>

                                        <td class="px-6 py-4">

                                            <?php if ($bhw["status"] === "Active"): ?>

                                                <span class="px-3 py-1 rounded-full
                                                    text-xs font-semibold
                                                    bg-green-100 text-green-700">
                                                    Active
                                                </span>

                                            <?php else: ?>

                                                <span class="px-3 py-1 rounded-full
                                                    text-xs font-semibold
                                                    bg-yellow-100 text-yellow-700">
                                                    Pending
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                        <td class="px-6 py-4">

                                            <?php if ($bhw["status"] === "Inactive"): ?>

                                                <form method="POST"
                                                    onsubmit="return confirm('Approve this BHW account?');">

                                                    <input type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($csrfToken) ?>">

                                                    <input type="hidden"
                                                        name="action"
                                                        value="approve">

                                                    <input type="hidden"
                                                        name="bhw_id"
                                                        value="<?= (int)$bhw["id"] ?>">

                                                    <button type="submit"
                                                        class="px-4 py-2 rounded-lg
                                                        bg-blue-600 text-white
                                                        text-xs font-semibold
                                                        hover:bg-blue-700">
                                                        Approve
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span class="text-xs text-green-600
                                                    font-semibold">
                                                    Approved
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="6"
                                        class="px-6 py-10 text-center
                                        text-slate-500">
                                        No BHW accounts registered yet.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <!-- FOOTER -->
            <footer class="mt-8 text-center text-sm text-slate-400">
                © <?= date("Y") ?> Barangay Health Management System.
                All rights reserved.
            </footer>

        </div>

    </main>

</div>

<script>
    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("overlay");

    menuBtn.addEventListener("click", function () {
        sidebar.classList.toggle("show");
        overlay.classList.toggle("hidden");
    });

    overlay.addEventListener("click", function () {
        sidebar.classList.remove("show");
        overlay.classList.add("hidden");
    });

    lucide.createIcons();
</script>

</body>
</html>
