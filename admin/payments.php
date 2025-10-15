<?php
session_start();
include '../config/database.php';
include '../config/functions.php';
include 'auth_check.php';

$message = '';
$error_message = '';

// Handle payment status updates and deletions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_status':
                $stmt = $pdo->prepare("
                    UPDATE customer_payments 
                    SET payment_status = ?, notes = ?, transaction_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['payment_status'],
                    $_POST['notes'],
                    $_POST['transaction_id'],
                    $_POST['payment_id']
                ]);
                
                // Map payment status for orders table (completed -> paid)
                $order_payment_status = $_POST['payment_status'];
                if ($order_payment_status === 'completed') {
                    $order_payment_status = 'paid';
                }
                
                // Also update order payment status
                $stmt = $pdo->prepare("
                    UPDATE orders 
                    SET payment_status = ?
                    WHERE id = (SELECT order_id FROM customer_payments WHERE id = ?)
                ");
                $stmt->execute([$order_payment_status, $_POST['payment_id']]);
                
                $_SESSION['success_message'] = 'Payment status updated successfully in both orders and payments!';
                header('Location: payments.php');
                exit();
                
            case 'delete':
                // Get the order_id before deleting the payment
                $stmt = $pdo->prepare("SELECT order_id FROM customer_payments WHERE id = ?");
                $stmt->execute([$_POST['payment_id']]);
                $order_id = $stmt->fetchColumn();
                
                // Delete the payment record
                $stmt = $pdo->prepare("DELETE FROM customer_payments WHERE id = ?");
                $stmt->execute([$_POST['payment_id']]);
                
                // Update the order's payment status to pending if it had this payment
                if ($order_id) {
                    $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'pending' WHERE id = ?");
                    $stmt->execute([$order_id]);
                }
                
                $_SESSION['success_message'] = 'Payment deleted successfully!';
                header('Location: payments.php');
                exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = 'Operation failed: ' . $e->getMessage();
        header('Location: payments.php');
        exit();
    }
}

// Display messages from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Get payment statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount) as total_amount,
        SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as completed_amount,
        SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) as completed_count
    FROM customer_payments
    WHERE MONTH(payment_date) = MONTH(CURDATE()) 
    AND YEAR(payment_date) = YEAR(CURDATE())
");
$stats = $stmt->fetch();

// Get filter
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$where_conditions = ['1=1'];
$params = [];

if ($filter !== 'all') {
    $where_conditions[] = "cp.payment_status = ?";
    $params[] = $filter;
}

if (!empty($search)) {
    $where_conditions[] = "(cp.payment_number LIKE ? OR o.order_number LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get payments
$stmt = $pdo->prepare("
    SELECT cp.*, 
           o.order_number,
           o.status as order_status,
           c.first_name,
           c.last_name,
           c.email
    FROM customer_payments cp
    JOIN orders o ON cp.order_id = o.id
    JOIN customers c ON cp.customer_id = c.id
    WHERE $where_clause
    ORDER BY cp.payment_date DESC
    LIMIT 100
");
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <div class="admin-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1">Payment Management</h2>
                            <p class="text-muted mb-0">Track customer payments and transactions</p>
                        </div>
                        <div>
                            <a href="reports.php?type=payments" class="btn btn-outline-success">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-dollar-sign text-success mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($stats['total_amount']); ?></h4>
                                <small class="text-muted">Total Payments (This Month)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle text-success mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($stats['completed_amount']); ?></h4>
                                <small class="text-muted">Completed Payments (<?php echo $stats['completed_count']; ?>)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock text-warning mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($stats['pending_amount']); ?></h4>
                                <small class="text-muted">Pending Payments (<?php echo $stats['pending_count']; ?>)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-receipt text-primary mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo $stats['total_payments']; ?></h4>
                                <small class="text-muted">Total Transactions</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search by payment #, order #, or customer..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="?" class="btn btn-outline-primary btn-sm <?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
                            <a href="?filter=completed" class="btn btn-outline-success btn-sm <?php echo $filter === 'completed' ? 'active' : ''; ?>">Completed</a>
                            <a href="?filter=pending" class="btn btn-outline-warning btn-sm <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?filter=failed" class="btn btn-outline-danger btn-sm <?php echo $filter === 'failed' ? 'active' : ''; ?>">Failed</a>
                            <a href="?filter=refunded" class="btn btn-outline-secondary btn-sm <?php echo $filter === 'refunded' ? 'active' : ''; ?>">Refunded</a>
                        </div>
                    </div>
                </div>

                <!-- Payments Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Payment #</th>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Payment Status</th>
                                        <th>Order Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox mb-2" style="font-size: 3rem; opacity: 0.3;"></i>
                                            <p>No payments found</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($payments as $payment): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($payment['payment_number']); ?></strong></td>
                                            <td>
                                                <a href="orders.php?search=<?php echo urlencode($payment['order_number']); ?>">
                                                    <?php echo htmlspecialchars($payment['order_number']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></td>
                                            <td><strong><?php echo formatPrice($payment['amount']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucwords(str_replace('_', ' ', $payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" data-current-status="<?php echo htmlspecialchars($payment['payment_status']); ?>" onchange="onPaymentStatusChange(<?php echo $payment['id']; ?>, this)">
                                                    <option value="pending" <?php echo $payment['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="completed" <?php echo $payment['payment_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="failed" <?php echo $payment['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                    <option value="refunded" <?php echo $payment['payment_status'] === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($payment['order_status']) {
                                                        'pending' => 'warning',
                                                        'processing' => 'info',
                                                        'shipped' => 'primary',
                                                        'delivered' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($payment['order_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="viewPayment(<?php echo htmlspecialchars(json_encode($payment)); ?>)"
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($payment['payment_status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-outline-success" 
                                                            onclick="updateStatus(<?php echo $payment['id']; ?>, 'completed')"
                                                            title="Mark as Completed">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $payment['id']; ?>, '<?php echo htmlspecialchars($payment['payment_number']); ?>')"
                                                            title="Delete Payment">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Payment Modal -->
    <div class="modal fade" id="viewPaymentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Payment Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="payment_id" id="modal_payment_id">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Payment Number:</strong>
                                <p id="modal_payment_number"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Order Number:</strong>
                                <p id="modal_order_number"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Customer:</strong>
                                <p id="modal_customer"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Amount:</strong>
                                <p id="modal_amount"></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Payment Method:</strong>
                                <p id="modal_payment_method"></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Date:</strong>
                                <p id="modal_payment_date"></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="pending">Pending</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="transaction_id" class="form-label">Transaction ID</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Payment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deletePaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="deletePaymentForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payment_id" id="delete_payment_id">
                        
                        <div class="text-center py-3">
                            <i class="fas fa-exclamation-circle text-danger mb-3" style="font-size: 4rem;"></i>
                            <h5>Are you sure you want to delete this payment?</h5>
                            <p class="text-muted mb-3">Payment Number: <strong id="delete_payment_number"></strong></p>
                            <div class="alert alert-warning">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Warning:</strong> This action cannot be undone. The associated order's payment status will be reset to "pending".
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Delete Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crud-ajax.js"></script>
    <script src="assets/js/universal-crud.js"></script>
    <script>
        function viewPayment(payment) {
            document.getElementById('modal_payment_id').value = payment.id;
            document.getElementById('modal_payment_number').textContent = payment.payment_number;
            document.getElementById('modal_order_number').textContent = payment.order_number;
            document.getElementById('modal_customer').textContent = payment.first_name + ' ' + payment.last_name + ' (' + payment.email + ')';
            document.getElementById('modal_amount').textContent = 'LKR ' + parseFloat(payment.amount).toFixed(2);
            document.getElementById('modal_payment_method').textContent = payment.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            document.getElementById('modal_payment_date').textContent = new Date(payment.payment_date).toLocaleString();
            document.getElementById('payment_status').value = payment.payment_status;
            document.getElementById('transaction_id').value = payment.transaction_id || '';
            document.getElementById('notes').value = payment.notes || '';
            
            new bootstrap.Modal(document.getElementById('viewPaymentModal')).show();
        }
        
        function updateStatus(paymentId, status) {
            if (!confirm('Are you sure you want to mark this payment as ' + status + '?')) return;
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('payment_id', paymentId);
            formData.append('payment_status', status);
            formData.append('notes', '');
            formData.append('transaction_id', '');
            formData.append('table', 'payments');
            fetch('ajax_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(() => showToast('Update failed', 'error'));
        }
        
        function onPaymentStatusChange(paymentId, selectEl) {
            const newStatus = selectEl.value;
            const prevStatus = selectEl.getAttribute('data-current-status');
            if (!confirm('Change payment status from ' + prevStatus + ' to ' + newStatus + '?')) {
                selectEl.value = prevStatus;
                return;
            }

            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('payment_id', paymentId);
            formData.append('payment_status', newStatus);
            formData.append('notes', '');
            formData.append('transaction_id', '');
            formData.append('table', 'payments');

            fetch('ajax_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast('Payment status updated', 'success');
                        selectEl.setAttribute('data-current-status', newStatus);
                    } else {
                        showToast(data.message || 'Update failed', 'error');
                        selectEl.value = prevStatus;
                    }
                })
                .catch(() => {
                    showToast('Update failed', 'error');
                    selectEl.value = prevStatus;
                });
        }

        function confirmDelete(paymentId, paymentNumber) {
            document.getElementById('delete_payment_id').value = paymentId;
            document.getElementById('delete_payment_number').textContent = paymentNumber;
            
            new bootstrap.Modal(document.getElementById('deletePaymentModal')).show();
        }
    </script>
</body>
</html>
