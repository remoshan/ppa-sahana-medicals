<?php
include 'config/database.php';

echo "<h2>🔧 Fix Admin Password</h2>";
echo "<hr>";

// Generate a fresh hash for 'secret_password'
$password = 'secret_password';
$new_hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Step 1: Generate Fresh Hash</h3>";
echo "Password: <strong>secret_password</strong><br>";
echo "New Hash: <code style='word-break: break-all;'>" . $new_hash . "</code><br><br>";

// Verify the hash works
echo "<h3>Step 2: Verify Hash</h3>";
if (password_verify($password, $new_hash)) {
    echo "✅ <span style='color: green;'><strong>Hash verification: SUCCESS</strong></span><br><br>";
} else {
    echo "❌ <span style='color: red;'><strong>Hash verification: FAILED</strong></span><br><br>";
    exit;
}

// Update the newadmin user
echo "<h3>Step 3: Update 'newadmin' Password</h3>";
try {
    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE username = 'newadmin'");
    $stmt->execute([$new_hash]);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ <span style='color: green;'><strong>Password updated successfully!</strong></span><br><br>";
        
        // Verify the update worked
        echo "<h3>Step 4: Verify Database Update</h3>";
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE username = 'newadmin'");
        $stmt->execute();
        $stored_hash = $stmt->fetchColumn();
        
        if (password_verify($password, $stored_hash)) {
            echo "✅ <span style='color: green;'><strong>Database verification: SUCCESS</strong></span><br>";
            echo "<p style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
            echo "<strong>🎉 All Done!</strong><br>";
            echo "You can now login with:<br>";
            echo "Username: <code>newadmin</code><br>";
            echo "Password: <code>secret_password</code><br>";
            echo "</p>";
            
            echo "<br><a href='admin/login.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Login →</a>";
        } else {
            echo "❌ <span style='color: red;'><strong>Database verification: FAILED</strong></span><br>";
        }
    } else {
        echo "⚠️ <span style='color: orange;'><strong>No rows updated. User 'newadmin' might not exist.</strong></span><br>";
        
        // Create the user instead
        echo "<br><h3>Step 5: Create 'newadmin' User</h3>";
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password, full_name, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['newadmin', 'newadmin@sahanamedicals.com', $new_hash, 'New Administrator', 'admin', 'active']);
        
        echo "✅ <span style='color: green;'><strong>User created successfully!</strong></span><br>";
        echo "<p style='background: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
        echo "<strong>🎉 User Created!</strong><br>";
        echo "You can now login with:<br>";
        echo "Username: <code>newadmin</code><br>";
        echo "Password: <code>secret_password</code><br>";
        echo "</p>";
        
        echo "<br><a href='admin/login.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Go to Admin Login →</a>";
    }
    
} catch (PDOException $e) {
    echo "❌ <span style='color: red;'><strong>Error: " . $e->getMessage() . "</strong></span><br>";
}

echo "<hr>";
echo "<h3>Alternative: SQL Command</h3>";
echo "<p>If you prefer to run SQL manually, use this:</p>";
echo "<textarea rows='3' cols='80' style='font-family: monospace; padding: 10px;' onclick='this.select()'>UPDATE admin_users SET password = '" . $new_hash . "' WHERE username = 'newadmin';</textarea>";
?>

