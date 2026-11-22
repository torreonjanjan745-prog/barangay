
<?php
// =============================================
// BHW PROFILE MANAGEMENT
// COMPLETE RESPONSIVE PROFILE WITH PHOTO UPLOAD
// Database: barangay_health_db
// =============================================

$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

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
        "samesite" => "Lax"
    ]);

    session_start();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

// =============================================
// SESSION CHECK
// =============================================

if (!isset($_SESSION["bhw_id"])) {
    header("Location: logIn.php");
    exit;
}

$bhwId = (int)$_SESSION["bhw_id"];

if ($bhwId <= 0) {
    $_SESSION = [];
    session_destroy();
    header("Location: logIn.php");
    exit;
}

// =============================================
// DATABASE CONNECTION
// =============================================

require_once __DIR__ . "/database.php";

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("BHW database connection is unavailable.");
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// =============================================
// UPLOAD CONFIGURATION
// =============================================

$uploadDirectory = __DIR__ . "/uploads/profiles/";
$uploadWebPath = "uploads/profiles/";

$maxPhotoSize = 2 * 1024 * 1024;

$allowedMimeTypes = [
    "image/jpeg" => "jpg",
    "image/png"  => "png",
    "image/webp" => "webp"
];

// =============================================
// CSRF TOKEN
// =============================================

if (empty($_SESSION["profile_csrf"])) {
    $_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "";

// =============================================
// FETCH PROFILE
// =============================================

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            email,
            contact_number,
            position,
            status,
            created_at,
            password,
            profile_photo
        FROM bhw_accounts
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([":id" => $bhwId]);
    $bhw = $stmt->fetch();

    if (!$bhw || strtolower(trim($bhw["status"])) !== "active") {
        $_SESSION = [];
        session_destroy();

        header("Location: logIn.php");
        exit;
    }

} catch (PDOException $e) {
    error_log("BHW Profile Fetch Error: " . $e->getMessage());
    http_response_code(500);
    exit("Unable to load BHW profile. Please check the database structure.");
}

// =============================================
// PROCESS FORMS
// =============================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["profile_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    // =========================================
    // UPDATE PROFILE
    // =========================================

    } elseif (isset($_POST["update_profile"])) {

        $fullName = trim($_POST["full_name"] ?? "");
        $email = strtolower(trim($_POST["email"] ?? ""));
        $contact = trim($_POST["contact_number"] ?? "");

        $bhw["full_name"] = $fullName;
        $bhw["email"] = $email;
        $bhw["contact_number"] = $contact;

        $newPhotoName = null;
        $uploadedFilePath = null;
        $oldPhoto = $bhw["profile_photo"] ?? null;

        // -------------------------------------
        // VALIDATE PERSONAL INFORMATION
        // -------------------------------------

        if ($fullName === "" || $email === "") {
            $message = "Please provide your full name and email.";
            $messageType = "error";

        } elseif (strlen($fullName) > 150) {
            $message = "Full name must not exceed 150 characters.";
            $messageType = "error";

        } elseif (
            strlen($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            $message = "Please enter a valid email address.";
            $messageType = "error";

        } elseif (
            strlen($contact) > 20 ||
            ($contact !== "" && !preg_match('/^[0-9+\-\s()]+$/', $contact))
        ) {
            $message = "Please enter a valid contact number.";
            $messageType = "error";

        } else {

            try {

                // ---------------------------------
                // CHECK DUPLICATE EMAIL
                // ---------------------------------

                $check = $pdo->prepare("
                    SELECT id
                    FROM bhw_accounts
                    WHERE email = :email
                    AND id != :id
                    LIMIT 1
                ");

                $check->execute([
                    ":email" => $email,
                    ":id" => $bhwId
                ]);

                if ($check->fetch()) {
                    $message = "This email address is already registered.";
                    $messageType = "error";

                } else {

                    // ---------------------------------
                    // VALIDATE PROFILE PHOTO
                    // ---------------------------------

                    $photoError = "";

                    if (
                        isset($_FILES["profile_photo"]) &&
                        $_FILES["profile_photo"]["error"] !== UPLOAD_ERR_NO_FILE
                    ) {

                        $file = $_FILES["profile_photo"];

                        if ($file["error"] !== UPLOAD_ERR_OK) {
                            $photoError = "Photo upload failed. Please try again.";

                        } elseif ($file["size"] <= 0 || $file["size"] > $maxPhotoSize) {
                            $photoError = "Photo must not exceed 2MB.";

                        } elseif (!is_uploaded_file($file["tmp_name"])) {
                            $photoError = "Invalid uploaded file.";

                        } else {

                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mime = $finfo->file($file["tmp_name"]);

                            $imageInfo = @getimagesize($file["tmp_name"]);

                            if (
                                !isset($allowedMimeTypes[$mime]) ||
                                $imageInfo === false
                            ) {
                                $photoError = "Only valid JPG, PNG, and WEBP images are allowed.";

                            } else {

                                // Create upload directory if missing.
                                if (
                                    !is_dir($uploadDirectory) &&
                                    !mkdir($uploadDirectory, 0755, true) &&
                                    !is_dir($uploadDirectory)
                                ) {
                                    $photoError = "Unable to create profile upload folder.";

                                } elseif (!is_writable($uploadDirectory)) {
                                    $photoError = "Profile upload folder is not writable.";

                                } else {

                                    $extension = $allowedMimeTypes[$mime];

                                    $newPhotoName =
                                        bin2hex(random_bytes(16)) . "." . $extension;

                                    $uploadedFilePath =
                                        $uploadDirectory . $newPhotoName;

                                    if (
                                        !move_uploaded_file(
                                            $file["tmp_name"],
                                            $uploadedFilePath
                                        )
                                    ) {
                                        $photoError = "Unable to save profile picture.";
                                        $newPhotoName = null;
                                        $uploadedFilePath = null;
                                    }
                                }
                            }
                        }
                    }

                    if ($photoError !== "") {

                        $message = $photoError;
                        $messageType = "error";

                    } else {

                        // ---------------------------------
                        // SAVE PROFILE AND PHOTO
                        // ---------------------------------

                        try {

                            $photoToSave = $newPhotoName !== null
                                ? $newPhotoName
                                : $oldPhoto;

                            $pdo->beginTransaction();

                            $update = $pdo->prepare("
                                UPDATE bhw_accounts
                                SET
                                    full_name = :name,
                                    email = :email,
                                    contact_number = :contact,
                                    profile_photo = :photo
                                WHERE id = :id
                            ");

                            $update->execute([
                                ":name" => $fullName,
                                ":email" => $email,
                                ":contact" => $contact,
                                ":photo" => $photoToSave,
                                ":id" => $bhwId
                            ]);

                            $pdo->commit();

                            // Update session information.
                            $_SESSION["bhw_name"] = $fullName;
                            $_SESSION["bhw_email"] = $email;

                            // Delete old image only after successful update.
                            if (
                                $newPhotoName !== null &&
                                !empty($oldPhoto) &&
                                basename($oldPhoto) === $oldPhoto
                            ) {
                                $oldFile = $uploadDirectory . $oldPhoto;

                                if (is_file($oldFile)) {
                                    @unlink($oldFile);
                                }
                            }

                            $_SESSION["profile_csrf"] =
                                bin2hex(random_bytes(32));

                            header("Location: profile.php?updated=1");
                            exit;

                        } catch (Throwable $e) {

                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }

                            // Remove newly uploaded file if database update failed.
                            if (
                                $uploadedFilePath !== null &&
                                is_file($uploadedFilePath)
                            ) {
                                @unlink($uploadedFilePath);
                            }

                            error_log("BHW Profile Update Error: " . $e->getMessage());

                            if (
                                $e instanceof PDOException &&
                                (
                                    $e->getCode() === "23000" ||
                                    (
                                        isset($e->errorInfo[1]) &&
                                        (int)$e->errorInfo[1] === 1062
                                    )
                                )
                            ) {
                                $message = "This email address is already registered.";
                            } else {
                                $message = "Unable to update profile. Please try again.";
                            }

                            $messageType = "error";
                        }
                    }
                }

            } catch (PDOException $e) {
                error_log("BHW Profile Validation Error: " . $e->getMessage());

                $message = "Unable to validate profile information.";
                $messageType = "error";
            }
        }

    // =========================================
    // CHANGE PASSWORD
    // =========================================

    } elseif (isset($_POST["change_password"])) {

        $currentPassword = $_POST["current_password"] ?? "";
        $newPassword = $_POST["new_password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";

        if (
            !is_string($currentPassword) ||
            !is_string($newPassword) ||
            !is_string($confirmPassword) ||
            $currentPassword === "" ||
            $newPassword === "" ||
            $confirmPassword === ""
        ) {
            $message = "Please complete all password fields.";
            $messageType = "error";

        } elseif (!password_verify($currentPassword, $bhw["password"])) {
            $message = "Your current password is incorrect.";
            $messageType = "error";

        } elseif (strlen($newPassword) < 8) {
            $message = "New password must be at least 8 characters.";
            $messageType = "error";

        } elseif ($newPassword !== $confirmPassword) {
            $message = "New passwords do not match.";
            $messageType = "error";

        } elseif ($currentPassword === $newPassword) {
            $message = "Your new password must be different from your current password.";
            $messageType = "error";

        } else {

            try {

                $hashedPassword = password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

                if ($hashedPassword === false) {
                    throw new RuntimeException("Password hashing failed.");
                }

                $updatePassword = $pdo->prepare("
                    UPDATE bhw_accounts
                    SET password = :password
                    WHERE id = :id
                ");

                $updatePassword->execute([
                    ":password" => $hashedPassword,
                    ":id" => $bhwId
                ]);

                $_SESSION["profile_csrf"] =
                    bin2hex(random_bytes(32));

                header("Location: profile.php?password_changed=1");
                exit;

            } catch (Throwable $e) {
                error_log("BHW Password Update Error: " . $e->getMessage());

                $message = "Unable to change password. Please try again.";
                $messageType = "error";
            }
        }
    }
}

// =============================================
// SUCCESS MESSAGES
// =============================================

if (isset($_GET["updated"])) {
    $message = "Your profile has been successfully updated.";
    $messageType = "success";
}

if (isset($_GET["password_changed"])) {
    $message = "Your password has been successfully changed.";
    $messageType = "success";
}

// =============================================
// PROFILE DISPLAY VALUES
// =============================================

$displayName = $bhw["full_name"] ?? "BHW User";
$displayEmail = $bhw["email"] ?? "";
$displayContact = $bhw["contact_number"] ?? "";
$displayPosition = $bhw["position"] ?? "Barangay Health Worker";
$displayStatus = $bhw["status"] ?? "Active";

$createdAt = "Not available";

if (!empty($bhw["created_at"])) {
    $timestamp = strtotime($bhw["created_at"]);

    if ($timestamp !== false) {
        $createdAt = date("F d, Y", $timestamp);
    }
}

$initial = strtoupper(substr(trim($displayName), 0, 1));

if ($initial === "") {
    $initial = "B";
}

// Only use a safe filename stored by this application.
$profilePhoto = $bhw["profile_photo"] ?? "";

$photoExists = (
    $profilePhoto !== "" &&
    basename($profilePhoto) === $profilePhoto &&
    is_file($uploadDirectory . $profilePhoto)
);

$profileImageUrl = $photoExists
    ? $uploadWebPath . rawurlencode($profilePhoto)
    : "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>BHW Profile | Barangay Health System</title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .sidebar {
            width: 260px;
            min-width: 260px;
            transition: transform .3s ease;
        }

        .main-content {
            min-width: 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 10px;
            color: #cbd5e1;
            font-size: 13px;
            font-weight: 500;
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

        .mobile-overlay {
            display: none;
        }

        .mobile-overlay.show {
            display: block;
        }

        .profile-avatar {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            overflow: hidden;
            flex-shrink: 0;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        input {
            outline: none;
        }

        input:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }

        .upload-label {
            cursor: pointer;
            transition: .2s;
        }

        .upload-label:hover {
            background: #dbeafe;
        }

        @media (max-width: 767px) {

            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                z-index: 50;
                transform: translateX(-100%);
                height: 100dvh;
                overflow-y: auto;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .mobile-overlay.show {
                position: fixed;
                inset: 0;
                z-index: 40;
                background: rgba(15,23,42,.55);
                backdrop-filter: blur(2px);
            }

            .main-content {
                width: 100%;
            }

            .content-area {
                padding: 18px 14px;
            }
        }

    </style>

</head>

<body class="min-h-screen">

<div class="flex min-h-screen">

    <!-- =====================================
         MOBILE OVERLAY
    ====================================== -->

    <div
        id="mobileOverlay"
        class="mobile-overlay"
        onclick="closeSidebar()">
    </div>

    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside id="sidebar" class="sidebar bg-slate-900 text-white flex flex-col">

        <!-- Logo -->

        <div class="px-6 py-6 border-b border-slate-800 flex items-center justify-between">

            <div class="flex items-center gap-3">

                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center">

                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v12m-6-6h12"/>
                    </svg>

                </div>

                <div>
                    <h1 class="font-bold text-sm">BHW PORTAL</h1>
                    <p class="text-xs text-slate-400">Health Management</p>
                </div>

            </div>

            <button
                type="button"
                onclick="closeSidebar()"
                class="md:hidden text-slate-400 hover:text-white"
                aria-label="Close menu">

                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>

            </button>

        </div>

        <!-- Sidebar User -->

        <div class="px-5 py-5 border-b border-slate-800">

            <div class="flex items-center gap-3">

                <div class="profile-avatar w-11 h-11 rounded-full flex items-center justify-center text-white font-bold text-lg">

                    <?php if ($photoExists): ?>
                        <img src="<?= e($profileImageUrl) ?>" alt="Profile">
                    <?php else: ?>
                        <?= e($initial) ?>
                    <?php endif; ?>

                </div>

                <div class="min-w-0">

                    <p class="text-sm font-semibold truncate">
                        <?= e($displayName) ?>
                    </p>

                    <p class="text-xs text-slate-400 truncate">
                        <?= e($displayPosition) ?>
                    </p>

                </div>

            </div>

        </div>

        <!-- Navigation -->

        <nav class="flex-1 px-4 py-5">

            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold px-3 mb-3">
                Main Menu
            </p>

            <div class="space-y-1">

                <a href="dashboard.php" class="nav-link">
                    <span>⌂</span>
                    Dashboard
                </a>

                <a href="patients.php" class="nav-link">
                    <span>♧</span>
                    Patients
                </a>

                <a href="health_records.php" class="nav-link">
                    <span>▤</span>
                    Health Records
                </a>

                <a href="prenatal.php" class="nav-link">
                    <span>♡</span>
                    Prenatal
                </a>

                <a href="immunization.php" class="nav-link">
                    <span>✚</span>
                    Immunization
                </a>

                <a href="appointments.php" class="nav-link">
                    <span>▦</span>
                    Appointments
                </a>

            </div>

            <p class="text-[10px] uppercase tracking-widest text-slate-500 font-bold px-3 mt-8 mb-3">
                Account
            </p>

            <div class="space-y-1">

                <a href="profile.php" class="nav-link active">
                    <span>♙</span>
                    My Profile
                </a>

                <a href="logout.php" class="nav-link hover:!bg-red-600">
                    <span>⇥</span>
                    Logout
                </a>

            </div>

        </nav>

        <div class="px-6 py-4 border-t border-slate-800">
            <p class="text-xs text-slate-500 text-center">
                Barangay Health System
            </p>
        </div>

    </aside>

    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content flex-1">

        <!-- HEADER -->

        <header class="bg-white border-b border-slate-200 sticky top-0 z-30">

            <div class="px-4 md:px-8 py-4 flex items-center justify-between">

                <div class="flex items-center gap-3">

                    <button
                        type="button"
                        onclick="openSidebar()"
                        class="md:hidden p-2 rounded-lg hover:bg-slate-100 text-slate-700"
                        aria-label="Open navigation menu">

                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>

                    </button>

                    <div>

                        <h2 class="text-lg md:text-xl font-bold text-slate-800">
                            My Profile
                        </h2>

                        <p class="text-xs text-slate-500 hidden sm:block">
                            Manage your personal account information
                        </p>

                    </div>

                </div>

                <div class="flex items-center gap-2">

                    <div class="text-right hidden sm:block">

                        <p class="text-sm font-semibold text-slate-700">
                            <?= e($displayName) ?>
                        </p>

                        <p class="text-xs text-slate-500">
                            <?= e($displayPosition) ?>
                        </p>

                    </div>

                    <div class="profile-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">

                        <?php if ($photoExists): ?>
                            <img src="<?= e($profileImageUrl) ?>" alt="Profile">
                        <?php else: ?>
                            <?= e($initial) ?>
                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </header>

        <!-- =====================================
             PAGE CONTENT
        ====================================== -->

        <div class="content-area p-4 md:p-8 max-w-7xl mx-auto">

            <!-- PAGE TITLE -->

            <div class="mb-6 md:mb-8">

                <h1 class="text-2xl md:text-3xl font-extrabold text-slate-800">
                    Account Settings
                </h1>

                <p class="text-sm text-slate-500 mt-2">
                    Manage your profile picture, personal information, and password.
                </p>

            </div>

            <!-- ALERT MESSAGE -->

            <?php if ($message !== ""): ?>

                <div class="mb-6 p-4 rounded-xl border flex items-start gap-3
                    <?= $messageType === "success"
                        ? "bg-green-50 border-green-200 text-green-700"
                        : "bg-red-50 border-red-200 text-red-700" ?>">

                    <p class="text-sm font-medium">
                        <?= e($message) ?>
                    </p>

                </div>

            <?php endif; ?>

            <!-- =================================
                 PROFILE OVERVIEW
            ================================== -->

            <section class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 md:p-7 mb-6">

                <div class="flex flex-col sm:flex-row sm:items-center gap-5">

                    <div class="profile-avatar w-24 h-24 rounded-2xl flex items-center justify-center text-white text-3xl font-extrabold shadow-lg">

                        <?php if ($photoExists): ?>
                            <img src="<?= e($profileImageUrl) ?>" alt="Profile">
                        <?php else: ?>
                            <?= e($initial) ?>
                        <?php endif; ?>

                    </div>

                    <div class="flex-1 min-w-0">

                        <h2 class="text-xl md:text-2xl font-bold text-slate-800 break-words">
                            <?= e($displayName) ?>
                        </h2>

                        <p class="text-sm text-slate-500 mt-1 break-all">
                            <?= e($displayEmail) ?>
                        </p>

                        <div class="flex flex-wrap items-center gap-2 mt-3">

                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700">
                                <?= e($displayPosition) ?>
                            </span>

                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700">
                                <?= e(ucfirst($displayStatus)) ?>
                            </span>

                        </div>

                    </div>

                    <div class="sm:text-right border-t sm:border-t-0 pt-4 sm:pt-0">

                        <p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">
                            Member Since
                        </p>

                        <p class="text-sm font-semibold text-slate-700 mt-1">
                            <?= e($createdAt) ?>
                        </p>

                    </div>

                </div>

            </section>

            <!-- =================================
                 PROFILE INFORMATION + PASSWORD
            ================================== -->

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <!-- =================================
                     EDIT PROFILE
                ================================== -->

                <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                    <div class="p-5 md:p-6 border-b border-slate-100">

                        <h3 class="font-bold text-slate-800">
                            Edit Profile
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Update your photo and personal information.
                        </p>

                    </div>

                    <form
                        method="POST"
                        action="profile.php"
                        enctype="multipart/form-data"
                        class="p-5 md:p-6 space-y-5">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION["profile_csrf"]) ?>">

                        <!-- PROFILE PHOTO -->

                        <div class="flex flex-col items-center text-center gap-3">

                            <div class="relative">

                                <div class="profile-avatar w-32 h-32 rounded-full border-4 border-blue-100 shadow-md flex items-center justify-center text-white text-4xl font-bold">

                                    <img
                                        id="profilePreview"
                                        src="<?= $photoExists ? e($profileImageUrl) : "" ?>"
                                        alt="Profile Picture Preview"
                                        class="<?= $photoExists ? "" : "hidden" ?>">

                                    <span
                                        id="profileInitial"
                                        class="<?= $photoExists ? "hidden" : "" ?>">
                                        <?= e($initial) ?>
                                    </span>

                                </div>

                                <div class="absolute bottom-1 right-1 w-9 h-9 bg-blue-600 rounded-full border-4 border-white flex items-center justify-center text-white">

                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 7h3l2-3h8l2 3h3v12H3V7zm9 3a3 3 0 100 6 3 3 0 000-6z"/>
                                    </svg>

                                </div>

                            </div>

                            <label
                                for="profile_photo"
                                class="upload-label px-5 py-2.5 bg-blue-50 text-blue-700 rounded-xl text-sm font-semibold">

                                Choose Profile Photo

                            </label>

                            <input
                                type="file"
                                id="profile_photo"
                                name="profile_photo"
                                accept="image/jpeg,image/png,image/webp"
                                class="hidden">

                            <p class="text-xs text-slate-400">
                                JPG, PNG, or WEBP. Maximum file size: 2MB.
                            </p>

                            <p id="photoFileName" class="text-xs text-blue-600 font-medium"></p>

                        </div>

                        <div>

                            <label for="full_name" class="block text-sm font-semibold text-slate-700 mb-2">
                                Full Name
                            </label>

                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                maxlength="150"
                                required
                                value="<?= e($bhw["full_name"]) ?>"
                                placeholder="Enter your full name"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                        </div>

                        <div>

                            <label for="email" class="block text-sm font-semibold text-slate-700 mb-2">
                                Email Address
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                required
                                value="<?= e($bhw["email"]) ?>"
                                placeholder="Enter your email"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                        </div>

                        <div>

                            <label for="contact_number" class="block text-sm font-semibold text-slate-700 mb-2">
                                Contact Number
                            </label>

                            <input
                                type="tel"
                                id="contact_number"
                                name="contact_number"
                                maxlength="20"
                                value="<?= e($bhw["contact_number"]) ?>"
                                placeholder="Enter your contact number"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                        </div>

                        <div>

                            <label class="block text-sm font-semibold text-slate-700 mb-2">
                                Position
                            </label>

                            <input
                                type="text"
                                value="<?= e($displayPosition) ?>"
                                readonly
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm text-slate-500 cursor-not-allowed">

                            <p class="text-xs text-slate-400 mt-2">
                                Contact the administrator if you need to change your position.
                            </p>

                        </div>

                        <button
                            type="submit"
                            name="update_profile"
                            class="w-full px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition shadow-sm">

                            Save Profile Changes

                        </button>

                    </form>

                </section>

                <!-- =================================
                     CHANGE PASSWORD
                ================================== -->

                <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

                    <div class="p-5 md:p-6 border-b border-slate-100">

                        <h3 class="font-bold text-slate-800">
                            Change Password
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Update your password to keep your account secure.
                        </p>

                    </div>

                    <form method="POST" action="profile.php" class="p-5 md:p-6 space-y-5">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION["profile_csrf"]) ?>">

                        <div>

                            <label for="current_password" class="block text-sm font-semibold text-slate-700 mb-2">
                                Current Password
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter current password"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                        </div>

                        <div>

                            <label for="new_password" class="block text-sm font-semibold text-slate-700 mb-2">
                                New Password
                            </label>

                            <input
                                type="password"
                                id="new_password"
                                name="new_password"
                                minlength="8"
                                required
                                autocomplete="new-password"
                                placeholder="Enter new password"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                            <p class="text-xs text-slate-400 mt-2">
                                Use at least 8 characters.
                            </p>

                        </div>

                        <div>

                            <label for="confirm_password" class="block text-sm font-semibold text-slate-700 mb-2">
                                Confirm New Password
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                minlength="8"
                                required
                                autocomplete="new-password"
                                placeholder="Confirm new password"
                                class="w-full px-4 py-3 rounded-xl border border-slate-300 text-sm text-slate-700">

                        </div>

                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">

                            <p class="text-xs text-amber-800 leading-relaxed">
                                Never share your password with anyone. Use a strong password that you do not use on other websites.
                            </p>

                        </div>

                        <button
                            type="submit"
                            name="change_password"
                            class="w-full px-6 py-3 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold transition shadow-sm">

                            Update Password

                        </button>

                    </form>

                </section>

            </div>

            <!-- =================================
                 ACCOUNT INFORMATION
            ================================== -->

            <section class="mt-6 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 md:p-6">

                <h3 class="font-bold text-slate-800 mb-5">
                    Account Information
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                    <div class="p-4 rounded-xl bg-slate-50">

                        <p class="text-xs text-slate-500 mb-1">
                            Account ID
                        </p>

                        <p class="font-bold text-slate-800">
                            #<?= e($bhw["id"]) ?>
                        </p>

                    </div>

                    <div class="p-4 rounded-xl bg-slate-50">

                        <p class="text-xs text-slate-500 mb-1">
                            Account Status
                        </p>

                        <p class="font-bold text-green-600">
                            <?= e(ucfirst($displayStatus)) ?>
                        </p>

                    </div>

                    <div class="p-4 rounded-xl bg-slate-50">

                        <p class="text-xs text-slate-500 mb-1">
                            Account Created
                        </p>

                        <p class="font-bold text-slate-800">
                            <?= e($createdAt) ?>
                        </p>

                    </div>

                </div>

            </section>

            <!-- FOOTER -->

            <footer class="mt-8 pb-4 text-center">

                <p class="text-xs text-slate-400">
                    &copy; <?= date("Y") ?> Barangay Health Management System.
                    All rights reserved.
                </p>

            </footer>

        </div>

    </main>

</div>

<!-- =====================================
     JAVASCRIPT
====================================== -->

<script>

// =====================================
// MOBILE SIDEBAR
// =====================================

const sidebar = document.getElementById("sidebar");
const mobileOverlay = document.getElementById("mobileOverlay");

function openSidebar() {
    sidebar.classList.add("open");
    mobileOverlay.classList.add("show");
    document.body.style.overflow = "hidden";
}

function closeSidebar() {
    sidebar.classList.remove("open");
    mobileOverlay.classList.remove("show");
    document.body.style.overflow = "";
}

document.addEventListener("keydown", function(event) {
    if (event.key === "Escape") {
        closeSidebar();
    }
});

document.querySelectorAll("#sidebar a").forEach(function(link) {
    link.addEventListener("click", function() {
        if (window.innerWidth < 768) {
            closeSidebar();
        }
    });
});

window.addEventListener("resize", function() {
    if (window.innerWidth >= 768) {
        closeSidebar();
    }
});

// =====================================
// PROFILE PHOTO LIVE PREVIEW
// =====================================

const photoInput = document.getElementById("profile_photo");
const photoPreview = document.getElementById("profilePreview");
const profileInitial = document.getElementById("profileInitial");
const photoFileName = document.getElementById("photoFileName");

let currentPreviewUrl = null;

photoInput.addEventListener("change", function() {

    const file = this.files[0];

    if (!file) {
        return;
    }

    const allowedTypes = [
        "image/jpeg",
        "image/png",
        "image/webp"
    ];

    if (!allowedTypes.includes(file.type)) {
        alert("Please select a JPG, PNG, or WEBP image.");
        this.value = "";
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        alert("Maximum profile photo size is 2MB.");
        this.value = "";
        return;
    }

    if (currentPreviewUrl) {
        URL.revokeObjectURL(currentPreviewUrl);
    }

    currentPreviewUrl = URL.createObjectURL(file);

    photoPreview.src = currentPreviewUrl;
    photoPreview.classList.remove("hidden");

    profileInitial.classList.add("hidden");

    photoFileName.textContent = file.name;

});

// =====================================
// PASSWORD CONFIRMATION
// =====================================

const passwordForm = document.querySelector(
    'button[name="change_password"]'
);

if (passwordForm) {

    passwordForm.closest("form").addEventListener("submit", function(event) {

        const newPassword =
            document.getElementById("new_password").value;

        const confirmPassword =
            document.getElementById("confirm_password").value;

        if (newPassword !== confirmPassword) {
            event.preventDefault();
            alert("New passwords do not match.");
        }

    });
}

</script>

</body>
</html>
