<?php
session_start();
include 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: auth.php?mode=login');
    exit;
}

// Get user's prescriptions
$stmt = $pdo->prepare("
    SELECT *
    FROM prescriptions
    WHERE customer_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$prescriptions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Prescriptions - Sahana Medicals</title>
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
                        <a class="nav-link" href="about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
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
                            <li><a class="dropdown-item active" href="my_prescriptions.php"><i class="fas fa-clipboard-list me-2"></i>My Prescriptions</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
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
            <h1>My Prescriptions</h1>
            <p>View and manage your prescriptions</p>
        </div>
    </section>

    <!-- Prescriptions Content -->
    <section class="py-5">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row mb-4">
                <div class="col-12">
                    <a href="submit_prescription.php" class="btn btn-primary-hero">
                        <i class="fas fa-plus me-2"></i>Submit New Prescription
                    </a>
                </div>
            </div>

            <?php if (empty($prescriptions)): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="card text-center py-5">
                            <div class="card-body">
                                <i class="fas fa-file-medical text-muted mb-4 icon-5xl icon-muted"></i>
                                <h3 class="text-muted">No prescriptions yet</h3>
                                <p class="text-muted mb-4">Submit your first prescription to get started!</p>
                                <a href="submit_prescription.php" class="btn btn-primary-hero btn-lg">
                                    <i class="fas fa-file-medical me-2"></i>Submit Prescription
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($prescriptions as $prescription): 
                        // Determine status color and icon
                        $status_info = match($prescription['status']) {
                            'pending' => ['color' => 'warning', 'icon' => 'clock'],
                            'approved' => ['color' => 'success', 'icon' => 'check-circle'],
                            'rejected' => ['color' => 'danger', 'icon' => 'times-circle'],
                            'filled' => ['color' => 'success', 'icon' => 'check-double'],
                            'cancelled' => ['color' => 'secondary', 'icon' => 'ban'],
                            default => ['color' => 'secondary', 'icon' => 'question']
                        };
                    ?>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1">
                                            <i class="fas fa-file-prescription me-2"></i>Prescription #PR<?php echo str_pad($prescription['id'], 4, '0', STR_PAD_LEFT); ?>
                                        </h6>
                                        <small class="text-muted"><?php echo date('F d, Y', strtotime($prescription['created_at'])); ?></small>
                                    </div>
                                    <span class="badge bg-<?php echo $status_info['color']; ?>">
                                        <i class="fas fa-<?php echo $status_info['icon']; ?> me-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $prescription['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted">Doctor</small>
                                        <p class="mb-0 fw-bold"><?php echo htmlspecialchars($prescription['doctor_name']); ?></p>
                                        <?php if ($prescription['doctor_license']): ?>
                                        <small class="text-muted">License: <?php echo htmlspecialchars($prescription['doctor_license']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Prescription Date</small>
                                        <p class="mb-0 fw-bold"><?php echo date('F d, Y', strtotime($prescription['prescription_date'])); ?></p>
                                    </div>
                                </div>

                                <?php if ($prescription['diagnosis']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Diagnosis</small>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($prescription['diagnosis'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($prescription['instructions']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Instructions</small>
                                    <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($prescription['instructions'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if ($prescription['prescription_file']): ?>
                                <div class="mb-3">
                                    <small class="text-muted">Uploaded File</small><br>
                                    <?php if ($prescription['file_type'] === 'image'): ?>
                                        <a href="<?php echo htmlspecialchars($prescription['prescription_file']); ?>" 
                                           target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-image me-2"></i>View Image
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo htmlspecialchars($prescription['prescription_file']); ?>" 
                                           target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-file-pdf me-2"></i>View PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($prescription['admin_notes']): ?>
                                <div class="alert <?php echo $prescription['status'] === 'rejected' ? 'alert-danger' : 'alert-info'; ?> mb-0">
                                    <strong><i class="fas fa-<?php echo $prescription['status'] === 'rejected' ? 'exclamation-triangle' : 'comment-medical'; ?> me-2"></i><?php echo $prescription['status'] === 'rejected' ? 'Rejection Reason:' : 'Pharmacist\'s Note:'; ?></strong><br>
                                    <?php echo nl2br(htmlspecialchars($prescription['admin_notes'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <?php if ($prescription['status'] === 'pending'): ?>
                                            <i class="fas fa-info-circle me-1"></i>Awaiting review by pharmacist
                                        <?php elseif ($prescription['status'] === 'approved'): ?>
                                            <i class="fas fa-check-circle me-1 text-success"></i>Approved - You can now order
                                        <?php elseif ($prescription['status'] === 'rejected'): ?>
                                            <i class="fas fa-times-circle me-1 text-danger"></i>Please contact us for details
                                        <?php elseif ($prescription['status'] === 'filled'): ?>
                                            <i class="fas fa-check-double me-1 text-success"></i>Prescription filled
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($prescription['status'] === 'approved'): ?>
                                    <a href="medicines.php" class="btn btn-primary-hero btn-sm">
                                        <i class="fas fa-pills me-2"></i>Order Medicines
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
