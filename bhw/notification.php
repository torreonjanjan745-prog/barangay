
<?php
// ==========================================
// BHW NOTIFICATIONS
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

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// ==========================================
// MARK NOTIFICATION AS READ
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["mark_read"])) {

    $notificationId = (int) ($_POST["notification_id"] ?? 0);

    $stmt = $pdo->prepare("
        UPDATE bhw_notifications
        SET is_read = 1
        WHERE id = ? AND bhw_id = ?
    ");

    $stmt->execute([$notificationId, $bhwId]);

    header("Location: notifications.php");
    exit;
}

// ==========================================
// MARK ALL AS READ
// ==========================================

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["mark_all"])) {

    $stmt = $pdo->prepare("
        UPDATE bhw_notifications
        SET is_read = 1
        WHERE bhw_id = ?
    ");

    $stmt->execute([$bhwId]);

    header("Location: notifications.php");
    exit;
}

// ==========================================
// GET NOTIFICATIONS
// ==========================================

$stmt = $pdo->prepare("
    SELECT *
    FROM bhw_notifications
    WHERE bhw_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$bhwId]);

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// COUNT UNREAD
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM bhw_notifications
    WHERE bhw_id = ? AND is_read = 0
");

$stmt->execute([$bhwId]);

$unreadCount = (int) $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Notifications</title>

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

    </style>

</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen flex">

    <!-- OVERLAY -->

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
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="history"></i>
                Activity Logs

            </a>

            <a href="reports.php"
               class="menu-link flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300">

                <i data-lucide="chart-no-axes-combined"></i>
                Reports

            </a>

            <a href="notifications.php"
               class="active-link flex items-center gap-3 px-4 py-3 rounded-xl">

                <i data-lucide="bell"></i>
                Notifications

                <?php if ($unreadCount > 0): ?>

                    <span class="ml-auto bg-red-500 text-white
                                 text-xs px-2 py-1 rounded-full">

                        <?= $unreadCount ?>

                    </span>

                <?php endif; ?>

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
                        Notifications
                    </h2>

                    <p class="text-sm text-slate-500">
                        Barangay Health Center Updates
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


        <!-- PAGE CONTENT -->

        <section class="p-5 md:p-8">


            <!-- WELCOME -->

            <div class="mb-7">

                <h1 class="text-2xl font-bold">
                    Your Notifications
                </h1>

                <p class="text-sm text-slate-500 mt-2">
                    Stay updated with important health center activities.
                </p>

            </div>


            <!-- SUMMARY CARDS -->

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-7">

                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">

                        <div>

                            <p class="text-sm text-slate-500">
                                Total Notifications
                            </p>

                            <h2 class="text-3xl font-bold mt-3">
                                <?= count($notifications) ?>
                            </h2>

                        </div>

                        <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">

                            <i data-lucide="bell" class="w-7 h-7"></i>

                        </div>

                    </div>

                </div>


                <div class="bg-white border border-slate-200
                            rounded-2xl p-6 shadow-sm">

                    <div class="flex justify-between">

                        <div>

                            <p class="text-sm text-slate-500">
                                Unread Notifications
                            </p>

                            <h2 class="text-3xl font-bold mt-3">
                                <?= $unreadCount ?>
                            </h2>

                        </div>

                        <div class="bg-orange-100 text-orange-600 p-3 rounded-xl">

                            <i data-lucide="bell-ring" class="w-7 h-7"></i>

                        </div>

                    </div>

                </div>

            </div>


            <!-- NOTIFICATION LIST -->

            <div class="bg-white border border-slate-200
                        rounded-2xl shadow-sm overflow-hidden">

                <div class="p-5 md:p-6 border-b border-slate-200
                            flex flex-col sm:flex-row
                            justify-between gap-4">

                    <div>

                        <h2 class="font-bold text-lg">
                            Recent Notifications
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Your latest system announcements and updates.
                        </p>

                    </div>


                    <?php if ($unreadCount > 0): ?>

                        <form method="POST">

                            <button type="submit"
                                    name="mark_all"
                                    value="1"
                                    class="text-blue-600 hover:text-blue-800
                                           text-sm font-semibold">

                                Mark All as Read

                            </button>

                        </form>

                    <?php endif; ?>

                </div>


                <div class="divide-y divide-slate-100">

                    <?php if (!empty($notifications)): ?>

                        <?php foreach ($notifications as $notification): ?>

                            <?php

                            $type = $notification["notification_type"] ?? "info";

                            $styles = [
                                "info" => "bg-blue-100 text-blue-600",
                                "success" => "bg-green-100 text-green-600",
                                "warning" => "bg-orange-100 text-orange-600",
                                "danger" => "bg-red-100 text-red-600"
                            ];

                            $style = $styles[$type] ?? $styles["info"];

                            ?>

                            <div class="p-5 md:p-6 flex gap-4
                                        <?= !$notification["is_read"] ? "bg-blue-50/40" : "" ?>">

                                <div class="<?= $style ?> p-3 rounded-xl h-fit">

                                    <i data-lucide="bell" class="w-5 h-5"></i>

                                </div>


                                <div class="flex-1 min-w-0">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h3 class="font-bold text-slate-800">

                                            <?= e($notification["title"]) ?>

                                        </h3>

                                        <?php if (!$notification["is_read"]): ?>

                                            <span class="bg-blue-600 text-white
                                                         text-xs px-2 py-1 rounded-full">

                                                New

                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <p class="text-sm text-slate-600 mt-2">

                                        <?= nl2br(e($notification["message"])) ?>

                                    </p>


                                    <p class="text-xs text-slate-400 mt-3">

                                        <?= date(
                                            "M d, Y - h:i A",
                                            strtotime($notification["created_at"])
                                        ) ?>

                                    </p>

                                </div>


                                <?php if (!$notification["is_read"]): ?>

                                    <form method="POST">

                                        <input type="hidden"
                                               name="notification_id"
                                               value="<?= (int)$notification["id"] ?>">

                                        <button type="submit"
                                                name="mark_read"
                                                value="1"
                                                title="Mark as read"
                                                class="text-blue-600 hover:bg-blue-100
                                                       p-2 rounded-lg">

                                            <i data-lucide="check"
                                               class="w-5 h-5"></i>

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="py-16 px-6 text-center">

                            <div class="bg-slate-100 p-5 rounded-full
                                        inline-flex mb-4">

                                <i data-lucide="bell-off"
                                   class="w-10 h-10 text-slate-400"></i>

                            </div>

                            <h3 class="font-bold text-slate-700">
                                No Notifications Yet
                            </h3>

                            <p class="text-sm text-slate-400 mt-2">
                                Your notifications will appear here when available.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

                <div class="px-6 py-4 border-t border-slate-200
                            text-sm text-slate-500">

                    <?= count($notifications) ?> notification(s)

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

</body>
<script src="bhw.js"></script>
</html>
