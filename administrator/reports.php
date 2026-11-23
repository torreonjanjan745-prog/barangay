
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR REPORTS DASHBOARD
//
// Health Database: barangay_health_db
// Tables:
// bhw_patients
// bhw_appointments
// bhw_accounts
//
// Features:
// - Dashboard Summary
// - Patient Reports
// - Appointment Reports
// - BHW Account Reports
// - Monthly Patient Registration
// - Gender Distribution
// - Recent Appointments
// - Print Reports
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = isset($_SERVER["HTTPS"]) &&
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
// 2. AUTHENTICATION CHECK
// ==================================================

// IMPORTANT:
// This assumes your admin login stores admin_id
// in the session. If your login uses another
// session key, change this check to match it.

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
// HEALTH DATABASE
// ==================================================

$host = "localhost";
$dbname = "barangay_health_db";
$dbUsername = "root";
$dbPassword = "";

try {

    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log("Reports Database Error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to connect to the health database.");
}


// ==================================================
// 5. HELPER FUNCTION
// ==================================================

function reportCount(PDO $pdo, string $sql): int
{
    return (int) $pdo->query($sql)->fetchColumn();
}

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// ==================================================
// 6. INITIAL VALUES
// ==================================================

$errorMessage = "";

$totalPatients = 0;
$totalAppointments = 0;
$totalBHW = 0;

$pendingAppointments = 0;
$approvedAppointments = 0;
$completedAppointments = 0;
$cancelledAppointments = 0;

$malePatients = 0;
$femalePatients = 0;
$otherPatients = 0;

$monthlyPatients = [];
$recentAppointments = [];


// ==================================================
// 7. GET REPORT DATA
// ==================================================

try {

    // TOTAL PATIENTS

    $totalPatients = reportCount(
        $pdo,
        "SELECT COUNT(*) FROM bhw_patients"
    );


    // TOTAL APPOINTMENTS

    $totalAppointments = reportCount(
        $pdo,
        "SELECT COUNT(*) FROM bhw_appointments"
    );


    // TOTAL BHW ACCOUNTS

    $totalBHW = reportCount(
        $pdo,
        "SELECT COUNT(*) FROM bhw_accounts"
    );


    // APPOINTMENT STATUS

    $statusStmt = $pdo->query("
        SELECT
            status,
            COUNT(*) AS total
        FROM bhw_appointments
        GROUP BY status
    ");

    foreach ($statusStmt->fetchAll() as $row) {

        $status = strtolower(trim($row["status"] ?? ""));

        if ($status === "pending") {
            $pendingAppointments = (int)$row["total"];
        }

        if ($status === "approved") {
            $approvedAppointments = (int)$row["total"];
        }

        if ($status === "completed") {
            $completedAppointments = (int)$row["total"];
        }

        if ($status === "cancelled") {
            $cancelledAppointments = (int)$row["total"];
        }
    }


    // GENDER DISTRIBUTION

    $genderStmt = $pdo->query("
        SELECT
            gender,
            COUNT(*) AS total
        FROM bhw_patients
        GROUP BY gender
    ");

    foreach ($genderStmt->fetchAll() as $row) {

        $gender = strtolower(trim($row["gender"] ?? ""));

        if ($gender === "male") {
            $malePatients = (int)$row["total"];
        }

        if ($gender === "female") {
            $femalePatients = (int)$row["total"];
        }

        if ($gender === "other") {
            $otherPatients = (int)$row["total"];
        }
    }


    // MONTHLY PATIENT REGISTRATION - LAST 6 MONTHS

    $monthlyStmt = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') AS month_key,
            DATE_FORMAT(created_at, '%b %Y') AS month_label,
            COUNT(*) AS total
        FROM bhw_patients
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY
            YEAR(created_at),
            MONTH(created_at)
        ORDER BY
            YEAR(created_at),
            MONTH(created_at)
    ");

    $monthlyPatients = $monthlyStmt->fetchAll();


    // RECENT APPOINTMENTS

    $recentStmt = $pdo->query("
        SELECT
            a.id,
            a.appointment_date,
            a.appointment_time,
            a.purpose,
            a.status,
            p.full_name AS patient_name,
            b.full_name AS bhw_name
        FROM bhw_appointments a

        LEFT JOIN bhw_patients p
            ON a.patient_id = p.id

        LEFT JOIN bhw_accounts b
            ON a.bhw_id = b.id

        ORDER BY
            a.created_at DESC
        LIMIT 10
    ");

    $recentAppointments = $recentStmt->fetchAll();


} catch (PDOException $e) {

    error_log("Reports Query Error: " . $e->getMessage());

    $errorMessage = "Unable to load reports. Please verify your health database tables and columns.";
}


// ==================================================
// 8. CHART CALCULATIONS
// ==================================================

$maxMonthlyPatients = 1;

foreach ($monthlyPatients as $month) {

    $maxMonthlyPatients = max(
        $maxMonthlyPatients,
        (int)$month["total"]
    );
}

$genderTotal = $malePatients + $femalePatients + $otherPatients;

$malePercent = $genderTotal > 0
    ? round(($malePatients / $genderTotal) * 100)
    : 0;

$femalePercent = $genderTotal > 0
    ? round(($femalePatients / $genderTotal) * 100)
    : 0;

$otherPercent = $genderTotal > 0
    ? round(($otherPatients / $genderTotal) * 100)
    : 0;

$appointmentStatusTotal =
    $pendingAppointments +
    $approvedAppointments +
    $completedAppointments +
    $cancelledAppointments;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Reports | Barangay Guindacpan Administrator</title>

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
            background: #10251d;
            min-height: 100vh;
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
        }

        .nav-link:hover,
        .nav-link.active {
            background: #1c4733;
            color: #ffffff;
        }

        .stat-card {
            transition: 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .chart-bar {
            min-height: 8px;
            border-radius: 6px 6px 0 0;
            background: #15803d;
            transition: height 0.3s ease;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
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
                margin: 0 !important;
            }

            .stat-card,
            .report-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
            }

            .chart-bar {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            table {
                width: 100%;
            }
        }

        @media (max-width: 900px) {

            .sidebar {
                position: fixed;
                z-index: 50;
                left: -260px;
                top: 0;
                transition: 0.25s;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                width: 100%;
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
                    Administrator
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

            <a href="reports.php" class="nav-link active">
                <span>▤</span>
                Reports
            </a>

            <a href="activity_logs.php" class="nav-link">
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


        <!-- TOP HEADER -->

        <header class="flex flex-wrap justify-between items-center gap-4 mb-8">

            <div class="flex items-center gap-3">

                <button
                    type="button"
                    onclick="toggleSidebar()"
                    class="mobile-menu-button lg:hidden w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-700">

                    ☰

                </button>

                <div>

                    <p class="text-xs text-green-700 font-bold uppercase tracking-widest">
                        Administrator Panel
                    </p>

                    <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900 mt-1">
                        Reports & Analytics
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Barangay Health Services Overview
                    </p>

                </div>

            </div>


            <div class="flex items-center gap-3 no-print">

                <button
                    onclick="window.print()"
                    type="button"
                    class="px-5 py-3 rounded-xl bg-green-800 hover:bg-green-900 text-white text-sm font-semibold shadow-sm">

                    Print Report

                </button>

            </div>

        </header>


        <!-- REPORT DATE -->

        <div class="flex flex-wrap justify-between items-center gap-3 mb-6">

            <p class="text-sm text-slate-500">
                Report generated:
                <strong class="text-slate-700">
                    <?= date("F d, Y") ?>
                </strong>
            </p>

            <p class="text-xs text-slate-400">
                Data source: barangay_health_db
            </p>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($errorMessage !== ""): ?>

            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
                <?= e($errorMessage) ?>
            </div>

        <?php endif; ?>


        <!-- ==================================================
             SUMMARY CARDS
        ================================================== -->

        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5 mb-8">


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

                <div class="flex justify-between items-start">

                    <div>

                        <p class="text-sm text-slate-500 font-medium">
                            Total Patients
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalPatients) ?>
                        </h3>

                        <p class="text-xs text-slate-400 mt-2">
                            Registered patient records
                        </p>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-green-50 text-green-700 flex items-center justify-center text-2xl">
                        ♙
                    </div>

                </div>

            </div>


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

                <div class="flex justify-between items-start">

                    <div>

                        <p class="text-sm text-slate-500 font-medium">
                            Total Appointments
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalAppointments) ?>
                        </h3>

                        <p class="text-xs text-slate-400 mt-2">
                            All appointment records
                        </p>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-2xl">
                        ▣
                    </div>

                </div>

            </div>


            <div class="stat-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

                <div class="flex justify-between items-start">

                    <div>

                        <p class="text-sm text-slate-500 font-medium">
                            BHW Accounts
                        </p>

                        <h3 class="text-3xl font-extrabold text-slate-900 mt-3">
                            <?= number_format($totalBHW) ?>
                        </h3>

                        <p class="text-xs text-slate-400 mt-2">
                            Registered health workers
                        </p>

                    </div>

                    <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center text-2xl">
                        ♧
                    </div>

                </div>

            </div>

        </section>


        <!-- ==================================================
             APPOINTMENT REPORTS
        ================================================== -->

        <section class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">


            <!-- APPOINTMENT STATUS -->

            <div class="report-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

                <div class="mb-6">

                    <h3 class="font-bold text-slate-900 text-lg">
                        Appointment Status
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">
                        Summary of all appointment records
                    </p>

                </div>


                <div class="space-y-5">


                    <?php

                    $appointmentStatuses = [
                        [
                            "name" => "Pending",
                            "count" => $pendingAppointments,
                            "color" => "bg-amber-500",
                            "text" => "text-amber-700"
                        ],
                        [
                            "name" => "Approved",
                            "count" => $approvedAppointments,
                            "color" => "bg-blue-500",
                            "text" => "text-blue-700"
                        ],
                        [
                            "name" => "Completed",
                            "count" => $completedAppointments,
                            "color" => "bg-green-600",
                            "text" => "text-green-700"
                        ],
                        [
                            "name" => "Cancelled",
                            "count" => $cancelledAppointments,
                            "color" => "bg-red-500",
                            "text" => "text-red-700"
                        ]
                    ];

                    ?>

                    <?php foreach ($appointmentStatuses as $item): ?>

                        <?php

                        $percentage = $appointmentStatusTotal > 0
                            ? ($item["count"] / $appointmentStatusTotal) * 100
                            : 0;

                        ?>

                        <div>

                            <div class="flex justify-between items-center mb-2">

                                <span class="text-sm font-semibold text-slate-700">
                                    <?= e($item["name"]) ?>
                                </span>

                                <span class="text-sm font-bold <?= e($item["text"]) ?>">
                                    <?= number_format($item["count"]) ?>
                                    <span class="text-slate-400 font-normal">
                                        (<?= round($percentage, 1) ?>%)
                                    </span>
                                </span>

                            </div>

                            <div class="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden">

                                <div
                                    class="<?= e($item["color"]) ?> h-full rounded-full"
                                    style="width: <?= $percentage ?>%">
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- GENDER DISTRIBUTION -->

            <div class="report-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">

                <div class="mb-6">

                    <h3 class="font-bold text-slate-900 text-lg">
                        Patient Gender Distribution
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">
                        Registered patients by gender
                    </p>

                </div>


                <div class="text-center mb-7">

                    <h4 class="text-4xl font-extrabold text-slate-900">
                        <?= number_format($genderTotal) ?>
                    </h4>

                    <p class="text-xs text-slate-400 mt-1">
                        Total categorized patients
                    </p>

                </div>


                <div class="space-y-5">

                    <div>

                        <div class="flex justify-between text-sm mb-2">

                            <span class="font-semibold text-slate-700">
                                Male
                            </span>

                            <span class="font-bold text-blue-700">
                                <?= number_format($malePatients) ?>
                                (<?= $malePercent ?>%)
                            </span>

                        </div>

                        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">

                            <div
                                class="h-full bg-blue-500 rounded-full"
                                style="width: <?= $malePercent ?>%">
                            </div>

                        </div>

                    </div>


                    <div>

                        <div class="flex justify-between text-sm mb-2">

                            <span class="font-semibold text-slate-700">
                                Female
                            </span>

                            <span class="font-bold text-pink-700">
                                <?= number_format($femalePatients) ?>
                                (<?= $femalePercent ?>%)
                            </span>

                        </div>

                        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">

                            <div
                                class="h-full bg-pink-500 rounded-full"
                                style="width: <?= $femalePercent ?>%">
                            </div>

                        </div>

                    </div>


                    <div>

                        <div class="flex justify-between text-sm mb-2">

                            <span class="font-semibold text-slate-700">
                                Other
                            </span>

                            <span class="font-bold text-purple-700">
                                <?= number_format($otherPatients) ?>
                                (<?= $otherPercent ?>%)
                            </span>

                        </div>

                        <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">

                            <div
                                class="h-full bg-purple-500 rounded-full"
                                style="width: <?= $otherPercent ?>%">
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </section>


        <!-- ==================================================
             MONTHLY PATIENT REGISTRATION
        ================================================== -->

        <section class="report-card bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">

            <div class="flex flex-wrap justify-between items-center gap-3 mb-8">

                <div>

                    <h3 class="font-bold text-slate-900 text-lg">
                        Monthly Patient Registration
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">
                        Patient registrations over the last six months
                    </p>

                </div>

                <span class="text-xs font-semibold text-green-800 bg-green-50 px-3 py-2 rounded-lg">
                    Last 6 Months
                </span>

            </div>


            <?php if (count($monthlyPatients) > 0): ?>

                <div class="flex items-end gap-3 md:gap-6 h-56 border-b border-l border-slate-200 px-3 pb-0">

                    <?php foreach ($monthlyPatients as $month): ?>

                        <?php

                        $value = (int)$month["total"];

                        $height = max(
                            5,
                            ($value / $maxMonthlyPatients) * 100
                        );

                        ?>

                        <div class="flex-1 h-full flex flex-col justify-end items-center min-w-0">

                            <span class="text-xs font-bold text-slate-600 mb-2">
                                <?= number_format($value) ?>
                            </span>

                            <div
                                class="chart-bar w-full max-w-16"
                                style="height: <?= $height ?>%">
                            </div>

                            <span class="text-[10px] md:text-xs text-slate-500 mt-3 mb-2 text-center whitespace-nowrap">
                                <?= e($month["month_label"]) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="py-12 text-center">

                    <p class="text-sm text-slate-400">
                        No monthly patient registration data available.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <!-- ==================================================
             RECENT APPOINTMENTS TABLE
        ================================================== -->

        <section class="report-card bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mb-8">

            <div class="p-6 border-b border-slate-100 flex flex-wrap justify-between items-center gap-3">

                <div>

                    <h3 class="font-bold text-slate-900 text-lg">
                        Recent Appointments
                    </h3>

                    <p class="text-xs text-slate-500 mt-1">
                        Latest 10 appointment records
                    </p>

                </div>

                <a
                    href="appointments.php"
                    class="text-xs font-bold text-green-800 hover:text-green-950 no-print">

                    View Appointments →

                </a>

            </div>


            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead class="bg-slate-50">

                        <tr>

                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">
                                Patient
                            </th>

                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">
                                Appointment
                            </th>

                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">
                                Purpose
                            </th>

                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">
                                BHW
                            </th>

                            <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase">
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody class="divide-y divide-slate-100">

                        <?php if (count($recentAppointments) > 0): ?>

                            <?php foreach ($recentAppointments as $appointment): ?>

                                <?php

                                $status = strtolower(
                                    trim($appointment["status"] ?? "")
                                );

                                $statusClass = "status-pending";

                                if ($status === "approved") {
                                    $statusClass = "status-approved";
                                } elseif ($status === "completed") {
                                    $statusClass = "status-completed";
                                } elseif ($status === "cancelled") {
                                    $statusClass = "status-cancelled";
                                }

                                ?>

                                <tr class="hover:bg-slate-50">

                                    <td class="px-6 py-4">

                                        <p class="text-sm font-semibold text-slate-800">
                                            <?= e($appointment["patient_name"] ?: "Unknown Patient") ?>
                                        </p>

                                        <p class="text-xs text-slate-400 mt-1">
                                            Appointment #<?= (int)$appointment["id"] ?>
                                        </p>

                                    </td>


                                    <td class="px-6 py-4">

                                        <p class="text-sm text-slate-700">
                                            <?= e($appointment["appointment_date"]) ?>
                                        </p>

                                        <p class="text-xs text-slate-400 mt-1">
                                            <?= e($appointment["appointment_time"]) ?>
                                        </p>

                                    </td>


                                    <td class="px-6 py-4">

                                        <span class="text-sm text-slate-600">
                                            <?= e($appointment["purpose"] ?: "Not specified") ?>
                                        </span>

                                    </td>


                                    <td class="px-6 py-4">

                                        <span class="text-sm text-slate-600">
                                            <?= e($appointment["bhw_name"] ?: "Unassigned") ?>
                                        </span>

                                    </td>


                                    <td class="px-6 py-4">

                                        <span class="status <?= e($statusClass) ?>">
                                            <?= e(ucfirst($status ?: "Unknown")) ?>
                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="5" class="px-6 py-12 text-center">

                                    <p class="text-sm text-slate-400">
                                        No appointment records found.
                                    </p>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>


        <!-- ==================================================
             REPORT FOOTER
        ================================================== -->

        <footer class="text-center py-5 border-t border-slate-200">

            <p class="text-xs text-slate-400">
                Barangay Guindacpan Health Services Management System
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Confidential Administrator Report
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
