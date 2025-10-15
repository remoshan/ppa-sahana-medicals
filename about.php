<?php
session_start();
include 'config/database.php';

// Get some statistics for the about page
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicines WHERE status = 'active'");
    $total_medicines = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM categories WHERE status = 'active'");
    $total_categories = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers WHERE status = 'active'");
    $total_customers = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM staff WHERE status = 'active'");
    $total_staff = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $total_medicines = $total_categories = $total_customers = $total_staff = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Sahana Medicals</title>
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
                        <a class="nav-link" href="categories.php">Categories</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="about.php">About Us</a>
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
            <h1>About Sahana Medicals</h1>
            <p>Your trusted healthcare partner since 2024</p>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($total_medicines); ?>+</div>
                        <div class="stat-label">Medicines Available</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($total_categories); ?>+</div>
                        <div class="stat-label">Medicine Categories</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($total_customers); ?>+</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($total_staff); ?>+</div>
                        <div class="stat-label">Expert Staff</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Content -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6">
                    <h2 class="fw-bold text-primary mb-4">Our Story</h2>
                    <p class="lead text-muted mb-4">
                        Founded in 2024, Sahana Medicals has been dedicated to providing quality healthcare services and authentic medicines to our community. Our mission is to make healthcare accessible, affordable, and reliable for everyone.
                    </p>
                    <p class="text-muted mb-4">
                        We understand that health is the most precious asset, and we are committed to ensuring that our customers receive the best possible care and medication. Our team of qualified pharmacists and healthcare professionals work tirelessly to maintain the highest standards of service.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="medicines.php" class="btn btn-primary-hero">Browse Medicines</a>
                        <a href="contact.php" class="btn btn-secondary-hero">Contact Us</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="fas fa-heartbeat text-primary mb-3 icon-6xl icon-muted"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center mb-5">
                    <h2 class="fw-bold text-primary">Our Values</h2>
                    <p class="lead text-muted">The principles that guide everything we do</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5 class="card-title">Quality & Safety</h5>
                            <p class="card-text">We ensure all our medicines are genuine, properly stored, and meet the highest quality standards.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h5 class="card-title">Compassionate Care</h5>
                            <p class="card-text">We treat every customer with empathy, respect, and understanding of their healthcare needs.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-body text-center p-4">
                            <div class="stat-icon mb-3">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <h5 class="card-title">Expert Knowledge</h5>
                            <p class="card-text">Our team consists of qualified pharmacists with extensive knowledge in healthcare and medicine.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Call to Action -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card text-center">
                        <div class="card-header">
                            <h3 class="mb-0">Ready to Experience Quality Healthcare?</h3>
                        </div>
                        <div class="card-body p-5">
                            <p class="lead mb-4">Join thousands of satisfied customers who trust Sahana Medicals for their healthcare needs.</p>
                            <div class="d-flex gap-3 justify-content-center">
                                <a href="auth.php?mode=register" class="btn btn-primary-hero btn-lg">Register Now</a>
                                <a href="contact.php" class="btn btn-secondary-hero btn-lg">Get in Touch</a>
                            </div>
                        </div>
                    </div>
                </div>
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
