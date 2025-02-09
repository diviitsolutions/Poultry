<?php
require 'db.php'; // Ensure database connection

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stock_id = $_POST['stock_id'];
    $mortality_date = $_POST['date'];
    $stock_type = $_POST['stock_type'];
    $quantity = intval($_POST['quantity']);
    $reason = trim($_POST['reason']);

    if ($quantity <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid quantity"]);
        exit;
    }

    // Check current stock level
    $stmt = $pdo->prepare("SELECT quantity FROM stock WHERE id = ?");
    $stmt->execute([$stock_id]);
    $stock = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stock) {
        echo json_encode(["status" => "error", "message" => "Stock item not found"]);
        exit;
    }

    if ($stock['quantity'] < $quantity) {
        echo json_encode(["status" => "error", "message" => "Not enough stock available"]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Deduct quantity from stock
        $updateStock = $pdo->prepare("UPDATE stock SET quantity = quantity - ? WHERE id = ?");
        $updateStock->execute([$quantity, $stock_id]);

        // Insert into mortalities table
        $insertMortality = $pdo->prepare("INSERT INTO mortalities (stock_id, mortality_date, stock_type, quantity, reason) VALUES (?, ?, ?, ?, ?)");
        $insertMortality->execute([$stock_id, $mortality_date, $stock_type, $quantity, $reason]);

        $pdo->commit();

        echo json_encode(["status" => "success", "message" => "Mortality recorded successfully"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "Failed to process mortality"]);
    }
}
?>
