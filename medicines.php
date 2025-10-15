<?php
session_start();
include 'config/database.php';
include 'config/functions.php';

// Get medicines with category names
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$where_conditions = ['m.status = "active"'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(m.name LIKE ? OR m.manufacturer LIKE ? OR m.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "m.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT m.*, c.name as category_name 
    FROM medicines m 
    LEFT JOIN categories c ON m.category_id = c.id 
    $where_clause
    ORDER BY m.name ASC
");
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// Get categories for filter
$stmt = $pdo->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name ASC");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-pills me-2"></i>Sahana Medicals
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="medicines.php">Medicines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="cart.php">
                                <i class="fas fa-shopping-cart"></i>
                                <?php 
                                $cart_count = isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0;
                                if ($cart_count > 0): 
                                ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        <?php echo $cart_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                                <li><a class="dropdown-item" href="my_orders.php"><i class="fas fa-shopping-cart me-2"></i>My Orders</a></li>
                                <li><a class="dropdown-item" href="submit_prescription.php"><i class="fas fa-prescription me-2"></i>Submit Prescription</a></li>
                                <li><a class="dropdown-item" href="my_prescriptions.php"><i class="fas fa-clipboard-list me-2"></i>My Prescriptions</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item auth-buttons-group">
                            <a class="nav-link auth-btn-secondary" href="auth.php?mode=register">Register</a>
                            <a class="nav-link auth-btn" href="auth.php?mode=login">Login</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/login.php"><i class="fas fa-user-shield me-1"></i>Admin</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <h1>Our Medicines</h1>
            <p>Quality medicines for your health and wellness</p>
        </div>
    </section>

    <!-- Search and Filter -->
    <section class="py-4 bg-white">
        <div class="container">
            <div class="row g-3">
                <div class="col-md-6">
                    <form method="GET" class="d-flex">
                        <input type="text" class="form-control me-2" name="search" placeholder="Search medicines..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <form method="GET" class="d-flex">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <select class="form-select me-2" name="category" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($category_filter)): ?>
                            <a href="?" class="btn btn-outline-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Medicines Grid -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <?php foreach ($medicines as $medicine): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php echo htmlspecialchars($medicine['name']); ?>
                                <?php if ($medicine['prescription_required']): ?>
                                    <span class="badge bg-warning ms-2">Prescription Required</span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-2">
                                <small class="text-muted">Category:</small>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($medicine['category_name'] ?? 'Uncategorized'); ?></span>
                            </div>
                            
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($medicine['description']); ?>
                            </p>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <small class="text-muted">Manufacturer</small>
                                    <div class="fw-medium"><?php echo htmlspecialchars($medicine['manufacturer']); ?></div>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Batch</small>
                                    <div class="fw-medium"><?php echo htmlspecialchars($medicine['batch_number']); ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="h5 text-primary mb-0"><?php echo formatPrice($medicine['price']); ?></span>
                                </div>
                                <div class="text-end">
                                    <small class="text-muted">Stock</small>
                                    <div class="badge bg-<?php echo $medicine['quantity'] <= 10 ? 'danger' : ($medicine['quantity'] <= 50 ? 'warning' : 'success'); ?>">
                                        <?php echo $medicine['quantity']; ?> available
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="d-grid">
                                <?php if ($medicine['quantity'] > 0): ?>
                                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
                                        <form method="POST" action="add_to_cart.php" class="d-flex gap-2">
                                            <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                                            <input type="number" name="quantity" value="1" min="1" max="<?php echo $medicine['quantity']; ?>" class="form-control w-80">
                                            <button type="submit" class="btn btn-primary-hero flex-grow-1">
                                                <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="auth.php?mode=login&redirect=medicines.php" class="btn btn-primary-hero">
                                            <i class="fas fa-sign-in-alt me-2"></i>Login to Order
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="fas fa-times-circle me-2"></i>Out of Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($medicines)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-pills text-muted mb-3 icon-4xl"></i>
                        <h4 class="text-muted">No medicines found</h4>
                        <p class="text-muted">Try adjusting your search criteria.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>
                        <i class="fas fa-pills me-2"></i>Sahana Medicals
                    </h4>
                    <p>Your trusted partner in health and wellness. We provide quality medicines and healthcare services with care and professionalism.</p>
                    <div class="social-links">
                        <a href="#" class="text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h5>Quick Links</h5>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="medicines.php">Medicines</a></li>
                        <li><a href="categories.php">Categories</a></li>
                        <li><a href="about.php">About Us</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Contact Info</h5>
                    <ul>
                        <li><i class="fas fa-map-marker-alt me-2"></i>QWWP+45C, Colombo - Horana Rd, Piliyandala</li>
                        <li><i class="fas fa-phone me-2"></i>0112702329</li>
                        <li><i class="fas fa-envelope me-2"></i>sahanamedicalenterprises@gmail.com</li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h5>Business Hours</h5>
                    <ul>
                        <li>Monday - Friday: 8:00 AM - 10:00 PM</li>
                        <li>Saturday: 9:00 AM - 8:00 PM</li>
                        <li>Sunday: 10:00 AM - 6:00 PM</li>
                        <li>Emergency: 24/7</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <p class="mb-0">&copy; 2024 Sahana Medicals. All rights reserved.</p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <small>
                            <a href="#" class="me-3">Privacy Policy</a>
                            <a href="#">Terms of Service</a>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
