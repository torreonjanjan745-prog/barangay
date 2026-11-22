
<?php
// =============================================
// BHW IMMUNIZATION MANAGEMENT
// =============================================

$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

session_name("BARANGAY_BHW_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}

if (!isset($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// BHW DATABASE CONNECTION
require_once __DIR__ . "/database.php";

$bhw_id = (int) $_SESSION["bhw_id"];
$bhw_name = $_SESSION["bhw_name"] ?? "Health Worker";

$message = "";
$error = "";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["immunization_csrf"])) {
    $_SESSION["immunization_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["immunization_csrf"];

// =============================================
// ADD IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_immunization"])) {

    if (!hash_equals($csrfToken, $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $patient_id = (int)($_POST["patient_id"] ?? 0);
        $vaccine_name = trim($_POST["vaccine_name"] ?? "");
        $dose_number = trim($_POST["dose_number"] ?? "");
        $vaccination_date = !empty($_POST["vaccination_date"]) ? $_POST["vaccination_date"] : null;
        $next_schedule = !empty($_POST["next_schedule"]) ? $_POST["next_schedule"] : null;
        $notes = trim($_POST["notes"] ?? "");

        if ($patient_id <= 0 || $vaccine_name === "") {
            $error = "Please select a patient and enter a vaccine name.";
        } else {

            $check = $pdo->prepare("
                SELECT id
                FROM bhw_patients
                WHERE id = ?
            ");

            $check->execute([$patient_id]);

            if (!$check->fetch()) {
                $error = "Selected patient does not exist.";
            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO bhw_immunization
                    (
                        patient_id,
                        bhw_id,
                        vaccine_name,
                        dose_number,
                        vaccination_date,
                        next_schedule,
                        notes
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $patient_id,
                    $bhw_id,
                    $vaccine_name,
                    $dose_number ?: null,
                    $vaccination_date,
                    $next_schedule,
                    $notes ?: null
                ]);

                header("Location: immunization.php?success=added");
                exit;
            }
        }
    }
}

// =============================================
// UPDATE IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_immunization"])) {

    if (!hash_equals($csrfToken, $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $id = (int)($_POST["id"] ?? 0);
        $patient_id = (int)($_POST["patient_id"] ?? 0);
        $vaccine_name = trim($_POST["vaccine_name"] ?? "");
        $dose_number = trim($_POST["dose_number"] ?? "");
        $vaccination_date = !empty($_POST["vaccination_date"]) ? $_POST["vaccination_date"] : null;
        $next_schedule = !empty($_POST["next_schedule"]) ? $_POST["next_schedule"] : null;
        $notes = trim($_POST["notes"] ?? "");

        if ($id <= 0 || $patient_id <= 0 || $vaccine_name === "") {
            $error = "Please complete the required fields.";
        } else {

            // Verify patient belongs to this BHW.
            $check = $pdo->prepare("
                SELECT id
                FROM bhw_patients
                WHERE id = ? AND bhw_id = ?
            ");

            $check->execute([$patient_id, $bhw_id]);

            if (!$check->fetch()) {
                $error = "Selected patient does not exist or is not assigned to your account.";
            } else {

                $stmt = $pdo->prepare("
                    UPDATE bhw_immunization
                    SET patient_id = ?,
                        vaccine_name = ?,
                        dose_number = ?,
                        vaccination_date = ?,
                        next_schedule = ?,
                        notes = ?
                    WHERE id = ? AND bhw_id = ?
                ");

                $stmt->execute([
                    $patient_id,
                    $vaccine_name,
                    $dose_number ?: null,
                    $vaccination_date,
                    $next_schedule,
                    $notes ?: null,
                    $id,
                    $bhw_id
                ]);

                header("Location: immunization.php?success=updated");
                exit;
            }
        }
    }
}

// =============================================
// DELETE IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_immunization"])) {

    if (!hash_equals($csrfToken, $_POST["csrf_token"] ?? "")) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        $id = (int)($_POST["id"] ?? 0);

        if ($id > 0) {
            $stmt = $pdo->prepare("
                DELETE FROM bhw_immunization
                WHERE id = ? AND bhw_id = ?
            ");

            $stmt->execute([$id, $bhw_id]);
        }

        header("Location: immunization.php?success=deleted");
        exit;
    }
}

// =============================================
// SUCCESS MESSAGES
// =============================================

$successMessages = [
    "added" => "Immunization record successfully added!",
    "updated" => "Immunization record successfully updated!",
    "deleted" => "Immunization record successfully deleted!"
];

if (isset($_GET["success"])) {
    $message = $successMessages[$_GET["success"]] ?? "";
}

// =============================================
// PATIENT LIST
// =============================================

$patients = $pdo->query("
    SELECT id, full_name
    FROM bhw_patients
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// EDIT RECORD
// =============================================

$editRecord = null;

if (isset($_GET["edit"])) {

    $edit_id = (int)$_GET["edit"];

    $stmt = $pdo->prepare("
        SELECT i.*, p.full_name
        FROM bhw_immunization i
        INNER JOIN bhw_patients p ON i.patient_id = p.id
        WHERE i.id = ? AND i.bhw_id = ?
    ");

    $stmt->execute([$edit_id, $bhw_id]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

// =============================================
// SEARCH IMMUNIZATION RECORDS
// =============================================

$search = trim($_GET["search"] ?? "");

$sql = "
    SELECT
        i.*,
        p.full_name,
        p.age,
        p.gender
    FROM bhw_immunization i
    INNER JOIN bhw_patients p ON i.patient_id = p.id
    WHERE i.bhw_id = ?
";

$params = [$bhw_id];

if ($search !== "") {
    $sql .= "
        AND (
            p.full_name LIKE ?
            OR i.vaccine_name LIKE ?
        )
    ";

    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY i.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// STATISTICS
// =============================================

$totalRecords = count($records);
$totalUpcoming = 0;

foreach ($records as $record) {
    if (
        !empty($record["next_schedule"]) &&
        $record["next_schedule"] >= date("Y-m-d")
    ) {
        $totalUpcoming++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Immunization Management | BHW Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
        }

        #immunizationSidebar {
            transition: transform 0.3s ease-in-out;
        }

        #immunizationOverlay {
            transition: opacity 0.3s ease-in-out;
        }

        @media (max-width: 767px) {
            #immunizationSidebar {
                transform: translateX(-100%);
            }

            #immunizationSidebar.show {
                transform: translateX(0);
            }
        }

        @media (min-width: 768px) {
            #immunizationSidebar {
                transform: translateX(0) !important;
            }
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen flex">

    <!-- MOBILE OVERLAY -->
    <div
        id="immunizationOverlay"
        class="hidden fixed inset-0 bg-black/50 z-40 md:hidden">
    </div>

    <!-- SIDEBAR -->
    <aside
        id="immunizationSidebar"
        class="fixed md:sticky top-0 left-0 z-50 w-72 h-screen bg-slate-900 text-white flex flex-col shrink-0">

        <div class="p-6 border-b border-white/10">

            <div class="flex items-center justify-between">

                <div>
                    <h2 class="text-xl font-bold">
                        BHW Portal
                    </h2>

                    <p class="text-xs text-slate-400 mt-1">
                        Barangay Health Worker
                    </p>
                </div>

                <button
                    type="button"
                    id="closeImmunizationMenu"
                    class="md:hidden p-2 rounded-lg hover:bg-white/10"
                    aria-label="Close navigation menu">

                    <i data-lucide="x"></i>

                </button>

            </div>

        </div>

        <div class="p-5 border-b border-white/10">

            <p class="text-xs text-slate-400">
                LOGGED IN AS
            </p>

            <p class="font-semibold mt-1">
                <?= e($bhw_name) ?>
            </p>

        </div>

        <!-- NAVIGATION -->
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">

            <a href="dashboard.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                <span>Dashboard</span>

            </a>

            <a href="patients.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="users" class="w-5 h-5"></i>
                <span>Patient Management</span>

            </a>

            <a href="health_records.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="file-heart" class="w-5 h-5"></i>
                <span>Health Records</span>

            </a>

            <a href="appointments.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="calendar-check" class="w-5 h-5"></i>
                <span>Appointments</span>

            </a>

            <a href="prenatal.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="baby" class="w-5 h-5"></i>
                <span>Prenatal Monitoring</span>

            </a>

            <a href="immunization.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl bg-blue-600 text-white font-semibold">

                <i data-lucide="syringe" class="w-5 h-5"></i>
                <span>Immunization</span>

            </a>

            <a href="health_reports.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 text-slate-300">

                <i data-lucide="chart-column" class="w-5 h-5"></i>
                <span>Health Reports</span>

            </a>

        </nav>

        <!-- LOGOUT -->
        <div class="p-4 border-t border-white/10">

            <a href="logout.php"
               onclick="return confirm('Are you sure you want to logout?');"
               class="flex items-center gap-3 px-4 py-3 rounded-xl text-red-300 hover:bg-red-500/10">

                <i data-lucide="log-out" class="w-5 h-5"></i>
                <span>Logout</span>

            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <div class="flex-1 min-w-0">

        <!-- HEADER -->
        <header class="bg-white border-b border-slate-200 px-4 sm:px-6 py-4 sticky top-0 z-30">

            <div class="flex items-center justify-between gap-4">

                <div class="flex items-center gap-3">

                    <!-- HAMBURGER -->
                    <button
                        type="button"
                        id="immunizationMenuBtn"
                        class="md:hidden p-2 rounded-xl border border-slate-200 hover:bg-slate-100"
                        aria-label="Open navigation menu"
                        aria-expanded="false">

                        <i data-lucide="menu"></i>

                    </button>

                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold">
                            Immunization Management
                        </h1>

                        <p class="text-xs sm:text-sm text-slate-500 mt-1">
                            Barangay Health Worker Portal
                        </p>
                    </div>

                </div>

                <a href="dashboard.php"
                   class="hidden sm:inline-flex bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-xl text-sm font-semibold">

                    <span>Back to Dashboard</span>

                </a>

            </div>

        </header>

        <main class="max-w-7xl mx-auto p-4 sm:p-6">

            <!-- NOTIFICATIONS -->

            <?php if ($message): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-5">
                    <?= e($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-5">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- STATISTICS -->

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-8">

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">

                    <div class="flex justify-between items-center">

                        <div>
                            <p class="text-sm text-slate-500">
                                Total Immunization Records
                            </p>

                            <h2 class="text-3xl font-bold mt-2">
                                <?= $totalRecords ?>
                            </h2>
                        </div>

                        <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">
                            <i data-lucide="syringe" class="w-7 h-7"></i>
                        </div>

                    </div>

                </div>

                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">

                    <div class="flex justify-between items-center">

                        <div>
                            <p class="text-sm text-slate-500">
                                Upcoming Schedules
                            </p>

                            <h2 class="text-3xl font-bold text-blue-600 mt-2">
                                <?= $totalUpcoming ?>
                            </h2>
                        </div>

                        <div class="bg-indigo-100 text-indigo-600 p-3 rounded-xl">
                            <i data-lucide="calendar-clock" class="w-7 h-7"></i>
                        </div>

                    </div>

                </div>

            </div>

            <!-- ADD / EDIT FORM -->

            <section class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">

                <h2 class="text-xl font-bold mb-6">
                    <?= $editRecord ? "Update Immunization Record" : "Add Immunization Record" ?>
                </h2>

                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <?php if ($editRecord): ?>
                        <input type="hidden" name="id" value="<?= (int)$editRecord["id"] ?>">
                    <?php endif; ?>

                    <!-- PATIENT -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-semibold mb-2">
                            Patient Name *
                        </label>

                        <select
                            name="patient_id"
                            required
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                            <option value="">Select Patient</option>

                            <?php foreach ($patients as $patient): ?>

                                <option
                                    value="<?= (int)$patient["id"] ?>"
                                    <?= (int)($editRecord["patient_id"] ?? 0) === (int)$patient["id"] ? "selected" : "" ?>>

                                    <?= e($patient["full_name"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- VACCINE NAME -->

                    <div>

                        <label class="block text-sm font-semibold mb-2">
                            Vaccine Name *
                        </label>

                        <input
                            type="text"
                            name="vaccine_name"
                            required
                            value="<?= e($editRecord["vaccine_name"] ?? "") ?>"
                            placeholder="Enter vaccine name"
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                    </div>

                    <!-- DOSE -->

                    <div>

                        <label class="block text-sm font-semibold mb-2">
                            Dose Number
                        </label>

                        <input
                            type="text"
                            name="dose_number"
                            value="<?= e($editRecord["dose_number"] ?? "") ?>"
                            placeholder="Example: 1st Dose"
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                    </div>

                    <!-- VACCINATION DATE -->

                    <div>

                        <label class="block text-sm font-semibold mb-2">
                            Vaccination Date
                        </label>

                        <input
                            type="date"
                            name="vaccination_date"
                            value="<?= e($editRecord["vaccination_date"] ?? "") ?>"
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                    </div>

                    <!-- NEXT SCHEDULE -->

                    <div>

                        <label class="block text-sm font-semibold mb-2">
                            Next Schedule
                        </label>

                        <input
                            type="date"
                            name="next_schedule"
                            value="<?= e($editRecord["next_schedule"] ?? "") ?>"
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                    </div>

                    <!-- NOTES -->

                    <div class="md:col-span-2">

                        <label class="block text-sm font-semibold mb-2">
                            Notes
                        </label>

                        <textarea
                            name="notes"
                            rows="3"
                            placeholder="Enter immunization notes..."
                            class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none"><?= e($editRecord["notes"] ?? "") ?></textarea>

                    </div>

                    <!-- BUTTONS -->

                    <div class="md:col-span-2 flex flex-wrap gap-3">

                        <button
                            type="submit"
                            name="<?= $editRecord ? "update_immunization" : "add_immunization" ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold">

                            <?= $editRecord ? "Update Record" : "Save Immunization" ?>

                        </button>

                        <?php if ($editRecord): ?>

                            <a
                                href="immunization.php"
                                class="bg-slate-200 hover:bg-slate-300 px-6 py-3 rounded-xl font-semibold">

                                Cancel

                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </section>

            <!-- IMMUNIZATION RECORDS -->

            <section class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-200">

                <div class="flex flex-col lg:flex-row justify-between gap-4 mb-6">

                    <div>
                        <h2 class="text-xl font-bold">
                            Vaccination Records
                        </h2>

                        <p class="text-sm text-slate-500 mt-1">
                            View and manage patient immunization history.
                        </p>
                    </div>

                    <form method="GET" class="flex flex-col sm:flex-row gap-2">

                        <input
                            type="text"
                            name="search"
                            value="<?= e($search) ?>"
                            placeholder="Search patient or vaccine..."
                            class="w-full sm:w-72 border border-slate-300 rounded-xl px-4 py-2 outline-none focus:ring-2 focus:ring-blue-500">

                        <button
                            type="submit"
                            class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-2 rounded-xl">

                            Search

                        </button>

                    </form>

                </div>

                <!-- MOBILE RECORD CARDS -->

                <div class="md:hidden space-y-4">

                    <?php if ($records): ?>

                        <?php foreach ($records as $record): ?>

                            <div class="border border-slate-200 rounded-xl p-4">

                                <div class="flex justify-between items-start gap-3 mb-3">

                                    <div>
                                        <h3 class="font-bold text-slate-800">
                                            <?= e($record["full_name"]) ?>
                                        </h3>

                                        <p class="text-sm text-blue-600 font-semibold mt-1">
                                            <?= e($record["vaccine_name"]) ?>
                                        </p>
                                    </div>

                                    <span class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded-full">
                                        <?= e($record["dose_number"] ?? "N/A") ?>
                                    </span>

                                </div>

                                <div class="space-y-2 text-sm">

                                    <p>
                                        <span class="text-slate-500">Vaccination Date:</span>
                                        <?= e($record["vaccination_date"] ?? "N/A") ?>
                                    </p>

                                    <p>
                                        <span class="text-slate-500">Next Schedule:</span>
                                        <?= e($record["next_schedule"] ?? "N/A") ?>
                                    </p>

                                    <p class="break-words">
                                        <span class="text-slate-500">Notes:</span>
                                        <?= e($record["notes"] ?? "N/A") ?>
                                    </p>

                                </div>

                                <div class="flex gap-4 border-t border-slate-100 mt-4 pt-3">

                                    <a
                                        href="immunization.php?edit=<?= (int)$record["id"] ?>"
                                        class="text-blue-600 font-semibold">

                                        Edit

                                    </a>

                                    <form method="POST"
                                          onsubmit="return confirm('Delete this immunization record?');">

                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$record["id"] ?>">

                                        <button
                                            type="submit"
                                            name="delete_immunization"
                                            class="text-red-600 font-semibold">

                                            Delete

                                        </button>

                                    </form>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="text-center text-slate-500 py-8">
                            No immunization records found.
                        </div>

                    <?php endif; ?>

                </div>

                <!-- DESKTOP TABLE -->

                <div class="hidden md:block overflow-x-auto">

                    <table class="w-full text-sm text-left">

                        <thead class="bg-slate-50 text-slate-600">

                            <tr>
                                <th class="p-4">Patient</th>
                                <th class="p-4">Vaccine</th>
                                <th class="p-4">Dose</th>
                                <th class="p-4">Vaccination Date</th>
                                <th class="p-4">Next Schedule</th>
                                <th class="p-4">Notes</th>
                                <th class="p-4">Action</th>
                            </tr>

                        </thead>

                        <tbody class="divide-y divide-slate-100">

                        <?php if ($records): ?>

                            <?php foreach ($records as $record): ?>

                                <tr class="hover:bg-slate-50">

                                    <td class="p-4 font-semibold">
                                        <?= e($record["full_name"]) ?>
                                    </td>

                                    <td class="p-4">
                                        <?= e($record["vaccine_name"]) ?>
                                    </td>

                                    <td class="p-4">
                                        <?= e($record["dose_number"] ?? "N/A") ?>
                                    </td>

                                    <td class="p-4">
                                        <?= e($record["vaccination_date"] ?? "N/A") ?>
                                    </td>

                                    <td class="p-4">
                                        <?= e($record["next_schedule"] ?? "N/A") ?>
                                    </td>

                                    <td class="p-4 max-w-xs break-words">
                                        <?= e($record["notes"] ?? "N/A") ?>
                                    </td>

                                    <td class="p-4">

                                        <div class="flex gap-3">

                                            <a
                                                href="immunization.php?edit=<?= (int)$record["id"] ?>"
                                                class="text-blue-600 font-semibold hover:underline">

                                                Edit

                                            </a>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Delete this immunization record?');">

                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int)$record["id"] ?>">

                                                <button
                                                    type="submit"
                                                    name="delete_immunization"
                                                    class="text-red-600 font-semibold hover:underline">

                                                    Delete

                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="7" class="p-8 text-center text-slate-500">
                                    No immunization records found.
                                </td>
                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

            <div class="mt-6 sm:hidden">
                <a href="dashboard.php"
                   class="block text-center bg-slate-800 hover:bg-slate-900 text-white px-5 py-3 rounded-xl font-semibold">

                    Back to Dashboard

                </a>
            </div>

        </main>

    </div>

</div>

<!-- SIDEBAR SCRIPT -->
<script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebar = document.getElementById("immunizationSidebar");
    const menuBtn = document.getElementById("immunizationMenuBtn");
    const closeBtn = document.getElementById("closeImmunizationMenu");
    const overlay = document.getElementById("immunizationOverlay");

    function openMenu() {
        sidebar.classList.add("show");
        overlay.classList.remove("hidden");
        menuBtn.setAttribute("aria-expanded", "true");
        document.body.style.overflow = "hidden";
    }

    function closeMenu() {
        sidebar.classList.remove("show");
        overlay.classList.add("hidden");
        menuBtn.setAttribute("aria-expanded", "false");
        document.body.style.overflow = "";
    }

    menuBtn.addEventListener("click", function () {
        if (sidebar.classList.contains("show")) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    closeBtn.addEventListener("click", closeMenu);
    overlay.addEventListener("click", closeMenu);

    sidebar.querySelectorAll("nav a").forEach(function (link) {
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

    if (window.lucide) {
        lucide.createIcons();
    }

});
</script>

<script src="bhw.js"></script>

</body>
</html>
