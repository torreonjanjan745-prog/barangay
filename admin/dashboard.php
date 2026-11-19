
<?php
// ============================================
// ADMIN SESSION SECURITY
// ============================================

$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

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
// ADMIN AUTHENTICATION
// ============================================

if (empty($_SESSION["admin_id"])) {
    header("Location: login.php");
    exit();
}

// ============================================
// SESSION TIMEOUT
// ============================================

$timeout = 1800;

if (
    !isset($_SESSION["admin_last_activity"]) ||
    (time() - $_SESSION["admin_last_activity"]) > $timeout
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(session_name(), "", [
            "expires" => time() - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => $params["httponly"],
            "samesite" => "Strict"
        ]);
    }

    session_destroy();

    header("Location: login.php?expired=1");
    exit();
}

$_SESSION["admin_last_activity"] = time();

// ============================================
// CSRF TOKEN
// ============================================

if (empty($_SESSION["admin_logout_csrf"])) {
    $_SESSION["admin_logout_csrf"] = bin2hex(random_bytes(32));
}

// ============================================
// HELPER
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
// DEFAULT VALUES
// ============================================

$totalResidents = 0;
$pendingResidents = 0;
$approvedResidents = 0;
$recentResidents = [];
$statusAvailable = false;
$errorMessage = "";

// ============================================
// DATABASE INFORMATION
// ============================================

try {

    $countResult = mysqli_query(
        $conn,
        "SELECT COUNT(*) AS total FROM residents"
    );

    if (!$countResult) {
        throw new Exception(mysqli_error($conn));
    }

    $countRow = mysqli_fetch_assoc($countResult);
    $totalResidents = (int)$countRow["total"];

    // Detect available columns
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

    // Resident status counts
    if ($statusAvailable) {

        $statusResult = mysqli_query(
            $conn,
            "SELECT
                SUM(CASE WHEN LOWER(status) = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN LOWER(status) = 'approved' THEN 1 ELSE 0 END) AS approved
             FROM residents"
        );

        if (!$statusResult) {
            throw new Exception(mysqli_error($conn));
        }

        $statusRow = mysqli_fetch_assoc($statusResult);

        $pendingResidents = (int)($statusRow["pending"] ?? 0);
        $approvedResidents = (int)($statusRow["approved"] ?? 0);
    }

    // Recent residents
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

    error_log("Dashboard Error: " . $ex->getMessage());

    $errorMessage = "Unable to load resident information. Please check your database.";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Admin Dashboard | Barangay Guindacpan</title>

<script src="https://cdn.tailwindcss.com"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

* {
    font-family: 'Inter', sans-serif;
}

body {
    background: #f8fafc;
}

.sidebar {
    transition: transform .3s ease;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    border-radius: 12px;
    color: #94a3b8;
    font-size: 14px;
    font-weight: 500;
    transition: .2s;
}

.nav-link:hover {
    background: #1e293b;
    color: white;
}

.nav-link.active {
    background: #166534;
    color: white;
    box-shadow: 0 5px 15px rgba(22,101,52,.2);
}

.nav-link svg {
    width: 19px;
    height: 19px;
    flex-shrink: 0;
}

.stat-card {
    transition: all .25s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 30px rgba(15,23,42,.07);
}

#overlay {
    transition: opacity .3s ease;
}

</style>

</head>

<body class="text-slate-800">

<div class="min-h-screen">

<!-- MOBILE OVERLAY -->

<div id="overlay"
     class="fixed inset-0 bg-slate-950/60 z-40 hidden lg:hidden"
     onclick="closeSidebar()">
</div>


<!-- SIDEBAR -->

<aside id="adminSidebar"
       class="sidebar fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 text-white flex flex-col -translate-x-full lg:translate-x-0">

    <!-- LOGO -->

    <div class="px-6 py-7 border-b border-slate-800">

        <div class="flex items-center gap-3">

            <div class="w-12 h-12 rounded-2xl bg-green-700 flex items-center justify-center text-2xl">
                🏛️
            </div>

            <div>
                <h1 class="text-lg font-extrabold tracking-tight">
                    Barangay
                </h1>

                <p class="text-green-400 text-sm font-semibold">
                    Guindacpan
                </p>
            </div>

        </div>

        <p class="text-[10px] text-slate-500 uppercase tracking-[3px] mt-5">
            Administration Portal
        </p>

    </div>


    <!-- NAVIGATION -->

    <nav class="flex-1 px-4 py-7 overflow-y-auto">

        <p class="text-[10px] uppercase tracking-[2px] font-bold text-slate-500 px-3 mb-4">
            Workspace
        </p>

        <div class="space-y-2">

            <a href="#overview" class="nav-link active">

                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                </svg>

                Overview

            </a>


            <a href="#residents" class="nav-link">

                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="9" cy="8" r="4"/>
                    <path d="M2 21a7 7 0 0 1 14 0"/>
                    <path d="M16 4a4 4 0 0 1 0 8M18 14a7 7 0 0 1 4 7"/>
                </svg>

                Residents

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


        <p class="text-[10px] uppercase tracking-[2px] font-bold text-slate-500 px-3 mb-4 mt-10">
            System
        </p>

        <div class="space-y-2">

            <a href="#overview" class="nav-link">

                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.5 2.6-.2-.1a1.7 1.7 0 0 0-1.9.3l-.1.1h-3l-.1-.2a1.7 1.7 0 0 0-1.5-1l-.2.1-2.6-1.5.1-.2a1.7 1.7 0 0 0-.3-1.9l-.1-.1v-3l.2-.1a1.7 1.7 0 0 0 1-1.5l-.1-.2 1.5-2.6.2.1a1.7 1.7 0 0 0 1.9-.3l.1-.1h3l.1.2a1.7 1.7 0 0 0 1.5 1l.2-.1 2.6 1.5-.1.2a1.7 1.7 0 0 0 .3 1.9l.1.1z"/>
                </svg>

                Settings

            </a>

        </div>

    </nav>


    <!-- ADMIN PROFILE -->

    <div class="p-4 border-t border-slate-800">

        <div class="flex items-center gap-3 mb-4">

            <div class="w-10 h-10 rounded-xl bg-green-700 flex items-center justify-center font-bold">
                <?= e(strtoupper(substr($adminName, 0, 1))) ?>
            </div>

            <div class="min-w-0">
                <p class="text-sm font-semibold truncate">
                    <?= e($adminName) ?>
                </p>

                <p class="text-xs text-slate-500">
                    Administrator
                </p>
            </div>

        </div>


        <form action="logout.php" method="POST">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= e($_SESSION["admin_logout_csrf"]) ?>">

            <button type="submit"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-500/10 transition text-sm font-semibold">

                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5"/>
                    <path d="M15 12H3"/>
                    <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                </svg>

                Sign Out

            </button>

        </form>

    </div>

</aside>


<!-- MAIN CONTENT -->

<main class="min-h-screen lg:ml-72">


    <!-- TOPBAR -->

    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-xl border-b border-slate-200">

        <div class="px-5 md:px-8 py-4 flex justify-between items-center">

            <div class="flex items-center gap-4">

                <button onclick="openSidebar()"
                        class="lg:hidden p-2 rounded-xl hover:bg-slate-100"
                        aria-label="Open sidebar">

                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>

                </button>

                <div>
                    <p class="text-xs text-slate-400 font-medium">
                        Barangay Guindacpan / Administration
                    </p>

                    <h2 class="font-bold text-lg md:text-xl text-slate-900">
                        Dashboard
                    </h2>
                </div>

            </div>


            <div class="flex items-center gap-3">

                <div class="hidden sm:block text-right">

                    <p class="text-sm font-bold">
                        <?= e($adminName) ?>
                    </p>

                    <p class="text-xs text-slate-400">
                        Administrator
                    </p>

                </div>

                <div class="w-10 h-10 rounded-full bg-green-100 text-green-800 flex items-center justify-center font-bold">
                    <?= e(strtoupper(substr($adminName, 0, 1))) ?>
                </div>

            </div>

        </div>

    </header>


    <!-- DASHBOARD CONTENT -->

    <div class="p-5 md:p-8 max-w-[1600px] mx-auto">


        <!-- WELCOME BANNER -->

        <section id="overview" class="mb-8">

            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-green-950 via-green-900 to-emerald-800 p-7 md:p-10 text-white shadow-xl">

                <div class="relative z-10 max-w-2xl">

                    <span class="inline-flex items-center gap-2 bg-white/10 border border-white/10 px-3 py-1.5 rounded-full text-xs text-green-200 font-semibold">

                        <span class="w-2 h-2 rounded-full bg-green-400"></span>

                        ADMINISTRATION PORTAL

                    </span>

                    <h1 class="text-2xl md:text-4xl font-extrabold mt-5 tracking-tight">
                        Welcome back, <?= e($adminName) ?>!
                    </h1>

                    <p class="text-green-100/80 mt-3 text-sm md:text-base leading-relaxed">
                        Monitor resident registrations, manage barangay information,
                        and keep your community organized in one place.
                    </p>

                    <a href="#residents"
                       class="inline-flex items-center gap-2 mt-6 bg-white text-green-900 px-5 py-3 rounded-xl text-sm font-bold hover:bg-green-50 transition">

                        View Residents

                        <span>→</span>

                    </a>

                </div>

                <div class="absolute right-5 bottom-0 text-[150px] md:text-[210px] opacity-[0.08] leading-none pointer-events-none">
                    🏛️
                </div>

            </div>

        </section>


        <!-- ERROR MESSAGE -->

        <?php if ($errorMessage !== ""): ?>

            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl mb-6">
                <?= e($errorMessage) ?>
            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <section class="mb-10">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">

                <div>
                    <h2 class="text-xl font-extrabold text-slate-900">
                        Overview Statistics
                    </h2>

                    <p class="text-sm text-slate-400 mt-1">
                        A quick look at your resident records
                    </p>
                </div>

                <span class="text-xs text-slate-400">
                    Updated when this page loads
                </span>

            </div>


            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">


                <!-- TOTAL RESIDENTS -->

                <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6">

                    <div class="flex items-center justify-between">

                        <div>
                            <p class="text-sm font-medium text-slate-500">
                                Total Residents
                            </p>

                            <h3 class="text-4xl font-extrabold text-slate-900 mt-3">
                                <?= number_format($totalResidents) ?>
                            </h3>
                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                            👥
                        </div>

                    </div>

                    <p class="text-xs text-slate-400 mt-5">
                        Registered resident accounts
                    </p>

                </div>


                <!-- PENDING -->

                <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6">

                    <div class="flex items-center justify-between">

                        <div>
                            <p class="text-sm font-medium text-slate-500">
                                Pending Accounts
                            </p>

                            <h3 class="text-4xl font-extrabold text-amber-500 mt-3">
                                <?= $statusAvailable ? number_format($pendingResidents) : "—" ?>
                            </h3>
                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
                            ⏳
                        </div>

                    </div>

                    <p class="text-xs text-slate-400 mt-5">
                        Awaiting account verification
                    </p>

                </div>


                <!-- APPROVED -->

                <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6">

                    <div class="flex items-center justify-between">

                        <div>
                            <p class="text-sm font-medium text-slate-500">
                                Approved Accounts
                            </p>

                            <h3 class="text-4xl font-extrabold text-green-600 mt-3">
                                <?= $statusAvailable ? number_format($approvedResidents) : "—" ?>
                            </h3>
                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-2xl">
                            ✓
                        </div>

                    </div>

                    <p class="text-xs text-slate-400 mt-5">
                        Verified resident accounts
                    </p>

                </div>

            </div>

        </section>


        <!-- RESIDENT TABLE -->

        <section id="residents" class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-8">

            <div class="p-6 border-b border-slate-100">

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

                    <div>

                        <h2 class="text-lg font-extrabold text-slate-900">
                            Recent Residents
                        </h2>

                        <p class="text-sm text-slate-400 mt-1">
                            Latest resident registrations
                        </p>

                    </div>


                    <div class="relative">

                        <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">

                            <circle cx="11" cy="11" r="7"/>
                            <path d="m20 20-4-4"/>

                        </svg>

                        <input type="text"
                               id="residentSearch"
                               placeholder="Search residents..."
                               class="w-full md:w-64 pl-10 pr-4 py-3 rounded-xl border border-slate-200 text-sm outline-none focus:border-green-600 focus:ring-2 focus:ring-green-100">

                    </div>

                </div>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full min-w-[700px] text-left" id="residentTable">

                    <thead class="bg-slate-50">

                        <tr class="text-xs uppercase tracking-wider text-slate-400">

                            <th class="py-4 px-6 font-semibold">ID</th>
                            <th class="py-4 px-6 font-semibold">Resident Name</th>
                            <th class="py-4 px-6 font-semibold">Username</th>
                            <th class="py-4 px-6 font-semibold">Address</th>
                            <th class="py-4 px-6 font-semibold">Status</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!empty($recentResidents)): ?>

                        <?php foreach ($recentResidents as $person): ?>

                            <?php

                            $name = $person["full_name"]
                                ?? $person["fullName"]
                                ?? "N/A";

                            $address = $person["purok_address"]
                                ?? $person["address"]
                                ?? "N/A";

                            $status = $person["status"] ?? "Not set";

                            $statusLower = strtolower(trim($status));

                            if ($statusLower === "approved") {
                                $badgeClass = "bg-green-50 text-green-700";
                            } elseif ($statusLower === "rejected") {
                                $badgeClass = "bg-red-50 text-red-700";
                            } else {
                                $badgeClass = "bg-amber-50 text-amber-700";
                            }

                            ?>

                            <tr class="resident-row border-b border-slate-100 hover:bg-slate-50 transition">

                                <td class="py-5 px-6 text-sm text-slate-400">
                                    <?= e($person["id"] ?? "") ?>
                                </td>

                                <td class="py-5 px-6">

                                    <div class="flex items-center gap-3">

                                        <div class="w-9 h-9 rounded-xl bg-green-50 text-green-700 flex items-center justify-center font-bold text-sm">
                                            <?= e(strtoupper(substr($name, 0, 1))) ?>
                                        </div>

                                        <span class="text-sm font-semibold text-slate-800">
                                            <?= e($name) ?>
                                        </span>

                                    </div>

                                </td>

                                <td class="py-5 px-6 text-sm text-slate-500">
                                    <?= e($person["username"] ?? "N/A") ?>
                                </td>

                                <td class="py-5 px-6 text-sm text-slate-500">
                                    <?= e($address) ?>
                                </td>

                                <td class="py-5 px-6">

                                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold <?= e($badgeClass) ?>">
                                        <?= e(ucfirst($status)) ?>
                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400 text-sm">
                                No resident records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

                <p id="noSearchResults" class="hidden text-center py-8 text-slate-400 text-sm">
                    No matching residents found.
                </p>

            </div>


            <div class="p-5 border-t border-slate-100 flex items-center justify-between">

                <p class="text-xs text-slate-400">
                    Showing up to 8 recent records
                </p>

                <span class="text-xs font-semibold text-green-700">
                    <?= number_format($totalResidents) ?> Total Residents
                </span>

            </div>

        </section>


        <!-- OTHER MODULES -->

        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mb-10">


            <!-- VERIFICATION -->

            <div id="verification" class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mb-5">
                    🛡️
                </div>

                <h3 class="font-bold text-slate-900">
                    Account Verification
                </h3>

                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Review resident accounts and manage verification.
                </p>

                <p class="text-xs text-amber-600 font-semibold mt-5">
                    Module not connected yet
                </p>

            </div>


            <!-- DOCUMENT REQUESTS -->

            <div id="requests" class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5">
                    📄
                </div>

                <h3 class="font-bold text-slate-900">
                    Document Requests
                </h3>

                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Manage barangay clearance and other resident document requests.
                </p>

                <p class="text-xs text-blue-600 font-semibold mt-5">
                    Module not connected yet
                </p>

            </div>


            <!-- ANNOUNCEMENTS -->

            <div id="announcements" class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl mb-5">
                    📢
                </div>

                <h3 class="font-bold text-slate-900">
                    Announcements
                </h3>

                <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                    Publish important barangay updates and community notices.
                </p>

                <p class="text-xs text-orange-600 font-semibold mt-5">
                    Module not connected yet
                </p>

            </div>

        </section>


        <!-- FOOTER -->

        <footer class="border-t border-slate-200 pt-6 pb-3 text-center">

            <p class="text-xs text-slate-400">
                © <?= date("Y") ?> Barangay Guindacpan
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Administration Management System
            </p>

        </footer>

    </div>

</main>

</div>


<!-- JAVASCRIPT -->

<script>

// ============================================
// MOBILE SIDEBAR
// ============================================

function openSidebar() {

    document.getElementById("adminSidebar")
        .classList.remove("-translate-x-full");

    document.getElementById("overlay")
        .classList.remove("hidden");

}

function closeSidebar() {

    document.getElementById("adminSidebar")
        .classList.add("-translate-x-full");

    document.getElementById("overlay")
        .classList.add("hidden");

}


// ============================================
// RESIDENT SEARCH
// ============================================

const searchInput = document.getElementById("residentSearch");

if (searchInput) {

    searchInput.addEventListener("input", function () {

        const searchValue = this.value.toLowerCase();

        const rows = document.querySelectorAll(".resident-row");

        let visibleCount = 0;

        rows.forEach(row => {

            const rowText = row.textContent.toLowerCase();

            if (rowText.includes(searchValue)) {

                row.style.display = "";

                visibleCount++;

            } else {

                row.style.display = "none";

            }

        });

        const noResults = document.getElementById("noSearchResults");

        if (noResults) {
            noResults.classList.toggle("hidden", visibleCount !== 0);
        }

    });

}


// ============================================
// CLOSE MOBILE SIDEBAR ON NAVIGATION
// ============================================

document.querySelectorAll("#adminSidebar .nav-link").forEach(link => {

    link.addEventListener("click", function () {

        if (window.innerWidth < 1024) {
            closeSidebar();
        }

    });

});

</script>

</body>
</html>
