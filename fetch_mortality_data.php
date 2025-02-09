<?php
require 'db.php'; // Ensure database connection

// Fetch mortalities grouped by date
$query = "SELECT DATE(mortality_date) AS mortality_date, COALESCE(SUM(quantity), 0) AS total_mortalities 
          FROM mortalities 
          GROUP BY DATE(mortality_date) 
          ORDER BY mortality_date ASC";

$stmt = $pdo->prepare($query);
$stmt->execute();
$mortalityData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for JSON response
$dates = [];
$quantities = [];

foreach ($mortalityData as $row) {
    $dates[] = $row['mortality_date'];
    $quantities[] = (int) $row['total_mortalities']; // Ensure integer values
}

// Return JSON response
echo json_encode(['dates' => $dates, 'quantities' => $quantities]);
?>
