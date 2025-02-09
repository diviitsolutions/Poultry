<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");

// Retrieve and sanitize POST data
$name         = trim($_POST['name'] ?? '');
$supplier_id  = $_POST['supplier_id'] ?: null; // supplier_id is optional
$quantity     = isset($_POST['quantity']) ? floatval($_POST['quantity']) : 0;
$unit         = trim($_POST['unit'] ?? '');
$cost         = isset($_POST['cost']) ? floatval($_POST['cost']) : 0;
$purchased_at = trim($_POST['purchased_at'] ?? '');

if (empty($name) || $quantity <= 0 || empty($unit) || $cost <= 0 || empty($purchased_at)) {
    echo json_encode([
        "status" => "error",
        "message" => "Please fill in all required fields for Medication Purchase and ensure quantity and cost are greater than zero."
    ]);
    exit();
}


// Calculate Total Capital Invested
$stmt = $pdo->query("SELECT SUM(amount) AS total_capital FROM capital");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCapital = $row['total_capital'] ?? 0;

// Calculate totals from other tables
$stmt = $pdo->query("SELECT SUM(cost) AS total_feeds FROM feeds");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalFeeds = $row['total_feeds'] ?? 0;

$stmt = $pdo->query("SELECT SUM(amount) AS total_expenses FROM expenses");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalExpenses = $row['total_expenses'] ?? 0;

$stmt = $pdo->query("SELECT SUM(amount_paid) AS total_liability_payment FROM liability_payments");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalliabilitypayments = $row['total_liability_payment'] ?? 0;

$stmt = $pdo->query("SELECT SUM(purchase_cost) AS total_stock FROM stock");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalStock = $row['total_stock'] ?? 0;

$stmt = $pdo->query("SELECT SUM(cost) AS total_medications FROM medications");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalMedications = $row['total_medications'] ?? 0;

$stmt = $pdo->query("SELECT SUM(value) AS total_assets FROM farm_assets");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalAssets = $row['total_assets'] ?? 0;

$stmt = $pdo->query("SELECT SUM(salary) AS total_salaries FROM salaries");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSalaries = $row['total_salaries'] ?? 0;

$stmt = $pdo->query("SELECT SUM(total_price) AS total_sales FROM sales");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSales = $row['total_sales'] ?? 0;

// Calculate Remaining Balance = Total Capital Invested - (Feeds + Expenses + Medications + Assets + Salaries)
$availableBalance = ($totalCapital + $totalSales) - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $totalStock);

// Check if available balance is sufficient for the medication purchase cost
if ($cost > $availableBalance) {
    echo json_encode([
        "status" => "error",
        "message" => "Insufficient balance. Available balance is KShs. " . number_format($availableBalance, 2)
    ]);
    exit();
}

// Insert the medication purchase record
try {
    $stmt = $pdo->prepare("INSERT INTO medications (name, supplier_id, quantity, unit, cost, purchased_at) VALUES (:name, :supplier_id, :quantity, :unit, :cost, :purchased_at)");
    $stmt->execute([
        ':name'         => $name,
        ':supplier_id'  => $supplier_id,
        ':quantity'     => $quantity,
        ':unit'         => $unit,
        ':cost'         => $cost,
        ':purchased_at' => $purchased_at
    ]);
    $medicationId = $pdo->lastInsertId();
    echo json_encode([
        "status" => "success",
        "message" => "Medication Purchase Added Successfully with ID: $medicationId"
    ]);
    exit();
} catch (PDOException $e) {
    echo json_encode([
        "status" => "error",
        "message" => "Database Error: " . $e->getMessage()
    ]);
    exit();
}
?>
