<?php
session_start();
include '../config/database.php';
include '../config/functions.php';
include 'auth_check.php';

$message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_po':
                $pdo->beginTransaction();
                
                // Generate PO number
                $year = date('Y');
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(order_number, 4) AS UNSIGNED)) as max_num FROM purchase_orders WHERE YEAR(created_at) = $year");
                $max_num = $stmt->fetchColumn() ?: 0;
                $po_number = 'PO-' . ($max_num + 1);
                
                // Create purchase order
                $stmt = $pdo->prepare("
                    INSERT INTO purchase_orders (order_number, supplier_id, order_date, expected_delivery_date, total_amount, tax_amount, shipping_cost, discount_amount, final_amount, notes, created_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                ");
                
                $total = $_POST['total_amount'];
                $tax = $_POST['tax_amount'];
                $shipping = $_POST['shipping_cost'];
                $discount = $_POST['discount_amount'];
                $final = $total + $tax + $shipping - $discount;
                
                $stmt->execute([
                    $po_number,
                    $_POST['supplier_id'],
                    $_POST['order_date'],
                    $_POST['expected_delivery_date'],
                    $total,
                    $tax,
                    $shipping,
                    $discount,
                    $final,
                    $_POST['notes'],
                    $_SESSION['admin_id']
                ]);
                
                $po_id = $pdo->lastInsertId();
                
                // Add items
                $medicines = $_POST['medicines'];
                $quantities = $_POST['quantities'];
                $prices = $_POST['prices'];
                
                foreach ($medicines as $index => $medicine_id) {
                    if (!empty($medicine_id) && !empty($quantities[$index])) {
                        $item_total = $quantities[$index] * $prices[$index];
                        $stmt = $pdo->prepare("
                            INSERT INTO purchase_order_items (purchase_order_id, medicine_id, quantity_ordered, unit_price, total_price)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$po_id, $medicine_id, $quantities[$index], $prices[$index], $item_total]);
                    }
                }
                
                $pdo->commit();
                $message = "Purchase order $po_number created successfully!";
                break;
                
            case 'receive_stock':
                // CRITICAL: This is where the stock integration happens!
                $pdo->beginTransaction();
                
                $po_id = $_POST['po_id'];
                $items = $_POST['items'];
                
                foreach ($items as $item_id => $data) {
                    $qty_received = (int)($data['quantity'] ?? 0);
                    
                    if ($qty_received > 0) {
                        // Update purchase order item
                        $stmt = $pdo->prepare("
                            UPDATE purchase_order_items 
                            SET quantity_received = quantity_received + ?, 
                                received_date = CURDATE(),
                                batch_number = ?,
                                expiry_date = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $qty_received,
                            $data['batch_number'],
                            $data['expiry_date'],
                            $item_id
                        ]);
                        
                        // Get medicine ID
                        $stmt = $pdo->prepare("SELECT medicine_id FROM purchase_order_items WHERE id = ?");
                        $stmt->execute([$item_id]);
                        $medicine_id = $stmt->fetchColumn();
                        
                        // *** UPDATE MEDICINE STOCK - THIS IS THE INTEGRATION! ***
                        $stmt = $pdo->prepare("
                            UPDATE medicines 
                            SET quantity = quantity + ?,
                                batch_number = ?,
                                expiry_date = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $qty_received,
                            $data['batch_number'],
                            $data['expiry_date'],
                            $medicine_id
                        ]);
                    }
                }
                
                // Update PO status
                $stmt = $pdo->prepare("
                    SELECT 
                        SUM(quantity_ordered) as total_ordered,
                        SUM(quantity_received) as total_received
                    FROM purchase_order_items
                    WHERE purchase_order_id = ?
                ");
                $stmt->execute([$po_id]);
                $po_status = $stmt->fetch();
                
                $new_status = 'partially_received';
                if ($po_status['total_received'] >= $po_status['total_ordered']) {
                    $new_status = 'received';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE purchase_orders 
                    SET status = ?, actual_delivery_date = CURDATE() 
                    WHERE id = ?
                ");
                $stmt->execute([$new_status, $po_id]);
                
                $pdo->commit();
                $message = 'Stock received and medicine inventory updated successfully!';
                break;
                
            case 'update_status':
                $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
                $stmt->execute([$_POST['status'], $_POST['po_id']]);
                $message = 'Purchase order status updated!';
                break;
        }
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get purchase orders
$filter = $_GET['filter'] ?? '';
$where = '';
if ($filter) {
    $where = "WHERE po.status = '$filter'";
}

$stmt = $pdo->query("
    SELECT po.*, s.company_name as supplier_name,
           COUNT(poi.id) as item_count,
           SUM(poi.quantity_ordered) as total_items,
           SUM(poi.quantity_received) as received_items
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
    $where
    GROUP BY po.id
    ORDER BY po.created_at DESC
");
$purchase_orders = $stmt->fetchAll();

// Get suppliers for dropdown
$stmt = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'active' ORDER BY company_name");
$suppliers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Purchase Orders</h2>
                            <p class="text-muted mb-0">Manage supplier orders and stock receiving</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="suppliers.php" class="btn btn-outline-secondary">
                                <i class="fas fa-building me-2"></i>Suppliers
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPOModal">
                                <i class="fas fa-plus me-2"></i>New Purchase Order
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

                <!-- Filters -->
                <div class="mb-4">
                    <div class="d-flex gap-2">
                        <a href="?" class="btn btn-outline-primary btn-sm">All</a>
                        <a href="?filter=pending" class="btn btn-outline-warning btn-sm">Pending</a>
                        <a href="?filter=confirmed" class="btn btn-outline-info btn-sm">Confirmed</a>
                        <a href="?filter=partially_received" class="btn btn-outline-secondary btn-sm">Partially Received</a>
                        <a href="?filter=received" class="btn btn-outline-success btn-sm">Received</a>
                        <a href="?filter=cancelled" class="btn btn-outline-danger btn-sm">Cancelled</a>
                    </div>
                </div>

                <!-- Purchase Orders Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Supplier</th>
                                        <th>Order Date</th>
                                        <th>Expected Delivery</th>
                                        <th>Items</th>
                                        <th>Total Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($purchase_orders as $po): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($po['order_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                                        <td><?php echo $po['expected_delivery_date'] ? date('M d, Y', strtotime($po['expected_delivery_date'])) : 'N/A'; ?></td>
                                        <td>
                                            <small>
                                                <?php echo $po['received_items']; ?> / <?php echo $po['total_items']; ?> received
                                            </small>
                                        </td>
                                        <td><?php echo formatPrice($po['final_amount']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($po['status']) {
                                                    'pending' => 'warning',
                                                    'confirmed' => 'info',
                                                    'partially_received' => 'secondary',
                                                    'received' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'primary'
                                                };
                                            ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $po['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="viewPO(<?php echo $po['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($po['status'] !== 'received' && $po['status'] !== 'cancelled'): ?>
                                                <button type="button" class="btn btn-outline-success" onclick="receiveStock(<?php echo $po['id']; ?>)">
                                                    <i class="fas fa-box-open"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Create PO Modal -->
    <div class="modal fade" id="createPOModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" id="createPOForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Purchase Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_po">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="supplier_id" class="form-label">Supplier *</label>
                                <select class="form-select" id="supplier_id" name="supplier_id" required onchange="loadSupplierProducts()">
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?php echo $supplier['id']; ?>">
                                        <?php echo htmlspecialchars($supplier['company_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="order_date" class="form-label">Order Date *</label>
                                <input type="date" class="form-control" id="order_date" name="order_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="expected_delivery_date" class="form-label">Expected Delivery</label>
                                <input type="date" class="form-control" id="expected_delivery_date" name="expected_delivery_date" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            </div>
                        </div>
                        
                        <h6 class="mb-3">Order Items</h6>
                        <div id="poItems">
                            <div class="row mb-2 po-item">
                                <div class="col-md-5">
                                    <select class="form-select form-select-sm medicine-select" name="medicines[]" onchange="updatePrice(this)">
                                        <option value="">Select Medicine</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm quantity-input" name="quantities[]" placeholder="Qty" min="1" onchange="calculateTotal()">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control form-control-sm price-input" name="prices[]" placeholder="Price" step="0.01" readonly>
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control form-control-sm item-total" placeholder="Total" readonly>
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeItem(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addItem()">
                            <i class="fas fa-plus me-2"></i>Add Item
                        </button>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-2">
                                    <label>Subtotal:</label>
                                    <input type="number" class="form-control" id="total_amount" name="total_amount" readonly>
                                </div>
                                <div class="mb-2">
                                    <label>Tax:</label>
                                    <input type="number" class="form-control" id="tax_amount" name="tax_amount" value="0" step="0.01" onchange="calculateTotal()">
                                </div>
                                <div class="mb-2">
                                    <label>Shipping:</label>
                                    <input type="number" class="form-control" id="shipping_cost" name="shipping_cost" value="0" step="0.01" onchange="calculateTotal()">
                                </div>
                                <div class="mb-2">
                                    <label>Discount:</label>
                                    <input type="number" class="form-control" id="discount_amount" name="discount_amount" value="0" step="0.01" onchange="calculateTotal()">
                                </div>
                                <div class="mb-2">
                                    <label><strong>Final Total:</strong></label>
                                    <input type="text" class="form-control fw-bold" id="final_amount" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Receive Stock Modal -->
    <div class="modal fade" id="receiveStockModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Receive Stock</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="receiveStockContent">
                        <!-- Content loaded via AJAX -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Confirm Receipt & Update Stock
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
        let supplierProducts = [];
        
        function loadSupplierProducts() {
            const supplierId = document.getElementById('supplier_id').value;
            if (!supplierId) return;
            
            fetch(`get_supplier_products.php?supplier_id=${supplierId}`)
                .then(res => res.json())
                .then(data => {
                    supplierProducts = data;
                    updateAllProductSelects();
                });
        }
        
        function updateAllProductSelects() {
            document.querySelectorAll('.medicine-select').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">Select Medicine</option>';
                supplierProducts.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.medicine_id;
                    option.textContent = `${product.medicine_name} - ${product.supplier_price}`;
                    option.dataset.price = product.supplier_price;
                    select.appendChild(option);
                });
                select.value = currentValue;
            });
        }
        
        function updatePrice(select) {
            const row = select.closest('.po-item');
            const priceInput = row.querySelector('.price-input');
            const selected = select.options[select.selectedIndex];
            if (selected.dataset.price) {
                priceInput.value = selected.dataset.price;
                calculateTotal();
            }
        }
        
        function calculateTotal() {
            let subtotal = 0;
            document.querySelectorAll('.po-item').forEach(row => {
                const qty = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const itemTotal = qty * price;
                row.querySelector('.item-total').value = itemTotal.toFixed(2);
                subtotal += itemTotal;
            });
            
            const tax = parseFloat(document.getElementById('tax_amount').value) || 0;
            const shipping = parseFloat(document.getElementById('shipping_cost').value) || 0;
            const discount = parseFloat(document.getElementById('discount_amount').value) || 0;
            const final = subtotal + tax + shipping - discount;
            
            document.getElementById('total_amount').value = subtotal.toFixed(2);
            document.getElementById('final_amount').value = final.toFixed(2);
        }
        
        function addItem() {
            const template = document.querySelector('.po-item').cloneNode(true);
            template.querySelectorAll('input').forEach(input => input.value = '');
            document.getElementById('poItems').appendChild(template);
            updateAllProductSelects();
        }
        
        function removeItem(btn) {
            if (document.querySelectorAll('.po-item').length > 1) {
                btn.closest('.po-item').remove();
                calculateTotal();
            }
        }
        
        function receiveStock(poId) {
            fetch(`get_po_details.php?po_id=${poId}`)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('receiveStockContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('receiveStockModal')).show();
                });
        }
        
        function viewPO(poId) {
            window.location.href = `view_purchase_order.php?id=${poId}`;
        }
    </script>
</body>
</html>

