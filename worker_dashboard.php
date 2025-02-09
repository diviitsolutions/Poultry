<?php
session_start();
require_once 'db.php';

// Only allow worker users to access this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'worker') {
    header("Location: index.php");
    exit();
}

// Determine active section. Default is dashboard overview.
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

// For workers, we'll provide Egg Production data only.
$eggStart = $_GET['egg_start'] ?? null;
$eggEnd   = $_GET['egg_end'] ?? null;
$eggData  = getData($pdo, 'egg_production', 'date', $eggStart, $eggEnd);

// Calculate simple stats (e.g., total eggs recorded)
$totalEggs = 0;
foreach($eggData as $row) {
    $totalEggs += $row['quantity'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker Dashboard - Moses Poultry Management System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- FontAwesome -->
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
    <h1>Moses PMS - Worker Dashboard</h1>
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
                <!-- Additional cards can be added if needed -->
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
    </div>
</div>

<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Toggle sidebar for mobile
    document.querySelector('.toggle-btn').addEventListener('click', function(){
        document.querySelector('.sidebar').classList.toggle('active');
    });
    
    // Print function
    function printSection(divId) {
        var printContents = document.getElementById(divId).innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload();
    }
    
    // SweetAlert message if URL parameters exist
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
