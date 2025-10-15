<?php
session_start();
include 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$message = '';
$error_message = '';
$mode = $_GET['mode'] ?? 'login'; // Default to login mode

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error_message = 'Please enter both email and password.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ? AND status = 'active'");
                $stmt->execute([$email]);
                $customer = $stmt->fetch();
                
                if ($customer && password_verify($password, $customer['password'])) {
                    // Clear any existing admin sessions for security
                    unset($_SESSION['admin_logged_in']);
                    unset($_SESSION['admin_id']);
                    unset($_SESSION['admin_username']);
                    unset($_SESSION['admin_role']);
                    unset($_SESSION['admin_name']);
                    
                    // Set customer session variables
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['user_id'] = $customer['id'];
                    $_SESSION['user_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
                    $_SESSION['user_email'] = $customer['email'];
                    
                    // Load cart from database
                    $stmt = $pdo->prepare("
                        SELECT c.*, m.name, m.quantity as max_quantity 
                        FROM cart c 
                        JOIN medicines m ON c.medicine_id = m.id 
                        WHERE c.customer_id = ? AND m.status = 'active'
                    ");
                    $stmt->execute([$customer['id']]);
                    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $_SESSION['cart'] = [];
                    foreach ($cart_items as $item) {
                        $_SESSION['cart'][] = [
                            'medicine_id' => $item['medicine_id'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'quantity' => $item['quantity'],
                            'max_quantity' => $item['max_quantity']
                        ];
                    }
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error_message = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $error_message = 'Login failed. Please try again.';
            }
        }
    } elseif ($action === 'register') {
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $date_of_birth = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $emergency_contact = trim($_POST['emergency_contact']);
        $medical_history = trim($_POST['medical_history']);
        
        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $error_message = 'Please fill in all required fields.';
        } elseif ($password !== $confirm_password) {
            $error_message = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif (!empty($phone) && (strlen($phone) !== 10 || !ctype_digit($phone))) {
            $error_message = 'Phone number must be exactly 10 digits.';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_message = 'An account with this email already exists.';
                } else {
                    // Hash the password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new customer
                    $stmt = $pdo->prepare("
                        INSERT INTO customers (first_name, last_name, email, password, phone, address, date_of_birth, gender, emergency_contact, medical_history, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $first_name,
                        $last_name,
                        $email,
                        $hashedPassword,
                        $phone,
                        $address,
                        $date_of_birth ?: null,
                        $gender ?: null,
                        $emergency_contact,
                        $medical_history
                    ]);
                    
                    $message = 'Registration successful! You can now log in with your email.';
                    $mode = 'login'; // Switch to login mode after successful registration
                    
                    // Clear form data
                    $_POST = [];
                }
            } catch (PDOException $e) {
                $error_message = 'Registration failed. Please try again.';
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
    <title><?php echo $mode === 'login' ? 'Login' : 'Register'; ?> - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
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
                    <?php if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true): ?>
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
            <h1 id="pageTitle">Welcome Back</h1>
            <p id="pageSubtitle">Sign in to your account or create a new one</p>
        </div>
    </section>

    <!-- Auth Form -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3 class="mb-0">
                            <i class="fas fa-user-circle me-2"></i>
                            <span id="authTitle"><?php echo $mode === 'register' ? 'Join Sahana Medicals' : 'Welcome Back'; ?></span>
                        </h3>
                        <p class="mb-0 mt-2 text-muted" id="authSubtitle">
                            <?php echo $mode === 'register' ? 'Create your account for better healthcare' : 'Sign in to your account'; ?>
                        </p>
                    </div>
                    <div class="card-body p-5">
                        <!-- Mode Toggle -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="authMode" id="loginMode" <?php echo $mode === 'login' ? 'checked' : ''; ?>>
                                    <label class="btn btn-secondary-hero" for="loginMode">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </label>
                                    
                                    <input type="radio" class="btn-check" name="authMode" id="registerMode" <?php echo $mode === 'register' ? 'checked' : ''; ?>>
                                    <label class="btn btn-secondary-hero" for="registerMode">
                                        <i class="fas fa-user-plus me-2"></i>Register
                                    </label>
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

                        <!-- Login Form -->
                        <div id="loginForm" class="auth-form" style="<?php echo $mode === 'login' ? 'display: block;' : 'display: none;'; ?>">
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="login">
                                
                                <div class="mb-4">
                                    <label for="loginEmail" class="form-label">Email Address *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" class="form-control" id="loginEmail" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter your email address.</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="loginPassword" class="form-label">Password *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" class="form-control" id="loginPassword" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <div class="invalid-feedback">Please enter your password.</div>
                                    </div>
                                </div>

                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                    </button>
                                </div>
                            </form>
                            
                            <div class="text-center">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> For security purposes, we use email-based authentication. Simply enter your registered email address to access your account.
                                </div>
                            </div>
                        </div>

                        <!-- Register Form -->
                        <div id="registerForm" class="auth-form" style="<?php echo $mode === 'register' ? 'display: block;' : 'display: none;'; ?>">
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="register">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="firstName" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="firstName" name="first_name" 
                                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter your first name.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lastName" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="lastName" name="last_name" 
                                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter your last name.</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="registerEmail" class="form-label">Email Address *</label>
                                        <input type="email" class="form-control" id="registerEmail" name="email" 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                               pattern="[0-9]{10}" maxlength="10" 
                                               title="Phone number must be exactly 10 digits">
                                        <small class="form-text text-muted">Enter exactly 10 digits (e.g., 0771234567)</small>
                                        <div class="invalid-feedback">Please enter exactly 10 digits.</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="registerPassword" class="form-label">Password *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="registerPassword" name="password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="toggleRegisterPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text text-muted">Password must be at least 6 characters long.</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="confirmPassword" class="form-label">Confirm Password *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-lock"></i>
                                            </span>
                                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                                            <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" 
                                               value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Select Gender</option>
                                            <option value="male" <?php echo ($_POST['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($_POST['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($_POST['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="emergencyContact" class="form-label">Emergency Contact</label>
                                        <input type="tel" class="form-control" id="emergencyContact" name="emergency_contact" 
                                               value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="medicalHistory" class="form-label">Medical History</label>
                                    <textarea class="form-control" id="medicalHistory" name="medical_history" rows="3" 
                                              placeholder="Known allergies, chronic conditions, current medications, etc."><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="terms" required>
                                        <label class="form-check-label" for="terms">
                                            I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a>
                                        </label>
                                        <div class="invalid-feedback">You must agree to the terms and conditions.</div>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-submit btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>Create Account
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form switching functionality
        document.addEventListener('DOMContentLoaded', function() {
            const loginMode = document.getElementById('loginMode');
            const registerMode = document.getElementById('registerMode');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            const authTitle = document.getElementById('authTitle');
            const authSubtitle = document.getElementById('authSubtitle');

            function switchToLogin() {
                loginForm.style.display = 'block';
                registerForm.style.display = 'none';
                authTitle.textContent = 'Welcome Back';
                authSubtitle.textContent = 'Sign in to your account';
                document.getElementById('pageTitle').textContent = 'Welcome Back';
                document.getElementById('pageSubtitle').textContent = 'Sign in to your account';
                registerForm.querySelector('form').reset();
            }

            function switchToRegister() {
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                authTitle.textContent = 'Join Sahana Medicals';
                authSubtitle.textContent = 'Create your account for better healthcare';
                document.getElementById('pageTitle').textContent = 'Join Sahana Medicals';
                document.getElementById('pageSubtitle').textContent = 'Create your account for better healthcare';
                loginForm.querySelector('form').reset();
            }

            loginMode.addEventListener('change', switchToLogin);
            registerMode.addEventListener('change', switchToRegister);

            // Password visibility toggles
            function setupPasswordToggle(toggleId, inputId) {
                const toggle = document.getElementById(toggleId);
                const input = document.getElementById(inputId);
                
                if (toggle && input) {
                    toggle.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        const icon = toggle.querySelector('i');
                        icon.classList.toggle('fa-eye');
                        icon.classList.toggle('fa-eye-slash');
                    });
                }
            }
            
            // Setup all password toggles
            setupPasswordToggle('toggleLoginPassword', 'loginPassword');
            setupPasswordToggle('toggleRegisterPassword', 'registerPassword');
            setupPasswordToggle('toggleConfirmPassword', 'confirmPassword');

            // Form validation
            (function() {
                'use strict';
                window.addEventListener('load', function() {
                    var forms = document.getElementsByClassName('needs-validation');
                    var validation = Array.prototype.filter.call(forms, function(form) {
                        form.addEventListener('submit', function(event) {
                            if (form.checkValidity() === false) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                            form.classList.add('was-validated');
                        }, false);
                    });
                }, false);
            })();

            // Auto-focus on email field when switching to login
            loginMode.addEventListener('change', function() {
                setTimeout(() => {
                    document.getElementById('loginEmail').focus();
                }, 100);
            });

            // Auto-focus on first name field when switching to register
            registerMode.addEventListener('change', function() {
                setTimeout(() => {
                    document.getElementById('firstName').focus();
                }, 100);
            });
        });
    </script>

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
                        <li><a href="contact.php">Contact</a></li>
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
                        <p class="mb-0">Designed with <i class="fas fa-heart text-danger"></i> for your health</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function() {
            let phone = this.value.replace(/\D/g, ''); // Remove non-digits
            if (phone.length > 10) {
                phone = phone.substring(0, 10); // Limit to 10 digits
            }
            this.value = phone;
            
            // Real-time validation feedback
            if (phone.length === 10) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (phone.length > 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        // Validate phone number
                        const phone = document.getElementById('phone').value;
                        if (phone && (phone.length !== 10 || !/^\d{10}$/.test(phone))) {
                            event.preventDefault();
                            event.stopPropagation();
                            alert('Phone number must be exactly 10 digits.');
                            return false;
                        }
                        
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>
