<?php
session_start();
include 'config/database.php';

// Get categories with medicine counts
$search = $_GET['search'] ?? '';

$where_conditions = ['c.status = "active"'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT c.*, COUNT(m.id) as medicine_count 
    FROM categories c 
    LEFT JOIN medicines m ON c.id = m.category_id AND m.status = 'active'
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
    <title>Medicine Categories - Sahana Medicals</title>
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
                        <a class="nav-link" href="medicines.php">Medicines</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="categories.php">Categories</a>
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
            <h1>Medicine Categories</h1>
            <p>Browse medicines by category for easy navigation</p>
        </div>
    </section>

    <!-- Search -->
    <section class="py-4 bg-white">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <form method="GET" class="d-flex">
                        <input type="text" class="form-control me-2" name="search" placeholder="Search categories..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Grid -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4">
                <?php foreach ($categories as $category): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($category['name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="card-text text-muted">
                                <?php echo htmlspecialchars($category['description'] ?? 'No description available.'); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="badge bg-info"><?php echo $category['medicine_count']; ?> medicines</span>
                                </div>
                                <a href="medicines.php?category=<?php echo $category['id']; ?>" class="btn btn-secondary-hero btn-sm">
                                    View Medicines
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($categories)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-tags text-muted mb-3 icon-4xl"></i>
                        <h4 class="text-muted">No categories found</h4>
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
