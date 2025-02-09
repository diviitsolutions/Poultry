<?php
// process_supplier.php
session_start();
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addSupplier'])) {
    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    if ($name && $phone && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, created_at) VALUES (:name, :phone, :email, NOW())");
            $stmt->execute([
                'name'  => $name,
                'phone' => $phone,
                'email' => $email
            ]);
            // Get the inserted supplier's ID
            $supplierId = $pdo->lastInsertId();
            header("Location: admin_dashboard.php?section=suppliersSection&message=Supplier+added+successfully+with+ID:+$supplierId&type=success");
            exit();
        } catch (PDOException $e) {
            header("Location: admin_dashboard.php?section=suppliersSection&message=Error+adding+supplier:+".urlencode($e->getMessage())."&type=error");
            exit();
        }
    } else {
        header("Location: admin_dashboard.php?section=suppliersSection&message=Please+fill+all+fields&type=error");
        exit();
    }
}
?>
