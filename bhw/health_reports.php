
<?php
// ==========================================
// BHW REPORTS
// ==========================================

session_name("BARANGAY_BHW_SESSION");
session_start();

if (!isset($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../db.php";

$bhwName = $_SESSION["bhw_name"] ?? "Barangay Health Worker";

function getCount($pdo, $table) {
    $allowed = [
        "bhw_patients",
        "bhw_appointments",
        "bhw_health_records",
        "bhw_immunization",
        "bhw_prenatal"
    ];

    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    try {
        return (int) $pdo->query(
            "SELECT COUNT(*) FROM `$table`"
        )->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

$totalPatients = getCount($pdo, "bhw_patients");
$totalAppointments = getCount($pdo, "bhw_appointments");
$totalHealthRecords = getCount($pdo, "bhw_health_records");
$totalImmunization = getCount($pdo, "bhw_immunization");
$totalPrenatal = getCount($pdo, "bhw_prenatal");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Reports</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .sidebar {
            transition: transform .3s ease;
        }

        .menu-link:hover {
            background: #1e293b;
            color: white;
        }

        .active-link {
            background: #2563eb;
            color: white;
        }

        @media(max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }
        }

        @media print {
            #sidebar,
            #menuBtn,
            #overlay,
            .no-print {
                display: none !important;
            }

            main {
                width: 100% !important;
            }

            body {
                background: white;
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

    <!-- SIDEBAR -->
    <aside id="sidebar"
           class="sidebar fixed md:sticky top-0 left-0 z-50
                  w-64 h-screen bg-slate-950 text-white flex flex-col">

        <div class="px-6 py-6 border-b border-slate-800">
            <div class="flex items-center gap-3">

                <div class="bg-blue-600 p-3 rounded-xl">
                    <i data-lucide="heart-pulse" class="w-7 h-7"></i>
                </div>

                <div>
                    <h1 class="font-bold text-lg">Barangay Health</h1>
                    <p class="text-xs text-slate-400">BHW Management Portal</p>
                </div>

            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-2 overflow-y-auto">

            <p class="text-xs text-slate-500 font-bold uppercase px-3 mb-3">
                Main Menu
            </p>

            <a href="dashboard.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="layout-dashboard"></i>
                Dashboard
            </a>

            <a href="patients.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="users"></i>
                Patients
            </a>

            <a href="appointments.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="calendar-days"></i>
                Appointments
            </a>

            <p class="text-xs text-slate-500 font-bold uppercase px-3 mt-7 mb-3">
                Health Services
            </p>

            <a href="health_records.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="file-heart"></i>
                Health Records
            </a>

            <a href="immunization.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="syringe"></i>
                Immunization
            </a>

            <a href="prenatal.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="baby"></i>
                Prenatal Records
            </a>

            <p class="text-xs text-slate-500 font-bold uppercase px-3 mt-7 mb-3">
                Account
            </p>

            <a href="profile.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="user-round"></i>
                My Profile
            </a>

            <a href="activity_logs.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">
                <i data-lucide="history"></i>
                Activity Logs
            </a>

            <a href="reports.php"
               class="active-link flex items-center gap-3 px-4 py-3 rounded-xl">
                <i data-lucide="chart-no-axes-combined"></i>
                Reports
            </a>

        </nav>

        <div class="p-4 border-t border-slate-800">
            <a href="logout.php"
               onclick="return confirm('Are you sure you want to logout?')"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-400 hover:bg-red-500/10">
                <i data-lucide="log-out"></i>
                Logout
            </a>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0">

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
                        Reports & Statistics
                    </h2>

                    <p class="text-sm text-slate-500">
                        Barangay Health Center Reports
                    </p>
                </div>

            </div>

            <div class="hidden sm:block text-right">
                <p class="font-semibold text-sm"><?= e($bhwName) ?></p>
                <p class="text-xs text-slate-500">Barangay Health Worker</p>
            </div>

        </header>

        <section class="p-5 md:p-8">

            <!-- TITLE -->
            <div class="flex flex-col sm:flex-row sm:items-center
                        justify-between gap-4 mb-7">

                <div>
                    <h1 class="text-2xl font-bold">
                        Health Center Reports
                    </h1>

                    <p class="text-sm text-slate-500 mt-1">
                        Overview of registered health service records.
                    </p>
                </div>

                <button onclick="window.print()"
                        class="no-print bg-blue-600 hover:bg-blue-700
                               text-white px-5 py-3 rounded-xl
                               flex items-center justify-center gap-2">

                    <i data-lucide="printer" class="w-5 h-5"></i>
                    Print Report

                </button>

            </div>

            <!-- REPORT DATE -->
            <div class="bg-white border border-slate-200 rounded-xl
                        p-5 mb-6">

                <p class="text-xs text-slate-500 uppercase font-bold">
                    Report Generated
                </p>

                <p class="font-semibold mt-2">
                    <?= date("F d, Y - h:i A") ?>
                </p>

            </div>

            <!-- STATISTICS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                <!-- PATIENTS -->
                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Total Patients</p>
                            <h2 class="text-3xl font-bold mt-3">
                                <?= $totalPatients ?>
                            </h2>
                        </div>

                        <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">
                            <i data-lucide="users" class="w-7 h-7"></i>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        Registered patient records
                    </p>
                </div>

                <!-- APPOINTMENTS -->
                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Appointments</p>
                            <h2 class="text-3xl font-bold mt-3">
                                <?= $totalAppointments ?>
                            </h2>
                        </div>

                        <div class="bg-purple-100 text-purple-600 p-3 rounded-xl">
                            <i data-lucide="calendar-days" class="w-7 h-7"></i>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        Recorded appointment entries
                    </p>
                </div>

                <!-- HEALTH RECORDS -->
                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Health Records</p>
                            <h2 class="text-3xl font-bold mt-3">
                                <?= $totalHealthRecords ?>
                            </h2>
                        </div>

                        <div class="bg-green-100 text-green-600 p-3 rounded-xl">
                            <i data-lucide="file-heart" class="w-7 h-7"></i>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        Recorded health information
                    </p>
                </div>

                <!-- IMMUNIZATION -->
                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Immunization</p>
                            <h2 class="text-3xl font-bold mt-3">
                                <?= $totalImmunization ?>
                            </h2>
                        </div>

                        <div class="bg-orange-100 text-orange-600 p-3 rounded-xl">
                            <i data-lucide="syringe" class="w-7 h-7"></i>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        Immunization record entries
                    </p>
                </div>

                <!-- PRENATAL -->
                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">
                        <div>
                            <p class="text-sm text-slate-500">Prenatal Records</p>
                            <h2 class="text-3xl font-bold mt-3">
                                <?= $totalPrenatal ?>
                            </h2>
                        </div>

                        <div class="bg-pink-100 text-pink-600 p-3 rounded-xl">
                            <i data-lucide="baby" class="w-7 h-7"></i>
                        </div>
                    </div>

                    <p class="text-xs text-slate-400 mt-4">
                        Prenatal record entries
                    </p>
                </div>

            </div>

            <!-- SUMMARY TABLE -->
            <div class="bg-white border border-slate-200
                        rounded-2xl shadow-sm mt-8 overflow-hidden">

                <div class="p-6 border-b border-slate-200">
                    <h2 class="font-bold text-lg">
                        Health Services Summary
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Overall database record counts
                    </p>
                </div>

                <div class="overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">
                            <tr>
                                <th class="px-6 py-4">Health Service</th>
                                <th class="px-6 py-4">Total Records</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">

                            <?php
                            $reports = [
                                ["Patients", $totalPatients],
                                ["Appointments", $totalAppointments],
                                ["Health Records", $totalHealthRecords],
                                ["Immunization", $totalImmunization],
                                ["Prenatal Records", $totalPrenatal]
                            ];
                            ?>

                            <?php foreach ($reports as $report): ?>

                                <tr>
                                    <td class="px-6 py-4 font-medium">
                                        <?= e($report[0]) ?>
                                    </td>

                                    <td class="px-6 py-4 font-bold">
                                        <?= (int)$report[1] ?>
                                    </td>

                                    <td class="px-6 py-4">
                                        <span class="bg-green-100 text-green-700
                                                     px-3 py-1 rounded-full text-xs">
                                            Retrieved
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </div>

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
