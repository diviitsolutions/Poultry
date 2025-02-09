<?php
require 'db.php';

$stmt = $pdo->query("SELECT * FROM mortalities ORDER BY mortality_date DESC");
$mortalities = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($mortalities);
?>
