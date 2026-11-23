
<?php
// ==========================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR - PATIENT MANAGEMENT
// PDO VERSION
//
// Database: barangay_health_db
// Table: bhw_patients
// Session: BARANGAY_ADMIN_SESSION
// ==========================================================


// ==========================================================
// SESSION SECURITY
// ==========================================================

$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_ADMIN_SESSION");

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


// ==========================================================
// SECURITY HEADERS
// ==========================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");


// ==========================================================
// ADMIN AUTHENTICATION
// ==========================================================

if (empty($_SESSION["admin_id"])) {
    header("Location: adminlogIn.php");
    exit;
}


// ==========================================================
// DATABASE CONNECTION
// Connect directly to barangay_health_db
// ==========================================================

$host = "localhost";
$port = "3306";
$dbname = "barangay_health_db";
$username = "root";
$password = "";

try {

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {

    error_log("Administrator Patient Database Error: " . $e->getMessage());

    http_response_code(500);
    exit("Database connection error. Please check barangay_health_db.");

}


// ==========================================================
// VERIFY ADMIN ACCOUNT
// ==========================================================

try {

    $adminCheck = $pdo->prepare("
        SELECT id, full_name, email, position, status
        FROM admin_account
        WHERE id = :id
        LIMIT 1
    ");

    $adminCheck->execute([
        ":id" => (int) $_SESSION["admin_id"]
    ]);

    $currentAdmin = $adminCheck->fetch();

    if (
        !$currentAdmin ||
        strcasecmp(trim((string)$currentAdmin["status"]), "Active") !== 0
    ) {

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );

        }

        session_destroy();

        header("Location: adminlogIn.php");
        exit;
    }

} catch (PDOException $e) {

    error_log("Admin Verification Error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to verify administrator account.");

}


// ==========================================================
// HELPER FUNCTION
// ==========================================================

function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// ==========================================================
// CSRF TOKEN
// ==========================================================

if (empty($_SESSION["patients_csrf"])) {
    $_SESSION["patients_csrf"] = bin2hex(random_bytes(32));
}


// ==========================================================
// FLASH MESSAGE
// ==========================================================

$message = $_SESSION["patients_message"] ?? "";
$messageType = $_SESSION["patients_message_type"] ?? "success";

unset(
    $_SESSION["patients_message"],
    $_SESSION["patients_message_type"]
);


// ==========================================================
// REDIRECT HELPER
// ==========================================================

function redirectPatients()
{
    header("Location: patients.php");
    exit;
}


// ==========================================================
// HANDLE FORM SUBMISSIONS
// ==========================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenInput = $_POST["csrf_token"] ?? "";
    $actionInput = $_POST["action"] ?? "";

    $token = is_string($tokenInput) ? $tokenInput : "";
    $action = is_string($actionInput) ? $actionInput : "";

    try {

        // ------------------------------------------
        // CSRF VALIDATION
        // ------------------------------------------

        if (
            !isset($_SESSION["patients_csrf"]) ||
            !hash_equals($_SESSION["patients_csrf"], $token)
        ) {
            throw new RuntimeException(
                "Invalid request. Please refresh the page."
            );
        }


        // ==========================================
        // ADD OR UPDATE PATIENT
        // ==========================================

        if ($action === "save") {

            $patientId = filter_var(
                $_POST["patient_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            $fullName = trim((string)($_POST["full_name"] ?? ""));
            $birthDate = trim((string)($_POST["birth_date"] ?? ""));
            $gender = trim((string)($_POST["gender"] ?? ""));
            $address = trim((string)($_POST["address"] ?? ""));
            $contactNumber = trim((string)($_POST["contact_number"] ?? ""));
            $bloodType = trim((string)($_POST["blood_type"] ?? ""));
            $emergencyContact = trim((string)($_POST["emergency_contact"] ?? ""));
            $emergencyNumber = trim((string)($_POST["emergency_number"] ?? ""));


            // --------------------------------------
            // VALIDATION
            // --------------------------------------

            if (
                $fullName === "" ||
                $birthDate === "" ||
                $gender === "" ||
                $address === ""
            ) {
                throw new RuntimeException(
                    "Please complete all required patient information."
                );
            }

            if (
                mb_strlen($fullName, "UTF-8") > 150 ||
                mb_strlen($emergencyContact, "UTF-8") > 150
            ) {
                throw new RuntimeException("Name is too long.");
            }

            if (mb_strlen($address, "UTF-8") > 255) {
                throw new RuntimeException("Address is too long.");
            }

            if (mb_strlen($contactNumber) > 30 || mb_strlen($emergencyNumber) > 30) {
                throw new RuntimeException("Contact number is too long.");
            }

            if (!in_array($gender, ["Male", "Female", "Other"], true)) {
                throw new RuntimeException("Please select a valid gender.");
            }

            $allowedBloodTypes = [
                "", "A+", "A-", "B+", "B-",
                "AB+", "AB-", "O+", "O-"
            ];

            if (!in_array($bloodType, $allowedBloodTypes, true)) {
                throw new RuntimeException("Invalid blood type.");
            }


            // --------------------------------------
            // VALIDATE BIRTH DATE
            // --------------------------------------

            $dateObject = DateTime::createFromFormat("!Y-m-d", $birthDate);
            $dateErrors = DateTime::getLastErrors();

            if (
                !$dateObject ||
                ($dateErrors !== false && (
                    $dateErrors["warning_count"] > 0 ||
                    $dateErrors["error_count"] > 0
                )) ||
                $dateObject->format("Y-m-d") !== $birthDate
            ) {
                throw new RuntimeException("Invalid birth date.");
            }

            if ($dateObject > new DateTime("today")) {
                throw new RuntimeException("Birth date cannot be in the future.");
            }

            $age = $dateObject->diff(new DateTime("today"))->y;


            // --------------------------------------
            // UPDATE PATIENT
            // --------------------------------------

            if ($patientId !== false && $patientId > 0) {

                $check = $pdo->prepare("
                    SELECT id
                    FROM bhw_patients
                    WHERE id = :id
                    LIMIT 1
                ");

                $check->execute([":id" => $patientId]);

                if (!$check->fetch()) {
                    throw new RuntimeException("Patient record not found.");
                }

                $stmt = $pdo->prepare("
                    UPDATE bhw_patients
                    SET
                        full_name = :full_name,
                        birth_date = :birth_date,
                        age = :age,
                        gender = :gender,
                        address = :address,
                        contact_number = :contact_number,
                        blood_type = :blood_type,
                        emergency_contact = :emergency_contact,
                        emergency_number = :emergency_number
                    WHERE id = :id
                ");

                $stmt->execute([
                    ":full_name" => $fullName,
                    ":birth_date" => $birthDate,
                    ":age" => $age,
                    ":gender" => $gender,
                    ":address" => $address,
                    ":contact_number" => $contactNumber,
                    ":blood_type" => $bloodType,
                    ":emergency_contact" => $emergencyContact,
                    ":emergency_number" => $emergencyNumber,
                    ":id" => $patientId
                ]);

                $_SESSION["patients_message"] = "Patient information updated successfully.";

            } else {

                // ----------------------------------
                // INSERT PATIENT
                // ----------------------------------

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
                        emergency_number,
                        created_at
                    ) VALUES (
                        :full_name,
                        :birth_date,
                        :age,
                        :gender,
                        :address,
                        :contact_number,
                        :blood_type,
                        :emergency_contact,
                        :emergency_number,
                        CURRENT_TIMESTAMP
                    )
                ");

                $stmt->execute([
                    ":full_name" => $fullName,
                    ":birth_date" => $birthDate,
                    ":age" => $age,
                    ":gender" => $gender,
                    ":address" => $address,
                    ":contact_number" => $contactNumber,
                    ":blood_type" => $bloodType,
                    ":emergency_contact" => $emergencyContact,
                    ":emergency_number" => $emergencyNumber
                ]);

                $_SESSION["patients_message"] = "Patient registered successfully.";
            }

            $_SESSION["patients_message_type"] = "success";
        }


        // ==========================================
        // DELETE PATIENT
        // ==========================================

        elseif ($action === "delete") {

            $patientId = filter_var(
                $_POST["patient_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            if (!$patientId || $patientId < 1) {
                throw new RuntimeException("Invalid patient record.");
            }

            $check = $pdo->prepare("
                SELECT id
                FROM bhw_patients
                WHERE id = :id
                LIMIT 1
            ");

            $check->execute([":id" => $patientId]);

            if (!$check->fetch()) {
                throw new RuntimeException("Patient record not found.");
            }


            // --------------------------------------
            // CHECK RELATED RECORDS
            // --------------------------------------

            $relatedTables = [
                "bhw_appointments",
                "bhw_health_records",
                "bhw_immunization",
                "bhw_prenatal"
            ];

            $hasRelatedRecords = false;

            foreach ($relatedTables as $table) {

                $tableCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.tables
                    WHERE table_schema = DATABASE()
                    AND table_name = :table_name
                ");

                $tableCheck->execute([":table_name" => $table]);

                if ((int)$tableCheck->fetchColumn() === 0) {
                    continue;
                }

                $columnCheck = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM information_schema.columns
                    WHERE table_schema = DATABASE()
                    AND table_name = :table_name
                    AND column_name = 'patient_id'
                ");

                $columnCheck->execute([":table_name" => $table]);

                if ((int)$columnCheck->fetchColumn() === 0) {
                    continue;
                }

                $relatedStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM `$table`
                    WHERE patient_id = :patient_id
                ");

                $relatedStmt->execute([
                    ":patient_id" => $patientId
                ]);

                if ((int)$relatedStmt->fetchColumn() > 0) {
                    $hasRelatedRecords = true;
                    break;
                }
            }

            if ($hasRelatedRecords) {
                throw new RuntimeException(
                    "Cannot delete this patient because related health records exist."
                );
            }


            // --------------------------------------
            // DELETE
            // --------------------------------------

            $delete = $pdo->prepare("
                DELETE FROM bhw_patients
                WHERE id = :id
            ");

            $delete->execute([":id" => $patientId]);

            $_SESSION["patients_message"] = "Patient record deleted successfully.";
            $_SESSION["patients_message_type"] = "success";
        }

        else {
            throw new RuntimeException("Invalid patient action.");
        }

    } catch (RuntimeException $e) {

        $_SESSION["patients_message"] = $e->getMessage();
        $_SESSION["patients_message_type"] = "error";

    } catch (PDOException $e) {

        error_log("Patient Management Database Error: " . $e->getMessage());

        $_SESSION["patients_message"] =
            "Unable to process patient information. Please check the database structure.";

        $_SESSION["patients_message_type"] = "error";

    } catch (Throwable $e) {

        error_log("Patient Management Error: " . $e->getMessage());

        $_SESSION["patients_message"] = "An unexpected error occurred.";
        $_SESSION["patients_message_type"] = "error";
    }


    $_SESSION["patients_csrf"] = bin2hex(random_bytes(32));

    redirectPatients();
}


// ==========================================================
// GET PATIENT FOR EDITING
// ==========================================================

$editPatient = null;

$editId = filter_input(INPUT_GET, "edit", FILTER_VALIDATE_INT);

if ($editId && $editId > 0) {

    $editStmt = $pdo->prepare("
        SELECT *
        FROM bhw_patients
        WHERE id = :id
        LIMIT 1
    ");

    $editStmt->execute([":id" => $editId]);

    $editPatient = $editStmt->fetch();

    if (!$editPatient) {
        $message = "Patient record not found.";
        $messageType = "error";
    }
}


// ==========================================================
// FETCH PATIENTS
// ==========================================================

try {

    $stmt = $pdo->query("
        SELECT
            id,
            full_name,
            birth_date,
            age,
            gender,
            address,
            contact_number,
            blood_type,
            emergency_contact,
            emergency_number,
            created_at
        FROM bhw_patients
        ORDER BY id DESC
    ");

    $patients = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log("Patient Fetch Error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load patient records.");
}


// ==========================================================
// STATISTICS
// ==========================================================

$totalPatients = count($patients);
$malePatients = 0;
$femalePatients = 0;

foreach ($patients as $patient) {

    if ($patient["gender"] === "Male") {
        $malePatients++;
    }

    if ($patient["gender"] === "Female") {
        $femalePatients++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title>Patient Management | Administrator</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-slate-100 min-h-screen">


<!-- ==========================================================
     NAVIGATION
========================================================== -->

<nav class="bg-blue-950 text-white px-5 sm:px-8 py-4 shadow-lg">

    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">

        <div>
            <h1 class="text-lg sm:text-xl font-bold">
                Barangay Guindacpan
            </h1>

            <p class="text-xs text-blue-200">
                Administrator Portal
            </p>
        </div>

        <div class="flex items-center gap-3">

            <span class="hidden sm:block text-sm text-blue-100">
                <?= e($currentAdmin["full_name"]); ?>
            </span>

            <a
                href="dashboard.php"
                class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm transition"
            >
                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                Dashboard
            </a>

        </div>

    </div>

</nav>


<main class="max-w-7xl mx-auto p-4 sm:p-8">


    <!-- PAGE HEADER -->

    <div class="mb-8">

        <div class="flex items-center gap-3">

            <div class="bg-blue-100 text-blue-700 p-3 rounded-xl">
                <i data-lucide="users" class="w-7 h-7"></i>
            </div>

            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">
                    Patient Management
                </h2>

                <p class="text-gray-500 text-sm mt-1">
                    Manage and monitor registered patient information.
                </p>
            </div>

        </div>

    </div>


    <!-- FLASH MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="mb-6 p-4 rounded-xl border flex gap-3
            <?= $messageType === "success"
                ? "bg-green-50 border-green-200 text-green-700"
                : "bg-red-50 border-red-200 text-red-700"; ?>">

            <i
                data-lucide="<?= $messageType === "success" ? "check-circle" : "alert-circle"; ?>"
                class="w-5 h-5 shrink-0"
            ></i>

            <p class="text-sm font-medium">
                <?= e($message); ?>
            </p>

        </div>

    <?php endif; ?>


    <!-- STATISTICS -->

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">

                <div>
                    <p class="text-gray-500 text-sm">Total Patients</p>
                    <h3 class="text-3xl font-bold text-gray-900 mt-2">
                        <?= $totalPatients; ?>
                    </h3>
                </div>

                <div class="bg-blue-100 text-blue-700 p-3 rounded-xl">
                    <i data-lucide="users" class="w-6 h-6"></i>
                </div>

            </div>
        </div>


        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">

                <div>
                    <p class="text-gray-500 text-sm">Male Patients</p>
                    <h3 class="text-3xl font-bold text-blue-600 mt-2">
                        <?= $malePatients; ?>
                    </h3>
                </div>

                <div class="bg-blue-100 text-blue-700 p-3 rounded-xl">
                    <i data-lucide="user" class="w-6 h-6"></i>
                </div>

            </div>
        </div>


        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
            <div class="flex items-center justify-between">

                <div>
                    <p class="text-gray-500 text-sm">Female Patients</p>
                    <h3 class="text-3xl font-bold text-pink-600 mt-2">
                        <?= $femalePatients; ?>
                    </h3>
                </div>

                <div class="bg-pink-100 text-pink-700 p-3 rounded-xl">
                    <i data-lucide="user-round" class="w-6 h-6"></i>
                </div>

            </div>
        </div>

    </div>


    <!-- PATIENT FORM -->

    <section
        id="patientForm"
        class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-7 mb-8"
    >

        <div class="flex items-center justify-between gap-3 mb-6">

            <div>
                <h3 class="text-xl font-bold text-gray-900">
                    <?= $editPatient ? "Edit Patient Information" : "Register New Patient"; ?>
                </h3>

                <p class="text-sm text-gray-500 mt-1">
                    Enter the patient's personal and contact details.
                </p>
            </div>

            <?php if ($editPatient): ?>

                <a
                    href="patients.php"
                    class="text-sm text-gray-500 hover:text-red-600"
                >
                    Cancel Edit
                </a>

            <?php endif; ?>

        </div>


        <form method="POST" action="patients.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION["patients_csrf"]); ?>"
            >

            <input type="hidden" name="action" value="save">

            <input
                type="hidden"
                name="patient_id"
                value="<?= e($editPatient["id"] ?? ""); ?>"
            >


            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">


                <!-- FULL NAME -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name <span class="text-red-500">*</span>
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        maxlength="150"
                        value="<?= e($editPatient["full_name"] ?? ""); ?>"
                        placeholder="Enter full name"
                        required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>


                <!-- BIRTH DATE -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Date of Birth <span class="text-red-500">*</span>
                    </label>

                    <input
                        type="date"
                        name="birth_date"
                        max="<?= date("Y-m-d"); ?>"
                        value="<?= e($editPatient["birth_date"] ?? ""); ?>"
                        required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>


                <!-- GENDER -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Gender <span class="text-red-500">*</span>
                    </label>

                    <select
                        name="gender"
                        required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    >

                        <option value="">Select Gender</option>

                        <?php foreach (["Male", "Female", "Other"] as $gender): ?>

                            <option
                                value="<?= e($gender); ?>"
                                <?= ($editPatient["gender"] ?? "") === $gender ? "selected" : ""; ?>
                            >
                                <?= e($gender); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- BLOOD TYPE -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Blood Type
                    </label>

                    <select
                        name="blood_type"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    >

                        <option value="">Select Blood Type</option>

                        <?php foreach (
                            ["A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"]
                            as $type
                        ): ?>

                            <option
                                value="<?= e($type); ?>"
                                <?= ($editPatient["blood_type"] ?? "") === $type ? "selected" : ""; ?>
                            >
                                <?= e($type); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <!-- CONTACT NUMBER -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Contact Number
                    </label>

                    <input
                        type="text"
                        name="contact_number"
                        maxlength="30"
                        value="<?= e($editPatient["contact_number"] ?? ""); ?>"
                        placeholder="Enter contact number"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>


                <!-- ADDRESS -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Complete Address <span class="text-red-500">*</span>
                    </label>

                    <input
                        type="text"
                        name="address"
                        maxlength="255"
                        value="<?= e($editPatient["address"] ?? ""); ?>"
                        placeholder="Purok / Sitio / Barangay"
                        required
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>


                <!-- EMERGENCY CONTACT -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Emergency Contact Person
                    </label>

                    <input
                        type="text"
                        name="emergency_contact"
                        maxlength="150"
                        value="<?= e($editPatient["emergency_contact"] ?? ""); ?>"
                        placeholder="Enter contact person's name"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>


                <!-- EMERGENCY NUMBER -->

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Emergency Contact Number
                    </label>

                    <input
                        type="text"
                        name="emergency_number"
                        maxlength="30"
                        value="<?= e($editPatient["emergency_number"] ?? ""); ?>"
                        placeholder="Enter emergency number"
                        class="w-full px-4 py-3 border border-gray-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>

            </div>


            <!-- BUTTONS -->

            <div class="flex flex-col sm:flex-row gap-3 mt-7">

                <button
                    type="submit"
                    class="inline-flex items-center justify-center gap-2 bg-blue-700 hover:bg-blue-800 text-white px-6 py-3 rounded-xl font-semibold transition"
                >
                    <i data-lucide="save" class="w-4 h-4"></i>
                    <?= $editPatient ? "Update Patient" : "Register Patient"; ?>
                </button>

                <?php if ($editPatient): ?>

                    <a
                        href="patients.php"
                        class="inline-flex items-center justify-center border border-gray-200 hover:bg-gray-50 px-6 py-3 rounded-xl font-semibold text-gray-600 transition"
                    >
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>


    <!-- PATIENT LIST -->

    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        <div class="p-5 sm:p-6 border-b border-gray-100">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">

                <div>
                    <h3 class="text-xl font-bold text-gray-900">
                        Registered Patients
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        View and manage patient records.
                    </p>
                </div>

                <div class="relative">

                    <i
                        data-lucide="search"
                        class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                    ></i>

                    <input
                        type="search"
                        id="patientSearch"
                        placeholder="Search patient..."
                        class="w-full sm:w-72 pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500"
                    >

                </div>

            </div>

        </div>


        <div class="overflow-x-auto">

            <table class="w-full text-sm text-left">

                <thead class="bg-slate-50 text-gray-600 uppercase text-xs">

                    <tr>
                        <th class="px-5 py-4">ID</th>
                        <th class="px-5 py-4">Patient Name</th>
                        <th class="px-5 py-4">Age</th>
                        <th class="px-5 py-4">Gender</th>
                        <th class="px-5 py-4">Contact</th>
                        <th class="px-5 py-4">Blood Type</th>
                        <th class="px-5 py-4">Address</th>
                        <th class="px-5 py-4">Actions</th>
                    </tr>

                </thead>


                <tbody id="patientTable" class="divide-y divide-gray-100">

                    <?php if (empty($patients)): ?>

                        <tr>
                            <td colspan="8" class="text-center py-14 text-gray-500">

                                <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 text-gray-300"></i>

                                <p class="font-medium">
                                    No patients registered yet.
                                </p>

                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($patients as $patient): ?>

                            <tr class="patient-row hover:bg-slate-50 transition">

                                <td class="px-5 py-4 font-semibold text-gray-600 whitespace-nowrap">
                                    #<?= e($patient["id"]); ?>
                                </td>

                                <td class="px-5 py-4 whitespace-nowrap">

                                    <p class="font-semibold text-gray-900">
                                        <?= e($patient["full_name"]); ?>
                                    </p>

                                    <p class="text-xs text-gray-400 mt-1">
                                        <?= e($patient["birth_date"]); ?>
                                    </p>

                                </td>

                                <td class="px-5 py-4 text-gray-700">
                                    <?= e($patient["age"]); ?>
                                </td>

                                <td class="px-5 py-4 text-gray-700">
                                    <?= e($patient["gender"]); ?>
                                </td>

                                <td class="px-5 py-4 text-gray-600 whitespace-nowrap">
                                    <?= e($patient["contact_number"] ?: "—"); ?>
                                </td>

                                <td class="px-5 py-4">

                                    <?php if (!empty($patient["blood_type"])): ?>

                                        <span class="px-3 py-1 rounded-full bg-red-50 text-red-700 text-xs font-semibold">
                                            <?= e($patient["blood_type"]); ?>
                                        </span>

                                    <?php else: ?>

                                        <span class="text-gray-400">—</span>

                                    <?php endif; ?>

                                </td>

                                <td class="px-5 py-4 text-gray-600 max-w-xs">
                                    <span class="block truncate">
                                        <?= e($patient["address"]); ?>
                                    </span>
                                </td>

                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-2">

                                        <!-- VIEW -->

                                        <button
                                            type="button"
                                            onclick="viewPatient(this)"
                                            data-name="<?= e($patient["full_name"]); ?>"
                                            data-birth="<?= e($patient["birth_date"]); ?>"
                                            data-age="<?= e($patient["age"]); ?>"
                                            data-gender="<?= e($patient["gender"]); ?>"
                                            data-address="<?= e($patient["address"]); ?>"
                                            data-contact="<?= e($patient["contact_number"]); ?>"
                                            data-blood="<?= e($patient["blood_type"]); ?>"
                                            data-emergency="<?= e($patient["emergency_contact"]); ?>"
                                            data-emergency-number="<?= e($patient["emergency_number"]); ?>"
                                            class="bg-blue-100 hover:bg-blue-200 text-blue-700 p-2 rounded-lg"
                                            title="View patient"
                                        >
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </button>


                                        <!-- EDIT -->

                                        <a
                                            href="patients.php?edit=<?= (int)$patient["id"]; ?>#patientForm"
                                            class="bg-orange-100 hover:bg-orange-200 text-orange-700 p-2 rounded-lg"
                                            title="Edit patient"
                                        >
                                            <i data-lucide="pencil" class="w-4 h-4"></i>
                                        </a>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this patient? Related health records will prevent deletion.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["patients_csrf"]); ?>"
                                            >

                                            <input type="hidden" name="action" value="delete">

                                            <input
                                                type="hidden"
                                                name="patient_id"
                                                value="<?= (int)$patient["id"]; ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="bg-red-100 hover:bg-red-200 text-red-700 p-2 rounded-lg"
                                                title="Delete patient"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        <tr id="noPatientResults" class="hidden">
                            <td colspan="8" class="text-center py-10 text-gray-500">
                                No matching patient found.
                            </td>
                        </tr>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>


        <div class="px-5 py-4 border-t border-gray-100 bg-slate-50">

            <p class="text-xs text-gray-500">
                Total registered patients: <?= $totalPatients; ?>
            </p>

        </div>

    </section>


    <!-- FOOTER -->

    <footer class="text-center text-xs text-gray-400 mt-8">

        Barangay Guindacpan Management System
        <br>
        Administrator Portal

    </footer>

</main>


<!-- ==========================================================
     PATIENT DETAILS MODAL
========================================================== -->

<div
    id="patientModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4"
>

    <div class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">

        <div class="bg-blue-950 text-white p-5 flex items-center justify-between">

            <div>
                <h3 class="font-bold text-lg">
                    Patient Information
                </h3>

                <p class="text-blue-200 text-xs">
                    Patient record details
                </p>
            </div>

            <button
                type="button"
                onclick="closePatientModal()"
                class="text-white hover:bg-white/10 p-2 rounded-lg"
            >
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

        </div>


        <div class="p-6 space-y-4">

            <div>
                <p class="text-xs text-gray-400">Full Name</p>
                <p id="viewName" class="font-semibold text-gray-900"></p>
            </div>

            <div class="grid grid-cols-2 gap-4">

                <div>
                    <p class="text-xs text-gray-400">Birth Date</p>
                    <p id="viewBirth" class="font-medium"></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400">Age</p>
                    <p id="viewAge" class="font-medium"></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400">Gender</p>
                    <p id="viewGender" class="font-medium"></p>
                </div>

                <div>
                    <p class="text-xs text-gray-400">Blood Type</p>
                    <p id="viewBlood" class="font-medium"></p>
                </div>

            </div>

            <div>
                <p class="text-xs text-gray-400">Address</p>
                <p id="viewAddress" class="font-medium"></p>
            </div>

            <div>
                <p class="text-xs text-gray-400">Contact Number</p>
                <p id="viewContact" class="font-medium"></p>
            </div>

            <div class="border-t border-gray-100 pt-4">

                <h4 class="font-bold text-gray-800 mb-3">
                    Emergency Contact
                </h4>

                <p class="text-xs text-gray-400">Contact Person</p>
                <p id="viewEmergency" class="font-medium mb-3"></p>

                <p class="text-xs text-gray-400">Emergency Number</p>
                <p id="viewEmergencyNumber" class="font-medium"></p>

            </div>

        </div>


        <div class="p-5 bg-gray-50 flex justify-end">

            <button
                type="button"
                onclick="closePatientModal()"
                class="bg-blue-700 hover:bg-blue-800 text-white px-5 py-2.5 rounded-lg text-sm font-semibold"
            >
                Close
            </button>

        </div>

    </div>

</div>


<!-- ==========================================================
     JAVASCRIPT
========================================================== -->

<script>

lucide.createIcons();


// ==========================================
// PATIENT SEARCH
// ==========================================

const patientSearch = document.getElementById("patientSearch");
const patientRows = document.querySelectorAll(".patient-row");
const noPatientResults = document.getElementById("noPatientResults");

patientSearch.addEventListener("input", function () {

    const keyword = this.value.toLowerCase().trim();

    let visible = 0;

    patientRows.forEach(function (row) {

        const matches = row.textContent.toLowerCase().includes(keyword);

        row.classList.toggle("hidden", !matches);

        if (matches) {
            visible++;
        }

    });

    if (noPatientResults) {
        noPatientResults.classList.toggle("hidden", visible !== 0);
    }

});


// ==========================================
// VIEW PATIENT
// ==========================================

function viewPatient(button) {

    document.getElementById("viewName").textContent =
        button.dataset.name || "—";

    document.getElementById("viewBirth").textContent =
        button.dataset.birth || "—";

    document.getElementById("viewAge").textContent =
        button.dataset.age ? button.dataset.age + " years old" : "—";

    document.getElementById("viewGender").textContent =
        button.dataset.gender || "—";

    document.getElementById("viewBlood").textContent =
        button.dataset.blood || "Not specified";

    document.getElementById("viewAddress").textContent =
        button.dataset.address || "—";

    document.getElementById("viewContact").textContent =
        button.dataset.contact || "Not provided";

    document.getElementById("viewEmergency").textContent =
        button.dataset.emergency || "Not provided";

    document.getElementById("viewEmergencyNumber").textContent =
        button.dataset.emergencyNumber || "Not provided";

    const modal = document.getElementById("patientModal");

    modal.classList.remove("hidden");
    modal.classList.add("flex");

}


// ==========================================
// CLOSE MODAL
// ==========================================

function closePatientModal() {

    const modal = document.getElementById("patientModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");

}


// Close outside modal

document.getElementById("patientModal").addEventListener("click", function (event) {

    if (event.target === this) {
        closePatientModal();
    }

});


// Close with Escape

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {
        closePatientModal();
    }

});

</script>

</body>
</html>
