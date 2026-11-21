
<?php
// ==========================================
// BHW DASHBOARD - MYSQLI
// ==========================================

session_name("BARANGAY_BHW_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_start();

// Check BHW login
if (!isset($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// Database connection
require_once __DIR__ . "/database.php";

// BHW information
$bhwName = $_SESSION["bhw_name"] ?? "Barangay Health Worker";

// ==========================================
// DATABASE STATISTICS
// ==========================================

function getCount($conn, $table) {

    $allowedTables = [
        "bhw_patients",
        "bhw_appointments",
        "bhw_health_records",
        "bhw_immunization",
        "bhw_prenatal"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS total FROM `$table`";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log(
            "Dashboard Count Error (" . $table . "): " .
            mysqli_error($conn)
        );

        return 0;
    }

    $row = mysqli_fetch_assoc($result);

    mysqli_free_result($result);

    return (int) ($row["total"] ?? 0);
}

$totalPatients = getCount($conn, "bhw_patients");
$totalAppointments = getCount($conn, "bhw_appointments");
$totalHealthRecords = getCount($conn, "bhw_health_records");
$totalImmunization = getCount($conn, "bhw_immunization");
$totalPrenatal = getCount($conn, "bhw_prenatal");

// ==========================================
// ESCAPE OUTPUT
// ==========================================

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>BHW Dashboard | Barangay Health Center</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .sidebar {
            transition: transform .3s ease;
        }

        .menu-link {
            transition: .2s ease;
        }

        .menu-link:hover {
            background: #1e293b;
            color: white;
        }

        .active-link {
            background: #2563eb;
            color: white;
        }

        .stat-card {
            transition: transform .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
        }

        @media(max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }
    </style>

</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen flex">

    <!-- MOBILE OVERLAY -->
    <div id="overlay"
         class="fixed inset-0 bg-black/50 z-40 hidden md:hidden">
    </div>

    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside id="sidebar"
           class="sidebar fixed md:sticky top-0 left-0 z-50
                  w-64 h-screen bg-slate-950 text-white
                  flex flex-col">

        <!-- LOGO -->

        <div class="px-6 py-6 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="bg-blue-600 p-3 rounded-xl">

                    <i data-lucide="heart-pulse"
                       class="w-7 h-7 text-white"></i>

                </div>

                <div>
                    <h1 class="font-bold text-lg">
                        Barangay Health
                    </h1>

                    <p class="text-xs text-slate-400">
                        BHW Management Portal
                    </p>
                </div>

            </div>

        </div>

        <!-- NAVIGATION -->

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">

            <p class="text-xs text-slate-500 font-bold uppercase mb-3 px-3">
                Main Menu
            </p>

            <a href="dashboard.php"
               class="menu-link active-link flex items-center gap-3 px-4 py-3 rounded-xl">

                <i data-lucide="layout-dashboard"></i>

                <span>Dashboard</span>

            </a>

            <a href="patients.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="users"></i>

                <span>Patients</span>

            </a>

            <a href="appointments.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="calendar-days"></i>

                <span>Appointments</span>

            </a>

            <p class="text-xs text-slate-500 font-bold uppercase mt-7 mb-3 px-3">
                Health Services
            </p>

            <a href="health_records.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="file-heart"></i>

                <span>Health Records</span>

            </a>

            <a href="immunization.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="syringe"></i>

                <span>Immunization</span>

            </a>

            <a href="prenatal.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="baby"></i>

                <span>Prenatal Records</span>

            </a>

            <p class="text-xs text-slate-500 font-bold uppercase mt-7 mb-3 px-3">
                Account
            </p>

            <a href="profile.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="user-round"></i>

                <span>My Profile</span>

            </a>

            <a href="notifications.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="bell"></i>

                <span>Notifications</span>

            </a>

        </nav>

        <!-- LOGOUT -->

        <div class="p-4 border-t border-slate-800">

            <a href="logout.php"
               onclick="return confirm('Are you sure you want to logout?')"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
                      text-red-400 hover:bg-red-500/10">

                <i data-lucide="log-out"></i>

                <span>Logout</span>

            </a>

        </div>

    </aside>


    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="flex-1 min-w-0">

        <!-- HEADER -->

        <header class="bg-white border-b border-slate-200
                       px-5 md:px-8 py-5 flex items-center
                       justify-between sticky top-0 z-30">

            <div class="flex items-center gap-4">

                <button id="menuBtn"
                        class="md:hidden p-2 rounded-lg hover:bg-slate-100">

                    <i data-lucide="menu"></i>

                </button>

                <div>

                    <h2 class="text-xl md:text-2xl font-bold">
                        BHW Dashboard
                    </h2>

                    <p class="text-sm text-slate-500">
                        Barangay Health Center Management
                    </p>

                </div>

            </div>

            <!-- PROFILE -->

            <div class="flex items-center gap-3">

                <div class="hidden sm:block text-right">

                    <p class="font-semibold text-sm">
                        <?= e($bhwName) ?>
                    </p>

                    <p class="text-xs text-slate-500">
                        Barangay Health Worker
                    </p>

                </div>

                <div class="w-11 h-11 rounded-full bg-blue-100
                            text-blue-700 flex items-center
                            justify-center font-bold">

                    <i data-lucide="user-round"></i>

                </div>

            </div>

        </header>


        <!-- PAGE CONTENT -->

        <section class="p-5 md:p-8">

            <!-- WELCOME -->

            <div class="bg-gradient-to-r from-blue-700 to-blue-500
                        rounded-2xl p-6 md:p-8 text-white mb-8">

                <p class="text-blue-100 text-sm mb-2">
                    Welcome back!
                </p>

                <h1 class="text-2xl md:text-3xl font-bold mb-2">

                    Hello, <?= e($bhwName) ?>!

                </h1>

                <p class="text-blue-100 text-sm md:text-base">

                    Manage patient information, appointments,
                    and community health services in one place.

                </p>

                <div class="mt-5">

                    <span class="inline-flex items-center gap-2
                                 bg-white/15 px-4 py-2 rounded-lg text-sm">

                        <i data-lucide="activity" class="w-4 h-4"></i>

                        Barangay Health Worker Portal

                    </span>

                </div>

            </div>


            <!-- STATISTICS -->

            <div class="flex items-center justify-between mb-5">

                <h2 class="text-lg font-bold">
                    Health Center Overview
                </h2>

                <span class="text-xs text-slate-500">
                    Live database counts
                </span>

            </div>


            <div class="grid grid-cols-1 sm:grid-cols-2
                        xl:grid-cols-3 gap-5 mb-9">


                <!-- PATIENTS -->

                <div class="stat-card bg-white p-6 rounded-2xl
                            border border-slate-200 shadow-sm">

                    <div class="flex justify-between">

                        <div>
                            <p class="text-sm text-slate-500">
                                Total Patients
                            </p>

                            <h3 class="text-3xl font-bold mt-3">
                                <?= $totalPatients ?>
                            </h3>
                        </div>

                        <div class="bg-blue-100 text-blue-600
                                    p-3 rounded-xl h-fit">

                            <i data-lucide="users" class="w-7 h-7"></i>

                        </div>

                    </div>

                    <a href="patients.php"
                       class="text-blue-600 text-sm font-semibold mt-5 inline-block">

                        View Patients →

                    </a>

                </div>


                <!-- APPOINTMENTS -->

                <div class="stat-card bg-white p-6 rounded-2xl
                            border border-slate-200 shadow-sm">

                    <div class="flex justify-between">

                        <div>
                            <p class="text-sm text-slate-500">
                                Total Appointments
                            </p>

                            <h3 class="text-3xl font-bold mt-3">
                                <?= $totalAppointments ?>
                            </h3>
                        </div>

                        <div class="bg-purple-100 text-purple-600
                                    p-3 rounded-xl h-fit">

                            <i data-lucide="calendar-days"
                               class="w-7 h-7"></i>

                        </div>

                    </div>

                    <a href="appointments.php"
                       class="text-purple-600 text-sm font-semibold mt-5 inline-block">

                        View Appointments →

                    </a>

                </div>


                <!-- HEALTH RECORDS -->

                <div class="stat-card bg-white p-6 rounded-2xl
                            border border-slate-200 shadow-sm">

                    <div class="flex justify-between">

                        <div>
                            <p class="text-sm text-slate-500">
                                Health Records
                            </p>

                            <h3 class="text-3xl font-bold mt-3">
                                <?= $totalHealthRecords ?>
                            </h3>
                        </div>

                        <div class="bg-green-100 text-green-600
                                    p-3 rounded-xl h-fit">

                            <i data-lucide="file-heart"
                               class="w-7 h-7"></i>

                        </div>

                    </div>

                    <a href="health_records.php"
                       class="text-green-600 text-sm font-semibold mt-5 inline-block">

                        View Health Records →

                    </a>
                </div>


                <!-- IMMUNIZATION -->

                <div class="stat-card bg-white p-6 rounded-2xl
                            border border-slate-200 shadow-sm">

                    <div class="flex justify-between">

                        <div>
                            <p class="text-sm text-slate-500">
                                Immunization Records
                            </p>

                            <h3 class="text-3xl font-bold mt-3">
                                <?= $totalImmunization ?>
                            </h3>
                        </div>

                        <div class="bg-orange-100 text-orange-600
                                    p-3 rounded-xl h-fit">

                            <i data-lucide="syringe"
                               class="w-7 h-7"></i>

                        </div>

                    </div>

                    <a href="immunization.php"
                       class="text-orange-600 text-sm font-semibold mt-5 inline-block">

                        View Immunization →

                    </a>

                </div>


                <!-- PRENATAL -->

                <div class="stat-card bg-white p-6 rounded-2xl
                            border border-slate-200 shadow-sm">

                    <div class="flex justify-between">

                        <div>
                            <p class="text-sm text-slate-500">
                                Prenatal Records
                            </p>

                            <h3 class="text-3xl font-bold mt-3">
                                <?= $totalPrenatal ?>
                            </h3>
                        </div>

                        <div class="bg-pink-100 text-pink-600
                                    p-3 rounded-xl h-fit">

                            <i data-lucide="baby"
                               class="w-7 h-7"></i>

                        </div>

                    </div>

                    <a href="prenatal.php"
                       class="text-pink-600 text-sm font-semibold mt-5 inline-block">

                        View Prenatal Records →

                    </a>

                </div>

            </div>


            <!-- QUICK ACTIONS -->

            <div class="mb-9">

                <h2 class="text-lg font-bold mb-5">
                    Quick Actions
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2
                            lg:grid-cols-3 gap-5">

                    <a href="patients.php"
                       class="bg-white border border-slate-200
                              rounded-2xl p-5 hover:border-blue-500
                              hover:shadow-md transition">

                        <div class="bg-blue-100 text-blue-600
                                    p-3 rounded-xl inline-flex mb-4">

                            <i data-lucide="user-plus"></i>

                        </div>

                        <h3 class="font-bold">
                            Manage Patients
                        </h3>

                        <p class="text-sm text-slate-500 mt-2">
                            View and manage registered patient information.
                        </p>

                    </a>


                    <a href="appointments.php"
                       class="bg-white border border-slate-200
                              rounded-2xl p-5 hover:border-purple-500
                              hover:shadow-md transition">

                        <div class="bg-purple-100 text-purple-600
                                    p-3 rounded-xl inline-flex mb-4">

                            <i data-lucide="calendar-plus"></i>

                        </div>

                        <h3 class="font-bold">
                            Manage Appointments
                        </h3>

                        <p class="text-sm text-slate-500 mt-2">
                            Review and manage patient appointments.
                        </p>

                    </a>


                    <a href="health_records.php"
                       class="bg-white border border-slate-200
                              rounded-2xl p-5 hover:border-green-500
                              hover:shadow-md transition">

                        <div class="bg-green-100 text-green-600
                                    p-3 rounded-xl inline-flex mb-4">

                            <i data-lucide="clipboard-plus"></i>

                        </div>

                        <h3 class="font-bold">
                            Health Records
                        </h3>

                        <p class="text-sm text-slate-500 mt-2">
                            Access and manage patient health records.
                        </p>

                    </a>

                </div>

            </div>


            <!-- SYSTEM INFORMATION -->

            <div class="bg-white border border-slate-200
                        rounded-2xl p-6">

                <div class="flex items-center gap-3 mb-4">

                    <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">

                        <i data-lucide="shield-check"></i>

                    </div>

                    <div>

                        <h2 class="font-bold">
                            BHW System Information
                        </h2>

                        <p class="text-sm text-slate-500">
                            Barangay Health Center Management System
                        </p>

                    </div>

                </div>

                <div class="border-t border-slate-100 pt-4
                            grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <div>
                        <p class="text-xs text-slate-500">
                            Database
                        </p>

                        <p class="font-semibold">
                            barangay_db
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-slate-500">
                            Account Type
                        </p>

                        <p class="font-semibold">
                            Barangay Health Worker
                        </p>
                    </div>

                </div>

            </div>


            <!-- FOOTER -->

            <footer class="text-center text-xs text-slate-400 mt-8">

                © <?= date("Y") ?> Barangay Hall Management System.

                All Rights Reserved.

            </footer>

        </section>

    </main>

</div>







</body>
<script src="bhw.js"></script>
</html>
