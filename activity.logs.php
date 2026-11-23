
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR - ACTIVITY LOGS DASHBOARD
// MOBILE RESPONSIVE + HAMBURGER MENU
//
// Database: barangay_db
// Table: activity_logs
// Session: BARANGAY_ADMIN_SESSION
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER['HTTPS']) &&
           $_SERVER['HTTPS'] !== 'off';

if (session_status() === PHP_SESSION_NONE) {

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('BARANGAY_ADMIN_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
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
    header('Location: login.php');
    exit;
}

$adminName = $_SESSION['admin_name']
    ?? $_SESSION['full_name']
    ?? 'Administrator';


// ==================================================
// 3. DATABASE CONNECTION
// ==================================================

require_once __DIR__ . '/../config/database.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);


// ==================================================
// 4. HELPER FUNCTIONS
// ==================================================

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function scalarQuery(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


// ==================================================
// 5. DASHBOARD STATISTICS
// ==================================================

try {

    $totalActivities = scalarQuery(
        $pdo,
        "SELECT COUNT(*) FROM activity_logs"
    );

    $todayActivities = scalarQuery(
        $pdo,
        "SELECT COUNT(*) FROM activity_logs
         WHERE created_at >= CURDATE()
         AND created_at < CURDATE() + INTERVAL 1 DAY"
    );

    $residentActivities = scalarQuery(
        $pdo,
        "SELECT COUNT(*) FROM activity_logs
         WHERE account_type = 'Resident'"
    );

    $adminActivities = scalarQuery(
        $pdo,
        "SELECT COUNT(*) FROM activity_logs
         WHERE account_type IN ('Administrator', 'Admin')"
    );

    $bhwActivities = scalarQuery(
        $pdo,
        "SELECT COUNT(*) FROM activity_logs
         WHERE account_type = 'BHW'"
    );

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    exit('Unable to load activity statistics. Check activity_logs table.');
}


// ==================================================
// 6. SEARCH AND FILTERS
// ==================================================

$search = trim($_GET['search'] ?? '');

$accountType = trim($_GET['account_type'] ?? '');

$action = trim($_GET['action'] ?? '');

$dateFrom = trim($_GET['date_from'] ?? '');

$dateTo = trim($_GET['date_to'] ?? '');

$where = [];
$params = [];

if ($search !== '') {

    $where[] = "(
        account_name LIKE :search1
        OR description LIKE :search2
        OR action LIKE :search3
        OR ip_address LIKE :search4
    )";

    $keyword = '%' . $search . '%';

    $params[':search1'] = $keyword;
    $params[':search2'] = $keyword;
    $params[':search3'] = $keyword;
    $params[':search4'] = $keyword;
}

if ($accountType !== '') {

    $where[] = "account_type = :account_type";

    $params[':account_type'] = $accountType;
}

if ($action !== '') {

    $where[] = "action = :action";

    $params[':action'] = $action;
}

if ($dateFrom !== '') {

    $date = DateTime::createFromFormat('!Y-m-d', $dateFrom);

    if ($date && $date->format('Y-m-d') === $dateFrom) {

        $where[] = "created_at >= :date_from";

        $params[':date_from'] = $dateFrom . ' 00:00:00';
    }
}

if ($dateTo !== '') {

    $date = DateTime::createFromFormat('!Y-m-d', $dateTo);

    if ($date && $date->format('Y-m-d') === $dateTo) {

        $where[] = "created_at < :date_to";

        $params[':date_to'] = date(
            'Y-m-d',
            strtotime($dateTo . ' +1 day')
        );
    }
}

$whereSQL = !empty($where)
    ? ' WHERE ' . implode(' AND ', $where)
    : '';


// ==================================================
// 7. PAGINATION
// ==================================================

$perPage = 15;

$page = max(1, (int) ($_GET['page'] ?? 1));

$countSQL = "
    SELECT COUNT(*)
    FROM activity_logs
    $whereSQL
";

$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);

$filteredTotal = (int) $countStmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($filteredTotal / $perPage)
);

$page = min($page, $totalPages);

$offset = ($page - 1) * $perPage;


// ==================================================
// 8. FETCH ACTIVITY RECORDS
// ==================================================

$sql = "
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
    $whereSQL
    ORDER BY created_at DESC, id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();

$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ==================================================
// 9. FETCH AVAILABLE ACTIONS
// ==================================================

$actionStmt = $pdo->query("
    SELECT DISTINCT action
    FROM activity_logs
    ORDER BY action ASC
");

$availableActions = $actionStmt->fetchAll(PDO::FETCH_COLUMN);


// ==================================================
// 10. PAGINATION URL
// ==================================================

function pageUrl(int $page): string
{
    $query = $_GET;
    $query['page'] = $page;

    return '?' . http_build_query($query);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Activity Logs | Barangay Guindacpan</title>

<script src="https://cdn.tailwindcss.com"></script>

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

.table-row:hover {
    background: #f8fafc;
}

/* MOBILE OVERLAY */

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

/* SIDEBAR */

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

}

@media (min-width: 1024px) {

    .sidebar {
        position: sticky;
        top: 0;
        height: 100vh;
        min-height: 100vh;
    }

}

</style>

</head>


<body class="text-gray-800">


<!-- ==================================================
     MOBILE OVERLAY
================================================== -->

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

            <div>

                <div class="flex items-center gap-3">

                    <div class="w-11 h-11 rounded-xl bg-blue-600 flex items-center justify-center flex-shrink-0">

                        <span class="text-xl font-bold">B</span>

                    </div>

                    <div>

                        <h1 class="text-xl font-extrabold tracking-wide">
                            BARANGAY
                        </h1>

                        <p class="text-blue-400 text-xs font-semibold mt-1">
                            GUINDACPAN MANAGEMENT
                        </p>

                    </div>

                </div>

            </div>


            <!-- CLOSE BUTTON -->

            <button
                type="button"
                id="closeMenu"
                class="lg:hidden text-gray-400 hover:text-white text-3xl px-2"
                aria-label="Close navigation menu"
            >
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
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-gray-300">

            <span>◷</span>

            <span class="text-sm font-medium">
                Activity Dashboard
            </span>

        </a>


        <a href="activity_logs.php"
           class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold">

            <span>☷</span>

            <span class="text-sm font-semibold">
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

                <button
                    type="button"
                    id="openMenu"
                    class="w-10 h-10 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-800 text-2xl"
                    aria-label="Open navigation menu"
                    aria-expanded="false"
                    aria-controls="sidebar"
                >
                    &#9776;
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

        <header class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">

            <div>

                <p class="text-sm text-gray-500 mb-1">
                    Administrator / Monitoring
                </p>

                <h2 class="text-2xl md:text-3xl font-extrabold text-gray-900">
                    Activity Logs
                </h2>

                <p class="text-gray-500 text-sm mt-2">
                    Monitor and review activities across barangay accounts.
                </p>

            </div>


            <div class="bg-white border rounded-xl px-4 py-3">

                <p class="text-xs text-gray-400">
                    Current Date
                </p>

                <p class="font-semibold text-sm">
                    <?= date('F d, Y') ?>
                </p>

            </div>

        </header>


        <!-- ==================================================
             STATISTICS
        ================================================== -->

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">


            <!-- TOTAL ACTIVITIES -->

            <div class="stat-card bg-white border rounded-2xl p-5">

                <div class="flex justify-between items-center">

                    <p class="text-sm text-gray-500 font-medium">
                        Total Activities
                    </p>

                    <span class="bg-blue-50 text-blue-600 p-3 rounded-xl">
                        ◷
                    </span>

                </div>

                <h3 class="text-3xl font-extrabold mt-4">
                    <?= number_format($totalActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    All recorded activities
                </p>

            </div>


            <!-- TODAY -->

            <div class="stat-card bg-white border rounded-2xl p-5">

                <div class="flex justify-between items-center">

                    <p class="text-sm text-gray-500 font-medium">
                        Today's Activities
                    </p>

                    <span class="bg-green-50 text-green-600 p-3 rounded-xl">
                        ✓
                    </span>

                </div>

                <h3 class="text-3xl font-extrabold mt-4">
                    <?= number_format($todayActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Activities recorded today
                </p>

            </div>


            <!-- RESIDENT -->

            <div class="stat-card bg-white border rounded-2xl p-5">

                <div class="flex justify-between items-center">

                    <p class="text-sm text-gray-500 font-medium">
                        Resident Activities
                    </p>

                    <span class="bg-purple-50 text-purple-600 p-3 rounded-xl">
                        ♙
                    </span>

                </div>

                <h3 class="text-3xl font-extrabold mt-4">
                    <?= number_format($residentActivities) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Resident account actions
                </p>

            </div>


            <!-- STAFF -->

            <div class="stat-card bg-white border rounded-2xl p-5">

                <div class="flex justify-between items-center">

                    <p class="text-sm text-gray-500 font-medium">
                        Staff Activities
                    </p>

                    <span class="bg-orange-50 text-orange-600 p-3 rounded-xl">
                        ♧
                    </span>

                </div>

                <h3 class="text-3xl font-extrabold mt-4">
                    <?= number_format(
                        $adminActivities + $bhwActivities
                    ) ?>
                </h3>

                <p class="text-xs text-gray-400 mt-2">
                    Admin: <?= number_format($adminActivities) ?>
                    · BHW: <?= number_format($bhwActivities) ?>
                </p>

            </div>

        </section>


        <!-- ==================================================
             SEARCH AND FILTERS
        ================================================== -->

        <section class="bg-white border rounded-2xl p-4 sm:p-5 mb-6">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">

                <div>

                    <h3 class="font-bold text-gray-900">
                        Activity History
                    </h3>

                    <p class="text-xs text-gray-400 mt-1">
                        Search and filter recorded activities.
                    </p>

                </div>

                <span class="text-xs bg-blue-50 text-blue-700 px-3 py-2 rounded-lg font-semibold w-fit">

                    <?= number_format($filteredTotal) ?> Records

                </span>

            </div>


            <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">


                <input
                    type="text"
                    name="search"
                    value="<?= e($search) ?>"
                    placeholder="Search account or activity..."
                    class="w-full min-w-0 border rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >


                <select
                    name="account_type"
                    class="w-full min-w-0 border rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >

                    <option value="">All Accounts</option>

                    <option value="Resident" <?= $accountType === 'Resident' ? 'selected' : '' ?>>
                        Resident
                    </option>

                    <option value="Administrator" <?= $accountType === 'Administrator' ? 'selected' : '' ?>>
                        Administrator
                    </option>

                    <option value="BHW" <?= $accountType === 'BHW' ? 'selected' : '' ?>>
                        BHW
                    </option>

                </select>


                <select
                    name="action"
                    class="w-full min-w-0 border rounded-xl px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500"
                >

                    <option value="">All Activities</option>

                    <?php foreach ($availableActions as $availableAction): ?>

                        <option
                            value="<?= e($availableAction) ?>"
                            <?= $action === $availableAction ? 'selected' : '' ?>
                        >
                            <?= e($availableAction) ?>
                        </option>

                    <?php endforeach; ?>

                </select>


                <input
                    type="date"
                    name="date_from"
                    value="<?= e($dateFrom) ?>"
                    class="w-full min-w-0 border rounded-xl px-4 py-3 text-sm"
                    title="Date from"
                >


                <input
                    type="date"
                    name="date_to"
                    value="<?= e($dateTo) ?>"
                    class="w-full min-w-0 border rounded-xl px-4 py-3 text-sm"
                    title="Date to"
                >


                <div class="flex flex-col sm:flex-row gap-2 sm:col-span-2 xl:col-span-5">

                    <button
                        type="submit"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl text-sm font-semibold"
                    >
                        Search / Filter
                    </button>


                    <a
                        href="activity_logs.php"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-6 py-3 rounded-xl text-sm font-semibold text-center"
                    >
                        Reset
                    </a>

                </div>

            </form>

        </section>


        <!-- ==================================================
             ACTIVITY TABLE
        ================================================== -->

        <section class="bg-white border rounded-2xl overflow-hidden">


            <div class="p-4 sm:p-5 border-b">

                <h3 class="font-bold text-gray-900">
                    Recorded Activities
                </h3>

                <p class="text-xs text-gray-400 mt-1">
                    Review account activity details and timestamps.
                </p>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full min-w-[850px] text-sm text-left">

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
                                IP Address
                            </th>

                            <th class="px-5 py-4">
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        <?php if (!empty($activities)): ?>

                            <?php foreach ($activities as $row): ?>

                                <?php

                                // PHP 7 COMPATIBLE ACCOUNT TYPE COLORS

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


                                <tr class="table-row">


                                    <!-- ACCOUNT -->

                                    <td class="px-5 py-4">

                                        <p class="font-semibold text-gray-900">
                                            <?= e($row['account_name']) ?>
                                        </p>

                                        <p class="text-xs text-gray-400 mt-1">

                                            Account ID:
                                            <?= e($row['resident_id'] ?? 'N/A') ?>

                                        </p>

                                    </td>


                                    <!-- ACCOUNT TYPE -->

                                    <td class="px-5 py-4">

                                        <span class="px-3 py-1 rounded-full text-xs font-semibold <?= e($typeColor) ?>">

                                            <?= e($row['account_type']) ?>

                                        </span>

                                    </td>


                                    <!-- ACTIVITY -->

                                    <td class="px-5 py-4 font-semibold text-gray-700">

                                        <?= e($row['action']) ?>

                                    </td>


                                    <!-- DESCRIPTION -->

                                    <td class="px-5 py-4 text-gray-500 min-w-64">

                                        <?= e($row['description']) ?>

                                    </td>


                                    <!-- IP ADDRESS -->

                                    <td class="px-5 py-4 text-gray-500 whitespace-nowrap">

                                        <?= e($row['ip_address'] ?: 'N/A') ?>

                                    </td>


                                    <!-- DATE -->

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

                                <td colspan="6" class="px-5 py-16 text-center">

                                    <div class="text-4xl mb-3 text-gray-300">
                                        ◷
                                    </div>

                                    <h3 class="font-bold text-gray-700">
                                        No Activity Records Found
                                    </h3>

                                    <p class="text-gray-400 text-sm mt-2">
                                        No activities match your search or filters.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>


            <!-- ==================================================
                 PAGINATION
            ================================================== -->

            <div class="border-t px-4 sm:px-5 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">

                <p class="text-xs text-gray-500 text-center sm:text-left">

                    Showing

                    <?= $filteredTotal > 0 ? $offset + 1 : 0 ?>

                    to

                    <?= min($offset + $perPage, $filteredTotal) ?>

                    of

                    <?= number_format($filteredTotal) ?>

                    records

                </p>


                <div class="flex flex-wrap justify-center gap-2">

                    <?php if ($page > 1): ?>

                        <a
                            href="<?= e(pageUrl($page - 1)) ?>"
                            class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"
                        >
                            Previous
                        </a>

                    <?php endif; ?>


                    <span class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold">

                        <?= $page ?> / <?= $totalPages ?>

                    </span>


                    <?php if ($page < $totalPages): ?>

                        <a
                            href="<?= e(pageUrl($page + 1)) ?>"
                            class="px-4 py-2 border rounded-lg text-sm hover:bg-gray-50"
                        >
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        </section>


        <!-- FOOTER -->

        <footer class="text-center text-xs text-gray-400 mt-8 pb-4">

            Barangay Guindacpan Management System

            <br>

            Administrator Activity Monitoring

        </footer>


    </div>

</main>

</div>


<!-- ==================================================
     HAMBURGER MENU JAVASCRIPT
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


// OPEN MENU

openMenu.addEventListener('click', openSidebar);


// CLOSE BUTTON

closeMenu.addEventListener('click', closeSidebar);


// CLOSE WHEN CLICKING OUTSIDE SIDEBAR

mobileOverlay.addEventListener('click', closeSidebar);


// CLOSE WHEN CLICKING NAVIGATION LINKS

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


// RESET MENU ON DESKTOP SCREEN

window.addEventListener('resize', function() {

    if (window.innerWidth >= 1024) {

        closeSidebar();

    }

});

</script>


</body>
</html>
