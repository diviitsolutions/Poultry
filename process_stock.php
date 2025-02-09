<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");

// Retrieve and sanitize POST data
$stockType = trim($_POST['stock_type'] ?? '');
$otherDescription = trim($_POST['other_description'] ?? '');
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
$purchaseCost = isset($_POST['purchase_cost']) ? floatval($_POST['purchase_cost']) : 0;
$purchaseDate = trim($_POST['purchase_date'] ?? '');
$mortality = isset($_POST['mortality']) ? intval($_POST['mortality']) : 0;

if (empty($stockType) || $quantity <= 0 || $purchaseCost <= 0 || empty($purchaseDate)) {
    echo json_encode([
        "status" => "error",
        "message" => "Please fill all required fields and ensure quantity and cost are greater than zero."
    ]);
    exit();
}

// Random descriptions for stock types other than "Others"
$randomDescriptions = [
    "Quality batch received.",
    "Healthy and active stock.",
    "New stock added to inventory.",
    "Fresh arrivals for farm expansion.",
    "Premium stock acquisition."
];

// Assign random description if stock type is not "Others"
if ($stockType !== "Others") {
    $otherDescription = $randomDescriptions[array_rand($randomDescriptions)];
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

// Check if available balance is sufficient for the stock purchase cost
if ($purchaseCost > $availableBalance) {
    echo json_encode([
        "status" => "error",
        "message" => "Insufficient balance. Available balance is KShs. " . number_format($availableBalance, 2)
    ]);
    exit();
}

try {
    // Always insert a new record
    $unitPrice = $purchaseCost / $quantity;
    $stmt = $pdo->prepare("INSERT INTO stock (stock_type, other_description, quantity, unit_price, purchase_cost, purchase_date, mortality, created_at) 
        VALUES (:stock_type, :other_description, :quantity, :unit_price, :purchase_cost, :purchase_date, :mortality, NOW())");
    $stmt->execute([
        ':stock_type' => $stockType,
        ':other_description' => $otherDescription,
        ':quantity' => $quantity,
        ':unit_price' => $unitPrice,
        ':purchase_cost' => $purchaseCost,
        ':purchase_date' => $purchaseDate,
        ':mortality' => $mortality
    ]);

    echo json_encode([
        "status" => "success",
        "message" => "Stock purchase recorded successfully."
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


