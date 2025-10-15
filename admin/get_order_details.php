<?php
session_start();
include '../config/database.php';
include 'auth_check.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('Order ID is required');
    }
    
    $orderId = (int)$_GET['id'];
    
    // Fetch order details with customer information
    $stmt = $pdo->prepare("
        SELECT o.*, 
               c.first_name, c.last_name, c.email, c.phone, c.address
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Fetch order items with medicine names
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as medicine_name
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $response['success'] = true;
    $response['order'] = $order;
    $response['items'] = $items;
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

