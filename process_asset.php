<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");


function generateAssetCode() {
    return 'KP' . mt_rand(100000, 999999);
}

$assetcode = generateAssetCode();
// Retrieve and sanitize POST data
$name         = trim($_POST['name'] ?? '');
$quantity     = trim($_POST['quantity'] ?? '');
$category     = trim($_POST['category'] ?? '');
$value        = isset($_POST['value']) ? floatval($_POST['value']) : 0;
$purchased_at = trim($_POST['purchased_at'] ?? '');
$unit_cost = $value / $quantity;
// Basic field validation
if (empty($name) || empty($category) || $value <= 0 || empty($purchased_at)) {
    echo json_encode([
        "status"  => "error",
        "message" => "Please fill in all required fields for Farm Asset and ensure the value is greater than zero."
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

$queryLiabilities = "SELECT SUM(amount - paid_amount) AS total_liabilities FROM liabilities";
$stmtLiabilities = $pdo->query($queryLiabilities);
$totalLiabilities = $stmtLiabilities->fetch()['total_liabilities'] ?? 0;

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

//Remaining Balance
$availableFunds = ($totalCapital + $totalSales + $totalLiability) - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $totalStock);

if ($value > $availableFunds) {
    echo json_encode([
        "status"  => "error",
        "message" => "Insufficient balance. Available balance is KShs. " . number_format($availableFunds, 2)
    ]);
    exit();
}

// Insert the new asset record
try {
    $stmt = $pdo->prepare("INSERT INTO farm_assets (name, unit_price, asset_code, quantity, category, value, purchased_at) VALUES (:name, :unit_price, :asset_code, :quantity, :category, :value, :purchased_at)");
    $stmt->execute([
        ':name'         => $name,
        ':unit_price'   => $unit_cost,
        ':asset_code'   => $assetcode,
        ':quantity'     => $quantity,
        ':category'     => $category,
        ':value'        => $value,
        ':purchased_at' => $purchased_at
    ]);
    $assetId = $pdo->lastInsertId();
    echo json_encode([
        "status"  => "success",
        "message" => "Farm Asset Added Successfully with ID: $assetId"
    ]);
    exit();
} catch (PDOException $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Error Adding Farm Asset: " . $e->getMessage()
    ]);
    exit();
}
?>
