
<?php

include 'db.php';

try {

    echo "Database connection successful!";

    echo "<br><br>";

    echo "Connected Database: ";

    echo $pdo->query("SELECT DATABASE()")->fetchColumn();

    echo "<br><br>";

    // Check if residents table exists
    $check = $pdo->query("SHOW TABLES LIKE 'residents'");

    if ($check->fetch()) {

        echo "Residents table exists!";

    } else {

        echo "Residents table NOT FOUND!";

    }

} catch (PDOException $e) {

    echo "Error: " . $e->getMessage();

}

?>
