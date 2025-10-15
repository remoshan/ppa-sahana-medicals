<?php
session_start();
include 'config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please log in to update cart.']);
    exit;
}

// Function to sync cart from database to session
function syncCartFromDB($pdo, $customer_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, m.name, m.quantity as max_quantity 
        FROM cart c 
        JOIN medicines m ON c.medicine_id = m.id 
        WHERE c.customer_id = ? AND m.status = 'active'
    ");
    $stmt->execute([$customer_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $_SESSION['cart'] = [];
    foreach ($cart_items as $item) {
        $_SESSION['cart'][] = [
            'medicine_id' => $item['medicine_id'],
            'name' => $item['name'],
            'price' => $item['price'],
            'quantity' => $item['quantity'],
            'max_quantity' => $item['max_quantity']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_quantity' && isset($_POST['key']) && isset($_POST['quantity'])) {
            $key = (int)$_POST['key'];
            $quantity = (int)$_POST['quantity'];
            
            if (isset($_SESSION['cart'][$key])) {
                $medicine_id = $_SESSION['cart'][$key]['medicine_id'];
                
                if ($quantity > 0) {
                    // Check if quantity doesn't exceed max
                    if ($quantity <= $_SESSION['cart'][$key]['max_quantity']) {
                        // Update database
                        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE customer_id = ? AND medicine_id = ?");
                        $stmt->execute([$quantity, $_SESSION['user_id'], $medicine_id]);
                        
                        // Sync cart from database
                        syncCartFromDB($pdo, $_SESSION['user_id']);
                        
                        // Calculate new totals
                        $subtotal = 0;
                        $itemCount = 0;
                        foreach ($_SESSION['cart'] as $item) {
                            $subtotal += $item['price'] * $item['quantity'];
                            $itemCount += $item['quantity'];
                        }
                        
                        // Find the updated item's new subtotal
                        $itemSubtotal = 0;
                        foreach ($_SESSION['cart'] as $item) {
                            if ($item['medicine_id'] == $medicine_id) {
                                $itemSubtotal = $item['price'] * $item['quantity'];
                                break;
                            }
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Cart updated successfully!',
                            'itemSubtotal' => number_format($itemSubtotal, 2),
                            'cartSubtotal' => number_format($subtotal, 2),
                            'cartTotal' => number_format($subtotal, 2),
                            'cartCount' => $itemCount
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Quantity exceeds available stock (' . $_SESSION['cart'][$key]['max_quantity'] . ' available)',
                            'maxQuantity' => $_SESSION['cart'][$key]['max_quantity']
                        ]);
                    }
                } elseif ($quantity == 0) {
                    // Remove from database
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? AND medicine_id = ?");
                    $stmt->execute([$_SESSION['user_id'], $medicine_id]);
                    
                    // Sync cart from database
                    syncCartFromDB($pdo, $_SESSION['user_id']);
                    
                    // Calculate new totals
                    $subtotal = 0;
                    $itemCount = 0;
                    foreach ($_SESSION['cart'] as $item) {
                        $subtotal += $item['price'] * $item['quantity'];
                        $itemCount += $item['quantity'];
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Item removed from cart!',
                        'removed' => true,
                        'cartSubtotal' => number_format($subtotal, 2),
                        'cartTotal' => number_format($subtotal, 2),
                        'cartCount' => $itemCount,
                        'isEmpty' => empty($_SESSION['cart'])
                    ]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
            }
        } elseif ($action === 'remove' && isset($_POST['key'])) {
            $key = (int)$_POST['key'];
            if (isset($_SESSION['cart'][$key])) {
                $medicine_id = $_SESSION['cart'][$key]['medicine_id'];
                
                // Remove from database
                $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ? AND medicine_id = ?");
                $stmt->execute([$_SESSION['user_id'], $medicine_id]);
                
                // Sync cart from database
                syncCartFromDB($pdo, $_SESSION['user_id']);
                
                // Calculate new totals
                $subtotal = 0;
                $itemCount = 0;
                foreach ($_SESSION['cart'] as $item) {
                    $subtotal += $item['price'] * $item['quantity'];
                    $itemCount += $item['quantity'];
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Item removed from cart!',
                    'removed' => true,
                    'cartSubtotal' => number_format($subtotal, 2),
                    'cartTotal' => number_format($subtotal, 2),
                    'cartCount' => $itemCount,
                    'isEmpty' => empty($_SESSION['cart'])
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Item not found in cart.']);
            }
        } elseif ($action === 'clear') {
            // Clear from database
            $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Clear session
            $_SESSION['cart'] = [];
            
            echo json_encode([
                'success' => true,
                'message' => 'Cart cleared!',
                'isEmpty' => true,
                'cartCount' => 0
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
