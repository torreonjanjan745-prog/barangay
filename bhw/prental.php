
<?php
// =============================================
// BHW PRENATAL MANAGEMENT
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

// =============================================
// ESCAPE OUTPUT
// =============================================

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// =============================================
// ADD PRENATAL RECORD
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_prenatal"])) {

    $patient_id = (int)($_POST["patient_id"] ?? 0);
    $pregnancy_start = ($_POST["pregnancy_start"] ?? "") ?: null;
    $expected_delivery = ($_POST["expected_delivery"] ?? "") ?: null;
    $last_checkup = ($_POST["last_checkup"] ?? "") ?: null;
    $blood_pressure = trim($_POST["blood_pressure"] ?? "");
    $weight = ($_POST["weight"] ?? "") !== "" ? (float)$_POST["weight"] : null;
    $notes = trim($_POST["notes"] ?? "");

    if ($patient_id <= 0) {
        $error = "Please select a patient.";
    } else {
        // Verify patient exists
        $check = $pdo->prepare(
            "SELECT id FROM bhw_patients WHERE id = ?"
        );
        $check->execute([$patient_id]);

        if (!$check->fetch()) {
            $error = "Selected patient does not exist.";
        } else {
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
                $blood_pressure ?: null,
                $weight,
                $notes ?: null
            ]);

            header("Location: prenatal.php?success=added");
            exit;
        }
    }
}

// =============================================
// UPDATE PRENATAL RECORD
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_prenatal"])) {

    $id = (int)($_POST["id"] ?? 0);
    $pregnancy_start = ($_POST["pregnancy_start"] ?? "") ?: null;
    $expected_delivery = ($_POST["expected_delivery"] ?? "") ?: null;
    $last_checkup = ($_POST["last_checkup"] ?? "") ?: null;
    $blood_pressure = trim($_POST["blood_pressure"] ?? "");
    $weight = ($_POST["weight"] ?? "") !== "" ? (float)$_POST["weight"] : null;
    $notes = trim($_POST["notes"] ?? "");
    $status = $_POST["status"] ?? "Active";

    $allowed_status = ["Active", "Completed", "Referred"];

    if ($id <= 0) {
        $error = "Invalid prenatal record.";
    } elseif (!in_array($status, $allowed_status, true)) {
        $error = "Invalid prenatal status.";
    } else {
        $check = $pdo->prepare("SELECT id FROM bhw_prenatal WHERE id = ? AND bhw_id = ?");
        $check->execute([$id, $bhw_id]);

        if (!$check->fetch()) {
            $error = "Prenatal record not found.";
        } else {
        $stmt = $pdo->prepare("
            UPDATE bhw_prenatal
            SET pregnancy_start = ?,
                expected_delivery = ?,
                last_checkup = ?,
                blood_pressure = ?,
                weight = ?,
                notes = ?,
                status = ?
            WHERE id = ? AND bhw_id = ?
        ");

        $stmt->execute([
            $pregnancy_start,
            $expected_delivery,
            $last_checkup,
            $blood_pressure ?: null,
            $weight,
            $notes ?: null,
            $status,
            $id,
            $bhw_id
        ]);

        header("Location: prenatal.php?success=updated");
        exit;
        }
    }
}

// =============================================
// FETCH SUCCESS MESSAGE
// =============================================

if (isset($_GET["success"])) {
    if ($_GET["success"] === "added") {
        $message = "Prenatal record successfully added!";
    } elseif ($_GET["success"] === "updated") {
        $message = "Prenatal record successfully updated!";
    }
}

// =============================================
// FETCH PATIENTS
// =============================================

$patients = $pdo->query("
    SELECT id, full_name
    FROM bhw_patients
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// SEARCH PRENATAL RECORDS
// =============================================

$search = trim($_GET["search"] ?? "");

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

// =============================================
// FETCH RECORD FOR EDITING
// =============================================

$editRecord = null;

if (isset($_GET["edit"])) {
    $edit_id = (int)$_GET["edit"];

    $stmt = $pdo->prepare("
        SELECT pr.*, p.full_name
        FROM bhw_prenatal pr
        INNER JOIN bhw_patients p ON pr.patient_id = p.id
        WHERE pr.id = ? AND pr.bhw_id = ?
    ");

    $stmt->execute([$edit_id, $bhw_id]);
    $editRecord = $stmt->fetch(PDO::FETCH_ASSOC);
}

$total = count($records);
$active = 0;
$completed = 0;
$referred = 0;

foreach ($records as $record) {
    if ($record["status"] === "Active") $active++;
    if ($record["status"] === "Completed") $completed++;
    if ($record["status"] === "Referred") $referred++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prenatal Management | BHW Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200 px-6 py-5">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Prenatal Management
                </h1>
                <p class="text-sm text-slate-500">
                    Barangay Health Worker Portal
                </p>
            </div>

            <a href="dashboard.php"
               class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold">
                Back to Dashboard
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-6">

        <!-- NOTIFICATIONS -->
        <?php if ($message): ?>
            <div class="mb-5 p-4 rounded-xl bg-green-100 text-green-700">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-5 p-4 rounded-xl bg-red-100 text-red-700">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <!-- STATISTICS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Total Records</p>
                <h2 class="text-3xl font-bold mt-2"><?= $total ?></h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Active Pregnancies</p>
                <h2 class="text-3xl font-bold text-blue-600 mt-2"><?= $active ?></h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Completed</p>
                <h2 class="text-3xl font-bold text-green-600 mt-2"><?= $completed ?></h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Referred</p>
                <h2 class="text-3xl font-bold text-orange-600 mt-2"><?= $referred ?></h2>
            </div>

        </div>

        <!-- ADD / EDIT FORM -->
        <section class="bg-white rounded-2xl shadow-sm p-6 mb-8">

            <h2 class="text-xl font-bold mb-6">
                <?= $editRecord ? "Update Prenatal Record" : "Add Prenatal Record" ?>
            </h2>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <?php if ($editRecord): ?>
                    <input type="hidden" name="id" value="<?= (int)$editRecord["id"] ?>">
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">
                        Patient
                    </label>

                    <?php if ($editRecord): ?>
                        <input type="text"
                               value="<?= e($editRecord["full_name"]) ?>"
                               disabled
                               class="w-full border rounded-xl px-4 py-3 bg-slate-100">
                    <?php else: ?>
                        <select name="patient_id" required
                                class="w-full border rounded-xl px-4 py-3">
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?= (int)$patient["id"] ?>">
                                    <?= e($patient["full_name"]) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Pregnancy Start
                    </label>
                    <input type="date" name="pregnancy_start"
                           value="<?= e($editRecord["pregnancy_start"] ?? "") ?>"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Expected Delivery
                    </label>
                    <input type="date" name="expected_delivery"
                           value="<?= e($editRecord["expected_delivery"] ?? "") ?>"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Last Checkup
                    </label>
                    <input type="date" name="last_checkup"
                           value="<?= e($editRecord["last_checkup"] ?? "") ?>"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Blood Pressure
                    </label>
                    <input type="text" name="blood_pressure"
                           placeholder="Example: 120/80"
                           value="<?= e($editRecord["blood_pressure"] ?? "") ?>"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Weight (kg)
                    </label>
                    <input type="number" step="0.01" min="0" name="weight"
                           value="<?= e($editRecord["weight"] ?? "") ?>"
                           class="w-full border rounded-xl px-4 py-3">
                </div>

                <?php if ($editRecord): ?>
                    <div>
                        <label class="block text-sm font-semibold mb-2">
                            Status
                        </label>
                        <select name="status"
                                class="w-full border rounded-xl px-4 py-3">
                            <?php foreach (["Active", "Completed", "Referred"] as $status): ?>
                                <option value="<?= e($status) ?>"
                                    <?= $editRecord["status"] === $status ? "selected" : "" ?>>
                                    <?= e($status) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">
                        Notes
                    </label>
                    <textarea name="notes" rows="3"
                              class="w-full border rounded-xl px-4 py-3"
                              placeholder="Enter prenatal notes..."><?= e($editRecord["notes"] ?? "") ?></textarea>
                </div>

                <div class="md:col-span-2 flex gap-3">
                    <button type="submit"
                            name="<?= $editRecord ? "update_prenatal" : "add_prenatal" ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold">
                        <?= $editRecord ? "Update Record" : "Save Prenatal Record" ?>
                    </button>

                    <?php if ($editRecord): ?>
                        <a href="prenatal.php"
                           class="bg-slate-200 hover:bg-slate-300 px-6 py-3 rounded-xl font-semibold">
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </section>

        <!-- SEARCH -->
        <section class="bg-white rounded-2xl shadow-sm p-6">

            <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">
                <div>
                    <h2 class="text-xl font-bold">Prenatal Records</h2>
                    <p class="text-sm text-slate-500">
                        Manage registered prenatal patients.
                    </p>
                </div>

                <form method="GET" class="flex gap-2">
                    <input type="text" name="search"
                           value="<?= e($search) ?>"
                           placeholder="Search patient..."
                           class="border rounded-xl px-4 py-2">

                    <button type="submit"
                            class="bg-slate-800 text-white px-5 py-2 rounded-xl">
                        Search
                    </button>
                </form>
            </div>

            <!-- TABLE -->
            <div class="overflow-x-auto">
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

                    <tbody class="divide-y">

                    <?php if (count($records) > 0): ?>

                        <?php foreach ($records as $record): ?>

                            <tr class="hover:bg-slate-50">

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
                                    <?= $record["weight"] !== null ? "kg" : "" ?>
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
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                        <?= e($record["status"]) ?>
                                    </span>
                                </td>

                                <td class="p-4">
                                    <a href="prenatal.php?edit=<?= (int)$record["id"] ?>"
                                       class="text-blue-600 hover:underline font-semibold">
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
</div>

</body>
<script src="bhw.js"></script>
</html>
