<?php
session_start();
include '../config/database.php';
include '../config/functions.php';

// Secure authentication check
include 'auth_check.php';

// Get dashboard statistics
try {
    // Count total medicines
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE status = 'active'");
    $total_medicines = $stmt->fetch()['total'];
    
    // Count total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
    $total_customers = $stmt->fetch()['total'];
    
    // Count total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Count pending prescriptions
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'pending'");
    $pending_prescriptions = $stmt->fetch()['total'];
    
    // Count total staff
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM staff WHERE status = 'active'");
    $total_staff = $stmt->fetch()['total'];
    
    // Count categories
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE status = 'active'");
    $total_categories = $stmt->fetch()['total'];
    
    // NEW: Count suppliers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM suppliers WHERE status = 'active'");
    $total_suppliers = $stmt->fetch()['total'];
    
    // NEW: Count purchase orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM purchase_orders WHERE status != 'cancelled'");
    $total_purchase_orders = $stmt->fetch()['total'];
    
    // NEW: Count customer payments this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as total, SUM(amount) as total_amount 
        FROM customer_payments 
        WHERE MONTH(payment_date) = MONTH(CURDATE()) 
        AND YEAR(payment_date) = YEAR(CURDATE())
    ");
    $payment_stats = $stmt->fetch();
    $total_payments = $payment_stats['total'];
    $total_payment_amount = $payment_stats['total_amount'] ?? 0;
    
    // Get recent orders
    $stmt = $pdo->query("
        SELECT o.*, c.first_name, c.last_name 
        FROM orders o 
        LEFT JOIN customers c ON o.customer_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();
    
    // Get low stock medicines
    $stmt = $pdo->query("
        SELECT * FROM medicines 
        WHERE quantity <= 10 AND status = 'active' 
        ORDER BY quantity ASC 
        LIMIT 5
    ");
    $low_stock_medicines = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error loading dashboard data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <!-- Header -->
                <div class="admin-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1">Dashboard</h2>
                            <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">Last login: <?php echo date('M d, Y H:i'); ?></small>
                        </div>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-pills text-primary mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-primary"><?php echo number_format($total_medicines); ?></h3>
                                <p class="text-muted mb-0">Medicines</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-users text-success mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-success"><?php echo number_format($total_customers); ?></h3>
                                <p class="text-muted mb-0">Customers</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart text-warning mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-warning"><?php echo number_format($total_orders); ?></h3>
                                <p class="text-muted mb-0">Orders</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-file-medical text-info mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-info"><?php echo number_format($pending_prescriptions); ?></h3>
                                <p class="text-muted mb-0">Pending Rx</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-user-tie text-secondary mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-secondary"><?php echo number_format($total_staff); ?></h3>
                                <p class="text-muted mb-0">Staff</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-2 col-md-4 col-sm-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-tags text-dark mb-3" style="font-size: 2.5rem;"></i>
                                <h3 class="fw-bold text-dark"><?php echo number_format($total_categories); ?></h3>
                                <p class="text-muted mb-0">Categories</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW: Additional Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #667eea !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Active Suppliers</p>
                                        <h3 class="fw-bold mb-0"><?php echo number_format($total_suppliers); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-truck" style="font-size: 2.5rem; color: #667eea; opacity: 0.3;"></i>
                                    </div>
                                </div>
                                <a href="suppliers.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-arrow-right me-1"></i>View Suppliers
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f093fb !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Purchase Orders</p>
                                        <h3 class="fw-bold mb-0"><?php echo number_format($total_purchase_orders); ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-shopping-bag" style="font-size: 2.5rem; color: #f093fb; opacity: 0.3;"></i>
                                    </div>
                                </div>
                                <a href="purchase_orders.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-arrow-right me-1"></i>View POs
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #4facfe !important;">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1">Payments (This Month)</p>
                                        <h3 class="fw-bold mb-0">LKR <?php echo number_format($total_payment_amount, 2); ?></h3>
                                        <small class="text-muted"><?php echo number_format($total_payments); ?> transactions</small>
                                    </div>
                                    <div>
                                        <i class="fas fa-money-bill-wave" style="font-size: 2.5rem; color: #4facfe; opacity: 0.3;"></i>
                                    </div>
                                </div>
                                <a href="payments.php" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="fas fa-arrow-right me-1"></i>View Payments
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- NEW: System Updates Alert -->
                <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                    <h5 class="alert-heading">
                        <i class="fas fa-sparkles me-2"></i>New Features Added!
                    </h5>
                    <p class="mb-2">The following modules have been updated with new functionality:</p>
                    <ul class="mb-2">
                        <li><strong>Suppliers Management</strong> - Track suppliers and link medicines with supplier pricing</li>
                        <li><strong>Purchase Orders</strong> - Create POs and automatically update medicine stock when receiving</li>
                        <li><strong>Payment Management</strong> - Automatic customer payment recording from checkout</li>
                        <li><strong>Checkout Process</strong> - Now creates payment records automatically for all orders</li>
                    </ul>
                    <hr>
                    <p class="mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Updated Files:</strong> checkout.php, payments.php (NEW), suppliers.php, purchase_orders.php, sidebar menu
                    </p>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>

                <div class="row g-4">
                    <!-- Recent Orders -->
                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-cart me-2"></i>Recent Orders
                                </h5>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Order #</th>
                                                <th>Customer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <a href="orders.php?action=view&id=<?php echo $order['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($order['order_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $order['status'] === 'pending' ? 'warning' : ($order['status'] === 'delivered' ? 'success' : 'info'); ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer text-center">
                                <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                            </div>
                        </div>
                    </div>

                    <!-- Low Stock Alert -->
                    <div class="col-lg-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($low_stock_medicines)): ?>
                                    <div class="text-center text-success">
                                        <i class="fas fa-check-circle mb-2" style="font-size: 2rem;"></i>
                                        <p class="mb-0">All medicines are well stocked!</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($low_stock_medicines as $medicine): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($medicine['name']); ?></h6>
                                            <small class="text-muted">Batch: <?php echo htmlspecialchars($medicine['batch_number']); ?></small>
                                        </div>
                                        <span class="badge bg-danger"><?php echo $medicine['quantity']; ?> left</span>
                                    </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="medicines.php?filter=low_stock" class="btn btn-warning btn-sm">Manage Stock</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
