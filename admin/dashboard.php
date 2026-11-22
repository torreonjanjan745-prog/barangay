
<?php
// =====================================================
// BARANGAY GUINDACPAN - ADMINISTRATOR DASHBOARD
// PHP + PDO + TAILWIND CSS
// NO CUSTOM JAVASCRIPT
// =====================================================

// =====================================================
// SESSION SECURITY
// =====================================================

$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {
    session_name("BARANGAY_ADMIN_SESSION");

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Strict"
    ]);

    session_start();
}

// =====================================================
// SECURITY HEADERS
// =====================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");

// =====================================================
// ADMIN AUTHENTICATION
// =====================================================

if (empty($_SESSION["admin_id"])) {
    header("Location: adminlogIn.php");
    exit();
}

// =====================================================
// SESSION TIMEOUT - 30 MINUTES
// =====================================================

$timeout = 1800;
$now = time();

if (
    isset($_SESSION["admin_last_activity"]) &&
    ($now - (int)$_SESSION["admin_last_activity"]) > $timeout
) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(session_name(), "", [
            "expires" => $now - 42000,
            "path" => $params["path"],
            "domain" => $params["domain"],
            "secure" => $params["secure"],
            "httponly" => true,
            "samesite" => "Strict"
        ]);
    }

    session_destroy();

    header("Location: adminlogIn.php?expired=1");
    exit();
}

$_SESSION["admin_last_activity"] = $now;

// =====================================================
// DATABASE CONNECTION - PDO
// Main database: barangay_db
// =====================================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Administrator Dashboard: Invalid PDO connection.");
    http_response_code(500);
    exit("Administrator database connection is not available.");
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->query("SELECT 1");
} catch (PDOException $ex) {
    error_log("Administrator Dashboard Connection Error: " . $ex->getMessage());
    http_response_code(500);
    exit("Administrator database connection failed.");
}

// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION["admin_logout_csrf"])) {
    $_SESSION["admin_logout_csrf"] = bin2hex(random_bytes(32));
}

// =====================================================
// ESCAPE OUTPUT
// =====================================================

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

// =====================================================
// ADMIN INFORMATION
// =====================================================

$adminName = $_SESSION["admin_name"] ?? "Administrator";
$initial = strtoupper(substr(trim($adminName), 0, 1));

if ($initial === "") {
    $initial = "A";
}

// =====================================================
// DEFAULT STATISTICS
// =====================================================

$totalResidents = 0;
$pendingResidents = 0;
$approvedResidents = 0;
$rejectedResidents = 0;

$recentResidents = [];
$errorMessage = "";

// =====================================================
// RESIDENT SEARCH - PHP ONLY
// =====================================================

$search = trim($_GET["search"] ?? "");

if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

// =====================================================
// DATABASE QUERIES
// =====================================================

try {

    // RESIDENT STATISTICS

    $statsQuery = "
        SELECT
            COUNT(*) AS total,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(COALESCE(status, '')) = 'pending'
                        THEN 1 ELSE 0
                    END
                ), 0
            ) AS pending,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(COALESCE(status, '')) = 'approved'
                        THEN 1 ELSE 0
                    END
                ), 0
            ) AS approved,

            COALESCE(
                SUM(
                    CASE
                        WHEN LOWER(COALESCE(status, '')) = 'rejected'
                        THEN 1 ELSE 0
                    END
                ), 0
            ) AS rejected

        FROM residents
    ";

    $statsResult = $pdo->query($statsQuery);
    $stats = $statsResult->fetch();

    $totalResidents = (int)($stats["total"] ?? 0);
    $pendingResidents = (int)($stats["pending"] ?? 0);
    $approvedResidents = (int)($stats["approved"] ?? 0);
    $rejectedResidents = (int)($stats["rejected"] ?? 0);

    // RECENT RESIDENTS
    // Password is intentionally excluded.

    $recentQuery = "
        SELECT
            id,
            full_name,
            username,
            purok_address,
            status,
            created_at

        FROM residents
    ";

    if ($search !== "") {
        $recentQuery .= "
            WHERE
                full_name LIKE :search_name
                OR username LIKE :search_username
                OR purok_address LIKE :search_address
                OR CAST(id AS CHAR) LIKE :search_id
        ";
    }

    $recentQuery .= "
        ORDER BY id DESC
        LIMIT 8
    ";

    $recentStmt = $pdo->prepare($recentQuery);

    if ($search !== "") {
        $searchValue = "%" . $search . "%";

        $recentStmt->bindValue(":search_name", $searchValue, PDO::PARAM_STR);
        $recentStmt->bindValue(":search_username", $searchValue, PDO::PARAM_STR);
        $recentStmt->bindValue(":search_address", $searchValue, PDO::PARAM_STR);
        $recentStmt->bindValue(":search_id", $searchValue, PDO::PARAM_STR);
    }

    $recentStmt->execute();
    $recentResidents = $recentStmt->fetchAll();

} catch (Throwable $ex) {

    error_log("Administrator Dashboard Query Error: " . $ex->getMessage());

    $errorMessage = "Unable to load dashboard information. Please check your database tables and columns.";

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Administrator Dashboard | Barangay Guindacpan</title>

<!-- TAILWIND CSS CDN -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- GOOGLE FONT -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">

<style>

* {
    font-family: 'Inter', sans-serif;
}

html {
    scroll-behavior: smooth;
    scroll-padding-top: 100px;
}

body {
    background: #f6f8f7;
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
    color: #a1a1aa;
    font-size: 13px;
    font-weight: 600;
    transition: background .2s ease, color .2s ease;
}

.nav-link:hover {
    background: #1e293b;
    color: white;
}

.nav-link.active {
    background: #166534;
    color: white;
}

.nav-link svg {
    width: 19px;
    height: 19px;
    flex-shrink: 0;
}

.stat-card {
    transition: transform .25s ease, box-shadow .25s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(15,23,42,.08);
}

.resident-row {
    transition: background .2s ease;
}

#sidebarToggle:checked ~ #adminSidebar {
    transform: translateX(0);
}

#sidebarToggle:checked ~ #sidebarOverlay {
    display: block;
}

@media (min-width: 1024px) {
    #sidebarOverlay {
        display: none !important;
    }
}

</style>

</head>

<body class="text-slate-800">

<div class="min-h-screen">

<!-- =================================================
     CSS-ONLY MOBILE SIDEBAR CONTROL
================================================== -->

<input
    type="checkbox"
    id="sidebarToggle"
    class="hidden"
    aria-label="Toggle sidebar">


<!-- =================================================
     SIDEBAR
================================================== -->

<aside
    id="adminSidebar"
    class="sidebar fixed inset-y-0 left-0 z-50 w-72 bg-slate-950 text-white flex flex-col -translate-x-full lg:translate-x-0">

    <!-- BRAND -->

    <div class="px-6 py-7 border-b border-slate-800">

        <div class="flex items-center gap-3">

            <div class="w-12 h-12 rounded-2xl bg-green-700 flex items-center justify-center text-2xl shadow-lg">
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

            <a href="#systemInfo" class="nav-link">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.5 2.6-.2-.1a1.7 1.7 0 0 0-1.9.3l-.1.1h-3l-.1-.2a1.7 1.7 0 0 0-1.5-1l-.2.1-2.6-1.5.1-.2a1.7 1.7 0 0 0-.3-1.9l-.1-.1v-3l.2-.1a1.7 1.7 0 0 0 1-1.5l-.1-.2 1.5-2.6.2.1a1.7 1.7 0 0 0 1.9-.3l.1-.1h3l.1.2a1.7 1.7 0 0 0 1.5 1l.2-.1 2.6 1.5-.1.2a1.7 1.7 0 0 0 .3 1.9l.1.1z"/>
                </svg>
                System Information
            </a>

        </div>

    </nav>


    <!-- ADMIN PROFILE -->

    <div class="p-4 border-t border-slate-800">

        <div class="flex items-center gap-3 mb-4">

            <div class="w-10 h-10 rounded-xl bg-green-700 flex items-center justify-center font-bold">
                <?= e($initial) ?>
            </div>

            <div class="min-w-0">

                <p class="text-sm font-semibold truncate">
                    <?= e($adminName) ?>
                </p>

                <p class="text-xs text-slate-500">
                    System Administrator
                </p>

            </div>

        </div>

        <form action="logout.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["admin_logout_csrf"]) ?>">

            <button
                type="submit"
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


<!-- MOBILE OVERLAY -->

<label
    id="sidebarOverlay"
    for="sidebarToggle"
    class="fixed inset-0 bg-slate-950/60 z-40 hidden lg:hidden"
    aria-label="Close sidebar">
</label>


<!-- =================================================
     MAIN CONTENT
================================================== -->

<main class="min-h-screen lg:ml-72">

    <!-- TOPBAR -->

    <header class="sticky top-0 z-30 bg-white/90 backdrop-blur-xl border-b border-slate-200">

        <div class="px-5 md:px-8 py-4 flex justify-between items-center">

            <div class="flex items-center gap-4">

                <label
                    for="sidebarToggle"
                    class="lg:hidden p-2 rounded-xl hover:bg-slate-100 cursor-pointer"
                    aria-label="Open sidebar">

                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>

                </label>

                <div>

                    <p class="text-xs text-slate-400 font-medium">
                        Barangay Guindacpan / Administration
                    </p>

                    <h2 class="font-bold text-lg md:text-xl text-slate-900">
                        Dashboard Overview
                    </h2>

                </div>

            </div>

            <div class="flex items-center gap-3">

                <div class="hidden sm:block text-right">

                    <p class="text-sm font-bold text-slate-800">
                        <?= e($adminName) ?>
                    </p>

                    <p class="text-xs text-slate-400">
                        Administrator
                    </p>

                </div>

                <div class="w-10 h-10 rounded-full bg-green-100 text-green-800 flex items-center justify-center font-bold">
                    <?= e($initial) ?>
                </div>

            </div>

        </div>

    </header>


    <!-- PAGE CONTENT -->

    <div class="p-5 md:p-8 max-w-[1600px] mx-auto">

        <!-- WELCOME BANNER -->

        <section id="overview" class="mb-9">

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
                        Manage resident information, monitor registrations,
                        and oversee barangay services through your administration dashboard.
                    </p>

                    <a
                        href="#residents"
                        class="inline-flex items-center gap-2 mt-6 bg-white text-green-900 px-5 py-3 rounded-xl text-sm font-bold hover:bg-green-50 transition">

                        Explore Residents
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

            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-2xl mb-6 text-sm">
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
                        Monitor the current status of resident accounts.
                    </p>

                </div>

                <span class="text-xs text-slate-400">
                    <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>
                    Database records on page load
                </span>

            </div>


            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">

                <!-- TOTAL -->

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
                        All registered resident accounts
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
                                <?= number_format($pendingResidents) ?>
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
                                <?= number_format($approvedResidents) ?>
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


                <!-- REJECTED -->

                <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6">

                    <div class="flex items-center justify-between">

                        <div>

                            <p class="text-sm font-medium text-slate-500">
                                Rejected Accounts
                            </p>

                            <h3 class="text-4xl font-extrabold text-red-500 mt-3">
                                <?= number_format($rejectedResidents) ?>
                            </h3>

                        </div>

                        <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl">
                            ✕
                        </div>

                    </div>

                    <p class="text-xs text-slate-400 mt-5">
                        Rejected resident registrations
                    </p>

                </div>

            </div>

        </section>


        <!-- RESIDENT TABLE -->

        <section
            id="residents"
            class="bg-white border border-slate-200 rounded-2xl shadow-sm mb-8">

            <div class="p-6 border-b border-slate-100">

                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">

                    <div>

                        <h2 class="text-lg font-extrabold text-slate-900">
                            Recent Residents
                        </h2>

                        <p class="text-sm text-slate-400 mt-1">
                            Latest resident registrations from your database.
                        </p>

                    </div>


                    <!-- PHP SEARCH FORM -->

                    <form
                        action="dashboard.php"
                        method="GET"
                        class="flex flex-col sm:flex-row gap-2">

                        <input
                            type="search"
                            name="search"
                            value="<?= e($search) ?>"
                            maxlength="100"
                            placeholder="Search residents..."
                            class="w-full md:w-72 px-4 py-3 rounded-xl border border-slate-200 text-sm outline-none focus:border-green-600 focus:ring-2 focus:ring-green-100">

                        <button
                            type="submit"
                            class="px-5 py-3 rounded-xl bg-green-700 hover:bg-green-800 text-white text-sm font-bold transition">

                            Search

                        </button>

                        <?php if ($search !== ""): ?>

                            <a
                                href="dashboard.php"
                                class="px-5 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-bold text-center transition">

                                Clear

                            </a>

                        <?php endif; ?>

                    </form>

                </div>

            </div>


            <!-- TABLE -->

            <div class="overflow-x-auto">

                <table class="w-full min-w-[850px] text-left">

                    <thead class="bg-slate-50">

                        <tr class="text-xs uppercase tracking-wider text-slate-400">

                            <th class="py-4 px-6 font-semibold">
                                Resident ID
                            </th>

                            <th class="py-4 px-6 font-semibold">
                                Resident Name
                            </th>

                            <th class="py-4 px-6 font-semibold">
                                Username
                            </th>

                            <th class="py-4 px-6 font-semibold">
                                Address
                            </th>

                            <th class="py-4 px-6 font-semibold">
                                Registration
                            </th>

                            <th class="py-4 px-6 font-semibold">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php if (!empty($recentResidents)): ?>

                        <?php foreach ($recentResidents as $person): ?>

                            <?php

                            $name = $person["full_name"] ?? "N/A";
                            $address = $person["purok_address"] ?? "N/A";
                            $status = $person["status"] ?? "Not set";

                            $statusLower = strtolower(trim((string)$status));

                            $badgeClass = "bg-slate-100 text-slate-600";

                            if ($statusLower === "approved") {
                                $badgeClass = "bg-green-50 text-green-700";
                            } elseif ($statusLower === "rejected") {
                                $badgeClass = "bg-red-50 text-red-700";
                            } elseif ($statusLower === "pending") {
                                $badgeClass = "bg-amber-50 text-amber-700";
                            }

                            $registrationDate = "N/A";

                            if (!empty($person["created_at"])) {
                                $timestamp = strtotime($person["created_at"]);

                                if ($timestamp !== false) {
                                    $registrationDate = date("M d, Y", $timestamp);
                                }
                            }

                            $residentInitial = strtoupper(substr(trim((string)$name), 0, 1));

                            if ($residentInitial === "") {
                                $residentInitial = "R";
                            }

                            ?>

                            <tr class="resident-row border-b border-slate-100 hover:bg-slate-50">

                                <td class="py-5 px-6 text-sm text-slate-400 font-semibold">
                                    #<?= e($person["id"]) ?>
                                </td>

                                <td class="py-5 px-6">

                                    <div class="flex items-center gap-3">

                                        <div class="w-10 h-10 rounded-xl bg-green-50 text-green-700 flex items-center justify-center font-bold text-sm">
                                            <?= e($residentInitial) ?>
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

                                <td class="py-5 px-6 text-sm text-slate-500">
                                    <?= e($registrationDate) ?>
                                </td>

                                <td class="py-5 px-6">

                                    <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-bold <?= e($badgeClass) ?>">

                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>

                                        <?= e(ucfirst((string)$status)) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="6" class="text-center py-14">

                                <div class="text-4xl mb-3">
                                    👥
                                </div>

                                <p class="text-sm font-semibold text-slate-600">
                                    <?= $search !== "" ? "No matching residents found." : "No resident records found." ?>
                                </p>

                                <p class="text-xs text-slate-400 mt-1">
                                    <?= $search !== "" ? "Try another name, username, address, or resident ID." : "Registered residents will appear here." ?>
                                </p>

                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- TABLE FOOTER -->

            <div class="p-5 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-2">

                <p class="text-xs text-slate-400">

                    <?php if ($search !== ""): ?>

                        Search results for: <strong><?= e($search) ?></strong>

                    <?php else: ?>

                        Showing up to 8 recent resident records

                    <?php endif; ?>

                </p>

                <span class="text-xs font-semibold text-green-700">
                    <?= number_format($totalResidents) ?> Total Residents
                </span>

            </div>

        </section>


        <!-- OTHER MODULES -->

        <section class="mb-10">

            <div class="mb-5">

                <h2 class="text-xl font-extrabold text-slate-900">
                    Barangay Management Modules
                </h2>

                <p class="text-sm text-slate-400 mt-1">
                    Additional services available in the administration portal.
                </p>

            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">


                <!-- ACCOUNT VERIFICATION -->

                <div
                    id="verification"
                    class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                    <div class="flex items-center justify-between mb-5">

                        <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl">
                            🛡️
                        </div>

                        <span class="text-xs font-bold text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full">
                            Verification
                        </span>

                    </div>

                    <h3 class="font-bold text-slate-900">
                        Account Verification
                    </h3>

                    <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                        Review resident registrations and verify account information before approval.
                    </p>

                    <div class="flex items-center justify-between mt-5">

                        <span class="text-sm font-bold text-amber-600">
                            <?= number_format($pendingResidents) ?> Pending
                        </span>

                        <span class="text-xs text-slate-400">
                            Module not connected
                        </span>

                    </div>

                </div>


                <!-- DOCUMENT REQUESTS -->

                <div
                    id="requests"
                    class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                    <div class="flex items-center justify-between mb-5">

                        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
                            📄
                        </div>

                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-3 py-1.5 rounded-full">
                            Documents
                        </span>

                    </div>

                    <h3 class="font-bold text-slate-900">
                        Document Requests
                    </h3>

                    <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                        Manage barangay clearance, certificates, and other resident document requests.
                    </p>

                    <p class="text-xs text-blue-600 font-semibold mt-5">
                        Module not connected
                    </p>

                </div>


                <!-- ANNOUNCEMENTS -->

                <div
                    id="announcements"
                    class="bg-white border border-slate-200 rounded-2xl p-6 hover:shadow-md transition">

                    <div class="flex items-center justify-between mb-5">

                        <div class="w-12 h-12 rounded-2xl bg-orange-50 text-orange-600 flex items-center justify-center text-2xl">
                            📢
                        </div>

                        <span class="text-xs font-bold text-orange-600 bg-orange-50 px-3 py-1.5 rounded-full">
                            Community
                        </span>

                    </div>

                    <h3 class="font-bold text-slate-900">
                        Announcements
                    </h3>

                    <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                        Publish important barangay updates, community notices, and public information.
                    </p>

                    <p class="text-xs text-orange-600 font-semibold mt-5">
                        Module not connected
                    </p>

                </div>

            </div>

        </section>


        <!-- SYSTEM INFORMATION -->

        <section
            id="systemInfo"
            class="bg-white border border-slate-200 rounded-2xl p-6 mb-8">

            <h2 class="font-extrabold text-slate-900">
                System Information
            </h2>

            <p class="text-sm text-slate-400 mt-1">
                Administration portal details
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-6">

                <div>

                    <p class="text-xs text-slate-400 mb-1">
                        Application
                    </p>

                    <p class="text-sm font-semibold text-slate-700">
                        Barangay Management System
                    </p>

                </div>

                <div>

                    <p class="text-xs text-slate-400 mb-1">
                        Barangay
                    </p>

                    <p class="text-sm font-semibold text-slate-700">
                        Guindacpan
                    </p>

                </div>

                <div>

                    <p class="text-xs text-slate-400 mb-1">
                        Account Type
                    </p>

                    <p class="text-sm font-semibold text-green-700">
                        Administrator
                    </p>

                </div>

            </div>

        </section>


        <!-- FOOTER -->

        <footer class="border-t border-slate-200 pt-6 pb-5 text-center">

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

</body>
</html>
