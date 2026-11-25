
<?php
// ==================================================
// BARANGAY GUINDACPAN
// ADMINISTRATOR - BHW ACCOUNT MANAGEMENT
// PDO VERSION
//
// Database: barangay_health_db
// Table: bhw_accounts
// Session: BARANGAY_ADMIN_SESSION
// ==================================================


// ==================================================
// SESSION SECURITY
// ==================================================

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


// ==================================================
// SECURITY HEADERS
// ==================================================

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");


// ==================================================
// ADMINISTRATOR AUTHENTICATION
// ==================================================

if (empty($_SESSION["admin_id"])) {

    header("Location: adminlogIn.php");
    exit;

}


// ==================================================
// DATABASE CONNECTION
// Database: barangay_health_db
// ==================================================

try {

    $adminPDO = new PDO(
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

    error_log("BHW Management Database Error: " . $e->getMessage());

    http_response_code(500);
    exit("Database connection error. Please check the administrator database.");

}


// ==================================================
// HELPER FUNCTION
// ==================================================

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


// ==================================================
// CSRF TOKEN
// ==================================================

if (empty($_SESSION["bhw_management_csrf"])) {

    $_SESSION["bhw_management_csrf"] = bin2hex(random_bytes(32));

}


// ==================================================
// FLASH MESSAGE
// ==================================================

$message = $_SESSION["bhw_management_message"] ?? "";
$messageType = $_SESSION["bhw_management_message_type"] ?? "success";

unset(
    $_SESSION["bhw_management_message"],
    $_SESSION["bhw_management_message_type"]
);


// ==================================================
// HANDLE ACCOUNT ACTIONS
// ==================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $tokenInput = $_POST["csrf_token"] ?? "";
    $actionInput = $_POST["action"] ?? "";
    $bhwIdInput = $_POST["bhw_id"] ?? "";

    $token = is_string($tokenInput) ? $tokenInput : "";
    $action = is_string($actionInput) ? $actionInput : "";

    $bhwId = filter_var(
        $bhwIdInput,
        FILTER_VALIDATE_INT
    );

    try {

        // ------------------------------------------
        // VALIDATE CSRF
        // ------------------------------------------

        if (
            !isset($_SESSION["bhw_management_csrf"]) ||
            !is_string($_SESSION["bhw_management_csrf"]) ||
            !hash_equals($_SESSION["bhw_management_csrf"], $token)
        ) {

            throw new RuntimeException(
                "Invalid request. Please refresh the page."
            );

        }


        // ------------------------------------------
        // VALIDATE BHW ID
        // ------------------------------------------

        if (!$bhwId || $bhwId < 1) {

            throw new RuntimeException(
                "Invalid BHW account."
            );

        }


        // ------------------------------------------
        // FIND BHW ACCOUNT
        // ------------------------------------------

        $checkStmt = $adminPDO->prepare("
            SELECT id, full_name, status
            FROM bhw_accounts
            WHERE id = :id
            LIMIT 1
        ");

        $checkStmt->execute([
            ":id" => $bhwId
        ]);

        $bhw = $checkStmt->fetch();

        if (!$bhw) {

            throw new RuntimeException(
                "BHW account not found."
            );

        }


        // ------------------------------------------
        // APPROVE ACCOUNT
        // ------------------------------------------

        if ($action === "approve") {

            $updateStmt = $adminPDO->prepare("
                UPDATE bhw_accounts
                SET status = 'Active'
                WHERE id = :id
            ");

            $updateStmt->execute([
                ":id" => $bhwId
            ]);

            $_SESSION["bhw_management_message"] =
                "BHW account approved successfully.";

            $_SESSION["bhw_management_message_type"] =
                "success";

        }


        // ------------------------------------------
        // DEACTIVATE ACCOUNT
        // ------------------------------------------

        elseif ($action === "deactivate") {

            $updateStmt = $adminPDO->prepare("
                UPDATE bhw_accounts
                SET status = 'Inactive'
                WHERE id = :id
            ");

            $updateStmt->execute([
                ":id" => $bhwId
            ]);

            $_SESSION["bhw_management_message"] =
                "BHW account deactivated successfully.";

            $_SESSION["bhw_management_message_type"] =
                "success";

        }


        // ------------------------------------------
        // DELETE ACCOUNT
        // ------------------------------------------

        elseif ($action === "delete") {

            $deleteStmt = $adminPDO->prepare("
                DELETE FROM bhw_accounts
                WHERE id = :id
            ");

            $deleteStmt->execute([
                ":id" => $bhwId
            ]);

            $_SESSION["bhw_management_message"] =
                "BHW account deleted successfully.";

            $_SESSION["bhw_management_message_type"] =
                "success";

        }


        // ------------------------------------------
        // INVALID ACTION
        // ------------------------------------------

        else {

            throw new RuntimeException(
                "Invalid account action."
            );

        }

    } catch (RuntimeException $e) {

        $_SESSION["bhw_management_message"] = $e->getMessage();

        $_SESSION["bhw_management_message_type"] = "error";

    } catch (PDOException $e) {

        error_log("BHW Management Action Error: " . $e->getMessage());

        $_SESSION["bhw_management_message"] =
            "Unable to process the account action. Please check the database.";

        $_SESSION["bhw_management_message_type"] = "error";

    } catch (Throwable $e) {

        error_log("BHW Management System Error: " . $e->getMessage());

        $_SESSION["bhw_management_message"] =
            "An unexpected error occurred.";

        $_SESSION["bhw_management_message_type"] = "error";

    }


    // ------------------------------------------
    // ROTATE CSRF TOKEN
    // ------------------------------------------

    $_SESSION["bhw_management_csrf"] = bin2hex(random_bytes(32));


    // ------------------------------------------
    // PREVENT FORM RESUBMISSION
    // ------------------------------------------

    header("Location: " . $_SERVER["PHP_SELF"]);
    exit;

}


// ==================================================
// FETCH BHW ACCOUNTS
// ==================================================

try {

    $stmt = $adminPDO->query("
        SELECT
            id,
            full_name,
            email,
            contact_number,
            position,
            status,
            created_at
        FROM bhw_accounts
        ORDER BY id DESC
    ");

    $accounts = $stmt->fetchAll();

} catch (PDOException $e) {

    error_log("BHW Account Fetch Error: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to load BHW accounts. Please check the bhw_accounts table.");

}


// ==================================================
// ACCOUNT STATISTICS
// ==================================================

$totalAccounts = count($accounts);
$activeAccounts = 0;
$inactiveAccounts = 0;

foreach ($accounts as $account) {

    if (
        strcasecmp(
            trim((string)$account["status"]),
            "Active"
        ) === 0
    ) {

        $activeAccounts++;

    } else {

        $inactiveAccounts++;

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>BHW Account Management | Administrator</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-slate-100 min-h-screen">


<!-- ==================================================
     NAVIGATION
================================================== -->

<nav class="bg-blue-950 text-white px-5 sm:px-8 py-4 shadow-lg">

    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">

        <div>

            <h1 class="text-lg sm:text-xl font-bold">
                Barangay Health System
            </h1>

            <p class="text-xs text-blue-200">
                Administrator Portal
            </p>

        </div>

        <a
            href="dashboard.php"
            class="inline-flex items-center gap-2 bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm transition"
        >

            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>

            Dashboard

        </a>

    </div>

</nav>


<!-- ==================================================
     MAIN CONTENT
================================================== -->

<main class="max-w-7xl mx-auto p-4 sm:p-8">


    <!-- PAGE HEADER -->

    <div class="mb-8">

        <div class="flex items-center gap-3 mb-3">

            <div class="bg-blue-100 text-blue-700 p-3 rounded-xl">

                <i data-lucide="users" class="w-7 h-7"></i>

            </div>

            <div>

                <h2 class="text-2xl sm:text-3xl font-bold text-gray-900">
                    BHW Account Management
                </h2>

                <p class="text-gray-500 text-sm mt-1">
                    Review, approve, and manage Barangay Health Worker accounts.
                </p>

            </div>

        </div>

    </div>


    <!-- FLASH MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="mb-6 p-4 rounded-xl border flex items-start gap-3
            <?= $messageType === "success"
                ? "bg-green-50 border-green-200 text-green-700"
                : "bg-red-50 border-red-200 text-red-700"; ?>">

            <i
                data-lucide="<?= $messageType === "success" ? "check-circle" : "alert-circle"; ?>"
                class="w-5 h-5 mt-0.5 shrink-0"
            ></i>

            <p class="text-sm font-medium">
                <?= e($message); ?>
            </p>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         STATISTICS
    ================================================== -->

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">


        <!-- TOTAL -->

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-gray-500 text-sm">
                        Total BHW Accounts
                    </p>

                    <h3 class="text-3xl font-bold text-gray-900 mt-2">
                        <?= $totalAccounts; ?>
                    </h3>

                </div>

                <div class="bg-blue-100 text-blue-700 p-3 rounded-xl">

                    <i data-lucide="users" class="w-6 h-6"></i>

                </div>

            </div>

        </div>


        <!-- ACTIVE -->

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-gray-500 text-sm">
                        Active Accounts
                    </p>

                    <h3 class="text-3xl font-bold text-green-600 mt-2">
                        <?= $activeAccounts; ?>
                    </h3>

                </div>

                <div class="bg-green-100 text-green-700 p-3 rounded-xl">

                    <i data-lucide="user-check" class="w-6 h-6"></i>

                </div>

            </div>

        </div>


        <!-- INACTIVE -->

        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">

            <div class="flex items-center justify-between">

                <div>

                    <p class="text-gray-500 text-sm">
                        Inactive / Pending Accounts
                    </p>

                    <h3 class="text-3xl font-bold text-orange-500 mt-2">
                        <?= $inactiveAccounts; ?>
                    </h3>

                </div>

                <div class="bg-orange-100 text-orange-600 p-3 rounded-xl">

                    <i data-lucide="user-x" class="w-6 h-6"></i>

                </div>

            </div>

        </div>

    </div>


    <!-- ==================================================
         ACCOUNT TABLE
    ================================================== -->

    <section class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">


        <!-- TABLE HEADER -->

        <div class="p-5 sm:p-6 border-b border-gray-100">

            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">

                <div>

                    <h3 class="text-xl font-bold text-gray-900">
                        Registered BHW Accounts
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        Manage registered Barangay Health Workers.
                    </p>

                </div>


                <!-- SEARCH AND FILTER -->

                <div class="flex flex-col sm:flex-row gap-3">

                    <div class="relative">

                        <i
                            data-lucide="search"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
                        ></i>

                        <input
                            type="search"
                            id="searchInput"
                            placeholder="Search BHW..."
                            class="w-full sm:w-64 pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500"
                        >

                    </div>


                    <select
                        id="statusFilter"
                        class="px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                    >

                        <option value="all">All Status</option>

                        <option value="active">Active</option>

                        <option value="inactive">Inactive / Pending</option>

                    </select>

                </div>

            </div>

        </div>


        <!-- TABLE -->

        <div class="overflow-x-auto">

            <table class="w-full text-sm text-left">

                <thead class="bg-slate-50 text-gray-600 uppercase text-xs">

                    <tr>

                        <th class="px-5 py-4 whitespace-nowrap">ID</th>

                        <th class="px-5 py-4 whitespace-nowrap">Full Name</th>

                        <th class="px-5 py-4 whitespace-nowrap">Email</th>

                        <th class="px-5 py-4 whitespace-nowrap">Contact</th>

                        <th class="px-5 py-4 whitespace-nowrap">Position</th>

                        <th class="px-5 py-4 whitespace-nowrap">Status</th>

                        <th class="px-5 py-4 whitespace-nowrap">Registered</th>

                        <th class="px-5 py-4 whitespace-nowrap">Actions</th>

                    </tr>

                </thead>


                <tbody id="accountTable" class="divide-y divide-gray-100">


                    <?php if (empty($accounts)): ?>

                        <tr>

                            <td colspan="8" class="text-center py-14 text-gray-500">

                                <i data-lucide="users" class="w-10 h-10 mx-auto mb-3 text-gray-300"></i>

                                <p class="font-medium">
                                    No BHW accounts registered yet.
                                </p>

                            </td>

                        </tr>

                    <?php else: ?>


                        <?php foreach ($accounts as $account): ?>

                            <?php

                                $isActive = strcasecmp(
                                    trim((string)$account["status"]),
                                    "Active"
                                ) === 0;

                            ?>


                            <tr
                                class="account-row hover:bg-slate-50 transition"
                                data-status="<?= $isActive ? "active" : "inactive"; ?>"
                            >


                                <!-- ID -->

                                <td class="px-5 py-4 font-semibold text-gray-700 whitespace-nowrap">

                                    #<?= e($account["id"]); ?>

                                </td>


                                <!-- FULL NAME -->

                                <td class="px-5 py-4 whitespace-nowrap">

                                    <div class="font-semibold text-gray-900">

                                        <?= e($account["full_name"]); ?>

                                    </div>

                                </td>


                                <!-- EMAIL -->

                                <td class="px-5 py-4 text-gray-600 whitespace-nowrap">

                                    <?= e($account["email"]); ?>

                                </td>


                                <!-- CONTACT -->

                                <td class="px-5 py-4 text-gray-600 whitespace-nowrap">

                                    <?= e($account["contact_number"]); ?>

                                </td>


                                <!-- POSITION -->

                                <td class="px-5 py-4 text-gray-600 whitespace-nowrap">

                                    <?= e($account["position"]); ?>

                                </td>


                                <!-- STATUS -->

                                <td class="px-5 py-4 whitespace-nowrap">

                                    <?php if ($isActive): ?>

                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">

                                            <span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>

                                            Active

                                        </span>

                                    <?php else: ?>

                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold">

                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>

                                            <?= e($account["status"]); ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- REGISTERED -->

                                <td class="px-5 py-4 text-gray-500 whitespace-nowrap">

                                    <?= e($account["created_at"]); ?>

                                </td>


                                <!-- ACTIONS -->

                                <td class="px-5 py-4">

                                    <div class="flex items-center gap-2">


                                        <?php if (!$isActive): ?>

                                            <!-- APPROVE -->

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION["bhw_management_csrf"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="bhw_id"
                                                    value="<?= e($account["id"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="approve"
                                                >

                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition"
                                                >

                                                    <i data-lucide="check" class="w-3.5 h-3.5"></i>

                                                    Approve

                                                </button>

                                            </form>

                                        <?php else: ?>

                                            <!-- DEACTIVATE -->

                                            <form method="POST">

                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= e($_SESSION["bhw_management_csrf"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="bhw_id"
                                                    value="<?= e($account["id"]); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="action"
                                                    value="deactivate"
                                                >

                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-lg text-xs font-semibold transition"
                                                >

                                                    <i data-lucide="ban" class="w-3.5 h-3.5"></i>

                                                    Deactivate

                                                </button>

                                            </form>

                                        <?php endif; ?>


                                        <!-- DELETE -->

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to permanently delete this BHW account? This action cannot be undone.');"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION["bhw_management_csrf"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="bhw_id"
                                                value="<?= e($account["id"]); ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="delete"
                                            >

                                            <button
                                                type="submit"
                                                class="inline-flex items-center gap-1 bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs font-semibold transition"
                                            >

                                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>

                                                Delete

                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        <!-- NO SEARCH RESULTS -->

                        <tr id="noResults" class="hidden">

                            <td colspan="8" class="text-center py-12 text-gray-500">

                                No matching BHW accounts found.

                            </td>

                        </tr>


                    <?php endif; ?>


                </tbody>

            </table>

        </div>


        <!-- TABLE FOOTER -->

        <div class="px-5 py-4 border-t border-gray-100 bg-slate-50">

            <p class="text-xs text-gray-500">

                Showing <span id="visibleCount"><?= $totalAccounts; ?></span>
                of <?= $totalAccounts; ?> BHW accounts.

            </p>

        </div>

    </section>


    <!-- FOOTER -->

    <footer class="text-center text-xs text-gray-400 mt-8">

        Barangay Guindacpan Administrator Portal

        <br>

        BHW Account Management

    </footer>


</main>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>

    // Initialize Lucide icons
    lucide.createIcons();


    // Search and status filter
    const searchInput = document.getElementById("searchInput");

    const statusFilter = document.getElementById("statusFilter");

    const accountRows = document.querySelectorAll(".account-row");

    const noResults = document.getElementById("noResults");

    const visibleCount = document.getElementById("visibleCount");


    function filterAccounts() {

        const searchValue = searchInput.value.toLowerCase().trim();

        const selectedStatus = statusFilter.value;

        let visible = 0;


        accountRows.forEach(function(row) {

            const rowText = row.textContent.toLowerCase();

            const rowStatus = row.dataset.status;

            const matchesSearch = rowText.includes(searchValue);

            const matchesStatus =
                selectedStatus === "all" ||
                rowStatus === selectedStatus;


            if (matchesSearch && matchesStatus) {

                row.classList.remove("hidden");

                visible++;

            } else {

                row.classList.add("hidden");

            }

        });


        visibleCount.textContent = visible;


        if (noResults) {

            noResults.classList.toggle(
                "hidden",
                visible !== 0
            );

        }

    }


    searchInput.addEventListener("input", filterAccounts);

    statusFilter.addEventListener("change", filterAccounts);


</script>

</body>

</html>
