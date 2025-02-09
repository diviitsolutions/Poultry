<?php
require 'db.php'; // Ensure database connection

// Fetch total stock grouped by type, adding up recorded mortalities
$query = "
    SELECT s.stock_type, 
           SUM(s.quantity) AS total_stock, 
           COALESCE(SUM(m.quantity), 0) AS total_mortalities
    FROM stock s
    LEFT JOIN mortalities m ON s.stock_type = m.stock_type
    GROUP BY s.stock_type";

$stmt = $pdo->prepare($query);
$stmt->execute();
$stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JSON response
$types = [];
$quantities = [];

foreach ($stockData as $row) {
    $types[] = $row['stock_type'];
    $quantities[] = $row['total_stock'] + $row['total_mortalities']; 
}

// Return JSON response
echo json_encode(['types' => $types, 'quantities' => $quantities]);
?>
