<?php
// process_egg_production.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addEggProduction'])) {
    $date     = $_POST['date'] ?? '';
    $batchID = $_POST['batch_id'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $damaged  = $_POST['damaged'] ?? 0;

    if ($date && $batchID && $quantity !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO egg_production (date, batch_id, quantity, damaged) VALUES (:date, :batch_id, :quantity, :damaged)");
            $stmt->execute([
                'date'     => $date,
                'batch_id' => $batchID,
                'quantity' => $quantity,
                'damaged'  => $damaged
            ]);
            header("Location: admin_dashboard.php?message=Egg Production Added Successfully&type=success");
            exit();
        } catch (PDOException $e) {
            header("Location: admin_dashboard.php?message=Error Adding Egg Production: " . urlencode($e->getMessage()) . "&type=error");
            exit();
        }
    } else {
        header("Location: admin_dashboard.php?message=Please fill in all required fields for Egg Production&type=error");
        exit();
    }
}
?>
