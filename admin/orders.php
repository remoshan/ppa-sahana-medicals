<?php
session_start();
include '../config/database.php';
include '../config/functions.php';

// Secure authentication check
include 'auth_check.php';

$message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'update_status':
                $order_id = $_POST['id'];
                $status = $_POST['status'];
                
                // Update order status only (payment status is now read-only in order management)
                $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
                $stmt->execute([$status, $order_id]);
                
                $message = 'Order status updated successfully!';
                break;
                
            case 'update_payment_status':
                $order_id = $_POST['id'];
                $payment_status = $_POST['payment_status'];
                
                // Update order payment status
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
                $stmt->execute([$payment_status, $order_id]);
                
                // Map payment status for customer_payments table (paid -> completed)
                $customer_payment_status = $payment_status;
                if ($customer_payment_status === 'paid') {
                    $customer_payment_status = 'completed';
                }
                
                // Also update customer_payments table to keep both in sync
                $stmt = $pdo->prepare("UPDATE customer_payments SET payment_status = ? WHERE order_id = ?");
                $stmt->execute([$customer_payment_status, $order_id]);
                
                $message = 'Payment status updated successfully in both orders and payments!';
                break;
                
            case 'delete':
                // First delete order items
                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$_POST['id']]);
                
                // Then delete the order
                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = 'Order deleted successfully!';
                break;
        }
    } catch (PDOException $e) {
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get order statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(total_amount) as total_revenue,
        SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as delivered_revenue,
        SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_revenue,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_count,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as payment_pending_count
    FROM orders
    WHERE MONTH(created_at) = MONTH(CURDATE()) 
    AND YEAR(created_at) = YEAR(CURDATE())
");
$order_stats = $stmt->fetch();

// Get orders with customer information
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    $where_conditions[] = "o.status = ?";
    $params[] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT o.*, c.first_name, c.last_name, c.email, c.phone,
           COUNT(oi.id) as item_count
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN order_items oi ON o.id = oi.order_id
    $where_clause
    GROUP BY o.id 
    ORDER BY o.created_at DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Orders Management</h2>
                            <p class="text-muted mb-0">Track and manage customer orders</p>
                        </div>
                        <div>
                            <a href="reports.php?type=orders" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createOrderModal">
                                <i class="fas fa-plus me-2"></i>Create Order
                            </button>
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
                                <i class="fas fa-shopping-cart text-primary mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($order_stats['total_revenue']); ?></h4>
                                <small class="text-muted">Total Revenue (This Month)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle text-success mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($order_stats['delivered_revenue']); ?></h4>
                                <small class="text-muted">Delivered Orders (<?php echo $order_stats['delivered_count']; ?>)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-clock text-warning mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo formatPrice($order_stats['pending_revenue']); ?></h4>
                                <small class="text-muted">Pending Orders (<?php echo $order_stats['pending_count']; ?>)</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-receipt text-info mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo $order_stats['total_orders']; ?></h4>
                                <small class="text-muted">Total Orders</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search by order #, customer name, email..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="?" class="btn btn-outline-primary btn-sm <?php echo empty($filter) ? 'active' : ''; ?>">All</a>
                            <a href="?filter=pending" class="btn btn-outline-warning btn-sm <?php echo $filter === 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?filter=confirmed" class="btn btn-outline-info btn-sm <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">Confirmed</a>
                            <a href="?filter=processing" class="btn btn-outline-secondary btn-sm <?php echo $filter === 'processing' ? 'active' : ''; ?>">Processing</a>
                            <a href="?filter=delivered" class="btn btn-outline-success btn-sm <?php echo $filter === 'delivered' ? 'active' : ''; ?>">Delivered</a>
                            <a href="?filter=cancelled" class="btn btn-outline-danger btn-sm <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                        </div>
                    </div>
                </div>

                <!-- Orders Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th>Items</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Order Status</th>
                                        <th>Payment Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            <i class="fas fa-shopping-cart mb-2" style="font-size: 3rem; opacity: 0.3;"></i>
                                            <p>No orders found</p>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td>
                                                <div>
                                                    <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y H:i', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $order['item_count']; ?> item(s)</span>
                                            </td>
                                            <td><strong><?php echo formatPrice($order['total_amount']); ?></strong></td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)">
                                                    <option value="pending" <?php echo $order['status']==='pending'?'selected':''; ?>>Pending</option>
                                                    <option value="confirmed" <?php echo $order['status']==='confirmed'?'selected':''; ?>>Confirmed</option>
                                                    <option value="processing" <?php echo $order['status']==='processing'?'selected':''; ?>>Processing</option>
                                                    <option value="shipped" <?php echo $order['status']==='shipped'?'selected':''; ?>>Shipped</option>
                                                    <option value="delivered" <?php echo $order['status']==='delivered'?'selected':''; ?>>Delivered</option>
                                                    <option value="cancelled" <?php echo $order['status']==='cancelled'?'selected':''; ?>>Cancelled</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm payment-status-select" onchange="updatePaymentStatus(<?php echo $order['id']; ?>, this.value, this)">
                                                    <option value="pending" <?php echo $order['payment_status']==='pending'?'selected':''; ?>>Pending</option>
                                                    <option value="paid" <?php echo $order['payment_status']==='paid'?'selected':''; ?>>Paid</option>
                                                    <option value="failed" <?php echo $order['payment_status']==='failed'?'selected':''; ?>>Failed</option>
                                                    <option value="refunded" <?php echo $order['payment_status']==='refunded'?'selected':''; ?>>Refunded</option>
                                                </select>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-info" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['order_number']); ?>')">
                                                        <i class="fas fa-trash"></i> Delete
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

    <!-- Create Order Modal -->
    <div class="modal fade" id="createOrderModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form id="createOrderForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Customer *</label>
                                <select class="form-select" id="order_customer_id" required>
                                    <option value="" disabled selected>Select Customer</option>
                                    <?php 
                                    $cstmt = $pdo->query("SELECT id, first_name, last_name, email FROM customers WHERE status='active' ORDER BY first_name, last_name LIMIT 200");
                                    foreach ($cstmt->fetchAll() as $c): ?>
                                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['first_name'].' '.$c['last_name'].($c['email']?(' ('.$c['email'].')') : '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Payment Method *</label>
                                <select class="form-select" id="order_payment_method" required>
                                    <option value="cash_on_delivery">Cash on Delivery</option>
                                    <option value="card">Card</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0">Order Items</h6>
                            <div>
                                <small class="text-muted me-3">Items: <span id="itemCount" class="badge bg-primary">1</span></small>
                                <small class="text-muted">Total Quantity: <span id="totalQuantity" class="badge bg-info">0</span></small>
                            </div>
                        </div>
                        <div id="orderItems">
                            <div class="row g-2 align-items-center order-item mb-2">
                                <div class="col-md-6">
                                    <select class="form-select form-select-sm order-medicine" required onchange="onMedicineChange(this)">
                                        <option value="">Select medicine</option>
                                        <?php 
                                        $mstmt = $pdo->query("SELECT id, name, price FROM medicines WHERE status='active' ORDER BY name LIMIT 500");
                                        foreach ($mstmt->fetchAll() as $m): ?>
                                            <option value="<?php echo $m['id']; ?>" data-price="<?php echo $m['price']; ?>"><?php echo htmlspecialchars($m['name']); ?> - <?php echo number_format($m['price'],2); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm order-qty" placeholder="Qty" min="1" value="1" required oninput="recalcOrderTotal(); updateTotalQuantity()">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm order-price" placeholder="Price" step="0.01" required oninput="recalcOrderTotal()">
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeOrderItem(this)"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                        <div id="orderMedicineOptionsTemplate" class="d-none">
                            <option value="">Select medicine</option>
                            <?php 
                            $mstmt2 = $pdo->query("SELECT id, name, price FROM medicines WHERE status='active' ORDER BY name LIMIT 500");
                            foreach ($mstmt2->fetchAll() as $m2): ?>
                                <option value="<?php echo $m2['id']; ?>" data-price="<?php echo $m2['price']; ?>"><?php echo htmlspecialchars($m2['name']); ?> - <?php echo number_format($m2['price'],2); ?></option>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addItemBtn" onclick="handleAddItem(event)"><i class="fas fa-plus me-1"></i>Add Item</button>
                        <hr>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" id="order_notes" rows="2"></textarea>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex justify-content-between">
                                    <strong>Total:</strong>
                                    <span id="order_total">0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <!-- Order details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Are you sure you want to delete order "<span id="deleteOrderNumber"></span>"? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden forms for AJAX updates -->
    <form id="updateStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" id="updateStatusId">
        <input type="hidden" name="status" id="updateStatusValue">
    </form>

    <form id="updatePaymentStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_payment_status">
        <input type="hidden" name="id" id="updatePaymentStatusId">
        <input type="hidden" name="payment_status" id="updatePaymentStatusValue">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crud-ajax.js"></script>
    <script src="assets/js/universal-crud.js"></script>
    <script>
        // Create Order modal logic
        let allCustomers = [];
        let allMedicines = [];
        // Global flag to prevent duplicate additions
        let isAddingItem = false;
        
        document.addEventListener('DOMContentLoaded', async function() {
            populateMedicines();
            recalcOrderTotal();
            updateItemCount();
            updateTotalQuantity();
            document.getElementById('createOrderForm').addEventListener('submit', submitCreateOrder);
            
            // Reset form and item count when modal opens
            const modal = document.getElementById('createOrderModal');
            modal.addEventListener('shown.bs.modal', function() {
                // Reset to single item
                const container = document.getElementById('orderItems');
                const items = container.querySelectorAll('.order-item');
                
                // Remove all items except the first one
                items.forEach((item, index) => {
                    if (index > 0) {
                        item.remove();
                    }
                });
                
                // Reset the first item
                const firstItem = container.querySelector('.order-item');
                if (firstItem) {
                    firstItem.querySelector('.order-medicine').value = '';
                    firstItem.querySelector('.order-qty').value = 1;
                    firstItem.querySelector('.order-price').value = '';
                }
                
                document.getElementById('order_customer_id').value = '';
                document.getElementById('order_payment_method').value = 'cash_on_delivery';
                document.getElementById('order_notes').value = '';
                
                recalcOrderTotal();
                updateItemCount();
                updateTotalQuantity();
            });
        });

        // In absence of dedicated endpoints, we'll pull minimal data via embedded data attributes could be added later
        function onMedicineChange(selectElement) {
            const priceInput = selectElement.closest('.order-item').querySelector('.order-price');
            const opt = selectElement.options[selectElement.selectedIndex];
            if (opt && opt.dataset.price) {
                priceInput.value = parseFloat(opt.dataset.price).toFixed(2);
                recalcOrderTotal();
            }
        }

        async function populateMedicines() {
            const selects = document.querySelectorAll('.order-medicine');
            const templateOptions = document.getElementById('orderMedicineOptionsTemplate').innerHTML;
            selects.forEach(sel => {
                if (!sel.dataset.filled) {
                    sel.innerHTML = templateOptions;
                    sel.dataset.filled = '1';
                    sel.setAttribute('onchange', 'onMedicineChange(this)');
                }
            });
        }

        // Handler function to prevent duplicate item additions
        function handleAddItem(event) {
            console.log('>>> handleAddItem CALLED');
            console.log('>>> isAddingItem flag:', isAddingItem);
            
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            if (isAddingItem) {
                console.log('>>> BLOCKED - already adding');
                return; // Already adding an item, ignore this call
            }
            
            console.log('>>> Setting flag and calling addOrderItem');
            isAddingItem = true;
            addOrderItem();
            
            // Reset flag after a short delay
            setTimeout(() => {
                console.log('>>> Resetting isAddingItem flag');
                isAddingItem = false;
            }, 300);
        }

        function addOrderItem() {
            console.log('=== addOrderItem START ===');
            const container = document.getElementById('orderItems');
            console.log('Container:', container);
            console.log('Items BEFORE clone:', container.querySelectorAll('.order-item').length);
            
            const firstItem = container.querySelector('.order-item');
            
            if (!firstItem) {
                console.error('No template item found!');
                return;
            }
            
            console.log('First item to clone:', firstItem);
            const row = firstItem.cloneNode(true);
            console.log('Cloned row:', row);
            console.log('Items AFTER clone (before append):', container.querySelectorAll('.order-item').length);
            
            row.querySelector('.order-medicine').dataset.filled = '';
            row.querySelector('.order-medicine').innerHTML = '';
            row.querySelector('.order-medicine').value = '';
            row.querySelector('.order-qty').value = 1;
            row.querySelector('.order-price').value = '';
            
            // Ensure the quantity input has the proper event handler
            const qtyInput = row.querySelector('.order-qty');
            qtyInput.setAttribute('oninput', 'recalcOrderTotal(); updateTotalQuantity()');
            
            console.log('About to appendChild...');
            container.appendChild(row);
            console.log('Items AFTER appendChild:', container.querySelectorAll('.order-item').length);
            console.log('=== addOrderItem END ===');
            
            populateMedicines();
            recalcOrderTotal();
            updateItemCount();
            updateTotalQuantity();
        }
        function removeOrderItem(btn) {
            const container = document.getElementById('orderItems');
            if (container.querySelectorAll('.order-item').length > 1) {
                btn.closest('.order-item').remove();
                recalcOrderTotal();
                updateItemCount();
            }
        }
        function updateItemCount() {
            const items = document.querySelectorAll('#orderItems .order-item');
            const count = items.length;
            const countElement = document.getElementById('itemCount');
            if (countElement) {
                countElement.textContent = count;
                console.log('Item count updated:', count);
            }
            updateTotalQuantity();
        }
        
        function updateTotalQuantity() {
            let totalQty = 0;
            document.querySelectorAll('#orderItems .order-item').forEach(row => {
                const qty = parseInt(row.querySelector('.order-qty').value) || 0;
                totalQty += qty;
            });
            const qtyElement = document.getElementById('totalQuantity');
            if (qtyElement) {
                qtyElement.textContent = totalQty;
                console.log('Total quantity updated:', totalQty);
            }
        }
        function recalcOrderTotal() {
            let total = 0;
            document.querySelectorAll('#orderItems .order-item').forEach(row => {
                const qty = parseFloat(row.querySelector('.order-qty').value) || 0;
                const price = parseFloat(row.querySelector('.order-price').value) || 0;
                total += qty * price;
            });
            document.getElementById('order_total').textContent = total.toFixed(2);
        }
        async function submitCreateOrder(e) {
            e.preventDefault();
            const items = [];
            document.querySelectorAll('#orderItems .order-item').forEach(row => {
                const medSel = row.querySelector('.order-medicine');
                const medId = parseInt(medSel.value || 0);
                const qty = parseInt(row.querySelector('.order-qty').value || 0);
                const price = parseFloat(row.querySelector('.order-price').value || 0);
                if (medId && qty > 0) items.push({ medicine_id: medId, quantity: qty, unit_price: price });
            });
            if (!document.getElementById('order_customer_id').value) {
                showToast('Please select a customer.', 'error');
                return;
            }
            if (items.length === 0) {
                showToast('Please add at least one order item.', 'error');
                return;
            }
            const fd = new FormData();
            fd.append('action', 'create_order');
            fd.append('table', 'orders');
            fd.append('customer_id', document.getElementById('order_customer_id').value);
            fd.append('payment_method', document.getElementById('order_payment_method').value);
            fd.append('items', JSON.stringify(items));
            const res = await fetch('ajax_handler.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                showToast(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('createOrderModal')).hide();
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(data.message, 'error');
            }
        }

        // AJAX delete order
        (function initDeleteOrderAjax(){
            const deleteForm = document.getElementById('orderDeleteForm');
            if (!deleteForm) return;
            deleteForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData(deleteForm);
                fd.append('table', 'orders');
                try {
                    const res = await fetch('ajax_handler.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message || 'Delete failed', 'error');
                    }
                } catch (err) {
                    showToast('Delete failed', 'error');
                }
            });
        })();
        async function updateOrderStatus(orderId, status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', orderId);
            formData.append('status', status);
            formData.append('table', 'orders');
            
            const response = await fetch('ajax_handler.php', { method: 'POST', body: formData });
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message, 'error');
            }
        }
        
        function updatePaymentStatus(orderId, paymentStatus, selectElement) {
            // Show confirmation
            const statusText = selectElement.options[selectElement.selectedIndex].text;
            if (confirm(`Are you sure you want to change payment status to ${statusText}?`)) {
                // Add loading indicator
                const originalHTML = selectElement.innerHTML;
                selectElement.disabled = true;
                selectElement.innerHTML = '<option>Updating...</option>';
                
                const formData = new FormData();
                formData.append('action', 'update_payment_status');
                formData.append('id', orderId);
                formData.append('payment_status', paymentStatus);
                formData.append('table', 'orders');
                
                fetch('ajax_handler.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(data.message, 'success');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            showToast(data.message, 'error');
                            selectElement.disabled = false;
                            selectElement.innerHTML = originalHTML;
                        }
                    })
                    .catch(() => {
                        showToast('Update failed', 'error');
                        selectElement.disabled = false;
                        selectElement.innerHTML = originalHTML;
                    });
            } else {
                // Reset to original value if cancelled
                selectElement.selectedIndex = Array.from(selectElement.options).findIndex(
                    option => option.value === selectElement.dataset.originalValue
                );
            }
        }
        
        // Store original values for payment status selects
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.payment-status-select').forEach(select => {
                select.dataset.originalValue = select.value;
                select.addEventListener('focus', function() {
                    this.dataset.originalValue = this.value;
                });
            });
        });
        
        async function viewOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
            const content = document.getElementById('orderDetailsContent');
            
            // Show loading state
            content.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            modal.show();
            
            try {
                // Fetch order details
                const response = await fetch(`get_order_details.php?id=${orderId}`);
                const data = await response.json();
                
                if (data.success) {
                    const order = data.order;
                    const items = data.items;
                    
                    // Build the HTML content
                    let html = `
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-receipt me-2"></i>Order Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Order Number:</th>
                                        <td><strong>${order.order_number}</strong></td>
                                    </tr>
                                    <tr>
                                        <th>Order Date:</th>
                                        <td>${new Date(order.created_at).toLocaleString()}</td>
                                    </tr>
                                    <tr>
                                        <th>Order Status:</th>
                                        <td><span class="badge bg-primary">${order.status.charAt(0).toUpperCase() + order.status.slice(1)}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Payment Status:</th>
                                        <td><span class="badge bg-${order.payment_status === 'paid' ? 'success' : 'warning'}">${order.payment_status.charAt(0).toUpperCase() + order.payment_status.slice(1)}</span></td>
                                    </tr>
                                    <tr>
                                        <th>Payment Method:</th>
                                        <td>${order.payment_method.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>Customer Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <th width="40%">Name:</th>
                                        <td>${order.first_name} ${order.last_name}</td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td>${order.email || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td>${order.phone || 'N/A'}</td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td>${order.address || 'N/A'}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <h6 class="text-primary mb-3"><i class="fas fa-shopping-cart me-2"></i>Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine</th>
                                        <th class="text-center">Quantity</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    items.forEach(item => {
                        html += `
                            <tr>
                                <td><strong>${item.medicine_name}</strong></td>
                                <td class="text-center">${item.quantity}</td>
                                <td class="text-end">LKR ${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td class="text-end">LKR ${parseFloat(item.total_price).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                </tbody>
                                <tfoot>
                                    <tr class="table-light">
                                        <th colspan="3" class="text-end">Total Amount:</th>
                                        <th class="text-end">LKR ${parseFloat(order.total_amount).toFixed(2)}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    `;
                    
                    content.innerHTML = html;
                } else {
                    content.innerHTML = '<div class="alert alert-danger">Error loading order details: ' + data.message + '</div>';
                }
            } catch (error) {
                content.innerHTML = '<div class="alert alert-danger">Error loading order details. Please try again.</div>';
                console.error('Error:', error);
            }
        }
        
        function confirmDelete(id, orderNumber) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteOrderNumber').textContent = orderNumber;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
