
<?php
// =============================================
// ADMINISTRATOR - BHW ACCOUNT MANAGEMENT
// =============================================

session_name("BARANGAY_ADMIN_SESSION");

ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
ini_set("session.use_trans_sid", "0");

session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
    "httponly" => true,
    "samesite" => "Lax"
]);

session_start();

mysqli_report(MYSQLI_REPORT_OFF);

// VERIFY ADMIN LOGIN
if (!isset($_SESSION["admin_id"])) {
    header("Location: adminlogIn.php");
    exit;
}

// DATABASE CONNECTION
require_once __DIR__ . "/../BHW/database.php";

if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) {
    http_response_code(500);
    exit("Database connection error.");
}

// CSRF TOKEN
if (empty($_SESSION["bhw_management_csrf"])) {
    $_SESSION["bhw_management_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "success";

// =============================================
// HANDLE ACCOUNT ACTIONS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";
    $action = $_POST["action"] ?? "";
    $bhwId = filter_var($_POST["bhw_id"] ?? "", FILTER_VALIDATE_INT);

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["bhw_management_csrf"], $token)
    ) {

        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } elseif (!$bhwId || $bhwId < 1) {

        $message = "Invalid BHW account.";
        $messageType = "error";

    } else {

        switch ($action) {

            // APPROVE ACCOUNT
            case "approve":

                $sql = "
                    UPDATE bhw_accounts
                    SET status = 'Active'
                    WHERE id = ?
                ";

                $message = "BHW account approved successfully.";
                break;

            // DEACTIVATE ACCOUNT
            case "deactivate":

                $sql = "
                    UPDATE bhw_accounts
                    SET status = 'Inactive'
                    WHERE id = ?
                ";

                $message = "BHW account deactivated successfully.";
                break;

            // DELETE ACCOUNT
            case "delete":

                $sql = "
                    DELETE FROM bhw_accounts
                    WHERE id = ?
                ";

                $message = "BHW account deleted successfully.";
                break;

            default:

                $sql = "";
                $message = "Invalid account action.";
                $messageType = "error";
        }

        if ($sql !== "") {

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {

                error_log("BHW Management Prepare Error: " . mysqli_error($conn));

                $message = "Unable to process account action.";
                $messageType = "error";

            } else {

                mysqli_stmt_bind_param($stmt, "i", $bhwId);

                if (!mysqli_stmt_execute($stmt)) {

                    error_log("BHW Management Execute Error: " . mysqli_stmt_error($stmt));

                    $message = "Unable to update BHW account.";
                    $messageType = "error";

                } elseif (mysqli_stmt_affected_rows($stmt) < 1) {

                    $message = "No account was changed. Check its current status.";
                    $messageType = "error";

                }

                mysqli_stmt_close($stmt);
            }
        }

        $_SESSION["bhw_management_csrf"] = bin2hex(random_bytes(32));
    }
}

// =============================================
// FETCH BHW ACCOUNTS
// =============================================

$accounts = [];

$sql = "
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
";

$result = mysqli_query($conn, $sql);

if (!$result) {

    error_log("BHW Fetch Error: " . mysqli_error($conn));

    http_response_code(500);
    exit("Unable to load BHW accounts. Please check the bhw_accounts table.");
}

while ($row = mysqli_fetch_assoc($result)) {
    $accounts[] = $row;
}

$totalAccounts = count($accounts);
$activeAccounts = 0;
$inactiveAccounts = 0;

foreach ($accounts as $account) {

    if (strcasecmp(trim($account["status"]), "Active") === 0) {
        $activeAccounts++;
    } else {
        $inactiveAccounts++;
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Account Management | Administrator</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-slate-100 min-h-screen">

    <!-- NAVIGATION -->
    <nav class="bg-blue-900 text-white px-6 py-4 shadow-lg">

        <div class="max-w-7xl mx-auto flex justify-between items-center">

            <div>
                <h1 class="text-xl font-bold">
                    Barangay Health System
                </h1>

                <p class="text-xs text-blue-200">
                    Administrator Portal
                </p>
            </div>

            <a href="dashboard.php"
               class="bg-white/10 hover:bg-white/20 px-4 py-2 rounded-lg text-sm">
                <i data-lucide="layout-dashboard" class="inline w-4 h-4"></i>
                Dashboard
            </a>

        </div>

    </nav>

    <main class="max-w-7xl mx-auto p-5 sm:p-8">

        <!-- HEADER -->
        <div class="mb-8">

            <h2 class="text-3xl font-bold text-gray-900">
                BHW Account Management
            </h2>

            <p class="text-gray-500 mt-2">
                Review, approve, and manage Barangay Health Worker accounts.
            </p>

        </div>

        <!-- MESSAGE -->
        <?php if ($message !== ""): ?>

            <div class="mb-6 p-4 rounded-xl border
                <?php echo $messageType === "success"
                    ? "bg-green-50 border-green-200 text-green-700"
                    : "bg-red-50 border-red-200 text-red-700"; ?>">

                <?php echo e($message); ?>

            </div>

        <?php endif; ?>

        <!-- STATISTICS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 mb-8">

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm">Total BHW Accounts</p>
                <h3 class="text-3xl font-bold text-gray-900 mt-2">
                    <?php echo $totalAccounts; ?>
                </h3>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm">Active Accounts</p>
                <h3 class="text-3xl font-bold text-green-600 mt-2">
                    <?php echo $activeAccounts; ?>
                </h3>
            </div>

            <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                <p class="text-gray-500 text-sm">Inactive / Pending Accounts</p>
                <h3 class="text-3xl font-bold text-orange-500 mt-2">
                    <?php echo $inactiveAccounts; ?>
                </h3>
            </div>

        </div>

        <!-- ACCOUNT TABLE -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

            <div class="p-6 border-b border-gray-100">

                <h3 class="text-xl font-bold text-gray-900">
                    Registered BHW Accounts
                </h3>

                <p class="text-sm text-gray-500 mt-1">
                    Manage registered Barangay Health Workers.
                </p>

            </div>

            <div class="overflow-x-auto">

                <table class="w-full text-sm text-left">

                    <thead class="bg-slate-50 text-gray-600 uppercase text-xs">

                        <tr>
                            <th class="px-5 py-4">ID</th>
                            <th class="px-5 py-4">Full Name</th>
                            <th class="px-5 py-4">Email</th>
                            <th class="px-5 py-4">Contact</th>
                            <th class="px-5 py-4">Position</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Registered</th>
                            <th class="px-5 py-4">Actions</th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-gray-100">

                        <?php if (empty($accounts)): ?>

                            <tr>
                                <td colspan="8" class="text-center py-12 text-gray-500">
                                    No BHW accounts registered yet.
                                </td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($accounts as $account): ?>

                                <tr class="hover:bg-slate-50">

                                    <td class="px-5 py-4 font-semibold">
                                        <?php echo e($account["id"]); ?>
                                    </td>

                                    <td class="px-5 py-4 font-semibold text-gray-900">
                                        <?php echo e($account["full_name"]); ?>
                                    </td>

                                    <td class="px-5 py-4">
                                        <?php echo e($account["email"]); ?>
                                    </td>

                                    <td class="px-5 py-4">
                                        <?php echo e($account["contact_number"]); ?>
                                    </td>

                                    <td class="px-5 py-4">
                                        <?php echo e($account["position"]); ?>
                                    </td>

                                    <td class="px-5 py-4">

                                        <?php if (strcasecmp(trim($account["status"]), "Active") === 0): ?>

                                            <span class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs font-semibold">
                                                Active
                                            </span>

                                        <?php else: ?>

                                            <span class="px-3 py-1 rounded-full bg-orange-100 text-orange-700 text-xs font-semibold">
                                                Inactive
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="px-5 py-4 text-gray-500">
                                        <?php echo e($account["created_at"]); ?>
                                    </td>

                                    <td class="px-5 py-4">

                                        <div class="flex items-center gap-2">

                                            <?php if (strcasecmp(trim($account["status"]), "Active") !== 0): ?>

                                                <form method="POST" onsubmit="return confirm('Approve this BHW account?');">

                                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["bhw_management_csrf"]); ?>">
                                                    <input type="hidden" name="bhw_id" value="<?php echo e($account["id"]); ?>">
                                                    <input type="hidden" name="action" value="approve">

                                                    <button type="submit"
                                                        class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg text-xs">
                                                        Approve
                                                    </button>

                                                </form>

                                            <?php else: ?>

                                                <form method="POST" onsubmit="return confirm('Deactivate this BHW account?');">

                                                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["bhw_management_csrf"]); ?>">
                                                    <input type="hidden" name="bhw_id" value="<?php echo e($account["id"]); ?>">
                                                    <input type="hidden" name="action" value="deactivate">

                                                    <button type="submit"
                                                        class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded-lg text-xs">
                                                        Deactivate
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                            <form method="POST" onsubmit="return confirm('Permanently delete this BHW account? This cannot be undone.');">

                                                <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION["bhw_management_csrf"]); ?>">
                                                <input type="hidden" name="bhw_id" value="<?php echo e($account["id"]); ?>">
                                                <input type="hidden" name="action" value="delete">

                                                <button type="submit"
                                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-2 rounded-lg text-xs">
                                                    Delete
                                                </button>

                                            </form>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </main>

  
</body>
 <script src="adme.js"></script>
</html>
