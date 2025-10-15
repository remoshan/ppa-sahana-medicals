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
            case 'create_supplier':
                $stmt = $pdo->prepare("
                    INSERT INTO suppliers (company_name, contact_person, email, phone, address, city, country, tax_id, payment_terms, notes, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['company_name'],
                    $_POST['contact_person'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['city'],
                    $_POST['country'],
                    $_POST['tax_id'],
                    $_POST['payment_terms'],
                    $_POST['notes'],
                    $_POST['status']
                ]);
                $message = 'Supplier added successfully!';
                break;
                
            case 'update_supplier':
                $stmt = $pdo->prepare("
                    UPDATE suppliers 
                    SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, city = ?, country = ?, tax_id = ?, payment_terms = ?, notes = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['company_name'],
                    $_POST['contact_person'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['address'],
                    $_POST['city'],
                    $_POST['country'],
                    $_POST['tax_id'],
                    $_POST['payment_terms'],
                    $_POST['notes'],
                    $_POST['status'],
                    $_POST['id']
                ]);
                $message = 'Supplier updated successfully!';
                break;
                
            case 'delete_supplier':
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = 'Supplier deleted successfully!';
                break;
                
            case 'receive_stock':
                // This updates medicine stock when receiving from supplier
                $po_id = $_POST['purchase_order_id'];
                $items = $_POST['items'];
                
                $pdo->beginTransaction();
                
                foreach ($items as $item_id => $data) {
                    $qty_received = (int)$data['quantity'];
                    
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
                        
                        // Update medicine stock - THIS IS THE KEY INTEGRATION
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
                    UPDATE purchase_orders 
                    SET status = 'received', actual_delivery_date = CURDATE() 
                    WHERE id = ?
                ");
                $stmt->execute([$po_id]);
                
                $pdo->commit();
                $message = 'Stock received and updated successfully!';
                break;
        }
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get suppliers
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT s.*, 
           COUNT(DISTINCT sp.medicine_id) as product_count,
           COUNT(DISTINCT po.id) as order_count
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.id = sp.supplier_id AND sp.status = 'active'
    LEFT JOIN purchase_orders po ON s.id = po.supplier_id
    $where_clause
    GROUP BY s.id
    ORDER BY s.company_name ASC
");
$stmt->execute($params);
$suppliers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Suppliers Management</h2>
                            <p class="text-muted mb-0">Manage suppliers and purchase orders</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="reports.php?type=suppliers" class="btn btn-outline-success">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <a href="purchase_orders.php" class="btn btn-success">
                                <i class="fas fa-shopping-bag me-2"></i>Purchase Orders
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal" onclick="openModal('create')">
                                <i class="fas fa-plus me-2"></i>Add Supplier
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
                                <i class="fas fa-building text-primary mb-2 icon-3xl"></i>
                                <h4 class="mb-0"><?php echo count($suppliers); ?></h4>
                                <small class="text-muted">Total Suppliers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle text-success mb-2 icon-3xl"></i>
                                <h4 class="mb-0">
                                    <?php 
                                    $active = array_filter($suppliers, fn($s) => $s['status'] === 'active');
                                    echo count($active);
                                    ?>
                                </h4>
                                <small class="text-muted">Active Suppliers</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-box text-warning mb-2 icon-3xl"></i>
                                <h4 class="mb-0">
                                    <?php 
                                    $total_products = array_sum(array_column($suppliers, 'product_count'));
                                    echo $total_products;
                                    ?>
                                </h4>
                                <small class="text-muted">Products Supplied</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-shopping-cart text-info mb-2 icon-3xl"></i>
                                <h4 class="mb-0">
                                    <?php 
                                    $total_orders = array_sum(array_column($suppliers, 'order_count'));
                                    echo $total_orders;
                                    ?>
                                </h4>
                                <small class="text-muted">Purchase Orders</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search suppliers..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="?filter=active" class="btn btn-outline-success btn-sm">Active</a>
                            <a href="?filter=inactive" class="btn btn-outline-secondary btn-sm">Inactive</a>
                            <a href="?filter=blocked" class="btn btn-outline-danger btn-sm">Blocked</a>
                            <a href="?" class="btn btn-outline-primary btn-sm">All</a>
                        </div>
                    </div>
                </div>

                <!-- Suppliers Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Supplier</th>
                                        <th>Contact</th>
                                        <th>Location</th>
                                        <th>Products</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($supplier['contact_person']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <small><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($supplier['email']); ?></small>
                                                <br>
                                                <small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($supplier['phone']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo htmlspecialchars($supplier['city']); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($supplier['country']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $supplier['product_count']; ?> items</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $supplier['status'] === 'active' ? 'success' : 
                                                     ($supplier['status'] === 'inactive' ? 'secondary' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($supplier['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($supplier)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <a href="supplier_products.php?supplier_id=<?php echo $supplier['id']; ?>" class="btn btn-outline-success" title="Manage Products">
                                                    <i class="fas fa-boxes"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $supplier['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

    <!-- Supplier Modal -->
    <div class="modal fade" id="supplierModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="supplierForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create_supplier">
                        <input type="hidden" name="id" id="modalId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="company_name" class="form-label">Company Name *</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="country" class="form-label">Country</label>
                                <input type="text" class="form-control" id="country" name="country" value="Sri Lanka">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="tax_id" class="form-label">Tax ID</label>
                                <input type="text" class="form-control" id="tax_id" name="tax_id">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="payment_terms" class="form-label">Payment Terms</label>
                                <select class="form-select" id="payment_terms" name="payment_terms">
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="Net 7">Net 7</option>
                                    <option value="Net 15">Net 15</option>
                                    <option value="Net 30" selected>Net 30</option>
                                    <option value="Net 45">Net 45</option>
                                    <option value="Net 60">Net 60</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="blocked">Blocked</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Supplier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="supplierDeleteForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_supplier">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Are you sure you want to delete this supplier? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crud-ajax.js"></script>
    <script src="assets/js/universal-crud.js"></script>
    <script>
        function openModal(action, supplier = null) {
            const modal = new bootstrap.Modal(document.getElementById('supplierModal'));
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('modalAction');
            const idInput = document.getElementById('modalId');
            
            if (action === 'create') {
                title.textContent = 'Add Supplier';
                actionInput.value = 'create_supplier';
                idInput.value = '';
                document.getElementById('supplierModal').querySelector('form').reset();
            } else if (action === 'edit' && supplier) {
                title.textContent = 'Edit Supplier';
                actionInput.value = 'update_supplier';
                idInput.value = supplier.id;
                
                document.getElementById('company_name').value = supplier.company_name;
                document.getElementById('contact_person').value = supplier.contact_person || '';
                document.getElementById('email').value = supplier.email || '';
                document.getElementById('phone').value = supplier.phone || '';
                document.getElementById('address').value = supplier.address || '';
                document.getElementById('city').value = supplier.city || '';
                document.getElementById('country').value = supplier.country || '';
                document.getElementById('tax_id').value = supplier.tax_id || '';
                document.getElementById('payment_terms').value = supplier.payment_terms || 'Net 30';
                document.getElementById('notes').value = supplier.notes || '';
                document.getElementById('status').value = supplier.status;
            }
            
            modal.show();
        }
        
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // AJAX submit for supplier create/update/delete
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('supplierForm');
            const deleteForm = document.getElementById('supplierDeleteForm');
            
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const fd = new FormData(form);
                    fd.append('table', 'suppliers');
                    const res = await fetch('ajax_handler.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('supplierModal'));
                        if (modal) modal.hide();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            }
            if (deleteForm) {
                deleteForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const fd = new FormData(deleteForm);
                    fd.append('table', 'suppliers');
                    const res = await fetch('ajax_handler.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                        if (modal) modal.hide();
                        setTimeout(() => location.reload(), 800);
                    } else {
                        showToast(data.message, 'error');
                    }
                });
            }
        });
    </script>
</body>
</html>

