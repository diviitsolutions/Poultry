<?php
require 'db.php';
header('Content-Type: application/json'); // Ensure JSON response

$response = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $liability_id = trim($_POST['liability_id']);
    $amount_paid = trim($_POST['amount_paid']);
    $payment_date = trim($_POST['payment_date']);
    $payment_method = trim($_POST['payment_method']);
    $notes = trim($_POST['notes']);

    // Validate Inputs
    if (empty($liability_id) || empty($amount_paid) || empty($payment_date) || empty($payment_method)) {
        echo json_encode(["status" => "error", "message" => "All required fields must be filled!"]);
        exit();
    }

    try {
        // --- Calculate Available Funds ---
        // 1. Total Capital Invested
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_capital FROM capital");
        $totalCapital = floatval($stmt->fetch()['total_capital'] ?? 0);

        // 2. Total Sales Income
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_price), 0) AS total_sales FROM sales");
        $totalSales = floatval($stmt->fetch()['total_sales'] ?? 0);

        // 3. Total Existing Expenses
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) AS total_expenses FROM expenses");
        $totalExpenses = floatval($stmt->fetch()['total_expenses'] ?? 0);

        // 4. Total Feeds Cost
        $stmt = $pdo->query("SELECT COALESCE(SUM(cost), 0) AS total_feeds FROM feeds");
        $totalFeeds = floatval($stmt->fetch()['total_feeds'] ?? 0);

        // 5. Total Medications Cost
        $stmt = $pdo->query("SELECT COALESCE(SUM(cost), 0) AS total_medications FROM medications");
        $totalMedications = floatval($stmt->fetch()['total_medications'] ?? 0);

        // 6. Total Liabilities Paid
        $stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) AS total_liability_payment FROM liability_payments");
        $totalLiabilityPayments = floatval($stmt->fetch()['total_liability_payment'] ?? 0);

        // 7. Total Assets Value
        $stmt = $pdo->query("SELECT COALESCE(SUM(value), 0) AS total_assets FROM farm_assets");
        $totalAssets = floatval($stmt->fetch()['total_assets'] ?? 0);

        // 8. Total Salaries Paid
        $stmt = $pdo->query("SELECT COALESCE(SUM(salary), 0) AS total_salaries FROM salaries");
        $totalSalaries = floatval($stmt->fetch()['total_salaries'] ?? 0);

        // 9. Total Stock Purchases
        $stmt = $pdo->query("SELECT COALESCE(SUM(purchase_cost), 0) AS total_stock FROM stock");
        $totalStock = floatval($stmt->fetch()['total_stock'] ?? 0);

        // Calculate available funds:
        $available_cash = ($totalCapital + $totalSales) - ($totalFeeds + $totalExpenses + $totalMedications + $totalAssets + $totalSalaries + $totalLiabilityPayments + $totalStock);

        // Check if there is enough money to process the payment
        if ($amount_paid > $available_cash) {
            echo json_encode(["status" => "error", "message" => "Insufficient funds to process this payment!"]);
            exit();
        }

        // Step 3: Insert into liability_payments table
        $stmt = $pdo->prepare("INSERT INTO liability_payments (liability_id, amount_paid, payment_date, payment_method, notes) 
                               VALUES (:liability_id, :amount_paid, :payment_date, :payment_method, :notes)");
        $stmt->execute([
            ':liability_id' => $liability_id,
            ':amount_paid' => $amount_paid,
            ':payment_date' => $payment_date,
            ':payment_method' => $payment_method,
            ':notes' => $notes
        ]);

        // Step 4: Update liabilities table by adding the amount paid
        $stmt = $pdo->prepare("UPDATE liabilities SET paid_amount = paid_amount + :amount_paid WHERE id = :liability_id");
        $stmt->execute([
            ':amount_paid' => $amount_paid,
            ':liability_id' => $liability_id
        ]);

        // Step 5: Check if liability is fully paid
        $stmt = $pdo->prepare("SELECT amount, paid_amount FROM liabilities WHERE id = :liability_id");
        $stmt->execute([':liability_id' => $liability_id]);
        $liability = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($liability && $liability['paid_amount'] >= $liability['amount']) {
            // Update status to "paid"
            $stmt = $pdo->prepare("UPDATE liabilities SET status = 'paid' WHERE id = :liability_id");
            $stmt->execute([':liability_id' => $liability_id]);
        }

        echo json_encode(["status" => "success", "message" => "Payment recorded successfully!"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}
?>


