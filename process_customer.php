<?php
// process_customer.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addCustomer'])) {
    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    if ($name && $phone && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, created_at) VALUES (:name, :phone, :email, NOW())");
            $stmt->execute([
                'name'  => $name,
                'phone' => $phone,
                'email' => $email
            ]);
            // Get the inserted customer's ID
            $customerId = $pdo->lastInsertId();
            header("Location: admin_dashboard.php?section=customersSection&message=Customer+added+successfully+with+ID:+$customerId&type=success");
            exit();
        } catch (PDOException $e) {
            header("Location: admin_dashboard.php?section=customersSection&message=Error+adding+customer:+".urlencode($e->getMessage())."&type=error");
            exit();
        }
    } else {
        header("Location: admin_dashboard.php?section=customersSection&message=Please+fill+all+fields&type=error");
        exit();
    }
}
?>
