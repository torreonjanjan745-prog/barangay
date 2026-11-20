
<?php
// ==========================================
// BHW ACTIVITY LOGS
// ==========================================

session_name("BARANGAY_BHW_SESSION");
session_start();

if (!isset($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../db.php";

$bhwId = (int) $_SESSION["bhw_id"];
$bhwName = $_SESSION["bhw_name"] ?? "Barangay Health Worker";

// ==========================================
// SEARCH ACTIVITY
// ==========================================

$search = trim($_GET["search"] ?? "");

try {

    if ($search !== "") {

        $stmt = $pdo->prepare("
            SELECT *
            FROM bhw_activity_logs
            WHERE bhw_id = :bhw_id
            AND (
                activity LIKE :search
                OR description LIKE :searchDescription
            )
            ORDER BY created_at DESC
        ");

        $stmt->execute([
            ":bhw_id" => $bhwId,
            ":search" => "%".$search."%",
            ":searchDescription" => "%".$search."%"
        ]);

    } else {

        $stmt = $pdo->prepare("
            SELECT *
            FROM bhw_activity_logs
            WHERE bhw_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$bhwId]);

    }

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $activities = [];
    $error = "Unable to retrieve activity logs. Please check your database table.";

}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Activity Logs</title>

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


    <!-- SIDEBAR -->

    <aside id="sidebar"
           class="sidebar fixed md:sticky top-0 left-0 z-50
                  w-64 h-screen bg-slate-950 text-white
                  flex flex-col">

        <div class="px-6 py-6 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="bg-blue-600 p-3 rounded-xl">

                    <i data-lucide="heart-pulse"
                       class="w-7 h-7"></i>

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
               class="menu-link active-link flex items-center gap-3 px-4 py-3 rounded-xl">

                <i data-lucide="history"></i>

                Activity Logs

            </a>

        </nav>


        <!-- LOGOUT -->

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
                        Activity Logs
                    </h2>

                    <p class="text-sm text-slate-500">
                        Monitor your BHW system activities
                    </p>

                </div>

            </div>


            <div class="hidden sm:block text-right">

                <p class="font-semibold text-sm">
                    <?= e($bhwName) ?>
                </p>

                <p class="text-xs text-slate-500">
                    Barangay Health Worker
                </p>

            </div>

        </header>


        <!-- CONTENT -->

        <section class="p-5 md:p-8">


            <!-- PAGE INTRODUCTION -->

            <div class="mb-7">

                <div class="flex items-center gap-3 mb-2">

                    <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">

                        <i data-lucide="history" class="w-7 h-7"></i>

                    </div>

                    <div>

                        <h1 class="text-2xl font-bold">
                            Activity History
                        </h1>

                        <p class="text-sm text-slate-500">
                            Review your recorded activities and transactions.
                        </p>

                    </div>

                </div>

            </div>


            <!-- SUMMARY -->

            <div class="bg-white border border-slate-200
                        rounded-2xl p-6 mb-7 shadow-sm">

                <div class="flex items-center justify-between">

                    <div>

                        <p class="text-sm text-slate-500">
                            Total Recorded Activities
                        </p>

                        <h2 class="text-3xl font-bold mt-2">
                            <?= count($activities) ?>
                        </h2>

                    </div>

                    <div class="bg-blue-100 text-blue-600 p-4 rounded-xl">

                        <i data-lucide="clipboard-list"
                           class="w-8 h-8"></i>

                    </div>

                </div>

            </div>


            <!-- ACTIVITY TABLE -->

            <div class="bg-white border border-slate-200
                        rounded-2xl shadow-sm overflow-hidden">


                <!-- TABLE HEADER -->

                <div class="p-5 md:p-6 border-b border-slate-200
                            flex flex-col md:flex-row
                            justify-between gap-4">

                    <div>

                        <h2 class="font-bold text-lg">
                            Your Activity Records
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            All activities are arranged from newest to oldest.
                        </p>

                    </div>


                    <!-- SEARCH -->

                    <form method="GET"
                          class="flex gap-2">

                        <input
                            type="search"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search activities..."
                            class="border border-slate-300 rounded-lg px-4 py-2
                                   text-sm outline-none focus:ring-2
                                   focus:ring-blue-500 w-full md:w-64">

                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700
                                       text-white px-4 py-2 rounded-lg">

                            <i data-lucide="search"
                               class="w-5 h-5"></i>

                        </button>

                    </form>

                </div>


                <!-- ERROR MESSAGE -->

                <?php if (isset($error)): ?>

                    <div class="m-5 bg-red-50 border border-red-200
                                text-red-700 p-4 rounded-lg">

                        <?= e($error) ?>

                    </div>

                <?php endif; ?>


                <!-- TABLE -->

                <div class="overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">

                            <tr>

                                <th class="px-6 py-4">
                                    #
                                </th>

                                <th class="px-6 py-4">
                                    Activity
                                </th>

                                <th class="px-6 py-4">
                                    Description
                                </th>

                                <th class="px-6 py-4">
                                    Date & Time
                                </th>

                            </tr>

                        </thead>


                        <tbody class="divide-y divide-slate-100">

                            <?php if (!empty($activities)): ?>

                                <?php foreach ($activities as $index => $row): ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-6 py-5 text-slate-500">
                                            <?= $index + 1 ?>
                                        </td>


                                        <td class="px-6 py-5">

                                            <div class="flex items-center gap-2">

                                                <div class="bg-blue-100 text-blue-600
                                                            p-2 rounded-lg">

                                                    <i data-lucide="activity"
                                                       class="w-4 h-4"></i>

                                                </div>

                                                <span class="font-semibold text-slate-800">
                                                    <?= e($row["activity"]) ?>
                                                </span>

                                            </div>

                                        </td>


                                        <td class="px-6 py-5 text-slate-500">

                                            <?= e($row["description"] ?? "No description") ?>

                                        </td>


                                        <td class="px-6 py-5">

                                            <p class="font-medium text-slate-700">

                                                <?= date(
                                                    "M d, Y",
                                                    strtotime($row["created_at"])
                                                ) ?>

                                            </p>

                                            <p class="text-xs text-slate-400 mt-1">

                                                <?= date(
                                                    "h:i A",
                                                    strtotime($row["created_at"])
                                                ) ?>

                                            </p>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>

                                    <td colspan="4" class="px-6 py-16 text-center">

                                        <div class="flex flex-col items-center">

                                            <div class="bg-slate-100 p-5 rounded-full mb-4">

                                                <i data-lucide="clipboard-list"
                                                   class="w-10 h-10 text-slate-400"></i>

                                            </div>

                                            <h3 class="font-bold text-slate-700">
                                                No Activity Records Found
                                            </h3>

                                            <p class="text-sm text-slate-400 mt-2">
                                                Your activity history will appear here.
                                            </p>

                                        </div>

                                    </td>

                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>


                <!-- FOOTER -->

                <div class="px-6 py-4 border-t border-slate-200
                            text-sm text-slate-500">

                    Showing <?= count($activities) ?> activity record(s)

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


<!-- JAVASCRIPT -->

<script>

  

  

</script>

</body>
<script src="bhw.js"></script>
</html>
