<?php
/**
 * Admin Authentication Check
 * Include this file at the top of every admin page to ensure proper authentication
 */

// Prevent page caching to ensure real-time data updates
include_once __DIR__ . '/includes/cache_control.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Not logged in as admin, redirect to login
    header('Location: login.php');
    exit;
}

// Additional security: Check if customer session exists
// If customer is logged in, this is a security breach - clear and redirect
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    // Security breach detected - someone trying to access admin panel with customer session
    // Clear all sessions and redirect to customer login
    session_destroy();
    session_start();
    $_SESSION['error'] = 'Access denied. Please login as administrator.';
    header('Location: login.php');
    exit;
}

// Verify admin session is valid by checking required session variables
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Invalid session, clear and redirect
    session_destroy();
    session_start();
    header('Location: login.php');
    exit;
}

// Optional: Verify admin still exists and is active in database
// Uncomment if you want real-time database verification (may impact performance)
/*
include_once '../config/database.php';
try {
    $stmt = $pdo->prepare("SELECT status FROM admin_users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Admin no longer exists or is inactive
        session_destroy();
        session_start();
        $_SESSION['error'] = 'Your account has been deactivated. Please contact administrator.';
        header('Location: login.php');
        exit;
    }
} catch (PDOException $e) {
    // Database error, continue but log it
    error_log('Admin auth check database error: ' . $e->getMessage());
}
*/
?>

