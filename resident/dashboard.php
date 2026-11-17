
<?php
// =============================================
// RESIDENT DASHBOARD - BARANGAY GUINDACPAN
// =============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';

// CHECK LOGIN
if (!isset($_SESSION['resident_id'])) {
    header("Location: ../main.php");
    exit();
}

$residentId = (int) $_SESSION['resident_id'];

$resident = [];
$residentName = "Resident";
$errorMessage = "";

// GET RESIDENT INFORMATION
try {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT * FROM residents WHERE id = ? LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $residentId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $resident = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    if (!$resident) {
        session_unset();
        session_destroy();

        header("Location: ../main.php");
        exit();
    }

    $residentName = $resident['full_name']
        ?? $resident['fullName']
        ?? 'Resident';

} catch (Throwable $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $errorMessage = "Unable to load resident information.";
}

// SAFE OUTPUT
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// RESIDENT INFORMATION
$username = $resident['username'] ?? 'N/A';

$address = $resident['purok_address']
    ?? $resident['address']
    ?? 'N/A';

$birthday = $resident['birthday'] ?? 'N/A';

$status = $resident['status'] ?? 'Pending';

$statusColor = strtolower($status) === 'approved'
    ? 'bg-green-100 text-green-700'
    : (strtolower($status) === 'rejected'
        ? 'bg-red-100 text-red-700'
        : 'bg-yellow-100 text-yellow-700');

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Dashboard | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, sans-serif;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 12px 15px;
            border-radius: 10px;
            color: #cbd5e1;
            transition: .2s;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: #166534;
            color: white;
        }

        .sidebar-link svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .dashboard-card {
            transition: .2s;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
        }

        #mobileSidebar {
            transition: transform .3s ease;
        }

        #sidebarOverlay {
            transition: opacity .3s ease;
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- MOBILE OVERLAY -->
    <div
        id="sidebarOverlay"
        class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"
        onclick="closeSidebar()">
    </div>

    <!-- SIDEBAR -->
    <aside
        id="mobileSidebar"
        class="fixed top-0 left-0 z-50 h-screen w-72 bg-slate-950 text-white
        flex flex-col -translate-x-full lg:translate-x-0">

        <!-- BRAND -->
        <div class="px-6 py-7 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="w-12 h-12 rounded-xl bg-green-700 flex items-center justify-center text-2xl">
                    🏛️
                </div>

                <div>
                    <h1 class="font-bold text-lg leading-tight">
                        Barangay
                    </h1>

                    <p class="text-green-400 text-sm font-semibold">
                        Guindacpan
                    </p>
                </div>

            </div>

            <p class="text-xs text-slate-400 mt-4 tracking-widest uppercase">
                Resident Portal
            </p>

        </div>

        <!-- NAVIGATION -->
        <nav class="flex-1 px-4 py-6 overflow-y-auto">

            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 mb-3">
                Main Menu
            </p>

            <div class="space-y-2">

                <a href="#dashboard" class="sidebar-link active">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                    </svg>

                    Dashboard
                </a>

                <a href="#profile" class="sidebar-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"/>
                        <path d="M4 21a8 8 0 0 1 16 0"/>
                    </svg>

                    My Profile
                </a>

                <a href="#services" class="sidebar-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M4 5h16v14H4z"/>
                        <path d="M8 9h8M8 13h8M8 17h5"/>
                    </svg>

                    Barangay Services
                </a>

                <a href="#requests" class="sidebar-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M7 3h10l4 4v14H3V3z"/>
                        <path d="M7 3v5h10V3M7 13h10M7 17h7"/>
                    </svg>

                    My Requests
                </a>

                <a href="#announcements" class="sidebar-link">

                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M3 11v2h3l12 5V6L6 11z"/>
                        <path d="M6 13l2 7h4l-2-5"/>
                        <path d="M21 9v6"/>
                    </svg>

                    Announcements
                </a>

            </div>

            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider px-3 mt-9 mb-3">
                Account
            </p>

            <a href="resident_logout.php"
               class="sidebar-link hover:!bg-red-700">

                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10 17l5-5-5-5"/>
                    <path d="M15 12H3"/>
                    <path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>
                </svg>

                Logout
            </a>

        </nav>

        <!-- SIDEBAR FOOTER -->
        <div class="p-5 border-t border-slate-800">

            <p class="text-xs text-slate-500">
                Barangay Guindacpan
            </p>

            <p class="text-xs text-slate-600 mt-1">
                Resident Management Portal
            </p>

        </div>

    </aside>


    <!-- MAIN CONTENT -->
    <main class="min-h-screen lg:ml-72">

        <!-- TOP HEADER -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30">

            <div class="px-5 md:px-8 py-4 flex items-center justify-between">

                <div class="flex items-center gap-4">

                    <!-- MOBILE MENU BUTTON -->
                    <button
                        onclick="openSidebar()"
                        class="lg:hidden p-2 rounded-lg hover:bg-slate-100"
                        aria-label="Open navigation menu">

                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>

                    </button>

                    <div>
                        <p class="text-sm text-slate-500">
                            Resident Portal
                        </p>

                        <h2 class="text-lg md:text-xl font-bold text-slate-900">
                            Dashboard
                        </h2>
                    </div>

                </div>

                <div class="flex items-center gap-3">

                    <div class="hidden sm:block text-right">
                        <p class="text-sm font-semibold text-slate-800">
                            <?= e($residentName) ?>
                        </p>

                        <p class="text-xs text-slate-500">
                            Resident Account
                        </p>
                    </div>

                    <div class="w-10 h-10 rounded-full bg-green-100 text-green-800
                        flex items-center justify-center font-bold">
                        <?= e(strtoupper(substr($residentName, 0, 1))) ?>
                    </div>

                </div>

            </div>

        </header>


        <!-- PAGE CONTENT -->
        <div class="p-5 md:p-8">

            <!-- WELCOME -->
            <section id="dashboard" class="mb-8">

                <div class="bg-gradient-to-r from-green-950 to-green-800
                    rounded-2xl p-6 md:p-9 text-white shadow-lg">

                    <p class="text-green-300 text-sm font-semibold uppercase tracking-wider">
                        Welcome Back
                    </p>

                    <h1 class="text-2xl md:text-3xl font-bold mt-2">
                        Hello, <?= e($residentName) ?>!
                    </h1>

                    <p class="text-green-100 mt-3 max-w-2xl text-sm md:text-base">
                        Welcome to the Barangay Guindacpan Resident Portal.
                        Access barangay services and monitor your document requests here.
                    </p>

                    <a href="#services"
                       class="inline-block mt-6 bg-white text-green-900
                       font-semibold px-5 py-3 rounded-lg hover:bg-green-50 transition">

                        Explore Services
                    </a>

                </div>

            </section>


            <!-- ERROR MESSAGE -->
            <?php if ($errorMessage !== ""): ?>

                <div class="bg-red-100 border border-red-200 text-red-700
                    p-4 rounded-xl mb-6">

                    <?= e($errorMessage) ?>

                </div>

            <?php endif; ?>


            <!-- OVERVIEW -->
            <section class="mb-10">

                <div class="flex items-center justify-between mb-5">

                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            Overview
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Your resident activity summary
                        </p>
                    </div>

                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">

                    <!-- TOTAL REQUESTS -->
                    <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm border border-slate-100">

                        <div class="flex items-center justify-between">

                            <div>
                                <p class="text-sm text-slate-500 font-medium">
                                    Total Requests
                                </p>

                                <h3 class="text-3xl font-bold mt-3 text-slate-900">
                                    0
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-blue-100
                                text-blue-700 flex items-center justify-center text-2xl">
                                📄
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            Your submitted document requests
                        </p>

                    </div>


                    <!-- PENDING -->
                    <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm border border-slate-100">

                        <div class="flex items-center justify-between">

                            <div>
                                <p class="text-sm text-slate-500 font-medium">
                                    Pending Requests
                                </p>

                                <h3 class="text-3xl font-bold mt-3 text-yellow-600">
                                    0
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-yellow-100
                                text-yellow-700 flex items-center justify-center text-2xl">
                                ⏳
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            Requests awaiting processing
                        </p>

                    </div>


                    <!-- APPROVED -->
                    <div class="dashboard-card bg-white rounded-2xl p-6 shadow-sm border border-slate-100">

                        <div class="flex items-center justify-between">

                            <div>
                                <p class="text-sm text-slate-500 font-medium">
                                    Approved Requests
                                </p>

                                <h3 class="text-3xl font-bold mt-3 text-green-600">
                                    0
                                </h3>
                            </div>

                            <div class="w-12 h-12 rounded-xl bg-green-100
                                text-green-700 flex items-center justify-center text-2xl">
                                ✓
                            </div>

                        </div>

                        <p class="text-xs text-slate-400 mt-4">
                            Requests approved by the barangay
                        </p>

                    </div>

                </div>

            </section>


            <!-- BARANGAY SERVICES -->
            <section id="services" class="mb-10">

                <div class="mb-5">
                    <h2 class="text-xl font-bold text-slate-900">
                        Barangay Services
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Select a service to submit a document request.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">

                    <!-- CLEARANCE -->
                    <div class="dashboard-card bg-white border border-slate-200
                        rounded-2xl p-6 shadow-sm">

                        <div class="w-12 h-12 rounded-xl bg-blue-100
                            flex items-center justify-center text-2xl">
                            📋
                        </div>

                        <h3 class="font-bold text-lg mt-5">
                            Barangay Clearance
                        </h3>

                        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                            Request a barangay clearance certificate for your personal or official transactions.
                        </p>

                        <a href="request_clearance.php"
                           class="inline-block mt-5 text-green-800 font-semibold
                           text-sm hover:underline">

                            Request Now →
                        </a>

                    </div>


                    <!-- INDIGENCY -->
                    <div class="dashboard-card bg-white border border-slate-200
                        rounded-2xl p-6 shadow-sm">

                        <div class="w-12 h-12 rounded-xl bg-purple-100
                            flex items-center justify-center text-2xl">
                            📑
                        </div>

                        <h3 class="font-bold text-lg mt-5">
                            Certificate of Indigency
                        </h3>

                        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                            Apply for a certificate of indigency for eligible purposes.
                        </p>

                        <a href="request_indigency.php"
                           class="inline-block mt-5 text-green-800 font-semibold
                           text-sm hover:underline">

                            Request Now →
                        </a>

                    </div>


                    <!-- RESIDENCY -->
                    <div class="dashboard-card bg-white border border-slate-200
                        rounded-2xl p-6 shadow-sm">

                        <div class="w-12 h-12 rounded-xl bg-green-100
                            flex items-center justify-center text-2xl">
                            🏠
                        </div>

                        <h3 class="font-bold text-lg mt-5">
                            Certificate of Residency
                        </h3>

                        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
                            Request proof of your residency in Barangay Guindacpan.
                        </p>

                        <a href="request_residency.php"
                           class="inline-block mt-5 text-green-800 font-semibold
                           text-sm hover:underline">

                            Request Now →
                        </a>

                    </div>

                </div>

            </section>


            <!-- MY REQUESTS -->
            <section id="requests"
                class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-10">

                <div class="mb-5">

                    <h2 class="text-xl font-bold text-slate-900">
                        My Recent Requests
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Track the status of your submitted applications.
                    </p>

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full text-left min-w-[500px]">

                        <thead>
                            <tr class="border-b border-slate-200 text-sm text-slate-500">

                                <th class="py-4 font-semibold">Document</th>
                                <th class="py-4 font-semibold">Date Submitted</th>
                                <th class="py-4 font-semibold">Status</th>

                            </tr>
                        </thead>

                        <tbody>

                            <tr>
                                <td colspan="3" class="py-10 text-center">

                                    <div class="text-3xl mb-3">📂</div>

                                    <p class="font-semibold text-slate-700">
                                        No requests yet
                                    </p>

                                    <p class="text-sm text-slate-400 mt-1">
                                        Your submitted document requests will appear here.
                                    </p>

                                </td>
                            </tr>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- ANNOUNCEMENTS -->
            <section id="announcements"
                class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-10">

                <div class="flex items-center gap-3 mb-5">

                    <div class="w-11 h-11 rounded-xl bg-orange-100
                        flex items-center justify-center text-xl">
                        📢
                    </div>

                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            Barangay Announcements
                        </h2>

                        <p class="text-sm text-slate-500">
                            Latest community updates
                        </p>
                    </div>

                </div>

                <div class="bg-slate-50 rounded-xl p-5 text-center">

                    <p class="text-slate-500 text-sm">
                        No announcements available at the moment.
                    </p>

                </div>

            </section>


            <!-- PROFILE -->
            <section id="profile"
                class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-10">

                <div class="flex flex-col sm:flex-row sm:items-center
                    justify-between gap-4 mb-6">

                    <div>
                        <h2 class="text-xl font-bold text-slate-900">
                            My Profile
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            Your registered resident information
                        </p>
                    </div>

                    <span class="inline-flex w-fit px-4 py-2 rounded-full
                        text-xs font-bold <?= e($statusColor) ?>">

                        <?= e($status) ?>

                    </span>

                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="bg-slate-50 rounded-xl p-4">

                        <p class="text-xs text-slate-500 uppercase font-semibold">
                            Full Name
                        </p>

                        <p class="font-semibold text-slate-800 mt-2">
                            <?= e($residentName) ?>
                        </p>

                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">

                        <p class="text-xs text-slate-500 uppercase font-semibold">
                            Username
                        </p>

                        <p class="font-semibold text-slate-800 mt-2">
                            <?= e($username) ?>
                        </p>

                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">

                        <p class="text-xs text-slate-500 uppercase font-semibold">
                            Address
                        </p>

                        <p class="font-semibold text-slate-800 mt-2">
                            <?= e($address) ?>
                        </p>

                    </div>

                    <div class="bg-slate-50 rounded-xl p-4">

                        <p class="text-xs text-slate-500 uppercase font-semibold">
                            Birthday
                        </p>

                        <p class="font-semibold text-slate-800 mt-2">
                            <?= e($birthday) ?>
                        </p>

                    </div>

                </div>

                <div class="mt-6 border-t border-slate-100 pt-5">

                    <p class="text-sm text-slate-500">
                        If your personal information needs correction,
                        please contact the Barangay Guindacpan office.
                    </p>

                </div>

            </section>


            <!-- FOOTER -->
            <footer class="text-center text-sm text-slate-400 py-5">

                © <?= date("Y") ?> Barangay Guindacpan.

                All Rights Reserved.

            </footer>

        </div>

    </main>

</div>


<!-- MOBILE SIDEBAR SCRIPT -->
<script>

    const sidebar = document.getElementById("mobileSidebar");
    const overlay = document.getElementById("sidebarOverlay");

    function openSidebar() {
        sidebar.classList.remove("-translate-x-full");
        overlay.classList.remove("hidden");
    }

    function closeSidebar() {
        sidebar.classList.add("-translate-x-full");
        overlay.classList.add("hidden");
    }

    // Close mobile sidebar after clicking a navigation link
    document.querySelectorAll("#mobileSidebar a[href^='#']").forEach(link => {
        link.addEventListener("click", () => {
            document.querySelectorAll(".sidebar-link").forEach(item => {
                item.classList.remove("active");
            });

            link.classList.add("active");

            if (window.innerWidth < 1024) {
                closeSidebar();
            }
        });
    });

</script>

</body>
</html>
