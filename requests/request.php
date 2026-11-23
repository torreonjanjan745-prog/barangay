
<?php
// ==================================================
// BARANGAY GUINDACPAN
// DOCUMENT REQUEST SYSTEM
// SECURE PDO VERSION
// ==================================================

session_start();

require_once __DIR__ . '/../db.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fullName = trim($_POST['full_name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $documentType = trim($_POST['document_type'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');

    $allowedDocuments = [
        'Barangay Clearance',
        'Certificate of Residency',
        'Certificate of Indigency',
        'Business Clearance',
        'Certificate of Good Moral',
        'Other'
    ];

    if (
        $fullName === '' ||
        $address === '' ||
        $contact === '' ||
        $documentType === '' ||
        $purpose === ''
    ) {
        $message = 'Please complete all required fields.';
        $messageType = 'error';

    } elseif (!in_array($documentType, $allowedDocuments, true)) {
        $message = 'Invalid document type selected.';
        $messageType = 'error';

    } else {

        try {
            $sql = "INSERT INTO document_requests
                    (full_name, address, contact_number,
                     document_type, purpose, status)
                    VALUES
                    (:full_name, :address, :contact,
                     :document_type, :purpose, 'Pending')";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                ':full_name' => $fullName,
                ':address' => $address,
                ':contact' => $contact,
                ':document_type' => $documentType,
                ':purpose' => $purpose
            ]);

            $message = 'Your document request has been submitted successfully!';
            $messageType = 'success';

        } catch (PDOException $e) {
            error_log($e->getMessage());

            $message = 'Unable to submit your request. Please try again.';
            $messageType = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Document | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body class="bg-slate-100 min-h-screen">

    <!-- HEADER -->
    <header class="bg-blue-950 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-6 py-5 flex items-center gap-4">

            <div class="bg-white/10 rounded-xl p-3">
                <i class="fas fa-landmark text-2xl"></i>
            </div>

            <div>
                <h1 class="text-xl font-bold">
                    BARANGAY GUINDACPAN
                </h1>

                <p class="text-blue-200 text-sm">
                    Document Request Portal
                </p>
            </div>

        </div>
    </header>


    <!-- MAIN -->
    <main class="max-w-3xl mx-auto px-5 py-12">

        <div class="mb-8">

            <a href="index.php"
               class="text-blue-700 hover:text-blue-900 text-sm font-semibold">

                <i class="fas fa-arrow-left mr-2"></i>
                Back to Home

            </a>

            <h2 class="text-3xl font-bold text-slate-800 mt-6">
                Request a Document
            </h2>

            <p class="text-slate-500 mt-2">
                Fill out the form below to request an official barangay document.
            </p>

        </div>


        <!-- NOTIFICATION -->
        <?php if ($message !== ''): ?>

            <div class="mb-6 p-4 rounded-xl
                <?= $messageType === 'success'
                    ? 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800' ?>">

                <i class="fas <?= $messageType === 'success'
                    ? 'fa-circle-check'
                    : 'fa-circle-exclamation' ?> mr-2"></i>

                <?= htmlspecialchars($message) ?>

            </div>

        <?php endif; ?>


        <!-- REQUEST FORM -->
        <div class="bg-white rounded-2xl shadow-md border border-slate-200 p-7">

            <div class="flex items-center gap-3 mb-7">

                <div class="bg-blue-100 text-blue-800 p-3 rounded-xl">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>

                <div>
                    <h3 class="font-bold text-lg text-slate-800">
                        Document Information
                    </h3>

                    <p class="text-sm text-slate-500">
                        Provide accurate information.
                    </p>
                </div>

            </div>


            <form method="POST" action="" class="space-y-5">

                <!-- FULL NAME -->
                <div>

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Full Name *
                    </label>

                    <input
                        type="text"
                        name="full_name"
                        required
                        maxlength="150"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                        placeholder="Enter your complete name"
                        class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">

                </div>


                <!-- ADDRESS -->
                <div>

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Complete Address *
                    </label>

                    <input
                        type="text"
                        name="address"
                        required
                        maxlength="255"
                        value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                        placeholder="Purok, Barangay, Municipality"
                        class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">

                </div>


                <!-- CONTACT -->
                <div>

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Contact Number *
                    </label>

                    <input
                        type="tel"
                        name="contact_number"
                        required
                        maxlength="30"
                        value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                        placeholder="09XXXXXXXXX"
                        class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">

                </div>


                <!-- DOCUMENT TYPE -->
                <div>

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Type of Document *
                    </label>

                    <select
                        name="document_type"
                        required
                        class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500">

                        <option value="">Select document type</option>

                        <?php foreach ([
                            'Barangay Clearance',
                            'Certificate of Residency',
                            'Certificate of Indigency',
                            'Business Clearance',
                            'Certificate of Good Moral',
                            'Other'
                        ] as $document): ?>

                            <option
                                value="<?= htmlspecialchars($document) ?>"
                                <?= ($_POST['document_type'] ?? '') === $document
                                    ? 'selected'
                                    : '' ?>>

                                <?= htmlspecialchars($document) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PURPOSE -->
                <div>

                    <label class="block text-sm font-semibold text-slate-700 mb-2">
                        Purpose of Request *
                    </label>

                    <textarea
                        name="purpose"
                        required
                        rows="4"
                        maxlength="2000"
                        placeholder="Explain why you need this document..."
                        class="w-full border border-slate-300 rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($_POST['purpose'] ?? '') ?></textarea>

                </div>


                <!-- INFORMATION -->
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">

                    <p class="text-sm text-blue-900">
                        <i class="fas fa-info-circle mr-2"></i>

                        Your request will initially be marked as
                        <strong>Pending</strong> until reviewed by the barangay administrator.
                    </p>

                </div>


                <!-- SUBMIT -->
                <button
                    type="submit"
                    class="w-full bg-blue-700 hover:bg-blue-800 text-white font-bold py-4 rounded-xl transition duration-200">

                    <i class="fas fa-paper-plane mr-2"></i>
                    Submit Document Request

                </button>

            </form>

        </div>


        <p class="text-center text-slate-400 text-sm mt-8">
            © <?= date('Y') ?> Barangay Guindacpan. All Rights Reserved.
        </p>

    </main>

</body>
</html>
