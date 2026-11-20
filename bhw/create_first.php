
<?php
require_once __DIR__ . "/database.php";

// =============================================
// CREATE INITIAL BHW ACCOUNT
// =============================================

$full_name = "janjan Torreon";
$email = "torreonjanjan745@gmail.com";

// PALITAN ITO NG GUSTO MONG LOGIN PASSWORD
$password = "123!";

$contact_number = "09656569";
$position = "Barangay Health Worker";
$status = "Active";

// Check if account already exists
$check = mysqli_prepare(
    $conn,
    "SELECT id FROM bhw_account WHERE email = ? LIMIT 1"
);

if (!$check) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);

$result = mysqli_stmt_get_result($check);
$existing = mysqli_fetch_assoc($result);

mysqli_stmt_close($check);

if ($existing) {
    exit("BHW account already exists.");
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert account
$sql = "
    INSERT INTO bhw_account
    (full_name, email, password, contact_number, position, status)
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database Error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt,
    "ssssss",
    $full_name,
    $email,
    $hashedPassword,
    $contact_number,
    $position,
    $status
);

if (mysqli_stmt_execute($stmt)) {
    echo "BHW account created successfully!";
} else {
    echo "Failed to create account: " . mysqli_stmt_error($stmt);
}

mysqli_stmt_close($stmt);
?>
