<nav class="col-md-3 col-lg-2 d-md-block admin-sidebar">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <i class="fas fa-pills text-white mb-2" style="font-size: 2rem;"></i>
            <h6 class="text-white">Sahana Medicals</h6>
            <small class="text-white-50">Admin Panel</small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'medicines.php' ? 'active' : ''; ?>" href="medicines.php">
                    <i class="fas fa-pills"></i>
                    Medicines
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'categories.php' ? 'active' : ''; ?>" href="categories.php">
                    <i class="fas fa-tags"></i>
                    Categories
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'customers.php' ? 'active' : ''; ?>" href="customers.php">
                    <i class="fas fa-users"></i>
                    Customers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'orders.php' ? 'active' : ''; ?>" href="orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    Orders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'prescriptions.php' ? 'active' : ''; ?>" href="prescriptions.php">
                    <i class="fas fa-file-medical"></i>
                    Prescriptions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'staff.php' ? 'active' : ''; ?>" href="staff.php">
                    <i class="fas fa-user-tie"></i>
                    Staff
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['suppliers.php', 'supplier_products.php', 'purchase_orders.php']) ? 'active' : ''; ?>" href="suppliers.php">
                    <i class="fas fa-truck"></i>
                    Suppliers
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                    <i class="fas fa-money-bill-wave"></i>
                    Payments
                </a>
            </li>
        </ul>
        
        <hr class="text-white-50">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../index.php" target="_blank">
                    <i class="fas fa-external-link-alt"></i>
                    View Website
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</nav>
