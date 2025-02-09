<?php 
session_start();
require_once 'db.php';

// Return JSON responses
header("Content-Type: application/json");
file_put_contents("debug_log.txt", print_r($_POST, true), FILE_APPEND);

// Check if required fields exist
if (!isset($_POST['item_category'], $_POST['item_type'], $_POST['item_name'], $_POST['quantity'], $_POST['unit_price'])) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

// Extract values and sanitize input
$item_category = strtolower(trim($_POST['item_category']));
$item_type = trim($_POST['item_type']);
$item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : null;
$item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : ""; // FIXED: Do not use intval()
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$unit_price = isset($_POST['unit_price']) ? floatval($_POST['unit_price']) : 0;
$sale_date = trim($_POST['sale_date'] ?? '');
$customer_id = trim($_POST['customer_id'] ?? '');

file_put_contents("debug_log.txt", "Category: $item_category, Type: $item_type, ID: $item_id, Name: $item_name, Qty: $quantity\n", FILE_APPEND);

// Validation
if (empty($item_category) || ($item_category !== 'egg' && is_null($item_id)) || $quantity <= 0 || $unit_price <= 0 || empty($sale_date)) {
    echo json_encode(["status" => "error", "message" => "Please fill all required fields."]);
    exit();
}

$total_price = $quantity * $unit_price;

// Check available quantity
if ($item_category === "egg") {
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_produced FROM egg_production");
    $produced = intval($stmt->fetch(PDO::FETCH_ASSOC)['total_produced'] ?? 0);
    $available = $produced;
} elseif ($item_category === "asset") {
    $stmt = $pdo->prepare("SELECT quantity FROM farm_assets WHERE id = :id");
    $stmt->execute(['id' => $item_id]);
    $available = intval($stmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0);
} elseif ($item_category === "stock") {
    $stmt = $pdo->prepare("SELECT quantity FROM stock WHERE id = :id");
    $stmt->execute(['id' => $item_id]);
    $available = intval($stmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0);
} else {
    echo json_encode(["status" => "error", "message" => "Invalid item category provided."]);
    exit();
}

// Prevent overselling
if ($quantity > $available) {
    echo json_encode(["status" => "error", "message" => "Insufficient quantity available. Only $available available."]);
    exit();
}

// Default item_id for eggs
if ($item_category === 'egg') {
    $item_id = 0;
}

// Start transaction (FIXED)
$pdo->beginTransaction();

try {
    // Insert sale record
    $stmt = $pdo->prepare("INSERT INTO sales (item_type, item_id, item_name, quantity, unit_price, total_price, customer_id, sale_date, created_at)
                           VALUES (:item_category, :item_id, :item_name, :quantity, :unit_price, :total_price, :customer_id, :sale_date, NOW())");
    $stmt->execute([
        'item_category' => $item_category,
        'item_id' => $item_id,
        'item_name' => $item_name, // FIXED: Now correctly stores the name
        'quantity' => $quantity,
        'unit_price' => $unit_price,
        'total_price' => $total_price,
        'customer_id' => $customer_id,
        'sale_date' => $sale_date
    ]);
    $saleId = $pdo->lastInsertId();

    // Deduct sold quantity from stock
    if ($item_category === "asset") {
        $stmt = $pdo->prepare("UPDATE farm_assets SET quantity = quantity - :quantity WHERE id = :id");
        $stmt->execute(['quantity' => $quantity, 'id' => $item_id]);
    } elseif ($item_category === "stock") {
        $stmt = $pdo->prepare("UPDATE stock SET quantity = quantity - :quantity WHERE id = :id");
        $stmt->execute(['quantity' => $quantity, 'id' => $item_id]);
    } elseif ($item_category === "egg") {
        $stmt = $pdo->prepare("INSERT INTO egg_production (date, quantity, created_at) VALUES (:sale_date, :negative_quantity, NOW())");
        $stmt->execute(['sale_date' => $sale_date, 'negative_quantity' => -$quantity]);
    }

    // Commit transaction (FIXED)
    $pdo->commit();

    echo json_encode(["status" => "success", "message" => "Sale recorded successfully with ID: $saleId"]);
    exit();
} catch (PDOException $e) {
    $pdo->rollBack(); // Rollback in case of error
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    exit();
}

?>