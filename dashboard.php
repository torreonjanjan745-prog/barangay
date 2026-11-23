
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ACTIVITY MONITORING DASHBOARD
// RESPONSIVE HAMBURGER MENU
// Database: barangay_db
// Connection: db.php (same folder)
// Table: activity_logs
// Session: BARANGAY_ADMIN_SESSION
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER['HTTPS']) &&
           $_SERVER['HTTPS'] !== 'off';

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
        "samesite" => "Lax"
    ]);

    session_start();
}


// ==================================================
// 2. ADMIN AUTHENTICATION
// ==================================================

if (
    empty($_SESSION['admin_id']) &&
    empty($_SESSION['admin_name']) &&
    empty($_SESSION['full_name'])
) {
    header("Location: login.php");
    exit;
}

$adminName = $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? 'Administrator';


// ==================================================
// 3. DATABASE CONNECTION
// ==================================================

require_once __DIR__ . '/db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection unavailable.");
}


// ==================================================
// 4. HELPER FUNCTION
// ==================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


// ==================================================
// 5. INITIALIZE STATISTICS
// ==================================================

$totalActivities = 0;
$todayActivities = 0;
$residentActivities = 0;
$adminActivities = 0;
$bhwActivities = 0;

$recentActivities = [];
$dailyLabels = [];
$dailyCounts = [];
$typeLabels = [];
$typeCounts = [];

$databaseError = false;


// ==================================================
// 6. GET DASHBOARD DATA
// ==================================================

try {

    // TOTAL ACTIVITIES

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
    ");

    $totalActivities = (int)$stmt->fetchColumn();


    // TODAY'S ACTIVITIES

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE created_at >= CURDATE()
        AND created_at < CURDATE() + INTERVAL 1 DAY
    ");

    $todayActivities = (int)$stmt->fetchColumn();


    // RESIDENT ACTIVITIES

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE account_type = 'Resident'
    ");

    $residentActivities = (int)$stmt->fetchColumn();


    // ADMINISTRATOR ACTIVITIES

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE account_type IN ('Administrator', 'Admin')
    ");

    $adminActivities = (int)$stmt->fetchColumn();


    // BHW ACTIVITIES

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE account_type = 'BHW'
    ");

    $bhwActivities = (int)$stmt->fetchColumn();


    // DAILY ACTIVITY - LAST 7 DAYS

    $stmt = $pdo->query("
        SELECT
            DATE(created_at) AS activity_date,
            COUNT(*) AS total
        FROM activity_logs
        WHERE created_at >= CURDATE() - INTERVAL 6 DAY
        AND created_at < CURDATE() + INTERVAL 1 DAY
        GROUP BY DATE(created_at)
        ORDER BY activity_date ASC
    ");

    $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dailyMap = [];

    foreach ($dailyRows as $row) {
        $dailyMap[$row['activity_date']] = (int)$row['total'];
    }

    for ($i = 6; $i >= 0; $i--) {

        $date = date('Y-m-d', strtotime("-$i days"));

        $dailyLabels[] = date('M d', strtotime($date));

        $dailyCounts[] = $dailyMap[$date] ?? 0;
    }


    // ACCOUNT TYPE DISTRIBUTION

    $stmt = $pdo->query("
        SELECT
            account_type,
            COUNT(*) AS total
        FROM activity_logs
        GROUP BY account_type
        ORDER BY total DESC
    ");

    $typeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($typeRows as $row) {

        $typeLabels[] = $row['account_type'];

        $typeCounts[] = (int)$row['total'];
    }


    // RECENT ACTIVITIES

    $stmt = $pdo->query("
        SELECT
            id,
            resident_id,
            account_name,
            account_type,
            action,
            description,
            ip_address,
            created_at
        FROM activity_logs
        ORDER BY created_at DESC, id DESC
        LIMIT 10
    ");

    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    error_log(
        "Activity Dashboard Error: " . $e->getMessage()
    );

    $databaseError = true;
}


// ==================================================
// 7. CHART DATA
// ==================================================

$dailyLabelsJSON = json_encode(
    $dailyLabels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$dailyCountsJSON = json_encode($dailyCounts);

$typeLabelsJSON = json_encode(
    $typeLabels,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
);

$typeCountsJSON = json_encode($typeCounts);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Activity Dashboard | Barangay Guindacpan</title>

<script src="https://cdn.tailwindcss.com"></script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>

* {
    font-family: 'Inter', sans-serif;
    box-sizing: border-box;
}

body {
    background: #f5f7fb;
    overflow-x: hidden;
}

.sidebar {
    background: #111827;
}

.nav-link {
    transition: all .2s ease;
}

.nav-link:hover {
    background: #25334a;
}

.stat-card {
    transition: transform .2s ease, box-shadow .2s ease;
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 25px rgba(0,0,0,.05);
}

.chart-container {
    position: relative;
    height: 280px;
    width: 100%;
}

/* MOBILE SIDEBAR */

.mobile-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 40;
    opacity: 0;
    visibility: hidden;
    transition: all .3s ease;
}

.mobile-overlay.show {
    opacity: 1;
    visibility: visible;
}

.sidebar {
    z-index: 50;
}

@media (max-width: 1023px) {

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        max-width: 85%;
        height: 100vh;
        overflow-y: auto;
        transform: translateX(-100%);
        transition: transform .3s ease;
    }

    .sidebar.open {
        transform: translateX(0);
    }

    body.menu-open {
        overflow: hidden;
    }

    .chart-container {
        height: 250px;
    }

}

@media (max-width: 640px) {

    .chart-container {
        height: 230px;
    }

}

</style>

</head>


<body class="text-gray-800">


<!-- MOBILE OVERLAY -->

<div id="mobileOverlay"
     class="mobile-overlay"
     aria-hidden="true">
</div>


<div class="min-h-screen flex">


<!-- ==================================================
     SIDEBAR
================================================== -->

<aside id="sidebar"
       class="sidebar w-64 min-h-screen text-white flex flex-col flex-shrink-0">


    <!-- LOGO -->

    <div class="p-6 border-b border-gray-700">

        <div class="flex items-center justify-between gap-3">

            <div class="flex items-center gap-3">

                <div class="w-11 h-11 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0">

                    <span class="text-xl font-bold">B</span>

                </div>

                <div>

                    <h1 class="font-extrabold tracking-wide">
                        BARANGAY
                    </h1>

                    <p class="text-blue-400 text-xs font-semibold">
                        GUINDACPAN SYSTEM
                    </p>

                </div>

            </div>


            <!-- MOBILE CLOSE BUTTON -->

            <button type="button"
                    id="closeMenu"
                    class="lg:hidden text-gray-400 hover:text-white text-2xl px-2"
                    aria-label="Close navigation menu">

                &times;

            </button>

        </div>

    </div>


    <!-- NAVIGATION -->

    <nav class="p-4 space-y-2 flex-1">

        <p class="text-gray-500 text-xs uppercase tracking-widest px-3 py-3">
            Main Menu
        </p>


        <a href="dashboard.php"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-300">

            <span>▦</span>

            <span class="text-sm font-medium">
                Dashboard
            </span>

        </a>


        <p class="text-gray-500 text-xs uppercase tracking-widest px-3 pt-6 pb-2">
            Monitoring
        </p>


        <a href="activity_dashboard.php"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-600 text-white">

            <span>◷</span>

            <span class="text-sm font-semibold">
                Activity Dashboard
            </span>

        </a>


        <a href="activity_logs.php"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-300">

            <span>☷</span>

            <span class="text-sm font-medium">
                Activity History
            </span>

        </a>


        <p class="text-gray-500 text-xs uppercase tracking-widest px-3 pt-6 pb-2">
            Account
        </p>


        <a href="logout.php"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-300">

            <span>↪</span>

            <span class="text-sm font-medium">
                Logout
            </span>

        </a>

    </nav>


    <!-- ADMIN PROFILE -->

    <div class="p-5 border-t border-gray-700">

        <p class="text-xs text-gray-500">
            Logged in as
        </p>

        <p class="font-semibold text-sm mt-1 break-words">
            <?= e($adminName) ?>
        </p>

        <p class="text-blue-400 text-xs mt-1">
            Administrator
        </p>

    </div>

</aside>


<!-- ==================================================
     MAIN CONTENT
================================================== -->

<main class="flex-1 min-w-0 w-full">


    <!-- MOBILE TOP NAVIGATION -->

    <div class="lg:hidden bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-30">

        <div class="flex items-center justify-between gap-3">

            <div class="flex items-center gap-3">

                <button type="button"
                        id="openMenu"
                        class="w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-800 text-2xl"
                        aria-label="Open navigation menu"
                        aria-expanded="false"
                        aria-controls="sidebar">

                    <span>&#9776;</span>

                </button>

                <div>

                    <h1 class="font-extrabold text-gray-900 text-sm">
                        BARANGAY GUINDACPAN
                    </h1>

                    <p class="text-xs text-gray-400">
                        Administrator Panel
                    </p>

                </div>

            </div>


            <div class="w-9 h-9 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold flex-shrink-0">

                <?= e(strtoupper(substr($adminName, 0, 1))) ?>

            </div>

        </div>

    </div>


    <!-- PAGE CONTENT -->

    <div class="p-4 sm:p-5 md:p-8">


        <!-- HEADER -->

        <header class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-5 mb-8">

            <div>

                <p class="text-sm text-gray-400 mb-2">
                    Administrator / Monitoring
                </p>

                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">
                    Activity Monitoring Dashboard
                </h2>

                <p class="text-sm text-gray-500 mt-2">
                    Monitor all recorded activities across barangay accounts.
                </p>

            </div>


            <div class="flex flex-wrap items-center gap-3">

                <div class="bg-white border rounded-xl px-4 py-3">

                    <p class="text-xs text-gray-400">
                        Today's Date
                    </p>

                    <p class="text-sm font-bold">
                        <?= date('F d, Y') ?>
                    </p>

                </div>


                <a href="activity_dashboard.php"
                   class="bg-blue-600 hover:bg-blue-700 text-white rounded-xl px-4 py-3 text-sm font-semibold text-center">

                    ↻ Refresh

                </a>

            </div>

        </header>


        <?php if ($databaseError): ?>

            <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-5 mb-6">

                <h3 class="font-bold">
                    Unable to load dashboard data
                </h3>

                <p class="text-sm mt-1">
                    Please check the activity_logs table and database connection.
                </p>

            </div>

        <?php endif; ?>


        <!-- ==================================================
             STATISTICS CARDS
        ================================================== -->

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-7">


            <!-- TOTAL ACTIVITIES -->

            <div class="stat-card bg-white border border-gray-100 rounded-2xl p-5">

                <div class="flex items-center justify-between">

                    <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl">
                        ◷
                    </div>

                    <span class="text-xs text-gray-400">
                        All time
                    </span>

                </div>

                <p class="text-sm text-gray-500 mt-5">
                    Total Activities
                </p>

                <h3 class="text-3xl font-extrabold mt-1">
                    <?= number_format($totalActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Total recorded actions
                </p>

            </div>


            <!-- TODAY -->

            <div class="stat-card bg-white border border-gray-100 rounded-2xl p-5">

                <div class="flex items-center justify-between">

                    <div class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-xl">
                        ✓
                    </div>

                    <span class="text-xs text-green-600 font-semibold">
                        Today
                    </span>

                </div>

                <p class="text-sm text-gray-500 mt-5">
                    Today's Activities
                </p>

                <h3 class="text-3xl font-extrabold mt-1">
                    <?= number_format($todayActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Actions recorded today
                </p>

            </div>


            <!-- RESIDENT -->

            <div class="stat-card bg-white border border-gray-100 rounded-2xl p-5">

                <div class="flex items-center justify-between">

                    <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                        ♙
                    </div>

                    <span class="text-xs text-gray-400">
                        Residents
                    </span>

                </div>

                <p class="text-sm text-gray-500 mt-5">
                    Resident Activities
                </p>

                <h3 class="text-3xl font-extrabold mt-1">
                    <?= number_format($residentActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Resident account actions
                </p>

            </div>


            <!-- STAFF -->

            <div class="stat-card bg-white border border-gray-100 rounded-2xl p-5">

                <div class="flex items-center justify-between">

                    <div class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-xl">
                        ♧
                    </div>

                    <span class="text-xs text-gray-400">
                        Staff
                    </span>

                </div>

                <p class="text-sm text-gray-500 mt-5">
                    Staff Activities
                </p>

                <h3 class="text-3xl font-extrabold mt-1">
                    <?= number_format($adminActivities + $bhwActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Admin and BHW actions
                </p>

            </div>

        </section>


        <!-- ==================================================
             CHARTS
        ================================================== -->

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-7">


            <!-- DAILY ACTIVITY CHART -->

            <div class="xl:col-span-2 bg-white border border-gray-100 rounded-2xl p-5 md:p-6 min-w-0">

                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">

                    <div>

                        <h3 class="font-bold text-gray-900">
                            Daily Activity Overview
                        </h3>

                        <p class="text-xs text-gray-400 mt-1">
                            Recorded activities over the last 7 days
                        </p>

                    </div>

                    <span class="text-xs bg-blue-50 text-blue-600 px-3 py-2 rounded-lg">
                        Last 7 Days
                    </span>

                </div>

                <div class="chart-container">

                    <canvas id="dailyActivityChart"></canvas>

                </div>

            </div>


            <!-- ACCOUNT TYPE CHART -->

            <div class="bg-white border border-gray-100 rounded-2xl p-5 md:p-6 min-w-0">

                <div class="mb-6">

                    <h3 class="font-bold text-gray-900">
                        Account Distribution
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Activities by account type
                    </p>

                </div>

                <div class="chart-container">

                    <canvas id="accountTypeChart"></canvas>

                </div>

            </div>

        </section>


        <!-- ==================================================
             ACCOUNT ACTIVITY SUMMARY
        ================================================== -->

        <section class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-7">


            <div class="bg-white border rounded-2xl p-5">

                <div class="flex items-center gap-3">

                    <div class="bg-blue-50 text-blue-600 w-11 h-11 rounded-xl flex items-center justify-center text-xl">
                        A
                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Administrator
                        </p>

                        <h3 class="text-2xl font-extrabold">
                            <?= number_format($adminActivities) ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="bg-white border rounded-2xl p-5">

                <div class="flex items-center gap-3">

                    <div class="bg-green-50 text-green-600 w-11 h-11 rounded-xl flex items-center justify-center text-xl">
                        B
                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Barangay Health Worker
                        </p>

                        <h3 class="text-2xl font-extrabold">
                            <?= number_format($bhwActivities) ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="bg-white border rounded-2xl p-5">

                <div class="flex items-center gap-3">

                    <div class="bg-purple-50 text-purple-600 w-11 h-11 rounded-xl flex items-center justify-center text-xl">
                        R
                    </div>

                    <div>

                        <p class="text-sm text-gray-500">
                            Resident
                        </p>

                        <h3 class="text-2xl font-extrabold">
                            <?= number_format($residentActivities) ?>
                        </h3>

                    </div>

                </div>

            </div>

        </section>


        <!-- ==================================================
             RECENT ACTIVITIES
        ================================================== -->

        <section class="bg-white border border-gray-100 rounded-2xl overflow-hidden">


            <div class="p-5 md:p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

                <div>

                    <h3 class="font-bold text-gray-900">
                        Recent Account Activities
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Latest 10 recorded system activities
                    </p>

                </div>


                <a href="activity_logs.php"
                   class="bg-blue-50 hover:bg-blue-100 text-blue-600 rounded-xl px-4 py-2.5 text-sm font-semibold text-center">

                    View All Activities →

                </a>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full min-w-[750px] text-sm text-left">

                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">

                        <tr>

                            <th class="px-5 py-4">
                                Account
                            </th>

                            <th class="px-5 py-4">
                                Account Type
                            </th>

                            <th class="px-5 py-4">
                                Activity
                            </th>

                            <th class="px-5 py-4">
                                Description
                            </th>

                            <th class="px-5 py-4">
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        <?php if (!empty($recentActivities)): ?>

                            <?php foreach ($recentActivities as $row): ?>

                                <?php

                                $typeColor = 'bg-gray-100 text-gray-700';

                                if ($row['account_type'] === 'Resident') {

                                    $typeColor = 'bg-purple-50 text-purple-700';

                                } elseif (
                                    $row['account_type'] === 'Administrator' ||
                                    $row['account_type'] === 'Admin'
                                ) {

                                    $typeColor = 'bg-blue-50 text-blue-700';

                                } elseif ($row['account_type'] === 'BHW') {

                                    $typeColor = 'bg-green-50 text-green-700';

                                }

                                ?>

                                <tr class="hover:bg-gray-50 transition">

                                    <td class="px-5 py-4">

                                        <p class="font-semibold text-gray-800">
                                            <?= e($row['account_name']) ?>
                                        </p>

                                        <p class="text-xs text-gray-400 mt-1">
                                            Log #<?= e($row['id']) ?>
                                        </p>

                                    </td>


                                    <td class="px-5 py-4">

                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= e($typeColor) ?>">

                                            <?= e($row['account_type']) ?>

                                        </span>

                                    </td>


                                    <td class="px-5 py-4 font-semibold text-gray-700">

                                        <?= e($row['action']) ?>

                                    </td>


                                    <td class="px-5 py-4 text-gray-500 min-w-64">

                                        <?= e($row['description']) ?>

                                    </td>


                                    <td class="px-5 py-4 text-gray-500 whitespace-nowrap">

                                        <?= e(date(
                                            'M d, Y h:i A',
                                            strtotime($row['created_at'])
                                        )) ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="5" class="px-5 py-16 text-center">

                                    <div class="text-4xl text-gray-300 mb-3">
                                        ◷
                                    </div>

                                    <h3 class="font-bold text-gray-700">
                                        No Activities Yet
                                    </h3>

                                    <p class="text-sm text-gray-400 mt-2">
                                        Account activities will appear here once recorded.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- FOOTER -->

        <footer class="text-center text-xs text-gray-400 mt-8 pb-4">

            Barangay Guindacpan Management System

            <br>

            Activity Monitoring Dashboard

        </footer>


    </div>

</main>

</div>


<!-- ==================================================
     MOBILE HAMBURGER MENU SCRIPT
================================================== -->

<script>

const sidebar = document.getElementById('sidebar');

const openMenu = document.getElementById('openMenu');

const closeMenu = document.getElementById('closeMenu');

const mobileOverlay = document.getElementById('mobileOverlay');


function openSidebar() {

    sidebar.classList.add('open');

    mobileOverlay.classList.add('show');

    document.body.classList.add('menu-open');

    openMenu.setAttribute('aria-expanded', 'true');

    mobileOverlay.setAttribute('aria-hidden', 'false');

}


function closeSidebar() {

    sidebar.classList.remove('open');

    mobileOverlay.classList.remove('show');

    document.body.classList.remove('menu-open');

    openMenu.setAttribute('aria-expanded', 'false');

    mobileOverlay.setAttribute('aria-hidden', 'true');

}


openMenu.addEventListener('click', openSidebar);

closeMenu.addEventListener('click', closeSidebar);

mobileOverlay.addEventListener('click', closeSidebar);


// CLOSE WHEN CLICKING A NAVIGATION LINK ON MOBILE

const sidebarLinks = sidebar.querySelectorAll('nav a');

sidebarLinks.forEach(function(link) {

    link.addEventListener('click', function() {

        closeSidebar();

    });

});


// CLOSE USING ESCAPE KEY

document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {

        closeSidebar();

    }

});


// RESET SIDEBAR WHEN SCREEN BECOMES DESKTOP

window.addEventListener('resize', function() {

    if (window.innerWidth >= 1024) {

        closeSidebar();

    }

});

</script>


<!-- ==================================================
     CHART.JS
================================================== -->

<script>

// DAILY ACTIVITY DATA

const dailyLabels = <?= $dailyLabelsJSON ?: '[]' ?>;

const dailyCounts = <?= $dailyCountsJSON ?: '[]' ?>;


// DAILY ACTIVITY LINE CHART

const dailyCanvas = document.getElementById('dailyActivityChart');

if (dailyCanvas) {

    new Chart(dailyCanvas, {

        type: 'line',

        data: {

            labels: dailyLabels,

            datasets: [{

                label: 'Activities',

                data: dailyCounts,

                borderColor: '#2563eb',

                backgroundColor: 'rgba(37, 99, 235, 0.10)',

                borderWidth: 3,

                fill: true,

                tension: 0.4,

                pointRadius: 4,

                pointHoverRadius: 6,

                pointBackgroundColor: '#2563eb'

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    display: false
                }

            },

            scales: {

                y: {

                    beginAtZero: true,

                    ticks: {
                        precision: 0
                    },

                    grid: {
                        color: '#f1f5f9'
                    }

                },

                x: {

                    grid: {
                        display: false
                    }

                }

            }

        }

    });

}


// ACCOUNT TYPE DISTRIBUTION

const typeLabels = <?= $typeLabelsJSON ?: '[]' ?>;

const typeCounts = <?= $typeCountsJSON ?: '[]' ?>;


const accountCanvas = document.getElementById('accountTypeChart');

if (accountCanvas) {

    new Chart(accountCanvas, {

        type: 'doughnut',

        data: {

            labels: typeLabels,

            datasets: [{

                data: typeCounts,

                backgroundColor: [
                    '#2563eb',
                    '#9333ea',
                    '#16a34a',
                    '#f97316',
                    '#64748b'
                ],

                borderWidth: 0,

                hoverOffset: 6

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: '68%',

            plugins: {

                legend: {

                    position: 'bottom',

                    labels: {

                        usePointStyle: true,

                        padding: 18

                    }

                }

            }

        }

    });

}

</script>

</body>
</html>
