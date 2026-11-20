
<?php
// =============================================
// BHW PATIENT MANAGEMENT
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
// ADD PATIENT
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_patient"])) {

    $full_name = trim($_POST["full_name"] ?? "");
    $birth_date = trim((string)($_POST["birth_date"] ?? "")) ?: null;
    $ageInput = trim((string)($_POST["age"] ?? ""));
    $age = $ageInput !== "" ? (int)$ageInput : null;
    $gender = $_POST["gender"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $blood_type = trim($_POST["blood_type"] ?? "");
    $emergency_contact = trim($_POST["emergency_contact"] ?? "");
    $emergency_number = trim($_POST["emergency_number"] ?? "");

    $allowed_gender = ["Male", "Female", "Other"];

    if ($full_name === "") {
        $error = "Patient name is required.";
    } elseif (!in_array($gender, $allowed_gender, true)) {
        $error = "Please select a valid gender.";
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO bhw_patients
            (
                full_name,
                birth_date,
                age,
                gender,
                address,
                contact_number,
                blood_type,
                emergency_contact,
                emergency_number
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $full_name,
            $birth_date,
            $age,
            $gender,
            $address ?: null,
            $contact_number ?: null,
            $blood_type ?: null,
            $emergency_contact ?: null,
            $emergency_number ?: null
        ]);

        header("Location: patients.php?success=added");
        exit;
    }
}

// =============================================
// UPDATE PATIENT
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_patient"])) {

    $id = (int)($_POST["id"] ?? 0);
    $full_name = trim($_POST["full_name"] ?? "");
    $birth_date = trim((string)($_POST["birth_date"] ?? "")) ?: null;
    $ageInput = trim((string)($_POST["age"] ?? ""));
    $age = $ageInput !== "" ? (int)$ageInput : null;
    $gender = $_POST["gender"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $blood_type = trim($_POST["blood_type"] ?? "");
    $emergency_contact = trim($_POST["emergency_contact"] ?? "");
    $emergency_number = trim($_POST["emergency_number"] ?? "");

    $allowed_gender = ["Male", "Female", "Other"];

    if ($id <= 0 || $full_name === "") {
        $error = "Please provide a valid patient name.";
    } elseif (!in_array($gender, $allowed_gender, true)) {
        $error = "Please select a valid gender.";
    } else {

        $stmt = $pdo->prepare("
            UPDATE bhw_patients
            SET full_name = ?,
                birth_date = ?,
                age = ?,
                gender = ?,
                address = ?,
                contact_number = ?,
                blood_type = ?,
                emergency_contact = ?,
                emergency_number = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $full_name,
            $birth_date,
            $age,
            $gender,
            $address ?: null,
            $contact_number ?: null,
            $blood_type ?: null,
            $emergency_contact ?: null,
            $emergency_number ?: null,
            $id
        ]);

        header("Location: patients.php?success=updated");
        exit;
    }
}

// =============================================
// DELETE PATIENT
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_patient"])) {

    $id = (int)($_POST["id"] ?? 0);

    // Prevent deletion if patient has related records
    $related = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM bhw_appointments WHERE patient_id = ?) +
            (SELECT COUNT(*) FROM bhw_health_records WHERE patient_id = ?) +
            (SELECT COUNT(*) FROM bhw_immunization WHERE patient_id = ?) +
            (SELECT COUNT(*) FROM bhw_prenatal WHERE patient_id = ?)
    ");

    $related->execute([$id, $id, $id, $id]);
    $count = (int)$related->fetchColumn();

    if ($count > 0) {
        $error = "This patient has existing health records. Deletion is blocked to protect medical history.";
    } else {
        $stmt = $pdo->prepare("
            DELETE FROM bhw_patients WHERE id = ?
        ");
        $stmt->execute([$id]);

        header("Location: patients.php?success=deleted");
        exit;
    }
}

// =============================================
// SUCCESS MESSAGE
// =============================================

if (isset($_GET["success"])) {
    $messages = [
        "added" => "Patient successfully registered!",
        "updated" => "Patient information successfully updated!",
        "deleted" => "Patient successfully deleted!"
    ];

    $message = $messages[$_GET["success"]] ?? "";
}

// =============================================
// EDIT PATIENT
// =============================================

$editPatient = null;

if (isset($_GET["edit"])) {
    $edit_id = (int)$_GET["edit"];

    if ($edit_id > 0) {
        $stmt = $pdo->prepare("
        SELECT * FROM bhw_patients WHERE id = ?
    ");

        $stmt->execute([$edit_id]);
        $editPatient = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// =============================================
// SEARCH PATIENTS
// =============================================

$search = trim($_GET["search"] ?? "");

$sql = "SELECT * FROM bhw_patients WHERE 1=1";
$params = [];

if ($search !== "") {
    $sql .= "
        AND (
            full_name LIKE ?
            OR contact_number LIKE ?
            OR address LIKE ?
        )
    ";

    $params = [
        "%$search%",
        "%$search%",
        "%$search%"
    ];
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================================
// STATISTICS
// =============================================

$totalPatients = (int)$pdo->query("
    SELECT COUNT(*) FROM bhw_patients
")->fetchColumn();

$malePatients = (int)$pdo->query("
    SELECT COUNT(*) FROM bhw_patients WHERE gender = 'Male'
")->fetchColumn();

$femalePatients = (int)$pdo->query("
    SELECT COUNT(*) FROM bhw_patients WHERE gender = 'Female'
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patients | BHW Management</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-slate-100 text-slate-800">

<div class="min-h-screen">

    <!-- HEADER -->
    <header class="bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-6 py-5 flex flex-col sm:flex-row justify-between gap-4">

            <div>
                <h1 class="text-2xl font-bold">
                    Patient Management
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
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Total Patients</p>
                <h2 class="text-3xl font-bold mt-2">
                    <?= $totalPatients ?>
                </h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Male Patients</p>
                <h2 class="text-3xl font-bold text-blue-600 mt-2">
                    <?= $malePatients ?>
                </h2>
            </div>

            <div class="bg-white p-6 rounded-2xl shadow-sm">
                <p class="text-sm text-slate-500">Female Patients</p>
                <h2 class="text-3xl font-bold text-pink-600 mt-2">
                    <?= $femalePatients ?>
                </h2>
            </div>

        </div>

        <!-- PATIENT FORM -->
        <section class="bg-white p-6 rounded-2xl shadow-sm mb-8">

            <h2 class="text-xl font-bold mb-6">
                <?= $editPatient ? "Edit Patient Information" : "Register New Patient" ?>
            </h2>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

                <?php if ($editPatient): ?>
                    <input type="hidden" name="id"
                           value="<?= (int)$editPatient["id"] ?>">
                <?php endif; ?>

                <!-- FULL NAME -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">
                        Full Name *
                    </label>

                    <input type="text"
                           name="full_name"
                           required
                           value="<?= e($editPatient["full_name"] ?? "") ?>"
                           placeholder="Enter patient's full name"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- BIRTH DATE -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Birth Date
                    </label>

                    <input type="date"
                           name="birth_date"
                           value="<?= e($editPatient["birth_date"] ?? "") ?>"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- AGE -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Age
                    </label>

                    <input type="number"
                           name="age"
                           min="0"
                           max="150"
                           value="<?= e($editPatient["age"] ?? "") ?>"
                           placeholder="Enter age"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- GENDER -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Gender *
                    </label>

                    <select name="gender" required
                            class="w-full border border-slate-300 rounded-xl px-4 py-3">

                        <option value="">Select Gender</option>

                        <?php foreach (["Male", "Female", "Other"] as $gender): ?>
                            <option value="<?= e($gender) ?>"
                                <?= ($editPatient["gender"] ?? "") === $gender ? "selected" : "" ?>>
                                <?= e($gender) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- CONTACT -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Contact Number
                    </label>

                    <input type="text"
                           name="contact_number"
                           value="<?= e($editPatient["contact_number"] ?? "") ?>"
                           placeholder="09XXXXXXXXX"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- ADDRESS -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-2">
                        Address
                    </label>

                    <textarea name="address"
                              rows="2"
                              placeholder="Enter complete address"
                              class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editPatient["address"] ?? "") ?></textarea>
                </div>

                <!-- BLOOD TYPE -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Blood Type
                    </label>

                    <select name="blood_type"
                            class="w-full border border-slate-300 rounded-xl px-4 py-3">

                        <option value="">Select Blood Type</option>

                        <?php foreach (["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"] as $blood): ?>
                            <option value="<?= e($blood) ?>"
                                <?= ($editPatient["blood_type"] ?? "") === $blood ? "selected" : "" ?>>
                                <?= e($blood) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>
                </div>

                <!-- EMERGENCY CONTACT -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Emergency Contact Name
                    </label>

                    <input type="text"
                           name="emergency_contact"
                           value="<?= e($editPatient["emergency_contact"] ?? "") ?>"
                           placeholder="Emergency contact person"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- EMERGENCY NUMBER -->
                <div>
                    <label class="block text-sm font-semibold mb-2">
                        Emergency Contact Number
                    </label>

                    <input type="text"
                           name="emergency_number"
                           value="<?= e($editPatient["emergency_number"] ?? "") ?>"
                           placeholder="09XXXXXXXXX"
                           class="w-full border border-slate-300 rounded-xl px-4 py-3">
                </div>

                <!-- BUTTONS -->
                <div class="md:col-span-2 flex flex-wrap gap-3">

                    <button type="submit"
                            name="<?= $editPatient ? "update_patient" : "add_patient" ?>"
                            class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-semibold">

                        <?= $editPatient ? "Update Patient" : "Register Patient" ?>

                    </button>

                    <?php if ($editPatient): ?>
                        <a href="patients.php"
                           class="bg-slate-200 hover:bg-slate-300 px-6 py-3 rounded-xl font-semibold">
                            Cancel
                        </a>
                    <?php endif; ?>

                </div>

            </form>

        </section>

        <!-- PATIENT LIST -->
        <section class="bg-white p-6 rounded-2xl shadow-sm">

            <div class="flex flex-col md:flex-row justify-between gap-4 mb-6">

                <div>
                    <h2 class="text-xl font-bold">
                        Registered Patients
                    </h2>

                    <p class="text-sm text-slate-500 mt-1">
                        Patient information and medical details
                    </p>
                </div>

                <form method="GET" class="flex gap-2">

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
                            <th class="p-4">Patient Name</th>
                            <th class="p-4">Age</th>
                            <th class="p-4">Gender</th>
                            <th class="p-4">Contact</th>
                            <th class="p-4">Address</th>
                            <th class="p-4">Blood Type</th>
                            <th class="p-4">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">

                    <?php if ($patients): ?>

                        <?php foreach ($patients as $patient): ?>

                            <tr class="hover:bg-slate-50">

                                <td class="p-4 font-semibold">
                                    <?= e($patient["full_name"]) ?>
                                </td>

                                <td class="p-4">
                                    <?= e($patient["age"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($patient["gender"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($patient["contact_number"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($patient["address"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <?= e($patient["blood_type"] ?? "N/A") ?>
                                </td>

                                <td class="p-4">
                                    <div class="flex gap-3 items-center">

                                        <a href="patients.php?edit=<?= (int)$patient["id"] ?>"
                                           class="text-blue-600 font-semibold hover:underline">
                                            Edit
                                        </a>

                                        <form method="POST"
                                              onsubmit="return confirm('Are you sure you want to delete this patient?');">

                                            <input type="hidden"
                                                   name="id"
                                                   value="<?= (int)$patient["id"] ?>">

                                            <button type="submit"
                                                    name="delete_patient"
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
                                No patient records found.
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
