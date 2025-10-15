<?php
session_start();
include 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: auth.php?mode=login');
    exit;
}

$message = '';
$error_message = '';

// Get customer details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$customer = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact = trim($_POST['emergency_contact']);
    $medical_history = trim($_POST['medical_history']);
    
    if (empty($first_name) || empty($last_name)) {
        $error_message = 'First name and last name are required.';
    } elseif (!empty($phone) && (strlen($phone) !== 10 || !ctype_digit($phone))) {
        $error_message = 'Phone number must be exactly 10 digits.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE customers 
                SET first_name = ?, last_name = ?, phone = ?, address = ?, date_of_birth = ?, 
                    gender = ?, emergency_contact = ?, medical_history = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $first_name,
                $last_name,
                $phone,
                $address,
                $date_of_birth ?: null,
                $gender ?: null,
                $emergency_contact,
                $medical_history,
                $_SESSION['user_id']
            ]);
            
            // Update session name
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            // Refresh customer data
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $customer = $stmt->fetch();
            
            $message = 'Profile updated successfully!';
            
        } catch (PDOException $e) {
            $error_message = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Sahana Medicals</title>
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
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                            <li><a class="dropdown-item" href="my_orders.php"><i class="fas fa-shopping-cart me-2"></i>My Orders</a></li>
                            <li><a class="dropdown-item" href="submit_prescription.php"><i class="fas fa-prescription me-2"></i>Submit Prescription</a></li>
                            <li><a class="dropdown-item" href="my_prescriptions.php"><i class="fas fa-clipboard-list me-2"></i>My Prescriptions</a></li>
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
            <h1>My Profile</h1>
            <p>Manage your account information</p>
        </div>
    </section>

    <!-- Profile Content -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
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

                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>Personal Information
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($customer['email']); ?>" readonly>
                                    <small class="form-text text-muted">Email cannot be changed</small>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" 
                                           pattern="[0-9]{10}" maxlength="10">
                                    <small class="form-text text-muted">Enter exactly 10 digits</small>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo htmlspecialchars($customer['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($customer['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($customer['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($customer['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                        <input type="tel" class="form-control" id="emergency_contact" name="emergency_contact" 
                                               value="<?php echo htmlspecialchars($customer['emergency_contact'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="medical_history" class="form-label">Medical History</label>
                                    <textarea class="form-control" id="medical_history" name="medical_history" rows="3" 
                                              placeholder="Known allergies, chronic conditions, current medications, etc."><?php echo htmlspecialchars($customer['medical_history'] ?? ''); ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                                    </a>
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>Account Summary
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Get order count
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE customer_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $order_count = $stmt->fetch()['count'];
                            
                            // Get prescription count
                            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE customer_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $prescription_count = $stmt->fetch()['count'];
                            ?>
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-shopping-bag"></i>
                                        </div>
                                        <div class="stat-number"><?php echo $order_count; ?></div>
                                        <div class="stat-label">Total Orders</div>
                                        <a href="my_orders.php" class="btn btn-sm btn-outline-primary mt-2">View Orders</a>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-file-prescription"></i>
                                        </div>
                                        <div class="stat-number"><?php echo $prescription_count; ?></div>
                                        <div class="stat-label">Prescriptions</div>
                                        <a href="my_prescriptions.php" class="btn btn-sm btn-outline-primary mt-2">View Prescriptions</a>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="stat-number">
                                            <?php
                                            $join_date = new DateTime($customer['created_at']);
                                            $today = new DateTime();
                                            $days = $today->diff($join_date)->days;
                                            echo $days;
                                            ?>
                                        </div>
                                        <div class="stat-label">Days with Us</div>
                                        <small class="text-muted">Since <?php echo date('M Y', strtotime($customer['created_at'])); ?></small>
                                    </div>
                                </div>
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
    <script>
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function() {
            let phone = this.value.replace(/\D/g, '');
            if (phone.length > 10) {
                phone = phone.substring(0, 10);
            }
            this.value = phone;
        });
    </script>
</body>
</html>
