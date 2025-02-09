<?php
session_start();
require_once 'db.php';

// Set header to return JSON
header("Content-Type: application/json");

// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get POST data
$amount       = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$source       = trim($_POST['source'] ?? '');
$dateInvested = trim($_POST['date_invested'] ?? '');
$description  = trim($_POST['description'] ?? '');

if ($amount > 0 && !empty($source) && !empty($dateInvested)) {
    try {
        $stmt = $pdo->prepare("INSERT INTO capital (amount, source, date_invested, description, created_at) VALUES (:amount, :source, :date_invested, :description, NOW())");
        $stmt->execute([
            ':amount'       => $amount,
            ':source'       => $source,
            ':date_invested'=> $dateInvested,
            ':description'  => $description
        ]);
        $capitalId = $pdo->lastInsertId();
        echo json_encode([
            "status"  => "success",
            "message" => "Capital added successfully with ID: $capitalId"
        ]);
        exit();
    } catch (PDOException $e) {
        echo json_encode([
            "status"  => "error",
            "message" => "Database Error: " . $e->getMessage()
        ]);
        exit();
    }
} else {
    echo json_encode([
        "status"  => "error",
        "message" => "Please fill all required fields and ensure the amount is greater than zero."
    ]);
    exit();
}
?>
