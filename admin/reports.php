<?php
session_start();
include '../config/database.php';

// Secure authentication check
include 'auth_check.php';

// Get report type and date range
$report_type = $_GET['type'] ?? 'medicines';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Report titles
$report_titles = [
    'medicines' => 'Medicines Inventory Report',
    'orders' => 'Orders & Sales Report',
    'prescriptions' => 'Prescriptions Management Report',
    'customers' => 'Customers Database Report',
    'payments' => 'Payments & Revenue Report',
    'categories' => 'Product Categories Report',
    'staff' => 'Staff Management Report',
    'suppliers' => 'Suppliers & Procurement Report'
];

$page_title = $report_titles[$report_type] ?? 'Report';

try {
    // Load data based on report type
    switch ($report_type) {
        case 'medicines':
            // Medicines summary
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
            $summary = $stmt->fetch();
            
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
            $by_category = $stmt->fetchAll();
            
            // Top stocked medicines
            $stmt = $pdo->query("
                SELECT name, quantity, price, batch_number, manufacturer, expiry_date
                FROM medicines
                WHERE status='active'
                ORDER BY quantity DESC
                LIMIT 10
            ");
            $top_stocked = $stmt->fetchAll();
            
            // Low stock medicines
            $stmt = $pdo->query("
                SELECT name, quantity, price, batch_number, expiry_date
                FROM medicines
                WHERE quantity < 10 AND status='active'
                ORDER BY quantity ASC
                LIMIT 15
            ");
            $low_stock = $stmt->fetchAll();
            
            // Expiring medicines
            $stmt = $pdo->query("
                SELECT name, quantity, price, expiry_date, batch_number
                FROM medicines
                WHERE (expiry_date < CURDATE() OR expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                AND status='active'
                ORDER BY expiry_date ASC
                LIMIT 15
            ");
            $expiring = $stmt->fetchAll();
            
            // Most expensive medicines
            $stmt = $pdo->query("
                SELECT name, price, quantity, manufacturer, batch_number
                FROM medicines
                WHERE status='active'
                ORDER BY price DESC
                LIMIT 10
            ");
            $expensive = $stmt->fetchAll();
            break;
            
        case 'orders':
            // Orders summary
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
            $summary = $stmt->fetch();
            
            // Top ordered medicines
            $stmt = $pdo->prepare("
                SELECT m.name, 
                       SUM(oi.quantity) as total_ordered,
                       SUM(oi.total_price) as total_revenue,
                       COUNT(DISTINCT oi.order_id) as order_count
                FROM order_items oi
                JOIN medicines m ON oi.medicine_id = m.id
                JOIN orders o ON oi.order_id = o.id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY m.id, m.name
                ORDER BY total_ordered DESC
                LIMIT 15
            ");
            $stmt->execute([$start_date, $end_date]);
            $top_products = $stmt->fetchAll();
            
            // Top customers
            $stmt = $pdo->prepare("
                SELECT c.first_name, c.last_name, c.email,
                       COUNT(o.id) as order_count,
                       SUM(o.total_amount) as total_spent
                FROM customers c
                JOIN orders o ON c.id = o.customer_id
                WHERE DATE(o.created_at) BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY total_spent DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $top_customers = $stmt->fetchAll();
            
            // Daily trends
            $stmt = $pdo->prepare("
                SELECT DATE(created_at) as order_date,
                       COUNT(*) as order_count,
                       SUM(total_amount) as daily_revenue
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY DATE(created_at)
                ORDER BY order_date DESC
                LIMIT 30
            ");
            $stmt->execute([$start_date, $end_date]);
            $daily_trends = $stmt->fetchAll();
            
            // Payment methods
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
            $payment_methods = $stmt->fetchAll();
            break;
            
        case 'prescriptions':
            // Prescriptions summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_prescriptions,
                    COUNT(CASE WHEN status='pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN status='approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status='rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status='filled' THEN 1 END) as filled,
                    COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled
                FROM prescriptions
                WHERE DATE(created_at) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $summary = $stmt->fetch();
            
            // Recent prescriptions
            $stmt = $pdo->prepare("
                SELECT p.*, c.first_name, c.last_name, c.email
                FROM prescriptions p
                JOIN customers c ON p.customer_id = c.id
                WHERE DATE(p.created_at) BETWEEN ? AND ?
                ORDER BY p.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$start_date, $end_date]);
            $recent_prescriptions = $stmt->fetchAll();
            
            // Prescriptions by status
            $stmt = $pdo->prepare("
                SELECT status, COUNT(*) as count
                FROM prescriptions
                WHERE DATE(created_at) BETWEEN ? AND ?
                GROUP BY status
                ORDER BY count DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $by_status = $stmt->fetchAll();
            break;
            
        case 'customers':
            // Customers summary
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
            $summary = $stmt->fetch();
            
            // Top customers by orders
            $stmt = $pdo->query("
                SELECT c.id, c.first_name, c.last_name, c.email, c.phone,
                       COUNT(o.id) as order_count,
                       SUM(o.total_amount) as total_spent
                FROM customers c
                LEFT JOIN orders o ON c.id = o.customer_id
                WHERE c.status='active'
                GROUP BY c.id
                HAVING order_count > 0
                ORDER BY total_spent DESC
                LIMIT 15
            ");
            $top_customers = $stmt->fetchAll();
            
            // New customers
            $stmt = $pdo->query("
                SELECT first_name, last_name, email, phone, created_at
                FROM customers
                WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ORDER BY created_at DESC
                LIMIT 15
            ");
            $new_customers = $stmt->fetchAll();
            break;
            
        case 'payments':
            // Payments summary
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
            $summary = $stmt->fetch();
            
            // Daily payment trends
            $stmt = $pdo->prepare("
                SELECT DATE(payment_date) as payment_date,
                       COUNT(*) as payment_count,
                       SUM(amount) as daily_total
                FROM customer_payments
                WHERE DATE(payment_date) BETWEEN ? AND ?
                GROUP BY DATE(payment_date)
                ORDER BY payment_date DESC
                LIMIT 30
            ");
            $stmt->execute([$start_date, $end_date]);
            $daily_payments = $stmt->fetchAll();
            
            // Payment methods breakdown
            $stmt = $pdo->prepare("
                SELECT payment_method, 
                       COUNT(*) as count,
                       SUM(amount) as total_amount
                FROM customer_payments
                WHERE DATE(payment_date) BETWEEN ? AND ?
                GROUP BY payment_method
                ORDER BY total_amount DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $payment_methods = $stmt->fetchAll();
            break;
            
        case 'categories':
            // Categories summary
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_categories,
                    COUNT(CASE WHEN status='active' THEN 1 END) as active_categories,
                    COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_categories
                FROM categories
            ");
            $summary = $stmt->fetch();
            
            // Categories with medicine count
            $stmt = $pdo->query("
                SELECT c.id, c.name, c.description, c.status,
                       COUNT(m.id) as medicine_count,
                       SUM(m.quantity) as total_stock,
                       SUM(m.quantity * m.price) as total_value
                FROM categories c
                LEFT JOIN medicines m ON c.id = m.category_id AND m.status='active'
                GROUP BY c.id
                ORDER BY medicine_count DESC
            ");
            $categories_detail = $stmt->fetchAll();
            break;
            
        case 'staff':
            // Staff summary
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_staff,
                    COUNT(CASE WHEN status='active' THEN 1 END) as active_staff,
                    COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_staff,
                    COUNT(DISTINCT department) as total_departments,
                    COUNT(DISTINCT position) as total_positions,
                    AVG(salary) as avg_salary,
                    MAX(salary) as max_salary,
                    MIN(salary) as min_salary,
                    SUM(salary) as total_payroll
                FROM staff
            ");
            $summary = $stmt->fetch();
            
            // Staff by department
            $stmt = $pdo->query("
                SELECT department, 
                       COUNT(*) as staff_count,
                       AVG(salary) as avg_salary,
                       SUM(salary) as dept_payroll
                FROM staff
                WHERE status='active'
                GROUP BY department
                ORDER BY staff_count DESC
            ");
            $by_department = $stmt->fetchAll();
            
            // Staff by position
            $stmt = $pdo->query("
                SELECT position, 
                       COUNT(*) as count,
                       AVG(salary) as avg_salary
                FROM staff
                WHERE status='active'
                GROUP BY position
                ORDER BY count DESC
            ");
            $by_position = $stmt->fetchAll();
            
            // All staff details
            $stmt = $pdo->query("
                SELECT first_name, last_name, email, phone, department, position, salary, status
                FROM staff
                ORDER BY department, position
            ");
            $all_staff = $stmt->fetchAll();
            break;
            
        case 'suppliers':
            // Suppliers summary
            $stmt = $pdo->query("
                SELECT 
                    COUNT(*) as total_suppliers,
                    COUNT(CASE WHEN status='active' THEN 1 END) as active_suppliers,
                    COUNT(CASE WHEN status='inactive' THEN 1 END) as inactive_suppliers
                FROM suppliers
            ");
            $summary = $stmt->fetch();
            
            // Suppliers with product count
            $stmt = $pdo->query("
                SELECT s.id, s.company_name, s.contact_person, s.email, s.phone, s.status,
                       COUNT(DISTINCT sp.id) as product_count,
                       COUNT(DISTINCT po.id) as order_count
                FROM suppliers s
                LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
                LEFT JOIN purchase_orders po ON s.id = po.supplier_id
                GROUP BY s.id, s.company_name, s.contact_person, s.email, s.phone, s.status
                ORDER BY product_count DESC, s.company_name ASC
            ");
            $suppliers_detail = $stmt->fetchAll();
            
            // Recent purchase orders
            $stmt = $pdo->prepare("
                SELECT po.*, s.company_name as supplier_name
                FROM purchase_orders po
                JOIN suppliers s ON po.supplier_id = s.id
                WHERE DATE(po.order_date) BETWEEN ? AND ?
                ORDER BY po.order_date DESC
                LIMIT 15
            ");
            $stmt->execute([$start_date, $end_date]);
            $recent_orders = $stmt->fetchAll();
            
            // Purchase orders summary
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_value,
                    COUNT(CASE WHEN status='pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN status='approved' THEN 1 END) as approved_orders,
                    COUNT(CASE WHEN status='received' THEN 1 END) as received_orders,
                    COUNT(CASE WHEN status='cancelled' THEN 1 END) as cancelled_orders
                FROM purchase_orders
                WHERE DATE(order_date) BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $po_summary = $stmt->fetch();
            break;
    }
    
} catch (PDOException $e) {
    $error_message = "Error loading report data: " . $e->getMessage();
    error_log("Report Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <!-- Excel Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
        body { 
            background-color: #f8f9fa; 
            padding-top: 20px;
        }
        .admin-content { 
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        .admin-header { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            margin-bottom: 30px; 
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border-left: 4px solid #0d6efd;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .data-table {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        @media print {
            .no-print { display: none !important; }
            .admin-header { display: none !important; }
        }
        
        /* Hide elements during PDF generation */
        .pdf-generating .no-print {
            display: none !important;
        }
        
        /* Simplified PDF mode - only show report header and data tables */
        body.pdf-mode .stat-card {
            display: none !important;
        }
        body.pdf-mode main .row {
            display: none !important;
        }
        body.pdf-mode main .alert {
            display: none !important;
        }
        body.pdf-mode .data-table {
            display: block !important;
            page-break-inside: avoid;
            margin-bottom: 30px;
        }
        body.pdf-mode .data-table .row {
            display: flex !important;
        }
        body.pdf-mode #reportHeader {
            display: block !important;
            margin-bottom: 40px;
            page-break-after: avoid;
        }
        body.pdf-mode .data-table h5 {
            color: #333;
            margin-bottom: 15px;
        }
        body.pdf-mode .table {
            display: table !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-4 admin-content">
                <!-- Header -->
                <div class="admin-header no-print">
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                            <i class="fas fa-arrow-left me-2"></i>Back to <?php echo ucfirst($report_type); ?> Management
                        </button>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1"><?php echo $page_title; ?></h2>
                            <p class="text-muted mb-0">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-danger" onclick="downloadPDF()" id="pdfBtn">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger no-print">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
                <?php endif; ?>

                <!-- Report Header for Print -->
                <div class="text-center mb-4" id="reportHeader">
                    <h3 class="fw-bold">Sahana Medicals Pharmacy</h3>
                    <h5><?php echo $page_title; ?></h5>
                    <p class="text-muted">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                    <p class="text-muted small">Generated: <?php echo date('M d, Y H:i:s'); ?></p>
                </div>

                <?php if ($report_type === 'medicines'): ?>
                    <!-- MEDICINES REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Medicines</small>
                                <div class="stat-number"><?php echo number_format($summary['total_medicines']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Total Stock</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['total_stock']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Inventory Value</small>
                                <div class="stat-number text-info">LKR <?php echo number_format($summary['total_inventory_value'], 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Active Products</small>
                                <div class="stat-number text-warning"><?php echo number_format($summary['active_medicines']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <small class="text-muted">Low Stock</small>
                                <div class="stat-number text-danger"><?php echo number_format($summary['low_stock_count']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <small class="text-muted">Out of Stock</small>
                                <div class="stat-number text-danger"><?php echo number_format($summary['out_of_stock']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Expiring Soon</small>
                                <div class="stat-number text-warning"><?php echo number_format($summary['expiring_soon']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <small class="text-muted">Expired</small>
                                <div class="stat-number text-danger"><?php echo number_format($summary['expired']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Stock by Category -->
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-layer-group me-2"></i>Stock Distribution by Category</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Products</th>
                                        <th class="text-end">Total Stock</th>
                                        <th class="text-end">Value (LKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_category as $cat): ?>
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

                    <!-- Top Stocked Medicines -->
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Top 10 Most Stocked Medicines</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Manufacturer</th>
                                        <th>Batch Number</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total Value</th>
                                        <th>Expiry Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_stocked as $med): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td><?php echo htmlspecialchars($med['manufacturer'] ?? 'N/A'); ?></td>
                                        <td><code><?php echo htmlspecialchars($med['batch_number']); ?></code></td>
                                        <td class="text-end"><strong><?php echo number_format($med['quantity']); ?></strong></td>
                                        <td class="text-end">LKR <?php echo number_format($med['price'], 2); ?></td>
                                        <td class="text-end"><strong>LKR <?php echo number_format($med['quantity'] * $med['price'], 2); ?></strong></td>
                                        <td><?php echo $med['expiry_date'] ? date('M d, Y', strtotime($med['expiry_date'])) : 'N/A'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if (count($low_stock) > 0): ?>
                    <!-- Low Stock Alert -->
                    <div class="data-table">
                        <h5 class="mb-3 text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Low Stock Alert - Requires Immediate Attention</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-warning">
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Batch Number</th>
                                        <th class="text-end">Current Stock</th>
                                        <th class="text-end">Unit Price</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($low_stock as $med): ?>
                                    <tr class="<?php echo $med['quantity'] == 0 ? 'table-danger' : ''; ?>">
                                        <td><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($med['batch_number']); ?></code></td>
                                        <td class="text-end"><strong><?php echo $med['quantity']; ?></strong></td>
                                        <td class="text-end">LKR <?php echo number_format($med['price'], 2); ?></td>
                                        <td><?php echo $med['expiry_date'] ? date('M d, Y', strtotime($med['expiry_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($med['quantity'] == 0): ?>
                                                <span class="badge bg-danger">OUT OF STOCK</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">LOW STOCK</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (count($expiring) > 0): ?>
                    <!-- Expiring Medicines -->
                    <div class="data-table">
                        <h5 class="mb-3 text-warning"><i class="fas fa-clock me-2"></i>Expiration Alert - Medicines Expiring Soon or Expired</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-danger">
                                    <tr>
                                        <th>Medicine Name</th>
                                        <th>Batch Number</th>
                                        <th class="text-end">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expiring as $med): 
                                        $is_expired = strtotime($med['expiry_date']) < time();
                                    ?>
                                    <tr class="<?php echo $is_expired ? 'table-danger' : ''; ?>">
                                        <td><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($med['batch_number']); ?></code></td>
                                        <td class="text-end"><?php echo number_format($med['quantity']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($med['price'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($med['expiry_date'])); ?></td>
                                        <td>
                                            <?php if ($is_expired): ?>
                                                <span class="badge bg-danger">EXPIRED</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">EXPIRING SOON</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Most Expensive Medicines -->
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-dollar-sign me-2"></i>Top 10 Most Expensive Medicines</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Medicine Name</th>
                                        <th>Manufacturer</th>
                                        <th>Batch Number</th>
                                        <th class="text-end">Price (LKR)</th>
                                        <th class="text-end">Stock</th>
                                        <th class="text-end">Total Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($expensive as $med): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($med['name']); ?></td>
                                        <td><?php echo htmlspecialchars($med['manufacturer'] ?? 'N/A'); ?></td>
                                        <td><code><?php echo htmlspecialchars($med['batch_number']); ?></code></td>
                                        <td class="text-end"><strong>LKR <?php echo number_format($med['price'], 2); ?></strong></td>
                                        <td class="text-end"><?php echo number_format($med['quantity']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($med['price'] * $med['quantity'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($report_type === 'orders'): ?>
                    <!-- ORDERS REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Orders</small>
                                <div class="stat-number"><?php echo number_format($summary['total_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Total Revenue</small>
                                <div class="stat-number text-success">LKR <?php echo number_format($summary['total_revenue'], 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Avg Order Value</small>
                                <div class="stat-number text-info">LKR <?php echo number_format($summary['avg_order_value'], 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Delivered</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['delivered_orders']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Pending</small>
                                <div class="stat-number text-warning"><?php echo number_format($summary['pending_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Processing</small>
                                <div class="stat-number text-info"><?php echo number_format($summary['processing_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Paid Orders</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['paid_count']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Pending Payments</small>
                                <div class="stat-number text-warning">LKR <?php echo number_format($summary['pending_revenue'], 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <?php if (!empty($payment_methods)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Payment Methods Distribution</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-end">Order Count</th>
                                        <th class="text-end">Total Amount (LKR)</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_methods as $pm): ?>
                                    <tr>
                                        <td><?php echo ucwords(str_replace('_', ' ', $pm['payment_method'])); ?></td>
                                        <td class="text-end"><?php echo number_format($pm['order_count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($pm['total_amount'], 2); ?></td>
                                        <td class="text-end"><?php echo $summary['total_orders'] > 0 ? round(($pm['order_count'] / $summary['total_orders']) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Top Ordered Medicines -->
                    <?php if (!empty($top_products)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-trophy me-2"></i>Top 15 Most Ordered Medicines</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Medicine Name</th>
                                        <th class="text-end">Total Quantity</th>
                                        <th class="text-end">Total Revenue</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Avg Per Order</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($top_products as $prod): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($prod['name']); ?></td>
                                        <td class="text-end"><strong><?php echo number_format($prod['total_ordered']); ?></strong> units</td>
                                        <td class="text-end"><strong>LKR <?php echo number_format($prod['total_revenue'], 2); ?></strong></td>
                                        <td class="text-end"><?php echo number_format($prod['order_count']); ?></td>
                                        <td class="text-end"><?php echo number_format($prod['total_ordered'] / $prod['order_count'], 1); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Top Customers -->
                    <?php if (!empty($top_customers)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-users me-2"></i>Top 10 Customers by Spending</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th class="text-end">Total Orders</th>
                                        <th class="text-end">Total Spent</th>
                                        <th class="text-end">Avg Order Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($top_customers as $cust): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                        <td class="text-end"><?php echo number_format($cust['order_count']); ?></td>
                                        <td class="text-end"><strong>LKR <?php echo number_format($cust['total_spent'], 2); ?></strong></td>
                                        <td class="text-end">LKR <?php echo number_format($cust['total_spent'] / $cust['order_count'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Daily Trends -->
                    <?php if (!empty($daily_trends)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Daily Order Trend (Last 30 Days)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Revenue</th>
                                        <th class="text-end">Avg Order Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_trends as $day): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y (D)', strtotime($day['order_date'])); ?></td>
                                        <td class="text-end"><?php echo number_format($day['order_count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($day['daily_revenue'], 2); ?></td>
                                        <td class="text-end">LKR <?php echo $day['order_count'] > 0 ? number_format($day['daily_revenue'] / $day['order_count'], 2) : '0.00'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'prescriptions'): ?>
                    <!-- PRESCRIPTIONS REPORT -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="stat-card">
                                <small class="text-muted">Total</small>
                                <div class="stat-number"><?php echo number_format($summary['total_prescriptions']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Pending</small>
                                <div class="stat-number text-warning"><?php echo number_format($summary['pending']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Approved</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['approved']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <small class="text-muted">Rejected</small>
                                <div class="stat-number text-danger"><?php echo number_format($summary['rejected']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Filled</small>
                                <div class="stat-number text-info"><?php echo number_format($summary['filled']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="stat-card" style="border-left-color: #6c757d;">
                                <small class="text-muted">Cancelled</small>
                                <div class="stat-number text-secondary"><?php echo number_format($summary['cancelled']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Distribution -->
                    <?php if (!empty($by_status)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-pie-chart me-2"></i>Prescriptions by Status</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_status as $status): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php 
                                            echo match($status['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'filled' => 'info',
                                                'cancelled' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>"><?php echo ucfirst($status['status']); ?></span></td>
                                        <td class="text-end"><?php echo number_format($status['count']); ?></td>
                                        <td class="text-end"><?php echo $summary['total_prescriptions'] > 0 ? round(($status['count'] / $summary['total_prescriptions']) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Recent Prescriptions -->
                    <?php if (!empty($recent_prescriptions)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-file-medical me-2"></i>Recent Prescriptions (Last 20)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Doctor Name</th>
                                        <th>Prescription Date</th>
                                        <th>Submission Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_prescriptions as $presc): ?>
                                    <tr>
                                        <td>#<?php echo $presc['id']; ?></td>
                                        <td><?php echo htmlspecialchars($presc['first_name'] . ' ' . $presc['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($presc['doctor_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($presc['prescription_date'])); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($presc['created_at'])); ?></td>
                                        <td><span class="badge bg-<?php 
                                            echo match($presc['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'success',
                                                'rejected' => 'danger',
                                                'filled' => 'info',
                                                'cancelled' => 'secondary',
                                                default => 'secondary'
                                            };
                                        ?>"><?php echo ucfirst($presc['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'customers'): ?>
                    <!-- CUSTOMERS REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Customers</small>
                                <div class="stat-number"><?php echo number_format($summary['total_customers']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Active</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['active_customers']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">New This Month</small>
                                <div class="stat-number text-info"><?php echo number_format($summary['new_this_month']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">With Email</small>
                                <div class="stat-number text-warning"><?php echo number_format($summary['with_email']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Customers -->
                    <?php if (!empty($top_customers)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-star me-2"></i>Top 15 Customers by Total Spending</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th class="text-end">Total Orders</th>
                                        <th class="text-end">Total Spent</th>
                                        <th class="text-end">Avg Order Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; foreach ($top_customers as $cust): ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['phone'] ?? 'N/A'); ?></td>
                                        <td class="text-end"><?php echo number_format($cust['order_count']); ?></td>
                                        <td class="text-end"><strong>LKR <?php echo number_format($cust['total_spent'], 2); ?></strong></td>
                                        <td class="text-end">LKR <?php echo number_format($cust['total_spent'] / $cust['order_count'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- New Customers -->
                    <?php if (!empty($new_customers)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-user-plus me-2"></i>New Customers (Last 30 Days)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Joined Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($new_customers as $cust): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cust['first_name'] . ' ' . $cust['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($cust['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'payments'): ?>
                    <!-- PAYMENTS REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Payments</small>
                                <div class="stat-number"><?php echo number_format($summary['total_payments']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Total Amount</small>
                                <div class="stat-number text-success">LKR <?php echo number_format($summary['total_amount'], 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Completed</small>
                                <div class="stat-number text-info">LKR <?php echo number_format($summary['completed_amount'], 2); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Pending</small>
                                <div class="stat-number text-warning">LKR <?php echo number_format($summary['pending_amount'], 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <?php if (!empty($payment_methods)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-credit-card me-2"></i>Payment Methods Breakdown</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment Method</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Total Amount (LKR)</th>
                                        <th class="text-end">Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_methods as $pm): ?>
                                    <tr>
                                        <td><?php echo ucwords(str_replace('_', ' ', $pm['payment_method'])); ?></td>
                                        <td class="text-end"><?php echo number_format($pm['count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($pm['total_amount'], 2); ?></td>
                                        <td class="text-end"><?php echo $summary['total_payments'] > 0 ? round(($pm['count'] / $summary['total_payments']) * 100, 1) : 0; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Daily Trends -->
                    <?php if (!empty($daily_payments)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Daily Payment Trend (Last 30 Days)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Payments</th>
                                        <th class="text-end">Total Amount</th>
                                        <th class="text-end">Avg Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($daily_payments as $day): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y (D)', strtotime($day['payment_date'])); ?></td>
                                        <td class="text-end"><?php echo number_format($day['payment_count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($day['daily_total'], 2); ?></td>
                                        <td class="text-end">LKR <?php echo $day['payment_count'] > 0 ? number_format($day['daily_total'] / $day['payment_count'], 2) : '0.00'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'categories'): ?>
                    <!-- CATEGORIES REPORT -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <small class="text-muted">Total Categories</small>
                                <div class="stat-number"><?php echo number_format($summary['total_categories']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Active</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['active_categories']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card" style="border-left-color: #6c757d;">
                                <small class="text-muted">Inactive</small>
                                <div class="stat-number text-secondary"><?php echo number_format($summary['inactive_categories']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Categories Detail -->
                    <?php if (!empty($categories_detail)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-layer-group me-2"></i>Categories Overview</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Category Name</th>
                                        <th>Description</th>
                                        <th class="text-end">Products</th>
                                        <th class="text-end">Total Stock</th>
                                        <th class="text-end">Total Value (LKR)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories_detail as $cat): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($cat['description'] ?? 'N/A'); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['medicine_count']); ?></td>
                                        <td class="text-end"><?php echo number_format($cat['total_stock'] ?? 0); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($cat['total_value'] ?? 0, 2); ?></td>
                                        <td><span class="badge bg-<?php echo $cat['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($cat['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'staff'): ?>
                    <!-- STAFF REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Staff</small>
                                <div class="stat-number"><?php echo number_format($summary['total_staff']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Active</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['active_staff']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Departments</small>
                                <div class="stat-number text-info"><?php echo number_format($summary['total_departments']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Total Payroll</small>
                                <div class="stat-number text-warning">LKR <?php echo number_format($summary['total_payroll'], 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Staff by Department -->
                    <?php if (!empty($by_department)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-building me-2"></i>Staff Distribution by Department</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Department</th>
                                        <th class="text-end">Staff Count</th>
                                        <th class="text-end">Avg Salary (LKR)</th>
                                        <th class="text-end">Dept Payroll (LKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_department as $dept): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                        <td class="text-end"><?php echo number_format($dept['staff_count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($dept['avg_salary'], 2); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($dept['dept_payroll'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Staff by Position -->
                    <?php if (!empty($by_position)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-user-tag me-2"></i>Staff Distribution by Position</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Position</th>
                                        <th class="text-end">Count</th>
                                        <th class="text-end">Avg Salary (LKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($by_position as $pos): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pos['position']); ?></td>
                                        <td class="text-end"><?php echo number_format($pos['count']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($pos['avg_salary'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- All Staff -->
                    <?php if (!empty($all_staff)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-users me-2"></i>Complete Staff Directory</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Department</th>
                                        <th>Position</th>
                                        <th class="text-end">Salary</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_staff as $staff): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                        <td><?php echo htmlspecialchars($staff['position']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($staff['salary'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $staff['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($staff['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($report_type === 'suppliers'): ?>
                    <!-- SUPPLIERS REPORT -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <small class="text-muted">Total Suppliers</small>
                                <div class="stat-number"><?php echo number_format($summary['total_suppliers']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Active Suppliers</small>
                                <div class="stat-number text-success"><?php echo number_format($summary['active_suppliers']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Purchase Orders</small>
                                <div class="stat-number text-info"><?php echo number_format($po_summary['total_orders'] ?? 0); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Total Procurement</small>
                                <div class="stat-number text-warning">LKR <?php echo number_format($po_summary['total_value'] ?? 0, 2); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Purchase Orders Summary -->
                    <?php if (($po_summary['total_orders'] ?? 0) > 0): ?>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #ffc107;">
                                <small class="text-muted">Pending Orders</small>
                                <div class="stat-number text-warning"><?php echo number_format($po_summary['pending_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #17a2b8;">
                                <small class="text-muted">Approved Orders</small>
                                <div class="stat-number text-info"><?php echo number_format($po_summary['approved_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #28a745;">
                                <small class="text-muted">Received Orders</small>
                                <div class="stat-number text-success"><?php echo number_format($po_summary['received_orders']); ?></div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card" style="border-left-color: #dc3545;">
                                <small class="text-muted">Cancelled Orders</small>
                                <div class="stat-number text-danger"><?php echo number_format($po_summary['cancelled_orders']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Suppliers Detail -->
                    <?php if (!empty($suppliers_detail)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-truck me-2"></i>Suppliers Directory</h5>
                        <p><strong>Total Suppliers: <?php echo count($suppliers_detail); ?></strong></p>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supplier Name</th>
                                        <th>Contact Person</th>
                                        <th>Phone</th>
                                        <th>Email</th>
                                        <th class="text-end">Products</th>
                                        <th class="text-end">Purchase Orders</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers_detail as $supplier): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($supplier['contact_person'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['phone'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['email'] ?? 'N/A'); ?></td>
                                        <td class="text-end"><?php echo number_format($supplier['product_count']); ?></td>
                                        <td class="text-end"><?php echo number_format($supplier['order_count']); ?></td>
                                        <td><span class="badge bg-<?php echo $supplier['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($supplier['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No suppliers found in the system.
                    </div>
                    <?php endif; ?>

                    <!-- Recent Purchase Orders -->
                    <?php if (!empty($recent_orders)): ?>
                    <div class="data-table">
                        <h5 class="mb-3"><i class="fas fa-shopping-bag me-2"></i>Recent Purchase Orders (Last 15)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Order Date</th>
                                        <th>Expected Delivery</th>
                                        <th class="text-end">Total Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($order['order_number']); ?></code></td>
                                        <td><?php echo htmlspecialchars($order['supplier_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo $order['expected_delivery'] ? date('M d, Y', strtotime($order['expected_delivery'])) : 'N/A'; ?></td>
                                        <td class="text-end">LKR <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="badge bg-<?php 
                                            echo match($order['status']) {
                                                'pending' => 'warning',
                                                'approved' => 'info',
                                                'received' => 'success',
                                                'cancelled' => 'danger',
                                                default => 'secondary'
                                            };
                                        ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function downloadPDF() {
            const button = document.getElementById('pdfBtn');
            const originalHTML = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
            button.disabled = true;
            
            // Hide the header section and stat cards during PDF generation
            const header = document.querySelector('.admin-header');
            if (header) {
                header.style.display = 'none';
            }
            
            // Add pdf-mode class to hide stat cards and show only tables
            document.body.classList.add('pdf-mode');
            
            const element = document.querySelector('.admin-content');
            
            const opt = {
                margin: 10,
                filename: '<?php echo str_replace(' ', '_', $page_title); ?>_<?php echo date('Y-m-d'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    letterRendering: true
                },
                jsPDF: { 
                    unit: 'mm', 
                    format: 'a4', 
                    orientation: 'portrait' 
                }
            };
            
            html2pdf().set(opt).from(element).save().then(() => {
                // Restore visibility
                document.body.classList.remove('pdf-mode');
                if (header) {
                    header.style.display = '';
                }
                button.innerHTML = originalHTML;
                button.disabled = false;
            }).catch((error) => {
                console.error('PDF generation error:', error);
                alert('Error generating PDF. Please try again.');
                // Restore visibility
                document.body.classList.remove('pdf-mode');
                if (header) {
                    header.style.display = '';
                }
                button.innerHTML = originalHTML;
                button.disabled = false;
            });
        }
        
        function exportToExcel() {
            const tables = document.querySelectorAll('.data-table table');
            if (tables.length === 0) {
                alert('No data available to export');
                return;
            }
            
            const wb = XLSX.utils.book_new();
            
            tables.forEach((table, index) => {
                const ws = XLSX.utils.table_to_sheet(table);
                const sheetName = `Data_${index + 1}`;
                XLSX.utils.book_append_sheet(wb, ws, sheetName);
            });
            
            XLSX.writeFile(wb, '<?php echo str_replace(' ', '_', $page_title); ?>_<?php echo date('Y-m-d'); ?>.xlsx');
        }
    </script>
</body>
</html>
