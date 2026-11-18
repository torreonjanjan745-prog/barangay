

<?php
// ============================================
// ADMIN SESSION SECURITY
// ============================================

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

// Use a separate session name for admin accounts
session_name("BARANGAY_ADMIN_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Strict"
    ]);

    session_start();
}

// ============================================
// SECURITY HEADERS
// ============================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// ============================================
// DATABASE CONNECTION
// ============================================

require_once __DIR__ . "/../db.php";

// ============================================
// CHECK ADMIN AUTHENTICATION
// ============================================

if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// ============================================
// 30-MINUTE INACTIVITY TIMEOUT
// ============================================

$timeout = 1800;

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - $_SESSION["admin_last_activity"]) > $timeout
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            "",
            [
                "expires" => time() - 42000,
                "path" => $params["path"],
                "domain" => $params["domain"],
                "secure" => $params["secure"],
                "httponly" => $params["httponly"],
                "samesite" => "Strict"
            ]
        );
    }

    session_destroy();

    header("Location: login.php?expired=1");
    exit();
}

$_SESSION["admin_last_activity"] = time();

// ============================================
// LOGOUT CSRF TOKEN
// ============================================

if (empty($_SESSION["admin_logout_csrf"])) {
    $_SESSION["admin_logout_csrf"] = bin2hex(random_bytes(32));
}

// ============================================
// OUTPUT ESCAPING HELPER
// ============================================

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// ============================================
// ADMIN INFORMATION
// ============================================

$adminName = $_SESSION["admin_name"] ?? "Administrator";

// ============================================
// DASHBOARD DEFAULT VALUES
// ============================================

$totalResidents = 0;
$pendingResidents = 0;
$approvedResidents = 0;
$recentResidents = [];
$statusAvailable = false;
$errorMessage = "";

// ============================================
// LOAD RESIDENT DATABASE INFORMATION
// ============================================

try {

    // Total residents
    $countResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM residents"
    );

    if (!$countResult) {
        throw new Exception(mysqli_error($conn));
    }

    $countRow = mysqli_fetch_assoc($countResult);
    $totalResidents = (int)$countRow["total"];

    // Detect actual resident columns
    $columnsResult = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM residents"
    );

    if (!$columnsResult) {
        throw new Exception(mysqli_error($conn));
    }

    $columns = [];

    while ($column = mysqli_fetch_assoc($columnsResult)) {
        $columns[] = $column["Field"];
    }

    $statusAvailable = in_array("status", $columns, true);

    // Count pending and approved residents
    if ($statusAvailable) {

        $statusResult = mysqli_query(
            $conn,
            "SELECT
                SUM(
                    CASE
                        WHEN LOWER(status) = 'pending' THEN 1
                        ELSE 0
                    END
                ) AS pending,
                SUM(
                    CASE
                        WHEN LOWER(status) = 'approved' THEN 1
                        ELSE 0
                    END
                ) AS approved
             FROM residents"
        );

        if (!$statusResult) {
            throw new Exception(mysqli_error($conn));
        }

        $statusRow = mysqli_fetch_assoc($statusResult);

        $pendingResidents = (int)($statusRow["pending"] ?? 0);
        $approvedResidents = (int)($statusRow["approved"] ?? 0);
    }

    // Latest resident records
    $recentResult = mysqli_query(
        $conn,
        "SELECT * FROM residents ORDER BY id DESC LIMIT 8"
    );

    if (!$recentResult) {
        throw new Exception(mysqli_error($conn));
    }

    while ($row = mysqli_fetch_assoc($recentResult)) {
        $recentResidents[] = $row;
    }

} catch (Throwable $ex) {

    error_log("Admin dashboard error: " . $ex->getMessage());

    $errorMessage = "Unable to load dashboard information. Please check your database.";

}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 15px;
            border-radius: 10px;
            color: #cbd5e1;
            transition: .2s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #166534;
            color: white;
        }

        .nav-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .stat-card {
            transition: .2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        #adminSidebar {
            transition: transform .3s ease;
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- MOBILE OVERLAY -->
    <div
        id="overlay"
        class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"
        onclick="closeSidebar()">
    </div>

    <!-- SIDEBAR -->
    <aside
        id="adminSidebar"
        class="fixed inset-y-0 left-0 z-50 w-72 bg-slate-950
        text-white flex flex-col -translate-x-full lg:translate-x-0">

        <!-- BRAND -->
        <div class="px-6 py-7 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="w-12 h-12 rounded-xl bg-green-700
                    flex items-center justify-center text-2xl">
                    🏛️
                </div>

                <div>
                    <h1 class="font-bold text-lg">
                        Barangay
                    </h1>

                    <p class="text-green-400 text-sm font-semibold">
                        Guindacpan
                    </p>
                </div>

            </div>

            <p class="text-xs text-slate-400 uppercase tracking-widest mt-4">
                Administration Portal
            </p>

        </div>

        <!-- NAVIGATION -->
        <nav class="flex-1 px-4 py-6 overflow-y-auto">

            <p class="text-xs text-slate-500 font-semibold uppercase tracking-wider px-3 mb-3">
                Main Menu
            </p>

            <div class="space-y-2">

                <a href="#overview" class="nav-link active">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                    </svg>

                    Dashboard

                </a>

                <a href="#residents" class="nav-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="9" cy="8" r="4"/>
                        <path d="M2 21a7 7 0 0 1 14 0"/>
                        <path d="M16 4a4 4 0 0 1 0 8M18 14a7 7 0 0 1 4 7"/>
                    </svg>

                    Manage Residents

                </a>

                <a href="#verification" class="nav-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="9"/>
                        <path d="m8 12 3 3 5-6"/>
                    </svg>

                    Account Verification

                </a>

                <a href="#requests" class="nav-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 3h14v18H5z"/>
                        <path d="M8 8h8M8 12h8M8 16h5"/>
                    </svg>

                    Document Requests

                </a>

                <a href="#announcements" class="nav-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 11v2h3l12 5V6L6 11z"/>
                        <path d="M6 13l2 7h4l-2-5"/>
                    </svg>

                    Announcements

                </a>

            </div>

        </nav>

        <!-- ADMIN ACCOUNT -->
        <div class="p-5 border-t border-slate-800">

            <div class="mb-4">
                <p class="text-xs text-slate-500">
                    Signed in as
                </p>

                <p class="font-semibold text-sm mt-1">
                    <?= e($adminName) ?>
                </p>

                <p class="text-xs text-green-400">
                    Administrator
                </p>
            </div>

            <a href="logout.php"
               class="nav-link hover:!bg-red-700">

                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5"/>
                    <path d="M15 12H3"/>
                    <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                </svg>

                Logout

            </a>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="min-h-screen lg:ml-72">

        <!-- TOP HEADER -->
        <header class="sticky top-0 z-30 bg-white border-b border-slate-200">

            <div class="px-5 md:px-8 py-4 flex items-center justify-between">

                <div class="flex items-center gap-4">

                    <button
                        onclick="openSidebar()"
                        class="lg:hidden p-2 rounded-lg hover:bg-slate-100"
                        aria-label="Open menu">

                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>

                    </button>

                    <div>
                        <p class="text-xs text-slate-500">
                            Administration
                        </p>

                        <h2 class="font-bold text-xl text-slate-900">
                            Dashboard Overview
                        </h2>
                    </div>

                </div>

                <div class="hidden sm:flex items-center gap-3">

                    <div class="text-right">
                        <p class="font-semibold text-sm">
                            <?= e($adminName) ?>
                        </p>

                        <p class="text-xs text-slate-500">
                            Administrator
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-full bg-green-100
                        text-green-800 flex items-center justify-center font-bold">
                        <?= e(strtoupper(substr($adminName, 0, 1))) ?>
                    </div>

                </div>

            </div>

        </header>


        <!-- CONTENT -->
        <div class="p-5 md:p-8">

            <!-- WELCOME -->
            <section id="overview" class="mb-8">

                <div class="bg-gradient-to-r from-green-950 to-green-800
                    rounded-2xl p-7 md:p-9 text-white shadow-lg">

                    <p class="text-green-300 uppercase tracking-wider text-sm font-semibold">
                        Administration Portal
                    </p>

                    <h1 class="text-2xl md:text-3xl font-bold mt-2">
                        Welcome, <?= e($adminName) ?>!
                    </h1>

                    <p class="text-green-100 mt-3 text-sm md:text-base max-w-2xl">
                        Manage resident records and monitor the activities
                        of Barangay Guindacpan through this administration dashboard.
                    </p>

                </div>

            </section>


            <!-- ERROR -->
            <?php if ($errorMessage !== ""): ?>

                <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-6">
                    <?= e($errorMessage) ?>
                </div>

            <?php endif; ?>


            <!-- STATISTICS -->
            <section class="mb-10">

                <div class="mb-5">
                    <h2 class="text-xl font-bold">
                        System Overview
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Current resident registration statistics
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                    <!-- TOTAL -->
                    <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-slate-200">

                        <div class="flex justify-between items-center">

                            <div>
                                <p class="text-sm text-slate-500">
                                    Total Residents
                                </p>

                                <h3 class="text-3xl font-bold mt-3">
                                    <?= $totalResidents ?>
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-blue-100
                                flex items-center justify-center text-2xl">
                                👥
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            Registered resident accounts
                        </p>

                    </div>


                    <!-- PENDING -->
                    <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-slate-200">

                        <div class="flex justify-between items-center">

                            <div>
                                <p class="text-sm text-slate-500">
                                    Pending Accounts
                                </p>

                                <h3 class="text-3xl font-bold mt-3 text-yellow-600">
                                    <?= $statusAvailable ? $pendingResidents : '—' ?>
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-yellow-100
                                flex items-center justify-center text-2xl">
                                ⏳
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            <?php if ($statusAvailable): ?>
                                Waiting for verification
                            <?php else: ?>
                                Resident status column not detected
                            <?php endif; ?>
                        </p>

                    </div>


                    <!-- APPROVED -->
                    <div class="stat-card bg-white rounded-2xl p-6 shadow-sm border border-slate-200">

                        <div class="flex justify-between items-center">

                            <div>
                                <p class="text-sm text-slate-500">
                                    Approved Accounts
                                </p>

                                <h3 class="text-3xl font-bold mt-3 text-green-600">
                                    <?= $statusAvailable ? $approvedResidents : '—' ?>
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-green-100
                                flex items-center justify-center text-2xl">
                                ✓
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            <?php if ($statusAvailable): ?>
                                Verified resident accounts
                            <?php else: ?>
                                Resident status column not detected
                            <?php endif; ?>
                        </p>

                    </div>

                </div>

            </section>


            <!-- RESIDENT TABLE -->
            <section id="residents"
                class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-10">

                <div class="flex flex-col sm:flex-row sm:items-center
                    justify-between gap-3 mb-6">

                    <div>
                        <h2 class="text-xl font-bold">
                            Registered Residents
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Latest resident accounts from your database
                        </p>
                    </div>

                    <span class="bg-green-100 text-green-800 text-xs
                        font-semibold px-4 py-2 rounded-full">
                        <?= $totalResidents ?> Residents
                    </span>

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full min-w-[650px] text-left">

                        <thead>
                            <tr class="border-b border-slate-200 text-sm text-slate-500">

                                <th class="py-4 px-3">ID</th>
                                <th class="py-4 px-3">Resident Name</th>
                                <th class="py-4 px-3">Username</th>
                                <th class="py-4 px-3">Address</th>
                                <th class="py-4 px-3">Status</th>

                            </tr>
                        </thead>

                        <tbody>

                            <?php if (count($recentResidents) > 0): ?>

                                <?php foreach ($recentResidents as $person): ?>

                                    <?php
                                    $name = $person['full_name']
                                        ?? $person['fullName']
                                        ?? 'N/A';

                                    $personAddress = $person['purok_address']
                                        ?? $person['address']
                                        ?? 'N/A';

                                    $personStatus = $person['status'] ?? 'Not set';

                                    $personStatusLower = strtolower($personStatus);

                                    $badgeClass = $personStatusLower === 'approved'
                                        ? 'bg-green-100 text-green-700'
                                        : ($personStatusLower === 'rejected'
                                            ? 'bg-red-100 text-red-700'
                                            : 'bg-yellow-100 text-yellow-700');
                                    ?>

                                    <tr class="border-b border-slate-100 hover:bg-slate-50">

                                        <td class="py-4 px-3 text-sm">
                                            <?= e($person['id'] ?? '') ?>
                                        </td>

                                        <td class="py-4 px-3 text-sm font-semibold">
                                            <?= e($name) ?>
                                        </td>

                                        <td class="py-4 px-3 text-sm">
                                            <?= e($person['username'] ?? 'N/A') ?>
                                        </td>

                                        <td class="py-4 px-3 text-sm">
                                            <?= e($personAddress) ?>
                                        </td>

                                        <td class="py-4 px-3">

                                            <span class="px-3 py-1 rounded-full text-xs font-semibold <?= e($badgeClass) ?>">
                                                <?= e($personStatus) ?>
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="5" class="text-center py-10 text-slate-400">
                                        No resident records found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- ACCOUNT VERIFICATION -->
            <section id="verification"
                class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">

                <div class="flex items-center gap-3 mb-3">
                    <div class="w-11 h-11 rounded-xl bg-yellow-100
                        flex items-center justify-center text-xl">
                        🛡️
                    </div>

                    <h2 class="text-lg font-bold">
                        Account Verification
                    </h2>
                </div>

                <p class="text-sm text-slate-500">
                    Resident account approval and rejection actions are not
                    connected yet. We need to confirm the exact status values
                    and verification rules in your residents table before
                    enabling these actions.
                </p>

            </section>


            <!-- DOCUMENT REQUESTS -->
            <section id="requests"
                class="bg-white border border-slate-200 rounded-2xl p-6 mb-6">

                <div class="flex items-center gap-3 mb-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-100
                        flex items-center justify-center text-xl">
                        📄
                    </div>

                    <h2 class="text-lg font-bold">
                        Document Requests
                    </h2>
                </div>

                <p class="text-sm text-slate-500">
                    Document request management will be connected after
                    confirming your document request table structure.
                </p>

            </section>


            <!-- ANNOUNCEMENTS -->
            <section id="announcements"
                class="bg-white border border-slate-200 rounded-2xl p-6 mb-10">

                <div class="flex items-center gap-3 mb-3">
                    <div class="w-11 h-11 rounded-xl bg-orange-100
                        flex items-center justify-center text-xl">
                        📢
                    </div>

                    <h2 class="text-lg font-bold">
                        Announcements
                    </h2>
                </div>

                <p class="text-sm text-slate-500">
                    Announcement publishing is not connected yet.
                    We can add it once we define the announcements table.
                </p>

            </section>


            <!-- FOOTER -->
            <footer class="text-center text-sm text-slate-400 py-5">
                © <?= date("Y") ?> Barangay Guindacpan.
                Administration Portal.
            </footer>

        </div>

    </main>

</div>

</body>
 <script src="admin.js"></script>
</html>
