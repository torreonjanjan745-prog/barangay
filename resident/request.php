
<?php
// ======================================================
// BARANGAY GUINDACPAN
// RESIDENT DOCUMENT REQUEST PAGE
//
// Database: barangay_db
// Table: residents
// Connection: ../db.php
// Submit Handler: submit.php
// ======================================================

// SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// DATABASE CONNECTION
require_once __DIR__ . '/../db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    exit('Database connection is unavailable.');
}

// CHECK RESIDENT LOGIN
if (empty($_SESSION['resident_id'])) {
    header('Location: ../LogIn.php');
    exit;
}

$residentId = (int) $_SESSION['resident_id'];

// GET RESIDENT INFORMATION
try {
    $stmt = $pdo->prepare("
        SELECT id, full_name, username, purok_address
        FROM residents
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $residentId
    ]);

    $resident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resident) {
        unset(
            $_SESSION['resident_id'],
            $_SESSION['resident_username'],
            $_SESSION['resident_name']
        );

        header('Location: ../LogIn.php');
        exit;
    }

} catch (PDOException $e) {
    error_log('Resident request page: ' . $e->getMessage());
    exit('Unable to load resident information.');
}

// RESIDENT DETAILS
$residentName = $resident['full_name'];
$residentAddress = $resident['purok_address'] ?? '';

// CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ======================================================
// FLASH MESSAGES
// IMPORTANT: MATCH WITH submit.php SESSION KEYS
// ======================================================

$success = $_SESSION['request_success'] ?? '';
$error = $_SESSION['request_error'] ?? '';
$trackingCode = $_SESSION['request_tracking_code'] ?? '';
$old = $_SESSION['request_old'] ?? [];

unset(
    $_SESSION['request_success'],
    $_SESSION['request_error'],
    $_SESSION['request_tracking_code'],
    $_SESSION['request_old']
);

// ESCAPE OUTPUT
function requestEscape($value): string {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// AVAILABLE DOCUMENT TYPES
$documentTypes = [
    'Barangay Clearance',
    'Certificate of Residency',
    'Certificate of Indigency',
    'Business Clearance',
    'Certificate of Good Moral',
    'Other'
];

$selectedType = $old['request_type'] ?? '';
$purpose = $old['purpose'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Document | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f7f5;
        }

        .brand-green {
            background: #0B3D2E;
        }

        .brand-gold {
            color: #B9972F;
        }

        .form-input {
            width: 100%;
            border: 1px solid #dce5df;
            border-radius: 12px;
            padding: 13px 15px;
            outline: none;
            background: #fff;
            transition: .2s;
        }

        .form-input:focus {
            border-color: #14532D;
            box-shadow: 0 0 0 3px rgba(20, 83, 45, .10);
        }

        .submit-button {
            background: #0B3D2E;
            transition: .2s ease;
        }

        .submit-button:hover {
            background: #14532D;
            transform: translateY(-1px);
        }
    </style>

</head>

<body class="min-h-screen">

<!-- ==================================================
     NAVIGATION
================================================== -->

<header class="brand-green text-white shadow-lg">

    <div class="max-w-7xl mx-auto px-5 py-4 flex items-center justify-between">

        <a href="dashboard.php"
           class="flex items-center gap-3">

            <img src="../asset/logo.png"
                 alt="Barangay Guindacpan Logo"
                 class="w-12 h-12 object-contain rounded-full bg-white p-1">

            <div>
                <h1 class="font-extrabold text-sm sm:text-base">
                    BARANGAY GUINDACPAN
                </h1>

                <p class="text-xs text-green-100">
                    Resident Portal
                </p>
            </div>

        </a>

        <a href="dashboard.php"
           class="text-sm font-semibold hover:text-yellow-300 transition">

            <i class="fa-solid fa-arrow-left mr-2"></i>
            Dashboard

        </a>

    </div>

</header>


<!-- ==================================================
     MAIN CONTENT
================================================== -->

<main class="max-w-5xl mx-auto px-4 py-8 sm:py-12">

    <!-- PAGE HEADING -->

    <div class="mb-8">

        <p class="text-sm font-semibold brand-gold mb-2">
            RESIDENT SERVICES
        </p>

        <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900">
            Request a Document
        </h2>

        <p class="text-gray-500 mt-2 text-sm sm:text-base">
            Submit your document request online through Barangay Guindacpan.
        </p>

    </div>


    <!-- ==================================================
         SUCCESS MESSAGE
    ================================================== -->

    <?php if (!empty($success)): ?>

        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-5">

            <div class="flex gap-3">

                <i class="fa-solid fa-circle-check text-green-600 text-xl mt-1"></i>

                <div class="flex-1">

                    <h3 class="font-bold text-green-800">
                        Request Submitted Successfully!
                    </h3>

                    <p class="text-sm text-green-700 mt-1">
                        <?= requestEscape($success) ?>
                    </p>

                    <?php if (!empty($trackingCode)): ?>

                        <div class="mt-3 bg-white border border-green-200 rounded-lg p-3">

                            <p class="text-xs text-gray-500 mb-1">
                                YOUR TRACKING CODE
                            </p>

                            <div class="flex items-center justify-between gap-3">

                                <strong id="trackingCode"
                                        class="text-green-900 text-lg tracking-wide break-all">

                                    <?= requestEscape($trackingCode) ?>

                                </strong>

                                <button type="button"
                                        onclick="copyTrackingCode()"
                                        class="text-sm text-green-700 font-bold hover:text-green-900 shrink-0">

                                    <i class="fa-regular fa-copy mr-1"></i>
                                    Copy

                                </button>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         ERROR MESSAGE
    ================================================== -->

    <?php if (!empty($error)): ?>

        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700 text-sm">

            <i class="fa-solid fa-circle-exclamation mr-2"></i>

            <?= requestEscape($error) ?>

        </div>

    <?php endif; ?>


    <!-- ==================================================
         REQUEST LAYOUT
    ================================================== -->

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">


        <!-- ==================================================
             REQUEST FORM
        ================================================== -->

        <section class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sm:p-8">

            <!-- FORM HEADER -->

            <div class="flex items-center gap-3 mb-7">

                <div class="w-11 h-11 rounded-xl bg-green-50 text-green-800 flex items-center justify-center">

                    <i class="fa-solid fa-file-circle-plus text-lg"></i>

                </div>

                <div>

                    <h3 class="font-bold text-gray-900 text-lg">
                        Document Request Form
                    </h3>

                    <p class="text-xs text-gray-500">
                        Fill in the required information.
                    </p>

                </div>

            </div>


            <!-- FORM -->

            <form action="submit.php"
                  method="POST"
                  class="space-y-5">

                <!-- CSRF TOKEN -->

                <input type="hidden"
                       name="csrf_token"
                       value="<?= requestEscape($_SESSION['csrf_token']) ?>">


                <!-- RESIDENT NAME -->

                <div>

                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Full Name
                    </label>

                    <div class="relative">

                        <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                        <input type="text"
                               value="<?= requestEscape($residentName) ?>"
                               class="form-input pl-11 bg-gray-50"
                               readonly>

                    </div>

                </div>


                <!-- ADDRESS -->

                <div>

                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Purok / Address
                    </label>

                    <div class="relative">

                        <i class="fa-solid fa-location-dot absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>

                        <input type="text"
                               value="<?= requestEscape($residentAddress) ?>"
                               class="form-input pl-11 bg-gray-50"
                               readonly>

                    </div>

                </div>


                <!-- DOCUMENT TYPE -->

                <div>

                    <label for="request_type"
                           class="block text-sm font-semibold text-gray-700 mb-2">

                        Document Type
                        <span class="text-red-500">*</span>

                    </label>

                    <select name="request_type"
                            id="request_type"
                            class="form-input"
                            required>

                        <option value="">
                            Select document type
                        </option>

                        <?php foreach ($documentTypes as $type): ?>

                            <option value="<?= requestEscape($type) ?>"
                                <?= $selectedType === $type ? 'selected' : '' ?>>

                                <?= requestEscape($type) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PURPOSE -->

                <div>

                    <label for="purpose"
                           class="block text-sm font-semibold text-gray-700 mb-2">

                        Purpose of Request
                        <span class="text-red-500">*</span>

                    </label>

                    <textarea name="purpose"
                              id="purpose"
                              rows="5"
                              maxlength="2000"
                              class="form-input resize-y"
                              placeholder="Please explain why you need this document..."
                              required><?= requestEscape($purpose) ?></textarea>

                    <p class="text-xs text-gray-400 mt-2">
                        Maximum of 2,000 characters.
                    </p>

                </div>


                <!-- SUBMIT BUTTON -->

                <div class="pt-3">

                    <button type="submit"
                            class="submit-button w-full text-white font-bold py-4 rounded-xl shadow-md">

                        <i class="fa-solid fa-paper-plane mr-2"></i>

                        Submit Document Request

                    </button>

                    <p class="text-center text-xs text-gray-400 mt-4">

                        Please review your information before submitting.

                    </p>

                </div>

            </form>

        </section>


        <!-- ==================================================
             INFORMATION PANEL
        ================================================== -->

        <aside class="space-y-5">


            <!-- REQUEST INFORMATION -->

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">

                <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-700 flex items-center justify-center mb-4">

                    <i class="fa-solid fa-circle-info text-xl"></i>

                </div>

                <h3 class="font-bold text-gray-900 mb-3">
                    Request Information
                </h3>

                <ul class="space-y-4 text-sm text-gray-600">

                    <li class="flex gap-3">

                        <i class="fa-solid fa-check-circle text-green-700 mt-1"></i>

                        <span>
                            Submit your request using your registered resident account.
                        </span>

                    </li>

                    <li class="flex gap-3">

                        <i class="fa-solid fa-clock text-green-700 mt-1"></i>

                        <span>
                            Wait for the barangay administrator to review your request.
                        </span>

                    </li>

                    <li class="flex gap-3">

                        <i class="fa-solid fa-barcode text-green-700 mt-1"></i>

                        <span>
                            Keep your tracking code to monitor your request.
                        </span>

                    </li>

                </ul>

            </div>


            <!-- ASSISTANCE -->

            <div class="brand-green rounded-2xl p-6 text-white">

                <i class="fa-solid fa-headset text-2xl text-yellow-300 mb-4"></i>

                <h3 class="font-bold text-lg">
                    Need Assistance?
                </h3>

                <p class="text-sm text-green-100 mt-2 leading-relaxed">

                    Visit the Barangay Guindacpan office if you need help with your document request.

                </p>

            </div>

        </aside>

    </div>

</main>


<!-- ==================================================
     FOOTER
================================================== -->

<footer class="text-center text-xs text-gray-400 py-7">

    &copy; <?= date('Y') ?> Barangay Guindacpan. All rights reserved.

</footer>


<!-- ==================================================
     JAVASCRIPT
================================================== -->

<script>

function copyTrackingCode() {

    const code = document.getElementById('trackingCode');

    if (!code) {
        return;
    }

    const trackingCode = code.textContent.trim();

    if (navigator.clipboard && window.isSecureContext) {

        navigator.clipboard.writeText(trackingCode)
            .then(() => {
                alert('Tracking code copied!');
            })
            .catch(() => {
                alert('Unable to copy tracking code.');
            });

    } else {

        const temp = document.createElement('textarea');

        temp.value = trackingCode;

        document.body.appendChild(temp);

        temp.select();

        try {
            document.execCommand('copy');
            alert('Tracking code copied!');
        } catch (error) {
            alert('Unable to copy tracking code.');
        }

        document.body.removeChild(temp);

    }

}

</script>

</body>
</html>
