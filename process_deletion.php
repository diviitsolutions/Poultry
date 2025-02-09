<?php
session_start();
require_once 'db.php';
header("Content-Type: application/json");

// Retrieve and sanitize POST data
$entity = strtolower(trim($_POST['entity'] ?? ''));
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (empty($entity) || $id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid parameters."]);
    exit();
}

// Define a whitelist of allowed entities with their corresponding table names and primary key field
$allowedEntities = [
    "user"      => ["table" => "users", "id_field" => "id"],
    "egg"       => ["table" => "egg_production", "id_field" => "id"],
    "stock"       => ["table" => "stock", "id_field" => "id"],
    "sale"       => ["table" => "sales", "id_field" => "id"],
    "supplier"  => ["table" => "suppliers", "id_field" => "id"],
    "customer"  => ["table" => "customers", "id_field" => "id"],
    "expense"   => ["table" => "expenses", "id_field" => "id"],
    "feed"      => ["table" => "feeds", "id_field" => "id"],
    "medication"=> ["table" => "medications", "id_field" => "id"],
    "asset"     => ["table" => "farm_assets", "id_field" => "id"],
    "salary"    => ["table" => "salaries", "id_field" => "id"],
    "liability" => ["table" => "liabilities", "id_field" => "id"],
    "liability_pay" => ["table" => "liability_payments", "id_field" => "id"],
    "capital" => ["table" => "capital", "id_field" => "id"],
];

if (!array_key_exists($entity, $allowedEntities)) {
    echo json_encode(["status" => "error", "message" => "Deletion not permitted for this entity."]);
    exit();
}

$table = $allowedEntities[$entity]['table'];
$id_field = $allowedEntities[$entity]['id_field'];

try {
    $stmt = $pdo->prepare("DELETE FROM $table WHERE $id_field = :id");
    $stmt->execute(["id" => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(["status" => "success", "message" => ucfirst($entity) . " deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "No record found with the given ID."]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
exit();
?>
