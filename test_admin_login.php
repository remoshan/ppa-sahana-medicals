<?php
// Test Admin Login - Diagnostic Script
include 'config/database.php';

echo "<h2>Admin Login Diagnostic Test</h2>";
echo "<hr>";

// Test 1: Check database connection
echo "<h3>Test 1: Database Connection</h3>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Database connection: <strong style='color: green;'>SUCCESS</strong><br><br>";
} catch (PDOException $e) {
    echo "❌ Database connection: <strong style='color: red;'>FAILED</strong><br>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 2: Check if admin_users table exists
echo "<h3>Test 2: Check admin_users Table</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
    $table_exists = $stmt->rowCount() > 0;
    
    if ($table_exists) {
        echo "✅ Table 'admin_users': <strong style='color: green;'>EXISTS</strong><br><br>";
    } else {
        echo "❌ Table 'admin_users': <strong style='color: red;'>NOT FOUND</strong><br>";
        echo "Please run the database_setup.sql file first!<br><br>";
        exit;
    }
} catch (PDOException $e) {
    echo "❌ Error checking table: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 3: List all admin users
echo "<h3>Test 3: List All Admin Users</h3>";
try {
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, status, created_at FROM admin_users");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($admins)) {
        echo "⚠️ <strong style='color: orange;'>No admin users found in database!</strong><br>";
        echo "You may need to add an admin user.<br><br>";
    } else {
        echo "Found " . count($admins) . " admin user(s):<br><br>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Full Name</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
              </tr>";
        
        foreach ($admins as $admin) {
            $status_color = $admin['status'] === 'active' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($admin['id']) . "</td>";
            echo "<td><strong>" . htmlspecialchars($admin['username']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($admin['email']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($admin['role']) . "</td>";
            echo "<td style='color: {$status_color};'><strong>" . htmlspecialchars($admin['status']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($admin['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table><br><br>";
    }
} catch (PDOException $e) {
    echo "❌ Error fetching admin users: " . $e->getMessage() . "<br><br>";
}

// Test 4: Test password verification
echo "<h3>Test 4: Password Hash Verification</h3>";
$test_password = 'secret_password';
$stored_hash = '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdyrKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm';

echo "Testing password: '<strong>secret_password</strong>'<br>";
echo "Against stored hash: <code>" . substr($stored_hash, 0, 30) . "...</code><br>";

if (password_verify($test_password, $stored_hash)) {
    echo "✅ Password verification: <strong style='color: green;'>SUCCESS</strong><br><br>";
} else {
    echo "❌ Password verification: <strong style='color: red;'>FAILED</strong><br>";
    echo "Generating new hash for 'secret_password':<br>";
    $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
    echo "<code>" . $new_hash . "</code><br><br>";
}

// Test 5: Try to find 'newadmin' user
echo "<h3>Test 5: Check for 'newadmin' User</h3>";
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE LOWER(username) = LOWER(?)");
    $stmt->execute(['newadmin']);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ User 'newadmin': <strong style='color: green;'>FOUND</strong><br>";
        echo "Status: <strong style='color: " . ($admin['status'] === 'active' ? 'green' : 'red') . ";'>" . $admin['status'] . "</strong><br>";
        echo "Email: " . htmlspecialchars($admin['email']) . "<br>";
        echo "Full Name: " . htmlspecialchars($admin['full_name']) . "<br>";
        
        // Test password
        echo "<br>Testing password 'secret_password' against this user's hash:<br>";
        if (password_verify('secret_password', $admin['password'])) {
            echo "✅ Password match: <strong style='color: green;'>SUCCESS</strong><br>";
            echo "<br><strong style='color: green;'>You should be able to login with:</strong><br>";
            echo "Username: <code>newadmin</code><br>";
            echo "Password: <code>secret_password</code><br>";
        } else {
            echo "❌ Password match: <strong style='color: red;'>FAILED</strong><br>";
            echo "The stored password hash doesn't match 'secret_password'<br>";
            echo "<br>Password hash in database: <br><code>" . $admin['password'] . "</code><br>";
        }
    } else {
        echo "❌ User 'newadmin': <strong style='color: red;'>NOT FOUND</strong><br>";
        echo "<br><strong>To create the user, run this SQL:</strong><br>";
        echo "<textarea rows='4' cols='80' style='font-family: monospace;'>
INSERT INTO admin_users (username, email, password, full_name, role, status) 
VALUES ('newadmin', 'newadmin@sahanamedicals.com', '$2y$10\$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'New Administrator', 'admin', 'active');
</textarea><br>";
    }
} catch (PDOException $e) {
    echo "❌ Error checking user: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all tests pass and password verification succeeds, you should be able to login.</p>";
echo "<p>If not, use the diagnostic information above to troubleshoot the issue.</p>";
echo "<br>";
echo "<a href='admin/login.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a>";
?>

