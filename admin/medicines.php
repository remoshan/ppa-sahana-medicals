<?php
session_start();
include '../config/database.php';
include '../config/functions.php';

// Secure authentication check
include 'auth_check.php';

$message = '';
$error_message = '';

// Display messages from session
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Note: Form submissions are handled via AJAX (ajax_handler.php) to prevent double submissions

// Get medicines with category names
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(m.name LIKE ? OR m.manufacturer LIKE ? OR m.batch_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'low_stock') {
    $where_conditions[] = "m.quantity <= 10";
}

if ($filter === 'expired') {
    $where_conditions[] = "m.expiry_date < CURDATE()";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT m.*, c.name as category_name 
    FROM medicines m 
    LEFT JOIN categories c ON m.category_id = c.id 
    $where_clause
    ORDER BY m.name ASC
");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Medicines Management</h2>
                            <p class="text-muted mb-0">Manage your medicine inventory</p>
                        </div>
                        <div>
                            <a href="reports.php?type=medicines" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#medicineModal" onclick="openModal('create')">
                                <i class="fas fa-plus me-2"></i>Add Medicine
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

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search medicines..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <a href="?filter=low_stock" class="btn btn-outline-warning btn-sm">Low Stock</a>
                            <a href="?filter=expired" class="btn btn-outline-danger btn-sm">Expired</a>
                            <a href="?" class="btn btn-outline-secondary btn-sm">All</a>
                        </div>
                    </div>
                </div>

                <!-- Medicines Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Manufacturer</th>
                                        <th>Price</th>
                                        <th>Stock</th>
                                        <th>Expiry</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicines as $medicine): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                                <?php if ($medicine['prescription_required']): ?>
                                                    <span class="badge bg-warning ms-2">Rx</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($medicine['batch_number']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($medicine['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td><?php echo htmlspecialchars($medicine['manufacturer']); ?></td>
                                        <td><?php echo formatPrice($medicine['price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $medicine['quantity'] <= 10 ? 'danger' : ($medicine['quantity'] <= 50 ? 'warning' : 'success'); ?>">
                                                <?php echo $medicine['quantity']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($medicine['expiry_date']): ?>
                                                <?php
                                                $expiry_date = new DateTime($medicine['expiry_date']);
                                                $today = new DateTime();
                                                $days_left = $today->diff($expiry_date)->days;
                                                $is_expired = $expiry_date < $today;
                                                ?>
                                                <span class="badge bg-<?php echo $is_expired ? 'danger' : ($days_left <= 30 ? 'warning' : 'success'); ?>">
                                                    <?php echo date('M d, Y', strtotime($medicine['expiry_date'])); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $medicine['status'] === 'active' ? 'success' : ($medicine['status'] === 'inactive' ? 'secondary' : 'danger'); ?>">
                                                <?php echo ucfirst($medicine['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($medicine)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $medicine['id']; ?>)">
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

    <!-- Medicine Modal -->
    <div class="modal fade" id="medicineModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="medicineForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Medicine</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        <input type="hidden" name="image_url" id="image_url" value="">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Medicine Name *</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="manufacturer" class="form-label">Manufacturer *</label>
                                <input type="text" class="form-control" id="manufacturer" name="manufacturer" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="batch_number" class="form-label">Batch Number *</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="batch_number" name="batch_number" readonly required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateBatchNumber()" title="Generate Batch Number">
                                        <i class="fas fa-sync-alt"></i> Generate
                                    </button>
                                </div>
                                <small class="text-muted">Auto-generated unique batch number</small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="price" class="form-label">Price *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="expiry_date" class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" id="expiry_date" name="expiry_date">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="discontinued">Discontinued</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="prescription_required" name="prescription_required">
                                    <label class="form-check-label" for="prescription_required">
                                        Prescription Required
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Medicine</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deleteForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Are you sure you want to delete this medicine? This action cannot be undone.</p>
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
        // Initialize AJAX form handling
        document.addEventListener('DOMContentLoaded', function() {
            const medicineForm = document.getElementById('medicineForm');
            const deleteForm = document.getElementById('deleteForm');
            
            let isSubmitting = false; // Prevent double submission
            
            // Handle medicine form (create/update)
            medicineForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent double submission
                if (isSubmitting) {
                    return false;
                }
                isSubmitting = true;
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                
                const formData = new FormData(this);
                formData.append('table', 'medicines');
                
                try {
                    const response = await fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('medicineModal'));
                        modal.hide();
                        setTimeout(() => {
                            window.location.href = 'medicines.php';
                        }, 1000);
                    } else {
                        showToast(data.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        isSubmitting = false;
                    }
                } catch (error) {
                    showToast('An error occurred. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    isSubmitting = false;
                }
            });
            
            // Handle delete form
            deleteForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                // Prevent double submission
                if (isSubmitting) {
                    return false;
                }
                isSubmitting = true;
                
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting...';
                
                const formData = new FormData(this);
                formData.append('table', 'medicines');
                
                try {
                    const response = await fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                        modal.hide();
                        setTimeout(() => {
                            window.location.href = 'medicines.php';
                        }, 1000);
                    } else {
                        showToast(data.message, 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        isSubmitting = false;
                    }
                } catch (error) {
                    showToast('An error occurred. Please try again.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    isSubmitting = false;
                }
            });
            
            // Reset submission flag when modal is closed
            document.getElementById('medicineModal').addEventListener('hidden.bs.modal', function() {
                isSubmitting = false;
            });
            
            document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function() {
                isSubmitting = false;
            });
        });
        
        function generateBatchNumber() {
            // Generate simple batch number in format: B + 6 random digits
            // Example: B834729, B102847, B456123
            const random = String(Math.floor(Math.random() * 1000000)).padStart(6, '0');
            const batchNumber = `B${random}`;
            document.getElementById('batch_number').value = batchNumber;
            
            return batchNumber;
        }
        
        function openModal(action, medicine = null) {
            const modal = new bootstrap.Modal(document.getElementById('medicineModal'));
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('modalAction');
            const idInput = document.getElementById('modalId');
            
            if (action === 'create') {
                title.textContent = 'Add Medicine';
                actionInput.value = 'create';
                idInput.value = '';
                document.getElementById('medicineModal').querySelector('form').reset();
                // Auto-generate batch number for new medicine
                generateBatchNumber();
            } else if (action === 'edit' && medicine) {
                title.textContent = 'Edit Medicine';
                actionInput.value = 'update';
                idInput.value = medicine.id;
                
                // Fill form with medicine data
                document.getElementById('name').value = medicine.name;
                document.getElementById('description').value = medicine.description || '';
                document.getElementById('category_id').value = medicine.category_id || '';
                document.getElementById('manufacturer').value = medicine.manufacturer;
                document.getElementById('batch_number').value = medicine.batch_number;
                document.getElementById('price').value = medicine.price;
                document.getElementById('quantity').value = medicine.quantity;
                document.getElementById('expiry_date').value = medicine.expiry_date || '';
                document.getElementById('status').value = medicine.status;
                document.getElementById('prescription_required').checked = medicine.prescription_required == 1;
            }
            
            modal.show();
        }
        
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
