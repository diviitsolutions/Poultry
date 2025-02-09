<?php
require 'db.php';

$stmt = $pdo->query("SELECT id, stock_type, quantity FROM stock WHERE quantity > 0");
$stock = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($stock);
?>
