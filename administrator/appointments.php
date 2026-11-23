
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR APPOINTMENT MANAGEMENT
// SECURE PDO VERSION
//
// Database: barangay_health_db
// Tables: bhw_appointments, bhw_patients, admin_account
// Session: BARANGAY_ADMIN_SESSION
// ==================================================


// ==================================================
// 1. SESSION SECURITY
// ==================================================

$isHttps = !empty($_SERVER["HTTPS"]) &&
           $_SERVER["HTTPS"] !== "off";

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


// ==================================================
// 2. SECURITY HEADERS
// ==================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");


// ==================================================
// 3. HELPER FUNCTIONS
// ==================================================

function e($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

function redirectAppointments(): void
{
    header("Location: appointments.php");
    exit;
}


// ==================================================
// 4. VERIFY ADMIN SESSION
// ==================================================

if (
    empty($_SESSION["admin_id"]) ||
    !is_numeric($_SESSION["admin_id"])
) {
    header("Location: login.php");
    exit;
}


// ==================================================
// 5. DATABASE CONNECTION
// ==================================================

try {

    $pdo = new PDO(
        "mysql:host=localhost;port=3306;dbname=barangay_health_db;charset=utf8mb4",
        "root",
        "",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $exception) {

    error_log("APPOINTMENT DATABASE ERROR: " . $exception->getMessage());

    http_response_code(500);

    exit("Database connection error. Please contact the administrator.");

}


// ==================================================
// 6. VERIFY ADMIN ACCOUNT
// ==================================================

try {

    $adminCheck = $pdo->prepare("
        SELECT id, full_name, status
        FROM admin_account
        WHERE id = :id
        LIMIT 1
    ");

    $adminCheck->execute([
        ":id" => (int)$_SESSION["admin_id"]
    ]);

    $admin = $adminCheck->fetch();

    if (
        !$admin ||
        strtolower(trim((string)$admin["status"])) !== "active"
    ) {

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(session_name(), "", [
                "expires" => time() - 42000,
                "path" => $params["path"],
                "domain" => $params["domain"],
                "secure" => $params["secure"],
                "httponly" => true,
                "samesite" => "Lax"
            ]);
        }

        session_destroy();

        header("Location: login.php");
        exit;
    }

    $_SESSION["admin_name"] = $admin["full_name"];

} catch (PDOException $exception) {

    error_log("ADMIN VALIDATION ERROR: " . $exception->getMessage());

    http_response_code(500);

    exit("Unable to verify administrator account.");

}


// ==================================================
// 7. SESSION TIMEOUT
// ==================================================

$sessionTimeout = 1800;

if (
    isset($_SESSION["admin_last_activity"]) &&
    time() - (int)$_SESSION["admin_last_activity"] > $sessionTimeout
) {

    $_SESSION = [];

    session_destroy();

    header("Location: login.php?timeout=1");
    exit;
}

$_SESSION["admin_last_activity"] = time();


// ==================================================
// 8. CSRF TOKEN
// ==================================================

if (
    empty($_SESSION["admin_appointments_csrf"]) ||
    !is_string($_SESSION["admin_appointments_csrf"])
) {

    $_SESSION["admin_appointments_csrf"] = bin2hex(random_bytes(32));

}

$csrfToken = $_SESSION["admin_appointments_csrf"];


// ==================================================
// 9. FLASH MESSAGE
// ==================================================

$message = $_SESSION["appointment_message"] ?? "";
$messageType = $_SESSION["appointment_message_type"] ?? "success";

unset(
    $_SESSION["appointment_message"],
    $_SESSION["appointment_message_type"]
);


// ==================================================
// 10. HANDLE APPOINTMENT ACTIONS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {

        $_SESSION["appointment_message"] =
            "Invalid request. Please refresh the page.";

        $_SESSION["appointment_message_type"] = "error";

        redirectAppointments();

    }

    $action = $_POST["action"] ?? "";

    // Validate appointment ID only for appointment actions.
    $appointmentId = filter_var(
        $_POST["appointment_id"] ?? "",
        FILTER_VALIDATE_INT
    );

    if (!$appointmentId || $appointmentId < 1) {

        $_SESSION["appointment_message"] = "Invalid appointment ID.";
        $_SESSION["appointment_message_type"] = "error";

        redirectAppointments();

    }

    try {

        $check = $pdo->prepare("
            SELECT id, status
            FROM bhw_appointments
            WHERE id = :id
            LIMIT 1
        ");

        $check->execute([
            ":id" => $appointmentId
        ]);

        $appointment = $check->fetch();

        if (!$appointment) {

            throw new RuntimeException("Appointment not found.");

        }

        $currentStatus = trim((string)$appointment["status"]);

        $updated = false;

        // ------------------------------------------
        // APPROVE
        // ------------------------------------------

        if ($action === "approve") {

            if ($currentStatus !== "Pending") {

                throw new RuntimeException(
                    "Only pending appointments can be approved."
                );

            }

            $stmt = $pdo->prepare("
                UPDATE bhw_appointments
                SET status = 'Approved'
                WHERE id = :id
                AND status = 'Pending'
            ");

            $stmt->execute([
                ":id" => $appointmentId
            ]);

            $updated = $stmt->rowCount() > 0;

            if (!$updated) {
                throw new RuntimeException(
                    "Appointment was not updated."
                );
            }

            $_SESSION["appointment_message"] =
                "Appointment approved successfully.";

        }

        // ------------------------------------------
        // CANCEL
        // ------------------------------------------

        elseif ($action === "cancel") {

            if (
                !in_array(
                    $currentStatus,
                    ["Pending", "Approved"],
                    true
                )
            ) {

                throw new RuntimeException(
                    "This appointment can no longer be cancelled."
                );

            }

            $stmt = $pdo->prepare("
                UPDATE bhw_appointments
                SET status = 'Cancelled'
                WHERE id = :id
                AND status IN ('Pending', 'Approved')
            ");

            $stmt->execute([
                ":id" => $appointmentId
            ]);

            $updated = $stmt->rowCount() > 0;

            if (!$updated) {
                throw new RuntimeException(
                    "Appointment was not updated."
                );
            }

            $_SESSION["appointment_message"] =
                "Appointment cancelled successfully.";

        }

        // ------------------------------------------
        // COMPLETE
        // ------------------------------------------

        elseif ($action === "complete") {

            if ($currentStatus !== "Approved") {

                throw new RuntimeException(
                    "Only approved appointments can be completed."
                );

            }

            $stmt = $pdo->prepare("
                UPDATE bhw_appointments
                SET status = 'Completed'
                WHERE id = :id
                AND status = 'Approved'
            ");

            $stmt->execute([
                ":id" => $appointmentId
            ]);

            $updated = $stmt->rowCount() > 0;

            if (!$updated) {
                throw new RuntimeException(
                    "Appointment was not updated."
                );
            }

            $_SESSION["appointment_message"] =
                "Appointment marked as completed.";

        }

        // ------------------------------------------
        // RESCHEDULE
        // ------------------------------------------

        elseif ($action === "reschedule") {

            if (
                !in_array(
                    $currentStatus,
                    ["Pending", "Approved"],
                    true
                )
            ) {

                throw new RuntimeException(
                    "Only pending or approved appointments can be rescheduled."
                );

            }

            $newDate = trim(
                (string)($_POST["appointment_date"] ?? "")
            );

            $newTime = trim(
                (string)($_POST["appointment_time"] ?? "")
            );

            $dateObject = DateTime::createFromFormat(
                "!Y-m-d",
                $newDate
            );

            $validDate = $dateObject &&
                $dateObject->format("Y-m-d") === $newDate;

            $validTime = preg_match(
                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                $newTime
            );

            if (!$validDate || !$validTime) {

                throw new RuntimeException(
                    "Please enter a valid appointment date and time."
                );

            }

            if ($newDate < date("Y-m-d")) {

                throw new RuntimeException(
                    "Appointment date cannot be in the past."
                );

            }

            $stmt = $pdo->prepare("
                UPDATE bhw_appointments
                SET
                    appointment_date = :appointment_date,
                    appointment_time = :appointment_time
                WHERE id = :id
                AND status IN ('Pending', 'Approved')
            ");

            $stmt->execute([
                ":appointment_date" => $newDate,
                ":appointment_time" => $newTime . ":00",
                ":id" => $appointmentId
            ]);

            $updated = $stmt->rowCount() > 0;

            if (!$updated) {
                throw new RuntimeException(
                    "Appointment schedule was not updated."
                );
            }

            $_SESSION["appointment_message"] =
                "Appointment schedule updated successfully.";

        }

        else {

            throw new RuntimeException("Invalid appointment action.");

        }

        $_SESSION["appointment_message_type"] = "success";

    } catch (RuntimeException $exception) {

        $_SESSION["appointment_message"] = $exception->getMessage();
        $_SESSION["appointment_message_type"] = "error";

    } catch (PDOException $exception) {

        error_log("APPOINTMENT ACTION ERROR: " . $exception->getMessage());

        $_SESSION["appointment_message"] =
            "Unable to process the appointment action.";

        $_SESSION["appointment_message_type"] = "error";

    }

    // Rotate token after successful validation of the submitted token.
    $_SESSION["admin_appointments_csrf"] = bin2hex(random_bytes(32));

    redirectAppointments();

}


// ==================================================
// 11. SEARCH AND FILTER
// ==================================================

$search = trim((string)($_GET["search"] ?? ""));

$statusFilter = trim((string)($_GET["status"] ?? ""));

$allowedStatuses = [
    "Pending",
    "Approved",
    "Completed",
    "Cancelled"
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "";
}


// ==================================================
// 12. APPOINTMENT STATISTICS
// ==================================================

$statistics = [
    "total" => 0,
    "pending" => 0,
    "approved" => 0,
    "completed" => 0,
    "cancelled" => 0
];

try {

    $statsStmt = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM bhw_appointments
    ");

    $result = $statsStmt->fetch();

    if ($result) {

        foreach ($statistics as $key => $value) {

            $statistics[$key] = (int)($result[$key] ?? 0);

        }

    }

} catch (PDOException $exception) {

    error_log("APPOINTMENT STATISTICS ERROR: " . $exception->getMessage());

    $message = "Unable to load appointment statistics.";
    $messageType = "error";

}


// ==================================================
// 13. FETCH APPOINTMENTS
// ==================================================

$appointments = [];

try {

    $sql = "
        SELECT
            a.id,
            a.patient_id,
            a.bhw_id,
            a.appointment_date,
            a.appointment_time,
            a.purpose,
            a.status,
            a.created_at,
            p.full_name,
            p.contact_number,
            p.address
        FROM bhw_appointments a
        LEFT JOIN bhw_patients p
            ON p.id = a.patient_id
        WHERE 1 = 1
    ";

    $params = [];

    if ($search !== "") {

        $sql .= "
            AND (
                p.full_name LIKE :search
                OR CAST(a.id AS CHAR) LIKE :search_id
                OR CAST(a.patient_id AS CHAR) LIKE :patient_search
            )
        ";

        $searchValue = "%" . $search . "%";

        $params[":search"] = $searchValue;
        $params[":search_id"] = $searchValue;
        $params[":patient_search"] = $searchValue;

    }

    if ($statusFilter !== "") {

        $sql .= " AND a.status = :status";

        $params[":status"] = $statusFilter;

    }

    $sql .= "
        ORDER BY
            CASE
                WHEN a.status = 'Pending' THEN 1
                WHEN a.status = 'Approved' THEN 2
                WHEN a.status = 'Completed' THEN 3
                ELSE 4
            END,
            a.appointment_date DESC,
            a.appointment_time DESC
        LIMIT 300
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $appointments = $stmt->fetchAll();

} catch (PDOException $exception) {

    error_log("APPOINTMENT FETCH ERROR: " . $exception->getMessage());

    $message = "Unable to load appointments. Please check the database table structure.";
    $messageType = "error";

}


// ==================================================
// 14. ADMIN PROFILE
// ==================================================

$adminName = $_SESSION["admin_name"] ?? "Administrator";

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">

<title>Appointments | Administrator - Barangay Guindacpan</title>

<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f1f5f9;
    color: #1e293b;
}

button, input, select {
    font: inherit;
}

button {
    cursor: pointer;
}

a {
    text-decoration: none;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 260px;
    background: #0f172a;
    color: white;
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
}

.brand {
    padding: 26px 22px;
    border-bottom: 1px solid #263449;
}

.brand h1 {
    font-size: 19px;
    margin-bottom: 5px;
}

.brand p {
    color: #94a3b8;
    font-size: 12px;
}

.navigation {
    padding: 22px 14px;
    flex: 1;
}

.navigation a {
    display: block;
    padding: 14px 16px;
    color: #cbd5e1;
    border-radius: 9px;
    margin-bottom: 7px;
    font-size: 14px;
}

.navigation a:hover,
.navigation a.active {
    background: #2563eb;
    color: white;
}

.admin-profile {
    padding: 20px;
    border-top: 1px solid #263449;
}

.profile-name {
    font-weight: bold;
    font-size: 14px;
    overflow-wrap: anywhere;
}

.profile-role {
    color: #94a3b8;
    font-size: 12px;
    margin-top: 5px;
}

.logout-button {
    width: 100%;
    border: 0;
    background: #7f1d1d;
    color: white;
    padding: 12px;
    border-radius: 8px;
    margin-top: 18px;
    font-weight: bold;
}

/* MAIN */

.main {
    flex: 1;
    min-width: 0;
}

.topbar {
    background: white;
    border-bottom: 1px solid #e2e8f0;
    padding: 22px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.topbar h2 {
    font-size: 23px;
}

.topbar p {
    color: #64748b;
    font-size: 13px;
    margin-top: 5px;
}

.portal-label {
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
}

.content {
    padding: 30px;
}

.page-heading {
    margin-bottom: 25px;
}

.page-heading h3 {
    font-size: 25px;
}

.page-heading p {
    color: #64748b;
    font-size: 14px;
    margin-top: 8px;
}

/* ALERT */

.alert {
    padding: 15px 18px;
    border-radius: 10px;
    margin-bottom: 22px;
    font-size: 14px;
}

.alert.success {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.alert.error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* STATISTICS */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
}

.stat-card p {
    color: #64748b;
    font-size: 13px;
}

.stat-card h3 {
    font-size: 30px;
    margin-top: 12px;
}

.blue { color: #2563eb; }
.yellow { color: #ca8a04; }
.green { color: #16a34a; }
.red { color: #dc2626; }

/* FILTER */

.filter-card {
    background: white;
    padding: 20px;
    border: 1px solid #e2e8f0;
    border-radius: 13px;
    margin-bottom: 20px;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-form input,
.filter-form select {
    padding: 12px 14px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    min-width: 180px;
    background: white;
}

.filter-form input {
    flex: 1;
}

.filter-form input:focus,
.filter-form select:focus {
    border-color: #2563eb;
}

.filter-button {
    padding: 12px 20px;
    border: 0;
    background: #2563eb;
    color: white;
    border-radius: 8px;
    font-weight: bold;
}

.reset-button {
    display: inline-block;
    padding: 12px 20px;
    border: 1px solid #cbd5e1;
    color: #475569;
    border-radius: 8px;
    background: white;
}

/* TABLE */

.table-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
}

.section-header {
    padding: 22px;
    border-bottom: 1px solid #e2e8f0;
}

.section-header h3 {
    font-size: 18px;
}

.section-header p {
    color: #64748b;
    font-size: 13px;
    margin-top: 6px;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    min-width: 1150px;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}

thead {
    background: #f8fafc;
    color: #475569;
}

th, td {
    padding: 16px 18px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

tbody tr:hover {
    background: #f8fafc;
}

.patient-name {
    font-weight: bold;
    color: #1e293b;
}

.muted {
    color: #64748b;
    font-size: 12px;
    margin-top: 5px;
}

.status {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 30px;
    font-size: 11px;
    font-weight: bold;
}

.status.Pending {
    background: #fef3c7;
    color: #92400e;
}

.status.Approved {
    background: #dbeafe;
    color: #1d4ed8;
}

.status.Completed {
    background: #dcfce7;
    color: #166534;
}

.status.Cancelled {
    background: #fee2e2;
    color: #991b1b;
}

/* ACTIONS */

.action-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-button {
    border: 0;
    padding: 8px 10px;
    border-radius: 6px;
    color: white;
    font-size: 11px;
    font-weight: bold;
}

.approve { background: #2563eb; }
.complete { background: #16a34a; }
.cancel { background: #dc2626; }
.reschedule { background: #7c3aed; }

.empty-row {
    text-align: center;
    padding: 40px;
    color: #64748b;
}

footer {
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    margin-top: 30px;
}

/* MODAL */

.modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 200;
    background: rgba(15, 23, 42, .65);
    align-items: center;
    justify-content: center;
    padding: 18px;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    width: 100%;
    max-width: 430px;
    border-radius: 14px;
    padding: 25px;
    box-shadow: 0 15px 40px rgba(0,0,0,.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-header h3 {
    font-size: 19px;
}

.close-modal {
    border: 0;
    background: transparent;
    font-size: 25px;
    color: #64748b;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    margin-bottom: 7px;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
}

.save-button {
    width: 100%;
    padding: 13px;
    background: #2563eb;
    color: white;
    border: 0;
    border-radius: 8px;
    font-weight: bold;
}

.cancel-modal-button {
    width: 100%;
    padding: 12px;
    background: #f1f5f9;
    color: #475569;
    border: 0;
    border-radius: 8px;
    margin-top: 9px;
}

/* MOBILE */

#menu-toggle {
    display: none;
}

.menu-button,
.overlay,
.close-menu {
    display: none;
}

@media (max-width: 1100px) {

    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }

}

@media (max-width: 767px) {

    .layout {
        display: block;
    }

    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 100;
        width: 270px;
        transform: translateX(-100%);
        transition: transform .25s ease;
    }

    #menu-toggle:checked ~ .layout .sidebar {
        transform: translateX(0);
    }

    .overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 90;
    }

    #menu-toggle:checked ~ .layout .overlay {
        display: block;
    }

    .close-menu {
        display: inline-block;
        color: white;
        float: right;
        font-size: 22px;
        cursor: pointer;
    }

    .menu-button {
        display: inline-block;
        background: #eff6ff;
        color: #1d4ed8;
        border: 0;
        border-radius: 8px;
        padding: 9px 12px;
        font-size: 20px;
        cursor: pointer;
    }

    .topbar {
        padding: 18px;
    }

    .topbar h2 {
        font-size: 19px;
    }

    .content {
        padding: 18px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-card h3 {
        font-size: 25px;
    }

    .portal-label {
        display: none;
    }

    .filter-form {
        flex-direction: column;
    }

    .filter-form input,
    .filter-form select,
    .filter-button,
    .reset-button {
        width: 100%;
    }

}

@media (max-width: 400px) {

    .stats-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>

<body>

<input type="checkbox" id="menu-toggle">

<div class="layout">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">

            <label for="menu-toggle" class="close-menu">×</label>

            <h1>♥ Barangay Health</h1>

            <p>Administrator Portal</p>

        </div>

        <nav class="navigation">

            <a href="dashboard.php">▦ Dashboard</a>
            <a href="bhwAccount.php">♙ BHW Accounts</a>
            <a href="patients.php">♥ Patients</a>
            <a href="appointments.php" class="active">▣ Appointments</a>
            <a href="reports.php">▤ Reports</a>

        </nav>

        <div class="admin-profile">

            <div class="profile-name">
                <?= e($adminName) ?>
            </div>

            <div class="profile-role">
                Administrator
            </div>

            <form method="POST" action="dashboard.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["admin_dashboard_csrf"] ?? "") ?>"
                >

                <input
                    type="hidden"
                    name="action"
                    value="logout"
                >

                <button type="submit" class="logout-button">
                    ↪ Logout
                </button>

            </form>

        </div>

    </aside>


    <!-- MOBILE OVERLAY -->

    <label for="menu-toggle" class="overlay"></label>


    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <div style="display:flex;align-items:center;gap:13px;">

                <label for="menu-toggle" class="menu-button">
                    ☰
                </label>

                <div>

                    <h2>Appointment Management</h2>

                    <p>Barangay Health Management System</p>

                </div>

            </div>

            <div class="portal-label">
                ✚ Administrator Portal
            </div>

        </header>


        <div class="content">

            <?php if ($message !== ""): ?>

                <div class="alert <?= e($messageType) ?>" role="alert">
                    <?= e($message) ?>
                </div>

            <?php endif; ?>


            <!-- HEADING -->

            <section class="page-heading">

                <h3>Appointments</h3>

                <p>
                    Review and manage appointment requests submitted through the BHW system.
                </p>

            </section>


            <!-- STATISTICS -->

            <section class="stats-grid">

                <div class="stat-card">
                    <p>Total Appointments</p>
                    <h3 class="blue"><?= (int)$statistics["total"] ?></h3>
                </div>

                <div class="stat-card">
                    <p>Pending</p>
                    <h3 class="yellow"><?= (int)$statistics["pending"] ?></h3>
                </div>

                <div class="stat-card">
                    <p>Approved</p>
                    <h3 class="blue"><?= (int)$statistics["approved"] ?></h3>
                </div>

                <div class="stat-card">
                    <p>Completed</p>
                    <h3 class="green"><?= (int)$statistics["completed"] ?></h3>
                </div>

                <div class="stat-card">
                    <p>Cancelled</p>
                    <h3 class="red"><?= (int)$statistics["cancelled"] ?></h3>
                </div>

            </section>


            <!-- SEARCH -->

            <section class="filter-card">

                <form method="GET" action="appointments.php" class="filter-form">

                    <input
                        type="search"
                        name="search"
                        placeholder="Search patient name or appointment ID..."
                        value="<?= e($search) ?>"
                    >

                    <select name="status">

                        <option value="">All Statuses</option>

                        <?php foreach ($allowedStatuses as $status): ?>

                            <option
                                value="<?= e($status) ?>"
                                <?= $statusFilter === $status ? "selected" : "" ?>
                            >
                                <?= e($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                    <button type="submit" class="filter-button">
                        Search
                    </button>

                    <a href="appointments.php" class="reset-button">
                        Reset
                    </a>

                </form>

            </section>


            <!-- TABLE -->

            <section class="table-section">

                <div class="section-header">

                    <h3>Appointment Records</h3>

                    <p>
                        Showing up to 300 appointment records.
                    </p>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>BHW ID</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>

                        </thead>

                        <tbody>

                        <?php if (!empty($appointments)): ?>

                            <?php foreach ($appointments as $row): ?>

                                <?php

                                $appointmentDate = (string)$row["appointment_date"];
                                $appointmentTime = (string)$row["appointment_time"];

                                $safeDate = preg_match(
                                    '/^\d{4}-\d{2}-\d{2}$/',
                                    $appointmentDate
                                ) ? $appointmentDate : "";

                                $safeTime = substr($appointmentTime, 0, 5);

                                $displayDate = strtotime($appointmentDate);
                                $displayTime = strtotime($appointmentTime);

                                ?>

                                <tr>

                                    <td>
                                        <strong>#<?= (int)$row["id"] ?></strong>
                                    </td>

                                    <td>

                                        <div class="patient-name">
                                            <?= e($row["full_name"] ?? "Patient not found") ?>
                                        </div>

                                        <div class="muted">
                                            Patient ID: <?= (int)$row["patient_id"] ?>
                                        </div>

                                    </td>

                                    <td>
                                        <?= e($row["contact_number"] ?? "N/A") ?>
                                    </td>

                                    <td>
                                        <?= (int)$row["bhw_id"] ?>
                                    </td>

                                    <td>
                                        <?= $displayDate !== false ? e(date("M d, Y", $displayDate)) : "N/A" ?>
                                    </td>

                                    <td>
                                        <?= $displayTime !== false ? e(date("h:i A", $displayTime)) : "N/A" ?>
                                    </td>

                                    <td>
                                        <?= e($row["purpose"] ?? "Not specified") ?>
                                    </td>

                                    <td>

                                        <span class="status <?= e($row["status"]) ?>">
                                            <?= e($row["status"]) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <div class="action-group">

                                            <?php if ($row["status"] === "Pending"): ?>

                                                <form method="POST" action="appointments.php">

                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="appointment_id" value="<?= (int)$row["id"] ?>">
                                                    <input type="hidden" name="action" value="approve">

                                                    <button type="submit" class="action-button approve">
                                                        Approve
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if ($row["status"] === "Approved"): ?>

                                                <form method="POST" action="appointments.php">

                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="appointment_id" value="<?= (int)$row["id"] ?>">
                                                    <input type="hidden" name="action" value="complete">

                                                    <button type="submit" class="action-button complete">
                                                        Complete
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (in_array($row["status"], ["Pending", "Approved"], true)): ?>

                                                <button
                                                    type="button"
                                                    class="action-button reschedule"
                                                    onclick='openReschedule(
                                                        <?= (int)$row["id"] ?>,
                                                        <?= json_encode($safeDate) ?>,
                                                        <?= json_encode($safeTime) ?>
                                                    )'
                                                >
                                                    Reschedule
                                                </button>


                                                <form
                                                    method="POST"
                                                    action="appointments.php"
                                                    onsubmit="return confirm('Are you sure you want to cancel this appointment?');"
                                                >

                                                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                    <input type="hidden" name="appointment_id" value="<?= (int)$row["id"] ?>">
                                                    <input type="hidden" name="action" value="cancel">

                                                    <button type="submit" class="action-button cancel">
                                                        Cancel
                                                    </button>

                                                </form>

                                            <?php endif; ?>


                                            <?php if (in_array($row["status"], ["Completed", "Cancelled"], true)): ?>

                                                <span class="muted">
                                                    No available actions
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="9" class="empty-row">
                                    No appointment records found.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <footer>

                © <?= date("Y") ?> Barangay Guindacpan Health Management System.
                All rights reserved.

            </footer>

        </div>

    </main>

</div>


<!-- RESCHEDULE MODAL -->

<div class="modal" id="rescheduleModal">

    <div class="modal-content">

        <div class="modal-header">

            <h3>Reschedule Appointment</h3>

            <button
                type="button"
                class="close-modal"
                onclick="closeReschedule()"
            >
                ×
            </button>

        </div>

        <form method="POST" action="appointments.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="reschedule"
            >

            <input
                type="hidden"
                name="appointment_id"
                id="rescheduleAppointmentId"
            >

            <div class="form-group">

                <label for="rescheduleDate">
                    New Appointment Date
                </label>

                <input
                    type="date"
                    name="appointment_date"
                    id="rescheduleDate"
                    min="<?= date("Y-m-d") ?>"
                    required
                >

            </div>

            <div class="form-group">

                <label for="rescheduleTime">
                    New Appointment Time
                </label>

                <input
                    type="time"
                    name="appointment_time"
                    id="rescheduleTime"
                    required
                >

            </div>

            <button type="submit" class="save-button">
                Save New Schedule
            </button>

            <button
                type="button"
                class="cancel-modal-button"
                onclick="closeReschedule()"
            >
                Cancel
            </button>

        </form>

    </div>

</div>


<script>

function openReschedule(id, date, time) {

    document.getElementById("rescheduleAppointmentId").value = id;

    document.getElementById("rescheduleDate").value = date;

    document.getElementById("rescheduleTime").value = time;

    document.getElementById("rescheduleModal").classList.add("show");

}

function closeReschedule() {

    document.getElementById("rescheduleModal").classList.remove("show");

}

document.getElementById("rescheduleModal").addEventListener("click", function(event) {

    if (event.target === this) {
        closeReschedule();
    }

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {
        closeReschedule();
        document.getElementById("menu-toggle").checked = false;
    }

});

</script>

</body>
</html>
