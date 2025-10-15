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

// Handle prescription submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $doctor_name = trim($_POST['doctor_name']);
    $prescription_date = $_POST['prescription_date'];
    $instructions = trim($_POST['instructions']);
    
    if (empty($doctor_name) || empty($prescription_date)) {
        $error_message = 'Please fill in all required fields.';
    } else {
        // Handle file upload
        $prescription_file = '';
        $file_type = '';
        
        if (isset($_FILES['prescription_file']) && $_FILES['prescription_file']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $filename = $_FILES['prescription_file']['name'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed)) {
                $error_message = 'Invalid file type. Only JPG, PNG, and PDF files are allowed.';
            } else {
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($_FILES['prescription_file']['size'] > $max_size) {
                    $error_message = 'File size exceeds 5MB limit.';
                } else {
                    $new_filename = 'prescription_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
                    $upload_path = 'uploads/prescriptions/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['prescription_file']['tmp_name'], $upload_path)) {
                        $prescription_file = $upload_path;
                        $file_type = in_array($file_ext, ['jpg', 'jpeg', 'png']) ? 'image' : 'pdf';
                    } else {
                        $error_message = 'Failed to upload file. Please try again.';
                    }
                }
            }
        }
        
        if (empty($error_message)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO prescriptions (customer_id, doctor_name, prescription_date, instructions, prescription_file, file_type, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $doctor_name,
                    $prescription_date,
                    $instructions,
                    $prescription_file,
                    $file_type
                ]);
                
                // If this was for a specific medicine, redirect back with success
                if (isset($_SESSION['prescription_medicine_id'])) {
                    unset($_SESSION['prescription_medicine_id']);
                    unset($_SESSION['prescription_quantity']);
                }
                
                $message = 'Prescription submitted successfully! Our pharmacist will review it shortly.';
                $_SESSION['success'] = $message;
                header('Location: my_prescriptions.php');
                exit;
                
            } catch (PDOException $e) {
                $error_message = 'An error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Prescription - Sahana Medicals</title>
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
                            <li><a class="dropdown-item active" href="submit_prescription.php"><i class="fas fa-prescription me-2"></i>Submit Prescription</a></li>
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
            <h1>Submit Prescription</h1>
            <p>Upload your prescription for review</p>
        </div>
    </section>

    <!-- Prescription Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <?php if (isset($_SESSION['info'])): ?>
                        <div class="alert alert-info alert-dismissible fade show">
                            <i class="fas fa-info-circle me-2"></i><?php echo htmlspecialchars($_SESSION['info']); unset($_SESSION['info']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

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
                                <i class="fas fa-file-medical me-2"></i>Prescription Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="doctor_name" class="form-label">Doctor Name *</label>
                                        <input type="text" class="form-control" id="doctor_name" name="doctor_name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="prescription_date" class="form-label">Prescription Date *</label>
                                        <input type="date" class="form-control" id="prescription_date" name="prescription_date" 
                                               value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="instructions" class="form-label">Special Instructions</label>
                                    <textarea class="form-control" id="instructions" name="instructions" rows="3" 
                                              placeholder="Any special instructions or notes..."></textarea>
                                </div>

                                <div class="mb-4">
                                    <label for="prescription_file" class="form-label">Upload Prescription (Image/PDF) *</label>
                                    <input type="file" class="form-control" id="prescription_file" name="prescription_file" 
                                           accept=".jpg,.jpeg,.png,.pdf" required>
                                    <small class="form-text text-muted">
                                        Accepted formats: JPG, PNG, PDF | Maximum size: 5MB
                                    </small>
                                </div>

                                <div class="alert alert-info">
                                    <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Important Information</h6>
                                    <ul class="mb-0 small">
                                        <li>Ensure your prescription is clear and readable</li>
                                        <li>Include all pages of your prescription</li>
                                        <li>Our pharmacist will review your prescription within 24 hours</li>
                                        <li>You will be notified once your prescription is approved</li>
                                    </ul>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                                    <a href="my_prescriptions.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>View My Prescriptions
                                    </a>
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i>Submit Prescription
                                    </button>
                                </div>
                            </form>
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
        // Preview uploaded file
        document.getElementById('prescription_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                console.log('Selected file:', fileName);
            }
        });
    </script>
</body>
</html>
