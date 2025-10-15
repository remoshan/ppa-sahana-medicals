<?php
session_start();
include 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$customer_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.email, c.phone
        FROM orders o
        JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ? AND o.customer_id = ?
    ");
    $stmt->execute([$order_id, $customer_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['error' => 'Order not found or access denied']);
        exit;
    }

    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as medicine_name
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare customer data
    $customer = [
        'first_name' => $order['first_name'],
        'last_name' => $order['last_name'],
        'email' => $order['email'],
        'phone' => $order['phone']
    ];

    // Remove customer fields from order array
    unset($order['first_name']);
    unset($order['last_name']);
    unset($order['email']);
    unset($order['phone']);

    echo json_encode([
        'order' => $order,
        'customer' => $customer,
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

