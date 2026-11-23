require_once "../db.php";

$stmt = $pdo->prepare("SELECT * FROM residents WHERE id = :id");

$stmt->execute([
    ":id" => $resident_id
]);

$resident = $stmt->fetch();