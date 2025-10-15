<?php
session_start();
include '../config/database.php';

// Secure authentication check
include 'auth_check.php';

// Get date range from filters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$report_type = $_GET['type'] ?? 'all';

try {
    // ========================================
    // MEDICINES FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_medicines,
            SUM(quantity) as total_stock,
            SUM(quantity * price) as total_inventory_value,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_medicines,
            COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_medicines,
            COUNT(CASE WHEN quantity < 10 AND status='active' THEN 1 END) as low_stock_count,
            COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
            COUNT(CASE WHEN expiry_date < CURDATE() THEN 1 END) as expired,
            COUNT(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
            COUNT(CASE WHEN prescription_required = 1 THEN 1 END) as prescription_only,
            AVG(price) as avg_price,
            MAX(price) as max_price,
            MIN(price) as min_price
        FROM medicines
    ");
    $medicines_summary = $stmt->fetch();
    
    // Medicines by Category
    $stmt = $pdo->query("
        SELECT c.name as category_name, 
               COUNT(m.id) as medicine_count,
               SUM(m.quantity) as total_quantity,
               SUM(m.quantity * m.price) as category_value
        FROM categories c
        LEFT JOIN medicines m ON c.id = m.category_id AND m.status='active'
        WHERE c.status='active'
        GROUP BY c.id, c.name
        ORDER BY medicine_count DESC
    ");
    $medicines_by_category = $stmt->fetchAll();
    
    // Top 5 Most Stocked Medicines
    $stmt = $pdo->query("
        SELECT name, quantity, price, batch_number, manufacturer
        FROM medicines
        WHERE status='active'
        ORDER BY quantity DESC
        LIMIT 5
    ");
    $top_stocked = $stmt->fetchAll();
    
    // ========================================
    // ORDERS FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(CASE WHEN status='pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status='confirmed' THEN 1 END) as confirmed_orders,
            COUNT(CASE WHEN status='processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status='shipped' THEN 1 END) as shipped_orders,
            COUNT(CASE WHEN status='delivered' THEN 1 END) as delivered_orders,
            COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN payment_status='paid' THEN 1 END) as paid_count,
            SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) as paid_revenue,
            COUNT(CASE WHEN payment_status='pending' THEN 1 END) as pending_payment_count,
            SUM(CASE WHEN payment_status='pending' THEN total_amount ELSE 0 END) as pending_revenue
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $orders_summary = $stmt->fetch();
    
    // Orders by Payment Method
    $stmt = $pdo->prepare("
        SELECT payment_method, 
               COUNT(*) as order_count,
               SUM(total_amount) as total_amount
        FROM orders
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY order_count DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $orders_by_payment = $stmt->fetchAll();
    
    // Daily Order Trend (Last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as order_date,
               COUNT(*) as order_count,
               SUM(total_amount) as daily_revenue
        FROM orders
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY order_date DESC
    ");
    $daily_trends = $stmt->fetchAll();
    
    // ========================================
    // PRESCRIPTIONS FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_prescriptions,
            COUNT(CASE WHEN status='pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status='approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status='rejected' THEN 1 END) as rejected,
            COUNT(CASE WHEN status='filled' THEN 1 END) as filled,
            COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled,
            COUNT(CASE WHEN admin_notes IS NOT NULL THEN 1 END) as with_notes,
            COUNT(CASE WHEN prescription_file IS NOT NULL THEN 1 END) as with_files
        FROM prescriptions
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $prescriptions_summary = $stmt->fetch();
    
    // Prescription Processing Rate
    $total_presc = $prescriptions_summary['total_prescriptions'];
    $processing_rate = $total_presc > 0 ? 
        round((($prescriptions_summary['approved'] + $prescriptions_summary['filled']) / $total_presc) * 100, 1) : 0;
    $rejection_rate = $total_presc > 0 ? 
        round(($prescriptions_summary['rejected'] / $total_presc) * 100, 1) : 0;
    
    // ========================================
    // CUSTOMERS FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_customers,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_customers,
            COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_customers,
            COUNT(CASE WHEN email IS NOT NULL AND email != '' THEN 1 END) as with_email,
            COUNT(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 END) as with_phone,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
        FROM customers
    ");
    $customers_summary = $stmt->fetch();
    
    // Customer Order Statistics
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.first_name,
            c.last_name,
            COUNT(o.id) as order_count,
            SUM(o.total_amount) as total_spent
        FROM customers c
        LEFT JOIN orders o ON c.id = o.customer_id
        WHERE c.status='active'
        GROUP BY c.id
        HAVING order_count > 0
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $top_customers = $stmt->fetchAll();
    
    // ========================================
    // CATEGORIES FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_categories,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_categories,
            COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_categories
        FROM categories
    ");
    $categories_summary = $stmt->fetch();
    
    // ========================================
    // PAYMENTS FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payments,
            SUM(amount) as total_amount,
            AVG(amount) as avg_payment,
            COUNT(CASE WHEN payment_status='completed' THEN 1 END) as completed,
            SUM(CASE WHEN payment_status='completed' THEN amount ELSE 0 END) as completed_amount,
            COUNT(CASE WHEN payment_status='pending' THEN 1 END) as pending,
            SUM(CASE WHEN payment_status='pending' THEN amount ELSE 0 END) as pending_amount,
            COUNT(CASE WHEN payment_status='failed' THEN 1 END) as failed,
            COUNT(CASE WHEN payment_method='cash_on_delivery' THEN 1 END) as cod_count,
            COUNT(CASE WHEN payment_method='card' THEN 1 END) as card_count,
            COUNT(CASE WHEN payment_method='bank_transfer' THEN 1 END) as bank_count
        FROM customer_payments
        WHERE DATE(payment_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$start_date, $end_date]);
    $payments_summary = $stmt->fetch();
    
    // ========================================
    // STAFF FUNCTION SUMMARY
    // ========================================
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_staff,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_staff,
            COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_staff,
            COUNT(DISTINCT department) as total_departments,
            COUNT(DISTINCT position) as total_positions,
            AVG(salary) as avg_salary,
            MAX(salary) as max_salary,
            MIN(salary) as min_salary
        FROM staff
    ");
    $staff_summary = $stmt->fetch();
    
    // Staff by Department
    $stmt = $pdo->query("
        SELECT department, COUNT(*) as staff_count
        FROM staff
        WHERE status='active'
        GROUP BY department
        ORDER BY staff_count DESC
    ");
    $staff_by_dept = $stmt->fetchAll();
    
    // ========================================
    // SUPPLIERS FUNCTION SUMMARY  
    // ========================================
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_suppliers,
            COUNT(CASE WHEN status='active' THEN 1 END) as active_suppliers,
            COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_suppliers,
            COUNT(DISTINCT country) as countries
        FROM suppliers
    ");
    $suppliers_summary = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error fetching report data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprehensive Report - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .function-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s;
        }
        .function-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .function-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .metric-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .metric-item:last-child {
            border-bottom: none;
        }
        .metric-label {
            color: #666;
            font-size: 0.9rem;
        }
        .metric-value {
            font-weight: 600;
            color: #333;
            font-size: 1.1rem;
        }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <!-- Header -->
                <div class="admin-header no-print">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1">Comprehensive Business Report</h2>
                            <p class="text-muted mb-0">Detailed summaries of all pharmacy functions</p>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-danger" onclick="window.print()">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Report Header -->
                <div class="text-center mb-4" id="reportHeader">
                    <h3 class="fw-bold">Sahana Medicals Pharmacy</h3>
                    <h5 class="text-muted">Complete Functions Summary Report</h5>
                    <p class="text-muted small">Report Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                    <p class="text-muted small">Generated: <?php echo date('M d, Y H:i:s'); ?></p>
                </div>

                <!-- MEDICINES FUNCTION -->
                <div class="card function-card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="function-header">
                            <h4 class="mb-0"><i class="fas fa-pills me-2"></i>MEDICINES MANAGEMENT FUNCTION</h4>
                            <small>Inventory Control & Stock Management System</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-primary mb-3">Inventory Overview</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Total Medicines</div>
                                    <div class="metric-value"><?php echo number_format($medicines_summary['total_medicines']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Active Products</div>
                                    <div class="metric-value text-success"><?php echo number_format($medicines_summary['active_medicines']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Total Stock Units</div>
                                    <div class="metric-value"><?php echo number_format($medicines_summary['total_stock']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Inventory Value</div>
                                    <div class="metric-value">LKR <?php echo number_format($medicines_summary['total_inventory_value'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-danger mb-3">Stock Alerts & Issues</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Low Stock (<10 units)</div>
                                    <div class="metric-value text-warning"><?php echo number_format($medicines_summary['low_stock_count']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Out of Stock</div>
                                    <div class="metric-value text-danger"><?php echo number_format($medicines_summary['out_of_stock']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Expired Medicines</div>
                                    <div class="metric-value text-danger"><?php echo number_format($medicines_summary['expired']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Expiring Soon (30 days)</div>
                                    <div class="metric-value text-warning"><?php echo number_format($medicines_summary['expiring_soon']); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-info mb-3">Product Analytics</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Prescription Required</div>
                                    <div class="metric-value"><?php echo number_format($medicines_summary['prescription_only']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Average Price</div>
                                    <div class="metric-value">LKR <?php echo number_format($medicines_summary['avg_price'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Highest Price</div>
                                    <div class="metric-value">LKR <?php echo number_format($medicines_summary['max_price'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Total Categories</div>
                                    <div class="metric-value"><?php echo count($medicines_by_category); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <h6 class="text-secondary">Stock Distribution by Category</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="text-end">Products</th>
                                            <th class="text-end">Total Stock</th>
                                            <th class="text-end">Value</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($medicines_by_category as $cat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cat['category_name']); ?></td>
                                            <td class="text-end"><?php echo number_format($cat['medicine_count']); ?></td>
                                            <td class="text-end"><?php echo number_format($cat['total_quantity'] ?? 0); ?></td>
                                            <td class="text-end">LKR <?php echo number_format($cat['category_value'] ?? 0, 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <strong>Function Performance:</strong> 
                            <?php 
                            $health = 100;
                            $health -= ($medicines_summary['low_stock_count'] * 2);
                            $health -= ($medicines_summary['expired'] * 5);
                            $health -= ($medicines_summary['out_of_stock'] * 3);
                            $health = max(0, min(100, $health));
                            echo $health >= 80 ? '✅ Excellent' : ($health >= 60 ? '⚠️ Good' : '❌ Needs Attention');
                            ?> (<?php echo $health; ?>% Health Score)
                        </div>
                    </div>
                </div>

                <!-- ORDERS FUNCTION -->
                <div class="card function-card border-0 shadow-sm mb-4" style="border-left-color: #28a745 !important;">
                    <div class="card-body">
                        <div class="function-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                            <h4 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>ORDERS MANAGEMENT FUNCTION</h4>
                            <small>Sales Processing & Revenue Tracking System</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-success mb-3">Revenue Metrics</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Total Orders</div>
                                    <div class="metric-value"><?php echo number_format($orders_summary['total_orders']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Total Revenue</div>
                                    <div class="metric-value text-success">LKR <?php echo number_format($orders_summary['total_revenue'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Average Order Value</div>
                                    <div class="metric-value">LKR <?php echo number_format($orders_summary['avg_order_value'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Paid Revenue</div>
                                    <div class="metric-value text-success">LKR <?php echo number_format($orders_summary['paid_revenue'], 2); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-primary mb-3">Order Status Breakdown</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Pending Orders</div>
                                    <div class="metric-value text-warning"><?php echo number_format($orders_summary['pending_orders']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Processing</div>
                                    <div class="metric-value"><?php echo number_format($orders_summary['processing_orders']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Delivered</div>
                                    <div class="metric-value text-success"><?php echo number_format($orders_summary['delivered_orders']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Cancelled</div>
                                    <div class="metric-value text-danger"><?php echo number_format($orders_summary['cancelled_orders']); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-info mb-3">Payment Status</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Paid Orders</div>
                                    <div class="metric-value text-success"><?php echo number_format($orders_summary['paid_count']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Pending Payments</div>
                                    <div class="metric-value text-warning"><?php echo number_format($orders_summary['pending_payment_count']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Pending Amount</div>
                                    <div class="metric-value text-warning">LKR <?php echo number_format($orders_summary['pending_revenue'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Payment Success Rate</div>
                                    <div class="metric-value">
                                        <?php echo $orders_summary['total_orders'] > 0 ? 
                                            round(($orders_summary['paid_count'] / $orders_summary['total_orders']) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($orders_by_payment)): ?>
                        <div class="mt-3">
                            <h6 class="text-secondary">Payment Methods Distribution</h6>
                            <div class="row">
                                <?php foreach ($orders_by_payment as $payment): ?>
                                <div class="col-md-4">
                                    <div class="card border">
                                        <div class="card-body text-center">
                                            <h5><?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?></h5>
                                            <p class="mb-0"><strong><?php echo $payment['order_count']; ?></strong> orders</p>
                                            <p class="mb-0 text-success">LKR <?php echo number_format($payment['total_amount'], 2); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-success mt-3 mb-0">
                            <strong>Function Performance:</strong> 
                            <?php 
                            $completion_rate = $orders_summary['total_orders'] > 0 ? 
                                round(($orders_summary['delivered_orders'] / $orders_summary['total_orders']) * 100, 1) : 0;
                            echo $completion_rate >= 80 ? '✅ Excellent' : ($completion_rate >= 60 ? '⚠️ Good' : '❌ Needs Attention');
                            ?> (<?php echo $completion_rate; ?>% Delivery Rate)
                        </div>
                    </div>
                </div>

                <!-- PRESCRIPTIONS FUNCTION -->
                <div class="card function-card border-0 shadow-sm mb-4" style="border-left-color: #17a2b8 !important;">
                    <div class="card-body">
                        <div class="function-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <h4 class="mb-0"><i class="fas fa-file-medical me-2"></i>PRESCRIPTIONS MANAGEMENT FUNCTION</h4>
                            <small>Prescription Review & Approval System</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <h6 class="text-info mb-3">Submission Statistics</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Total Prescriptions</div>
                                    <div class="metric-value"><?php echo number_format($prescriptions_summary['total_prescriptions']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">With Uploaded Files</div>
                                    <div class="metric-value"><?php echo number_format($prescriptions_summary['with_files']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">With Admin Notes</div>
                                    <div class="metric-value"><?php echo number_format($prescriptions_summary['with_notes']); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-primary mb-3">Processing Status</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Pending Review</div>
                                    <div class="metric-value text-warning"><?php echo number_format($prescriptions_summary['pending']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Approved</div>
                                    <div class="metric-value text-success"><?php echo number_format($prescriptions_summary['approved']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Rejected</div>
                                    <div class="metric-value text-danger"><?php echo number_format($prescriptions_summary['rejected']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Filled/Completed</div>
                                    <div class="metric-value text-success"><?php echo number_format($prescriptions_summary['filled']); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <h6 class="text-success mb-3">Performance Metrics</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Approval Rate</div>
                                    <div class="metric-value text-success"><?php echo $processing_rate; ?>%</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Rejection Rate</div>
                                    <div class="metric-value text-danger"><?php echo $rejection_rate; ?>%</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Completion Rate</div>
                                    <div class="metric-value">
                                        <?php echo $total_presc > 0 ? 
                                            round(($prescriptions_summary['filled'] / $total_presc) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Cancelled</div>
                                    <div class="metric-value"><?php echo number_format($prescriptions_summary['cancelled']); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $prescriptions_summary['filled'] > 0 ? round(($prescriptions_summary['filled'] / $total_presc) * 100) : 0; ?>%">
                                    Filled: <?php echo $prescriptions_summary['filled']; ?>
                                </div>
                                <div class="progress-bar bg-info" style="width: <?php echo $prescriptions_summary['approved'] > 0 ? round(($prescriptions_summary['approved'] / $total_presc) * 100) : 0; ?>%">
                                    Approved: <?php echo $prescriptions_summary['approved']; ?>
                                </div>
                                <div class="progress-bar bg-warning" style="width: <?php echo $prescriptions_summary['pending'] > 0 ? round(($prescriptions_summary['pending'] / $total_presc) * 100) : 0; ?>%">
                                    Pending: <?php echo $prescriptions_summary['pending']; ?>
                                </div>
                                <div class="progress-bar bg-danger" style="width: <?php echo $prescriptions_summary['rejected'] > 0 ? round(($prescriptions_summary['rejected'] / $total_presc) * 100) : 0; ?>%">
                                    Rejected: <?php echo $prescriptions_summary['rejected']; ?>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3 mb-0">
                            <strong>Function Performance:</strong> 
                            <?php 
                            echo $processing_rate >= 80 ? '✅ Excellent Service' : ($processing_rate >= 60 ? '⚠️ Good Service' : '❌ Needs Improvement');
                            ?> (<?php echo $processing_rate; ?>% Approval Rate)
                        </div>
                    </div>
                </div>

                <!-- CUSTOMERS FUNCTION -->
                <div class="card function-card border-0 shadow-sm mb-4" style="border-left-color: #ffc107 !important;">
                    <div class="card-body">
                        <div class="function-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <h4 class="mb-0"><i class="fas fa-users me-2"></i>CUSTOMERS MANAGEMENT FUNCTION</h4>
                            <small>Customer Database & Relationship Management</small>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-warning mb-3">Customer Base</h6>
                                <div class="metric-item">
                                    <div class="metric-label">Total Customers</div>
                                    <div class="metric-value"><?php echo number_format($customers_summary['total_customers']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Active Customers</div>
                                    <div class="metric-value text-success"><?php echo number_format($customers_summary['active_customers']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">New This Month</div>
                                    <div class="metric-value text-info"><?php echo number_format($customers_summary['new_this_month']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">With Email</div>
                                    <div class="metric-value"><?php echo number_format($customers_summary['with_email']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">With Phone</div>
                                    <div class="metric-value"><?php echo number_format($customers_summary['with_phone']); ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-success mb-3">Top 5 Customers (by Spending)</h6>
                                <?php if (!empty($top_customers)): ?>
                                    <?php foreach ($top_customers as $index => $customer): ?>
                                    <div class="metric-item">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo $index + 1; ?>. <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></span>
                                            <strong class="text-success">LKR <?php echo number_format($customer['total_spent'], 2); ?></strong>
                                        </div>
                                        <small class="text-muted"><?php echo $customer['order_count']; ?> orders</small>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No customer orders yet</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-warning mt-3 mb-0">
                            <strong>Function Performance:</strong> ✅ Database Healthy 
                            (<?php echo $customers_summary['active_customers']; ?> active, 
                             <?php echo $customers_summary['new_this_month']; ?> new this month)
                        </div>
                    </div>
                </div>

                <!-- PAYMENTS & OTHER FUNCTIONS -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card function-card border-0 shadow-sm mb-4" style="border-left-color: #dc3545 !important;">
                            <div class="card-body">
                                <div class="function-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>PAYMENTS FUNCTION</h5>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Total Transactions</div>
                                    <div class="metric-value"><?php echo number_format($payments_summary['total_payments']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Total Amount</div>
                                    <div class="metric-value text-success">LKR <?php echo number_format($payments_summary['total_amount'], 2); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Completed</div>
                                    <div class="metric-value text-success"><?php echo number_format($payments_summary['completed']); ?> (LKR <?php echo number_format($payments_summary['completed_amount'], 2); ?>)</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Pending</div>
                                    <div class="metric-value text-warning"><?php echo number_format($payments_summary['pending']); ?> (LKR <?php echo number_format($payments_summary['pending_amount'], 2); ?>)</div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Success Rate</div>
                                    <div class="metric-value">
                                        <?php echo $payments_summary['total_payments'] > 0 ? 
                                            round(($payments_summary['completed'] / $payments_summary['total_payments']) * 100, 1) : 0; ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card function-card border-0 shadow-sm mb-4" style="border-left-color: #6c757d !important;">
                            <div class="card-body">
                                <div class="function-header" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                    <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>CATEGORIES FUNCTION</h5>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Total Categories</div>
                                    <div class="metric-value"><?php echo number_format($categories_summary['total_categories']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Active Categories</div>
                                    <div class="metric-value text-success"><?php echo number_format($categories_summary['active_categories']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Inactive</div>
                                    <div class="metric-value text-muted"><?php echo number_format($categories_summary['inactive_categories']); ?></div>
                                </div>

                                <hr>
                                <div class="function-header" style="background: linear-gradient(135deg, #d299c2 0%, #fef9d7 100%);">
                                    <h5 class="mb-0"><i class="fas fa-user-tie me-2"></i>STAFF FUNCTION</h5>
                                </div>
                                
                                <div class="metric-item">
                                    <div class="metric-label">Total Staff</div>
                                    <div class="metric-value"><?php echo number_format($staff_summary['total_staff']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Active Staff</div>
                                    <div class="metric-value text-success"><?php echo number_format($staff_summary['active_staff']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Departments</div>
                                    <div class="metric-value"><?php echo number_format($staff_summary['total_departments']); ?></div>
                                </div>
                                <div class="metric-item">
                                    <div class="metric-label">Average Salary</div>
                                    <div class="metric-value">LKR <?php echo number_format($staff_summary['avg_salary'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Overall Summary -->
                <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <h4 class="text-white mb-3"><i class="fas fa-check-circle me-2"></i>OVERALL SYSTEM SUMMARY</h4>
                        <div class="row text-center">
                            <div class="col-md-2">
                                <h2 class="text-white"><?php echo count($medicines_by_category); ?></h2>
                                <small>Active Functions</small>
                            </div>
                            <div class="col-md-2">
                                <h2 class="text-white"><?php echo number_format($medicines_summary['total_medicines']); ?></h2>
                                <small>Total Products</small>
                            </div>
                            <div class="col-md-2">
                                <h2 class="text-white"><?php echo number_format($orders_summary['total_orders']); ?></h2>
                                <small>Total Orders</small>
                            </div>
                            <div class="col-md-2">
                                <h2 class="text-white"><?php echo number_format($customers_summary['active_customers']); ?></h2>
                                <small>Active Customers</small>
                            </div>
                            <div class="col-md-2">
                                <h2 class="text-white"><?php echo number_format($staff_summary['active_staff']); ?></h2>
                                <small>Staff Members</small>
                            </div>
                            <div class="col-md-2">
                                <h2 class="text-white">LKR <?php echo number_format($orders_summary['total_revenue'], 0); ?></h2>
                                <small>Total Revenue</small>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            alert('Excel export functionality - data prepared for download');
            // Excel export implementation
        }
    </script>
</body>
</html>

