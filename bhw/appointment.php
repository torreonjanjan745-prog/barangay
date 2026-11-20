
<?php
// ============================================
// BHW APPOINTMENT MANAGEMENT
// ============================================

session_name("BARANGAY_BHW_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_start();

if (empty($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit();
}

require_once "../db.php";

$bhwId = (int) $_SESSION["bhw_id"];
$message = "";
$messageType = "";

// ============================================
// UPDATE APPOINTMENT STATUS
// ============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $appointmentId = (int)($_POST["appointment_id"] ?? 0);
    $status = $_POST["status"] ?? "";

    $allowedStatuses = [
        "Approved",
        "Completed",
        "Cancelled"
    ];

    if (
        $appointmentId > 0 &&
        in_array($status, $allowedStatuses, true)
    ) {

        $stmt = $conn->prepare(
            "UPDATE bhw_appointments
             SET status = ?
             WHERE id = ?
             AND bhw_id = ?
             AND status IN ('Pending', 'Approved')"
        );

        if ($stmt) {

            $stmt->bind_param(
                "sii",
                $status,
                $appointmentId,
                $bhwId
            );

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $message = "Appointment updated successfully!";
                $messageType = "success";
            } else {
                $message = "Appointment not found or cannot be updated.";
                $messageType = "error";
            }

            $stmt->close();

        } else {
            $message = "Database error. Please try again.";
            $messageType = "error";
        }

    } else {
        $message = "Invalid appointment information.";
        $messageType = "error";
    }
}

// ============================================
// DASHBOARD STATISTICS
// ============================================

$statsQuery = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Completed') AS completed
     FROM bhw_appointments
     WHERE bhw_id = ?"
);

$statsQuery->bind_param("i", $bhwId);
$statsQuery->execute();

$stats = $statsQuery->get_result()->fetch_assoc();

$total = (int)($stats["total"] ?? 0);
$pending = (int)($stats["pending"] ?? 0);
$approved = (int)($stats["approved"] ?? 0);
$completed = (int)($stats["completed"] ?? 0);

$statsQuery->close();

// ============================================
// SEARCH AND FILTER
// ============================================

$search = trim($_GET["search"] ?? "");
$filter = $_GET["status"] ?? "";

$validFilters = [
    "Pending",
    "Approved",
    "Completed",
    "Cancelled"
];

$sql = "SELECT
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
        WHERE a.bhw_id = ?";

$types = "i";
$params = [$bhwId];

if ($search !== "") {

    $sql .= " AND (
        p.full_name LIKE ?
        OR a.purpose LIKE ?
    )";

    $keyword = "%" . $search . "%";

    $types .= "ss";
    $params[] = $keyword;
    $params[] = $keyword;
}

if (in_array($filter, $validFilters, true)) {

    $sql .= " AND a.status = ?";

    $types .= "s";
    $params[] = $filter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $conn->prepare($sql);

$bindValues = [$types];
foreach ($params as $key => $value) {
    $bindValues[] = &$params[$key];
}
call_user_func_array([$stmt, "bind_param"], $bindValues);
$stmt->execute();

$appointments = $stmt->get_result();

$bhwName = $_SESSION["bhw_name"] ?? "Health Worker";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BHW Appointments | Barangay Health System</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
rel="stylesheet">

<style>
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

@media(max-width:768px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.show {
        transform: translateX(0);
    }
}
</style>

</head>

<body>

<div class="min-h-screen flex">

<!-- =====================================
     SIDEBAR
===================================== -->

<aside id="sidebar"
class="sidebar fixed md:sticky top-0 left-0 z-50
w-72 h-screen bg-slate-950 text-white flex flex-col">

    <div class="px-7 py-7 border-b border-white/10">

        <div class="flex items-center gap-3">

            <div class="w-12 h-12 bg-blue-600 rounded-xl
            flex items-center justify-center">

                <i data-lucide="heart-pulse"
                class="w-7 h-7"></i>

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


    <div class="flex-1 px-4 py-6">

        <p class="text-xs uppercase tracking-widest
        text-slate-500 font-semibold px-4 mb-4">
            Main Menu
        </p>

        <nav class="space-y-2">

            <a href="dashboard.php"
            class="nav-link flex items-center gap-3
            px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="layout-dashboard"
                class="w-5 h-5"></i>

                Dashboard

            </a>

            <a href="patients.php"
            class="nav-link flex items-center gap-3
            px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="users"
                class="w-5 h-5"></i>

                Patient Management

            </a>

            <a href="healthRecords.php"
            class="nav-link flex items-center gap-3
            px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="clipboard-list"
                class="w-5 h-5"></i>

                Health Records

            </a>

            <a href="appointments.php"
            class="nav-link active flex items-center gap-3
            px-4 py-3 rounded-xl text-sm">

                <i data-lucide="calendar-days"
                class="w-5 h-5"></i>

                Appointments

            </a>

            <a href="prenatal.php"
            class="nav-link flex items-center gap-3
            px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="baby"
                class="w-5 h-5"></i>

                Prenatal Monitoring

            </a>

            <a href="immunization.php"
            class="nav-link flex items-center gap-3
            px-4 py-3 rounded-xl text-slate-300 text-sm">

                <i data-lucide="syringe"
                class="w-5 h-5"></i>

                Immunization

            </a>

        </nav>


        <p class="text-xs uppercase tracking-widest
        text-slate-500 font-semibold px-4 mt-9 mb-4">
            Management
        </p>

        <a href="reports.php"
        class="nav-link flex items-center gap-3
        px-4 py-3 rounded-xl text-slate-300 text-sm">

            <i data-lucide="chart-column"
            class="w-5 h-5"></i>

            Health Reports

        </a>

    </div>


    <div class="p-4 border-t border-white/10">

        <a href="logout.php"
        onclick="return confirm('Are you sure you want to logout?')"
        class="flex gap-3 items-center px-4 py-3
        text-red-400 rounded-xl hover:bg-red-500/10 text-sm">

            <i data-lucide="log-out"
            class="w-5 h-5"></i>

            Logout

        </a>

    </div>

</aside>


<div id="overlay"
class="hidden fixed inset-0 bg-black/50 z-40 md:hidden">
</div>


<!-- =====================================
     MAIN CONTENT
===================================== -->

<main class="flex-1 min-w-0">

    <!-- HEADER -->

    <header class="bg-white border-b border-slate-200
    sticky top-0 z-30">

        <div class="px-5 md:px-8 py-4 flex
        items-center justify-between">

            <div class="flex items-center gap-4">

                <button id="menuBtn"
                class="md:hidden p-2 hover:bg-slate-100 rounded-lg">

                    <i data-lucide="menu"
                    class="w-6 h-6"></i>

                </button>

                <div>
                    <h2 class="font-bold text-xl text-slate-800">
                        Appointments
                    </h2>

                    <p class="text-xs text-slate-500">
                        Manage patient consultation schedules
                    </p>
                </div>

            </div>

            <div class="flex items-center gap-3">

                <div class="w-10 h-10 rounded-full bg-blue-100
                text-blue-700 flex items-center justify-center
                font-bold">

                    <?= htmlspecialchars(strtoupper(substr($bhwName, 0, 1))) ?>

                </div>

                <div class="hidden sm:block">

                    <p class="text-sm font-semibold text-slate-800">
                        <?= htmlspecialchars($bhwName) ?>
                    </p>

                    <p class="text-xs text-slate-500">
                        Health Worker
                    </p>

                </div>

            </div>

        </div>

    </header>


    <section class="p-5 md:p-8 space-y-7">


        <!-- TITLE -->

        <div class="flex flex-col sm:flex-row
        sm:items-center sm:justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Appointment Management
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Monitor and manage your patient schedules.
                </p>
            </div>

            <a href="patients.php"
            class="inline-flex items-center justify-center
            gap-2 bg-blue-600 hover:bg-blue-700
            text-white px-5 py-3 rounded-xl
            font-semibold text-sm">

                <i data-lucide="user-plus"
                class="w-4 h-4"></i>

                Register Patient

            </a>

        </div>


        <!-- MESSAGE -->

        <?php if ($message !== ""): ?>

        <div class="<?= $messageType === 'success'
            ? 'bg-green-50 border-green-200 text-green-700'
            : 'bg-red-50 border-red-200 text-red-700' ?>
            border rounded-xl p-4 text-sm">

            <?= htmlspecialchars($message) ?>

        </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <div class="grid grid-cols-1 sm:grid-cols-2
        xl:grid-cols-4 gap-5">

            <div class="stat-card bg-white p-6 rounded-2xl
            border border-slate-200">

                <div class="w-11 h-11 bg-blue-100
                text-blue-600 rounded-xl flex
                items-center justify-center">

                    <i data-lucide="calendar-days"></i>

                </div>

                <p class="text-sm text-slate-500 mt-4">
                    Total Appointments
                </p>

                <h2 class="text-3xl font-bold mt-1 text-slate-800">
                    <?= $total ?>
                </h2>

            </div>


            <div class="stat-card bg-white p-6 rounded-2xl
            border border-slate-200">

                <div class="w-11 h-11 bg-orange-100
                text-orange-600 rounded-xl flex
                items-center justify-center">

                    <i data-lucide="clock"></i>

                </div>

                <p class="text-sm text-slate-500 mt-4">
                    Pending
                </p>

                <h2 class="text-3xl font-bold mt-1 text-slate-800">
                    <?= $pending ?>
                </h2>

            </div>


            <div class="stat-card bg-white p-6 rounded-2xl
            border border-slate-200">

                <div class="w-11 h-11 bg-green-100
                text-green-600 rounded-xl flex
                items-center justify-center">

                    <i data-lucide="calendar-check"></i>

                </div>

                <p class="text-sm text-slate-500 mt-4">
                    Approved
                </p>

                <h2 class="text-3xl font-bold mt-1 text-slate-800">
                    <?= $approved ?>
                </h2>

            </div>


            <div class="stat-card bg-white p-6 rounded-2xl
            border border-slate-200">

                <div class="w-11 h-11 bg-purple-100
                text-purple-600 rounded-xl flex
                items-center justify-center">

                    <i data-lucide="check-circle"></i>

                </div>

                <p class="text-sm text-slate-500 mt-4">
                    Completed
                </p>

                <h2 class="text-3xl font-bold mt-1 text-slate-800">
                    <?= $completed ?>
                </h2>

            </div>

        </div>


        <!-- APPOINTMENT TABLE -->

        <div class="bg-white border border-slate-200
        rounded-2xl overflow-hidden">

            <div class="p-6 border-b border-slate-100">

                <h2 class="font-bold text-slate-800">
                    Appointment Records
                </h2>

                <p class="text-xs text-slate-500 mt-1">
                    All appointment schedules assigned to you
                </p>

            </div>


            <!-- SEARCH AND FILTER -->

            <form method="GET"
            class="p-5 flex flex-col md:flex-row gap-3">

                <div class="relative flex-1">

                    <i data-lucide="search"
                    class="absolute left-4 top-1/2
                    -translate-y-1/2 w-5 h-5 text-slate-400">
                    </i>

                    <input type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search patient or purpose..."
                    class="w-full border border-slate-200
                    rounded-xl pl-12 pr-4 py-3 text-sm
                    focus:outline-none focus:border-blue-500">

                </div>


                <select name="status"
                class="border border-slate-200 rounded-xl
                px-4 py-3 text-sm bg-white">

                    <option value="">All Status</option>

                    <?php foreach ($validFilters as $option): ?>

                    <option value="<?= $option ?>"
                    <?= $filter === $option ? "selected" : "" ?>>

                        <?= $option ?>

                    </option>

                    <?php endforeach; ?>

                </select>


                <button type="submit"
                class="bg-blue-600 text-white px-6 py-3
                rounded-xl font-semibold text-sm
                hover:bg-blue-700">

                    Search

                </button>

            </form>


            <!-- TABLE -->

            <div class="overflow-x-auto">

                <table class="w-full text-left">

                    <thead class="bg-slate-50">

                        <tr class="text-xs uppercase
                        text-slate-500 tracking-wide">

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

                    <?php if ($appointments->num_rows > 0): ?>

                        <?php while ($row = $appointments->fetch_assoc()): ?>

                        <tr class="hover:bg-slate-50">

                            <td class="px-6 py-4">

                                <p class="text-sm font-semibold
                                text-slate-800">

                                    <?= htmlspecialchars($row["full_name"]) ?>

                                </p>

                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">

                                <?= htmlspecialchars($row["contact_number"] ?? "") ?>

                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">

                                <?= date("M d, Y", strtotime($row["appointment_date"])) ?>

                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">

                                <?= date("h:i A", strtotime($row["appointment_time"])) ?>

                            </td>

                            <td class="px-6 py-4 text-sm text-slate-600">

                                <?= htmlspecialchars($row["purpose"] ?? "") ?>

                            </td>

                            <td class="px-6 py-4">

                                <?php
                                $statusColors = [
                                    "Pending" => "bg-orange-100 text-orange-700",
                                    "Approved" => "bg-green-100 text-green-700",
                                    "Completed" => "bg-blue-100 text-blue-700",
                                    "Cancelled" => "bg-red-100 text-red-700"
                                ];

                                $color = $statusColors[$row["status"]]
                                    ?? "bg-slate-100 text-slate-700";
                                ?>

                                <span class="<?= $color ?>
                                px-3 py-1 rounded-full
                                text-xs font-semibold">

                                    <?= htmlspecialchars($row["status"]) ?>

                                </span>

                            </td>

                            <td class="px-6 py-4">

                                <?php if (in_array(
                                    $row["status"],
                                    ["Pending", "Approved"],
                                    true
                                )): ?>

                                <form method="POST"
                                class="flex flex-col gap-2">

                                    <input type="hidden"
                                    name="appointment_id"
                                    value="<?= (int)$row["id"] ?>">

                                    <select name="status"
                                    class="border border-slate-200
                                    rounded-lg px-2 py-2 text-xs">

                                        <?php if ($row["status"] === "Pending"): ?>

                                        <option value="Approved">
                                            Approve
                                        </option>

                                        <?php endif; ?>

                                        <?php if ($row["status"] === "Approved"): ?>

                                        <option value="Completed">
                                            Complete
                                        </option>

                                        <?php endif; ?>

                                        <option value="Cancelled">
                                            Cancel
                                        </option>

                                    </select>

                                    <button type="submit"
                                    onclick="return confirm('Update this appointment?')"
                                    class="bg-blue-600 hover:bg-blue-700
                                    text-white rounded-lg px-3 py-2
                                    text-xs font-semibold">

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

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td colspan="7"
                            class="text-center py-12">

                                <i data-lucide="calendar-x"
                                class="w-12 h-12 text-slate-300 mx-auto mb-3">
                                </i>

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


        <footer class="text-center text-xs
        text-slate-400 py-5">

            Barangay Health Management System
            <br>
            © 2026 All Rights Reserved.

        </footer>

    </section>

</main>

</div>


<!-- =====================================
     JAVASCRIPT
===================================== -->


</body>

<script src="bhw.js"></script>
</html>

<?php
$stmt->close();
?>
