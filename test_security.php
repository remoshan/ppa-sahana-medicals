<?php
/**
 * Security Test - Verify Admin/Customer Session Separation
 * This script tests if admin and customer sessions are properly separated
 */

session_start();

echo "<h2>🔒 Security Test - Session Separation Check</h2>";
echo "<hr>";

echo "<h3>Current Session Status</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr style='background: #f0f0f0;'><th>Session Variable</th><th>Value</th><th>Status</th></tr>";

// Check admin session
$admin_logged = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
echo "<tr>";
echo "<td><strong>admin_logged_in</strong></td>";
echo "<td>" . ($admin_logged ? 'true' : 'false') . "</td>";
echo "<td style='color: " . ($admin_logged ? 'green' : 'gray') . ";'>" . ($admin_logged ? '✅ Active' : '❌ Inactive') . "</td>";
echo "</tr>";

// Check customer session  
$customer_logged = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
echo "<tr>";
echo "<td><strong>user_logged_in</strong></td>";
echo "<td>" . ($customer_logged ? 'true' : 'false') . "</td>";
echo "<td style='color: " . ($customer_logged ? 'green' : 'gray') . ";'>" . ($customer_logged ? '✅ Active' : '❌ Inactive') . "</td>";
echo "</tr>";

echo "</table><br>";

// Security Analysis
echo "<h3>Security Analysis</h3>";

if ($admin_logged && $customer_logged) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545;'>";
    echo "<strong>⚠️ SECURITY BREACH DETECTED!</strong><br>";
    echo "Both admin and customer sessions are active simultaneously. This should not happen!<br>";
    echo "Action: Please test the authentication system and clear sessions.";
    echo "</div>";
} elseif ($admin_logged) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; border-left: 4px solid #28a745;'>";
    echo "<strong>✅ Admin Session Active</strong><br>";
    echo "Logged in as: <strong>" . ($_SESSION['admin_name'] ?? 'Unknown') . "</strong><br>";
    echo "Username: <code>" . ($_SESSION['admin_username'] ?? 'Unknown') . "</code><br>";
    echo "Role: <code>" . ($_SESSION['admin_role'] ?? 'Unknown') . "</code><br>";
    echo "Customer session: <strong>Not Active</strong> ✅";
    echo "</div>";
} elseif ($customer_logged) {
    echo "<div style='background: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 5px; border-left: 4px solid #17a2b8;'>";
    echo "<strong>✅ Customer Session Active</strong><br>";
    echo "Logged in as: <strong>" . ($_SESSION['user_name'] ?? 'Unknown') . "</strong><br>";
    echo "Email: <code>" . ($_SESSION['user_email'] ?? 'Unknown') . "</code><br>";
    echo "Admin session: <strong>Not Active</strong> ✅";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;'>";
    echo "<strong>ℹ️ No Active Sessions</strong><br>";
    echo "Neither admin nor customer is logged in.";
    echo "</div>";
}

echo "<br><h3>Session Details (All Variables)</h3>";
echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
print_r($_SESSION);
echo "</pre>";

echo "<br><h3>Test Actions</h3>";
echo "<div style='display: flex; gap: 10px; flex-wrap: wrap;'>";
echo "<a href='auth.php?mode=login' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px;'>Customer Login</a>";
echo "<a href='admin/login.php' style='padding: 10px 20px; background: #764ba2; color: white; text-decoration: none; border-radius: 5px;'>Admin Login</a>";
echo "<a href='logout.php' style='padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>Customer Logout</a>";
echo "<a href='admin/logout.php' style='padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>Admin Logout</a>";
echo "</div>";

echo "<br><h3>Expected Behavior</h3>";
echo "<ul>";
echo "<li>✅ Only ONE session type should be active at a time</li>";
echo "<li>✅ When admin logs in, customer session should be cleared</li>";
echo "<li>✅ When customer logs in, admin session should be cleared</li>";
echo "<li>✅ Customer cannot access admin panel even if admin was logged in</li>";
echo "<li>✅ Admin accessing customer pages will see customer view (not logged in)</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Test Procedure:</strong></p>";
echo "<ol>";
echo "<li>Login as customer → Check this page → Should show ONLY customer session</li>";
echo "<li>Then login as admin → Check this page → Should show ONLY admin session</li>";
echo "<li>Try accessing admin panel while logged in as customer → Should be denied</li>";
echo "<li>Logout and verify all sessions are cleared</li>";
echo "</ol>";
?>

