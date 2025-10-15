<?php
session_start();
include 'config/database.php';
include 'config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: auth.php?mode=login');
    exit;
}

// Check if cart is empty
if (empty($_SESSION['cart'])) {
    $_SESSION['error'] = 'Your cart is empty!';
    header('Location: cart.php');
    exit;
}

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = trim($_POST['shipping_address']);
    $phone = trim($_POST['phone']);
    $payment_method = $_POST['payment_method'];
    $notes = trim($_POST['notes']);
    
    if (empty($shipping_address) || empty($phone)) {
        $error_message = 'Please provide shipping address and phone number.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Calculate total
            $total_amount = 0;
            foreach ($_SESSION['cart'] as $item) {
                $total_amount += $item['price'] * $item['quantity'];
            }
            
            // Generate unique order number
            $order_number = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Insert order
            $stmt = $pdo->prepare("
                INSERT INTO orders (customer_id, order_number, total_amount, status, payment_status, payment_method, shipping_address, notes) 
                VALUES (?, ?, ?, 'pending', 'pending', ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $order_number,
                $total_amount,
                $payment_method,
                $shipping_address,
                $notes
            ]);
            
            $order_id = $pdo->lastInsertId();
            
            // Insert order items and update medicine quantities
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, total_price) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $update_stmt = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            
            foreach ($_SESSION['cart'] as $item) {
                $item_total = $item['price'] * $item['quantity'];
                $stmt->execute([
                    $order_id,
                    $item['medicine_id'],
                    $item['quantity'],
                    $item['price'],
                    $item_total
                ]);
                
                // Update medicine stock
                $update_stmt->execute([$item['quantity'], $item['medicine_id']]);
            }
            
            // Generate payment number
            $payment_number = 'PAY-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
            // Record customer payment
            $payment_status = ($payment_method === 'cash_on_delivery') ? 'pending' : 'completed';
            $stmt = $pdo->prepare("
                INSERT INTO customer_payments (payment_number, order_id, customer_id, amount, payment_method, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $payment_number,
                $order_id,
                $_SESSION['user_id'],
                $total_amount,
                $payment_method,
                $payment_status
            ]);
            
            $pdo->commit();
            
            // Clear cart from database
            $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            // Clear cart from session
            unset($_SESSION['cart']);
            
            $_SESSION['success'] = 'Order placed successfully! Order Number: ' . $order_number;
            header('Location: my_orders.php');
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = 'An error occurred while placing your order. Please try again.';
        }
    }
}

// Calculate totals
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 0; // Free shipping
$total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-pills me-2"></i>Sahana Medicals
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medicines.php">Medicines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="cart.php">
                            <i class="fas fa-shopping-cart"></i>
                            <?php 
                            $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
                            if ($cart_count > 0): 
                            ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?php echo $cart_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="my_orders.php"><i class="fas fa-shopping-cart me-2"></i>My Orders</a></li>
                            <li><a class="dropdown-item" href="submit_prescription.php"><i class="fas fa-prescription me-2"></i>Submit Prescription</a></li>
                            <li><a class="dropdown-item" href="my_prescriptions.php"><i class="fas fa-clipboard-list me-2"></i>My Prescriptions</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php"><i class="fas fa-user-shield me-1"></i>Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Checkout</h1>
            <p>Complete your order</p>
        </div>
    </section>

    <!-- Checkout Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row g-4">
                    <div class="col-lg-8">
                        <!-- Shipping Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shipping-fast me-2"></i>Shipping Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" value="<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>" readonly>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($customer['email']); ?>" readonly>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number *</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" 
                                           pattern="[0-9]{10}" maxlength="10" required>
                                    <small class="form-text text-muted">Enter exactly 10 digits</small>
                                </div>
                                <div class="mb-3">
                                    <label for="shipping_address" class="form-label">Shipping Address *</label>
                                    <textarea class="form-control" id="shipping_address" name="shipping_address" rows="3" required><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Order Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Any special instructions..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-credit-card me-2"></i>Payment Method
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="cod" value="Cash on Delivery" checked>
                                    <label class="form-check-label" for="cod">
                                        <i class="fas fa-money-bill-wave me-2 text-success"></i><strong>Cash on Delivery</strong>
                                        <br>
                                        <small class="text-muted">Pay when you receive your order</small>
                                    </label>
                                </div>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="radio" name="payment_method" id="bank" value="Bank Transfer">
                                    <label class="form-check-label" for="bank">
                                        <i class="fas fa-university me-2 text-primary"></i><strong>Bank Transfer</strong>
                                        <br>
                                        <small class="text-muted">Transfer to our bank account</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="payment_method" id="card" value="Credit/Debit Card">
                                    <label class="form-check-label" for="card">
                                        <i class="fas fa-credit-card me-2 text-info"></i><strong>Credit/Debit Card</strong>
                                        <br>
                                        <small class="text-muted">Pay securely with your card</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Order Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-receipt me-2"></i>Order Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="mb-2">Items (<?php echo count($_SESSION['cart']); ?>)</h6>
                                    <?php foreach ($_SESSION['cart'] as $item): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <small><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</small>
                                            <small><?php echo formatPrice($item['price'] * $item['quantity']); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Subtotal:</span>
                                    <strong><?php echo formatPrice($subtotal); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Shipping:</span>
                                    <strong class="text-success">FREE</strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between mb-4">
                                    <h5>Total:</h5>
                                    <h5 class="text-primary"><?php echo formatPrice($total); ?></h5>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-check me-2"></i>Place Order
                                    </button>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="cart.php" class="btn btn-link">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Cart
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Security Badge -->
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-lock text-success mb-2 icon-2xl"></i>
                                <h6>Secure Checkout</h6>
                                <p class="small text-muted mb-0">Your information is protected and encrypted</p>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>
                        <i class="fas fa-pills me-2"></i>Sahana Medicals
                    </h4>
                    <p>Your trusted partner in health and wellness. We provide quality medicines and healthcare services with care and professionalism.</p>
                    <div class="social-links">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="medicines.php">Medicines</a></li>
                        <li><a href="categories.php">Categories</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Contact Info</h5>
                    <ul>
                        <li><i class="fas fa-map-marker-alt me-2"></i>QWWP+45C, Colombo - Horana Rd, Piliyandala</li>
                        <li><i class="fas fa-phone me-2"></i>0112702329</li>
                        <li><i class="fas fa-envelope me-2"></i>sahanamedicalenterprises@gmail.com</li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Business Hours</h5>
                    <ul>
                        <li>Monday - Friday: 8:00 AM - 10:00 PM</li>
                        <li>Saturday: 9:00 AM - 8:00 PM</li>
                        <li>Sunday: 10:00 AM - 6:00 PM</li>
                        <li>Emergency: 24/7</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; 2024 Sahana Medicals. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small>
                            <a href="#" class="me-3">Privacy Policy</a>
                            <a href="#">Terms of Service</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function() {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length > 10) {
                phone = phone.substring(0, 10);
            }
            this.value = phone;
        });
    </script>
</body>
</html>

