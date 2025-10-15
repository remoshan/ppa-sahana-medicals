<?php
session_start();
include 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sahana Medicals - Your Trusted Pharmacy</title>
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="medicines.php">Medicines</a>
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="floating-shape"></div>
        <div class="floating-shape"></div>
        <div class="container">
            <div class="hero-content">
                <h1>Your Health, Our Priority</h1>
                <p>Sahana Medicals provides quality medicines and healthcare products with professional service and care.</p>
                <div class="hero-buttons">
                    <a href="medicines.php" class="btn btn-primary-hero btn-hero">Browse Medicines</a>
                    <a href="contact.php" class="btn btn-secondary-hero btn-hero">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-title">
                <h2>Why Choose Sahana Medicals?</h2>
                <p>We are committed to providing the best healthcare services</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Authentic Medicines</h4>
                        <p>We provide only genuine and certified medicines from trusted manufacturers.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4>24/7 Availability</h4>
                        <p>Emergency medicines available round the clock to serve your urgent needs.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <h4>Expert Consultation</h4>
                        <p>Get professional advice from our qualified pharmacists and healthcare experts.</p>
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
                    <h5>
                        <i class="fas fa-pills me-2"></i>Sahana Medicals
                    </h5>
                    <p>Your trusted partner in health and wellness. We provide quality medicines and healthcare services with care and professionalism.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h6>Quick Links</h6>
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
                <p>&copy; 2024 Sahana Medicals. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
