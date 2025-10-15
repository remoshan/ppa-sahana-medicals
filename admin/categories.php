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
    
    try {
        switch ($action) {
            case 'create':
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['name'], $_POST['description'], $_POST['status']]);
                $message = 'Category added successfully!';
                break;
                
            case 'update':
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['description'], $_POST['status'], $_POST['id']]);
                $message = 'Category updated successfully!';
                break;
                
            case 'delete':
                // Check if category has medicines
                $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE category_id = ?");
                $check_stmt->execute([$_POST['id']]);
                $count = $check_stmt->fetch()['count'];
                
                if ($count > 0) {
                    $error_message = 'Cannot delete category. It contains ' . $count . ' medicine(s).';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = 'Category deleted successfully!';
                }
                break;
        }
    } catch (PDOException $e) {
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get categories with medicine counts
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = 'WHERE c.name LIKE ? OR c.description LIKE ?';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(m.id) as medicine_count 
    FROM categories c 
    LEFT JOIN medicines m ON c.id = m.category_id 
    $where_clause
    GROUP BY c.id 
    ORDER BY c.name ASC
");
$stmt->execute($params);
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories Management - Sahana Medicals</title>
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
                            <h2 class="h3 mb-1">Categories Management</h2>
                            <p class="text-muted mb-0">Organize medicines by categories</p>
                        </div>
                        <div>
                            <a href="reports.php?type=categories" class="btn btn-outline-success me-2">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openModal('create')">
                                <i class="fas fa-plus me-2"></i>Add Category
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

                <!-- Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Categories Grid -->
                <div class="row g-4">
                    <?php foreach ($categories as $category): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-tag me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h5>
                                <span class="badge bg-<?php echo $category['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($category['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars($category['description'] ?? 'No description provided'); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-pills me-1"></i>
                                        <?php echo $category['medicine_count']; ?> medicine(s)
                                    </small>
                                    <small class="text-muted">
                                        Created: <?php echo date('M d, Y', strtotime($category['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="openModal('edit', <?php echo htmlspecialchars(json_encode($category)); ?>)">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['medicine_count']; ?>)">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($categories)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-tags text-muted mb-3" style="font-size: 4rem;"></i>
                            <h4 class="text-muted">No categories found</h4>
                            <p class="text-muted">Start by adding your first category.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Category Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="categoryForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="modalAction" value="create">
                        <input type="hidden" name="id" id="modalId">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe what medicines belong to this category..."></textarea>
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
                        <button type="submit" class="btn btn-primary">Save Category</button>
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
        // Initialize AJAX form handling
        document.addEventListener('DOMContentLoaded', function() {
            const categoryForm = document.querySelector('#categoryModal form');
            const deleteForm = document.querySelector('#deleteModal form');
            
            // Handle category form (create/update)
            categoryForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('table', 'categories');
                
                try {
                    const response = await fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
                        modal.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('An error occurred. Please try again.', 'error');
                }
            });
            
            // Handle delete form
            deleteForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('table', 'categories');
                
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
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    showToast('An error occurred. Please try again.', 'error');
                }
            });
        });
        
        function openModal(action, category = null) {
            const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            const title = document.getElementById('modalTitle');
            const actionInput = document.getElementById('modalAction');
            const idInput = document.getElementById('modalId');
            
            if (action === 'create') {
                title.textContent = 'Add Category';
                actionInput.value = 'create';
                idInput.value = '';
                document.getElementById('categoryModal').querySelector('form').reset();
            } else if (action === 'edit' && category) {
                title.textContent = 'Edit Category';
                actionInput.value = 'update';
                idInput.value = category.id;
                
                // Fill form with category data
                document.getElementById('name').value = category.name;
                document.getElementById('description').value = category.description || '';
                document.getElementById('status').value = category.status;
            }
            
            modal.show();
        }
        
        function confirmDelete(id, name, medicineCount) {
            document.getElementById('deleteId').value = id;
            const message = document.getElementById('deleteMessage');
            
            if (medicineCount > 0) {
                message.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cannot delete category "<strong>${name}</strong>" because it contains ${medicineCount} medicine(s).
                    </div>
                `;
                document.querySelector('#deleteModal .btn-danger').style.display = 'none';
            } else {
                message.innerHTML = `
                    <p>Are you sure you want to delete the category "<strong>${name}</strong>"? This action cannot be undone.</p>
                `;
                document.querySelector('#deleteModal .btn-danger').style.display = 'block';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
    </script>
</body>
</html>
