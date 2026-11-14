
<?php
require_once "db.php";
// =====================================
// DATABASE CONNECTION
// =====================================

include 'db.php';

$errors = [];
$message = "";

$old = [
    'full_name' => '',
    'username' => '',
    'address' => '',
    'birthday' => ''
];


// =====================================
// CHECK REGISTRATION SUBMISSION
// =====================================

if (isset($_SERVER['request']) && $_SERVER['REQUEST_METHOD'] === 'POST') {

    // Your login or registration processing code goes here



    // =====================================
    // 1. FILTER INPUTS
    // =====================================

    $full_name = trim(strip_tags($_POST['full_name'] ?? ''));
    $username  = trim(strip_tags($_POST['username'] ?? ''));
    $address   = trim(strip_tags($_POST['address'] ?? ''));
    $birthday  = trim($_POST['birthday'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old = [
        'full_name' => $full_name,
        'username' => $username,
        'address' => $address,
        'birthday' => $birthday
    ];


    // =====================================
    // 2. VALIDATE FULL NAME
    // =====================================

    if (empty($full_name)) {

        $errors[] = "Full Name is required!";

    } elseif (strlen($full_name) < 3) {

        $errors[] = "Full Name must be at least 3 characters!";

    } elseif (!preg_match("/^[a-zA-ZÑñ .'-]+$/", $full_name)) {

        $errors[] = "Full Name must contain letters only!";

    }


    // =====================================
    // 3. VALIDATE USERNAME
    // =====================================

    if (empty($username)) {

        $errors[] = "Username is required!";

    } elseif (strlen($username) < 4 || strlen($username) > 20) {

        $errors[] = "Username must be 4-20 characters!";

    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username)) {

        $errors[] = "Username cannot contain spaces or special characters!";

    }


    // =====================================
    // 4. VALIDATE ADDRESS
    // =====================================

    if (empty($address)) {

        $errors[] = "Purok / Address is required!";

    } elseif (strlen($address) < 3) {

        $errors[] = "Address must be at least 3 characters!";

    }


    // =====================================
    // 5. VALIDATE BIRTHDAY
    // =====================================

    if (empty($birthday)) {

        $errors[] = "Birthday is required!";

    } else {

        $date = DateTime::createFromFormat('!Y-m-d', $birthday);

        if (!$date || $date->format('Y-m-d') !== $birthday) {

            $errors[] = "Invalid birthday format!";

        } elseif ($birthday > date('Y-m-d')) {

            $errors[] = "Birthday cannot be in the future!";

        }

    }


    // =====================================
    // 6. VALIDATE PASSWORD
    // =====================================

    if (empty($password)) {

        $errors[] = "Password is required!";

    } elseif (strlen($password) < 8) {

        $errors[] = "Password must be at least 8 characters!";

    } elseif (
        !preg_match("/[A-Za-z]/", $password) ||
        !preg_match("/[0-9]/", $password)
    ) {

        $errors[] = "Password must contain letters and numbers!";

    }


    // =====================================
    // 7. CONFIRM PASSWORD
    // =====================================

    if ($password !== $confirm) {

        $errors[] = "Passwords do not match!";

    }


    // =====================================
    // 8. CHECK USERNAME + INSERT ACCOUNT
    // =====================================

    if (empty($errors)) {

        try {

            // Check existing username
            $check = $pdo->prepare("
                SELECT id
                FROM residents
                WHERE username = ?
            ");

            $check->execute([$username]);

            if ($check->fetch()) {

                $errors[] = "Username already exists!";

            } else {

                // Hash password securely
                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // Insert resident into MySQL
                $insert = $pdo->prepare("
                    INSERT INTO residents
                    (
                        full_name,
                        username,
                        purok_address,
                        birthday,
                        password,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");

                $insert->execute([
                    $full_name,
                    $username,
                    $address,
                    $birthday,
                    $hashed_password
                ]);

                // Registration successful
                $message = "Account created successfully! Your account is pending approval.";

                // Clear old inputs
                $old = [
                    'full_name' => '',
                    'username' => '',
                    'address' => '',
                    'birthday' => ''
                ];

            }

        } catch (PDOException $e) {

            error_log($e->getMessage());

            $errors[] = "Database Error: Unable to create your account. Please try again.";

        }

    }

}

?>
