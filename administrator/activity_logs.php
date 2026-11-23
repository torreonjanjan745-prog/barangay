
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR - ACTIVITY LOGS DASHBOARD
//
// Database: barangay_db
// Table: activity_logs
// Connection: ../db.php
// PDO Variable: $pdo
// Session: BARANGAY_ADMIN_SESSION
//
// FEATURES:
// - Activity Statistics
// - Search Activity Logs
// - Filter by Account Type
// - Filter by Action
// - Filter by Date
// - Pagination
// - Print Activity Logs
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER["HTTPS"]) &&
           $_SERVER["HTTPS"] !== "off";

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


// ==================================================
// 2. AUTHENTICATION
// ==================================================

if (empty($_SESSION["admin_id"])) {

    header("Location: adminlogIn.php");
    exit;
}


// ==================================================
// 3. SECURITY HEADERS
// ==================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");


// ==================================================
// 4. DATABASE CONNECTION
// ==================================================

require_once __DIR__ . "/../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {

    error_log("Activity Logs: PDO connection unavailable.");

    http_response_code(500);

    exit("Database connection error.");
}


// ==================================================
// 5. HELPER FUNCTIONS
// ==================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


function validDate($date): bool
{
    if (!is_string($date) || $date === "") {
        return false;
    }

    $parsed = DateTime::createFromFormat("!Y-m-d", $date);

    return $parsed &&
           $parsed->format("Y-m-d") === $date;
}


// ==================================================
// 6. ADMIN INFORMATION
// ==================================================

$adminName = $_SESSION["admin_name"]
    ?? $_SESSION["full_name"]
    ?? "Administrator";


// ==================================================
// 7. SEARCH AND FILTERS
// ==================================================

$search = trim($_GET["search"] ?? "");

$accountType = trim($_GET["account_type"] ?? "");

$actionFilter = trim($_GET["action"] ?? "");

$dateFrom = trim($_GET["date_from"] ?? "");

$dateTo = trim($_GET["date_to"] ?? "");

$allowedAccountTypes = [
    "Admin",
    "Administrator",
    "BHW",
    "Resident"
];

$allowedActions = [
    "Login",
    "Logout",
    "Create",
    "Update",
    "Delete",
    "Register",
    "Approve",
    "Cancel"
];

if (!in_array($accountType, $allowedAccountTypes, true)) {
    $accountType = "";
}

if (!in_array($actionFilter, $allowedActions, true)) {
    $actionFilter = "";
}

if (!validDate($dateFrom)) {
    $dateFrom = "";
}

if (!validDate($dateTo)) {
    $dateTo = "";
}

if (
    $dateFrom !== "" &&
    $dateTo !== "" &&
    $dateFrom > $dateTo
) {
    $dateFrom = "";
    $dateTo = "";
}


// ==================================================
// 8. PAGINATION
// ==================================================

$limit = 15;

$page = filter_input(
    INPUT_GET,
    "page",
    FILTER_VALIDATE_INT
);

$page = ($page && $page > 0) ? $page : 1;

$offset = ($page - 1) * $limit;


// ==================================================
// 9. BUILD SQL FILTERS
// ==================================================

$where = [];
$params = [];

if ($search !== "") {

    $where[] = "(
        account_name LIKE :search_name
        OR action LIKE :search_action
        OR description LIKE :search_description
        OR ip_address LIKE :search_ip
        OR account_type LIKE :search_type
    )";

    $searchValue = "%" . $search . "%";

    $params[":search_name"] = $searchValue;
    $params[":search_action"] = $searchValue;
    $params[":search_description"] = $searchValue;
    $params[":search_ip"] = $searchValue;
    $params[":search_type"] = $searchValue;
}

if ($accountType !== "") {

    $where[] = "account_type = :account_type";

    $params[":account_type"] = $accountType;
}

if ($actionFilter !== "") {

    $where[] = "action = :action";

    $params[":action"] = $actionFilter;
}

if ($dateFrom !== "") {

    $where[] = "created_at >= :date_from";

    $params[":date_from"] = $dateFrom . " 00:00:00";
}

if ($dateTo !== "") {

    $where[] = "created_at <= :date_to";

    $params[":date_to"] = $dateTo . " 23:59:59";
}

$whereSQL = count($where) > 0
    ? "WHERE " . implode(" AND ", $where)
    : "";


// ==================================================
// 10. INITIAL DATA
// ==================================================

$errorMessage = "";

$logs = [];

$totalLogs = 0;
$totalPages = 1;

$totalToday = 0;
$totalAdmins = 0;
$totalBHW = 0;
$totalResidents = 0;


// ==================================================
// 11. LOAD ACTIVITY LOGS
// ==================================================

try {

    // TOTAL RECORDS

    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM activity_logs
        {$whereSQL}
    ");

    $countStmt->execute($params);

    $totalLogs = (int)$countStmt->fetchColumn();

    $totalPages = max(
        1,
        (int)ceil($totalLogs / $limit)
    );

    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }


    // ACTIVITY LOG RECORDS

    $logsStmt = $pdo->prepare("
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
        {$whereSQL}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $key => $value) {
        $logsStmt->bindValue($key, $value);
    }

    $logsStmt->bindValue(
        ":limit",
        $limit,
        PDO::PARAM_INT
    );

    $logsStmt->bindValue(
        ":offset",
        $offset,
        PDO::PARAM_INT
    );

    $logsStmt->execute();

    $logs = $logsStmt->fetchAll();


    // TODAY'S ACTIVITIES

    $totalToday = (int)$pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE DATE(created_at) = CURDATE()
    ")->fetchColumn();


    // ADMIN ACTIVITIES

    $totalAdmins = (int)$pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE LOWER(account_type) IN ('admin', 'administrator')
    ")->fetchColumn();


    // BHW ACTIVITIES

    $totalBHW = (int)$pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE LOWER(account_type) = 'bhw'
    ")->fetchColumn();


    // RESIDENT ACTIVITIES

    $totalResidents = (int)$pdo->query("
        SELECT COUNT(*)
        FROM activity_logs
        WHERE LOWER(account_type) = 'resident'
    ")->fetchColumn();


} catch (PDOException $ex) {

    error_log("Activity Logs Query Error: " . $ex->getMessage());

    $errorMessage = "Unable to load activity logs. Please verify your activity_logs table and database columns.";
}


// ==================================================
// 12. PAGINATION URL
// ==================================================

function paginationUrl($pageNumber): string
{
    $query = $_GET;

    $query["page"] = $pageNumber;

    return "?" . http_build_query($query);
}


// ==================================================
// 13. ACTION COLOR
// ==================================================

function actionStyle($action): string
{
    $action = strtolower(trim((string)$action));

    if (
        strpos($action, "login") !== false ||
        strpos($action, "approve") !== false
    ) {
        return "bg-green-100 text-green-700";
    }

    if (
        strpos($action, "delete") !== false ||
        strpos($action, "cancel") !== false
    ) {
        return "bg-red-100 text-red-700";
    }

    if (
        strpos($action, "update") !== false ||
        strpos($action, "edit") !== false
    ) {
        return "bg-blue-100 text-blue-700";
    }

    if (
        strpos($action, "logout") !== false
    ) {
        return "bg-slate-100 text-slate-700";
    }

    return "bg-amber-100 text-amber-700";
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Activity Logs | Barangay Guindacpan</title>

    <link rel="icon" type="image/png" href="../asset/logo.png">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            width: 255px;
            min-height: 100vh;
            background: #10251d;
            flex-shrink: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            border-radius: 10px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
            text-decoration: none;
        }

        .nav-link:hover,
        .nav-link.active {
            background: #1c4733;
            color: white;
        }

        .stat-card {
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .filter-input {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 11px 13px;
            border-radius: 9px;
            outline: none;
            font-size: 13px;
        }

        .filter-input:focus {
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21, 128, 61, .10);
        }

        .table-row:hover {
            background: #f8fafc;
        }

        @media(max-width: 900px) {

            .sidebar {
                position: fixed;
                z-index: 50;
                left: -260px;
                top: 0;
                bottom: 0;
                transition: left .25s ease;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                width: 100%;
            }
        }

        @media print {

            body {
                background: white !important;
            }

            .sidebar,
            .no-print,
            .mobile-menu-button {
                display: none !important;
            }

            .main-content {
                width: 100% !important;
                padding: 0 !important;
            }

            .stat-card,
            .report-card {
                box-shadow: none !important;
                break-inside: avoid;
            }

            table {
                width: 100%;
            }

            .overflow-x-auto {
                overflow: visible !important;
            }
        }

    </style>

</head>

<body>

<div class="flex min-h-screen">


    <!-- ==================================================
         SIDEBAR
    ================================================== -->

    <aside id="sidebar" class="sidebar flex flex-col p-5 no-print">

        <div class="flex items-center gap-3 px-2 py-4 border-b border-white/10">

            <div class="w-11 h-11 rounded-xl bg-white flex items-center justify-center overflow-hidden">

                <img
                    src="../asset/logo.png"
                    alt="Barangay Logo"
                    class="w-full h-full object-contain"
                    onerror="this.style.display='none';">

            </div>

            <div>

                <h1 class="text-white font-bold text-sm">
                    Barangay Guindacpan
                </h1>

                <p class="text-green-300 text-xs mt-1">
                    Administrator Panel
                </p>

            </div>

        </div>


        <p class="text-[10px] uppercase tracking-[0.2em] text-slate-400 font-bold mt-8 mb-3 px-3">
            Main Menu
        </p>


        <nav class="space-y-2">

            <a href="dashboard.php" class="nav-link">
                <span>▦</span>
                Dashboard
            </a>

            <a href="patients.php" class="nav-link">
                <span>♙</span>
                Patient Records
            </a>

            <a href="appointments.php" class="nav-link">
                <span>▣</span>
                Appointments
            </a>

            <a href="bhw_accounts.php" class="nav-link">
                <span>♧</span>
                BHW Accounts
            </a>

            <a href="reports.php" class="nav-link">
                <span>▤</span>
                Reports
            </a>

            <a href="activity_logs.php" class="nav-link active">
                <span>◷</span>
                Activity Logs
            </a>

        </nav>


        <div class="mt-auto pt-6 border-t border-white/10">

            <a href="adminlogIn.php" class="nav-link">
                <span>↪</span>
                Admin Login
            </a>

            <p class="text-[10px] text-slate-500 mt-5 px-3">
                Barangay Guindacpan System
            </p>

        </div>

    </aside>


    <!-- ==================================================
         MAIN CONTENT
    ================================================== -->

    <main class="main-content flex-1 min-w-0 p-5 md:p-8">


        <!-- HEADER -->

        <header class="flex flex-wrap justify-between items-center gap-4 mb-8">

            <div class="flex items-center gap-3">

                <button
                    type="button"
                    onclick="toggleSidebar()"
                    class="mobile-menu-button lg:hidden w-10 h-10 rounded-lg bg-white border border-slate-200">

                    ☰

                </button>

                <div>

                    <p class="text-xs font-bold uppercase tracking-widest text-green-700">
                        Administrator Panel
                    </p>

                    <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mt-1">
                        Activity Logs
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Monitor system activities and account actions.
                    </p>

                </div>

            </div>


            <div class="flex gap-2 no-print">

                <button
                    type="button"
                    onclick="window.print()"
                    class="px-5 py-3 rounded-xl bg-green-800 hover:bg-green-900 text-white text-sm font-semibold">

                    Print Logs

                </button>

            </div>

        </header>


        <!-- ADMIN INFORMATION -->

        <div class="flex flex-wrap justify-between gap-3 mb-6">

            <p class="text-sm text-slate-500">
                Welcome,
                <strong class="text-slate-800">
                    <?= e($adminName) ?>
                </strong>
            </p>

            <p class="text-xs text-slate-400">
                <?= date("F d, Y | h:i A") ?>
            </p>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($errorMessage !== ""): ?>

            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm">

                <?= e($errorMessage) ?>

            </div>

        <?php endif; ?>


        <!-- ==================================================
             STATISTICS
        ================================================== -->

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">

                <div class="flex justify-between items-center">

                    <div>

                        <p class="text-xs text-slate-500 font-medium">
                            Total Activities
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalLogs) ?>
                        </h3>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-green-50 text-green-700 flex items-center justify-center text-2xl">
                        ◷
                    </div>

                </div>

                <p class="text-xs text-slate-400 mt-3">
                    Matching records
                </p>

            </div>


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">

                <div class="flex justify-between items-center">

                    <div>

                        <p class="text-xs text-slate-500 font-medium">
                            Today's Activities
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalToday) ?>
                        </h3>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-2xl">
                        ▦
                    </div>

                </div>

                <p class="text-xs text-slate-400 mt-3">
                    Activities recorded today
                </p>

            </div>


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">

                <div class="flex justify-between items-center">

                    <div>

                        <p class="text-xs text-slate-500 font-medium">
                            Administrator Activities
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalAdmins) ?>
                        </h3>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center text-2xl">
                        ♙
                    </div>

                </div>

                <p class="text-xs text-slate-400 mt-3">
                    Admin account actions
                </p>

            </div>


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">

                <div class="flex justify-between items-center">

                    <div>

                        <p class="text-xs text-slate-500 font-medium">
                            BHW Activities
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalBHW) ?>
                        </h3>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center text-2xl">
                        ♧
                    </div>

                </div>

                <p class="text-xs text-slate-400 mt-3">
                    Health worker account actions
                </p>

            </div>

        </section>


        <!-- ==================================================
             FILTER SECTION
        ================================================== -->

        <section class="report-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-6">

            <div class="mb-5">

                <h3 class="text-lg font-bold text-slate-900">
                    Search & Filter Activities
                </h3>

                <p class="text-xs text-slate-500 mt-1">
                    Find activity records using keywords and filters.
                </p>

            </div>


            <form method="GET" action="activity_logs.php" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">

                <div class="xl:col-span-2">

                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Search
                    </label>

                    <input
                        type="search"
                        name="search"
                        class="filter-input"
                        placeholder="Name, action, description, IP..."
                        value="<?= e($search) ?>">

                </div>


                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Account Type
                    </label>

                    <select name="account_type" class="filter-input">

                        <option value="">All Accounts</option>

                        <?php foreach ($allowedAccountTypes as $type): ?>

                            <option
                                value="<?= e($type) ?>"
                                <?= $accountType === $type ? "selected" : "" ?>>

                                <?= e($type) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Action
                    </label>

                    <select name="action" class="filter-input">

                        <option value="">All Actions</option>

                        <?php foreach ($allowedActions as $action): ?>

                            <option
                                value="<?= e($action) ?>"
                                <?= $actionFilter === $action ? "selected" : "" ?>>

                                <?= e($action) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="filter-input"
                        value="<?= e($dateFrom) ?>">

                </div>


                <div>

                    <label class="block text-xs font-semibold text-slate-600 mb-2">
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="filter-input"
                        value="<?= e($dateTo) ?>">

                </div>


                <div class="md:col-span-2 xl:col-span-5 flex flex-wrap gap-2">

                    <button
                        type="submit"
                        class="px-6 py-3 rounded-lg bg-green-800 hover:bg-green-900 text-white text-sm font-semibold">

                        Search Activities

                    </button>

                    <a
                        href="activity_logs.php"
                        class="px-6 py-3 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-600 text-sm font-semibold">

                        Clear Filters

                    </a>

                </div>

            </form>

        </section>


        <!-- ==================================================
             ACTIVITY LOG TABLE
        ================================================== -->

        <section class="report-card bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">


            <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-3">

                <div>

                    <h3 class="font-bold text-slate-900 text-lg">
                        System Activity Records
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">

                        Showing
                        <?= count($logs) ?>
                        of
                        <?= number_format($totalLogs) ?>
                        records

                    </p>

                </div>

                <span class="text-xs font-semibold text-green-800 bg-green-50 px-3 py-2 rounded-lg">
                    Latest Activities First
                </span>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                ID
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                Account
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                Account Type
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                Action
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                Description
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                IP Address
                            </th>

                            <th class="px-5 py-4 text-xs font-bold text-slate-500 uppercase">
                                Date & Time
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php if (count($logs) > 0): ?>

                            <?php foreach ($logs as $log): ?>

                                <tr class="table-row">

                                    <td class="px-5 py-4 text-xs font-bold text-slate-500">
                                        #<?= (int)$log["id"] ?>
                                    </td>


                                    <td class="px-5 py-4">

                                        <p class="text-sm font-semibold text-slate-800">
                                            <?= e($log["account_name"]) ?>
                                        </p>

                                        <?php if (!empty($log["resident_id"])): ?>

                                            <p class="text-xs text-slate-400 mt-1">
                                                Resident ID:
                                                <?= (int)$log["resident_id"] ?>
                                            </p>

                                        <?php endif; ?>

                                    </td>


                                    <td class="px-5 py-4">

                                        <span class="inline-block px-3 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
                                            <?= e($log["account_type"]) ?>
                                        </span>

                                    </td>


                                    <td class="px-5 py-4">

                                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold <?= e(actionStyle($log["action"])) ?>">
                                            <?= e($log["action"]) ?>
                                        </span>

                                    </td>


                                    <td class="px-5 py-4 max-w-xs">

                                        <p class="text-sm text-slate-600 leading-relaxed break-words">
                                            <?= e($log["description"]) ?>
                                        </p>

                                    </td>


                                    <td class="px-5 py-4">

                                        <span class="text-xs text-slate-500 font-mono">
                                            <?= e($log["ip_address"] ?: "N/A") ?>
                                        </span>

                                    </td>


                                    <td class="px-5 py-4 whitespace-nowrap">

                                        <?php

                                        $timestamp = strtotime($log["created_at"]);

                                        ?>

                                        <?php if ($timestamp !== false): ?>

                                            <p class="text-sm font-medium text-slate-700">
                                                <?= date("M d, Y", $timestamp) ?>
                                            </p>

                                            <p class="text-xs text-slate-400 mt-1">
                                                <?= date("h:i:s A", $timestamp) ?>
                                            </p>

                                        <?php else: ?>

                                            <span class="text-xs text-slate-400">
                                                Unknown date
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="7" class="px-6 py-16 text-center">

                                    <div class="text-4xl mb-3">
                                        ◷
                                    </div>

                                    <h4 class="font-bold text-slate-700">
                                        No Activity Records Found
                                    </h4>

                                    <p class="text-xs text-slate-400 mt-2">
                                        No records match your search or filter.
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

            <?php if ($totalPages > 1): ?>

                <div class="p-5 border-t border-slate-100 flex flex-wrap justify-between items-center gap-3 no-print">

                    <p class="text-xs text-slate-500">

                        Page
                        <strong><?= $page ?></strong>
                        of
                        <strong><?= $totalPages ?></strong>

                    </p>


                    <div class="flex items-center gap-2">

                        <?php if ($page > 1): ?>

                            <a
                                href="<?= e(paginationUrl($page - 1)) ?>"
                                class="px-4 py-2 border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:bg-slate-50">

                                Previous

                            </a>

                        <?php endif; ?>


                        <?php if ($page < $totalPages): ?>

                            <a
                                href="<?= e(paginationUrl($page + 1)) ?>"
                                class="px-4 py-2 bg-green-800 hover:bg-green-900 rounded-lg text-xs font-semibold text-white">

                                Next

                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </section>


        <!-- ==================================================
             FOOTER
        ================================================== -->

        <footer class="text-center py-7">

            <p class="text-xs text-slate-400">
                Barangay Guindacpan Management System
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Confidential Administrator Activity Records
            </p>

        </footer>


    </main>

</div>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>

function toggleSidebar() {

    const sidebar = document.getElementById("sidebar");

    sidebar.classList.toggle("open");
}

</script>

</body>
</html>
