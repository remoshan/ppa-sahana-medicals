<?php
session_start();
include 'config/database.php';
include 'config/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: auth.php?mode=login');
    exit;
}

// Handle cart updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update' && isset($_POST['updates'])) {
            foreach ($_POST['updates'] as $key => $quantity) {
                $quantity = (int)$quantity;
                if ($quantity > 0 && isset($_SESSION['cart'][$key])) {
                    // Check if quantity doesn't exceed max
                    if ($quantity <= $_SESSION['cart'][$key]['max_quantity']) {
                        $_SESSION['cart'][$key]['quantity'] = $quantity;
                    } else {
                        $_SESSION['error'] = 'Quantity exceeds available stock for ' . $_SESSION['cart'][$key]['name'];
                    }
                } elseif ($quantity == 0) {
                    unset($_SESSION['cart'][$key]);
                }
            }
            $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
            $_SESSION['success'] = 'Cart updated successfully!';
        } elseif ($action === 'remove' && isset($_POST['key'])) {
            $key = (int)$_POST['key'];
            if (isset($_SESSION['cart'][$key])) {
                unset($_SESSION['cart'][$key]);
                $_SESSION['cart'] = array_values($_SESSION['cart']); // Re-index array
                $_SESSION['success'] = 'Item removed from cart!';
            }
        } elseif ($action === 'clear') {
            $_SESSION['cart'] = [];
            $_SESSION['success'] = 'Cart cleared!';
        }
    }
    header('Location: cart.php');
    exit;
}

// Calculate cart totals
$cart_items = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$tax = $subtotal * 0.0; // No tax for now
$total = $subtotal + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Sahana Medicals</title>
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
                        <a class="nav-link position-relative active" href="cart.php">
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
            <h1>Shopping Cart</h1>
            <p>Review your items before checkout</p>
        </div>
    </section>

    <!-- Cart Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (empty($cart_items)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card text-center py-5">
                            <div class="card-body">
                                <i class="fas fa-shopping-cart text-muted mb-4 icon-5xl icon-muted"></i>
                                <h3 class="text-muted">Your cart is empty</h3>
                                <p class="text-muted mb-4">Add some medicines to your cart to get started!</p>
                                <a href="medicines.php" class="btn btn-primary-hero btn-lg">
                                    <i class="fas fa-pills me-2"></i>Browse Medicines
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" id="cartForm">
                    <div class="row g-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-shopping-cart me-2"></i>Cart Items (<?php echo count($cart_items); ?>)
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Medicine</th>
                                                    <th>Price</th>
                                                    <th class="col-md">Quantity</th>
                                                    <th>Subtotal</th>
                                                    <th class="col-sm">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($cart_items as $key => $item): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">Available: <?php echo $item['max_quantity']; ?></small>
                                                    </td>
                                                    <td><?php echo formatPrice($item['price']); ?></td>
                                                    <td>
                                                        <input type="number" name="updates[<?php echo $key; ?>]" 
                                                               value="<?php echo $item['quantity']; ?>" 
                                                               min="1" max="<?php echo $item['max_quantity']; ?>" 
                                                               data-key="<?php echo $key; ?>"
                                                               class="form-control form-control-sm quantity-input">
                                                    </td>
                                                    <td>
                                                        <strong><?php echo formatPrice($item['price'] * $item['quantity']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <button type="button" 
                                                                onclick="removeItem(<?php echo $key; ?>, this)"
                                                                class="btn btn-outline-danger btn-sm">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>Cart updates automatically
                                        </small>
                                        <button type="button" class="btn btn-outline-danger"
                                                onclick="clearCart()">
                                            <i class="fas fa-trash me-2"></i>Clear Cart
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="medicines.php" class="btn btn-secondary-hero">
                                    <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                                </a>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-receipt me-2"></i>Order Summary
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Subtotal:</span>
                                        <strong><?php echo formatPrice($subtotal); ?></strong>
                                    </div>
                                    <div class="d-flex justify-content-between mb-3">
                                        <span>Shipping:</span>
                                        <strong class="text-success">FREE</strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between mb-4">
                                        <h5>Total:</h5>
                                        <h5 class="text-primary"><?php echo formatPrice($total); ?></h5>
                                    </div>
                                    <div class="d-grid">
                                        <a href="checkout.php" class="btn btn-submit btn-lg">
                                            <i class="fas fa-lock me-2"></i>Proceed to Checkout
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="fas fa-shield-alt me-2 text-success"></i>Safe & Secure</h6>
                                    <ul class="list-unstyled mb-0 small text-muted">
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Genuine medicines only</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Secure payment gateway</li>
                                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Fast delivery</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Easy returns</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
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

    <!-- Toast Container -->
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
        <div id="cartToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage">
                    Cart updated successfully!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toast notification function
        function showToast(message, type = 'success') {
            const toast = document.getElementById('cartToast');
            const toastBody = document.getElementById('toastMessage');
            
            // Set message
            toastBody.textContent = message;
            
            // Set colors based on type
            if (type === 'success') {
                toast.className = 'toast align-items-center text-white bg-success border-0';
            } else if (type === 'error') {
                toast.className = 'toast align-items-center text-white bg-danger border-0';
            } else {
                toast.className = 'toast align-items-center text-white bg-info border-0';
            }
            
            // Show toast
            const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
            bsToast.show();
        }

        // Update cart count in navbar
        function updateCartCount(count) {
            const cartBadge = document.querySelector('.nav-link.position-relative .badge');
            if (count > 0) {
                if (cartBadge) {
                    cartBadge.textContent = count;
                } else {
                    // Create badge if it doesn't exist
                    const cartLink = document.querySelector('.nav-link.position-relative');
                    if (cartLink) {
                        const badge = document.createElement('span');
                        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                        badge.textContent = count;
                        cartLink.appendChild(badge);
                    }
                }
            } else {
                if (cartBadge) {
                    cartBadge.remove();
                }
            }
        }

        // AJAX update quantity
        function updateQuantity(key, quantity, inputElement) {
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('key', key);
            formData.append('quantity', quantity);

            fetch('update_cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.removed) {
                        // Remove the row
                        const row = inputElement.closest('tr');
                        row.style.transition = 'opacity 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            
                            // Check if cart is empty
                            if (data.isEmpty) {
                                location.reload(); // Reload to show empty cart message
                            } else {
                                // Update totals
                                updateCartTotals(data);
                            }
                        }, 300);
                    } else {
                        // Update item subtotal
                        const subtotalCell = inputElement.closest('tr').querySelector('td:nth-child(4) strong');
                        if (subtotalCell) {
                            subtotalCell.textContent = '$' + data.itemSubtotal;
                        }
                        
                        // Update cart totals
                        updateCartTotals(data);
                    }
                    
                    showToast(data.message, 'success');
                    updateCartCount(data.cartCount);
                } else {
                    showToast(data.message, 'error');
                    // Reset to max quantity if exceeded
                    if (data.maxQuantity) {
                        inputElement.value = data.maxQuantity;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }

        // Update cart totals
        function updateCartTotals(data) {
            const subtotalElement = document.querySelector('.d-flex.justify-content-between.mb-3 strong');
            const totalElement = document.querySelector('.d-flex.justify-content-between.mb-4 h5.text-primary');
            const itemCountElement = document.querySelector('.card-header h5');
            
            if (subtotalElement) {
                subtotalElement.textContent = '$' + data.cartSubtotal;
            }
            if (totalElement) {
                totalElement.textContent = '$' + data.cartTotal;
            }
            if (itemCountElement && data.cartCount !== undefined) {
                const currentCount = document.querySelectorAll('tbody tr').length;
                itemCountElement.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>Cart Items (' + currentCount + ')';
            }
        }

        // AJAX remove item
        function removeItem(key, button) {
            if (!confirm('Are you sure you want to remove this item?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'remove');
            formData.append('key', key);

            fetch('update_cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = button.closest('tr');
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        
                        if (data.isEmpty) {
                            location.reload(); // Reload to show empty cart message
                        } else {
                            updateCartTotals(data);
                        }
                    }, 300);
                    
                    showToast(data.message, 'success');
                    updateCartCount(data.cartCount);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });
        }

        // AJAX clear cart
        function clearCart() {
            if (!confirm('Are you sure you want to clear your cart?')) {
                return false;
            }

            const formData = new FormData();
            formData.append('action', 'clear');

            fetch('update_cart_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 500);
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('An error occurred. Please try again.', 'error');
            });

            return false;
        }

        // Auto-update on quantity change
        document.addEventListener('DOMContentLoaded', function() {
        const quantityInputs = document.querySelectorAll('.quantity-input');
        quantityInputs.forEach(input => {
            let timeout;
                
                // Update on change (when input loses focus or Enter is pressed)
            input.addEventListener('change', function() {
                clearTimeout(timeout);
                    const key = this.getAttribute('data-key');
                    const quantity = parseInt(this.value);
                    
                    if (quantity >= 0) {
                        updateQuantity(key, quantity, this);
                    }
                });

                // Debounced update on input (while typing)
                input.addEventListener('input', function() {
                    clearTimeout(timeout);
                    const key = this.getAttribute('data-key');
                    const quantity = parseInt(this.value);
                    
                timeout = setTimeout(() => {
                        if (quantity >= 0) {
                            updateQuantity(key, quantity, this);
                        }
                    }, 1000); // Wait 1 second after user stops typing
                });
            });

            // Prevent form submission (we're using AJAX)
            const cartForm = document.getElementById('cartForm');
            if (cartForm) {
                cartForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    return false;
                });
            }
        });
    </script>
</body>
</html>

