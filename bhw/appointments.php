
<?php
// ============================================
// BHW APPOINTMENT MANAGEMENT - PDO VERSION
// Database: barangay_health_db
// ============================================

// SESSION CONFIGURATION
$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {
    session_name("BARANGAY_BHW_SESSION");

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

// SECURITY HEADERS
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// VERIFY SESSION
if (empty($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . "/database.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection error. Please check database.php.");
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("BHW Appointment PDO Configuration Error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to configure database connection.");
}

$bhwId = (int)$_SESSION["bhw_id"];
$bhwName = $_SESSION["bhw_name"] ?? "Health Worker";

$message = "";
$messageType = "";

// HTML ESCAPING
function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// ============================================
// CSRF TOKEN
// ============================================

if (empty($_SESSION["appointment_csrf"])) {
    $_SESSION["appointment_csrf"] = bin2hex(random_bytes(32));
}

// ============================================
// UPDATE APPOINTMENT STATUS
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $appointmentId = filter_var(
        $_POST["appointment_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    $status = $_POST["status"] ?? "";
    $token = $_POST["csrf_token"] ?? "";

    $allowedStatuses = [
        "Approved",
        "Completed",
        "Cancelled"
    ];

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["appointment_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (
        !$appointmentId ||
        !is_string($status) ||
        !in_array($status, $allowedStatuses, true)
    ) {
        $message = "Invalid appointment information.";
        $messageType = "error";

    } else {

        try {
            $stmt = $pdo->prepare("
                UPDATE bhw_appointments
                SET status = :status
                WHERE id = :id
                AND bhw_id = :bhw_id
                AND (
                    (status = 'Pending' AND :new_status1 IN ('Approved', 'Cancelled'))
                    OR
                    (status = 'Approved' AND :new_status2 IN ('Completed', 'Cancelled'))
                )
            ");

            $stmt->execute([
                ":status" => $status,
                ":id" => $appointmentId,
                ":bhw_id" => $bhwId,
                ":new_status1" => $status,
                ":new_status2" => $status
            ]);

            if ($stmt->rowCount() > 0) {
                $message = "Appointment updated successfully!";
                $messageType = "success";
            } else {
                $message = "Appointment not found or cannot be updated.";
                $messageType = "error";
            }

            $_SESSION["appointment_csrf"] = bin2hex(random_bytes(32));

        } catch (PDOException $e) {
            error_log("BHW Appointment Update Error: " . $e->getMessage());

            $message = "Unable to update appointment. Please try again.";
            $messageType = "error";
        }
    }
}

// ============================================
// DASHBOARD STATISTICS
// ============================================

try {
    $statsQuery = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'Pending'), 0) AS pending,
            COALESCE(SUM(status = 'Approved'), 0) AS approved,
            COALESCE(SUM(status = 'Completed'), 0) AS completed
        FROM bhw_appointments
        WHERE bhw_id = :bhw_id
    ");

    $statsQuery->execute([
        ":bhw_id" => $bhwId
    ]);

    $stats = $statsQuery->fetch();

    $total = (int)($stats["total"] ?? 0);
    $pending = (int)($stats["pending"] ?? 0);
    $approved = (int)($stats["approved"] ?? 0);
    $completed = (int)($stats["completed"] ?? 0);

} catch (PDOException $e) {
    error_log("BHW Appointment Statistics Error: " . $e->getMessage());

    $total = 0;
    $pending = 0;
    $approved = 0;
    $completed = 0;
}

// ============================================
// SEARCH AND FILTER
// ============================================

$searchInput = $_GET["search"] ?? "";
$filterInput = $_GET["status"] ?? "";

$search = is_string($searchInput) ? trim($searchInput) : "";
$filter = is_string($filterInput) ? $filterInput : "";

$validFilters = [
    "Pending",
    "Approved",
    "Completed",
    "Cancelled"
];

// ============================================
// FETCH APPOINTMENTS
// ============================================

$appointments = [];

try {
    $sql = "
        SELECT
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.purpose,
            a.status,
            p.full_name,
            p.contact_number
        FROM bhw_appointments a
        INNER JOIN bhw_patients p
            ON a.patient_id = p.id
        WHERE a.bhw_id = :bhw_id
    ";

    $params = [
        ":bhw_id" => $bhwId
    ];

    if ($search !== "") {
        $sql .= "
            AND (
                p.full_name LIKE :search_name
                OR a.purpose LIKE :search_purpose
            )
        ";

        $keyword = "%" . $search . "%";

        $params[":search_name"] = $keyword;
        $params[":search_purpose"] = $keyword;
    }

    if (in_array($filter, $validFilters, true)) {
        $sql .= " AND a.status = :status";
        $params[":status"] = $filter;
    }

    $sql .= "
        ORDER BY
            a.appointment_date DESC,
            a.appointment_time DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("BHW Appointment Fetch Error: " . $e->getMessage());

    $appointments = [];

    $message = "Unable to load appointment records. Please check the database structure.";
    $messageType = "error";
}

// ============================================
// STATISTIC CARD DATA
// ============================================

$statCards = [
    [
        "title" => "Total Appointments",
        "count" => $total,
        "icon" => "calendar-days",
        "iconClass" => "bg-blue-100 text-blue-600"
    ],
    [
        "title" => "Pending",
        "count" => $pending,
        "icon" => "clock",
        "iconClass" => "bg-orange-100 text-orange-600"
    ],
    [
        "title" => "Approved",
        "count" => $approved,
        "icon" => "calendar-check",
        "iconClass" => "bg-green-100 text-green-600"
    ],
    [
        "title" => "Completed",
        "count" => $completed,
        "icon" => "check-circle",
        "iconClass" => "bg-purple-100 text-purple-600"
    ]
];

$statusColors = [
    "Pending" => "bg-orange-100 text-orange-700",
    "Approved" => "bg-green-100 text-green-700",
    "Completed" => "bg-blue-100 text-blue-700",
    "Cancelled" => "bg-red-100 text-red-700"
];

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BHW Appointments | Barangay Health System</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

* {
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
}

.sidebar {
    transition: transform .3s ease;
}

.nav-link {
    transition: .2s ease;
}

.nav-link:hover,
.nav-link.active {
    background: #1e40af;
    color: white;
}

.stat-card {
    transition: .2s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
}

/* MOBILE SIDEBAR */

@media (max-width: 767px) {

    body.menu-open {
        overflow: hidden;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 288px;
        max-width: 85vw;
        height: 100dvh;
        transform: translateX(-100%);
        visibility: hidden;
        z-index: 50;
        overflow-y: auto;
    }

    .sidebar.show {
        transform: translateX(0);
        visibility: visible;
    }

    .sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, .55);
        z-index: 40;
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease, visibility .3s ease;
    }

    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }
}

/* DESKTOP SIDEBAR */

@media (min-width: 768px) {
    .sidebar {
        position: sticky;
        top: 0;
        transform: none !important;
        visibility: visible !important;
        flex-shrink: 0;
    }

    .sidebar-overlay {
        display: none !important;
    }
}

/* MOBILE APPOINTMENT CARDS */

@media (max-width: 767px) {
    .desktop-appointments {
        display: none;
    }
}

@media (min-width: 768px) {
    .mobile-appointments {
        display: none;
    }
}

</style>

</head>

<body>

<div class="min-h-screen flex">

    <!-- MOBILE OVERLAY -->
    <div
        id="appointmentOverlay"
        class="sidebar-overlay"
        aria-hidden="true">
    </div>

    <!-- SIDEBAR -->
    <aside
        id="appointmentSidebar"
        class="sidebar w-72 bg-slate-950 text-white flex flex-col">

        <!-- BRAND -->
        <div class="px-7 py-7 border-b border-white/10">

            <div class="flex items-center gap-3">

                <div class="w-12 h-12 bg-blue-600 rounded-xl flex items-center justify-center">

                    <i data-lucide="heart-pulse" class="w-7 h-7"></i>

                </div>

                <div>

                    <h1 class="font-bold text-lg">
                        Barangay Health
                    </h1>

                    <p class="text-xs text-slate-400">
                        Management System
                    </p>

                </div>

            </div>

        </div>

        <!-- NAVIGATION -->
        <div class="flex-1 px-4 py-6">

            <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold px-4 mb-4">
                Main Menu
            </p>

            <nav class="space-y-2">

                <a href="dashboard.php"
                   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    Dashboard

                </a>

                <a href="patients.php"
                   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                    <i data-lucide="users" class="w-5 h-5"></i>
                    Patient Management

                </a>

                <a href="healthRecords.php"
                   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                    <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                    Health Records

                </a>

                <a href="appointments.php"
                   class="nav-link active flex items-center gap-3 px-4 py-3 rounded-xl text-sm">

                    <i data-lucide="calendar-days" class="w-5 h-5"></i>
                    Appointments

                </a>

                <a href="prenatal.php"
                   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                    <i data-lucide="baby" class="w-5 h-5"></i>
                    Prenatal Monitoring

                </a>

                <a href="immunization.php"
                   class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                    <i data-lucide="syringe" class="w-5 h-5"></i>
                    Immunization

                </a>

            </nav>

            <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold px-4 mt-9 mb-4">
                Management
            </p>

            <a href="reports.php"
               class="nav-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="chart-column" class="w-5 h-5"></i>
                Health Reports

            </a>

        </div>

        <!-- LOGOUT -->
        <div class="p-4 border-t border-white/10">

            <a
                href="logout.php"
                onclick="return confirm('Are you sure you want to logout?')"
                class="flex gap-3 items-center px-4 py-3 text-red-400 rounded-xl hover:bg-red-500/10 text-sm">

                <i data-lucide="log-out" class="w-5 h-5"></i>
                Logout

            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0">

        <!-- HEADER -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30">

            <div class="px-4 sm:px-5 md:px-8 py-4 flex items-center justify-between gap-3">

                <div class="flex items-center gap-3 min-w-0">

                    <!-- HAMBURGER BUTTON -->
                    <button
                        type="button"
                        id="appointmentMenuBtn"
                        aria-label="Open navigation menu"
                        aria-expanded="false"
                        aria-controls="appointmentSidebar"
                        class="md:hidden p-2 hover:bg-slate-100 rounded-lg text-slate-700 flex-shrink-0">

                        <i data-lucide="menu" id="appointmentMenuIcon" class="w-6 h-6"></i>

                    </button>

                    <div class="min-w-0">

                        <h2 class="font-bold text-lg sm:text-xl text-slate-800">
                            Appointments
                        </h2>

                        <p class="text-xs text-slate-500">
                            Manage patient consultation schedules
                        </p>

                    </div>

                </div>

                <!-- USER INFO -->
                <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">

                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold">

                        <?= e(strtoupper(substr($bhwName, 0, 1))) ?>

                    </div>

                    <div class="hidden sm:block">

                        <p class="text-sm font-semibold text-slate-800">
                            <?= e($bhwName) ?>
                        </p>

                        <p class="text-xs text-slate-500">
                            Health Worker
                        </p>

                    </div>

                </div>

            </div>

        </header>

        <section class="p-4 sm:p-5 md:p-8 space-y-7">

            <!-- PAGE TITLE -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div>

                    <h1 class="text-2xl font-bold text-slate-800">
                        Appointment Management
                    </h1>

                    <p class="text-sm text-slate-500 mt-1">
                        Monitor and manage your patient schedules.
                    </p>

                </div>

                <a
                    href="patients.php"
                    class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl font-semibold text-sm">

                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Register Patient

                </a>

            </div>

            <!-- MESSAGE -->
            <?php if ($message !== ""): ?>

                <div class="<?= $messageType === 'success'
                    ? 'bg-green-50 border-green-200 text-green-700'
                    : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-xl p-4 text-sm">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>

            <!-- STATISTICS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5">

                <?php foreach ($statCards as $card): ?>

                    <div class="stat-card bg-white p-5 sm:p-6 rounded-2xl border border-slate-200">

                        <div class="w-11 h-11 <?= e($card["iconClass"]) ?> rounded-xl flex items-center justify-center">

                            <i data-lucide="<?= e($card["icon"]) ?>" class="w-5 h-5"></i>

                        </div>

                        <p class="text-sm text-slate-500 mt-4">
                            <?= e($card["title"]) ?>
                        </p>

                        <h2 class="text-3xl font-bold mt-1 text-slate-800">
                            <?= (int)$card["count"] ?>
                        </h2>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- APPOINTMENT RECORDS -->
            <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">

                <div class="p-5 sm:p-6 border-b border-slate-100">

                    <h2 class="font-bold text-slate-800">
                        Appointment Records
                    </h2>

                    <p class="text-xs text-slate-500 mt-1">
                        All appointment schedules assigned to you
                    </p>

                </div>

                <!-- SEARCH AND FILTER -->
                <form method="GET" class="p-4 sm:p-5 flex flex-col md:flex-row gap-3">

                    <div class="relative flex-1">

                        <i data-lucide="search"
                           class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400">
                        </i>

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search patient or purpose..."
                            class="w-full border border-slate-200 rounded-xl pl-12 pr-4 py-3 text-sm focus:outline-none focus:border-blue-500"
                        >

                    </div>

                    <select
                        name="status"
                        class="border border-slate-200 rounded-xl px-4 py-3 text-sm bg-white">

                        <option value="">All Status</option>

                        <?php foreach ($validFilters as $option): ?>

                            <option
                                value="<?= e($option) ?>"
                                <?= $filter === $option ? "selected" : "" ?>>

                                <?= e($option) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button
                        type="submit"
                        class="bg-blue-600 text-white px-6 py-3 rounded-xl font-semibold text-sm hover:bg-blue-700">

                        Search

                    </button>

                    <?php if ($search !== "" || $filter !== ""): ?>

                        <a
                            href="appointments.php"
                            class="text-center border border-slate-200 text-slate-600 px-5 py-3 rounded-xl text-sm font-semibold hover:bg-slate-50">

                            Clear

                        </a>

                    <?php endif; ?>

                </form>

                <!-- MOBILE APPOINTMENT CARDS -->
                <div class="mobile-appointments p-4 space-y-4">

                    <?php if (!empty($appointments)): ?>

                        <?php foreach ($appointments as $row): ?>

                            <?php
                            $status = $row["status"] ?? "";
                            $color = $statusColors[$status] ?? "bg-slate-100 text-slate-700";
                            ?>

                            <article class="border border-slate-200 rounded-xl p-4 space-y-4">

                                <div class="flex items-start justify-between gap-3">

                                    <div class="min-w-0">

                                        <p class="text-xs text-slate-400 mb-1">
                                            Appointment #<?= (int)$row["id"] ?>
                                        </p>

                                        <h3 class="font-bold text-slate-800 break-words">
                                            <?= e($row["full_name"]) ?>
                                        </h3>

                                    </div>

                                    <span class="<?= e($color) ?> px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap">
                                        <?= e($status) ?>
                                    </span>

                                </div>

                                <div class="grid grid-cols-2 gap-3 text-sm">

                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">Contact</p>
                                        <p class="text-slate-700 break-words">
                                            <?= e($row["contact_number"] ?? "—") ?>
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">Date</p>
                                        <p class="text-slate-700">
                                            <?= !empty($row["appointment_date"])
                                                ? e(date("M d, Y", strtotime($row["appointment_date"])))
                                                : "—" ?>
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">Time</p>
                                        <p class="text-slate-700">
                                            <?= !empty($row["appointment_time"])
                                                ? e(date("h:i A", strtotime($row["appointment_time"])))
                                                : "—" ?>
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">Purpose</p>
                                        <p class="text-slate-700 break-words">
                                            <?= e($row["purpose"] ?? "—") ?>
                                        </p>
                                    </div>

                                </div>

                                <?php if (
                                    $status === "Pending" ||
                                    $status === "Approved"
                                ): ?>

                                    <form method="POST" class="border-t border-slate-100 pt-3 flex flex-col sm:flex-row gap-2">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION["appointment_csrf"]) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="appointment_id"
                                            value="<?= (int)$row["id"] ?>"
                                        >

                                        <select
                                            name="status"
                                            class="flex-1 border border-slate-200 rounded-lg px-3 py-2 text-sm"
                                            required>

                                            <?php if ($status === "Pending"): ?>
                                                <option value="Approved">Approve</option>
                                            <?php endif; ?>

                                            <?php if ($status === "Approved"): ?>
                                                <option value="Completed">Complete</option>
                                            <?php endif; ?>

                                            <option value="Cancelled">Cancel</option>

                                        </select>

                                        <button
                                            type="submit"
                                            onclick="return confirm('Update this appointment?')"
                                            class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-4 py-2 text-sm font-semibold">

                                            Update Status

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <p class="border-t border-slate-100 pt-3 text-xs text-slate-400">
                                        No further actions available.
                                    </p>

                                <?php endif; ?>

                            </article>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="text-center py-10">

                            <i data-lucide="calendar-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>

                            <p class="font-semibold text-slate-600">
                                No appointments found
                            </p>

                            <p class="text-xs text-slate-400 mt-1">
                                Appointment records will appear here.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

                <!-- DESKTOP APPOINTMENT TABLE -->
                <div class="desktop-appointments overflow-x-auto">

                    <table class="w-full text-left">

                        <thead class="bg-slate-50">

                            <tr class="text-xs uppercase text-slate-500 tracking-wide">

                                <th class="px-6 py-4">Patient</th>
                                <th class="px-6 py-4">Contact</th>
                                <th class="px-6 py-4">Date</th>
                                <th class="px-6 py-4">Time</th>
                                <th class="px-6 py-4">Purpose</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>

                            </tr>

                        </thead>

                        <tbody class="divide-y divide-slate-100">

                            <?php if (!empty($appointments)): ?>

                                <?php foreach ($appointments as $row): ?>

                                    <?php
                                    $status = $row["status"] ?? "";
                                    $color = $statusColors[$status] ?? "bg-slate-100 text-slate-700";
                                    ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-6 py-4">

                                            <p class="text-sm font-semibold text-slate-800">
                                                <?= e($row["full_name"]) ?>
                                            </p>

                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            <?= e($row["contact_number"] ?? "") ?>
                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">

                                            <?= !empty($row["appointment_date"])
                                                ? e(date("M d, Y", strtotime($row["appointment_date"])))
                                                : "—" ?>

                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">

                                            <?= !empty($row["appointment_time"])
                                                ? e(date("h:i A", strtotime($row["appointment_time"])))
                                                : "—" ?>

                                        </td>

                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            <?= e($row["purpose"] ?? "") ?>
                                        </td>

                                        <td class="px-6 py-4">

                                            <span class="<?= e($color) ?> px-3 py-1 rounded-full text-xs font-semibold">
                                                <?= e($status) ?>
                                            </span>

                                        </td>

                                        <td class="px-6 py-4">

                                            <?php if (
                                                $status === "Pending" ||
                                                $status === "Approved"
                                            ): ?>

                                                <form method="POST" class="flex flex-col gap-2 min-w-28">

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION["appointment_csrf"]) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="appointment_id"
                                                        value="<?= (int)$row["id"] ?>"
                                                    >

                                                    <select
                                                        name="status"
                                                        class="border border-slate-200 rounded-lg px-2 py-2 text-xs"
                                                        required>

                                                        <?php if ($status === "Pending"): ?>
                                                            <option value="Approved">Approve</option>
                                                        <?php endif; ?>

                                                        <?php if ($status === "Approved"): ?>
                                                            <option value="Completed">Complete</option>
                                                        <?php endif; ?>

                                                        <option value="Cancelled">Cancel</option>

                                                    </select>

                                                    <button
                                                        type="submit"
                                                        onclick="return confirm('Update this appointment?')"
                                                        class="bg-blue-600 hover:bg-blue-700 text-white rounded-lg px-3 py-2 text-xs font-semibold">

                                                        Update

                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <span class="text-xs text-slate-400">
                                                    No actions
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="7" class="text-center py-12">

                                        <i data-lucide="calendar-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>

                                        <p class="font-semibold text-slate-600">
                                            No appointments found
                                        </p>

                                        <p class="text-xs text-slate-400 mt-1">
                                            Appointment records will appear here.
                                        </p>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- FOOTER -->
            <footer class="text-center text-xs text-slate-400 py-5">

                Barangay Health Management System
                <br>
                © <?= date("Y") ?> All Rights Reserved.

            </footer>

        </section>

    </main>

</div>

<!-- HAMBURGER MENU SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.getElementById("appointmentSidebar");
    const menuBtn = document.getElementById("appointmentMenuBtn");
    const overlay = document.getElementById("appointmentOverlay");
    const menuIcon = document.getElementById("appointmentMenuIcon");

    if (!sidebar || !menuBtn || !overlay || !menuIcon) {
        return;
    }

    function openMenu() {
        sidebar.classList.add("show");
        overlay.classList.add("show");
        document.body.classList.add("menu-open");

        menuBtn.setAttribute("aria-expanded", "true");
        menuBtn.setAttribute("aria-label", "Close navigation menu");

        menuIcon.setAttribute("data-lucide", "x");

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function closeMenu() {
        sidebar.classList.remove("show");
        overlay.classList.remove("show");
        document.body.classList.remove("menu-open");

        menuBtn.setAttribute("aria-expanded", "false");
        menuBtn.setAttribute("aria-label", "Open navigation menu");

        menuIcon.setAttribute("data-lucide", "menu");

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    menuBtn.addEventListener("click", function () {
        if (sidebar.classList.contains("show")) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    overlay.addEventListener("click", closeMenu);

    sidebar.querySelectorAll(".nav-link").forEach(function (link) {
        link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth >= 768) {
            closeMenu();
        }
    });

});

// LOAD LUCIDE ICONS
if (window.lucide) {
    lucide.createIcons();
}
</script>

</body>
</html>
