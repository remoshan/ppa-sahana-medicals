<?php
session_start();
include 'config/database.php';
include 'config/functions.php';

// Function to load cart from database into session
function loadCartFromDatabase($pdo, $customer_id) {
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

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: auth.php?mode=login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    
    if ($medicine_id <= 0 || $quantity <= 0) {
        $_SESSION['error'] = 'Invalid medicine or quantity.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'medicines.php'));
        exit;
    }
    
    try {
        // Get medicine details
        $stmt = $pdo->prepare("SELECT * FROM medicines WHERE id = ? AND status = 'active'");
        $stmt->execute([$medicine_id]);
        $medicine = $stmt->fetch();
        
        if (!$medicine) {
            $_SESSION['error'] = 'Medicine not found.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'medicines.php'));
            exit;
        }
        
        // Check if quantity is available
        if ($quantity > $medicine['quantity']) {
            $_SESSION['error'] = 'Requested quantity exceeds available stock.';
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'medicines.php'));
            exit;
        }
        
        // Check if medicine requires prescription
        if ($medicine['prescription_required']) {
            // Redirect to prescription upload page
            $_SESSION['prescription_medicine_id'] = $medicine_id;
            $_SESSION['prescription_quantity'] = $quantity;
            $_SESSION['info'] = 'This medicine requires a prescription. Please upload your prescription to continue.';
            header('Location: submit_prescription.php');
            exit;
        }
        
        // Check if medicine already in cart (database)
        $stmt = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND medicine_id = ?");
        $stmt->execute([$_SESSION['user_id'], $medicine_id]);
        $cart_item = $stmt->fetch();
        
        if ($cart_item) {
            // Update quantity in database
            $new_quantity = $cart_item['quantity'] + $quantity;
            if ($new_quantity > $medicine['quantity']) {
                $_SESSION['error'] = 'Cannot add more items. Stock limit reached.';
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'medicines.php'));
                exit;
            }
            
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, price = ? WHERE customer_id = ? AND medicine_id = ?");
            $stmt->execute([$new_quantity, $medicine['price'], $_SESSION['user_id'], $medicine_id]);
        } else {
            // Add new item to database
            $stmt = $pdo->prepare("INSERT INTO cart (customer_id, medicine_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $medicine_id, $quantity, $medicine['price']]);
        }
        
        // Reload cart from database into session
        loadCartFromDatabase($pdo, $_SESSION['user_id']);
        
        $_SESSION['success'] = 'Medicine added to cart successfully!';
        header('Location: cart.php');
        exit;
        
    } catch (PDOException $e) {
        $_SESSION['error'] = 'An error occurred. Please try again.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'medicines.php'));
        exit;
    }
} else {
    header('Location: medicines.php');
    exit;
}
?>

