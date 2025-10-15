<?php
/**
 * Database Migration Script
 * Run this file once to update the prescriptions table with the 'rejected' status
 */

include 'config/database.php';

try {
    echo "Starting database migration...\n";
    
    // Modify the status ENUM to include 'rejected'
    $sql = "ALTER TABLE prescriptions 
            MODIFY COLUMN status ENUM('pending', 'reviewing', 'approved', 'rejected', 'denied', 'image_unclear', 'filled', 'cancelled') DEFAULT 'pending'";
    
    $pdo->exec($sql);
    
    echo "✓ Successfully updated prescriptions table status ENUM to include 'rejected'\n";
    echo "✓ Migration completed successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    
    // Check if the error is because 'rejected' already exists
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        echo "Note: The 'rejected' status may already exist in the ENUM.\n";
    }
}
?>

