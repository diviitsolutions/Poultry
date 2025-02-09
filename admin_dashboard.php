<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    header("Location: index.php");
    exit();
}

$activeSection = $_GET['section'] ?? 'dashboardSection';
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


$totalEggs = 0;
foreach($eggData as $row) {
    $totalEggs += $row['quantity'];
}
$totalExpenses = 0;
foreach($expenseData as $row) {
    $totalExpenses += $row['amount'];
} 

function getTotalEggsProduced(PDO $pdo) {
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) AS total_produced FROM egg_production WHERE quantity > 0");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return intval($row['total_produced'] ?? 0);
}

$totalEggsProduced = getTotalEggsProduced($pdo);


// --- INCOME (Sales) ---
$querySales = "SELECT SUM(total_price) AS total_income FROM sales" . ($dateFilter ? " WHERE sale_date BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtSales = $pdo->prepare($querySales);
$stmtSales->execute($params);
$totalIncome = $stmtSales->fetch()['total_income'] ?? 0;

$queryStocks = "SELECT SUM(quantity) AS total_stock FROM stock";
$stmtStocks = $pdo->prepare($queryStocks);
$stmtStocks->execute();
$totalstocking = $stmtStocks->fetch()['total_stock'] ?? 0;

$queryMortalities = "SELECT SUM(quantity) AS total_mortality FROM mortalities";
$stmtMortalities = $pdo->prepare($queryMortalities);
$stmtMortalities->execute();
$totalmortalities = $stmtMortalities->fetch()['total_mortality'] ?? 0;


$allstock = ($totalstocking + $totalmortalities); 

if ($totalstocking > 0) {
    $percentageMortality = ($totalmortalities / $allstock) * 100;
} else {
    $percentageMortality = 0; 
}

$percentageMortalityFormatted = number_format($percentageMortality, 2);


$queryExpenses = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE category != 'Salaries'" . ($dateFilter ? " AND date BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtExpenses = $pdo->prepare($queryExpenses);
$stmtExpenses->execute($params);
$totalExpenses = $stmtExpenses->fetch()['total_expenses'] ?? 0;


// Fetch the logged-in user's data
$queryUsers = "SELECT * FROM users WHERE id = :userId";
$stmtUsers = $pdo->prepare($queryUsers);
$stmtUsers->execute(['userId' => $_SESSION['user_id']]); // Use $_SESSION['user_id'] to fetch the logged-in user
$Users = $stmtUsers->fetch();

// Check if user data was found
if (!$Users) {
    die("User not found.");
}

$querySalaries = "SELECT SUM(salary) AS total_salaries FROM salaries" . ($dateFilter ? " WHERE paid_on BETWEEN :pl_start_date AND :pl_end_date" : "");
$stmtSalaries = $pdo->prepare($querySalaries);
$stmtSalaries->execute($params);
$totalSalaries = $stmtSalaries->fetch()['total_salaries'] ?? 0;

// --- TOTAL EXPENSES (including salaries) ---
$totalExpensesOverall = $totalExpenses + $totalSalaries;


// --- BALANCE SHEET CALCULATIONS ---
// Assets: Total assets from farm_assets table
$queryAssets = "SELECT SUM(value) AS total_assets FROM farm_assets";
$stmtAssets = $pdo->query($queryAssets);
$totalAssets = $stmtAssets->fetch()['total_assets'] ?? 0;

// Liabilities: Fetch total outstanding liabilities (loans, debts, etc.)
$queryLiabilities = "SELECT SUM(amount - paid_amount) AS total_liabilities FROM liabilities";
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
$remainingBalance = ($totalCapital + $totalSales + $totalLiabilities) - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $totalStock);

$totalAss = ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $remainingBalance + $totalStock); 
$totalcr = ($totalCapital + $totalSales + $totalLiabilities);


$totalExp = ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalliabilitypayments + $totalStock);


$totalExpe = ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalStock);

$netProfit = $totalSales - $totalExpe ;

// Fetch all capital records
$stmt = $pdo->query("SELECT * FROM capital ORDER BY date_invested DESC");
$capitalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT s.id, s.stock_type, s.other_description, s.quantity, 
                 s.purchase_cost, s.purchase_date, 
                 COALESCE(SUM(m.quantity), 0) AS mortality
          FROM stock s
          LEFT JOIN mortalities m ON s.id = m.stock_id
          GROUP BY s.id
          ORDER BY s.purchase_date DESC";

$stmt = $pdo->query($query);
$stockRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Define a function to fetch available items for a given category.
function getAvailableItems($category, PDO $pdo) {
    $items = [];
    if ($category === 'egg') {
        // Calculate available eggs as total produced minus total sold
        $stmt = $pdo->query("SELECT COALESCE(SUM(quantity),0) AS total_produced FROM egg_production");
        $producedData = $stmt->fetch(PDO::FETCH_ASSOC);
        $produced = isset($producedData['total_produced']) ? intval($producedData['total_produced']) : 0;
        
        $available = $produced;
        $items[] = ['id' => 'egg', 'name' => 'Eggs', 'available_quantity' => $available];
    } elseif ($category === 'asset') {
        $stmt = $pdo->query("SELECT id, name, quantity AS available_quantity FROM farm_assets WHERE quantity > 0 ORDER BY name ASC");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($category === 'stock') {
        $stmt = $pdo->query("SELECT id, stock_type AS name, COALESCE(SUM(quantity),0) AS available_quantity FROM stock GROUP BY stock_type HAVING available_quantity > 0"); 
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    return $items;
}

// Pre-fetch items for assets and stock; eggs is computed separately.
$eggItems = getAvailableItems('egg', $pdo);
$assetItems = getAvailableItems('asset', $pdo);
$stockItems = getAvailableItems('stock', $pdo);

// Prepare a JS variable with these data.
$preloadedItems = [
    'egg' => $eggItems,
    'asset' => $assetItems,
    'stock' => $stockItems
];

function generateBatchID() {
    return 'EP' . mt_rand(100000, 999999);
}

// Generate a unique batch ID
$batchID = generateBatchID();

// Ensure the batch ID is unique in the database (Optional)
require_once 'db.php';
$stmt = $pdo->prepare("SELECT COUNT(*) FROM egg_production WHERE batch_id = :batch_id");
$stmt->execute([':batch_id' => $batchID]);

while ($stmt->fetchColumn() > 0) {
    // If the batch ID already exists, generate a new one
    $batchID = generateBatchID();
}
$grossProfit = ($totalSales - $totalStock);
$operatingExpenses = ($totalFeeds + $totalMedications + $totalExpenses + $totalSalaries);
$operatingProfit = ($grossProfit - $operatingExpenses);
$profitbeforeTax = ($operatingProfit - $totalliabilitypayments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Kebo Poultry</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link href="https://fonts.googleapis.com/css2?family=Afacad+Flux:wght@100..1000&family=Inconsolata:wght@200..900&family=Playfair:ital,opsz,wght@0,5..1200,300..900;1,5..1200,300..900&family=Quattrocento+Sans:ital,wght@0,400;0,700;1,400;1,700&display=swap" rel="stylesheet">
 <script src="db.js"></script>
<script src="sync.js"></script>
<script src="install.js"></script>
   <style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
body {
   font-family: "Afacad Flux", serif;
  font-optical-sizing: auto;
  font-weight: <weight>;
  font-style: normal;
  font-variation-settings:
    "wdth" 100;
  background: url('https://poultry.divi.co.ke/includes/indexbg.jpg') no-repeat center center fixed;
      background-size: cover;
      overflow-x: hidden;
}
.dashboard-summary {
    display: flex;
    gap: 15px;
}
.chart-container {
    width: 100%;
    max-width: 800px;       
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    margin: 20px auto; 
}
/* Wrapper */
.wrapper {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 250px;
  background: #343a40;
  color: #fff;
  transition: transform 0.3s ease;
  overflow-y: auto; 
  scrollbar-width: thin;
}
.sidebar h2 {
  text-align: center;
  padding: 20px 0;
  border-bottom: 1px solid #495057;
}
.sidebar ul {
  list-style: none;
}
.sidebar ul li {
  border-bottom: 1px solid #495057;
}
.sidebar ul li a {
  display: block;
  color: #fff;
  padding: 15px 20px;
  text-decoration: none;
}
.sidebar ul li a:hover {
  background: #495057;
}

/* Topbar */
.topbar {
  background: #fff;
  padding: 10px 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
}
.toggle-btn {
  font-size: 20px;
  cursor: pointer;
  display: none;
}

/* Content */
.content {
  flex: 1;
  padding: 20px;
  overflow-x: hidden;
}

/* Cards */
.card-container {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}
.card {
  background: green;
  color: #fff;
  flex: 1;
  min-width: 200px;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  text-align: center;
}
.card h3 {
    font-size: 16px;
    color: #fff;
}
.card p {
    font-size: 20px;
    font-weight: bold;
    color: #fff;
}
.card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
  }
  .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin-bottom: 20px;
  }
  /* Data Table Styling */
  .data-table {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .data-table th, .data-table td {
    border: 1px solid #ccc;
    padding: 8px;
    text-align: left;
  }
  .data-table th {
    background: #f8f9fa;
  }
/* Sections */
.section {
  display: none;
  background: #fff;
  padding: 20px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.section.active {
  display: block;
}

/* Buttons */
.btn {
  padding: 10px;
  border: none;
  cursor: pointer;
  font-size: 16px;
  border-radius: 5px;
  margin: 5px;
}
.btn-primary {
  background: #007bff;
  color: white;
}
.btn-success {
  background: #28a745;
  color: white;
}
.btn-warning {
  background: #ffc107;
  color: black;
}
.btn-danger {
  background: #dc3545;
  color: white;
}
.btn-secondary {
  background: #6c757d;
  color: white;
}

/* Forms */
.form-container {
  background: #fff;
  padding: 15px;
  margin-top: 20px;
  border-radius: 5px;
}
.form-container form label {
  display: block;
  margin: 8px 0 4px;
}
.form-container form input,
.form-container form select,
.form-container form textarea {
  width: 100%;
  padding: 8px;
  margin-bottom: 10px;
  border: 1px solid #ccc;
  border-radius: 4px;
}

/* Filter Forms */
.filter-form {
  margin-bottom: 15px;
}
.filter-form input {
  padding: 5px;
  margin-right: 10px;
}

.table-container {
  overflow-x: auto;
}

.table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    margin-top: 20px;
}

table {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-collapse: collapse;
    margin-top: 20px;
}

th, td {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
}

th {
    background: #f8f9fa;
}

/* Data Tables */
.data-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}
.data-table th, .data-table td {
  border: 1px solid #ccc;
  padding: 8px;
  text-align: left;
}

/* Report Tables and Cards */
.report-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
  text-align: center;
}
.report-table th, .report-table td {
  border: 1px solid #ccc;
  padding: 10px;
}
.report-card {
  margin-bottom: 20px;
  padding: 15px;
  border: 1px solid #ccc;
  border-radius: 8px;
  background: #e9ecef;
}

/* Print Button */
.print-btn {
  background: #007bff;
  color: #fff;
  border: none;
  padding: 8px 12px;
  border-radius: 4px;
  cursor: pointer;
  margin-top: 10px;
}

/* Profit/Loss Colors */
.profit {
  color: green;
}
.loss {
  color: red;
}

/* Tabs */
.tab-container {
  margin-bottom: 20px;
}
.tab-buttons {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}
.tab-buttons button {
  padding: 10px 20px;
  border: none;
  cursor: pointer;
  background: #343a40;
  color: #fff;
  border-radius: 4px;
}
.tab-buttons button.active {
  background: #007bff;
}

/* Modal Styles */
.modal {
  display: none;
  position: fixed;
  z-index: 1;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background-color: rgba(0,0,0,0.4);
}
.modal-content {
  background-color: white;
  padding: 20px;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  margin: 15% auto;
  position: relative;
}
.close {
  position: absolute;
  top: 10px;
  right: 20px;
  font-size: 24px;
  cursor: pointer;
}

/* Responsive Styles */
@media (max-width: 1024px) {
  .wrapper {
    flex-direction: column;
  }
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    background-color: rgba(44, 62, 80, 0.95);
    width: 60%;
    height: 100vh;
    transform: translateX(-100%);
    overflow-y: auto; 
    padding-top: 60px;
    padding-left: 20px;
    z-index: 1000;
  }
  .content {
    padding: 10px;
  }
  .modal-content {
    width: 95%;
  }
}
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    background-color: rgba(44, 62, 80, 0.95);
    width: 60%;
    height: 100vh;
    transform: translateX(-100%);
    overflow-y: auto; 
    padding-top: 60px;
    padding-left: 20px;
    z-index: 1000;
  }
  h1 {
      font-size: 20px;
  }
  .dashboard-summary {
    flex-direction: column;
    gap: 15px;
}
  .card-container {
      flex-direction: column;
    }
  .sidebar.active {
    transform: translateX(0);
    width: 70%;
    height: 100%;
  }
  .toggle-btn {
    display: block;
  }
   .chart-container {
        padding: 15px;
    }
  .wrapper {
    flex-direction: column;
  }
}
@media (max-width: 480px) {
  .btn {
    font-size: 14px;
    padding: 8px;
  }
  .modal-content {
    padding: 15px;
  }
  .table-container, table {
    font-size: 12px;
  }
}
</style>
<script>
var availableItems = <?php echo json_encode($preloadedItems); ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>

</head>
<body>

<div class="topbar">
    <span class="toggle-btn"><i class="fas fa-bars"></i></span>
    <h1>Kebo Poultry</h1>
    <div>
        <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
       <button style="background-color: red; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
  <a href="logout.php" style="color: white; text-decoration: none;">Logout</a>
</button>

    </div>
</div>

<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Dashboard</h2>
        <ul>
            <li><a href="?section=dashboardSection"> <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="?section=capitalSection"> <i class="fas fa-coins"></i> Capital</a></li>
            <li><a href="?section=stockSection"> <i class="fas fa-boxes"></i> Stock</a></li>
            <li><a href="?section=eggProductionSection"> <i class="fas fa-egg"></i> Egg Production</a></li>
            <li><a href="?section=expensesSection"> <i class="fas fa-money-bill-wave"></i> Expenses</a></li>
            <li><a href="?section=feedsSection"> <i class="fas fa-drumstick-bite"></i> Feeds</a></li>
            <li><a href="?section=medicationsSection"> <i class="fas fa-pills"></i> Medications</a></li>
            <li><a href="?section=salesSection"> <i class="fas fa-shopping-cart"></i> Sales</a></li>
            <li><a href="?section=assetsSection"> <i class="fas fa-building"></i> Farm Assets</a></li>
            <li><a href="?section=salariesSection"> <i class="fas fa-user-cog"></i> Salaries</a></li>
            <li><a href="?section=usersSection"> <i class="fas fa-users"></i> Manage Users</a></li> 
            <li><a href="?section=suppliersSection"> <i class="fas fa-truck"></i> Suppliers</a></li>
            <li><a href="?section=customersSection"> <i class="fas fa-user-friends"></i> Customers</a></li>
            <li><a href="?section=reportsSection"> <i class="fas fa-chart-line"></i> Reports</a></li>
             <li><a href="?section=liabilities-section"> <i class="fas fa-file-invoice-dollar"></i> Liabilities</a></li>

        </ul>
        <p>Version: 1.2.4</p>
        
<button id="installBtn" >Install App</button>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <!-- Dashboard Overview Section -->
        <section id="dashboardSection" class="section <?php echo ($activeSection === 'dashboardSection') ? 'active' : ''; ?>">
            <h2>Dashboard Overview</h2>
              <h1>Welcome, <?php echo htmlspecialchars($Users['name']); ?>!</h1>
    <h2>Last Login: <?php echo $Users['last_login'] ? htmlspecialchars($Users['last_login']) : 'Never'; ?></h2>
            <div class="card-container">
                <div class="card">
                    <h3>Total Egg Production</h3>
                    <p><?php echo $totalEggsProduced; ?></p>
                </div>
                <div class="card">
                    <h3>Total Assets Value</h3>
                    <p>Kshs.<?php echo number_format($totalAssets, 2); ?></p>
                </div>
                <div class="card">
                    <h3>Total Farm Sales</h3>
                    <p>Kshs. <?php echo number_format($totalSales, 2); ?></p>
                </div>
            </div>
            
            
            <div class="dashboard-summary">
    <div class="card">
        <h3>Total Stock <p>(inclusive of mortality)</p></h3>
        <p> <?php echo htmlspecialchars($allstock); ?></p>
    </div>
    <div class="card">
        <h3>Total Mortalities</h3>
        <p>  <?php echo htmlspecialchars($totalmortalities); ?></p>
    </div>
    <div class="card">
        <h3>Mortality Rate</h3>
        <p><?php echo htmlspecialchars($percentageMortalityFormatted); ?>%</p>
    </div>
    <div class="card">
        <h3>Remaining Stock</h3>
        <p> <?php echo htmlspecialchars($totalstocking); ?></p>
    </div>
</div>

            
           <div>
               <h2>Mortality</h2>
                <canvas id="mortalityChart"></canvas> </div>
<div>
    <h2>Stock</h2>
    <canvas id="stockChart"></canvas></div>
            
            <div style="margin-top:20px;">
                <h3> Overview</h3>
                <div>
                  <div class="card-container">
                <div class="card">
                    <h3>Total Salay Paid Out</h3>
                    <p>Kshs.<?php echo $totalSalaries; ?></p> 
                </div>
                <div class="card">
                    <h3>Total Expenses</h3>
                    <p>Kshs.<?php echo number_format($totalExp, 2); ?></p>
                </div>
                <div class="card">
                    <h3>Balance at Hand</h3>
                    <p>Kshs. <?php echo number_format($remainingBalance, 2); ?></p>
                </div>
            </div>
                </div>
                <h2>Liabilities</h2>
<div id="liability-chart-container" class="chart-container">
    <h3>ðŸ“Š Liability Trends</h3>
    <canvas id="liabilityChart"></canvas>
</div>

<div id="egg-chart-container" class="chart-container">
    <h3>ðŸ¥šðŸ“ˆ Egg Production vs. Sales</h3>
    <canvas id="eggChart"></canvas>
</div>

            </div>
        </section>
        <section id="suppliersSection" class="section <?php echo ($activeSection === 'suppliersSection') ? 'active' : ''; ?>">
            <h2>Supplier Management</h2>
            <div id="supplierData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>View</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($supplierData as $supplier): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($supplier['id']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['phone']); ?></td>
                            <td><?php echo htmlspecialchars($supplier['email']); ?></td>
                            <td>
                                <button onclick="viewSupplier(<?php echo $supplier['id']; ?>)">View</button>
                            </td>
                            <td><button onclick="deleteRecord('supplier', <?php echo $supplier['id']; ?>)">Delete</button>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="print-btn" onclick="printSection('supplierData')">Print Suppliers</button>
            </div>
            <!-- Form to add new supplier -->
            <div class="form-container">
                <h3>Add New Supplier</h3>
                <form method="post" action="process_supplier.php">
                    <label>Name:</label>
                    <input type="text" name="name" required>
                    <label>Phone:</label>
                    <input type="text" name="phone" required>
                    <label>Location:</label>
                    <input type="text" name="email" required>
                    <button type="submit" name="addSupplier">Add Supplier</button>
                </form>
            </div>
        </section>
       
        <!-- Customer Management Section -->
        <section id="customersSection" class="section <?php echo ($activeSection === 'customersSection') ? 'active' : ''; ?>">
            <h2>Customer Management</h2>
            <!-- Table of existing customers -->
            <div id="customerData">
                <table class="table-container">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Location</th>
                            <th>View</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerData as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['id']); ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['email']); ?></td>
                            <td>
                                <button onclick="viewCustomer(<?php echo $customer['id']; ?>)">View</button>
                            </td>
                            <td><button onclick="deleteRecord('customer', <?php echo $customer['id']; ?>)">Delete</button>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="print-btn" onclick="printSection('customerData')">Print Customers</button>
            </div>
            <!-- Form to add new customer -->
            <div class="form-container">
                <h3>Add New Customer</h3>
                <form method="post" action="process_customer.php">
                    <label>Name:</label>
                    <input type="text" name="name" required>
                    <label>Phone:</label>
                    <input type="text" name="phone" required>
                    <label>Location:</label>
                    <input type="text" name="email" required>
                    <button type="submit" name="addCustomer">Add Customer</button>
                </form>
            </div>
        </section>
        <!-- Egg Production Section -->
        <section id="eggProductionSection" class="section <?php echo ($activeSection === 'eggProductionSection') ? 'active' : ''; ?>">
            <h2>Egg Production</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="eggProductionSection">
                <label>From: <input type="date" name="egg_start" value="<?php echo $eggStart; ?>"></label>
                <label>To: <input type="date" name="egg_end" value="<?php echo $eggEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('eggProductionData')">Print</button>
            </form>
            <div id="eggProductionData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Batch ID</th>
                            <th>Quantity</th>
                            <th>Damaged</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eggData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['damaged']); ?></td>
                            <td><button onclick="deleteRecord('egg', <?php echo $row['id']; ?>)">Delete</button>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Egg Production</h3>
                <form method="post" action="process_egg_production.php">
                    <label>Date:</label>
                    <input type="date" name="date" required>
                    <label>Batch ID:</label>
<input type="text" name="batch_id" value="<?php echo $batchID; ?>" readonly required> 
                    <label>Quantity:</label>
                    <input type="number" name="quantity" required>
                    <label>Damaged:</label>
                    <input type="number" name="damaged" value="0" required>
                    <button type="submit" name="addEggProduction">Add Production</button>
                </form>
            </div>
        </section>
       
        <!-- Expenses Section -->
        <section id="expensesSection" class="section <?php echo ($activeSection === 'expensesSection') ? 'active' : ''; ?>">
            <h2>Expenses</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="expensesSection">
                <label>From: <input type="date" name="expense_start" value="<?php echo $expenseStart; ?>"></label>
                <label>To: <input type="date" name="expense_end" value="<?php echo $expenseEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('expenseData')">Print</button>
            </form>
            <div id="expenseData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenseData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['amount']); ?></td>
                            <td><button onclick="deleteRecord('expense', <?php echo $row['id']; ?>)">Delete</button>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Expense</h3>
                <form id="expenseForm"   method="post" action="process_expense.php">
                    <label>Category:</label>
                    <select name="category" required>
                        <option value="utilities">Utilities</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="other">Other</option>
                    </select>
                    <label>Description:</label>
                    <textarea name="description" required></textarea>
                    <label>Amount:</label>
                    <input type="number" step="0.01" name="amount" required>
                    <label>Date:</label>
                    <input type="date" name="date" required>
                    <button type="submit" name="addExpense">Add Expense</button>
                </form>
            </div>
        </section>
        
        <!-- Feeds Section -->
        <section id="feedsSection" class="section <?php echo ($activeSection === 'feedsSection') ? 'active' : ''; ?>">
            <h2>Feeds</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="feedsSection">
                <label>From: <input type="date" name="feed_start" value="<?php echo $feedStart; ?>"></label>
                <label>To: <input type="date" name="feed_end" value="<?php echo $feedEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('feedData')">Print</button>
            </form>
            <div class="table-container" id="feedData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Manufacturer</th>
                            <th>Supplier ID</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost</th>
                            <th>Purchased Date</th>
                            <th>Expiry Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['Manufacturer']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo htmlspecialchars($row['cost']); ?></td>
                            <td><?php echo htmlspecialchars($row['purchased_at']); ?></td>
                            <td><?php echo htmlspecialchars($row['exp_date']); ?></td>
                            <td><button onclick="deleteRecord('feed', <?php echo $row['id']; ?>)">Delete</button>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Feed Purchase</h3>
                <form id="feedsForm" method="post" action="process_feed.php">
                    <label>Feed Name:</label>
                    <input type="text" name="name" required>
                    <label>Manufacturer:</label>
                    <input type="text" name="manufacturer" required>
                    <label>Supplier ID (Optional):</label>
                    <input type="number" name="supplier_id">
                    <label>Quantity:</label>
                    <input type="number" name="quantity" required>
                    <label>Unit:</label>
                    <select name="unit" required>
                        <option value="kg">kg</option>
                        <option value="bag">bag</option>
                    </select>
                    <label>Cost:</label>
                    <input type="number" step="0.01" name="cost" required>
                    <label>Purchased Date:</label>
                    <input type="date" name="purchased_at" required>
                    <label>Expiry Date:</label>
                    <input type="date" name="exp_date" required>
                    <button type="submit" name="addFeed">Add Feed</button>
                </form>
            </div>
        </section>
        
        <!-- Medications Section -->
        <section id="medicationsSection" class="section <?php echo ($activeSection === 'medicationsSection') ? 'active' : ''; ?>">
            <h2>Medications</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="medicationsSection">
                <label>From: <input type="date" name="med_start" value="<?php echo $medStart; ?>"></label>
                <label>To: <input type="date" name="med_end" value="<?php echo $medEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('medData')">Print</button>
            </form>
            <div class="table-container" id="medData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Supplier ID</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost</th>
                            <th>Purchased Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo htmlspecialchars($row['cost']); ?></td>
                            <td><?php echo htmlspecialchars($row['purchased_at']); ?></td>
                            <td><button onclick="deleteRecord('medication', <?php echo $row['id']; ?>)">Delete</button>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Medication Purchase</h3>
                <form id="medsForm" method="post" action="process_medication.php">
                    <label>Medication Name:</label>
                    <input type="text" name="name" required>
                    <label>Supplier ID (Optional):</label>
                    <select name="id">
  <?php
  require 'db.php';
  $stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name ASC");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo '<option value="' . htmlspecialchars($row['id']) . '">'
           . htmlspecialchars($row['name']) . ' (' . htmlspecialchars($row['id']) . ')'
           . '</option>';
  }
  ?>
</select>
                    <label>Quantity:</label>
                    <input type="number" name="quantity" required>
                    <label>Unit:</label>
                    <select name="unit" required>
                        <option value="ml">ml</option>
                        <option value="g">g</option>
                        <option value="bottle">bottle</option>
                        <option value="pack">pack</option>
                    </select>
                    <label>Cost:</label>
                    <input type="number" step="0.01" name="cost" required>
                    <label>Purchased Date:</label>
                    <input type="date" name="purchased_at" required>
                    <button type="submit" name="addMedication">Add Medication</button>
                </form>
            </div>
        </section>
        
        <!-- reports section -->
         <section id="reportsSection" class="section <?php echo ($activeSection === 'reportsSection') ? 'active' : ''; ?>">
        <h1>Comprehensive Financial Reports</h1>

    <form method="get" class="filter-form" action="#reportsSection">
        <label>Start Date: <input type="date" name="pl_start_date" value="<?php echo htmlspecialchars($plStartDate); ?>"></label>
        <label>End Date: <input type="date" name="pl_end_date" value="<?php echo htmlspecialchars($plEndDate); ?>"></label>
        <button type="submit">Filter</button>
    </form>

    <div id="Balancesheet" class="report-container">
        <h2 style="text-align: center;">Kebo Poultry</h2>
<h2 style="text-align: center;">Balance Sheet</h2>
<h3 style="text-align: center;">As at: <span id="timestamp"></span></h3>
        <table  class="report-table">
            <tr>
                <th>Assets</th>
                <th>Liabilities </th>
            </tr> 
            <tr>
                <td>Cash at Hand: Kshs. <?php echo number_format($remainingBalance, 2); ?></td>
            </tr>
            <tr>
                <td>Salaries: Kshs. <?php echo number_format($totalSalaries, 2); ?></td>
                <td>Sales Revenue: Kshs. <?php echo number_format($totalSales, 2); ?></td>
            </tr>
            <tr>
                <td>Feeds: Kshs. <?php echo number_format($totalFeeds, 2); ?></td>
                <td>Capital Investments: Kshs. <?php echo number_format($totalCapital, 2); ?></td>
            </tr>
            <tr> 
                <td>Medications: Kshs. <?php echo number_format($totalMedications, 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td>Farm Assets: Kshs. <?php echo number_format($totalAssets, 2); ?></td>
                <td></td>
            </tr> 
             <tr>
                <td>Liability Repayments: Kshs. <?php echo number_format($totalliabilitypayments, 2); ?></td>
                <td>Liabilities: Kshs. <?php echo number_format($totalLiabilities, 2); ?></td>
            </tr>
            <tr>
                <td>Poultry Stock: Kshs. <?php echo number_format($totalStock, 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td>Other Expenses: Kshs. <?php echo number_format($totalExpenses, 2); ?></td>
                <td></td>
            </tr>
            <tr>
                <td><strong>Total Assets:</strong> Kshs. <?php echo number_format($totalAss, 2); ?></td>
                <td><strong>Total Liabilities:</strong>Kshs. <?php echo number_format($totalcr, 2); ?></td>
            </tr> 
            <tr>
                
            </tr> 
        </table>
        <button type="button" class="print-btn" onclick="printSection('Balancesheet')">Print</button>
    </div>


<div class="table-container">
    <h1>Kebo Poultry</h1> 
    <h2>Profit and Loss Statement</h2>
    <h3>For the Period Ending  <span id="time"></span></h3>

    <table>
        <tr>
            <th>Item</th>
            <th>Amount (Kshs)</th>
        </tr>
        <tr>
            <td>Sales Revenue</td>
            <td><?php echo number_format($totalSales, 2); ?></td>
        </tr>
        <tr>
            <td><strong>Cost of Goods Sold (COGS)</strong></td>
            <td></td>
        </tr>
        <tr>
            <td>- Stock</td>
            <td><?php echo number_format($totalStock, 2); ?></td>
        </tr>
        <tr class="total">
            <td>Total COGS</td>
            <td><?php echo number_format($totalStock, 2); ?></td>
        </tr>
        <tr class="total">
            <td>Gross Profit</td>
            <td><?php echo number_format($grossProfit, 2); ?></td>
        </tr>
        <tr>
            <td><strong>Operating Expenses</strong></td>
            <td></td>
        </tr>
        <tr>
            <td>- Feeds</td>
            <td><?php echo number_format($totalFeeds, 2); ?></td>
        </tr>
        <tr>
            <td>- Medications</td>
            <td><?php echo number_format($totalMedications, 2); ?></td>
        </tr>
        <tr>
            <td>- Salaries</td>
            <td><?php echo number_format($totalSalaries, 2); ?></td>
        </tr>
        <tr>
            <td>- Other Expenses</td>
            <td><?php echo number_format($totalExpenses, 2); ?></td>
        </tr>
        <tr class="total">
            <td>Total Operating Expenses</td>
            <td><?php echo number_format($operatingExpenses, 2); ?></td>
        </tr>
        <tr class="total">
            <td>Operating Profit</td>
            <td><?php echo number_format($operatingProfit, 2); ?></td>
        </tr>
        <tr>
            <td><strong>Other Expenses</strong></td>
            <td></td>
        </tr>
        <tr>
            <td>- Liability Repayments (Interest)</td>
            <td><?php echo number_format($totalliabilitypayments, 2); ?></td>
        </tr>
        <tr class="total">
            <td>Net Profit Before Tax</td>
            <td><?php echo number_format($profitbeforeTax, 2); ?></td>
        </tr>
        <tr>
            <td>Income Tax Expense</td>
            <td>(Calculate Tax)</td>
        </tr>
        <tr class="total">
            <td>Net Profit</td>
            <td>(Net Profit Before Tax - Income Tax)</td>
        </tr>
    </table>
</div>



    <div id="pnl" class="report-container">
        <h2 style="text-align: center;">Kebo Poultry</h2>
<h2 style="text-align: center;">Profit And Loss Statement</h2>
<h3 style="text-align: center;">For the period ending: <span id="time"></span></h3>
        <table class="report-table">
            <tr>
                <th>Dr</th>
                <th>Cr</th>
            </tr>
            <tr>
                <td><strong>Total Assets:</strong> Kshs. <?php echo number_format($totalAss, 2); ?></td>
                 <td><strong>Total Liabilities & Equity:</strong> Kshs. <?php echo number_format($totalLiabilities + $totalSales + $totalCapital, 2); ?></td>
            </tr>
            <tr>
               <td><strong>Net Profit:</strong> <span class="<?php echo ($netProfit >= 0) ? 'profit' : 'loss'; ?>">
                    Kshs. <?php echo number_format($netProfit, 2); ?>
                </span></td>
            </tr>
            <tr>
                
               
            </tr>
        </table>
      <button type="button" class="print-btn" onclick="printSection('pnl')">Print</button>
    </div>
        
        </section>
        
        
        <!-- Stock Management Section -->
<section id="stockSection" class="section <?php echo ($activeSection === 'stockSection') ? 'active' : ''; ?>">
  <h2>Stock Management</h2>
  <p>
    Record purchases of chicks, active layers, matured cocks, and other stock.
    For "Others," please specify the description. Mortalities (dead stock) should be recorded to deduct from the purchased quantity.
    Note: Stock purchases rely on the available capital balance. If the balance is lower than the purchase cost, an error will occur.
  </p>
  
  <!-- Cards -->
  <div class="card-container">
    <div class="card">
      <h3>Available Balance</h3>
      <p>KShs. <?php echo number_format($remainingBalance, 2); ?></p>
    </div>
    <div class="card">
      <h3>Total Stock Purchase</h3>
      <p>KShs. <?php echo number_format($totalStockPurchase, 2); ?></p>
    </div>
  </div>
  
  <!-- Stock Investments Table -->
  <h3>Stock Purchases</h3>
  <div class="table-container">
    <table class="data-table" id="stockTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Stock Type</th>
          <th>Description (if Others)</th>
          <th>Quantity</th>
          <th>Purchase Cost (KSh)</th>
          <th>Purchase Date</th>
          <th>Mortality</th>
          <th>Net Stock</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
    <?php foreach ($stockRecords as $record): 
        $netStock = $record['quantity'] + $record['mortality'];
    ?>
    <tr>
        <td><?php echo htmlspecialchars($record['id']); ?></td>
        <td><?php echo htmlspecialchars($record['stock_type']); ?></td>
        <td><?php echo htmlspecialchars($record['other_description'] ?? ''); ?></td>
        <td><?php echo htmlspecialchars($netStock); ?></td>
        <td><?php echo number_format($record['purchase_cost'], 2); ?></td>
        <td><?php echo htmlspecialchars($record['purchase_date']); ?></td>
        <td><?php echo htmlspecialchars($record['mortality']); ?></td> <!-- Shows total mortalities -->
        <td><?php echo htmlspecialchars($record['quantity']); ?></td> 
        <td>
            <button onclick="deleteRecord('stock', <?php echo $record['id']; ?>)">Delete</button>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>

      <tfoot>
        <tr>
          <td colspan="4"><strong>Total</strong></td>
          <td colspan="5"><strong>KShs. <?php echo number_format($totalStockPurchase, 2); ?></strong></td>
        </tr>
      </tfoot>
    </table>
  </div>
  
  <!-- Form to Add New Stock Purchase -->
  <h3>Add New Stock Purchase</h3>
  <div class="form-container">
    <form id="stockForm">
      <label>Stock Type:</label>
      <select name="stock_type" id="stock_type" required onchange="toggleOtherDescription()">
        <option value="">Select Stock Type</option>
        <option value="Chicks">Chicks</option>
        <option value="Active Layers">Active Layers</option>
        <option value="Matured Cocks">Matured Cocks</option>
        <option value="Others">Others</option>
      </select>
      
      <div id="otherDescriptionDiv" style="display: none;">
        <label>Description (if Others):</label>
        <input type="text" name="other_description">
      </div>
      
      <label>Quantity:</label>
      <input type="number" name="quantity" required>
      
      <label>Purchase Cost (KSh):</label>
      <input type="number" name="purchase_cost" step="0.01" required>
      
      <label>Purchase Date:</label>
      <input type="date" name="purchase_date" required>
      
      <label>Mortality (Number of Dead Stock):</label>
      <input type="number" name="mortality" value="0">
      
      <button type="submit" class="btn btn-success">Submit</button>
    </form>
  </div>
  
   <h2>Stock Mortalities</h2>

    <!-- Mortality Form -->
    <div class="form-container">
        <h3>Record Mortality</h3>
        <form id="mortalityForm">
            <label>Stock Type:</label>
            <select name="stock_type" id="stock_type" required>
                <option value="Chicks">Chicks</option>
                <option value="Active Layers">Active Layers</option>
                <option value="Matured Cocks">Matured Cocks</option>
                <option value="Others">Others</option>
            </select>

            <label>Stock:</label>
            <select name="stock_id" id="stock_id" required></select>

            <label>Quantity:</label>
            <input type="number" name="quantity" min="1" required>
            
            <label>Date:</label>
            <input type="date" name="date" required>
            
            <label>Reason:</label>
            <textarea name="reason"></textarea>

            <button type="submit" class="btn btn-danger">Submit</button>
        </form>
    </div>

    <!-- Mortality Records Table -->
    <div id="mortalityData">
        <h3>Recorded Mortalities</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Stock Type</th>
                    <th>Quantity</th>
                    <th>Date</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody id="mortalityTableBody">
                <!-- AJAX Loaded Data -->
            </tbody>
        </table>
    </div>
</section>

        
        
        <!-- Sales Section -->
<section id="salesSection" class="section <?php echo ($activeSection === 'salesSection') ? 'active' : ''; ?>">
    <h2>Sales</h2>
    <div class="card-container">
  <div class="card">
    <h3>Available Eggs</h3>
    <p><?php echo number_format($eggItems[0]['available_quantity'] ?? 0); ?></p>
  </div>
  <div class="card">
    <h3>Available Assets</h3>
    <p><?php echo number_format($assetItems[0]['available_quantity'] ?? 0); ?></p>
  </div>
  <div class="card">
    <h3>Available Stock</h3>
    <p><?php echo number_format($stockItems[0]['available_quantity'] ?? 0); ?></p>
  </div>
</div>

    <!-- Filter Form for Previous Sales -->
    <form method="get" class="filter-form">
        <input type="hidden" name="section" value="salesSection">
        <label>From: <input type="date" name="sale_start" value="<?php echo $saleStart; ?>"></label>
        <label>To: <input type="date" name="sale_end" value="<?php echo $saleEnd; ?>"></label>
        <button type="submit">Filter</button>
        <button type="button" class="print-btn" onclick="printSection('saleData')">Print</button>
    </form>
    
    <!-- Sales Data Table -->
    <div class="table-container" id="saleData">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item Category</th>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total Price</th>
                    <th>Customer ID</th>
                    <th>Sale Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($saleData as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['item_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($row['unit_price']); ?></td>
                    <td><?php echo htmlspecialchars($row['total_price']); ?></td>
                    <td><?php echo htmlspecialchars($row['customer_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                    <td><button onclick="deleteRecord('sale', <?php echo $row['id']; ?>)">Delete</button>
</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Form to Add a New Sale -->
    <div class="form-container">
    <h3>Add Sale</h3>
    <form id="salesForm" method="post" action="process_sale.php">
        <label>Item Category:</label>
        <select name="item_category" id="categorySelect" required onchange="onCategoryChange()">
            <option value="">Select Category</option>
            <option value="egg">Eggs (Available: <?php echo number_format($eggItems[0]['available_quantity'] ?? 0); ?>)</option>
            <option value="asset">Assets</option>
            <option value="stock">Stock</option>
        </select>

        <!-- Hidden inputs for item_type and item_id -->
        <input type="hidden" id="itemType" name="item_type">
        <input type="hidden" id="itemId" name="item_id">

        <!-- For Assets and Stock, display item selection -->
        <div id="itemSelectionContainer" style="display: none;">
            <label>Item:</label>
            <select name="item_id" id="itemSelect" required>
                <option value="">Select Item</option>
            </select>
            <label>Item Name</label>
            <input type="text" id="itemName" readonly>
            <label>Available Quantity:</label>
            <input type="number" id="availableQuantity" readonly>
        </div>

        <!-- For Eggs, display informational message -->
        <div id="eggInfo" style="display: none;">
            <p>All eggs are sold as a single group.</p>
        </div>

        <label>Quantity:</label>
        <input type="number" name="quantity" id="quantityInput" required>
        <p id="quantityError" style="color:red; display:none;">Quantity exceeds available stock!</p>

        <label>Unit Price:</label>
        <input type="number" step="0.01" name="unit_price" required>

        <label>Customer ID:</label>
        <select name="customer_id">
            <?php
            $stmt = $pdo->query("SELECT id, name FROM customers ORDER BY name ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['name']) . '</option>';
            }
            ?>
        </select>

        <label>Sale Date:</label>
        <input type="date" name="sale_date" required>
        <button type="submit" name="addSale" class="btn btn-success">Add Sale</button>
    </form>
</div>
</section>


        <!-- Farm Assets Section -->
        <section id="assetsSection" class="section <?php echo ($activeSection === 'assetsSection') ? 'active' : ''; ?>">
            <h2>Farm Assets</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="assetsSection">
                <label>From: <input type="date" name="asset_start" value="<?php echo $assetStart; ?>"></label>
                <label>To: <input type="date" name="asset_end" value="<?php echo $assetEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('assetData')">Print</button>
            </form>
            <div class="table-container" id="assetData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Code</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Total Value</th>
                            <th>Unit Price</th>
                            <th>Purchased Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetData as $row): ?>
                        <tr>
                            <td>
    <svg id="barcode-<?php echo htmlspecialchars($row['asset_code']); ?>"></svg>
    <script>
        JsBarcode("#barcode-<?php echo htmlspecialchars($row['asset_code']); ?>", "<?php echo htmlspecialchars($row['asset_code']); ?>", {
            format: "CODE128",
            displayValue: true,
            fontSize: 10
        });
    </script>
</td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['value']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit_price']); ?></td>
                            <td><?php echo htmlspecialchars($row['purchased_at']); ?></td>
                            <td><button onclick="deleteRecord('asset', <?php echo $row['id']; ?>)">Delete</button>
</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Farm Asset</h3>
                <form id="assetForm" method="post" action="process_asset.php">
                    <label>Asset Name:</label>
                    <input type="text" name="name" required>
                    <label>Category:</label>
                    <select name="category" required>
                        <option value="equipment">Equipment</option>
                        <option value="building">Building</option>
                        <option value="vehicle">Vehicle</option>
                        <option value="land">Land</option>
                    </select>
                    <label>Quantity</label>
                    <input type="number" name="quantity" required>
                    <label>Value:</label>
                    <input type="number" step="0.01" name="value" required>
                    <label>Purchased Date:</label>
                    <input type="date" name="purchased_at" required>
                    <button type="submit" name="addAsset">Add Asset</button>
                </form>
            </div>
        </section>
        
        <!-- Salaries Section -->
        <section id="salariesSection" class="section <?php echo ($activeSection === 'salariesSection') ? 'active' : ''; ?>">
            <h2>Staff Salaries</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="salariesSection">
                <label>From: <input type="date" name="salary_start" value="<?php echo $salaryStart; ?>"></label>
                <label>To: <input type="date" name="salary_end" value="<?php echo $salaryEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('salaryData')">Print</button>
            </form>
            <div class="table-container" id="salaryData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Name</th>
                            <th>Salary</th>
                            <th>Paid On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
  <?php foreach ($salaryData as $row): ?>
    <tr>
      <td><?php echo htmlspecialchars($row['user_id']); ?></td>
      <td>
        <?php
        require 'db.php'; 
        try {
            $stmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
            $stmt->execute(['user_id' => $row['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
            } else {
                echo "User not found";
            }
        } catch (PDOException $e) {
            echo "Error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
        ?>
      </td>
      <td><?php echo htmlspecialchars($row['salary']); ?></td>
      <td><?php echo htmlspecialchars($row['paid_on']); ?></td>
      <td>
        <button onclick="deleteRecord('salary', <?php echo $row['id']; ?>)">Delete</button>
      </td>
    </tr>
  <?php endforeach; ?>
</tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Staff Salary</h3>
                <form id="salaryForm" method="post" action="process_salary.php">
                    <label>User ID:</label>
                    <select name="user_id">
  <?php
  require 'db.php';
  $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username ASC");
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
      echo '<option value="' . htmlspecialchars($row['id']) . '">'
           . htmlspecialchars($row['username']) . ' (' . htmlspecialchars($row['id']) . ')'
           . '</option>';
  }
  ?>
</select>
                    <label>Salary:</label>
                    <input type="number" step="0.01" name="salary" required>
                    <label>Paid On:</label>
                    <input type="date" name="paid_on" required>
                    <button type="submit" name="addSalary">Add Salary</button>
                </form>
            </div>
        </section>
        
        
      <!-- Manage Users Section -->
<section id="usersSection" class="section <?php echo ($activeSection === 'usersSection') ? 'active' : ''; ?>">
    <h2>Manage Users</h2>
    <p>This section allows you to view, add, edit, or delete users.</p>

    <!-- Add User Button -->
    <button onclick="toggleUserForm()" class="btn btn-primary">Add New User</button>

    <div id="add-user-form" style="display: none; margin-top: 20px;">
        <form id="addUserForm">
            <label>Full Name:</label>
            <input type="text" name="full_name" required>

            <label>Email:</label>
            <input type="email" name="email" required>

            <label>Username:</label>
            <input type="text" name="username" required>

            <label>Password:</label>
            <input type="password" name="password" required>

            <label>Role:</label>
            <select name="role">
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="worker">Worker</option>
            </select>

            <button type="submit" name="add_user" class="btn btn-success">Submit</button>
        </form>
    </div>

  <!-- Users Table -->
<h3>Existing Users</h3>
<div class="table-container">
  <table id="usersTable">
      <thead>
          <tr>
              <th>ID</th>
              <th>Full Name</th>
              <th>Email</th>
              <th>Username</th>
              <th>Role</th>
              <th>Action</th>
          </tr>
      </thead>
      <tbody>
          <?php
          require 'db.php';
          $stmt = $pdo->query("SELECT * FROM users ORDER BY role ASC");
          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
              // Use addslashes() on text values to prevent breaking JS strings
              $fullNameEsc = addslashes($row['full_name']);
              $emailEsc    = addslashes($row['email']);
              $usernameEsc = addslashes($row['username']);
              echo "<tr id='userRow{$row['id']}'>
                  <td>{$row['id']}</td>
                  <td>{$row['full_name']}</td>
                  <td>{$row['email']}</td>
                  <td>{$row['username']}</td>
                  <td>{$row['role']}</td>
                  <td>
                      <button onclick=\"editUser({$row['id']}, '$fullNameEsc', '$emailEsc', '$usernameEsc', '{$row['role']}')\" class='btn btn-warning'>Edit</button>
                      <button onclick=\"deleteUser({$row['id']})\" class='btn btn-danger'>Delete</button>
                  </td>
              </tr>";
          }
          ?>
      </tbody>
  </table>
</div>


    <!-- Print Users Button -->
    <button onclick="printUsers()" class="btn btn-secondary">Print Users</button>
</section>

<!-- Edit User Modal -->
<div id="editUserModal" style="display: none;">
    <h3>Edit User</h3>
    <form id="editUserForm">
        <input type="hidden" id="edit_user_id" name="id">
        <label>Full Name:</label>
        <input type="text" id="edit_full_name" name="full_name" required>

        <!-- Email is shown but not editable -->
        <p><strong>Email:</strong> <span id="edit_email_display"></span></p>

        <label>Username:</label>
        <input type="text" id="edit_username" name="username" required>

        <label>Role:</label>
        <select id="edit_role" name="role">
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="worker">Worker</option>
        </select>

        <button type="submit" class="btn btn-success">Update</button>
        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
    </form>
</div>

        
        
        
        
         <section id="liabilities-section" class="section <?php echo ($activeSection === 'liabilities-section') ? 'active' : ''; ?>">
    <h2>Liabilities Management</h2>
    <p>Liabilities include outstanding loans, supplier credit, or any other financial obligations.</p>

    <!-- Button to Open Add Liability Form -->
    <button onclick="toggleLiabilityForm()" class="btn btn-primary">Add New Liability</button>

    <!-- Add Liability Form -->
    <div id="add-liability-form" style="display: none; margin-top: 20px;">
        <form id="liabilityForm" action="process_liability.php" method="POST">
            <label>Description:</label>
            <input type="text" name="description" required>

            <label>Amount (KSh):</label>
            <input type="number" name="amount" step="0.01" required>

            <label>Liability Type:</label>
            <select name="liability_type">
                <option value="Loan">Loan</option>
                <option value="Supplier Credit">Supplier Credit</option>
                <option value="Other">Other</option>
            </select>

            <label>Due Date:</label>
            <input type="date" name="due_date" required>

            <button type="submit" name="add-liability" class="btn btn-success">Submit</button>
        </form>
    </div>

    <!-- Liabilities Table -->
    <h3>Existing Liabilities</h3>
    <div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Description</th>
                <th>Amount (KSh)</th>
                <th>Paid (KSh)</th>
                <th>Balance (KSh)</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Action</th>
                <th>Erase</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->query("SELECT * FROM liabilities ORDER BY due_date ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $balance = $row['amount'] - $row['paid_amount'];
                echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['description']}</td>
                    <td>{$row['amount']}</td>
                    <td>{$row['paid_amount']}</td>
                    <td>{$balance}</td>
                    <td>{$row['due_date']}</td>
                    <td>{$row['status']}</td>
                    <td>
                        <button onclick=\"openPaymentForm({$row['id']}, '{$row['description']}', {$balance})\" class='btn btn-warning'>Make Payment</button>

                    </td>
                    <td>
            <button onclick=\"deleteRecord('liability', {$row['id']})\" class='btn btn-danger'>Delete</button>
        </td>

                </tr>";
            }
            ?>
        </tbody>
    </table>
    </div>
    
 <!-- Payment Report Section -->
    <h3>Payment Report</h3>

    <!-- Date Filter Form -->
    <form method="GET">
        <label>Start Date:</label>
        <input type="date" name="start_date" value="<?= $_GET['start_date'] ?? '' ?>">

        <label>End Date:</label>
        <input type="date" name="end_date" value="<?= $_GET['end_date'] ?? '' ?>">

        <button type="submit" class="btn btn-primary">Generate Report</button>
    </form>

    <!-- Payments Report Table -->
    <div class="table-container">
   <table>
    <thead>
        <tr>
            <th>Liability</th>
            <th>Amount (KSh)</th>
            <th>Paid (KSh)</th>
            <th>Remaining (KSh)</th>
            <th>Payment Date</th>
            <th>Payment Method</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $query = "SELECT l.id AS liability_id, l.description, l.amount, 
                         lp.amount_paid, (l.amount - l.paid_amount) AS balance, 
                         lp.payment_date, lp.payment_method, lp.id AS payment_id
                  FROM liability_payments lp
                  INNER JOIN liabilities l ON l.id = lp.liability_id";

        $params = [];
        if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
            $query .= " WHERE lp.payment_date BETWEEN :start_date AND :end_date";
            $params = [
                'start_date' => $_GET['start_date'],
                'end_date'   => $_GET['end_date']
            ];
        }

        $query .= " ORDER BY lp.payment_date DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>
                <td>{$row['description']}</td>
                <td>{$row['amount']}</td>
                <td>{$row['amount_paid']}</td>
                <td>{$row['balance']}</td>
                <td>{$row['payment_date']}</td>
                <td>{$row['payment_method']}</td>
                <td>
                    <button onclick=\"deleteRecord('liability_pay', {$row['payment_id']})\" class='btn btn-danger'>Delete</button>
                </td>
            </tr>";
        }
        ?>
    </tbody>
</table>

    </div>
<!-- Payment Modal -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePaymentModal()">&times;</span>
        <h3>Make Payment</h3>
        <form id="paymentForm" action="process_payment.php" method="POST">
            <input type="hidden" id="payment_liability_id" name="liability_id">
            <p id="payment_description"></p>

            <label>Amount to Pay:</label>
            <input type="number" id="payment_amount" name="amount_paid" step="0.01" required>

            <label>Payment Date:</label>
            <input type="date" name="payment_date" required>

            <label>Payment Method:</label>
            <input type="text" name="payment_method" required>

            <label>Notes:</label>
            <textarea name="notes"></textarea>

            <button type="submit" class="btn btn-success">Submit Payment</button>
        </form>
    </div>
</div>

</section>

 <!-- Capital Management Section -->
<section id="capitalSection" class="section <?php echo ($activeSection === 'capitalSection') ? 'active' : ''; ?>">
  <h2>Capital Management</h2>
  <p>Use this section to input the capital you invest in the business and track your financial performance.</p>
  
  <!-- Cards for Totals -->
  <div class="card-container">
    <div class="card">
      <h3>Total Capital Invested</h3>
      <p>KShs. <?php echo number_format($totalCapital, 2); ?></p>
    </div>
    <div class="card">
      <h3>Remaining Balance</h3>
      <p>KShs. <?php echo number_format($remainingBalance, 2); ?></p>
    </div>
  </div>
  
  <!-- Capital Investments Table -->
  <h3>Capital Investments</h3>
  <div class="table-container">
    <table class="data-table" id="capitalTable">
      <thead>
        <tr>
          <th>ID</th>
          <th>Amount (KSh)</th>
          <th>Source</th>
          <th>Date Invested</th>
          <th>Description</th>
          <th>Created At</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $totalInvested = 0;
        foreach ($capitalRecords as $record) {
            $totalInvested += $record['amount'];
            echo "<tr>
                    <td>" . htmlspecialchars($record['id']) . "</td>
                    <td>" . number_format($record['amount'], 2) . "</td>
                    <td>" . htmlspecialchars($record['source']) . "</td>
                    <td>" . htmlspecialchars($record['date_invested']) . "</td>
                    <td>" . htmlspecialchars($record['description']) . "</td>
                    <td>" . htmlspecialchars($record['created_at']) . "</td>
                    <td>
            <button onclick=\"deleteRecord('capital', {$record['id']})\" class='btn btn-danger'>Delete</button>
        </td>
                  </tr>";
        }
        ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="1"><strong>Total</strong></td>
          <td colspan="5"><strong>KShs. <?php echo number_format($totalInvested, 2); ?></strong></td>
        </tr>
      </tfoot>
    </table>
  </div>
  
  <!-- Form to Add New Capital Investment -->
  <h3>Add New Capital Investment</h3>
  <div class="form-container">
    <form id="capitalForm">
      <label>Amount (KSh):</label>
      <input type="number" name="amount" step="0.01" required>
      
      <label>Source:</label>
      <input type="text" name="source" required>
      
      <label>Date Invested:</label>
      <input type="date" name="date_invested" required>
      
      <label>Description:</label>
      <textarea name="description" rows="3"></textarea>
      
      <button type="submit" class="btn btn-success">Submit</button>
    </form>
  </div>
</section>
    </div>
</div>
<div id="loader" style="display: none;">Loading...</div>

<script>
    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('loader').style.display = 'block';
    });
</script>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toggle sidebar on mobile
    document.querySelector('.toggle-btn').addEventListener('click', function(){
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // Function to print a section by its div ID
    function printSection(divId) {
        var printContents = document.getElementById(divId).innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
    // Function to view supplier details via SweetAlert popup
    function viewSupplier(supplierId) {
        // For demonstration, simply display the supplier ID.
        // In production, you might perform an AJAX call to fetch related sales/expenses.
        Swal.fire({
            icon: 'info',
            title: 'Supplier Details',
            html: 'Supplier ID: ' + supplierId + '<br><br>Further details (sales, expenses, etc.) can be loaded here.',
            confirmButtonText: 'Close'
        });
    }
    // Function to switch between tabs
        function showTab(tabId) {
            // Remove active class from both tab buttons and sections
            document.getElementById('plTab').classList.remove('active');
            document.getElementById('bsTab').classList.remove('active');
            document.getElementById('profitLoss').classList.remove('active');
            document.getElementById('balanceSheet').classList.remove('active');
            
            // Add active class to the selected tab button and section
            if (tabId === 'profitLoss') {
                document.getElementById('plTab').classList.add('active');
                document.getElementById('profitLoss').classList.add('active');
            } else if (tabId === 'balanceSheet') {
                document.getElementById('bsTab').classList.add('active');
                document.getElementById('balanceSheet').classList.add('active');
            }
        }
        
        // Function to print a specific section
        function printSection(divId) {
            var printContents = document.getElementById(divId).innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }
    // Function to view customer details via SweetAlert popup
    function viewCustomer(customerId) {
        Swal.fire({
            icon: 'info',
            title: 'Customer Details',
            html: 'Customer ID: ' + customerId + '<br><br>Further details (sales, purchase history, etc.) can be loaded here.',
            confirmButtonText: 'Close'
        });
    }
    // Display SweetAlert message if URL parameters 'message' and 'type' exist
    <?php if(isset($_GET['message'])): ?>
    Swal.fire({
        icon: '<?php echo $_GET['type'] ?? "success"; ?>',
        title: '<?php echo $_GET['message']; ?>',
        timer: 3000,
        showConfirmButton: false
    });
    <?php endif; ?>
</script>
<script>
    function toggleLiabilityForm() {
        var form = document.getElementById("add-liability-form");
        form.style.display = form.style.display === "none" ? "block" : "none";
    }

    function openPaymentForm(id, description, balance) {
        document.getElementById("payment_liability_id").value = id;
        document.getElementById("payment_description").innerHTML = "<strong>Paying for:</strong> " + description + "<br><strong>Remaining Balance:</strong> KSh " + balance;
        document.getElementById("paymentModal").style.display = "block";
    }

    function closePaymentModal() {
        document.getElementById("paymentModal").style.display = "none";
    }
</script>
<script>
function toggleLiabilityForm() {
    var form = document.getElementById("add-liability-form");
    form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
}

function openPaymentForm(id, description, balance) {
    document.getElementById("payment_liability_id").value = id;
    document.getElementById("payment_description").innerHTML = "Paying for: " + description;
    document.getElementById("payment_amount").max = balance;
    document.getElementById("paymentModal").style.display = "block";
}
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var ctx = document.getElementById('liabilityChart').getContext('2d');
        var liabilityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($liabilityNames) ?>,
                datasets: [
                    {
                        label: 'Total Liabilities (KSh)',
                        data: <?= json_encode($totalAmounts) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Payments Made (KSh)',
                        data: <?= json_encode($totalPaid) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        var ctx = document.getElementById('eggChart').getContext('2d');
        var eggChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [
                    {
                        label: 'Eggs Produced',
                        data: <?= json_encode($producedData) ?>,
                        backgroundColor: 'rgba(255, 205, 86, 0.5)',
                        borderColor: 'rgba(255, 205, 86, 1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'Eggs Sold',
                        data: <?= json_encode($soldData) ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>
<script>
    
    function onCategoryChange() {
    const category = document.getElementById('categorySelect').value;
    const itemSelect = document.getElementById('itemSelect');
    const itemSelectionContainer = document.getElementById('itemSelectionContainer');
    const eggInfo = document.getElementById('eggInfo');

    // Reset fields
    document.getElementById('itemName').value = "";
    document.getElementById('availableQuantity').value = "";
    document.getElementById('itemId').value = "";

    if (category === 'egg') {
        // Hide item selection and show egg info message
        itemSelectionContainer.style.display = 'none';
        itemSelect.removeAttribute('required');
        eggInfo.style.display = 'block';

    } else if (category === 'asset' || category === 'stock') {
        // Show item selection and add 'required' attribute
        itemSelectionContainer.style.display = 'block';
        itemSelect.setAttribute('required', 'required');

        // Hide egg info and load items for selected category
        eggInfo.style.display = 'none';
        loadItems(category);
    } else {
        // Hide all sections if no category is selected
        itemSelectionContainer.style.display = 'none';
        eggInfo.style.display = 'none';
        itemSelect.removeAttribute('required');
    }
}

function loadItems(category) {
    const itemSelect = document.getElementById('itemSelect');
    itemSelect.innerHTML = '<option value="">Select Item</option>';

    if (availableItems.hasOwnProperty(category)) {
        availableItems[category].forEach(item => {
            let option = document.createElement("option");
            option.value = item.id;
            option.textContent = `${item.name} (Available: ${item.available_quantity})`;
            option.setAttribute("data-quantity", item.available_quantity);
            option.setAttribute("data-name", item.name);
            itemSelect.appendChild(option);
        });
    }
}

// When an item is selected, update the hidden inputs and available quantity
document.getElementById('itemSelect').addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const available = selectedOption.getAttribute('data-quantity') || 0;
    const itemName = selectedOption.getAttribute('data-name') || '';

    document.getElementById('availableQuantity').value = available;
    document.getElementById('itemId').value = this.value;
    document.getElementById('itemName').value = itemName;
});

// Validate quantity input against available quantity
document.getElementById('quantityInput').addEventListener('input', function () {
    const max = parseInt(document.getElementById('availableQuantity').value) || Infinity;
    const value = parseInt(this.value) || 0;
    document.getElementById('quantityError').style.display = value > max ? 'block' : 'none';
});

// AJAX submission for the sales form
document.getElementById("salesForm").addEventListener("submit", function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);

    // Ensure `item_type`, `item_id`, and `item_name` are properly set
    if (!formData.has("item_type")) {
        formData.append("item_type", document.getElementById("itemType").value);
    }
    if (!formData.has("item_id")) {
        formData.append("item_id", document.getElementById("itemId").value);
    }
    if (!formData.has("item_name")) {
        formData.append("item_name", document.getElementById("itemName").value);
    }

    fetch("process_sale.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success") {
            Swal.fire({
                icon: "success",
                title: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: data.message,
                timer: 3000,
                showConfirmButton: false
            });
        }
    })
    .catch(error => {
        console.error("Error:", error);
        Swal.fire({
            icon: "error",
            title: "Ajax Error",
            text: error.message,
            timer: 3000,
            showConfirmButton: false
        });
    });
});

</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById("expenseForm").addEventListener("submit", function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);

    // Ensure `item_type` and `item_id` are properly set
    if (!formData.has("item_type")) {
        formData.append("item_type", document.getElementById("itemType").value);
    }
    if (!formData.has("item_id")) {
        formData.append("item_id", document.getElementById("itemId").value);
    }

    fetch("process_expense.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success") {
            Swal.fire({
                icon: "success",
                title: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => location.reload());
        } else {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: data.message,
                timer: 3000,
                showConfirmButton: false
            });
        }
    })
    .catch(error => {
        console.error("Error:", error);
        Swal.fire({
            icon: "error",
            title: "Ajax Error",
            text: error.message,
            timer: 3000,
            showConfirmButton: false
        });
    });
});
</script>

</script>
<script src="db.js"></script>
<script src="sync.js"></script>

<script>
document.getElementById("stockForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_stock.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message
    });
  });
});

function toggleOtherDescription() {
  const stockType = document.getElementById("stock_type").value;
  const otherDiv = document.getElementById("otherDescriptionDiv");
  if (stockType === "Others") {
    otherDiv.style.display = "block";
  } else {
    otherDiv.style.display = "none";
  }
}
</script>

<script>
document.getElementById("capitalForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_capital.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        // Reload the section (or the entire page)
        location.reload();
      });
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message
    });
  });
});
</script>

<script>
document.getElementById("feedsForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_feed.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => location.reload());
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message,
      timer: 2000,
      showConfirmButton: false
    });
  });
});
</script>


<script>
document.getElementById("medsForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_medication.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => location.reload());
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message,
      timer: 2000,
      showConfirmButton: false
    });
  });
});
</script>


<script>
document.getElementById("assetForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_asset.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => location.reload());
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message,
      timer: 2000,
      showConfirmButton: false
    });
  });
});
</script>

<script>
document.getElementById("salaryForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_salary.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => location.reload());
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message,
      timer: 2000,
      showConfirmButton: false
    });
  });
});
</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("liabilityForm");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch("process_liability.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire({
                        icon: "success",
                        title: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: "error",
                        title: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Ajax Error",
                    text: error.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("paymentForm");

    if (form) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch("process_payment.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    Swal.fire({
                        icon: "success",
                        title: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: "error",
                        title: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Ajax Error",
                    text: error.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
    }
});
</script>

<script>
document.getElementById("PaymentForm").addEventListener("submit", function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  
  fetch("process_payment.php", {
    method: "POST",
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if(data.status === "success") {
      Swal.fire({
        icon: "success",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        // Reload the section (or the entire page)
        location.reload();
      });
    } else {
      Swal.fire({
        icon: "error",
        title: data.message,
        timer: 2000,
        showConfirmButton: false
      });
    }
  })
  .catch(error => {
    console.error("Error:", error);
    Swal.fire({
      icon: "error",
      title: "Ajax Error",
      text: error.message
    });
  });
});
</script>

<script>
// Toggle Add User Form
function toggleUserForm() {
    var form = document.getElementById("add-user-form");
    form.style.display = (form.style.display === "none" || form.style.display === "") ? "block" : "none";
}

// AJAX for Add User using Fetch API
document.getElementById("addUserForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch("process_user.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            Swal.fire({ icon: "success", title: data.message, timer: 2000, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: "error", title: data.message, timer: 2000, showConfirmButton: false });
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire({ icon: "error", title: "Ajax Error", text: error.message });
    });
});

// Open Edit User Modal and populate fields
function editUser(id, fullName, email, username, role) {
    document.getElementById("edit_user_id").value = id;
    document.getElementById("edit_full_name").value = fullName;
    document.getElementById("edit_email_display").textContent = email;
    document.getElementById("edit_username").value = username;
    document.getElementById("edit_role").value = role;
    document.getElementById("editUserModal").style.display = "block";
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById("editUserModal").style.display = "none";
}

// AJAX for Edit User using Fetch API
document.getElementById("editUserForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    fetch("process_user.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            Swal.fire({ icon: "success", title: data.message, timer: 2000, showConfirmButton: false })
            .then(() => location.reload());
        } else {
            Swal.fire({ icon: "error", title: data.message, timer: 2000, showConfirmButton: false });
        }
    })
    .catch(error => {
        console.error(error);
        Swal.fire({ icon: "error", title: "Ajax Error", text: error.message });
    });
});

// AJAX for Delete User using Fetch API
function deleteUser(id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This action cannot be undone.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#3085d6",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if(result.isConfirmed) {
            fetch("process_user.php?delete=" + id)
            .then(response => response.json())
            .then(data => {
                if(data.status === "success") {
                    Swal.fire({ icon: "success", title: data.message, timer: 2000, showConfirmButton: false })
                    .then(() => location.reload());
                } else {
                    Swal.fire({ icon: "error", title: data.message, timer: 2000, showConfirmButton: false });
                }
            })
            .catch(error => {
                console.error(error);
                Swal.fire({ icon: "error", title: "Ajax Error", text: error.message });
            });
        }
    });
}

// Print Users Function
function printUsers() {
    var printContents = document.getElementById("usersTable").outerHTML;
    var printWindow = window.open("", "", "width=800,height=600");
    printWindow.document.write("<html><head><title>Print Users</title></head><body>" + printContents + "</body></html>");
    printWindow.document.close();
    printWindow.print();
}
</script>


<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var ctx = document.getElementById('liabilityChart').getContext('2d');
        var liabilityChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($liabilityNames) ?>,
                datasets: [
                    {
                        label: 'Total Liabilities (KSh)',
                        data: <?= json_encode($totalAmounts) ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.6)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Payments Made (KSh)',
                        data: <?= json_encode($totalPaid) ?>,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>






<script>
    function toggleLiabilityForm() {
        var form = document.getElementById("add-liability-form");
        form.style.display = form.style.display === "none" ? "block" : "none";
    }

    function openPaymentForm(id, description, balance) {
        document.getElementById("payment_liability_id").value = id;
        document.getElementById("payment_description").innerHTML = "<strong>Paying for:</strong> " + description + "<br><strong>Remaining Balance:</strong> KSh " + balance;
        document.getElementById("paymentModal").style.display = "block";
    }

    function closePaymentModal() {
        document.getElementById("paymentModal").style.display = "none";
    }
</script>

<script>
        // Function to switch between tabs
        function showTab(tabId) {
            // Remove active class from both tab buttons and sections
            document.getElementById('plTab').classList.remove('active');
            document.getElementById('bsTab').classList.remove('active');
            document.getElementById('profitLoss').classList.remove('active');
            document.getElementById('balanceSheet').classList.remove('active');
            
            // Add active class to the selected tab button and section
            if (tabId === 'profitLoss') {
                document.getElementById('plTab').classList.add('active');
                document.getElementById('profitLoss').classList.add('active');
            } else if (tabId === 'balanceSheet') {
                document.getElementById('bsTab').classList.add('active');
                document.getElementById('balanceSheet').classList.add('active');
            }
        }
        
        // Function to print a specific section
        function printSection(divId) {
            var printContents = document.getElementById(divId).innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            location.reload();
        }
    </script>

<!-- Include Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var ctx = document.getElementById('eggChart').getContext('2d');
        var eggChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($dates) ?>,
                datasets: [
                    {
                        label: 'Eggs Produced',
                        data: <?= json_encode($producedData) ?>,
                        backgroundColor: 'rgba(255, 205, 86, 0.5)',
                        borderColor: 'rgba(255, 205, 86, 1)',
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: 'Eggs Sold',
                        data: <?= json_encode($soldData) ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function deleteRecord(entity, id) {
    Swal.fire({
        title: "Are you sure?",
        text: "This action cannot be undone!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, delete it!",
        cancelButtonText: "Cancel"
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append("entity", entity);
            formData.append("id", id);
            
            fetch("process_deletion.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === "success") {
                    Swal.fire({
                        icon: "success",
                        title: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: data.message,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            })
            .catch(error => {
                console.error("Error:", error);
                Swal.fire({
                    icon: "error",
                    title: "Ajax Error",
                    text: error.message,
                    timer: 3000,
                    showConfirmButton: false
                });
            });
        }
    });
}
</script>
<script>
    // Scroll to the reports section after page reload
    window.onload = function() {
        if (window.location.hash === "#reportsSection") {
            document.getElementById("reportsSection").scrollIntoView();
        }
    };
</script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>

 <script>
        document.getElementById('timestamp').innerText = new Date().toLocaleString();
    </script>
     <script>
        document.getElementById('time').innerText = new Date().toLocaleString();
    </script>
    <script>
        let deferredPrompt;

window.addEventListener("beforeinstallprompt", (event) => {
  event.preventDefault();
  deferredPrompt = event;
  document.getElementById("installBtn").style.display = "block";
});

document.getElementById("installBtn").addEventListener("click", () => {
  if (deferredPrompt) {
    deferredPrompt.prompt();
    deferredPrompt.userChoice.then((choiceResult) => {
      if (choiceResult.outcome === "accepted") {
        console.log("User accepted PWA installation");
      } else {
        console.log("User dismissed PWA installation");
      }
      deferredPrompt = null;
      document.getElementById("installBtn").style.display = "none";
    });
  }
});

    </script>
    
    <script>
document.addEventListener("DOMContentLoaded", function() {
    loadStockOptions();
    loadMortalities();

    document.getElementById("mortalityForm").addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch("process_mortality.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === "success") {
                Swal.fire({
                    icon: "success",
                    title: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    this.reset();
                    loadMortalities();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: data.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        })
        .catch(error => {
            console.error("Error:", error);
            Swal.fire({
                icon: "error",
                title: "Ajax Error",
                text: error.message
            });
        });
    });
});

// Load Stock Options for Selection
function loadStockOptions() {
    fetch("fetch_stock.php") // You'll need to create this script
    .then(response => response.json())
    .then(data => {
        let stockSelect = document.getElementById("stock_id");
        stockSelect.innerHTML = "";
        data.forEach(stock => {
            let option = document.createElement("option");
            option.value = stock.id;
            option.textContent = `${stock.stock_type} (Available: ${stock.quantity})`;
            stockSelect.appendChild(option);
        });
    })
    .catch(error => console.error("Error loading stock:", error));
}

// Load Mortality Records
function loadMortalities() {
    fetch("fetch_mortalities.php") // You'll need to create this script
    .then(response => response.json())
    .then(data => {
        let tbody = document.getElementById("mortalityTableBody");
        tbody.innerHTML = "";
        data.forEach(row => {
            let tr = document.createElement("tr");
            tr.innerHTML = `
                <td>${row.id}</td>
                <td>${row.stock_type}</td>
                <td>${row.quantity}</td>
                <td>${row.mortality_date}</td>
                <td>${row.reason}</td>
            `;
            tbody.appendChild(tr);
        });
    })
    .catch(error => console.error("Error loading mortalities:", error));
}
</script>
<script>
fetch('fetch_stock_data.php')
  .then(response => response.json())
  .then(data => {
    new Chart(document.getElementById('stockChart'), {
      type: 'pie',
      data: {
        labels: data.types,
        datasets: [{
          data: data.quantities,
          backgroundColor: ['blue', 'green', 'orange']
        }]
      }
    });
  });
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
fetch('fetch_mortality_data.php')
  .then(response => response.json())
  .then(data => {
    const ctx = document.getElementById('mortalityChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.dates,  
        datasets: [{
          label: 'Mortalities Over Time',
          data: data.quantities,
          borderColor: 'red',
          fill: false
        }]
      }
    });
  });
</script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
    fetchDashboardData();
});

function fetchDashboardData() {
    Promise.all([
        fetch("fetch_stock_data.php").then(response => response.json()),
        fetch("fetch_mortality_data.php").then(response => response.json())
    ])
    .then(([stockData, mortalityData]) => {
        // Calculate totals
        const totalStock = stockData.quantities.reduce((a, b) => a + b, 0);
        const totalMortalities = mortalityData.quantities.reduce((a, b) => a + b, 0);
        const remainingStock = totalStock - totalMortalities;
        const mortalityRate = totalStock > 0 ? ((totalMortalities / totalStock) * 100).toFixed(2) : 0;

        // Update dashboard values
        document.getElementById("totalStock").textContent = totalStock;
        document.getElementById("totalMortalities").textContent = totalMortalities;
        document.getElementById("mortalityRate").textContent = mortalityRate + "%";
        document.getElementById("remainingStock").textContent = remainingStock;
    })
    .catch(error => console.error("Error fetching dashboard data:", error));
}

</script>
</body>
</html>
