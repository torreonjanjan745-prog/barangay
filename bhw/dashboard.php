
<?php
// ==================================================
// BHW DASHBOARD
// Database: barangay_health_db
// Connection: PDO
// Profile Photo: Shared with profile.php
// ==================================================

// ==================================================
// SESSION SECURITY
// ==================================================

$isHttps = (
    isset($_SERVER["HTTPS"]) &&
    $_SERVER["HTTPS"] !== "off"
);

if (session_status() === PHP_SESSION_NONE) {
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
}

// ==================================================
// SECURITY HEADERS
// ==================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

// ==================================================
// CHECK LOGIN
// ==================================================

if (empty($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

// ==================================================
// SESSION TIMEOUT
// ==================================================

$timeout = 1800;

if (
    isset($_SESSION["last_activity"]) &&
    (time() - (int) $_SESSION["last_activity"]) > $timeout
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
            "samesite" => "Strict"
        ]);
    }

    session_destroy();

    header("Location: logIn.php?timeout=1");
    exit;
}

$_SESSION["last_activity"] = time();

// ==================================================
// DATABASE CONNECTION
// ==================================================

require_once __DIR__ . "/database.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    error_log("BHW Dashboard: PDO connection is missing.");
    http_response_code(500);
    exit("BHW database connection is not available.");
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log("BHW Dashboard PDO Error: " . $e->getMessage());
    http_response_code(500);
    exit("BHW database connection failed.");
}

// ==================================================
// HELPER FUNCTIONS
// ==================================================

function e($value) {
    return htmlspecialchars(
        (string) ($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

function allowedTables() {
    return [
        "bhw_patients",
        "bhw_appointments",
        "bhw_health_records",
        "bhw_immunization",
        "bhw_prenatal"
    ];
}

function tableExists($pdo, $table) {
    if (!in_array($table, allowedTables(), true)) {
        return false;
    }

    try {
        $stmt = $pdo->query(
            "SHOW TABLES LIKE " . $pdo->quote($table)
        );

        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        error_log("BHW Table Error: " . $e->getMessage());
        return false;
    }
}

function getColumns($pdo, $table) {
    if (!tableExists($pdo, $table)) {
        return [];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    } catch (PDOException $e) {
        error_log("BHW Column Error: " . $e->getMessage());
        return [];
    }
}

function getCount($pdo, $table) {
    if (
        !in_array($table, allowedTables(), true) ||
        !tableExists($pdo, $table)
    ) {
        return 0;
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("BHW Count Error: " . $e->getMessage());
        return 0;
    }
}

// ==================================================
// USER INFORMATION AND PROFILE PHOTO
// ==================================================

$bhwId = (int) $_SESSION["bhw_id"];

$bhwName = $_SESSION["bhw_name"] ?? "Barangay Health Worker";
$bhwEmail = $_SESSION["bhw_email"] ?? "";

$profilePhoto = "";
$profilePhotoUrl = "";

$bhwInitial = strtoupper(substr(trim($bhwName), 0, 1));

if ($bhwInitial === "") {
    $bhwInitial = "B";
}

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            profile_photo
        FROM bhw_accounts
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ":id" => $bhwId
    ]);

    $bhwAccount = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bhwAccount) {
        $_SESSION = [];
        session_destroy();

        header("Location: logIn.php?account=invalid");
        exit;
    }

    $bhwName = $bhwAccount["full_name"] ?? $bhwName;
    $bhwEmail = $bhwAccount["email"] ?? $bhwEmail;

    $profilePhoto = trim(
        (string) ($bhwAccount["profile_photo"] ?? "")
    );

    $_SESSION["bhw_name"] = $bhwName;
    $_SESSION["bhw_email"] = $bhwEmail;

    // Update initial using current database name.
    $bhwInitial = strtoupper(substr(trim($bhwName), 0, 1));

    if ($bhwInitial === "") {
        $bhwInitial = "B";
    }

    // =============================================
    // PROFILE PHOTO DIRECTORY
    // IMPORTANT:
    // This must match profile.php:
    // bhw/uploads/profiles/
    // =============================================

    $uploadDirectory = __DIR__ . "/uploads/profiles/";

    // profile.php saves filename only, for example:
    // a8f31c9d.jpg

    if (
        $profilePhoto !== "" &&
        basename($profilePhoto) === $profilePhoto
    ) {
        $photoFile = $uploadDirectory . $profilePhoto;

        if (
            is_file($photoFile) &&
            is_readable($photoFile)
        ) {
            $profilePhotoUrl =
                "uploads/profiles/" .
                rawurlencode($profilePhoto) .
                "?v=" . filemtime($photoFile);
        }
    }

} catch (PDOException $e) {
    error_log("BHW Dashboard Account Error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load BHW account information.");
}

// ==================================================
// DATE INFORMATION
// ==================================================

$today = date("F d, Y");
$year = date("Y");

// ==================================================
// CSRF TOKEN
// ==================================================

if (empty($_SESSION["bhw_logout_csrf"])) {
    $_SESSION["bhw_logout_csrf"] = bin2hex(random_bytes(32));
}

$logoutToken = $_SESSION["bhw_logout_csrf"];

// ==================================================
// DASHBOARD STATISTICS
// ==================================================

$totalPatients = getCount($pdo, "bhw_patients");
$totalAppointments = getCount($pdo, "bhw_appointments");
$totalHealthRecords = getCount($pdo, "bhw_health_records");
$totalImmunization = getCount($pdo, "bhw_immunization");
$totalPrenatal = getCount($pdo, "bhw_prenatal");

// ==================================================
// RECENT PATIENTS
// ==================================================

$recentPatients = [];

$patientColumns = getColumns($pdo, "bhw_patients");

$patientNameColumn = null;

foreach (["full_name", "patient_name", "name"] as $column) {
    if (in_array($column, $patientColumns, true)) {
        $patientNameColumn = $column;
        break;
    }
}

if (
    $patientNameColumn !== null &&
    in_array("id", $patientColumns, true)
) {
    try {
        $sql = "
            SELECT
                `id`,
                `$patientNameColumn` AS patient_name
        ";

        if (in_array("status", $patientColumns, true)) {
            $sql .= ", `status`";
        }

        $sql .= "
            FROM `bhw_patients`
            ORDER BY `id` DESC
            LIMIT 5
        ";

        $stmt = $pdo->query($sql);
        $recentPatients = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("BHW Recent Patients Error: " . $e->getMessage());
    }
}

// ==================================================
// RECENT APPOINTMENTS
// ==================================================

$recentAppointments = [];

$appointmentColumns = getColumns($pdo, "bhw_appointments");

if (in_array("id", $appointmentColumns, true)) {

    $appointmentDateColumn = null;
    $appointmentNameColumn = null;

    foreach (
        ["appointment_date", "date", "created_at"] as $column
    ) {
        if (in_array($column, $appointmentColumns, true)) {
            $appointmentDateColumn = $column;
            break;
        }
    }

    foreach (
        ["patient_name", "full_name", "name"] as $column
    ) {
        if (in_array($column, $appointmentColumns, true)) {
            $appointmentNameColumn = $column;
            break;
        }
    }

    try {
        $sql = "SELECT `id`";

        if ($appointmentNameColumn !== null) {
            $sql .= ", `$appointmentNameColumn` AS patient_name";
        }

        if ($appointmentDateColumn !== null) {
            $sql .= ", `$appointmentDateColumn` AS appointment_date";
        }

        if (in_array("status", $appointmentColumns, true)) {
            $sql .= ", `status`";
        }

        $orderColumn = $appointmentDateColumn ?? "id";

        $sql .= "
            FROM `bhw_appointments`
            ORDER BY `$orderColumn` DESC
            LIMIT 5
        ";

        $stmt = $pdo->query($sql);
        $recentAppointments = $stmt->fetchAll();

    } catch (PDOException $e) {
        error_log("BHW Recent Appointments Error: " . $e->getMessage());
    }
}

// ==================================================
// STAT CARDS
// ==================================================

$stats = [
    [
        "title" => "Total Patients",
        "count" => $totalPatients,
        "icon" => "P",
        "color" => "blue",
        "link" => "patients.php"
    ],
    [
        "title" => "Total Appointments",
        "count" => $totalAppointments,
        "icon" => "A",
        "color" => "purple",
        "link" => "appointments.php"
    ],
    [
        "title" => "Health Records",
        "count" => $totalHealthRecords,
        "icon" => "H",
        "color" => "green",
        "link" => "health_records.php"
    ],
    [
        "title" => "Immunization Records",
        "count" => $totalImmunization,
        "icon" => "I",
        "color" => "orange",
        "link" => "immunization.php"
    ],
    [
        "title" => "Prenatal Records",
        "count" => $totalPrenatal,
        "icon" => "Pr",
        "color" => "pink",
        "link" => "prenatal.php"
    ]
];

?>

<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>BHW Dashboard | Barangay Health Center</title>

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

a {
    text-decoration: none;
    color: inherit;
}

button {
    font-family: inherit;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 260px;
    min-width: 260px;
    background: #0f172a;
    color: white;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    transition: transform .3s ease;
    z-index: 1000;
}

.brand {
    padding: 27px 22px;
    border-bottom: 1px solid #263449;
}

.brand h1 {
    font-size: 20px;
    margin-bottom: 6px;
}

.brand p {
    font-size: 12px;
    color: #94a3b8;
}

/* SIDEBAR PROFILE */

.sidebar-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px;
    border-bottom: 1px solid #263449;
}

.sidebar-profile-avatar {
    width: 45px;
    height: 45px;
    min-width: 45px;
    border-radius: 50%;
    overflow: hidden;
    background: #2563eb;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    border: 2px solid rgba(255,255,255,.25);
}

.sidebar-profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.sidebar-profile-name {
    color: white;
    font-size: 13px;
    font-weight: bold;
    overflow-wrap: anywhere;
}

.sidebar-profile-role {
    color: #94a3b8;
    font-size: 11px;
    margin-top: 4px;
}

/* NAVIGATION */

.nav {
    padding: 22px 14px;
    flex: 1;
}

.nav-label {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: bold;
    letter-spacing: 1px;
    padding: 15px 12px 10px;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 13px 15px;
    margin-bottom: 5px;
    border-radius: 9px;
    color: #cbd5e1;
    font-size: 14px;
    transition: .2s;
}

.nav-link:hover {
    background: #1e293b;
    color: white;
}

.nav-link.active {
    background: #2563eb;
    color: white;
}

.nav-icon {
    width: 25px;
    height: 25px;
    border-radius: 7px;
    background: rgba(255,255,255,.12);
    display: inline-flex;
    justify-content: center;
    align-items: center;
    font-size: 12px;
    font-weight: bold;
}

.sidebar-footer {
    padding: 18px;
    border-top: 1px solid #263449;
}

.logout-button {
    display: block;
    width: 100%;
    padding: 13px;
    background: transparent;
    color: #f87171;
    border: 1px solid #334155;
    border-radius: 9px;
    cursor: pointer;
    font-size: 14px;
    text-align: left;
}

.logout-button:hover {
    background: rgba(239,68,68,.1);
}

/* MOBILE OVERLAY */

.sidebar-overlay {
    display: none;
}

/* MAIN CONTENT */

.main {
    flex: 1;
    min-width: 0;
}

.header {
    background: white;
    padding: 22px 30px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    min-width: 0;
}

.header h2 {
    font-size: 23px;
    margin-bottom: 5px;
}

.header p {
    color: #64748b;
    font-size: 13px;
}

/* HEADER USER */

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 43px;
    height: 43px;
    min-width: 43px;
    border-radius: 50%;
    background: #dbeafe;
    color: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
    overflow: hidden;
    border: 2px solid #dbeafe;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.user-name {
    font-weight: bold;
    font-size: 14px;
    color: #1e293b !important;
}

.user-role {
    color: #64748b;
    font-size: 12px;
    margin-top: 4px;
}

/* HAMBURGER */

.menu-button {
    display: none;
    width: 42px;
    height: 42px;
    border: 0;
    border-radius: 9px;
    background: #eff6ff;
    color: #1d4ed8;
    cursor: pointer;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.menu-button:hover {
    background: #dbeafe;
}

.menu-button svg {
    width: 24px;
    height: 24px;
}

/* CONTENT */

.content {
    padding: 30px;
}

/* WELCOME */

.welcome {
    background: linear-gradient(120deg, #1e40af, #2563eb, #3b82f6);
    color: white;
    border-radius: 17px;
    padding: 30px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 25px;
    box-shadow: 0 8px 20px rgba(37,99,235,.15);
}

.welcome-label {
    color: #dbeafe;
    font-size: 13px;
    margin-bottom: 10px;
}

.welcome h1 {
    font-size: 29px;
    margin-bottom: 12px;
}

.welcome-description {
    color: #dbeafe;
    font-size: 14px;
    line-height: 1.7;
    max-width: 600px;
}

.date-card {
    background: rgba(255,255,255,.15);
    border: 1px solid rgba(255,255,255,.15);
    border-radius: 12px;
    padding: 18px;
    min-width: 175px;
}

.date-card p:first-child {
    color: #dbeafe;
    font-size: 12px;
}

.date-card strong {
    display: block;
    font-size: 18px;
    margin-top: 9px;
}

/* SECTION HEADINGS */

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.section-heading h2 {
    font-size: 19px;
    margin-bottom: 6px;
}

.section-heading p {
    font-size: 13px;
    color: #64748b;
}

.database-status {
    color: #15803d;
    font-size: 12px;
    font-weight: bold;
}

/* STATISTICS */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 35px;
}

.stat-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 23px;
    box-shadow: 0 2px 5px rgba(15,23,42,.03);
    transition: .2s;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 22px rgba(15,23,42,.08);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 10px;
}

.stat-title {
    font-size: 13px;
    color: #64748b;
}

.stat-count {
    font-size: 31px;
    font-weight: bold;
    margin-top: 13px;
}

.stat-icon {
    width: 43px;
    height: 43px;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-weight: bold;
    flex-shrink: 0;
}

.blue {
    background: #dbeafe;
    color: #2563eb;
}

.purple {
    background: #f3e8ff;
    color: #9333ea;
}

.green {
    background: #dcfce7;
    color: #16a34a;
}

.orange {
    background: #ffedd5;
    color: #ea580c;
}

.pink {
    background: #fce7f3;
    color: #db2777;
}

.view-link {
    display: inline-block;
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
    margin-top: 19px;
}

/* QUICK ACTIONS */

.quick-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 35px;
}

.quick-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 23px;
    transition: .2s;
}

.quick-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 8px 20px rgba(15,23,42,.07);
    transform: translateY(-3px);
}

.quick-icon {
    width: 43px;
    height: 43px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 17px;
}

.quick-card h3 {
    font-size: 16px;
    margin-bottom: 10px;
}

.quick-card p {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
}

.quick-card .quick-link {
    display: inline-block;
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
    margin-top: 17px;
}

/* TABLES */

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    margin-bottom: 25px;
    overflow: hidden;
}

.panel-header {
    padding: 20px 23px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.panel-header h2 {
    font-size: 16px;
    margin-bottom: 6px;
}

.panel-header p {
    font-size: 12px;
    color: #64748b;
}

.panel-link {
    color: #2563eb;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
    font-size: 13px;
}

thead {
    background: #f8fafc;
    color: #64748b;
}

th, td {
    padding: 16px 22px;
    border-bottom: 1px solid #f1f5f9;
    white-space: nowrap;
}

th {
    font-size: 12px;
    font-weight: bold;
}

tbody tr:hover {
    background: #f8fafc;
}

.badge {
    display: inline-block;
    padding: 6px 11px;
    border-radius: 20px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 11px;
    font-weight: bold;
}

.no-status {
    color: #94a3b8;
}

.empty-row {
    text-align: center;
    color: #94a3b8;
    padding: 35px 15px;
}

/* SYSTEM INFORMATION */

.system-info {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 15px;
    padding: 24px;
    margin-top: 28px;
}

.system-info h2 {
    font-size: 17px;
    margin-bottom: 7px;
}

.system-info-description {
    color: #64748b;
    font-size: 13px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 22px;
    border-top: 1px solid #f1f5f9;
    margin-top: 22px;
    padding-top: 20px;
}

.info-label {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 7px;
}

.info-value {
    font-size: 14px;
    font-weight: bold;
}

.connected {
    color: #16a34a;
}

/* FOOTER */

.footer {
    text-align: center;
    color: #94a3b8;
    font-size: 12px;
    padding: 28px 10px 10px;
}

/* TABLET */

@media (max-width: 1000px) {
    .stats-grid,
    .quick-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* MOBILE */

@media (max-width: 767px) {
    .layout {
        display: block;
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        min-width: 0;
        min-height: 100vh;
        height: 100dvh;
        overflow-y: auto;
        transform: translateX(-100%);
        visibility: hidden;
        box-shadow: 10px 0 30px rgba(0,0,0,.2);
    }

    .sidebar.mobile-open {
        transform: translateX(0);
        visibility: visible;
    }

    .sidebar-overlay {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,.55);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease, visibility .3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .menu-button {
        display: inline-flex;
    }

    .header {
        padding: 15px 18px;
    }

    .header h2 {
        font-size: 19px;
    }

    .header-left {
        gap: 10px;
    }

    .content {
        padding: 18px;
    }

    .welcome {
        flex-direction: column;
        align-items: flex-start;
        padding: 23px;
    }

    .welcome h1 {
        font-size: 24px;
    }

    .date-card {
        width: 100%;
    }

    .section-heading {
        align-items: flex-start;
        flex-direction: column;
    }

    .info-grid {
        grid-template-columns: 1fr;
    }

    body.menu-open {
        overflow: hidden;
    }
}

/* SMALL MOBILE */

@media (max-width: 480px) {
    .header {
        padding: 13px;
        gap: 8px;
    }

    .header h2 {
        font-size: 16px;
    }

    .header p {
        font-size: 11px;
    }

    .user-info {
        gap: 7px;
    }

    .user-name {
        font-size: 11px;
    }

    .user-role {
        font-size: 10px;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        min-width: 35px;
    }

    .menu-button {
        width: 38px;
        height: 38px;
    }

    .content {
        padding: 13px;
    }

    .stats-grid,
    .quick-grid {
        grid-template-columns: 1fr;
    }

    .welcome {
        padding: 20px;
    }

    .welcome h1 {
        font-size: 22px;
    }

    .stat-card,
    .quick-card {
        padding: 19px;
    }

    .panel-header {
        padding: 17px;
    }

    th, td {
        padding: 13px 15px;
    }
}

/* REDUCED MOTION */

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        transition-duration: .01ms !important;
        animation-duration: .01ms !important;
    }
}

</style>
</head>

<body>

<div class="layout">

    <!-- MOBILE OVERLAY -->

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- SIDEBAR -->

    <aside class="sidebar" id="sidebar">

        <div class="brand">
            <h1>♥ Barangay Health</h1>
            <p>BHW Management Portal</p>
        </div>

        <!-- SIDEBAR PROFILE -->

        <div class="sidebar-profile">

            <div class="sidebar-profile-avatar">

                <?php if ($profilePhotoUrl !== ""): ?>

                    <img
                        src="<?= e($profilePhotoUrl) ?>"
                        alt="BHW Profile Picture"
                    >

                <?php else: ?>

                    <span><?= e($bhwInitial) ?></span>

                <?php endif; ?>

            </div>

            <div>
                <p class="sidebar-profile-name">
                    <?= e($bhwName) ?>
                </p>

                <p class="sidebar-profile-role">
                    Barangay Health Worker
                </p>
            </div>

        </div>

        <!-- NAVIGATION -->

        <nav class="nav">

            <p class="nav-label">Main Menu</p>

            <a href="dashboard.php" class="nav-link active">
                <span class="nav-icon">D</span>
                Dashboard
            </a>

            <a href="patients.php" class="nav-link">
                <span class="nav-icon">P</span>
                Patients
            </a>

            <a href="appointments.php" class="nav-link">
                <span class="nav-icon">A</span>
                Appointments
            </a>

            <p class="nav-label">Health Services</p>

            <a href="health_records.php" class="nav-link">
                <span class="nav-icon">H</span>
                Health Records
            </a>

            <a href="immunization.php" class="nav-link">
                <span class="nav-icon">I</span>
                Immunization
            </a>

            <a href="prenatal.php" class="nav-link">
                <span class="nav-icon">Pr</span>
                Prenatal Records
            </a>

            <p class="nav-label">Account</p>

            <a href="profile.php" class="nav-link">
                <span class="nav-icon">U</span>
                My Profile
            </a>

            <a href="notifications.php" class="nav-link">
                <span class="nav-icon">N</span>
                Notifications
            </a>

        </nav>

        <!-- LOGOUT -->

        <div class="sidebar-footer">

            <form action="logout.php" method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($logoutToken) ?>"
                >

                <button type="submit" class="logout-button">
                    ↪ Logout
                </button>

            </form>

        </div>

    </aside>

    <!-- MAIN -->

    <main class="main">

        <!-- HEADER -->

        <header class="header">

            <div class="header-left">

                <button
                    type="button"
                    class="menu-button"
                    id="menuButton"
                    aria-label="Open navigation menu"
                    aria-expanded="false"
                    aria-controls="sidebar"
                >

                    <svg
                        id="menuIcon"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>

                </button>

                <div>
                    <h2>BHW Dashboard</h2>
                    <p>Barangay Health Center Management</p>
                </div>

            </div>

            <!-- HEADER USER -->

            <div class="user-info">

                <div>
                    <p class="user-name">
                        <?= e($bhwName) ?>
                    </p>

                    <p class="user-role">
                        Barangay Health Worker
                    </p>
                </div>

                <div class="user-avatar">

                    <?php if ($profilePhotoUrl !== ""): ?>

                        <img
                            src="<?= e($profilePhotoUrl) ?>"
                            alt="BHW Profile Picture"
                        >

                    <?php else: ?>

                        <span><?= e($bhwInitial) ?></span>

                    <?php endif; ?>

                </div>

            </div>

        </header>

        <!-- PAGE CONTENT -->

        <section class="content">

            <!-- WELCOME -->

            <div class="welcome">

                <div>
                    <p class="welcome-label">
                        Welcome back, Barangay Health Worker!
                    </p>

                    <h1>
                        Hello, <?= e($bhwName) ?>!
                    </h1>

                    <p class="welcome-description">
                        Manage patient information, appointments,
                        and community health services in one place.
                    </p>
                </div>

                <div class="date-card">
                    <p>Today's Date</p>
                    <strong><?= e($today) ?></strong>
                </div>

            </div>

            <!-- STATISTICS -->

            <div class="section-heading">

                <div>
                    <h2>Health Center Overview</h2>
                    <p>Summary of registered health center records</p>
                </div>

                <span class="database-status">
                    ● Database Connected
                </span>

            </div>

            <div class="stats-grid">

                <?php foreach ($stats as $stat): ?>

                    <div class="stat-card">

                        <div class="stat-top">

                            <div>
                                <p class="stat-title">
                                    <?= e($stat["title"]) ?>
                                </p>

                                <h3 class="stat-count">
                                    <?= number_format($stat["count"]) ?>
                                </h3>
                            </div>

                            <div class="stat-icon <?= e($stat["color"]) ?>">
                                <?= e($stat["icon"]) ?>
                            </div>

                        </div>

                        <a
                            href="<?= e($stat["link"]) ?>"
                            class="view-link"
                        >
                            View Records →
                        </a>

                    </div>

                <?php endforeach; ?>

            </div>

            <!-- QUICK ACTIONS -->

            <div class="section-heading">

                <div>
                    <h2>Quick Actions</h2>
                    <p>Quick access to your health center modules</p>
                </div>

            </div>

            <div class="quick-grid">

                <a href="patients.php" class="quick-card">

                    <div class="quick-icon blue">P</div>

                    <h3>Manage Patients</h3>

                    <p>
                        View and manage registered patient information.
                    </p>

                    <span class="quick-link">
                        Open Patients →
                    </span>

                </a>

                <a href="appointments.php" class="quick-card">

                    <div class="quick-icon purple">A</div>

                    <h3>Manage Appointments</h3>

                    <p>
                        Review and manage patient appointments.
                    </p>

                    <span class="quick-link">
                        Open Appointments →
                    </span>

                </a>

                <a href="health_records.php" class="quick-card">

                    <div class="quick-icon green">H</div>

                    <h3>Health Records</h3>

                    <p>
                        Access and manage patient health records.
                    </p>

                    <span class="quick-link">
                        Open Health Records →
                    </span>

                </a>

            </div>

            <!-- RECENT PATIENTS -->

            <div class="panel">

                <div class="panel-header">

                    <div>
                        <h2>Recent Patients</h2>
                        <p>Latest registered patient records</p>
                    </div>

                    <a href="patients.php" class="panel-link">
                        View All →
                    </a>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Patient Name</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!empty($recentPatients)): ?>

                                <?php foreach ($recentPatients as $patient): ?>

                                    <tr>

                                        <td>
                                            #<?= (int) $patient["id"] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($patient["patient_name"]) ?>
                                            </strong>
                                        </td>

                                        <td>

                                            <?php if (!empty($patient["status"])): ?>

                                                <span class="badge">
                                                    <?= e(ucfirst($patient["status"])) ?>
                                                </span>

                                            <?php else: ?>

                                                <span class="no-status">
                                                    No status
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="3" class="empty-row">
                                        No patient records found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- RECENT APPOINTMENTS -->

            <div class="panel">

                <div class="panel-header">

                    <div>
                        <h2>Recent Appointments</h2>
                        <p>Latest appointment records</p>
                    </div>

                    <a href="appointments.php" class="panel-link">
                        View All →
                    </a>

                </div>

                <div class="table-wrapper">

                    <table>

                        <thead>
                            <tr>
                                <th>Appointment ID</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (!empty($recentAppointments)): ?>

                                <?php foreach ($recentAppointments as $appointment): ?>

                                    <tr>

                                        <td>
                                            #<?= (int) $appointment["id"] ?>
                                        </td>

                                        <td>
                                            <strong>
                                                <?= e($appointment["patient_name"] ?? "Not specified") ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= e($appointment["appointment_date"] ?? "Not specified") ?>
                                        </td>

                                        <td>

                                            <?php if (!empty($appointment["status"])): ?>

                                                <span class="badge">
                                                    <?= e(ucfirst($appointment["status"])) ?>
                                                </span>

                                            <?php else: ?>

                                                <span class="no-status">
                                                    No status
                                                </span>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            <?php else: ?>

                                <tr>
                                    <td colspan="4" class="empty-row">
                                        No appointment records found.
                                    </td>
                                </tr>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

            <!-- SYSTEM INFORMATION -->

            <div class="system-info">

                <h2>BHW System Information</h2>

                <p class="system-info-description">
                    Barangay Health Center Management System
                </p>

                <div class="info-grid">

                    <div>
                        <p class="info-label">Database</p>
                        <p class="info-value">barangay_health_db</p>
                    </div>

                    <div>
                        <p class="info-label">Account Type</p>
                        <p class="info-value">Barangay Health Worker</p>
                    </div>

                    <div>
                        <p class="info-label">Account ID</p>
                        <p class="info-value">
                            #<?= (int) $bhwId ?>
                        </p>
                    </div>

                    <div>
                        <p class="info-label">System Status</p>
                        <p class="info-value connected">
                            ● Connected
                        </p>
                    </div>

                </div>

            </div>

            <!-- FOOTER -->

            <footer class="footer">
                © <?= e($year) ?>
                Barangay Health Center Management System.
                All Rights Reserved.
            </footer>

        </section>

    </main>

</div>

<!-- HAMBURGER MENU SCRIPT -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const menuButton = document.getElementById("menuButton");
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebarOverlay");
    const menuIcon = document.getElementById("menuIcon");

    if (!menuButton || !sidebar || !overlay || !menuIcon) {
        return;
    }

    function openMenu() {

        sidebar.classList.add("mobile-open");
        overlay.classList.add("active");
        document.body.classList.add("menu-open");

        menuButton.setAttribute("aria-expanded", "true");
        menuButton.setAttribute("aria-label", "Close navigation menu");

        menuIcon.innerHTML = `
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M6 18L18 6M6 6l12 12"
            />
        `;
    }

    function closeMenu() {

        sidebar.classList.remove("mobile-open");
        overlay.classList.remove("active");
        document.body.classList.remove("menu-open");

        menuButton.setAttribute("aria-expanded", "false");
        menuButton.setAttribute("aria-label", "Open navigation menu");

        menuIcon.innerHTML = `
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M4 6h16M4 12h16M4 18h16"
            />
        `;
    }

    function toggleMenu() {

        if (sidebar.classList.contains("mobile-open")) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    menuButton.addEventListener("click", toggleMenu);
    overlay.addEventListener("click", closeMenu);

    sidebar.querySelectorAll(".nav-link").forEach(function (link) {
        link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") {
            closeMenu();
        }
    });

    window.addEventListener("resize", function () {
        if (window.innerWidth > 767) {
            closeMenu();
        }
    });

});

</script>

</body>
</html>
