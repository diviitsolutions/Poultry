<?php
require 'db.php';
header('Content-Type: application/json'); // Ensure JSON response

$response = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $description = trim($_POST['description']);
    $amount = trim($_POST['amount']);
    $liability_type = trim($_POST['liability_type']);
    $due_date = trim($_POST['due_date']);

    // Validate Input
    if (empty($description) || empty($amount) || empty($liability_type) || empty($due_date)) {
        echo json_encode(["status" => "error", "message" => "All fields are required!"]);
        exit();
    }

    try {
        // Insert Liability into Database
        $stmt = $pdo->prepare("INSERT INTO liabilities (description, amount, liability_type, due_date) 
                               VALUES (:description, :amount, :liability_type, :due_date)");
        $stmt->execute([
            ':description' => $description,
            ':amount' => $amount,
            ':liability_type' => $liability_type,
            ':due_date' => $due_date
        ]);

        $insertId = $pdo->lastInsertId();

        echo json_encode(["status" => "success", "message" => "Liability Added Successfully (ID: $insertId)"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
    }
}
?>
