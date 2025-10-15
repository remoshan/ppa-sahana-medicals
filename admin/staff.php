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
                        INSERT INTO staff (first_name, last_name, email, phone, address, position, department, hire_date, salary, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $phone,
                        $_POST['address'],
                        $_POST['position'],
                        $_POST['department'],
                        $_POST['hire_date'],
                        $_POST['salary'],
                        $_POST['status']
                    ]);
                    $message = 'Staff member added successfully!';
                    break;
                    
                case 'update':
                    $stmt = $pdo->prepare("
                        UPDATE staff 
                        SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, position = ?, department = ?, hire_date = ?, salary = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $_POST['first_name'],
                        $_POST['last_name'],
                        $_POST['email'],
                        $phone,
                        $_POST['address'],
                        $_POST['position'],
                        $_POST['department'],
                        $_POST['hire_date'],
                        $_POST['salary'],
                        $_POST['status'],
                        $_POST['id']
                    ]);
                    $message = 'Staff member updated successfully!';
                    break;
                
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = 'Staff member deleted successfully!';
                    break;
            }
        } catch (PDOException $e) {
            $error_message = 'Operation failed: ' . $e->getMessage();
        }
    }
}

// Get staff members
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.email LIKE ? OR s.position LIKE ? OR s.department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    $where_conditions[] = "s.status = ?";
    $params[] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT s.*, 
           TIMESTAMPDIFF(YEAR, s.hire_date, CURDATE()) as years_employed
    FROM staff s 
    $where_clause
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$staff = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Staff Management</h2>
                            <p class="text-muted mb-0">Manage staff members and employees</p>
                        </div>
                        <div>
                            <a href="reports.php?type=staff" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal" onclick="openModal('create')">
                                <i class="fas fa-plus me-2"></i>Add Staff Member
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
                            <input type="text" class="form-control me-2" name="search" placeholder="Search staff..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <a href="?filter=active" class="btn btn-outline-success btn-sm">Active</a>
                            <a href="?filter=inactive" class="btn btn-outline-secondary btn-sm">Inactive</a>
                            <a href="?filter=terminated" class="btn btn-outline-danger btn-sm">Terminated</a>
                            <a href="?" class="btn btn-outline-primary btn-sm">All</a>
                        </div>
                    </div>
                </div>

                <!-- Staff Grid -->
                <div class="row g-4">
                    <?php foreach ($staff as $member): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($member['position']); ?></small>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $member['status'] === 'active' ? 'success' : ($member['status'] === 'inactive' ? 'secondary' : 'danger'); ?>">
                                    <?php echo ucfirst($member['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Department</small>
                                        <div class="fw-medium"><?php echo htmlspecialchars($member['department']); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Years Employed</small>
                                        <div class="fw-medium"><?php echo $member['years_employed']; ?> years</div>
                                    </div>
                                </div>
                                
                                <div class="row g-2 mb-3">
                                    <div class="col-12">
                                        <small class="text-muted">Contact</small>
                                        <div>
                                            <?php if ($member['email']): ?>
                                                <i class="fas fa-envelope me-1 text-muted"></i>
                                                <small><?php echo htmlspecialchars($member['email']); ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($member['phone']): ?>
                                                <i class="fas fa-phone me-1 text-muted"></i>
                                                <small><?php echo htmlspecialchars($member['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-2">
                                    <div class="col-6">
                                        <small class="text-muted">Hire Date</small>
                                        <div class="fw-medium"><?php echo date('M d, Y', strtotime($member['hire_date'])); ?></div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Salary</small>
                                        <div class="fw-medium">$<?php echo number_format($member['salary'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($member)); ?>)">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="viewStaffDetails(<?php echo htmlspecialchars(json_encode($member)); ?>)">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?>')">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($staff)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-user-tie text-muted mb-3" style="font-size: 4rem;"></i>
                            <h4 class="text-muted">No staff members found</h4>
                            <p class="text-muted">Start by adding your first staff member.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Staff Modal -->
    <div class="modal fade" id="staffModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Staff Member</h5>
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
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" required>
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
                            <div class="col-md-6 mb-3">
                                <label for="position" class="form-label">Position *</label>
                                <input type="text" class="form-control" id="position" name="position" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department *</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Management">Management</option>
                                    <option value="Customer Service">Customer Service</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Finance">Finance</option>
                                    <option value="IT">IT</option>
                                    <option value="Human Resources">Human Resources</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="hire_date" class="form-label">Hire Date *</label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="salary" class="form-label">Salary *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Staff Member</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Staff Details Modal -->
    <div class="modal fade" id="staffDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Staff Member Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="staffDetailsContent">
                    <!-- Staff details will be loaded here -->
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
                        <p>Are you sure you want to delete staff member "<span id="deleteStaffName"></span>"? This action cannot be undone.</p>
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
        function openModal(action, staff = null) {
            const modal = new bootstrap.Modal(document.getElementById('staffModal'));
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('modalAction');
            const idInput = document.getElementById('modalId');
            
            if (action === 'create') {
                title.textContent = 'Add Staff Member';
                actionInput.value = 'create';
                idInput.value = '';
                document.getElementById('staffModal').querySelector('form').reset();
                document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
            } else if (action === 'edit' && staff) {
                title.textContent = 'Edit Staff Member';
                actionInput.value = 'update';
                idInput.value = staff.id;
                
                // Fill form with staff data
                document.getElementById('first_name').value = staff.first_name;
                document.getElementById('last_name').value = staff.last_name;
                document.getElementById('email').value = staff.email;
                document.getElementById('phone').value = staff.phone || '';
                document.getElementById('address').value = staff.address || '';
                document.getElementById('position').value = staff.position;
                document.getElementById('department').value = staff.department;
                document.getElementById('hire_date').value = staff.hire_date;
                document.getElementById('salary').value = staff.salary;
                document.getElementById('status').value = staff.status;
            }
            
            modal.show();
        }
        
        function viewStaffDetails(staff) {
            const modal = new bootstrap.Modal(document.getElementById('staffDetailsModal'));
            const content = document.getElementById('staffDetailsContent');
            
            const hireDate = new Date(staff.hire_date);
            const today = new Date();
            const yearsEmployed = Math.floor((today - hireDate) / (365.25 * 24 * 60 * 60 * 1000));
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Personal Information</h6>
                        <p><strong>Name:</strong> ${staff.first_name} ${staff.last_name}</p>
                        <p><strong>Email:</strong> ${staff.email}</p>
                        <p><strong>Phone:</strong> ${staff.phone || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="badge bg-${staff.status === 'active' ? 'success' : (staff.status === 'inactive' ? 'secondary' : 'danger')}">${staff.status.charAt(0).toUpperCase() + staff.status.slice(1)}</span></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Employment Information</h6>
                        <p><strong>Position:</strong> ${staff.position}</p>
                        <p><strong>Department:</strong> ${staff.department}</p>
                        <p><strong>Hire Date:</strong> ${hireDate.toLocaleDateString()}</p>
                        <p><strong>Years Employed:</strong> ${yearsEmployed} years</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Address</h6>
                        <p>${staff.address || 'No address provided'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Salary Information</h6>
                        <p><strong>Annual Salary:</strong> $${parseFloat(staff.salary).toLocaleString()}</p>
                        <p><strong>Monthly Salary:</strong> $${(parseFloat(staff.salary) / 12).toLocaleString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Account Information</h6>
                        <p><strong>Staff ID:</strong> ${staff.id}</p>
                        <p><strong>Added to System:</strong> ${new Date(staff.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
            `;
            
            modal.show();
        }
        
        function confirmDelete(id, name) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteStaffName').textContent = name;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
