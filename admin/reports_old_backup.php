<?php
session_start();
include '../config/database.php';

// Secure authentication check
include 'auth_check.php';

// Get date range from filters
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Today
$report_type = $_GET['report_type'] ?? 'summary';

// Get summary statistics
try {
    // Total Medicines
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(quantity) as total_quantity, 
                         SUM(quantity * price) as total_value FROM medicines WHERE status='active'");
    $medicines_stats = $stmt->fetch();
    
    // Total Customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE status='active'");
    $customers_stats = $stmt->fetch();
    
    // Orders Statistics (filtered by date)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders, 
                           SUM(total_amount) as total_revenue,
                           SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) as paid_revenue,
                           SUM(CASE WHEN payment_status='pending' THEN total_amount ELSE 0 END) as pending_revenue
                           FROM orders 
                           WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $orders_stats = $stmt->fetch();
    
    // Prescriptions Statistics (filtered by date)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total,
                           SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
                           SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
                           SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
                           SUM(CASE WHEN status='filled' THEN 1 ELSE 0 END) as filled
                           FROM prescriptions 
                           WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $prescriptions_stats = $stmt->fetch();
    
    // Low Stock Medicines
    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM medicines WHERE quantity < 10 AND status='active'");
    $low_stock = $stmt->fetch()['low_stock'];
    
    // Expired/Expiring Soon Medicines
    $stmt = $pdo->query("SELECT COUNT(*) as expired FROM medicines 
                         WHERE expiry_date < CURDATE() AND status='active'");
    $expired = $stmt->fetch()['expired'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as expiring_soon FROM medicines 
                         WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status='active'");
    $expiring_soon = $stmt->fetch()['expiring_soon'];
    
    // Top Selling Medicines (from order_items)
    $stmt = $pdo->prepare("SELECT m.name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
                           FROM order_items oi
                           JOIN medicines m ON oi.medicine_id = m.id
                           JOIN orders o ON oi.order_id = o.id
                           WHERE DATE(o.created_at) BETWEEN ? AND ?
                           GROUP BY m.id, m.name
                           ORDER BY total_sold DESC
                           LIMIT 10");
    $stmt->execute([$start_date, $end_date]);
    $top_medicines = $stmt->fetchAll();
    
    // Recent Orders
    $stmt = $pdo->prepare("SELECT o.*, c.first_name, c.last_name
                           FROM orders o
                           LEFT JOIN customers c ON o.customer_id = c.id
                           WHERE DATE(o.created_at) BETWEEN ? AND ?
                           ORDER BY o.created_at DESC
                           LIMIT 20");
    $stmt->execute([$start_date, $end_date]);
    $recent_orders = $stmt->fetchAll();
    
    // Categories Summary
    $stmt = $pdo->query("SELECT c.name, COUNT(m.id) as medicine_count, SUM(m.quantity) as total_quantity
                         FROM categories c
                         LEFT JOIN medicines m ON c.id = m.category_id AND m.status='active'
                         WHERE c.status='active'
                         GROUP BY c.id, c.name
                         ORDER BY medicine_count DESC");
    $categories_summary = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Error fetching report data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .stat-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .stat-card {
                break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <!-- Header -->
                <div class="admin-header no-print">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1">Reports & Analytics</h2>
                            <p class="text-muted mb-0">Generate and export pharmacy reports</p>
                        </div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-danger" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf me-2"></i>Export as PDF
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportToExcel()">
                                <i class="fas fa-file-excel me-2"></i>Export as Excel
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card border-0 shadow-sm mb-4 no-print">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary d-block w-100">
                                    <i class="fas fa-filter me-2"></i>Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Report Header -->
                <div class="text-center mb-4" id="reportHeader">
                    <h3 class="fw-bold">Sahana Medicals Pharmacy</h3>
                    <p class="text-muted mb-1">Comprehensive Report Summary</p>
                    <p class="text-muted small">Report Period: <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></p>
                    <p class="text-muted small">Generated on: <?php echo date('M d, Y H:i:s'); ?></p>
                </div>

                <!-- Key Statistics -->
                <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Key Performance Indicators</h5>
                <div class="row mb-4" id="kpiSection">
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Total Revenue</p>
                                        <h4 class="stat-value mb-0">LKR <?php echo number_format($orders_stats['total_revenue'] ?? 0, 2); ?></h4>
                                    </div>
                                    <div class="text-primary">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Total Orders</p>
                                        <h4 class="stat-value mb-0"><?php echo number_format($orders_stats['total_orders'] ?? 0); ?></h4>
                                    </div>
                                    <div class="text-success">
                                        <i class="fas fa-shopping-cart fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Total Medicines</p>
                                        <h4 class="stat-value mb-0"><?php echo number_format($medicines_stats['total'] ?? 0); ?></h4>
                                    </div>
                                    <div class="text-info">
                                        <i class="fas fa-pills fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="text-muted mb-1 small">Active Customers</p>
                                        <h4 class="stat-value mb-0"><?php echo number_format($customers_stats['total'] ?? 0); ?></h4>
                                    </div>
                                    <div class="text-warning">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Revenue Breakdown -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Revenue Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Paid Revenue:</span>
                                    <strong class="text-success">LKR <?php echo number_format($orders_stats['paid_revenue'] ?? 0, 2); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pending Revenue:</span>
                                    <strong class="text-warning">LKR <?php echo number_format($orders_stats['pending_revenue'] ?? 0, 2); ?></strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total Revenue:</strong>
                                    <strong class="text-primary">LKR <?php echo number_format($orders_stats['total_revenue'] ?? 0, 2); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-prescription me-2"></i>Prescription Statistics</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Pending:</span>
                                    <strong class="text-warning"><?php echo number_format($prescriptions_stats['pending'] ?? 0); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Approved:</span>
                                    <strong class="text-success"><?php echo number_format($prescriptions_stats['approved'] ?? 0); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Rejected:</span>
                                    <strong class="text-danger"><?php echo number_format($prescriptions_stats['rejected'] ?? 0); ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Filled:</span>
                                    <strong class="text-info"><?php echo number_format($prescriptions_stats['filled'] ?? 0); ?></strong>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total Prescriptions:</strong>
                                    <strong class="text-primary"><?php echo number_format($prescriptions_stats['total'] ?? 0); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Alerts -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Inventory Alerts</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="alert alert-warning mb-0">
                                            <strong><i class="fas fa-box me-2"></i><?php echo $low_stock; ?></strong> medicines with low stock (&lt;10 units)
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-danger mb-0">
                                            <strong><i class="fas fa-calendar-times me-2"></i><?php echo $expired; ?></strong> expired medicines
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="alert alert-info mb-0">
                                            <strong><i class="fas fa-clock me-2"></i><?php echo $expiring_soon; ?></strong> medicines expiring within 30 days
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Selling Medicines -->
                <?php if (!empty($top_medicines)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Top 10 Selling Medicines</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Medicine Name</th>
                                        <th class="text-end">Quantity Sold</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_medicines as $index => $medicine): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($medicine['name']); ?></td>
                                        <td class="text-end"><?php echo number_format($medicine['total_sold']); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($medicine['revenue'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Categories Summary -->
                <?php if (!empty($categories_summary)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Categories Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th class="text-end">Number of Medicines</th>
                                        <th class="text-end">Total Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories_summary as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td class="text-end"><?php echo number_format($category['medicine_count'] ?? 0); ?></td>
                                        <td class="text-end"><?php echo number_format($category['total_quantity'] ?? 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Orders -->
                <?php if (!empty($recent_orders)): ?>
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="fas fa-shopping-bag me-2"></i>Recent Orders</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th class="text-end">Amount</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                        <td class="text-end">LKR <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="badge bg-<?php echo $order['status'] == 'delivered' ? 'success' : 'warning'; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                        <td><span class="badge bg-<?php echo $order['payment_status'] == 'paid' ? 'success' : 'warning'; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        function exportToPDF() {
            const element = document.querySelector('.admin-content');
            const opt = {
                margin: 10,
                filename: 'Sahana_Medicals_Report_<?php echo date('Y-m-d'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Show loading
            const btn = event.target.closest('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating PDF...';
            btn.disabled = true;
            
            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        function exportToExcel() {
            // Prepare data for Excel
            const wb = XLSX.utils.book_new();
            
            // Summary Sheet
            const summaryData = [
                ['Sahana Medicals Pharmacy - Report Summary'],
                ['Report Period:', '<?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?>'],
                ['Generated on:', '<?php echo date('M d, Y H:i:s'); ?>'],
                [],
                ['Key Performance Indicators'],
                ['Metric', 'Value'],
                ['Total Revenue', 'LKR <?php echo number_format($orders_stats['total_revenue'] ?? 0, 2); ?>'],
                ['Total Orders', '<?php echo $orders_stats['total_orders'] ?? 0; ?>'],
                ['Total Medicines', '<?php echo $medicines_stats['total'] ?? 0; ?>'],
                ['Active Customers', '<?php echo $customers_stats['total'] ?? 0; ?>'],
                [],
                ['Revenue Breakdown'],
                ['Type', 'Amount'],
                ['Paid Revenue', 'LKR <?php echo number_format($orders_stats['paid_revenue'] ?? 0, 2); ?>'],
                ['Pending Revenue', 'LKR <?php echo number_format($orders_stats['pending_revenue'] ?? 0, 2); ?>'],
                [],
                ['Prescription Statistics'],
                ['Status', 'Count'],
                ['Pending', '<?php echo $prescriptions_stats['pending'] ?? 0; ?>'],
                ['Approved', '<?php echo $prescriptions_stats['approved'] ?? 0; ?>'],
                ['Rejected', '<?php echo $prescriptions_stats['rejected'] ?? 0; ?>'],
                ['Filled', '<?php echo $prescriptions_stats['filled'] ?? 0; ?>'],
                [],
                ['Inventory Alerts'],
                ['Alert Type', 'Count'],
                ['Low Stock (<10 units)', '<?php echo $low_stock; ?>'],
                ['Expired Medicines', '<?php echo $expired; ?>'],
                ['Expiring Within 30 Days', '<?php echo $expiring_soon; ?>']
            ];
            
            const ws1 = XLSX.utils.aoa_to_sheet(summaryData);
            XLSX.utils.book_append_sheet(wb, ws1, 'Summary');
            
            <?php if (!empty($top_medicines)): ?>
            // Top Selling Medicines Sheet
            const topMedicinesData = [
                ['Top Selling Medicines'],
                ['#', 'Medicine Name', 'Quantity Sold', 'Revenue'],
                <?php foreach ($top_medicines as $index => $medicine): ?>
                [<?php echo $index + 1; ?>, '<?php echo addslashes($medicine['name']); ?>', <?php echo $medicine['total_sold']; ?>, 'LKR <?php echo number_format($medicine['revenue'], 2); ?>'],
                <?php endforeach; ?>
            ];
            const ws2 = XLSX.utils.aoa_to_sheet(topMedicinesData);
            XLSX.utils.book_append_sheet(wb, ws2, 'Top Medicines');
            <?php endif; ?>
            
            <?php if (!empty($recent_orders)): ?>
            // Recent Orders Sheet
            const ordersData = [
                ['Recent Orders'],
                ['Order #', 'Customer', 'Date', 'Amount', 'Status', 'Payment Status'],
                <?php foreach ($recent_orders as $order): ?>
                ['<?php echo $order['order_number']; ?>', '<?php echo addslashes($order['first_name'] . ' ' . $order['last_name']); ?>', '<?php echo date('M d, Y', strtotime($order['created_at'])); ?>', 'LKR <?php echo number_format($order['total_amount'], 2); ?>', '<?php echo ucfirst($order['status']); ?>', '<?php echo ucfirst($order['payment_status']); ?>'],
                <?php endforeach; ?>
            ];
            const ws3 = XLSX.utils.aoa_to_sheet(ordersData);
            XLSX.utils.book_append_sheet(wb, ws3, 'Recent Orders');
            <?php endif; ?>
            
            // Save Excel file
            XLSX.writeFile(wb, 'Sahana_Medicals_Report_<?php echo date('Y-m-d'); ?>.xlsx');
        }
    </script>
</body>
</html>

