
<?php

// =============================================
// DATABASE CONNECTION
// =============================================

include "db.php";

$message = "";


// =============================================
// RESIDENT REGISTRATION
// =============================================

if (isset($_POST['register'])) {

    // =========================================
    // GET FORM DATA
    // =========================================

    $fullName = trim($_POST['fullName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $birthday = $_POST['birthday'] ?? '';
    $password = $_POST['password'] ?? '';


    // =========================================
    // VALIDATE FORM INPUTS
    // =========================================

    if (
        empty($fullName) ||
        empty($username) ||
        empty($address) ||
        empty($birthday) ||
        empty($password)
    ) {

        $message = "Please complete all fields.";

    } else {

        // =====================================
        // CHECK EXISTING USERNAME
        // =====================================

        $check = mysqli_prepare(
            $conn,
            "SELECT id FROM residents WHERE username = ?"
        );

        if (!$check) {
            die("Database Error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($check, "s", $username);

        mysqli_stmt_execute($check);

        $check_result = mysqli_stmt_get_result($check);


        // =====================================
        // IF USERNAME ALREADY EXISTS
        // =====================================

        if (mysqli_num_rows($check_result) > 0) {

            $message = "Username already exists! Please use another.";

        } else {

            // =================================
            // HASH PASSWORD
            // =================================

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // =================================
            // INSERT RESIDENT ACCOUNT
            // =================================

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO residents
                (full_name, username, purok_address, birthday, password)
                VALUES (?, ?, ?, ?, ?)"
            );

            if (!$stmt) {
                die("Database Error: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "sssss",
                $fullName,
                $username,
                $address,
                $birthday,
                $hashedPassword
            );


            // =================================
            // EXECUTE REGISTRATION
            // =================================

            if (mysqli_stmt_execute($stmt)) {

                $message = "Registration successful! Your account is pending approval.";

            } else {

                $message = "Registration failed: " .
                           mysqli_stmt_error($stmt);
            }


            // =================================
            // CLOSE INSERT STATEMENT
            // =================================

            mysqli_stmt_close($stmt);
        }


        // =====================================
        // CLOSE CHECK STATEMENT
        // =====================================

        mysqli_stmt_close($check);
    }
}

?>
