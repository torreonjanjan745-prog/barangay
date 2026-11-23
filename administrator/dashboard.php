
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR DASHBOARD
// COMPLETE PDO VERSION
//
// Database: barangay_health_db
// Table: admin_account
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
// 3. VERIFY ADMIN SESSION
// ==================================================

if (
    empty($_SESSION["admin_id"]) ||
    !is_numeric($_SESSION["admin_id"])
) {

    header("Location: login.php");
    exit;

}


// ==================================================
// 4. DATABASE CONNECTION
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

} catch (PDOException $e) {

    error_log("ADMIN DASHBOARD DATABASE ERROR: " . $e->getMessage());

    http_response_code(500);

    exit("Database connection error. Please contact the administrator.");

}


// ==================================================
// 5. VERIFY ADMIN ACCOUNT STILL EXISTS AND ACTIVE
// ==================================================

try {

    $adminCheck = $pdo->prepare("
        SELECT id, full_name, status
        FROM admin_account
        WHERE id = :id
        LIMIT 1
    ");

    $adminCheck->execute([
        ":id" => (int) $_SESSION["admin_id"]
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
                "httponly" => $params["httponly"],
                "samesite" => "Lax"
            ]);

        }

        session_destroy();

        header("Location: login.php");
        exit;

    }

    $_SESSION["admin_name"] = $admin["full_name"];

} catch (PDOException $e) {

    error_log("ADMIN ACCOUNT VALIDATION ERROR: " . $e->getMessage());

    http_response_code(500);

    exit("Unable to verify administrator account.");

}


// ==================================================
// 6. SESSION TIMEOUT
// ==================================================

$sessionTimeout = 1800;

if (
    isset($_SESSION["admin_last_activity"]) &&
    time() - (int)$_SESSION["admin_last_activity"] > $sessionTimeout
) {

    $_SESSION = [];

    session_destroy();

    header("Location: login.php");
    exit;

}

$_SESSION["admin_last_activity"] = time();


// ==================================================
// 7. CSRF TOKEN
// ==================================================

if (
    empty($_SESSION["admin_dashboard_csrf"]) ||
    !is_string($_SESSION["admin_dashboard_csrf"])
) {

    $_SESSION["admin_dashboard_csrf"] = bin2hex(random_bytes(32));

}

$csrfToken = $_SESSION["admin_dashboard_csrf"];


// ==================================================
// 8. INITIAL VARIABLES
// ==================================================

$message = "";
$messageType = "success";


// ==================================================
// 9. HANDLE ADMIN ACTIONS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {

        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        // ------------------------------------------
        // LOGOUT
        // ------------------------------------------

        if ($action === "logout") {

            $_SESSION = [];

            if (ini_get("session.use_cookies")) {

                $params = session_get_cookie_params();

                setcookie(session_name(), "", [
                    "expires" => time() - 42000,
                    "path" => $params["path"],
                    "domain" => $params["domain"],
                    "secure" => $params["secure"],
                    "httponly" => $params["httponly"],
                    "samesite" => "Lax"
                ]);

            }

            session_destroy();

            header("Location: login.php");
            exit;

        }

        // ------------------------------------------
        // APPROVE BHW ACCOUNT
        // ------------------------------------------

        elseif ($action === "approve") {

            $bhwId = filter_var(
                $_POST["bhw_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            if (!$bhwId || $bhwId < 1) {

                $message = "Invalid BHW account.";
                $messageType = "error";

            } else {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE bhw_accounts
                        SET status = 'Active'
                        WHERE id = :id
                        AND status = 'Inactive'
                    ");

                    $stmt->execute([
                        ":id" => $bhwId
                    ]);

                    if ($stmt->rowCount() > 0) {

                        $message = "BHW account approved successfully.";

                    } else {

                        $message = "Account not found or already approved.";
                        $messageType = "error";

                    }

                } catch (PDOException $e) {

                    error_log("BHW APPROVAL ERROR: " . $e->getMessage());

                    $message = "Unable to approve the BHW account.";
                    $messageType = "error";

                }

            }

        } else {

            $message = "Invalid action.";
            $messageType = "error";

        }

    }

    // Rotate CSRF token after POST
    $_SESSION["admin_dashboard_csrf"] = bin2hex(random_bytes(32));

    $csrfToken = $_SESSION["admin_dashboard_csrf"];

}


// ==================================================
// 10. DATABASE COUNT FUNCTION
// ==================================================

function getCount(PDO $pdo, string $sql): int {

    try {

        return (int)$pdo->query($sql)->fetchColumn();

    } catch (PDOException $e) {

        error_log("DASHBOARD COUNT ERROR: " . $e->getMessage());

        return 0;

    }

}


// ==================================================
// 11. DASHBOARD STATISTICS
// ==================================================

$totalBHW = getCount(
    $pdo,
    "SELECT COUNT(*) FROM bhw_accounts"
);

$pendingBHW = getCount(
    $pdo,
    "SELECT COUNT(*) FROM bhw_accounts WHERE status = 'Inactive'"
);

$activeBHW = getCount(
    $pdo,
    "SELECT COUNT(*) FROM bhw_accounts WHERE status = 'Active'"
);

$totalPatients = getCount(
    $pdo,
    "SELECT COUNT(*) FROM bhw_patients"
);

$totalAppointments = getCount(
    $pdo,
    "SELECT COUNT(*) FROM bhw_appointments"
);


// ==================================================
// 12. RECENT BHW ACCOUNTS
// ==================================================

$bhwAccounts = [];

try {

    $stmt = $pdo->query("
        SELECT
            id,
            full_name,
            email,
            contact_number,
            position,
            status
        FROM bhw_accounts
        ORDER BY id DESC
        LIMIT 10
    ");

    $bhwAccounts = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log("BHW ACCOUNT FETCH ERROR: " . $e->getMessage());

}


// ==================================================
// 13. ADMIN PROFILE
// ==================================================

$adminName = $_SESSION["admin_name"] ?? "Administrator";


// ==================================================
// 14. ESCAPE OUTPUT
// ==================================================

function e($value): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Administrator Dashboard | Barangay Guindacpan</title>

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
        }

        button {
            font-family: inherit;
            cursor: pointer;
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
            transition: .2s;
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

        .logout-button:hover {
            background: #991b1b;
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

        .welcome {
            margin-bottom: 26px;
        }

        .welcome h3 {
            font-size: 24px;
            overflow-wrap: anywhere;
        }

        .welcome p {
            color: #64748b;
            margin-top: 8px;
            font-size: 14px;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            background: white;
            padding: 23px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,.03);
        }

        .stat-card p {
            color: #64748b;
            font-size: 13px;
        }

        .stat-card h3 {
            font-size: 32px;
            margin-top: 12px;
        }

        .stat-card .icon {
            display: inline-block;
            margin-top: 13px;
            font-size: 22px;
        }

        .blue { color: #2563eb; }
        .yellow { color: #ca8a04; }
        .green { color: #16a34a; }
        .purple { color: #9333ea; }
        .orange { color: #ea580c; }

        /* APPOINTMENTS */

        .appointment-card {
            background: white;
            padding: 23px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            margin-bottom: 25px;
        }

        .appointment-card p {
            color: #64748b;
            font-size: 13px;
        }

        .appointment-card h3 {
            font-size: 30px;
            margin-top: 10px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .section-header h3 {
            font-size: 18px;
        }

        .section-header p {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }

        .view-link {
            color: #2563eb;
            font-weight: bold;
            font-size: 13px;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 750px;
            text-align: left;
            font-size: 13px;
        }

        thead {
            background: #f8fafc;
            color: #475569;
        }

        th, td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: bold;
        }

        .status.active {
            background: #dcfce7;
            color: #166534;
        }

        .status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .approve-button {
            background: #2563eb;
            color: white;
            border: 0;
            border-radius: 7px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: bold;
        }

        .approve-button:hover {
            background: #1d4ed8;
        }

        .approved-label {
            color: #16a34a;
            font-weight: bold;
            font-size: 12px;
        }

        .empty-row {
            text-align: center;
            padding: 35px;
            color: #64748b;
        }

        footer {
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
            margin-top: 30px;
        }

        /* MOBILE MENU */

        #menu-toggle {
            display: none;
        }

        .menu-button,
        .overlay,
        .close-menu {
            display: none;
        }

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
                grid-template-columns: 1fr;
                gap: 13px;
            }

            .section-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .portal-label {
                display: none;
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

            <a href="dashboard.php" class="active">▦ Dashboard</a>

            <a href="bhwAccount.php">♙ BHW Accounts</a>

            <a href="patients.php">♥ Patients</a>

            <a href="appointments.php">▣ Appointments</a>

            <a href="reports.php">▤ Reports</a>

        </nav>

        <div class="admin-profile">

            <div class="profile-name">
                <?= e($adminName) ?>
            </div>

            <div class="profile-role">
                Administrator
            </div>

            <form method="POST" action="">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
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


    <!-- MAIN CONTENT -->

    <main class="main">

        <!-- TOPBAR -->

        <header class="topbar">

            <div style="display:flex;align-items:center;gap:13px;">

                <label for="menu-toggle" class="menu-button">
                    ☰
                </label>

                <div>

                    <h2>Admin Dashboard</h2>

                    <p>Barangay Health Management System</p>

                </div>

            </div>

            <div class="portal-label">
                ✚ Administrator Portal
            </div>

        </header>


        <div class="content">

            <!-- ALERT -->

            <?php if ($message !== ""): ?>

                <div class="alert <?= e($messageType) ?>" role="alert">

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <!-- WELCOME -->

            <section class="welcome">

                <h3>Welcome, <?= e($adminName) ?>!</h3>

                <p>
                    Here's an overview of your barangay health system.
                </p>

            </section>


            <!-- STATISTICS -->

            <section class="stats-grid">

                <div class="stat-card">

                    <p>Total BHW Accounts</p>

                    <h3><?= $totalBHW ?></h3>

                    <span class="icon blue">♙</span>

                </div>


                <div class="stat-card">

                    <p>Pending Approvals</p>

                    <h3><?= $pendingBHW ?></h3>

                    <span class="icon yellow">◷</span>

                </div>


                <div class="stat-card">

                    <p>Active BHW Accounts</p>

                    <h3><?= $activeBHW ?></h3>

                    <span class="icon green">✓</span>

                </div>


                <div class="stat-card">

                    <p>Total Patients</p>

                    <h3><?= $totalPatients ?></h3>

                    <span class="icon purple">♥</span>

                </div>

            </section>


            <!-- APPOINTMENTS -->

            <section class="appointment-card">

                <p>Total Appointments</p>

                <h3><?= $totalAppointments ?></h3>

                <span class="icon orange">▣</span>

            </section>


            <!-- BHW ACCOUNT TABLE -->

            <section class="table-section">

                <div class="section-header">

                    <div>

                        <h3>BHW Account Management</h3>

                        <p>
                            Review and approve newly registered BHW accounts.
                        </p>

                    </div>

                    <a href="bhwAccount.php" class="view-link">
                        View All Accounts →
                    </a>

                </div>


                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Position</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>


                        <tbody>

                        <?php if (!empty($bhwAccounts)): ?>

                            <?php foreach ($bhwAccounts as $bhw): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= e($bhw["full_name"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($bhw["email"]) ?>
                                    </td>

                                    <td>
                                        <?= e($bhw["contact_number"]) ?>
                                    </td>

                                    <td>
                                        <?= e($bhw["position"]) ?>
                                    </td>

                                    <td>

                                        <?php if (
                                            strcasecmp(trim((string)$bhw["status"]), "Active") === 0
                                        ): ?>

                                            <span class="status active">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="status pending">
                                                Pending
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            strcasecmp(trim((string)$bhw["status"]), "Inactive") === 0
                                        ): ?>

                                            <form method="POST" action="">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($csrfToken) ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="bhw_id"
                                                    value="<?= (int)$bhw["id"] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="approve-button"
                                                >
                                                    Approve
                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <span class="approved-label">
                                                Approved
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6" class="empty-row">
                                    No BHW accounts registered yet.
                                </td>

                            </tr>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>


            <!-- FOOTER -->

            <footer>

                © <?= date("Y") ?> Barangay Guindacpan Health Management System.

                All rights reserved.

            </footer>

        </div>

    </main>

</div>

</body>
</html>
