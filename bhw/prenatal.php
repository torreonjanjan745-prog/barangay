
<?php
// =============================================
// BHW PRENATAL MANAGEMENT
// Database: barangay_health_db
// Connection: database.php (PDO)
// =============================================

// SESSION CONFIGURATION
if (session_status() === PHP_SESSION_NONE) {
    session_name("BARANGAY_BHW_SESSION");
    session_start();
}

// CHECK BHW LOGIN
if (!isset($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . "/database.php";

// VERIFY PDO CONNECTION
if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("Prenatal Management: PDO connection is missing.");
    http_response_code(500);
    exit("Database connection is not available.");
}

$bhw_id = (int) $_SESSION["bhw_id"];

$message = "";
$error = "";

$patients = [];
$records = [];
$editRecord = null;

$total = 0;
$active = 0;
$completed = 0;
$referred = 0;

// =============================================
// ESCAPE OUTPUT
// =============================================

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// =============================================
// VALIDATE DATE
// =============================================

function validDateOrNull($value) {
    if ($value === "") {
        return null;
    }

    $date = DateTime::createFromFormat("Y-m-d", $value);

    if (!$date || $date->format("Y-m-d") !== $value) {
        return false;
    }

    return $value;
}

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["prenatal_csrf_token"])) {
    $_SESSION["prenatal_csrf_token"] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION["prenatal_csrf_token"];

// =============================================
// HANDLE POST REQUESTS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submitted_token) ||
        !hash_equals($csrf_token, $submitted_token)
    ) {
        $error = "Invalid or expired request. Please refresh the page.";
    }

    // =========================================
    // ADD PRENATAL RECORD
    // =========================================

    elseif (isset($_POST["add_prenatal"])) {

        $patient_id = (int) ($_POST["patient_id"] ?? 0);

        $pregnancy_start = validDateOrNull(
            trim($_POST["pregnancy_start"] ?? "")
        );

        $expected_delivery = validDateOrNull(
            trim($_POST["expected_delivery"] ?? "")
        );

        $last_checkup = validDateOrNull(
            trim($_POST["last_checkup"] ?? "")
        );

        $blood_pressure = trim(
            $_POST["blood_pressure"] ?? ""
        );

        $weight_input = trim(
            $_POST["weight"] ?? ""
        );

        $notes = trim(
            $_POST["notes"] ?? ""
        );

        $weight = null;

        if ($weight_input !== "") {
            if (
                !is_numeric($weight_input) ||
                (float) $weight_input <= 0
            ) {
                $error = "Please enter a valid weight.";
            } else {
                $weight = (float) $weight_input;
            }
        }

        if ($patient_id <= 0) {
            $error = "Please select a patient.";
        }

        if (
            $pregnancy_start === false ||
            $expected_delivery === false ||
            $last_checkup === false
        ) {
            $error = "Please enter valid dates.";
        }

        if ($error === "") {

            try {

                // VERIFY PATIENT
                $check = $pdo->prepare("
                    SELECT id
                    FROM bhw_patients
                    WHERE id = ?
                    LIMIT 1
                ");

                $check->execute([$patient_id]);

                if (!$check->fetch()) {

                    $error = "Selected patient does not exist.";

                } else {

                    // INSERT PRENATAL RECORD
                    $stmt = $pdo->prepare("
                        INSERT INTO bhw_prenatal
                        (
                            patient_id,
                            bhw_id,
                            pregnancy_start,
                            expected_delivery,
                            last_checkup,
                            blood_pressure,
                            weight,
                            notes,
                            status
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active')
                    ");

                    $stmt->execute([
                        $patient_id,
                        $bhw_id,
                        $pregnancy_start,
                        $expected_delivery,
                        $last_checkup,
                        $blood_pressure !== "" ? $blood_pressure : null,
                        $weight,
                        $notes !== "" ? $notes : null
                    ]);

                    header("Location: prenatal.php?success=added");
                    exit;
                }

            } catch (PDOException $e) {

                error_log(
                    "Prenatal Add Error: " . $e->getMessage()
                );

                $error = "Unable to save prenatal record. Please check your database structure.";
            }
        }
    }

    // =========================================
    // UPDATE PRENATAL RECORD
    // =========================================

    elseif (isset($_POST["update_prenatal"])) {

        $id = (int) ($_POST["id"] ?? 0);

        $pregnancy_start = validDateOrNull(
            trim($_POST["pregnancy_start"] ?? "")
        );

        $expected_delivery = validDateOrNull(
            trim($_POST["expected_delivery"] ?? "")
        );

        $last_checkup = validDateOrNull(
            trim($_POST["last_checkup"] ?? "")
        );

        $blood_pressure = trim(
            $_POST["blood_pressure"] ?? ""
        );

        $weight_input = trim(
            $_POST["weight"] ?? ""
        );

        $notes = trim(
            $_POST["notes"] ?? ""
        );

        $status = $_POST["status"] ?? "Active";

        $allowed_status = [
            "Active",
            "Completed",
            "Referred"
        ];

        $weight = null;

        if ($weight_input !== "") {

            if (
                !is_numeric($weight_input) ||
                (float) $weight_input <= 0
            ) {
                $error = "Please enter a valid weight.";
            } else {
                $weight = (float) $weight_input;
            }
        }

        if ($id <= 0) {
            $error = "Invalid prenatal record.";
        }

        if (!in_array($status, $allowed_status, true)) {
            $error = "Invalid prenatal status.";
        }

        if (
            $pregnancy_start === false ||
            $expected_delivery === false ||
            $last_checkup === false
        ) {
            $error = "Please enter valid dates.";
        }

        if ($error === "") {

            try {

                // VERIFY RECORD OWNERSHIP
                $check = $pdo->prepare("
                    SELECT id
                    FROM bhw_prenatal
                    WHERE id = ?
                    AND bhw_id = ?
                    LIMIT 1
                ");

                $check->execute([
                    $id,
                    $bhw_id
                ]);

                if (!$check->fetch()) {

                    $error = "Prenatal record not found.";

                } else {

                    // UPDATE RECORD
                    $stmt = $pdo->prepare("
                        UPDATE bhw_prenatal
                        SET
                            pregnancy_start = ?,
                            expected_delivery = ?,
                            last_checkup = ?,
                            blood_pressure = ?,
                            weight = ?,
                            notes = ?,
                            status = ?
                        WHERE id = ?
                        AND bhw_id = ?
                    ");

                    $stmt->execute([
                        $pregnancy_start,
                        $expected_delivery,
                        $last_checkup,
                        $blood_pressure !== "" ? $blood_pressure : null,
                        $weight,
                        $notes !== "" ? $notes : null,
                        $status,
                        $id,
                        $bhw_id
                    ]);

                    header("Location: prenatal.php?success=updated");
                    exit;
                }

            } catch (PDOException $e) {

                error_log(
                    "Prenatal Update Error: " . $e->getMessage()
                );

                $error = "Unable to update prenatal record. Please check your database structure.";
            }
        }
    }
}

// =============================================
// SUCCESS MESSAGE
// =============================================

if (isset($_GET["success"])) {

    if ($_GET["success"] === "added") {
        $message = "Prenatal record successfully added!";
    }

    elseif ($_GET["success"] === "updated") {
        $message = "Prenatal record successfully updated!";
    }
}

// =============================================
// FETCH PATIENTS AND PRENATAL RECORDS
// =============================================

$search = trim($_GET["search"] ?? "");

try {

    // FETCH PATIENTS
    $stmt = $pdo->query("
        SELECT id, full_name
        FROM bhw_patients
        ORDER BY full_name ASC
    ");

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // FETCH PRENATAL RECORDS
    $sql = "
        SELECT
            pr.*,
            p.full_name,
            p.age,
            p.contact_number,
            p.address
        FROM bhw_prenatal pr
        INNER JOIN bhw_patients p
            ON pr.patient_id = p.id
        WHERE pr.bhw_id = ?
    ";

    $params = [$bhw_id];

    if ($search !== "") {

        $sql .= " AND p.full_name LIKE ?";

        $params[] = "%" . $search . "%";
    }

    $sql .= " ORDER BY pr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // FETCH RECORD FOR EDITING
    if (isset($_GET["edit"])) {

        $edit_id = (int) $_GET["edit"];

        if ($edit_id > 0) {

            $stmt = $pdo->prepare("
                SELECT
                    pr.*,
                    p.full_name
                FROM bhw_prenatal pr
                INNER JOIN bhw_patients p
                    ON pr.patient_id = p.id
                WHERE pr.id = ?
                AND pr.bhw_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $edit_id,
                $bhw_id
            ]);

            $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // =========================================
    // STATISTICS
    // =========================================

    $total = count($records);

    foreach ($records as $record) {

        if ($record["status"] === "Active") {
            $active++;
        }

        elseif ($record["status"] === "Completed") {
            $completed++;
        }

        elseif ($record["status"] === "Referred") {
            $referred++;
        }
    }

} catch (PDOException $e) {

    error_log(
        "Prenatal Fetch Error: " . $e->getMessage()
    );

    $error = "Unable to load prenatal records. Please check your database tables and columns.";
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prenatal Management | BHW Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        * {
            -webkit-tap-highlight-color: transparent;
        }

        body {
            overflow-x: hidden;
        }

        input, select, textarea, button {
            font-size: 16px;
        }

        .mobile-card {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .mobile-card:active {
            transform: scale(.99);
        }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 min-h-screen">

<div class="min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-800">
                        Prenatal Management
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-500 mt-1">
                        Barangay Health Worker Portal
                    </p>
                </div>

                <a href="dashboard.php"
                   class="inline-flex items-center justify-center gap-2
                          w-full sm:w-auto
                          bg-blue-600 hover:bg-blue-700
                          text-white px-5 py-3
                          rounded-xl font-semibold text-sm
                          transition">

                    <svg xmlns="http://www.w3.org/2000/svg"
                         width="18" height="18"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="m15 18-6-6 6-6"/>
                        <path d="M9 12h12"/>

                    </svg>

                    Back to Dashboard

                </a>

            </div>

        </div>

    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-8">

        <!-- NOTIFICATIONS -->

        <?php if ($message): ?>

            <div class="mb-5 p-4 rounded-xl bg-green-100 border border-green-200 text-green-700 text-sm">
                <?= e($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($error): ?>

            <div class="mb-5 p-4 rounded-xl bg-red-100 border border-red-200 text-red-700 text-sm">
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <!-- STATISTICS -->

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-5 mb-7">

            <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Total Records
                    </p>

                    <span class="hidden sm:flex w-10 h-10 bg-slate-100 rounded-xl items-center justify-center text-slate-600">
                        <svg width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                        </svg>
                    </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold mt-2">
                    <?= (int)$total ?>
                </h2>
            </div>


            <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Active
                    </p>

                    <span class="hidden sm:flex w-10 h-10 bg-blue-50 rounded-xl items-center justify-center text-blue-600">
                        <svg width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z"/>
                        </svg>
                    </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold text-blue-600 mt-2">
                    <?= (int)$active ?>
                </h2>
            </div>


            <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Completed
                    </p>

                    <span class="hidden sm:flex w-10 h-10 bg-green-50 rounded-xl items-center justify-center text-green-600">
                        <svg width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m5 12 4 4L19 6"/>
                        </svg>
                    </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold text-green-600 mt-2">
                    <?= (int)$completed ?>
                </h2>
            </div>


            <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-sm border border-slate-100">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs sm:text-sm text-slate-500 font-medium">
                        Referred
                    </p>

                    <span class="hidden sm:flex w-10 h-10 bg-orange-50 rounded-xl items-center justify-center text-orange-600">
                        <svg width="20" height="20" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 9v4m0 4h.01M10.3 3.9 2.2 18a2 2 0 0 0 1.7 3h16.2a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                        </svg>
                    </span>
                </div>

                <h2 class="text-2xl sm:text-3xl font-bold text-orange-600 mt-2">
                    <?= (int)$referred ?>
                </h2>
            </div>

        </section>


        <!-- ADD / EDIT FORM -->

        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-6 mb-7">

            <div class="mb-6">
                <h2 class="text-lg sm:text-xl font-bold text-slate-800">
                    <?= $editRecord ? "Update Prenatal Record" : "Add Prenatal Record" ?>
                </h2>

                <p class="text-xs sm:text-sm text-slate-500 mt-1">
                    <?= $editRecord
                        ? "Update the patient's prenatal information."
                        : "Register a new prenatal patient record." ?>
                </p>
            </div>

            <form method="POST"
                  action="prenatal.php<?= $editRecord ? '?edit=' . (int)$editRecord['id'] : '' ?>"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($csrf_token) ?>">

                <?php if ($editRecord): ?>
                    <input type="hidden"
                           name="id"
                           value="<?= (int)$editRecord["id"] ?>">
                <?php endif; ?>


                <!-- PATIENT -->

                <div class="sm:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Patient
                    </label>

                    <?php if ($editRecord): ?>

                        <input type="text"
                               value="<?= e($editRecord["full_name"]) ?>"
                               disabled
                               class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-slate-100 text-slate-600">

                    <?php else: ?>

                        <select name="patient_id"
                                required
                                class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">

                            <option value="">Select Patient</option>

                            <?php foreach ($patients as $patient): ?>

                                <option value="<?= (int)$patient["id"] ?>"
                                    <?= (
                                        isset($_POST["patient_id"]) &&
                                        (int)$_POST["patient_id"] === (int)$patient["id"]
                                    ) ? "selected" : "" ?>>

                                    <?= e($patient["full_name"]) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    <?php endif; ?>

                </div>


                <!-- PREGNANCY START -->

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Pregnancy Start
                    </label>

                    <input type="date"
                           name="pregnancy_start"
                           value="<?= e($editRecord["pregnancy_start"] ?? $_POST["pregnancy_start"] ?? "") ?>"
                           class="w-full min-w-0 border border-slate-200 rounded-xl px-3 sm:px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>


                <!-- EXPECTED DELIVERY -->

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Expected Delivery
                    </label>

                    <input type="date"
                           name="expected_delivery"
                           value="<?= e($editRecord["expected_delivery"] ?? $_POST["expected_delivery"] ?? "") ?>"
                           class="w-full min-w-0 border border-slate-200 rounded-xl px-3 sm:px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>


                <!-- LAST CHECKUP -->

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Last Checkup
                    </label>

                    <input type="date"
                           name="last_checkup"
                           value="<?= e($editRecord["last_checkup"] ?? $_POST["last_checkup"] ?? "") ?>"
                           class="w-full min-w-0 border border-slate-200 rounded-xl px-3 sm:px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>


                <!-- BLOOD PRESSURE -->

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Blood Pressure
                    </label>

                    <input type="text"
                           name="blood_pressure"
                           maxlength="30"
                           placeholder="Example: 120/80"
                           value="<?= e($editRecord["blood_pressure"] ?? $_POST["blood_pressure"] ?? "") ?>"
                           class="w-full min-w-0 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>


                <!-- WEIGHT -->

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Weight (kg)
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0.01"
                           name="weight"
                           placeholder="Enter weight"
                           value="<?= e($editRecord["weight"] ?? $_POST["weight"] ?? "") ?>"
                           class="w-full min-w-0 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">
                </div>


                <!-- STATUS -->

                <?php if ($editRecord): ?>

                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Status
                        </label>

                        <select name="status"
                                class="w-full border border-slate-200 rounded-xl px-4 py-3 bg-white focus:ring-2 focus:ring-blue-500 outline-none">

                            <?php foreach (["Active", "Completed", "Referred"] as $status): ?>

                                <option value="<?= e($status) ?>"
                                    <?= ($editRecord["status"] ?? "") === $status ? "selected" : "" ?>>

                                    <?= e($status) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </div>

                <?php endif; ?>


                <!-- NOTES -->

                <div class="sm:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Notes
                    </label>

                    <textarea name="notes"
                              rows="4"
                              placeholder="Enter prenatal notes..."
                              class="w-full border border-slate-200 rounded-xl px-4 py-3 resize-y focus:ring-2 focus:ring-blue-500 outline-none"><?= e($editRecord["notes"] ?? $_POST["notes"] ?? "") ?></textarea>

                </div>


                <!-- ACTION BUTTONS -->

                <div class="sm:col-span-2 flex flex-col sm:flex-row gap-3 pt-2">

                    <button type="submit"
                            name="<?= $editRecord ? "update_prenatal" : "add_prenatal" ?>"
                            value="1"
                            class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white px-6 py-3.5 rounded-xl font-semibold transition">

                        <?= $editRecord ? "Update Record" : "Save Prenatal Record" ?>

                    </button>

                    <?php if ($editRecord): ?>

                        <a href="prenatal.php"
                           class="w-full sm:w-auto text-center bg-slate-100 hover:bg-slate-200 text-slate-700 px-6 py-3.5 rounded-xl font-semibold transition">

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>


        <!-- PRENATAL RECORDS -->

        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-6">

            <!-- SECTION HEADER -->

            <div class="mb-5">

                <h2 class="text-lg sm:text-xl font-bold">
                    Prenatal Records
                </h2>

                <p class="text-xs sm:text-sm text-slate-500 mt-1">
                    Manage registered prenatal patients.
                </p>

            </div>


            <!-- SEARCH -->

            <form method="GET"
                  action="prenatal.php"
                  class="flex flex-col sm:flex-row gap-3 mb-6">

                <input type="text"
                       name="search"
                       value="<?= e($search) ?>"
                       placeholder="Search patient name..."
                       class="w-full min-w-0 border border-slate-200 rounded-xl px-4 py-3 focus:ring-2 focus:ring-blue-500 outline-none">

                <div class="flex gap-2">

                    <button type="submit"
                            class="flex-1 sm:flex-none bg-slate-800 hover:bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold">

                        Search

                    </button>

                    <?php if ($search !== ""): ?>

                        <a href="prenatal.php"
                           class="flex-1 sm:flex-none text-center bg-slate-100 hover:bg-slate-200 px-5 py-3 rounded-xl font-semibold">

                            Clear

                        </a>

                    <?php endif; ?>

                </div>

            </form>


            <!-- MOBILE RECORD CARDS -->

            <div class="grid grid-cols-1 gap-4 md:hidden">

                <?php if (count($records) > 0): ?>

                    <?php foreach ($records as $record): ?>

                        <?php
                        $statusClass = "bg-blue-100 text-blue-700";

                        if ($record["status"] === "Completed") {
                            $statusClass = "bg-green-100 text-green-700";
                        } elseif ($record["status"] === "Referred") {
                            $statusClass = "bg-orange-100 text-orange-700";
                        }
                        ?>

                        <div class="mobile-card border border-slate-200 rounded-2xl p-4 bg-white shadow-sm">

                            <div class="flex items-start justify-between gap-3 mb-4">

                                <div class="min-w-0">

                                    <h3 class="font-bold text-slate-800 text-base break-words">
                                        <?= e($record["full_name"]) ?>
                                    </h3>

                                    <p class="text-xs text-slate-500 mt-1">
                                        Patient ID: #<?= (int)$record["patient_id"] ?>
                                    </p>

                                </div>

                                <span class="shrink-0 px-3 py-1 rounded-full text-xs font-semibold <?= e($statusClass) ?>">
                                    <?= e($record["status"]) ?>
                                </span>

                            </div>


                            <div class="grid grid-cols-2 gap-3 text-sm">

                                <div class="bg-slate-50 rounded-xl p-3">
                                    <p class="text-xs text-slate-500 mb-1">
                                        Age
                                    </p>
                                    <p class="font-semibold">
                                        <?= e($record["age"] ?? "N/A") ?>
                                    </p>
                                </div>

                                <div class="bg-slate-50 rounded-xl p-3">
                                    <p class="text-xs text-slate-500 mb-1">
                                        Weight
                                    </p>
                                    <p class="font-semibold">
                                        <?= e($record["weight"] ?? "N/A") ?>
                                        <?= $record["weight"] !== null ? " kg" : "" ?>
                                    </p>
                                </div>

                                <div class="bg-slate-50 rounded-xl p-3">
                                    <p class="text-xs text-slate-500 mb-1">
                                        Expected Delivery
                                    </p>
                                    <p class="font-semibold break-words">
                                        <?= e($record["expected_delivery"] ?? "N/A") ?>
                                    </p>
                                </div>

                                <div class="bg-slate-50 rounded-xl p-3">
                                    <p class="text-xs text-slate-500 mb-1">
                                        Last Checkup
                                    </p>
                                    <p class="font-semibold break-words">
                                        <?= e($record["last_checkup"] ?? "N/A") ?>
                                    </p>
                                </div>

                                <div class="bg-slate-50 rounded-xl p-3 col-span-2">
                                    <p class="text-xs text-slate-500 mb-1">
                                        Blood Pressure
                                    </p>
                                    <p class="font-semibold">
                                        <?= e($record["blood_pressure"] ?? "N/A") ?>
                                    </p>
                                </div>

                            </div>


                            <div class="mt-4 pt-4 border-t border-slate-100">

                                <a href="prenatal.php?edit=<?= (int)$record["id"] ?>"
                                   class="flex items-center justify-center gap-2 w-full bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold py-3 rounded-xl transition">

                                    <svg width="17" height="17"
                                         viewBox="0 0 24 24"
                                         fill="none"
                                         stroke="currentColor"
                                         stroke-width="2">

                                        <path d="M12 20h9"/>
                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/>

                                    </svg>

                                    Edit Record

                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="text-center py-10 px-4 border border-dashed border-slate-300 rounded-2xl">

                        <p class="font-semibold text-slate-600">
                            No prenatal records found.
                        </p>

                        <p class="text-sm text-slate-400 mt-1">
                            Try another search or add a new record.
                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- DESKTOP TABLE -->

            <div class="hidden md:block overflow-x-auto rounded-xl border border-slate-100">

                <table class="w-full text-sm text-left">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>
                            <th class="p-4">Patient</th>
                            <th class="p-4">Age</th>
                            <th class="p-4">Expected Delivery</th>
                            <th class="p-4">Last Checkup</th>
                            <th class="p-4">Blood Pressure</th>
                            <th class="p-4">Weight</th>
                            <th class="p-4">Status</th>
                            <th class="p-4">Action</th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                    <?php if (count($records) > 0): ?>

                        <?php foreach ($records as $record): ?>

                            <tr class="hover:bg-slate-50 transition">

                                <td class="p-4 font-semibold">
                                    <?= e($record["full_name"]) ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["age"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["expected_delivery"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["last_checkup"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["blood_pressure"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["weight"] ?? "N/A") ?>
                                    <?= $record["weight"] !== null ? " kg" : "" ?>
                                </td>

                                <td class="p-4">

                                    <?php
                                    $statusClass = "bg-blue-100 text-blue-700";

                                    if ($record["status"] === "Completed") {
                                        $statusClass = "bg-green-100 text-green-700";
                                    } elseif ($record["status"] === "Referred") {
                                        $statusClass = "bg-orange-100 text-orange-700";
                                    }
                                    ?>

                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= e($statusClass) ?>">
                                        <?= e($record["status"]) ?>
                                    </span>

                                </td>

                                <td class="p-4">

                                    <a href="prenatal.php?edit=<?= (int)$record["id"] ?>"
                                       class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-semibold">

                                        Edit

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-500">
                                No prenatal records found.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

    <!-- FOOTER -->

    <footer class="max-w-7xl mx-auto px-4 sm:px-6 py-6 text-center">

        <p class="text-xs text-slate-400">
            Barangay Health Worker Portal &copy; <?= date("Y") ?>
        </p>

    </footer>

</div>

<script src="bhw.js"></script>

</body>
</html>
