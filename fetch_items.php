<?php
session_start();
require_once 'db.php';

// Set header to return JSON response
header("Content-Type: application/json");

$category = trim($_GET['category'] ?? '');

// Initialize an empty array for results
$results = [];

if ($category === 'eggs') {
    // Calculate available eggs: total produced - total sold (for eggs)
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantity),0) AS total_produced FROM egg_production");
    $producedData = $stmt->fetch(PDO::FETCH_ASSOC);
    $produced = isset($producedData['total_produced']) ? intval($producedData['total_produced']) : 0;
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) AS total_sold FROM sales WHERE item_type = 'egg'");
    $stmt->execute();
    $soldData = $stmt->fetch(PDO::FETCH_ASSOC);
    $sold = isset($soldData['total_sold']) ? intval($soldData['total_sold']) : 0;
    
    $available = $produced - $sold;
    
    // Return a single object for eggs
    $results[] = [
        "id" => "egg",
        "name" => "Eggs",
        "available_quantity" => $available
    ];
} elseif ($category === 'assets') {
    // Select available assets from farm_assets (where quantity > 0)
    $stmt = $pdo->query("SELECT id, name, quantity AS available_quantity FROM farm_assets WHERE quantity > 0 ORDER BY name ASC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($category === 'stock') {
    // Group stock items by stock_type and sum the quantity (do not subtract mortality here)
    $stmt = $pdo->query("SELECT stock_type AS id, stock_type AS name, COALESCE(SUM(quantity),0) AS available_quantity FROM stock GROUP BY stock_type HAVING available_quantity > 0");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    echo json_encode([]);
    exit();
}

// Return the results as JSON
echo json_encode($results);
exit();
?>
