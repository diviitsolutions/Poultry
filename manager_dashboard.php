<?php
session_start();
require_once 'db.php';

// Only allow manager users to access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'manager') {
    header("Location: index.php");
    exit();
}

// Determine which section should be active. Default is 'dashboardSection'
$activeSection = $_GET['section'] ?? 'dashboardSection';

// Helper function to get data by date range for a given table and date column
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

// Date filters for each section
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

// Fetch real data from the database
$eggData     = getData($pdo, 'egg_production', 'date', $eggStart, $eggEnd);
$expenseData = getData($pdo, 'expenses', 'date', $expenseStart, $expenseEnd);
$feedData    = getData($pdo, 'feeds', 'purchased_at', $feedStart, $feedEnd);
$medData     = getData($pdo, 'medications', 'purchased_at', $medStart, $medEnd);
$saleData    = getData($pdo, 'sales', 'sale_date', $saleStart, $saleEnd);
$assetData   = getData($pdo, 'farm_assets', 'purchased_at', $assetStart, $assetEnd);

// (Optional) Calculate stats for overview cards (using your business logic)
$totalEggs = 0;
foreach($eggData as $row) {
    $totalEggs += $row['quantity'];
}
$totalExpenses = 0;
foreach($expenseData as $row) {
    $totalExpenses += $row['amount'];
}
// (Other stats can be calculated similarly)

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manager Dashboard - Moses Poultry Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* Basic Reset */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .wrapper { display: flex; min-height: 100vh; }
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #343a40;
            color: #fff;
            transition: transform 0.3s ease;
        }
        .sidebar h2 {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #495057;
        }
        .sidebar ul { list-style: none; }
        .sidebar ul li { border-bottom: 1px solid #495057; }
        .sidebar ul li a {
            display: block;
            color: #fff;
            padding: 15px 20px;
            text-decoration: none;
        }
        .sidebar ul li a:hover { background: #495057; }
        /* Topbar */
        .topbar {
            background: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0px 2px 5px rgba(0,0,0,0.1);
        }
        .toggle-btn { font-size: 20px; cursor: pointer; display: none; }
        /* Content */
        .content { flex: 1; padding: 20px; }
        .card-container { display: flex; flex-wrap: wrap; gap: 20px; }
        .card {
            background: #fff;
            flex: 1;
            min-width: 200px;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        /* Section styling */
        .section { display: none; }
        .section.active { display: block; }
        .form-container { background: #fff; padding: 15px; margin-top: 20px; border-radius: 5px; }
        .form-container form label { display: block; margin: 8px 0 4px; }
        .form-container form input, .form-container form select, .form-container form textarea {
            width: 100%; padding: 8px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px;
        }
        .form-container form button {
            background: #28a745; color: #fff; border: none; padding: 10px;
            border-radius: 4px; cursor: pointer;
        }
        .filter-form { margin-bottom: 15px; }
        .filter-form input { margin-right: 10px; }
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .print-btn { background: #007bff; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                height: 100%;
                transform: translateX(-100%);
                z-index: 1000;
            }
            .sidebar.active { transform: translateX(0); }
            .toggle-btn { display: block; }
            .wrapper { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="topbar">
    <span class="toggle-btn"><i class="fas fa-bars"></i></span>
    <h1>Moses PMS - Manager Dashboard</h1>
    <div>
        <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
        <a href="logout.php" style="margin-left: 15px; text-decoration: none; color: #333;">Logout</a>
    </div>
</div>

<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Moses PMS</h2>
        <ul>
            <li><a href="?section=dashboardSection"> <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="?section=eggProductionSection"> <i class="fas fa-egg"></i> Egg Production</a></li>
            <li><a href="?section=expensesSection"> <i class="fas fa-money-bill-wave"></i> Expenses</a></li>
            <li><a href="?section=feedsSection"> <i class="fas fa-drumstick-bite"></i> Feeds</a></li>
            <li><a href="?section=medicationsSection"> <i class="fas fa-pills"></i> Medications</a></li>
            <li><a href="?section=salesSection"> <i class="fas fa-shopping-cart"></i> Sales</a></li>
            <li><a href="?section=assetsSection"> <i class="fas fa-building"></i> Assets</a></li>
            <li><a href="?section=reportsSection"> <i class="fas fa-chart-line"></i> Reports</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="content">
        <!-- Dashboard Overview -->
        <section id="dashboardSection" class="section <?php echo ($activeSection === 'dashboardSection') ? 'active' : ''; ?>">
            <h2>Dashboard Overview</h2>
            <div class="card-container">
                <div class="card">
                    <h3>Total Egg Production</h3>
                    <p><?php echo $totalEggs; ?></p>
                </div>
                <div class="card">
                    <h3>Total Expenses</h3>
                    <p><?php echo number_format($totalExpenses, 2); ?></p>
                </div>
                <div class="card">
                    <h3>Total Sales</h3>
                    <p><!-- Calculate total sales as needed --></p>
                </div>
                <!-- Add additional stat cards as needed -->
            </div>
            <div style="margin-top:20px;">
                <h3>Graphical Overview</h3>
                <!-- Placeholder for graphs/charts -->
                <div style="width:100%; height:300px; background:#fff; border:1px solid #ccc; border-radius:8px; text-align:center; line-height:300px;">
                    Charts/Graphs here
                </div>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eggData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['batch_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['damaged']); ?></td>
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
                    <input type="number" name="batch_id" required>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenseData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['date']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['amount']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Expense</h3>
                <form method="post" action="process_expense.php">
                    <label>Category:</label>
                    <select name="category" required>
                        <option value="feed">Feed</option>
                        <option value="medication">Medication</option>
                        <option value="utilities">Utilities</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="salary">Salary</option>
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
            <div id="feedData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Supplier ID</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost</th>
                            <th>Purchased Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['supplier_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td><?php echo htmlspecialchars($row['cost']); ?></td>
                            <td><?php echo htmlspecialchars($row['purchased_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Feed Purchase</h3>
                <form method="post" action="process_feed.php">
                    <label>Feed Name:</label>
                    <input type="text" name="name" required>
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
            <div id="medData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Supplier ID</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Cost</th>
                            <th>Purchased Date</th>
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
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Medication Purchase</h3>
                <form method="post" action="process_medication.php">
                    <label>Medication Name:</label>
                    <input type="text" name="name" required>
                    <label>Supplier ID (Optional):</label>
                    <input type="number" name="supplier_id">
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
        
        <!-- Sales Section -->
        <section id="salesSection" class="section <?php echo ($activeSection === 'salesSection') ? 'active' : ''; ?>">
            <h2>Sales</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="salesSection">
                <label>From: <input type="date" name="sale_start" value="<?php echo $saleStart; ?>"></label>
                <label>To: <input type="date" name="sale_end" value="<?php echo $saleEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('saleData')">Print</button>
            </form>
            <div id="saleData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Type</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Total Price</th>
                            <th>Customer ID</th>
                            <th>Sale Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($saleData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['item_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit_price']); ?></td>
                            <td><?php echo htmlspecialchars($row['total_price']); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_id']); ?></td>
                            <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Sale</h3>
                <form method="post" action="process_sale.php">
                    <label>Item Type:</label>
                    <select name="item_type" required>
                        <option value="egg">Egg</option>
                        <option value="chick">Chick</option>
                    </select>
                    <label>Quantity:</label>
                    <input type="number" name="quantity" required>
                    <label>Unit Price:</label>
                    <input type="number" step="0.01" name="unit_price" required>
                    <label>Customer ID (Optional):</label>
                    <input type="number" name="customer_id">
                    <label>Sale Date:</label>
                    <input type="date" name="sale_date" required>
                    <button type="submit" name="addSale">Add Sale</button>
                </form>
            </div>
        </section>
        
        <!-- Assets Section -->
        <section id="assetsSection" class="section <?php echo ($activeSection === 'assetsSection') ? 'active' : ''; ?>">
            <h2>Assets</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="section" value="assetsSection">
                <label>From: <input type="date" name="asset_start" value="<?php echo $assetStart; ?>"></label>
                <label>To: <input type="date" name="asset_end" value="<?php echo $assetEnd; ?>"></label>
                <button type="submit">Filter</button>
                <button type="button" class="print-btn" onclick="printSection('assetData')">Print</button>
            </form>
            <div id="assetData">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Value</th>
                            <th>Purchased Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['value']); ?></td>
                            <td><?php echo htmlspecialchars($row['purchased_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="form-container">
                <h3>Add Asset</h3>
                <form method="post" action="process_asset.php">
                    <label>Asset Name:</label>
                    <input type="text" name="name" required>
                    <label>Category:</label>
                    <select name="category" required>
                        <option value="equipment">Equipment</option>
                        <option value="building">Building</option>
                        <option value="vehicle">Vehicle</option>
                        <option value="land">Land</option>
                    </select>
                    <label>Value:</label>
                    <input type="number" step="0.01" name="value" required>
                    <label>Purchased Date:</label>
                    <input type="date" name="purchased_at" required>
                    <button type="submit" name="addAsset">Add Asset</button>
                </form>
            </div>
        </section>
        
        <!-- Reports Section -->
        <section id="reportsSection" class="section <?php echo ($activeSection === 'reportsSection') ? 'active' : ''; ?>">
            <h2>Reports</h2>
            <div class="form-container">
                <h3>Generate Profit & Loss Report</h3>
                <!-- In a real system, you might query your database to compile the report -->
                <button onclick="generateReport('profit_loss')">Generate Profit & Loss</button>
            </div>
            <div class="form-container">
                <h3>Generate Balance Sheet</h3>
                <button onclick="generateReport('balance_sheet')">Generate Balance Sheet</button>
            </div>
        </section>
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toggle sidebar for mobile
    document.querySelector('.toggle-btn').addEventListener('click', function(){
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // Print function for a given div
    function printSection(divId) {
        var printContents = document.getElementById(divId).innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
    
    // Function to simulate report generation.
    function generateReport(reportType) {
        // Here you could make an AJAX call to fetch report data from the server.
        Swal.fire({
            icon: 'info',
            title: 'Report Generated',
            text: 'The ' + reportType.replace('_', ' ') + ' has been generated and sent to the printer.',
            timer: 3000,
            showConfirmButton: false
        });
        // Optionally call window.print() to print the report.
    }
    
    // Display SweetAlert message if URL parameters exist
    <?php if(isset($_GET['message'])): ?>
    Swal.fire({
        icon: '<?php echo $_GET['type'] ?? "success"; ?>',
        title: '<?php echo $_GET['message']; ?>',
        timer: 3000,
        showConfirmButton: false
    });
    <?php endif; ?>
</script>
</body>
</html>
