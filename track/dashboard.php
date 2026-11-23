
<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
// =====================================================
// BARANGAY GUINDACPAN
// RESIDENT TRACKING DASHBOARD
// File: tracking/dashboard.php
// Database: barangay_db
// Connection: Shared PDO
// =====================================================

// SESSION SECURITY
$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {
    session_name("BARANGAY_RESIDENT_SESSION");

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

// AUTHENTICATION
if (empty($_SESSION["resident_id"])) {
    header("Location: ../resident/login.php");
    exit;
}

// DATABASE
require_once "../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection is unavailable.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$residentId = (int) $_SESSION["resident_id"];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$message = "";
$error = "";

try {
    // RESIDENT PROFILE
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            username,
            purok_address,
            birthday,
            status,
            profile_picture
        FROM residents
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(["id" => $residentId]);
    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../resident/login.php");
        exit;
    }

    // REQUEST STATISTICS
    $stats = [
        "total" => 0,
        "pending" => 0,
        "processing" => 0,
        "approved" => 0,
        "completed" => 0,
        "rejected" => 0
    ];

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(status = 'Pending') AS pending,
            SUM(status = 'Processing') AS processing,
            SUM(status = 'Approved') AS approved,
            SUM(status = 'Completed') AS completed,
            SUM(status = 'Rejected') AS rejected
        FROM requests
        WHERE resident_id = :resident_id
    ");

    $stmt->execute(["resident_id" => $residentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        foreach ($stats as $key => $value) {
            $stats[$key] = (int)($result[$key] ?? 0);
        }
    }

    // RECENT REQUESTS
    $stmt = $pdo->prepare("
        SELECT
            id,
            tracking_code,
            request_type,
            purpose,
            status,
            submitted_at,
            updated_at
        FROM requests
        WHERE resident_id = :resident_id
        ORDER BY submitted_at DESC
        LIMIT 10
    ");

    $stmt->execute(["resident_id" => $residentId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $ex) {
    error_log("Tracking Dashboard Error: " . $ex->getMessage());
    $error = "Unable to load dashboard information. Please check your database tables.";
    $resident = $resident ?? [];
    $requests = [];
}

$defaultPicture = "../asset/default-profile.png";

$picture = !empty($resident["profile_picture"])
    ? $resident["profile_picture"]
    : $defaultPicture;

$statusColors = [
    "Pending" => "bg-amber-100 text-amber-700",
    "Processing" => "bg-blue-100 text-blue-700",
    "Approved" => "bg-indigo-100 text-indigo-700",
    "Completed" => "bg-green-100 text-green-700",
    "Rejected" => "bg-red-100 text-red-700"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Resident Tracking Dashboard | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar {
            transition: transform .3s ease;
        }

        .card {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(0,0,0,.06);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800">

<div class="min-h-screen flex">

    <!-- MOBILE OVERLAY -->
    <div id="overlay"
         class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"
         onclick="toggleSidebar()"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
        class="sidebar fixed lg:sticky top-0 left-0 z-50
        w-72 h-screen bg-slate-950 text-white
        -translate-x-full lg:translate-x-0
        flex flex-col">

        <div class="p-6 border-b border-white/10">

            <div class="flex items-center gap-3">

                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center overflow-hidden">
                    <img src="../asset/logo.jpg"
                         alt="Barangay Logo"
                         class="w-full h-full object-contain">
                </div>

                <div>
                    <h1 class="font-bold text-lg">Barangay Guindacpan</h1>
                    <p class="text-xs text-slate-400">Resident Portal</p>
                </div>

            </div>

        </div>

        <nav class="flex-1 p-5 space-y-2">

            <p class="text-xs uppercase tracking-wider text-slate-500 font-bold mb-4">
                Main Menu
            </p>

            <a href="dashboard.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-600 text-white">
                <i data-lucide="layout-dashboard"></i>
                Dashboard
            </a>

            <a href="track.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/10">
                <i data-lucide="search"></i>
                Track Request
            </a>

            <a href="profile.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-300 hover:bg-white/10">
                <i data-lucide="user-round"></i>
                My Profile
            </a>

        </nav>

        <div class="p-5 border-t border-white/10">

            <a href="../resident/logout.php"
               onclick="return confirm('Are you sure you want to logout?')"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
               text-red-300 hover:bg-red-500/10">
                <i data-lucide="log-out"></i>
                Logout
            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0">

        <!-- TOPBAR -->
        <header class="bg-white border-b border-slate-200 px-5 md:px-8 py-4">

            <div class="flex items-center justify-between gap-4">

                <div class="flex items-center gap-3">

                    <button onclick="toggleSidebar()"
                        class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                        <i data-lucide="menu"></i>
                    </button>

                    <div>
                        <h2 class="font-bold text-xl md:text-2xl">
                            Resident Dashboard
                        </h2>
                        <p class="text-sm text-slate-500">
                            Barangay Document Tracking Portal
                        </p>
                    </div>

                </div>

                <div class="hidden sm:flex items-center gap-3">

                    <div class="text-right">
                        <p class="text-sm font-semibold">
                            <?= e($resident["full_name"] ?? "Resident") ?>
                        </p>
                        <p class="text-xs text-slate-500">Resident Account</p>
                    </div>

                    <img src="<?= e($picture) ?>"
                         onerror="this.onerror=null;this.src='../asset/default-profile.png'"
                         class="w-11 h-11 rounded-full object-cover border"
                         alt="Profile">

                </div>

            </div>

        </header>

        <div class="p-5 md:p-8 max-w-7xl mx-auto">

            <?php if ($error): ?>
                <div class="mb-6 bg-red-50 text-red-700 border border-red-200 p-4 rounded-xl">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- WELCOME -->
            <section class="mb-8">

                <div class="bg-gradient-to-r from-blue-700 to-indigo-700
                            rounded-2xl p-6 md:p-8 text-white
                            flex flex-col md:flex-row md:items-center
                            justify-between gap-6">

                    <div>
                        <p class="text-blue-100 text-sm mb-2">
                            Welcome back!
                        </p>

                        <h2 class="text-2xl md:text-3xl font-extrabold mb-2">
                            <?= e($resident["full_name"] ?? "Resident") ?>
                        </h2>

                        <p class="text-blue-100 text-sm">
                            Manage your profile and monitor your document requests.
                        </p>
                    </div>

                    <a href="track.php"
                       class="inline-flex items-center justify-center gap-2
                       bg-white text-blue-700 px-5 py-3 rounded-xl
                       font-bold hover:bg-blue-50 transition">
                        <i data-lucide="search" class="w-5 h-5"></i>
                        Track a Request
                    </a>

                </div>

            </section>

            <!-- PROFILE + STATS -->
            <section class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">

                <!-- PROFILE CARD -->
                <div class="card bg-white border border-slate-200 rounded-2xl p-6">

                    <div class="flex items-center justify-between mb-5">

                        <h3 class="font-bold text-lg">My Profile</h3>

                        <a href="profile.php"
                           class="text-blue-600 text-sm font-semibold hover:underline">
                            Edit Profile
                        </a>

                    </div>

                    <div class="flex flex-col items-center text-center">

                        <div class="relative mb-4">

                            <img src="<?= e($picture) ?>"
                                 onerror="this.onerror=null;this.src='../asset/default-profile.png'"
                                 alt="Resident Profile"
                                 class="w-28 h-28 rounded-full object-cover
                                 border-4 border-blue-50 shadow-sm">

                            <a href="profile.php"
                               class="absolute bottom-0 right-0
                               bg-blue-600 text-white p-2 rounded-full
                               border-4 border-white">
                                <i data-lucide="camera" class="w-4 h-4"></i>
                            </a>

                        </div>

                        <h4 class="font-bold text-lg">
                            <?= e($resident["full_name"] ?? "") ?>
                        </h4>

                        <p class="text-sm text-slate-500 mb-4">
                            @<?= e($resident["username"] ?? "") ?>
                        </p>

                        <div class="w-full text-left space-y-3 border-t pt-4">

                            <div>
                                <p class="text-xs text-slate-400">Purok Address</p>
                                <p class="text-sm font-semibold">
                                    <?= e($resident["purok_address"] ?? "Not provided") ?>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-400">Birthday</p>
                                <p class="text-sm font-semibold">
                                    <?= e($resident["birthday"] ?? "Not provided") ?>
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-400">Account Status</p>
                                <p class="text-sm font-semibold">
                                    <?= e($resident["status"] ?? "Unknown") ?>
                                </p>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- REQUEST STATISTICS -->
                <div class="xl:col-span-2">

                    <h3 class="font-bold text-lg mb-4">Request Overview</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                        <?php
                        $statCards = [
                            ["Total Requests", "total", "files", "blue"],
                            ["Pending", "pending", "clock", "amber"],
                            ["Processing", "processing", "loader-circle", "indigo"],
                            ["Approved", "approved", "badge-check", "violet"],
                            ["Completed", "completed", "check-circle", "green"],
                            ["Rejected", "rejected", "x-circle", "red"]
                        ];
                        ?>

                        <?php foreach ($statCards as $card): ?>

                            <div class="card bg-white border border-slate-200
                                        rounded-2xl p-5 flex items-center gap-4">

                                <div class="w-12 h-12 rounded-xl
                                    bg-<?= $card[3] ?>-50 text-<?= $card[3] ?>-600
                                    flex items-center justify-center">

                                    <i data-lucide="<?= e($card[2]) ?>"
                                       class="w-6 h-6"></i>

                                </div>

                                <div>
                                    <p class="text-sm text-slate-500">
                                        <?= e($card[0]) ?>
                                    </p>

                                    <h4 class="text-2xl font-extrabold">
                                        <?= (int)$stats[$card[1]] ?>
                                    </h4>
                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </div>

            </section>

            <!-- RECENT REQUESTS -->
            <section class="bg-white border border-slate-200 rounded-2xl overflow-hidden">

                <div class="p-5 md:p-6 border-b border-slate-200
                            flex flex-col sm:flex-row sm:items-center
                            justify-between gap-3">

                    <div>
                        <h3 class="font-bold text-lg">My Document Requests</h3>
                        <p class="text-sm text-slate-500">
                            Monitor your submitted barangay documents.
                        </p>
                    </div>

                    <a href="track.php"
                       class="text-blue-600 text-sm font-semibold hover:underline">
                        Track Request →
                    </a>

                </div>

                <div class="overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-50 text-slate-500 uppercase text-xs">

                            <tr>
                                <th class="px-6 py-4">Tracking Number</th>
                                <th class="px-6 py-4">Document</th>
                                <th class="px-6 py-4">Date Submitted</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Action</th>
                            </tr>

                        </thead>

                        <tbody class="divide-y divide-slate-100">

                            <?php if (empty($requests)): ?>

                                <tr>
                                    <td colspan="5"
                                        class="px-6 py-12 text-center">

                                        <div class="flex flex-col items-center">

                                            <i data-lucide="file-x"
                                               class="w-10 h-10 text-slate-300 mb-3"></i>

                                            <p class="font-semibold text-slate-600">
                                                No document requests yet.
                                            </p>

                                            <p class="text-xs text-slate-400 mt-1">
                                                Your submitted requests will appear here.
                                            </p>

                                        </div>

                                    </td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($requests as $request): ?>

                                    <?php
                                    $status = $request["status"] ?? "Pending";
                                    $statusClass = $statusColors[$status]
                                        ?? "bg-slate-100 text-slate-600";
                                    ?>

                                    <tr class="hover:bg-slate-50">

                                        <td class="px-6 py-4 font-semibold text-blue-700 whitespace-nowrap">
                                            <?= e($request["tracking_code"]) ?>
                                        </td>

                                        <td class="px-6 py-4 font-medium whitespace-nowrap">
                                            <?= e($request["request_type"]) ?>
                                        </td>

                                        <td class="px-6 py-4 text-slate-500 whitespace-nowrap">
                                            <?= e($request["submitted_at"]) ?>
                                        </td>

                                        <td class="px-6 py-4 whitespace-nowrap">

                                            <span class="px-3 py-1 rounded-full
                                                text-xs font-bold <?= e($statusClass) ?>">
                                                <?= e($status) ?>
                                            </span>

                                        </td>

                                        <td class="px-6 py-4">

                                            <a href="track.php?tracking_code=<?= urlencode($request["tracking_code"]) ?>"
                                               class="inline-flex items-center gap-1
                                               text-blue-600 font-semibold hover:underline">

                                                View
                                                <i data-lucide="arrow-up-right"
                                                   class="w-4 h-4"></i>

                                            </a>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <footer class="text-center text-xs text-slate-400 py-8">
                © <?= date("Y") ?> Barangay Guindacpan. Resident Tracking Portal.
            </footer>

        </div>

    </main>

</div>

<script>
    lucide.createIcons();

    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("overlay");

        sidebar.classList.toggle("-translate-x-full");
        overlay.classList.toggle("hidden");
    }
</script>

</body>
</html>
