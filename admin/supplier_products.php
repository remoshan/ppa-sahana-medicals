<?php
session_start();
include '../config/database.php';
include '../config/functions.php';
include 'auth_check.php';

$supplier_id = $_GET['supplier_id'] ?? 0;
$message = '';
$error_message = '';

// Get supplier details
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    header('Location: suppliers.php');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_product':
                $stmt = $pdo->prepare("
                    INSERT INTO supplier_products (supplier_id, medicine_id, supplier_price, minimum_order_quantity, lead_time_days, is_preferred, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        supplier_price = VALUES(supplier_price),
                        minimum_order_quantity = VALUES(minimum_order_quantity),
                        lead_time_days = VALUES(lead_time_days),
                        is_preferred = VALUES(is_preferred),
                        status = VALUES(status)
                ");
                $stmt->execute([
                    $supplier_id,
                    $_POST['medicine_id'],
                    $_POST['supplier_price'],
                    $_POST['minimum_order_quantity'],
                    $_POST['lead_time_days'],
                    isset($_POST['is_preferred']) ? 1 : 0,
                    $_POST['status']
                ]);
                $message = 'Product added successfully!';
                break;
                
            case 'remove_product':
                $stmt = $pdo->prepare("DELETE FROM supplier_products WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = 'Product removed successfully!';
                break;
        }
    } catch (PDOException $e) {
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get supplier products
$stmt = $pdo->prepare("
    SELECT sp.*, m.name as medicine_name, m.price as retail_price, m.quantity as current_stock, c.name as category_name
    FROM supplier_products sp
    JOIN medicines m ON sp.medicine_id = m.id
    LEFT JOIN categories c ON m.category_id = c.id
    WHERE sp.supplier_id = ?
    ORDER BY m.name ASC
");
$stmt->execute([$supplier_id]);
$products = $stmt->fetchAll();

// Get all medicines for adding
$stmt = $pdo->query("SELECT id, name, price FROM medicines ORDER BY name ASC");
$all_medicines = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Products - <?php echo htmlspecialchars($supplier['company_name']); ?></title>
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
                            <h2 class="h3 mb-1"><?php echo htmlspecialchars($supplier['company_name']); ?></h2>
                            <p class="text-muted mb-0">Manage products supplied by this vendor</p>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="suppliers.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Back to Suppliers
                            </a>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                <i class="fas fa-plus me-2"></i>Add Product
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Category</th>
                                        <th>Current Stock</th>
                                        <th>Retail Price</th>
                                        <th>Supplier Price</th>
                                        <th>Margin</th>
                                        <th>Min Order Qty</th>
                                        <th>Lead Time</th>
                                        <th>Preferred</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): 
                                        $margin = (($product['retail_price'] - $product['supplier_price']) / $product['supplier_price']) * 100;
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($product['medicine_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['current_stock'] < 50 ? 'danger' : 'success'; ?>">
                                                <?php echo $product['current_stock']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo formatPrice($product['retail_price']); ?></td>
                                        <td><?php echo formatPrice($product['supplier_price']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $margin > 30 ? 'success' : 'warning'; ?>">
                                                <?php echo number_format($margin, 1); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo $product['minimum_order_quantity']; ?></td>
                                        <td><?php echo $product['lead_time_days']; ?> days</td>
                                        <td>
                                            <?php if ($product['is_preferred']): ?>
                                                <i class="fas fa-star text-warning"></i>
                                            <?php else: ?>
                                                <i class="far fa-star text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($product['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this product?');">
                                                <input type="hidden" name="action" value="remove_product">
                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
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

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add Product to Supplier</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_product">
                        
                        <div class="mb-3">
                            <label for="medicine_id" class="form-label">Medicine *</label>
                            <select class="form-select" id="medicine_id" name="medicine_id" required>
                                <option value="">Select Medicine</option>
                                <?php foreach ($all_medicines as $med): ?>
                                <option value="<?php echo $med['id']; ?>" data-price="<?php echo $med['price']; ?>">
                                    <?php echo htmlspecialchars($med['name']); ?> - <?php echo formatPrice($med['price']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="supplier_price" class="form-label">Supplier Price *</label>
                            <input type="number" class="form-control" id="supplier_price" name="supplier_price" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="minimum_order_quantity" class="form-label">Minimum Order Quantity *</label>
                            <input type="number" class="form-control" id="minimum_order_quantity" name="minimum_order_quantity" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lead_time_days" class="form-label">Lead Time (Days) *</label>
                            <input type="number" class="form-control" id="lead_time_days" name="lead_time_days" value="7" required>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_preferred" name="is_preferred">
                            <label class="form-check-label" for="is_preferred">
                                Mark as preferred supplier for this product
                            </label>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" selected>Active</option>
                                <option value="discontinued">Discontinued</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crud-ajax.js"></script>
    <script src="assets/js/universal-crud.js"></script>
    <script>
        // Auto-calculate supplier price suggestion based on retail price
        document.getElementById('medicine_id').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            if (selected.value) {
                const retailPrice = parseFloat(selected.dataset.price);
                const suggestedPrice = (retailPrice * 0.65).toFixed(2); // Suggest 65% of retail
                document.getElementById('supplier_price').value = suggestedPrice;
            }
        });
    </script>
</body>
</html>

