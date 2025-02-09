<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");

// Retrieve and sanitize POST data
$user_id = trim($_POST['user_id'] ?? '');
$salary  = isset($_POST['salary']) ? floatval($_POST['salary']) : 0;
$paid_on = trim($_POST['paid_on'] ?? '');

if (empty($user_id) || $salary <= 0 || empty($paid_on)) {
    echo json_encode([
        "status"  => "error",
        "message" => "Please fill in all required fields for Salary and ensure the salary is greater than zero."
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
// Check if available balance is sufficient for the salary expense
if ($salary > $availableFunds) {
    echo json_encode([
        "status"  => "error",
        "message" => "Insufficient balance. Available balance is KShs. " . number_format($availableFunds, 2)
    ]);
    exit();
}

// Insert the salary record if funds are sufficient
try {
    $stmt = $pdo->prepare("INSERT INTO salaries (user_id, salary, paid_on) VALUES (:user_id, :salary, :paid_on)");
    $stmt->execute([
        'user_id' => $user_id,
        'salary'  => $salary,
        'paid_on' => $paid_on
    ]);
    $salaryId = $pdo->lastInsertId();
    echo json_encode([
        "status"  => "success",
        "message" => "Salary Added Successfully with ID: $salaryId"
    ]);
    exit();
} catch (PDOException $e) {
    echo json_encode([
        "status"  => "error",
        "message" => "Error Adding Salary: " . $e->getMessage()
    ]);
    exit();
}
?>
