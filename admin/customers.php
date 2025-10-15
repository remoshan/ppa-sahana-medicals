<?php
session_start();
include '../config/database.php';

// Secure authentication check
include 'auth_check.php';

$message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Validate phone number if provided
    $phone = trim($_POST['phone'] ?? '');
    if (!empty($phone) && (strlen($phone) !== 10 || !ctype_digit($phone))) {
        $error_message = 'Phone number must be exactly 10 digits.';
    } else {
        try {
            switch ($action) {
                case 'create':
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (first_name, last_name, email, phone, address, date_of_birth, gender, emergency_contact, medical_history, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $phone,
                        $_POST['address'],
                        $_POST['date_of_birth'] ?: null,
                        $_POST['gender'] ?: null,
                        $_POST['emergency_contact'],
                        $_POST['medical_history'],
                        $_POST['status']
                    ]);
                    $message = 'Customer added successfully!';
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("
                        UPDATE customers 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, emergency_contact = ?, medical_history = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $phone,
                        $_POST['address'],
                        $_POST['date_of_birth'] ?: null,
                        $_POST['gender'] ?: null,
                        $_POST['emergency_contact'],
                        $_POST['medical_history'],
                        $_POST['status'],
                        $_POST['id']
                    ]);
                    $message = 'Customer updated successfully!';
                    break;
                
                case 'delete':
                    // Check if customer has orders
                    $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
                    $check_stmt->execute([$_POST['id']]);
                    $count = $check_stmt->fetch()['count'];
                    
                    if ($count > 0) {
                        $error_message = 'Cannot delete customer. They have ' . $count . ' order(s) in the system.';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                        $stmt->execute([$_POST['id']]);
                        $message = 'Customer deleted successfully!';
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error_message = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get customers with order counts
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'active') {
    $where_conditions[] = "c.status = 'active'";
} elseif ($filter === 'inactive') {
    $where_conditions[] = "c.status = 'inactive'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(o.id) as order_count 
    FROM customers c 
    LEFT JOIN orders o ON c.id = o.customer_id 
    $where_clause
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Customers Management</h2>
                            <p class="text-muted mb-0">Manage customer information and records</p>
                        </div>
                        <div>
                            <a href="reports.php?type=customers" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="openModal('create')">
                                <i class="fas fa-plus me-2"></i>Add Customer
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
                            <input type="text" class="form-control me-2" name="search" placeholder="Search customers..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <a href="?filter=active" class="btn btn-outline-success btn-sm">Active</a>
                            <a href="?filter=inactive" class="btn btn-outline-secondary btn-sm">Inactive</a>
                            <a href="?" class="btn btn-outline-primary btn-sm">All</a>
                        </div>
                    </div>
                </div>

                <!-- Customers Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Customer</th>
                                        <th>Contact</th>
                                        <th>Gender</th>
                                        <th>Age</th>
                                        <th>Orders</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $customer['id']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php if ($customer['email']): ?>
                                                    <i class="fas fa-envelope me-1 text-muted"></i>
                                                    <?php echo htmlspecialchars($customer['email']); ?><br>
                                                <?php endif; ?>
                                                <?php if ($customer['phone']): ?>
                                                    <i class="fas fa-phone me-1 text-muted"></i>
                                                    <?php echo htmlspecialchars($customer['phone']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($customer['gender']): ?>
                                                <span class="badge bg-<?php echo $customer['gender'] === 'male' ? 'primary' : ($customer['gender'] === 'female' ? 'success' : 'secondary'); ?>">
                                                    <?php echo ucfirst($customer['gender']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['date_of_birth']): ?>
                                                <?php
                                                $birth_date = new DateTime($customer['date_of_birth']);
                                                $today = new DateTime();
                                                $age = $today->diff($birth_date)->y;
                                                ?>
                                                <?php echo $age; ?> years
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $customer['order_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($customer['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-info" onclick="viewCustomerDetails(<?php echo htmlspecialchars(json_encode($customer)); ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>', <?php echo $customer['order_count']; ?>)">
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

    <!-- Customer Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       pattern="[0-9]{10}" maxlength="10" 
                                       title="Phone number must be exactly 10 digits">
                                <small class="form-text text-muted">Enter exactly 10 digits (e.g., 0771234567)</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="medical_history" class="form-label">Medical History</label>
                            <textarea class="form-control" id="medical_history" name="medical_history" rows="3" placeholder="Known allergies, chronic conditions, etc..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Customer Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="customerDetailsContent">
                    <!-- Customer details will be loaded here -->
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
                        <div id="deleteMessage"></div>
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
        function openModal(action, customer = null) {
            const modal = new bootstrap.Modal(document.getElementById('customerModal'));
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('modalAction');
            const idInput = document.getElementById('modalId');
            
            if (action === 'create') {
                title.textContent = 'Add Customer';
                actionInput.value = 'create';
                idInput.value = '';
                document.getElementById('customerModal').querySelector('form').reset();
            } else if (action === 'edit' && customer) {
                title.textContent = 'Edit Customer';
                actionInput.value = 'update';
                idInput.value = customer.id;
                
                // Fill form with customer data
                document.getElementById('first_name').value = customer.first_name;
                document.getElementById('last_name').value = customer.last_name;
                document.getElementById('email').value = customer.email || '';
                document.getElementById('phone').value = customer.phone || '';
                document.getElementById('address').value = customer.address || '';
                document.getElementById('date_of_birth').value = customer.date_of_birth || '';
                document.getElementById('gender').value = customer.gender || '';
                document.getElementById('emergency_contact').value = customer.emergency_contact || '';
                document.getElementById('medical_history').value = customer.medical_history || '';
                document.getElementById('status').value = customer.status;
            }
            
            modal.show();
        }
        
        function viewCustomerDetails(customer) {
            const modal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
            const content = document.getElementById('customerDetailsContent');
            
            const age = customer.date_of_birth ? 
                Math.floor((new Date() - new Date(customer.date_of_birth)) / (365.25 * 24 * 60 * 60 * 1000)) : 'N/A';
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Name:</strong> ${customer.first_name} ${customer.last_name}</p>
                        <p><strong>Gender:</strong> ${customer.gender ? customer.gender.charAt(0).toUpperCase() + customer.gender.slice(1) : 'N/A'}</p>
                        <p><strong>Age:</strong> ${age} ${age !== 'N/A' ? 'years' : ''}</p>
                        <p><strong>Date of Birth:</strong> ${customer.date_of_birth ? new Date(customer.date_of_birth).toLocaleDateString() : 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Contact Information</h6>
                        <p><strong>Email:</strong> ${customer.email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${customer.phone || 'N/A'}</p>
                        <p><strong>Emergency Contact:</strong> ${customer.emergency_contact || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${customer.status === 'active' ? 'success' : 'secondary'}">${customer.status.charAt(0).toUpperCase() + customer.status.slice(1)}</span></p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Address</h6>
                        <p>${customer.address || 'No address provided'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Medical History</h6>
                        <p>${customer.medical_history || 'No medical history recorded'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Account Information</h6>
                        <p><strong>Customer ID:</strong> ${customer.id}</p>
                        <p><strong>Member Since:</strong> ${new Date(customer.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
            `;
            
            modal.show();
        }
        
        function confirmDelete(id, name, orderCount) {
            document.getElementById('deleteId').value = id;
            const message = document.getElementById('deleteMessage');
            
            if (orderCount > 0) {
                message.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cannot delete customer "<strong>${name}</strong>" because they have ${orderCount} order(s) in the system.
                    </div>
                `;
                document.querySelector('#deleteModal .btn-danger').style.display = 'none';
            } else {
                message.innerHTML = `
                    <p>Are you sure you want to delete customer "<strong>${name}</strong>"? This action cannot be undone.</p>
                `;
                document.querySelector('#deleteModal .btn-danger').style.display = 'block';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
