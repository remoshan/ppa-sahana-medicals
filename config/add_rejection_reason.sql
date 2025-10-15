-- Add 'rejected' to the status ENUM for prescriptions table
-- Run this SQL to update the database schema

USE PPA_Sahana_Medicals;

-- Modify the status ENUM to include 'rejected'
ALTER TABLE prescriptions 
MODIFY COLUMN status ENUM('pending', 'reviewing', 'approved', 'rejected', 'denied', 'image_unclear', 'filled', 'cancelled') DEFAULT 'pending';

-- The admin_notes field already exists, so we'll use it to store rejection reasons
-- No additional column needed

