
<?php
// =============================================
// BHW IMMUNIZATION MANAGEMENT
// =============================================

session_name("BARANGAY_BHW_SESSION");
session_start();

if (!isset($_SESSION["bhw_id"])) {
    header("Location: login.php");
    exit;
}

require_once "../db.php";

$bhw_id = (int) $_SESSION["bhw_id"];
$message = "";
$error = "";

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// =============================================
// ADD IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_immunization"])) {

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
            SELECT id FROM bhw_patients WHERE id = ?
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

// =============================================
// UPDATE IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_immunization"])) {

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

        // Confirm that the selected patient exists before updating.
        $check = $pdo->prepare("
            SELECT id FROM bhw_patients WHERE id = ?
        ");
        $check->execute([$patient_id]);

        if (!$check->fetch()) {
            $error = "Selected patient does not exist.";
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

// =============================================
// DELETE IMMUNIZATION
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_immunization"])) {

    $id = (int)($_POST["id"] ?? 0);

    $stmt = $pdo->prepare("
        DELETE FROM bhw_immunization
        WHERE id = ? AND bhw_id = ?
    ");

    $stmt->execute([$id, $bhw_id]);

    header("Location: immunization.php?success=deleted");
    exit;
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
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200 px-6 py-5">

        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold">
                    Immunization Management
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
                    Total Immunization Records
                </p>

                <h2 class="text-3xl font-bold mt-2">
                    <?= $totalRecords ?>
                </h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">
                    Upcoming Schedules
                </p>

                <h2 class="text-3xl font-bold text-blue-600 mt-2">
                    <?= $totalUpcoming ?>
                </h2>
            </div>

        </div>

        <!-- ADD / EDIT FORM -->

        <section class="bg-white p-6 rounded-2xl shadow-sm mb-8">

            <h2 class="text-xl font-bold mb-6">
                <?= $editRecord ? "Update Immunization Record" : "Add Immunization Record" ?>
            </h2>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <?php if ($editRecord): ?>
                    <input type="hidden" name="id"
                           value="<?= (int)$editRecord["id"] ?>">
                <?php endif; ?>

                <!-- PATIENT -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Patient Name *
                    </label>

                    <select name="patient_id" required
                            class="w-full border border-slate-300 rounded-xl px-4 py-3">

                        <option value="">Select Patient</option>

                        <?php foreach ($patients as $patient): ?>

                            <option value="<?= (int)$patient["id"] ?>"
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

                    <input type="text"
                           name="vaccine_name"
                           required
                           value="<?= e($editRecord["vaccine_name"] ?? "") ?>"
                           placeholder="Enter vaccine name"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- DOSE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Dose Number
                    </label>

                    <input type="text"
                           name="dose_number"
                           value="<?= e($editRecord["dose_number"] ?? "") ?>"
                           placeholder="Example: 1st Dose"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- VACCINATION DATE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Vaccination Date
                    </label>

                    <input type="date"
                           name="vaccination_date"
                           value="<?= e($editRecord["vaccination_date"] ?? "") ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- NEXT SCHEDULE -->

                <div>

                    <label class="block text-sm font-semibold mb-2">
                        Next Schedule
                    </label>

                    <input type="date"
                           name="next_schedule"
                           value="<?= e($editRecord["next_schedule"] ?? "") ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">

                </div>

                <!-- NOTES -->

                <div class="md:col-span-2">

                    <label class="block text-sm font-semibold mb-2">
                        Notes
                    </label>

                    <textarea name="notes"
                              rows="3"
                              placeholder="Enter immunization notes..."
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editRecord["notes"] ?? "") ?></textarea>

                </div>

                <!-- BUTTONS -->

                <div class="md:col-span-2 flex flex-wrap gap-3">

                    <button type="submit"
                            name="<?= $editRecord ? "update_immunization" : "add_immunization" ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold">

                        <?= $editRecord ? "Update Record" : "Save Immunization" ?>

                    </button>

                    <?php if ($editRecord): ?>

                        <a href="immunization.php"
                           class="bg-slate-200 hover:bg-slate-300 px-6 py-3 rounded-xl font-semibold">

                            Cancel

                        </a>

                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- IMMUNIZATION RECORDS -->

        <section class="bg-white p-6 rounded-2xl shadow-sm">

            <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">

                <div>
                    <h2 class="text-xl font-bold">
                        Vaccination Records
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        View and manage patient immunization history.
                    </p>
                </div>

                <form method="GET" class="flex gap-2">

                    <input type="text"
                           name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search patient or vaccine..."
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

                                <td class="p-4">
                                    <?= e($record["notes"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">

                                    <div class="flex gap-3">

                                        <a href="immunization.php?edit=<?= (int)$record["id"] ?>"
                                           class="text-blue-600 font-semibold hover:underline">

                                            Edit

                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('Delete this immunization record?');">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$record["id"] ?>">

                                            <button type="submit"
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
                            <td colspan="7"
                                class="p-8 text-center text-slate-500">

                                No immunization records found.

                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
<script src="bhw.js"></script>
</html>
