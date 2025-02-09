<?php
require_once 'db.php';

// Only allow admin users
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header("Location: index.php");
    exit();
}

// Determine which section should be active. Default is 'dashboardSection'
$activeSection = $_GET['section'] ?? 'dashboardSection';

// Function to get data by date range for a given table and date column
function getData($pdo, $table, $dateColumn, $startDate = null, $endDate = null) {
    $query = "SELECT * FROM $table";
    $params = [];
    if ($startDate && $endDate) {
        $query .= " WHERE $dateColumn BETWEEN :startDate AND :endDate";
        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
    }
    $query .= " ORDER BY $dateColumn DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get date filters from GET parameters for each section
$eggStart     = $_GET['egg_start']     ?? null;
$eggEnd       = $_GET['egg_end']       ?? null;
$expenseStart = $_GET['expense_start'] ?? null;
$expenseEnd   = $_GET['expense_end']   ?? null;
$feedStart    = $_GET['feed_start']    ?? null;
$feedEnd      = $_GET['feed_end']      ?? null;
$medStart     = $_GET['med_start']     ?? null;
$medEnd       = $_GET['med_end']       ?? null;
$saleStart    = $_GET['sale_start']    ?? null;
$saleEnd      = $_GET['sale_end']      ?? null;
$assetStart   = $_GET['asset_start']   ?? null;
$assetEnd     = $_GET['asset_end']     ?? null;
$salaryStart  = $_GET['salary_start']  ?? null;
$salaryEnd    = $_GET['salary_end']    ?? null;

// Fetch data only if the section is active. (For performance, you might want to always fetch in production.)
$eggData     = getData($pdo, 'egg_production', 'date', $eggStart, $eggEnd);
$expenseData = getData($pdo, 'expenses', 'date', $expenseStart, $expenseEnd);
$feedData    = getData($pdo, 'feeds', 'purchased_at', $feedStart, $feedEnd);
$medData     = getData($pdo, 'medications', 'purchased_at', $medStart, $medEnd);
$saleData    = getData($pdo, 'sales', 'sale_date', $saleStart, $saleEnd);
$assetData   = getData($pdo, 'farm_assets', 'purchased_at', $assetStart, $assetEnd);
$salaryData  = getData($pdo, 'salaries', 'paid_on', $salaryStart, $salaryEnd);
$supplierData = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
$customerData = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC")->fetchAll();
// (Optional) Calculate some simple stats for the overview cards
$totalEggs = 0;
foreach($eggData as $row) {
    $totalEggs += $row['quantity'];
}
$totalExpenses = 0;
foreach($expenseData as $row) {
    $totalExpenses += $row['amount'];
} 
// Get date filters
$plStartDate = $_GET['pl_start_date'] ?? null;
$plEndDate   = $_GET['pl_end_date'] ?? null;
$dateFilter  = "";
$params = [];

if ($plStartDate && $plEndDate) {
    $dateFilter = " WHERE date BETWEEN :pl_start_date AND :pl_end_date";
    $params = [
        'pl_start_date' => $plStartDate,
        'pl_end_date'   => $plEndDate
    ];
}

// --- INCOME (Sales) ---
$querySales = "SELECT SUM(total_price) AS total_income FROM sales" . ($dateFilter ? " WHERE sale_date BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtSales = $pdo->prepare($querySales);
$stmtSales->execute($params);
$totalIncome = $stmtSales->fetch()['total_income'] ?? 0;

// --- EXPENSES (excluding salaries) ---
$queryExpenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE category != 'Salaries'" . ($dateFilter ? " AND date BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtExpenses = $pdo->prepare($queryExpenses);
$stmtExpenses->execute($params);
$totalExpenses = $stmtExpenses->fetch()['total_expenses'] ?? 0;

// --- SALARIES ---
$querySalaries = "SELECT SUM(salary) AS total_salaries FROM salaries" . ($dateFilter ? " WHERE paid_on BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtSalaries = $pdo->prepare($querySalaries);
$stmtSalaries->execute($params);
$totalSalaries = $stmtSalaries->fetch()['total_salaries'] ?? 0;

// --- TOTAL EXPENSES (including salaries) ---
$totalExpensesOverall = $totalExpenses + $totalSalaries;

// --- NET PROFIT (or Loss) ---
$netProfit = $totalIncome - $totalExpensesOverall;

// --- BALANCE SHEET CALCULATIONS ---
// Assets: Total assets from farm_assets table
$queryAssets = "SELECT SUM(value) AS total_assets FROM farm_assets";
$stmtAssets = $pdo->query($queryAssets);
$totalAssets = $stmtAssets->fetch()['total_assets'] ?? 0;

// Liabilities: Fetch total outstanding liabilities (loans, debts, etc.)
$queryLiabilities = "SELECT SUM(amount) AS total_liabilities FROM liabilities";
$stmtLiabilities = $pdo->query($queryLiabilities);
$totalLiabilities = $stmtLiabilities->fetch()['total_liabilities'] ?? 0;

// Capital (Initial Investment)
$capitalInvested = 50000;

// Equity Calculation
$equity = ($totalAssets + $netProfit) - $totalLiabilities;
$query = "SELECT l.description, l.amount, 
                 COALESCE(SUM(lp.amount_paid), 0) AS total_paid 
          FROM liabilities l 
          LEFT JOIN liability_payments lp ON l.id = lp.liability_id 
          GROUP BY l.id";

$stmt = $pdo->prepare($query);
$stmt->execute();
$liabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$liabilityNames = [];
$totalAmounts = [];
$totalPaid = [];

foreach ($liabilities as $liability) {
    $liabilityNames[] = $liability['description'];
    $totalAmounts[] = $liability['amount'];
    $totalPaid[] = $liability['total_paid'];
} 
// Fetch total egg production per date
$queryProduction = "SELECT date, SUM(quantity) AS total_produced 
                    FROM egg_production 
                    GROUP BY date 
                    ORDER BY date ASC";
$stmtProduction = $pdo->prepare($queryProduction);
$stmtProduction->execute();
$productions = $stmtProduction->fetchAll(PDO::FETCH_ASSOC);

// Fetch total egg sales per date
$querySales = "SELECT sale_date, SUM(quantity) AS total_sold 
               FROM sales 
               WHERE item_type = 'egg' 
               GROUP BY sale_date 
               ORDER BY sale_date ASC";
$stmtSales = $pdo->prepare($querySales);
$stmtSales->execute();
$sales = $stmtSales->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for Chart.js
$dates = [];
$producedData = [];
$soldData = [];

// Merge both data sets to have aligned dates
$dataMap = [];

// Map Production Data
foreach ($productions as $prod) {
    $dataMap[$prod['date']]['produced'] = $prod['total_produced'];
}

// Map Sales Data
foreach ($sales as $sale) {
    $dataMap[$sale['sale_date']]['sold'] = $sale['total_sold'];
}

// Sort data by date
ksort($dataMap);

// Prepare final arrays
foreach ($dataMap as $date => $values) {
    $dates[] = $date;
    $producedData[] = $values['produced'] ?? 0;
    $soldData[] = $values['sold'] ?? 0;
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

// Calculate Remaining Balance = Total Capital Invested - (Feeds + Expenses + Medications + Assets + Salaries)
$remainingBalance = $totalCapital - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalStock);

// Fetch all capital records
$stmt = $pdo->query("SELECT * FROM capital ORDER BY date_invested DESC");
$capitalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch stock records
$stmt = $pdo->query("SELECT * FROM stock ORDER BY purchase_date DESC");
$stockRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalStockPurchase = 0;
foreach ($stockRecords as $record) {
    $totalStockPurchase += $record['purchase_cost'];
}
?>