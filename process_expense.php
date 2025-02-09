<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");

// Retrieve and sanitize POST data
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$amount      = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$date        = trim($_POST['date'] ?? '');

if (empty($category) || empty($description) || $amount <= 0 || empty($date)) {
    echo json_encode([
        "status" => "error",
        "message" => "Please fill all required fields and ensure the amount is greater than zero."
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
$availableFunds = ($totalCapital + $totalSales) - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $totalStock);

// Check if available funds are sufficient for the expense
if ($amount > $availableFunds) {
    echo json_encode([
        "status" => "error",
        "message" => "Insufficient balance. Available balance is KShs. " . number_format($availableFunds, 2)
    ]);
    exit();
}

// Insert the new expense record
try {
    $stmt = $pdo->prepare("INSERT INTO expenses (category, description, amount, date) VALUES (:category, :description, :amount, :date)");
    $stmt->execute([
        ':category'    => $category,
        ':description' => $description,
        ':amount'      => $amount,
        ':date'        => $date
    ]);
    $expenseId = $pdo->lastInsertId();
    echo json_encode([
        "status"  => "success",
        "message" => "Expense recorded successfully with ID: $expenseId"
    ]);
    exit();
} catch (PDOException $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Database Error: " . $e->getMessage()
    ]);
    exit();
}
?>
