
<?php
// =====================================================
// BARANGAY GUINDACPAN
// RESIDENT PROFILE MANAGEMENT
// File: tracking/profile.php
// Database: barangay_db
// Connection: Shared PDO
// =====================================================

// =====================================================
// SESSION SECURITY
// =====================================================

$isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

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

if (empty($_SESSION["resident_id"])) {
    header("Location: ../resident/login.php");
    exit;
}

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
// HELPER
// =====================================================

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$residentId = (int) $_SESSION["resident_id"];

$message = "";
$error = "";

// =====================================================
// UPLOAD DIRECTORY
// =====================================================

$uploadDirectory = __DIR__ . "/uploads/profile/";

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        $error = "Unable to create profile upload directory.";
    }
}

// =====================================================
// FETCH RESIDENT
// =====================================================

try {
    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            username,
            purok_address,
            birthday,
            status,
            profile_picture
        FROM residents
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute(["id" => $residentId]);
    $resident = $stmt->fetch();

    if (!$resident) {
        $_SESSION = [];
        session_destroy();
        header("Location: ../resident/login.php");
        exit;
    }

} catch (PDOException $ex) {
    error_log("Profile Fetch Error: " . $ex->getMessage());
    http_response_code(500);
    exit("Unable to load resident profile.");
}

// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION["resident_profile_token"])) {
    $_SESSION["resident_profile_token"] = bin2hex(random_bytes(32));
}

// =====================================================
// UPDATE PROFILE
// =====================================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error === "") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["resident_profile_token"], $token)
    ) {
        $error = "Invalid form request. Please refresh the page.";
    }

    $fullName = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $purokAddress = trim($_POST["purok_address"] ?? "");
    $birthday = trim($_POST["birthday"] ?? "");

    if ($error === "") {

        if (
            $fullName === "" ||
            $username === "" ||
            $purokAddress === ""
        ) {
            $error = "Please complete all required fields.";
        } elseif (strlen($fullName) > 100) {
            $error = "Full name must not exceed 100 characters.";
        } elseif (strlen($username) > 20) {
            $error = "Username must not exceed 20 characters.";
        } elseif (strlen($purokAddress) > 100) {
            $error = "Purok address must not exceed 100 characters.";
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            $error = "Username may only contain letters, numbers, dots, underscores, and hyphens.";
        }

        // Validate birthday
        if ($error === "" && $birthday !== "") {
            $date = DateTime::createFromFormat("Y-m-d", $birthday);

            if (!$date || $date->format("Y-m-d") !== $birthday) {
                $error = "Invalid birthday.";
            } elseif ($birthday > date("Y-m-d")) {
                $error = "Birthday cannot be in the future.";
            }
        }
    }

    // Current picture remains unchanged unless a new upload succeeds.
    $newPicture = $resident["profile_picture"];

    $uploadedAbsolutePath = null;

    // =================================================
    // PROFILE PICTURE UPLOAD
    // =================================================

    if (
        $error === "" &&
        isset($_FILES["profile_picture"]) &&
        $_FILES["profile_picture"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES["profile_picture"];

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $error = "Profile picture upload failed.";

        } elseif ($file["size"] > 3 * 1024 * 1024) {

            $error = "Profile picture must not exceed 3 MB.";

        } else {

            $imageInfo = @getimagesize($file["tmp_name"]);

            if ($imageInfo === false) {

                $error = "Please upload a valid image.";

            } else {

                $mime = $imageInfo["mime"] ?? "";

                $allowedTypes = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                if (!isset($allowedTypes[$mime])) {

                    $error = "Only JPG, PNG, and WEBP images are allowed.";

                } else {

                    $extension = $allowedTypes[$mime];
                    $filename = bin2hex(random_bytes(16)) . "." . $extension;

                    $destination = $uploadDirectory . $filename;

                    if (move_uploaded_file($file["tmp_name"], $destination)) {

                        $newPicture = "uploads/profile/" . $filename;
                        $uploadedAbsolutePath = $destination;

                    } else {

                        $error = "Unable to save the uploaded picture.";

                    }
                }
            }
        }
    }

    // =================================================
    // SAVE PROFILE TO DATABASE
    // =================================================

    if ($error === "") {

        try {

            $stmt = $pdo->prepare("
                UPDATE residents
                SET
                    full_name = :full_name,
                    username = :username,
                    purok_address = :purok_address,
                    birthday = :birthday,
                    profile_picture = :profile_picture
                WHERE id = :id
            ");

            $stmt->execute([
                ":full_name" => $fullName,
                ":username" => $username,
                ":purok_address" => $purokAddress,
                ":birthday" => $birthday !== "" ? $birthday : null,
                ":profile_picture" => $newPicture,
                ":id" => $residentId
            ]);

            // Update session information
            $_SESSION["resident_name"] = $fullName;
            $_SESSION["resident_username"] = $username;

            // Remove previous uploaded picture after successful update
            if (
                $uploadedAbsolutePath !== null &&
                !empty($resident["profile_picture"])
            ) {
                $oldPicture = $resident["profile_picture"];

                if (
                    strpos($oldPicture, "uploads/profile/") === 0 &&
                    basename($oldPicture) === $oldPicture
                ) {
                    $oldPath = $uploadDirectory . $oldPicture;

                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }

            // Refresh form data
            $resident["full_name"] = $fullName;
            $resident["username"] = $username;
            $resident["purok_address"] = $purokAddress;
            $resident["birthday"] = $birthday;
            $resident["profile_picture"] = $newPicture;

            // Rotate token after successful update
            $_SESSION["resident_profile_token"] = bin2hex(random_bytes(32));

            $message = "Your profile has been updated successfully.";

        } catch (PDOException $ex) {

            // Remove newly uploaded file if database update failed
            if (
                $uploadedAbsolutePath !== null &&
                is_file($uploadedAbsolutePath)
            ) {
                @unlink($uploadedAbsolutePath);
            }

            if ($ex->getCode() === "23000") {
                $error = "That username is already being used by another resident.";
            } else {
                error_log("Profile Update Error: " . $ex->getMessage());
                $error = "Unable to update profile. Please try again.";
            }
        }
    }
}

// =====================================================
// PROFILE IMAGE URL
// =====================================================

$picture = !empty($resident["profile_picture"])
    ? $resident["profile_picture"]
    : "../asset/default-profile.png";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | Barangay Guindacpan</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: Inter, sans-serif;
        }

        .sidebar {
            transition: transform .3s ease;
        }
    </style>

</head>

<body class="bg-slate-50 text-slate-800">

<div class="min-h-screen flex">

    <!-- MOBILE OVERLAY -->
    <div id="overlay"
         onclick="toggleSidebar()"
         class="fixed inset-0 bg-black/40 z-40 hidden lg:hidden"></div>

    <!-- SIDEBAR -->
    <aside id="sidebar"
           class="sidebar fixed lg:sticky top-0 left-0 z-50
           w-72 h-screen bg-slate-950 text-white
           -translate-x-full lg:translate-x-0 flex flex-col">

        <div class="p-6 border-b border-white/10">

            <div class="flex items-center gap-3">

                <div class="w-12 h-12 bg-white rounded-xl overflow-hidden">
                    <img src="../asset/logo.jpg"
                         alt="Barangay Logo"
                         class="w-full h-full object-contain">
                </div>

                <div>
                    <h1 class="font-bold text-lg">Barangay Guindacpan</h1>
                    <p class="text-xs text-slate-400">Resident Portal</p>
                </div>

            </div>

        </div>

        <nav class="flex-1 p-5 space-y-2">

            <p class="text-xs uppercase tracking-wider text-slate-500 font-bold mb-4">
                Main Menu
            </p>

            <a href="dashboard.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
               text-slate-300 hover:bg-white/10">
                <i data-lucide="layout-dashboard"></i>
                Dashboard
            </a>

            <a href="track.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
               text-slate-300 hover:bg-white/10">
                <i data-lucide="search"></i>
                Track Request
            </a>

            <a href="profile.php"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
               bg-blue-600 text-white">
                <i data-lucide="user-round"></i>
                My Profile
            </a>

        </nav>

        <div class="p-5 border-t border-white/10">

            <a href="../resident/logout.php"
               onclick="return confirm('Are you sure you want to logout?')"
               class="flex items-center gap-3 px-4 py-3 rounded-xl
               text-red-300 hover:bg-red-500/10">

                <i data-lucide="log-out"></i>
                Logout

            </a>

        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="flex-1 min-w-0">

        <!-- TOPBAR -->
        <header class="bg-white border-b border-slate-200 px-5 md:px-8 py-4">

            <div class="flex items-center gap-3">

                <button onclick="toggleSidebar()"
                        class="lg:hidden p-2 rounded-lg hover:bg-slate-100">
                    <i data-lucide="menu"></i>
                </button>

                <div>
                    <h2 class="font-bold text-xl md:text-2xl">My Profile</h2>
                    <p class="text-sm text-slate-500">Manage your resident information</p>
                </div>

            </div>

        </header>

        <div class="max-w-5xl mx-auto p-5 md:p-8">

            <!-- PAGE INTRO -->
            <div class="mb-7">
                <h2 class="text-2xl font-extrabold">Profile Settings</h2>
                <p class="text-sm text-slate-500 mt-1">
                    Update your personal information and profile picture.
                </p>
            </div>

            <!-- SUCCESS MESSAGE -->
            <?php if ($message !== ""): ?>

                <div class="mb-6 p-4 rounded-xl bg-green-50
                            border border-green-200 text-green-700
                            flex items-center gap-3">

                    <i data-lucide="check-circle"></i>

                    <p class="text-sm font-semibold"><?= e($message) ?></p>

                </div>

            <?php endif; ?>

            <!-- ERROR MESSAGE -->
            <?php if ($error !== ""): ?>

                <div class="mb-6 p-4 rounded-xl bg-red-50
                            border border-red-200 text-red-700
                            flex items-center gap-3">

                    <i data-lucide="alert-circle"></i>

                    <p class="text-sm font-semibold"><?= e($error) ?></p>

                </div>

            <?php endif; ?>

            <!-- PROFILE FORM -->
            <form method="POST"
                  action=""
                  enctype="multipart/form-data"
                  class="space-y-6">

                <input type="hidden"
                       name="csrf_token"
                       value="<?= e($_SESSION["resident_profile_token"]) ?>">

                <!-- PROFILE PICTURE -->
                <section class="bg-white border border-slate-200
                                rounded-2xl p-6 md:p-8">

                    <h3 class="font-bold text-lg mb-6">Profile Picture</h3>

                    <div class="flex flex-col sm:flex-row
                                items-center gap-6">

                        <div class="relative">

                            <img id="profilePreview"
                                 src="<?= e($picture) ?>"
                                 onerror="this.onerror=null;this.src='../asset/default-profile.png'"
                                 alt="Resident Profile Picture"
                                 class="w-32 h-32 rounded-full object-cover
                                 border-4 border-slate-100 shadow-sm">

                            <label for="profile_picture"
                                   class="absolute bottom-0 right-0
                                   bg-blue-600 text-white p-3 rounded-full
                                   cursor-pointer border-4 border-white
                                   hover:bg-blue-700">

                                <i data-lucide="camera" class="w-5 h-5"></i>

                            </label>

                        </div>

                        <div class="text-center sm:text-left">

                            <h4 class="font-bold text-slate-800">
                                <?= e($resident["full_name"]) ?>
                            </h4>

                            <p class="text-sm text-slate-500 mt-1">
                                Upload a new profile picture.
                            </p>

                            <p class="text-xs text-slate-400 mt-2">
                                JPG, PNG, or WEBP · Maximum 3 MB
                            </p>

                            <input type="file"
                                   id="profile_picture"
                                   name="profile_picture"
                                   accept="image/jpeg,image/png,image/webp"
                                   class="hidden"
                                   onchange="previewImage(event)">

                            <label for="profile_picture"
                                   class="inline-flex items-center gap-2
                                   mt-4 bg-slate-100 hover:bg-slate-200
                                   text-slate-700 px-4 py-2 rounded-lg
                                   text-sm font-semibold cursor-pointer">

                                <i data-lucide="upload" class="w-4 h-4"></i>
                                Choose Picture

                            </label>

                        </div>

                    </div>

                </section>

                <!-- PERSONAL INFORMATION -->
                <section class="bg-white border border-slate-200
                                rounded-2xl p-6 md:p-8">

                    <h3 class="font-bold text-lg mb-6">
                        Personal Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- FULL NAME -->
                        <div>

                            <label class="block text-sm font-semibold mb-2">
                                Full Name <span class="text-red-500">*</span>
                            </label>

                            <input type="text"
                                   name="full_name"
                                   maxlength="100"
                                   required
                                   value="<?= e($resident["full_name"]) ?>"
                                   class="w-full border border-slate-300
                                   rounded-xl px-4 py-3 outline-none
                                   focus:ring-2 focus:ring-blue-500">

                        </div>

                        <!-- USERNAME -->
                        <div>

                            <label class="block text-sm font-semibold mb-2">
                                Username <span class="text-red-500">*</span>
                            </label>

                            <input type="text"
                                   name="username"
                                   maxlength="20"
                                   required
                                   value="<?= e($resident["username"]) ?>"
                                   class="w-full border border-slate-300
                                   rounded-xl px-4 py-3 outline-none
                                   focus:ring-2 focus:ring-blue-500">

                        </div>

                        <!-- PUROK ADDRESS -->
                        <div>

                            <label class="block text-sm font-semibold mb-2">
                                Purok Address <span class="text-red-500">*</span>
                            </label>

                            <input type="text"
                                   name="purok_address"
                                   maxlength="100"
                                   required
                                   value="<?= e($resident["purok_address"]) ?>"
                                   class="w-full border border-slate-300
                                   rounded-xl px-4 py-3 outline-none
                                   focus:ring-2 focus:ring-blue-500">

                        </div>

                        <!-- BIRTHDAY -->
                        <div>

                            <label class="block text-sm font-semibold mb-2">
                                Birthday
                            </label>

                            <input type="date"
                                   name="birthday"
                                   max="<?= date("Y-m-d") ?>"
                                   value="<?= e($resident["birthday"]) ?>"
                                   class="w-full border border-slate-300
                                   rounded-xl px-4 py-3 outline-none
                                   focus:ring-2 focus:ring-blue-500">

                        </div>

                    </div>

                    <!-- ACCOUNT STATUS -->
                    <div class="mt-6 pt-6 border-t border-slate-100">

                        <label class="block text-sm font-semibold mb-2">
                            Account Status
                        </label>

                        <div class="inline-flex items-center gap-2
                                    px-4 py-2 rounded-lg bg-green-50
                                    text-green-700 text-sm font-bold">

                            <i data-lucide="shield-check" class="w-4 h-4"></i>

                            <?= e($resident["status"]) ?>

                        </div>

                        <p class="text-xs text-slate-400 mt-2">
                            Your account status is managed by the Barangay Administrator.
                        </p>

                    </div>

                </section>

                <!-- ACTION BUTTONS -->
                <div class="flex flex-col-reverse sm:flex-row
                            justify-end gap-3">

                    <a href="dashboard.php"
                       class="px-6 py-3 rounded-xl border
                       border-slate-300 text-slate-600
                       font-semibold text-center hover:bg-slate-100">

                        Cancel

                    </a>

                    <button type="submit"
                            class="inline-flex items-center
                            justify-center gap-2 px-6 py-3
                            rounded-xl bg-blue-600 hover:bg-blue-700
                            text-white font-bold transition">

                        <i data-lucide="save" class="w-5 h-5"></i>

                        Save Changes

                    </button>

                </div>

            </form>

            <footer class="text-center text-xs text-slate-400 py-8">
                © <?= date("Y") ?> Barangay Guindacpan. All Rights Reserved.
            </footer>

        </div>

    </main>

</div>

<script>
    lucide.createIcons();

    // MOBILE SIDEBAR
    function toggleSidebar() {
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("overlay");

        sidebar.classList.toggle("-translate-x-full");
        overlay.classList.toggle("hidden");
    }

    // PROFILE IMAGE PREVIEW
    function previewImage(event) {
        const file = event.target.files[0];

        if (!file) return;

        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            alert("Please select a JPG, PNG, or WEBP image.");
            event.target.value = "";
            return;
        }

        if (file.size > 3 * 1024 * 1024) {
            alert("Image must not exceed 3 MB.");
            event.target.value = "";
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            document.getElementById("profilePreview").src = e.target.result;
        };

        reader.readAsDataURL(file);
    }
</script>

</body>
</html>
