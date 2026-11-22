
<?php
// =============================================
// BHW PATIENT MANAGEMENT - PDO
// =============================================

$isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

session_name("BARANGAY_BHW_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => $isHttps,
    "httponly" => true,
    "samesite" => "Strict"
]);

session_start();

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
    error_log("BHW Patients: Invalid PDO database connection.");
    http_response_code(500);
    exit("Database connection error. Please check database.php.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$bhwName = $_SESSION["bhw_name"] ?? "Barangay Health Worker";

$message = "";
$error = "";
$editPatient = null;

// CSRF
if (empty($_SESSION["bhw_patients_csrf"])) {
    $_SESSION["bhw_patients_csrf"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["bhw_patients_csrf"];

// ESCAPE OUTPUT
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// FLASH MESSAGES
$successMessages = [
    "added" => "Patient successfully registered!",
    "updated" => "Patient information successfully updated!",
    "deleted" => "Patient successfully deleted!"
];

if (isset($_GET["success"])) {
    $message = $successMessages[$_GET["success"]] ?? "";
}

// =============================================
// VALIDATE PATIENT INPUT
// =============================================

function validatePatientInput() {

    $fullName = trim($_POST["full_name"] ?? "");
    $birthDate = trim($_POST["birth_date"] ?? "");
    $ageInput = trim($_POST["age"] ?? "");
    $gender = $_POST["gender"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $contact = trim($_POST["contact_number"] ?? "");
    $bloodType = trim($_POST["blood_type"] ?? "");
    $emergencyContact = trim($_POST["emergency_contact"] ?? "");
    $emergencyNumber = trim($_POST["emergency_number"] ?? "");

    if ($fullName === "" || strlen($fullName) > 150) {
        throw new RuntimeException("Please enter a valid patient name.");
    }

    if (!in_array($gender, ["Male", "Female", "Other"], true)) {
        throw new RuntimeException("Please select a valid gender.");
    }

    $date = null;

    if ($birthDate !== "") {
        $parsedDate = DateTime::createFromFormat("Y-m-d", $birthDate);

        if (!$parsedDate || $parsedDate->format("Y-m-d") !== $birthDate) {
            throw new RuntimeException("Please enter a valid birth date.");
        }

        if ($birthDate > date("Y-m-d")) {
            throw new RuntimeException("Birth date cannot be in the future.");
        }

        $date = $birthDate;
    }

    $age = null;

    if ($ageInput !== "") {
        if (!ctype_digit($ageInput) || (int)$ageInput > 150) {
            throw new RuntimeException("Please enter a valid age.");
        }

        $age = (int)$ageInput;
    }

    $allowedBloodTypes = [
        "", "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"
    ];

    if (!in_array($bloodType, $allowedBloodTypes, true)) {
        throw new RuntimeException("Please select a valid blood type.");
    }

    return [
        $fullName,
        $date,
        $age,
        $gender,
        $address ?: null,
        $contact ?: null,
        $bloodType ?: null,
        $emergencyContact ?: null,
        $emergencyNumber ?: null
    ];
}

// =============================================
// HANDLE POST ACTIONS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $submittedToken = $_POST["csrf_token"] ?? "";

        if (
            !is_string($submittedToken) ||
            !hash_equals($csrfToken, $submittedToken)
        ) {
            throw new RuntimeException("Invalid request. Please refresh the page and try again.");
        }

        // ADD PATIENT
        if (isset($_POST["add_patient"])) {

            $data = validatePatientInput();

            $stmt = $pdo->prepare("
                INSERT INTO bhw_patients (
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

            $stmt->execute($data);

            header("Location: patients.php?success=added");
            exit;
        }

        // UPDATE PATIENT
        if (isset($_POST["update_patient"])) {

            $id = filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                throw new RuntimeException("Invalid patient ID.");
            }

            $data = validatePatientInput();

            $stmt = $pdo->prepare("
                UPDATE bhw_patients
                SET
                    full_name = ?,
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

            $data[] = $id;
            $stmt->execute($data);

            header("Location: patients.php?success=updated");
            exit;
        }

        // DELETE PATIENT
        if (isset($_POST["delete_patient"])) {

            $id = filter_var(
                $_POST["id"] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id || $id <= 0) {
                throw new RuntimeException("Invalid patient ID.");
            }

            $pdo->beginTransaction();

            // Verify patient exists
            $check = $pdo->prepare("
                SELECT id
                FROM bhw_patients
                WHERE id = ?
                FOR UPDATE
            ");

            $check->execute([$id]);

            if (!$check->fetch()) {
                throw new RuntimeException("Patient not found.");
            }

            // Check related records
            $relatedTables = [
                "bhw_appointments",
                "bhw_health_records",
                "bhw_immunization",
                "bhw_prenatal"
            ];

            foreach ($relatedTables as $table) {

                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM `$table` WHERE patient_id = ?"
                );

                $stmt->execute([$id]);

                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        "Deletion blocked. This patient has existing medical records."
                    );
                }
            }

            $delete = $pdo->prepare("
                DELETE FROM bhw_patients
                WHERE id = ?
            ");

            $delete->execute([$id]);

            $pdo->commit();

            header("Location: patients.php?success=deleted");
            exit;
        }

    } catch (Throwable $exception) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($exception instanceof PDOException) {
            error_log("BHW Patient Database Error: " . $exception->getMessage());
            $error = "Unable to process the request. Please check the database structure.";
        } else {
            $error = $exception->getMessage();
        }
    }
}

// =============================================
// EDIT PATIENT
// =============================================

if (isset($_GET["edit"])) {

    $editId = filter_var(
        $_GET["edit"],
        FILTER_VALIDATE_INT
    );

    if ($editId && $editId > 0) {

        $stmt = $pdo->prepare("
            SELECT *
            FROM bhw_patients
            WHERE id = ?
        ");

        $stmt->execute([$editId]);

        $editPatient = $stmt->fetch();
    }
}

// =============================================
// SEARCH PATIENTS
// =============================================

$search = trim($_GET["search"] ?? "");

try {

    $sql = "SELECT * FROM bhw_patients";
    $params = [];

    if ($search !== "") {

        $sql .= "
            WHERE
                full_name LIKE ?
                OR contact_number LIKE ?
                OR address LIKE ?
        ";

        $keyword = "%" . $search . "%";

        $params = [
            $keyword,
            $keyword,
            $keyword
        ];
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $patients = $stmt->fetchAll();

    // STATISTICS
    $totalPatients = (int)$pdo->query("
        SELECT COUNT(*) FROM bhw_patients
    ")->fetchColumn();

    $malePatients = (int)$pdo->query("
        SELECT COUNT(*) FROM bhw_patients
        WHERE gender = 'Male'
    ")->fetchColumn();

    $femalePatients = (int)$pdo->query("
        SELECT COUNT(*) FROM bhw_patients
        WHERE gender = 'Female'
    ")->fetchColumn();

} catch (PDOException $exception) {

    error_log("BHW Patient List Error: " . $exception->getMessage());

    $patients = [];
    $totalPatients = 0;
    $malePatients = 0;
    $femalePatients = 0;

    if (!$error) {
        $error = "Unable to load patient records. Please check your database tables.";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Patient Management | BHW Portal</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest"></script>

<style>
body {
    font-family: Arial, sans-serif;
}

.patient-card {
    transition: .2s ease;
}

.patient-card:hover {
    transform: translateY(-3px);
}

input, select, textarea {
    outline: none;
}

input:focus, select:focus, textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}
</style>

</head>

<body class="bg-slate-100 text-slate-800">

<!-- HEADER -->
<header class="bg-white border-b border-slate-200">

    <div class="max-w-7xl mx-auto px-5 md:px-8 py-5
                flex flex-col sm:flex-row justify-between gap-4">

        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-blue-600">
                BHW Management Portal
            </p>

            <h1 class="text-2xl font-bold mt-1">
                Patient Management
            </h1>

            <p class="text-sm text-slate-500 mt-1">
                Welcome, <?= e($bhwName) ?>
            </p>
        </div>

        <a href="dashboard.php"
           class="bg-slate-900 hover:bg-slate-800 text-white
                  px-5 py-3 rounded-xl text-center font-semibold
                  inline-flex items-center justify-center gap-2">

            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
            Back to Dashboard

        </a>

    </div>

</header>

<main class="max-w-7xl mx-auto p-5 md:p-8">

    <!-- NOTIFICATIONS -->
    <?php if ($message): ?>

        <div class="bg-green-50 border border-green-200 text-green-700
                    p-4 rounded-xl mb-5 flex gap-3">

            <i data-lucide="check-circle" class="shrink-0"></i>
            <?= e($message) ?>

        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="bg-red-50 border border-red-200 text-red-700
                    p-4 rounded-xl mb-5 flex gap-3">

            <i data-lucide="alert-circle" class="shrink-0"></i>
            <?= e($error) ?>

        </div>

    <?php endif; ?>

    <!-- STATISTICS -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

        <div class="patient-card bg-white p-6 rounded-2xl border border-slate-200">
            <div class="flex justify-between items-center">

                <div>
                    <p class="text-sm text-slate-500">Total Patients</p>
                    <h2 class="text-3xl font-bold mt-2">
                        <?= number_format($totalPatients) ?>
                    </h2>
                </div>

                <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">
                    <i data-lucide="users" class="w-7 h-7"></i>
                </div>

            </div>
        </div>

        <div class="patient-card bg-white p-6 rounded-2xl border border-slate-200">
            <div class="flex justify-between items-center">

                <div>
                    <p class="text-sm text-slate-500">Male Patients</p>
                    <h2 class="text-3xl font-bold text-blue-600 mt-2">
                        <?= number_format($malePatients) ?>
                    </h2>
                </div>

                <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">
                    <i data-lucide="user-round" class="w-7 h-7"></i>
                </div>

            </div>
        </div>

        <div class="patient-card bg-white p-6 rounded-2xl border border-slate-200">
            <div class="flex justify-between items-center">

                <div>
                    <p class="text-sm text-slate-500">Female Patients</p>
                    <h2 class="text-3xl font-bold text-pink-600 mt-2">
                        <?= number_format($femalePatients) ?>
                    </h2>
                </div>

                <div class="bg-pink-100 text-pink-600 p-3 rounded-xl">
                    <i data-lucide="user-round" class="w-7 h-7"></i>
                </div>

            </div>
        </div>

    </div>

    <!-- PATIENT FORM -->
    <section class="bg-white p-6 md:p-8 rounded-2xl
                    border border-slate-200 shadow-sm mb-8">

        <div class="flex items-center gap-3 mb-6">

            <div class="bg-blue-100 text-blue-600 p-3 rounded-xl">
                <i data-lucide="<?= $editPatient ? "user-round-pen" : "user-plus" ?>"></i>
            </div>

            <div>
                <h2 class="text-xl font-bold">
                    <?= $editPatient ? "Edit Patient Information" : "Register New Patient" ?>
                </h2>

                <p class="text-sm text-slate-500">
                    Enter the patient's information below.
                </p>
            </div>

        </div>

        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">

            <input type="hidden" name="csrf_token"
                   value="<?= e($csrfToken) ?>">

            <?php if ($editPatient): ?>

                <input type="hidden" name="id"
                       value="<?= (int)$editPatient["id"] ?>">

            <?php endif; ?>

            <div class="md:col-span-2">

                <label class="block text-sm font-semibold mb-2">
                    Full Name *
                </label>

                <input type="text"
                       name="full_name"
                       maxlength="150"
                       required
                       value="<?= e($editPatient["full_name"] ?? "") ?>"
                       placeholder="Enter patient's full name"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Birth Date
                </label>

                <input type="date"
                       name="birth_date"
                       max="<?= date("Y-m-d") ?>"
                       value="<?= e($editPatient["birth_date"] ?? "") ?>"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3">

            </div>

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

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Contact Number
                </label>

                <input type="text"
                       name="contact_number"
                       maxlength="30"
                       value="<?= e($editPatient["contact_number"] ?? "") ?>"
                       placeholder="09XXXXXXXXX"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3">

            </div>

            <div class="md:col-span-2">

                <label class="block text-sm font-semibold mb-2">
                    Address
                </label>

                <textarea name="address"
                          rows="2"
                          placeholder="Enter complete address"
                          class="w-full border border-slate-300 rounded-xl px-4 py-3"><?= e($editPatient["address"] ?? "") ?></textarea>

            </div>

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

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Emergency Contact Name
                </label>

                <input type="text"
                       name="emergency_contact"
                       maxlength="150"
                       value="<?= e($editPatient["emergency_contact"] ?? "") ?>"
                       placeholder="Emergency contact person"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3">

            </div>

            <div>

                <label class="block text-sm font-semibold mb-2">
                    Emergency Contact Number
                </label>

                <input type="text"
                       name="emergency_number"
                       maxlength="30"
                       value="<?= e($editPatient["emergency_number"] ?? "") ?>"
                       placeholder="09XXXXXXXXX"
                       class="w-full border border-slate-300 rounded-xl px-4 py-3">

            </div>

            <div class="md:col-span-2 flex flex-wrap gap-3">

                <button type="submit"
                        name="<?= $editPatient ? "update_patient" : "add_patient" ?>"
                        class="bg-blue-600 hover:bg-blue-700 text-white
                               px-6 py-3 rounded-xl font-semibold">

                    <?= $editPatient ? "Update Patient" : "Register Patient" ?>

                </button>

                <?php if ($editPatient): ?>

                    <a href="patients.php"
                       class="bg-slate-200 hover:bg-slate-300
                              px-6 py-3 rounded-xl font-semibold">

                        Cancel

                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>

    <!-- PATIENT LIST -->
    <section class="bg-white p-6 md:p-8 rounded-2xl
                    border border-slate-200 shadow-sm">

        <div class="flex flex-col md:flex-row
                    justify-between gap-4 mb-6">

            <div>
                <h2 class="text-xl font-bold">Registered Patients</h2>

                <p class="text-sm text-slate-500 mt-1">
                    Manage patient information and records.
                </p>
            </div>

            <form method="GET" class="flex gap-2">

                <input type="text"
                       name="search"
                       value="<?= e($search) ?>"
                       placeholder="Search patient..."
                       class="border border-slate-300 rounded-xl px-4 py-2 min-w-0">

                <button type="submit"
                        class="bg-slate-900 hover:bg-slate-800
                               text-white px-5 py-2 rounded-xl">

                    Search

                </button>

                <?php if ($search !== ""): ?>

                    <a href="patients.php"
                       class="bg-slate-200 px-4 py-2 rounded-xl flex items-center">

                        Clear

                    </a>

                <?php endif; ?>

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

                <?php if (!empty($patients)): ?>

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
                                               name="csrf_token"
                                               value="<?= e($csrfToken) ?>">

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
                            class="p-10 text-center text-slate-500">

                            <i data-lucide="users"
                               class="w-10 h-10 mx-auto mb-3 text-slate-300"></i>

                            No patient records found.

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <p class="text-xs text-slate-400 mt-5">
            Showing <?= count($patients) ?> patient record(s).
        </p>

    </section>

    <footer class="text-center text-xs text-slate-400 py-8">
        © <?= date("Y") ?> Barangay Health Center Management System.
        All Rights Reserved.
    </footer>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.lucide) {
        lucide.createIcons();
    }
});
</script>

</body>
</html>
