
<?php
// =============================================
// BHW HEALTH RECORDS MANAGEMENT
// PDO VERSION
// Database: barangay_health_db
// =============================================

// SESSION
session_name("BARANGAY_BHW_SESSION");
session_start();

// PREVENT BROWSER CACHING
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");

// AUTHENTICATION
if (!isset($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . "/database.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection error. Please check database.php.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$bhw_id = (int) $_SESSION["bhw_id"];

$message = "";
$error = "";

// =============================================
// HELPER FUNCTIONS
// =============================================

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function validVisitDate($date) {
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parts = explode("-", $date);

    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}

function nullableNumber($value) {
    if ($value === "" || $value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return false;
    }

    $number = (float) $value;

    if (!is_finite($number) || $number < 0) {
        return false;
    }

    return $number;
}

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["health_records_csrf"])) {
    $_SESSION["health_records_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["health_records_csrf"];

// =============================================
// HANDLE POST REQUESTS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        try {

            // =============================================
            // ADD HEALTH RECORD
            // =============================================

            if (isset($_POST["add_record"])) {

                $patient_id = (int) ($_POST["patient_id"] ?? 0);
                $visit_date = trim($_POST["visit_date"] ?? "");

                $blood_pressure = trim($_POST["blood_pressure"] ?? "");
                $temperature = nullableNumber($_POST["temperature"] ?? "");
                $weight = nullableNumber($_POST["weight"] ?? "");
                $height = nullableNumber($_POST["height"] ?? "");

                $symptoms = trim($_POST["symptoms"] ?? "");
                $diagnosis = trim($_POST["diagnosis"] ?? "");
                $treatment = trim($_POST["treatment"] ?? "");
                $notes = trim($_POST["notes"] ?? "");

                if ($patient_id <= 0 || !validVisitDate($visit_date)) {
                    $error = "Please select a patient and enter a valid visit date.";
                } elseif (
                    $temperature === false ||
                    $weight === false ||
                    $height === false
                ) {
                    $error = "Temperature, weight, and height must be valid non-negative numbers.";
                } else {

                    // CHECK PATIENT
                    $check = $pdo->prepare("
                        SELECT id
                        FROM bhw_patients
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $check->execute([$patient_id]);

                    if (!$check->fetch(PDO::FETCH_ASSOC)) {
                        $error = "Selected patient does not exist.";
                    } else {

                        // INSERT RECORD
                        $stmt = $pdo->prepare("
                            INSERT INTO bhw_health_records
                            (
                                patient_id,
                                bhw_id,
                                visit_date,
                                blood_pressure,
                                temperature,
                                weight,
                                height,
                                symptoms,
                                diagnosis,
                                treatment,
                                notes
                            )
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");

                        $stmt->execute([
                            $patient_id,
                            $bhw_id,
                            $visit_date,
                            $blood_pressure !== "" ? $blood_pressure : null,
                            $temperature,
                            $weight,
                            $height,
                            $symptoms !== "" ? $symptoms : null,
                            $diagnosis !== "" ? $diagnosis : null,
                            $treatment !== "" ? $treatment : null,
                            $notes !== "" ? $notes : null
                        ]);

                        header("Location: health_records.php?success=added");
                        exit;
                    }
                }
            }

            // =============================================
            // UPDATE HEALTH RECORD
            // =============================================

            elseif (isset($_POST["update_record"])) {

                $id = (int) ($_POST["id"] ?? 0);
                $patient_id = (int) ($_POST["patient_id"] ?? 0);
                $visit_date = trim($_POST["visit_date"] ?? "");

                $blood_pressure = trim($_POST["blood_pressure"] ?? "");
                $temperature = nullableNumber($_POST["temperature"] ?? "");
                $weight = nullableNumber($_POST["weight"] ?? "");
                $height = nullableNumber($_POST["height"] ?? "");

                $symptoms = trim($_POST["symptoms"] ?? "");
                $diagnosis = trim($_POST["diagnosis"] ?? "");
                $treatment = trim($_POST["treatment"] ?? "");
                $notes = trim($_POST["notes"] ?? "");

                if (
                    $id <= 0 ||
                    $patient_id <= 0 ||
                    !validVisitDate($visit_date)
                ) {
                    $error = "Please complete the required fields correctly.";
                } elseif (
                    $temperature === false ||
                    $weight === false ||
                    $height === false
                ) {
                    $error = "Temperature, weight, and height must be valid non-negative numbers.";
                } else {

                    // CHECK PATIENT
                    $checkPatient = $pdo->prepare("
                        SELECT id
                        FROM bhw_patients
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $checkPatient->execute([$patient_id]);

                    if (!$checkPatient->fetch(PDO::FETCH_ASSOC)) {
                        $error = "Selected patient does not exist.";
                    } else {

                        // CHECK RECORD OWNERSHIP
                        $checkRecord = $pdo->prepare("
                            SELECT id
                            FROM bhw_health_records
                            WHERE id = ? AND bhw_id = ?
                            LIMIT 1
                        ");

                        $checkRecord->execute([$id, $bhw_id]);

                        if (!$checkRecord->fetch(PDO::FETCH_ASSOC)) {
                            $error = "Health record not found or you do not have permission to update it.";
                        } else {

                            // UPDATE RECORD
                            $stmt = $pdo->prepare("
                                UPDATE bhw_health_records
                                SET
                                    patient_id = ?,
                                    visit_date = ?,
                                    blood_pressure = ?,
                                    temperature = ?,
                                    weight = ?,
                                    height = ?,
                                    symptoms = ?,
                                    diagnosis = ?,
                                    treatment = ?,
                                    notes = ?
                                WHERE id = ? AND bhw_id = ?
                            ");

                            $stmt->execute([
                                $patient_id,
                                $visit_date,
                                $blood_pressure !== "" ? $blood_pressure : null,
                                $temperature,
                                $weight,
                                $height,
                                $symptoms !== "" ? $symptoms : null,
                                $diagnosis !== "" ? $diagnosis : null,
                                $treatment !== "" ? $treatment : null,
                                $notes !== "" ? $notes : null,
                                $id,
                                $bhw_id
                            ]);

                            header("Location: health_records.php?success=updated");
                            exit;
                        }
                    }
                }
            }

            // =============================================
            // DELETE HEALTH RECORD
            // =============================================

            elseif (isset($_POST["delete_record"])) {

                $id = (int) ($_POST["id"] ?? 0);

                if ($id <= 0) {
                    $error = "Invalid health record.";
                } else {

                    $stmt = $pdo->prepare("
                        DELETE FROM bhw_health_records
                        WHERE id = ? AND bhw_id = ?
                    ");

                    $stmt->execute([$id, $bhw_id]);

                    if ($stmt->rowCount() > 0) {
                        header("Location: health_records.php?success=deleted");
                        exit;
                    } else {
                        $error = "Health record not found or already deleted.";
                    }
                }
            }

        } catch (PDOException $e) {
            error_log("BHW Health Records Error: " . $e->getMessage());
            $error = "Unable to process your request. Please check the database.";
        }
    }
}

// =============================================
// SUCCESS MESSAGES
// =============================================

$successMessages = [
    "added" => "Health record successfully saved!",
    "updated" => "Health record successfully updated!",
    "deleted" => "Health record successfully deleted!"
];

if (isset($_GET["success"])) {
    $successKey = $_GET["success"];

    if (isset($successMessages[$successKey])) {
        $message = $successMessages[$successKey];
    }
}

// =============================================
// FETCH PATIENTS
// =============================================

try {

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

        $edit_id = (int) $_GET["edit"];

        if ($edit_id > 0) {

            $stmt = $pdo->prepare("
                SELECT hr.*, p.full_name
                FROM bhw_health_records hr
                INNER JOIN bhw_patients p
                    ON hr.patient_id = p.id
                WHERE hr.id = ? AND hr.bhw_id = ?
                LIMIT 1
            ");

            $stmt->execute([$edit_id, $bhw_id]);

            $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // =============================================
    // SEARCH HEALTH RECORDS
    // =============================================

    $search = trim($_GET["search"] ?? "");

    $sql = "
        SELECT
            hr.*,
            p.full_name,
            p.age,
            p.gender
        FROM bhw_health_records hr
        INNER JOIN bhw_patients p
            ON hr.patient_id = p.id
        WHERE hr.bhw_id = ?
    ";

    $params = [$bhw_id];

    if ($search !== "") {

        $sql .= "
            AND (
                p.full_name LIKE ?
                OR hr.diagnosis LIKE ?
                OR hr.symptoms LIKE ?
            )
        ";

        $keyword = "%" . $search . "%";

        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
    }

    $sql .= " ORDER BY hr.visit_date DESC, hr.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // =============================================
    // STATISTICS
    // =============================================

    $totalRecords = count($records);
    $todayRecords = 0;

    foreach ($records as $record) {
        if ($record["visit_date"] === date("Y-m-d")) {
            $todayRecords++;
        }
    }

} catch (PDOException $e) {

    error_log("BHW Health Records Fetch Error: " . $e->getMessage());

    $patients = $patients ?? [];
    $records = [];
    $editRecord = null;
    $totalRecords = 0;
    $todayRecords = 0;

    if ($error === "") {
        $error = "Unable to load health records. Please check your database tables.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Health Records | BHW Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200 px-6 py-5">

        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold">
                    Health Records Management
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Barangay Health Worker Portal
                </p>
            </div>

            <a href="dashboard.php"
               class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-xl text-center font-semibold">
                Back to Dashboard
            </a>

        </div>

    </header>

    <main class="max-w-7xl mx-auto p-6">

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

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">
                    Total Health Records
                </p>

                <h2 class="text-3xl font-bold mt-2">
                    <?= (int) $totalRecords ?>
                </h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">
                    Today's Visits
                </p>

                <h2 class="text-3xl font-bold text-blue-600 mt-2">
                    <?= (int) $todayRecords ?>
                </h2>
            </div>

        </div>

        <!-- ADD / EDIT FORM -->

        <section class="bg-white p-6 rounded-2xl shadow-sm mb-8">

            <h2 class="text-xl font-bold mb-6">
                <?= $editRecord ? "Update Health Record" : "Add Health Record" ?>
            </h2>

            <form method="POST" action="health_records.php<?= $editRecord ? '?edit=' . (int)$editRecord['id'] : '' ?>"
                  class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($csrfToken) ?>">

                <?php if ($editRecord): ?>
                    <input type="hidden"
                           name="id"
                           value="<?= (int) $editRecord["id"] ?>">
                <?php endif; ?>

                <!-- PATIENT -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Patient Name *
                    </label>

                    <select name="patient_id"
                            required
                            class="w-full border border-slate-300 rounded-xl px-4 py-3">

                        <option value="">Select Patient</option>

                        <?php foreach ($patients as $patient): ?>

                            <option value="<?= (int) $patient["id"] ?>"
                                <?= (int)($editRecord["patient_id"] ?? 0) === (int)$patient["id"] ? "selected" : "" ?>>

                                <?= e($patient["full_name"]) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <!-- VISIT DATE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Visit Date *
                    </label>

                    <input type="date"
                           name="visit_date"
                           required
                           value="<?= e($editRecord["visit_date"] ?? date("Y-m-d")) ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- BLOOD PRESSURE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Blood Pressure
                    </label>

                    <input type="text"
                           name="blood_pressure"
                           placeholder="Example: 120/80"
                           value="<?= e($editRecord["blood_pressure"] ?? "") ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- TEMPERATURE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Temperature (°C)
                    </label>

                    <input type="number"
                           step="0.1"
                           min="0"
                           name="temperature"
                           placeholder="Example: 36.5"
                           value="<?= e($editRecord["temperature"] ?? "") ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- WEIGHT -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Weight (kg)
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           name="weight"
                           value="<?= e($editRecord["weight"] ?? "") ?>"
                           placeholder="Enter weight"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- HEIGHT -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Height (cm)
                    </label>

                    <input type="number"
                           step="0.01"
                           min="0"
                           name="height"
                           value="<?= e($editRecord["height"] ?? "") ?>"
                           placeholder="Enter height"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- SYMPTOMS -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Symptoms
                    </label>

                    <textarea name="symptoms"
                              rows="3"
                              placeholder="Enter patient symptoms..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editRecord["symptoms"] ?? "") ?></textarea>

                </div>

                <!-- DIAGNOSIS -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Diagnosis / Assessment
                    </label>

                    <textarea name="diagnosis"
                              rows="3"
                              placeholder="Enter assessment or diagnosis..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editRecord["diagnosis"] ?? "") ?></textarea>

                </div>

                <!-- TREATMENT -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Treatment / Advice
                    </label>

                    <textarea name="treatment"
                              rows="3"
                              placeholder="Enter treatment or advice..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editRecord["treatment"] ?? "") ?></textarea>

                </div>

                <!-- NOTES -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Additional Notes
                    </label>

                    <textarea name="notes"
                              rows="3"
                              placeholder="Additional health record notes..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editRecord["notes"] ?? "") ?></textarea>

                </div>

                <!-- BUTTONS -->

                <div class="md:col-span-2 flex flex-wrap gap-3">

                    <button type="submit"
                            name="<?= $editRecord ? "update_record" : "add_record" ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold">

                        <?= $editRecord ? "Update Health Record" : "Save Health Record" ?>

                    </button>

                    <?php if ($editRecord): ?>

                        <a href="health_records.php"
                           class="bg-slate-200 hover:bg-slate-300 px-6 py-3 rounded-xl font-semibold">

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- HEALTH RECORDS TABLE -->

        <section class="bg-white p-6 rounded-2xl shadow-sm">

            <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">

                <div>
                    <h2 class="text-xl font-bold">
                        Patient Health History
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        View and manage recorded patient visits.
                    </p>
                </div>

                <form method="GET" action="health_records.php" class="flex gap-2">

                    <input type="text"
                           name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search patient..."
                           class="border border-slate-300 rounded-xl px-4 py-2">

                    <button type="submit"
                            class="bg-slate-800 hover:bg-slate-900 text-white px-5 py-2 rounded-xl">
                        Search
                    </button>

                </form>

            </div>

            <div class="overflow-x-auto">

                <table class="w-full text-sm text-left">

                    <thead class="bg-slate-50 text-slate-600">

                        <tr>
                            <th class="p-4">Patient</th>
                            <th class="p-4">Visit Date</th>
                            <th class="p-4">Blood Pressure</th>
                            <th class="p-4">Temperature</th>
                            <th class="p-4">Weight</th>
                            <th class="p-4">Diagnosis</th>
                            <th class="p-4">Action</th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                    <?php if (!empty($records)): ?>

                        <?php foreach ($records as $record): ?>

                            <tr class="hover:bg-slate-50">

                                <td class="p-4 font-semibold">
                                    <?= e($record["full_name"]) ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["visit_date"]) ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["blood_pressure"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["temperature"] ?? "N/A") ?>
                                    <?= $record["temperature"] !== null ? "°C" : "" ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["weight"] ?? "N/A") ?>
                                    <?= $record["weight"] !== null ? "kg" : "" ?>
                                </td>

                                <td class="p-4">
                                    <?= e($record["diagnosis"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">

                                    <div class="flex gap-3">

                                        <a href="health_records.php?edit=<?= (int)$record["id"] ?>"
                                           class="text-blue-600 font-semibold hover:underline">

                                            Edit

                                        </a>

                                        <form method="POST"
                                              action="health_records.php"
                                              onsubmit="return confirm('Are you sure you want to delete this health record?');">

                                            <input type="hidden"
                                                   name="csrf_token"
                                                   value="<?= e($csrfToken) ?>">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$record["id"] ?>">

                                            <button type="submit"
                                                    name="delete_record"
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
                            <td colspan="7"
                                class="p-8 text-center text-slate-500">

                                No health records found.

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

<script src="bhw.js"></script>
</body>
</html>
