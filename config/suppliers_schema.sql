-- Suppliers Management System - Database Schema
-- Run this SQL to add supplier management features

USE PPA_Sahana_Medicals;

-- Suppliers Table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(200),
    email VARCHAR(200),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    tax_id VARCHAR(100),
    payment_terms VARCHAR(100),
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Supplier Products (What each supplier can supply)
CREATE TABLE IF NOT EXISTS supplier_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    medicine_id INT NOT NULL,
    supplier_price DECIMAL(10, 2) NOT NULL,
    minimum_order_quantity INT DEFAULT 1,
    lead_time_days INT DEFAULT 0,
    is_preferred BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    UNIQUE KEY unique_supplier_medicine (supplier_id, medicine_id)
);

-- Purchase Orders (Orders placed to suppliers)
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    actual_delivery_date DATE,
    total_amount DECIMAL(15, 2) NOT NULL,
    tax_amount DECIMAL(15, 2) DEFAULT 0.00,
    shipping_cost DECIMAL(10, 2) DEFAULT 0.00,
    discount_amount DECIMAL(10, 2) DEFAULT 0.00,
    final_amount DECIMAL(15, 2) NOT NULL,
    status ENUM('draft', 'pending', 'confirmed', 'partially_received', 'received', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- Purchase Order Items
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(15, 2) NOT NULL,
    batch_number VARCHAR(100),
    expiry_date DATE,
    received_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT
);

-- Customer Payments (Payments from customers at checkout)
CREATE TABLE IF NOT EXISTS customer_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15, 2) NOT NULL,
    payment_method ENUM('cash_on_delivery', 'bank_transfer', 'credit_card', 'debit_card', 'online_banking', 'mobile_payment') NOT NULL,
    payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    reference_number VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT
);

-- Insert sample suppliers
INSERT INTO suppliers (company_name, contact_person, email, phone, address, city, country, payment_terms, status) VALUES 
('MediPharm Distributors', 'John Anderson', 'contact@medipharm.com', '0771234567', '123 Medical Plaza', 'Colombo', 'Sri Lanka', 'Net 30', 'active'),
('HealthCare Supplies Ltd', 'Sarah Williams', 'info@healthcaresupplies.lk', '0112345678', '456 Supply Street', 'Kandy', 'Sri Lanka', 'Net 45', 'active'),
('Global Pharma International', 'Michael Chen', 'sales@globalpharma.com', '0777654321', '789 International Road', 'Colombo', 'Sri Lanka', 'Net 60', 'active'),
('Local Medicine Distributors', 'Priya Fernando', 'orders@localmed.lk', '0114567890', '321 Distribution Center', 'Gampaha', 'Sri Lanka', 'Net 30', 'active'),
('Premium Medical Supplies', 'David Silva', 'contact@premiummed.lk', '0773456789', '654 Premium Avenue', 'Negombo', 'Sri Lanka', 'Cash on Delivery', 'active');

-- Link some suppliers to medicines (sample data)
-- This assumes medicines already exist from the main database_setup.sql
INSERT INTO supplier_products (supplier_id, medicine_id, supplier_price, minimum_order_quantity, lead_time_days, is_preferred) 
SELECT 1, id, price * 0.6, 10, 7, TRUE FROM medicines WHERE id IN (1, 2, 3, 4, 5) LIMIT 5;

INSERT INTO supplier_products (supplier_id, medicine_id, supplier_price, minimum_order_quantity, lead_time_days, is_preferred) 
SELECT 2, id, price * 0.65, 20, 5, FALSE FROM medicines WHERE id IN (6, 7, 8, 9, 10) LIMIT 5;

INSERT INTO supplier_products (supplier_id, medicine_id, supplier_price, minimum_order_quantity, lead_time_days, is_preferred) 
SELECT 3, id, price * 0.55, 50, 14, TRUE FROM medicines WHERE id IN (11, 12, 13, 14, 15) LIMIT 5;
