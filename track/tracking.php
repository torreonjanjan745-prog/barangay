
<?php
// =====================================================
// BARANGAY GUINDACPAN
// RESIDENT REQUEST TRACKING
// Database: barangay_db
// Connection: Existing db.php (PDO)
// =====================================================

// =====================================================
// SESSION SECURITY
// =====================================================

$isHttps = !empty($_SERVER["HTTPS"]) &&
           $_SERVER["HTTPS"] !== "off";

if (session_status() === PHP_SESSION_NONE) {

    session_name("BARANGAY_RESIDENT_SESSION");

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

// =====================================================
// AUTHENTICATION
// =====================================================

if (!isset($_SESSION["resident_id"])) {
    header("Location: login.php");
    exit;
}

$resident_id = (int) $_SESSION["resident_id"];

// =====================================================
// DATABASE CONNECTION
// =====================================================

require_once "../db.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Database connection is unavailable.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// =====================================================
// ESCAPE OUTPUT
// =====================================================

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// =====================================================
// GET RESIDENT INFORMATION
// =====================================================

$stmt = $pdo->prepare("
    SELECT id, full_name, username
    FROM residents
    WHERE id = :resident_id
    LIMIT 1
");

$stmt->execute([
    ":resident_id" => $resident_id
]);

$resident = $stmt->fetch();

if (!$resident) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// =====================================================
// TRACK REQUEST
// =====================================================

$request = null;
$error = "";

$tracking_code = trim($_GET["tracking_code"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tracking_code = trim($_POST["tracking_code"] ?? "");
}

if ($tracking_code !== "") {

    if (strlen($tracking_code) > 30) {

        $error = "Invalid tracking number.";

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    tracking_code,
                    request_type,
                    purpose,
                    status,
                    admin_remarks,
                    submitted_at,
                    updated_at
                FROM requests
                WHERE tracking_code = :tracking_code
                AND resident_id = :resident_id
                LIMIT 1
            ");

            $stmt->execute([
                ":tracking_code" => $tracking_code,
                ":resident_id" => $resident_id
            ]);

            $request = $stmt->fetch();

            if (!$request) {
                $error = "Request not found. Please check your tracking number.";
            }

        } catch (PDOException $e) {

            error_log("Request Tracking Error: " . $e->getMessage());

            $error = "Unable to retrieve your request. Please try again.";

        }
    }
}

// =====================================================
// STATUS COLORS
// =====================================================

$statusColors = [
    "Pending" => "bg-yellow-100 text-yellow-800",
    "Processing" => "bg-blue-100 text-blue-800",
    "Approved" => "bg-green-100 text-green-800",
    "Rejected" => "bg-red-100 text-red-800",
    "Completed" => "bg-emerald-100 text-emerald-800"
];

$status = $request["status"] ?? "";

$statusClass = $statusColors[$status]
    ?? "bg-gray-100 text-gray-800";

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Track Request | Barangay Guindacpan</title>

<script src="https://cdn.tailwindcss.com"></script>

</head>

<body class="bg-gray-100 min-h-screen">

<!-- =================================================
     HEADER
================================================== -->

<header class="bg-[#101827] text-white shadow-lg">

    <div class="max-w-6xl mx-auto px-5 py-5
                flex justify-between items-center">

        <div>
            <h1 class="text-xl font-bold">
                Barangay Guindacpan
            </h1>

            <p class="text-sm text-gray-300">
                Resident Request Tracking
            </p>
        </div>

        <a href="dashboard.php"
           class="bg-blue-600 hover:bg-blue-700
                  px-4 py-2 rounded-lg text-sm
                  transition">

            Dashboard

        </a>

    </div>

</header>

<!-- =================================================
     MAIN CONTENT
================================================== -->

<main class="max-w-4xl mx-auto px-4 py-10">

    <!-- PAGE TITLE -->

    <div class="mb-8">

        <h2 class="text-3xl font-bold text-gray-800">
            Track Your Request
        </h2>

        <p class="text-gray-500 mt-2">

            Hello, <?= e($resident["full_name"]) ?>.
            Check the latest status of your document request.

        </p>

    </div>

    <!-- TRACKING FORM -->

    <div class="bg-white rounded-2xl shadow-md
                p-6 mb-8">

        <h3 class="text-lg font-bold text-gray-800 mb-4">

            Enter Tracking Number

        </h3>

        <form method="POST" class="space-y-4">

            <input
                type="text"
                name="tracking_code"
                value="<?= e($tracking_code) ?>"
                placeholder="Example: BRG-2026-0001"
                maxlength="30"
                required
                class="w-full border border-gray-300
                       rounded-lg px-4 py-3
                       focus:ring-2 focus:ring-blue-500
                       focus:outline-none"
            >

            <button
                type="submit"
                class="w-full bg-blue-600
                       hover:bg-blue-700
                       text-white font-semibold
                       rounded-lg py-3 transition">

                Track Request

            </button>

        </form>

    </div>

    <!-- ERROR MESSAGE -->

    <?php if ($error): ?>

        <div class="bg-red-100 border border-red-300
                    text-red-700 p-4 rounded-lg mb-6">

            <?= e($error) ?>

        </div>

    <?php endif; ?>

    <!-- REQUEST DETAILS -->

    <?php if ($request): ?>

        <div class="bg-white rounded-2xl shadow-md
                    p-6">

            <div class="flex flex-col sm:flex-row
                        justify-between gap-4
                        border-b pb-5 mb-5">

                <div>

                    <p class="text-sm text-gray-500">
                        Tracking Number
                    </p>

                    <h3 class="text-xl font-bold
                               text-gray-800">

                        <?= e($request["tracking_code"]) ?>

                    </h3>

                </div>

                <div>

                    <span class="inline-block px-4 py-2
                                 rounded-full text-sm
                                 font-semibold <?= $statusClass ?>">

                        <?= e($status) ?>

                    </span>

                </div>

            </div>

            <!-- REQUEST INFORMATION -->

            <div class="grid grid-cols-1 sm:grid-cols-2
                        gap-6">

                <div>

                    <p class="text-sm text-gray-500">
                        Resident Name
                    </p>

                    <p class="font-semibold text-gray-800">
                        <?= e($resident["full_name"]) ?>
                    </p>

                </div>

                <div>

                    <p class="text-sm text-gray-500">
                        Document Type
                    </p>

                    <p class="font-semibold text-gray-800">
                        <?= e($request["request_type"]) ?>
                    </p>

                </div>

                <div>

                    <p class="text-sm text-gray-500">
                        Purpose
                    </p>

                    <p class="font-semibold text-gray-800">
                        <?= nl2br(e($request["purpose"])) ?>
                    </p>

                </div>

                <div>

                    <p class="text-sm text-gray-500">
                        Date Submitted
                    </p>

                    <p class="font-semibold text-gray-800">
                        <?= e($request["submitted_at"]) ?>
                    </p>

                </div>

                <div>

                    <p class="text-sm text-gray-500">
                        Last Updated
                    </p>

                    <p class="font-semibold text-gray-800">
                        <?= e($request["updated_at"]) ?>
                    </p>

                </div>

            </div>

            <!-- ADMIN REMARKS -->

            <?php if (!empty($request["admin_remarks"])): ?>

                <div class="mt-7 bg-blue-50
                            border border-blue-200
                            rounded-lg p-5">

                    <h4 class="font-bold text-blue-900 mb-2">

                        Administrator Remarks

                    </h4>

                    <p class="text-gray-700">

                        <?= nl2br(e($request["admin_remarks"])) ?>

                    </p>

                </div>

            <?php endif; ?>

            <!-- STATUS INFORMATION -->

            <div class="mt-8 border-t pt-6">

                <h4 class="font-bold text-gray-800 mb-4">

                    Request Progress

                </h4>

                <div class="space-y-4">

                    <?php

                    $steps = [
                        "Pending",
                        "Processing",
                        "Approved",
                        "Completed"
                    ];

                    $progress = [
                        "Pending" => 0,
                        "Processing" => 1,
                        "Approved" => 2,
                        "Completed" => 3
                    ];

                    $currentStep = $progress[$status] ?? -1;

                    foreach ($steps as $index => $step):

                        $isDone = $currentStep >= $index;

                    ?>

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-full
                                flex items-center justify-center
                                text-sm font-bold
                                <?= $isDone
                                    ? 'bg-green-600 text-white'
                                    : 'bg-gray-200 text-gray-500' ?>">

                                <?= $isDone ? '✓' : ($index + 1) ?>

                            </div>

                            <span class="font-medium
                                <?= $isDone
                                    ? 'text-green-700'
                                    : 'text-gray-400' ?>">

                                <?= e($step) ?>

                            </span>

                        </div>

                    <?php endforeach; ?>

                    <?php if ($status === "Rejected"): ?>

                        <div class="flex items-center gap-3">

                            <div class="w-8 h-8 rounded-full
                                        bg-red-600 text-white
                                        flex items-center justify-center">

                                !

                            </div>

                            <span class="font-semibold text-red-600">
                                Request Rejected
                            </span>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    <?php endif; ?>

</main>

</body>
</html>
