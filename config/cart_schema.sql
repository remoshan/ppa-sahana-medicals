-- Cart Persistence - Database Schema
-- Run this SQL to add persistent cart functionality

USE PPA_Sahana_Medicals;

-- Cart Table (stores items added to cart by customers)
CREATE TABLE IF NOT EXISTS cart (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    UNIQUE KEY unique_customer_medicine (customer_id, medicine_id)
);

-- Index for faster queries
CREATE INDEX idx_customer_id ON cart(customer_id);
CREATE INDEX idx_created_at ON cart(created_at);

