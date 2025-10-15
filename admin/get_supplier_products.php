<?php
session_start();
include '../config/database.php';
include 'auth_check.php';

header('Content-Type: application/json');

$supplier_id = $_GET['supplier_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT sp.*, m.name as medicine_name
    FROM supplier_products sp
    JOIN medicines m ON sp.medicine_id = m.id
    WHERE sp.supplier_id = ? AND sp.status = 'active'
    ORDER BY m.name ASC
");
$stmt->execute([$supplier_id]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($products);
?>

