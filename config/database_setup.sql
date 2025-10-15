-- Sahana Medicals Database Setup
-- Run this SQL script to create the database and tables

CREATE DATABASE IF NOT EXISTS PPA_Sahana_Medicals;
USE PPA_Sahana_Medicals;

-- Categories Table
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicines Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    manufacturer VARCHAR(200),
    price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    expiry_date DATE,
    batch_number VARCHAR(100),
    prescription_required BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
    image_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(200) UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(10),
    address TEXT,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    emergency_contact VARCHAR(20),
    medical_history TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Staff Table
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(200) UNIQUE NOT NULL,
    phone VARCHAR(10),
    address TEXT,
    position VARCHAR(100),
    department VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(10, 2),
    status ENUM('active', 'inactive', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Prescriptions Table
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    doctor_name VARCHAR(200) NOT NULL,
    doctor_license VARCHAR(100),
    prescription_date DATE NOT NULL,
    diagnosis TEXT,
    medicines JSON,
    instructions TEXT,
    prescription_file VARCHAR(500),
    file_type ENUM('image', 'pdf') DEFAULT 'image',
    status ENUM('pending', 'reviewing', 'approved', 'denied', 'image_unclear', 'filled', 'cancelled') DEFAULT 'pending',
    admin_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
);

-- Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    notes TEXT,
    prescription_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE SET NULL
);

-- Order Items Table
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT,
    medicine_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
);

-- Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(200) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(200),
    role ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO admin_users (username, email, password, full_name, role) VALUES 
('Admin', 'admin@sahanamedicals.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', 'System Administrator', 'admin');

-- Insert sample categories
INSERT INTO categories (name, description) VALUES 
('Antibiotics', 'Prescription medicines for bacterial infections'),
('Pain Relief', 'Pain management and fever reduction medicines'),
('Vitamins & Supplements', 'Nutritional supplements, vitamins, and minerals'),
('Diabetes Care', 'Medicines and supplies for diabetes management'),
('Heart & Blood Pressure', 'Cardiovascular health medicines'),
('Respiratory', 'Asthma, COPD, and breathing medicines'),
('Digestive Health', 'Stomach, intestinal, and digestive system medicines'),
('Skin Care', 'Topical treatments for skin conditions'),
('Cold & Flu', 'Medicines for cold, cough, and flu symptoms'),
('Allergy Relief', 'Antihistamines and allergy medications'),
('Eye & Ear Care', 'Drops and treatments for eyes and ears'),
('Women''s Health', 'Feminine hygiene and reproductive health products'),
('Baby & Child Care', 'Medicines and care products for children'),
('First Aid', 'Wound care, bandages, and first aid supplies'),
('Personal Care', 'Health and wellness personal care items'),
('Mental Health', 'Prescription medicines for mental health conditions');

-- Insert sample medicines
INSERT INTO medicines (name, description, category_id, manufacturer, price, quantity, expiry_date, batch_number, prescription_required) VALUES 
-- Antibiotics (Prescription Required)
('Amoxicillin 500mg', 'Broad-spectrum antibiotic for bacterial infections', 1, 'GSK Pharmaceuticals', 850.00, 100, '2025-12-31', 'AMX2024001', TRUE),
('Azithromycin 250mg', 'Macrolide antibiotic for respiratory infections', 1, 'Pfizer', 1250.00, 75, '2025-11-20', 'AZI2024002', TRUE),
('Ciprofloxacin 500mg', 'Fluoroquinolone antibiotic for various infections', 1, 'Bayer', 980.00, 60, '2026-01-15', 'CIP2024003', TRUE),
('Cephalexin 500mg', 'Cephalosporin antibiotic for skin and soft tissue infections', 1, 'Teva Pharmaceuticals', 720.00, 90, '2025-10-30', 'CEP2024004', TRUE),

-- Pain Relief (OTC)
('Paracetamol 500mg', 'Pain relief and fever reducer', 2, 'GlaxoSmithKline', 45.00, 200, '2025-06-30', 'PAR2024005', FALSE),
('Ibuprofen 400mg', 'Anti-inflammatory pain reliever', 2, 'Advil', 65.00, 180, '2025-08-15', 'IBU2024006', FALSE),
('Aspirin 325mg', 'Pain relief and anti-inflammatory', 2, 'Bayer', 55.00, 150, '2025-07-20', 'ASP2024007', FALSE),
('Diclofenac Gel 1%', 'Topical pain relief gel for joint pain', 2, 'Voltaren', 450.00, 80, '2025-09-10', 'DIC2024008', FALSE),
('Paracetamol + Caffeine', 'Enhanced pain relief with caffeine', 2, 'Panadol Extra', 75.00, 120, '2025-12-05', 'PAC2024009', FALSE),

-- Vitamins & Supplements (OTC)
('Vitamin D3 1000IU', 'Bone health and immune support', 3, 'Nature Made', 650.00, 150, '2026-03-15', 'VD32024010', FALSE),
('Vitamin C 1000mg', 'Immune system support and antioxidant', 3, 'Redoxon', 480.00, 200, '2026-02-20', 'VTC2024011', FALSE),
('Multivitamin Daily', 'Complete daily vitamin and mineral supplement', 3, 'Centrum', 890.00, 100, '2026-01-10', 'MLT2024012', FALSE),
('Calcium + Vitamin D', 'Bone health supplement', 3, 'Caltrate', 720.00, 130, '2025-11-25', 'CAL2024013', FALSE),
('Omega-3 Fish Oil', 'Heart health and brain function support', 3, 'Nordic Naturals', 1250.00, 90, '2025-10-15', 'OME2024014', FALSE),
('Vitamin B Complex', 'Energy metabolism and nervous system support', 3, 'Solgar', 580.00, 110, '2026-04-01', 'VTB2024015', FALSE),
('Iron Supplement 65mg', 'Iron deficiency and anemia treatment', 3, 'Feroglobin', 390.00, 85, '2025-12-18', 'IRN2024016', FALSE),

-- Diabetes Care (Mixed - Insulin Rx, Monitors OTC)
('Metformin 500mg', 'First-line diabetes medication', 4, 'Bristol Myers Squibb', 125.00, 80, '2025-09-20', 'MET2024017', TRUE),
('Glibenclamide 5mg', 'Blood sugar control for Type 2 diabetes', 4, 'Sanofi', 180.00, 70, '2025-10-12', 'GLI2024018', TRUE),
('Insulin Glargine', 'Long-acting insulin injection', 4, 'Lantus', 2850.00, 40, '2025-08-30', 'INS2024019', TRUE),
('Blood Glucose Test Strips', 'Test strips for glucose monitoring (50 strips)', 4, 'Accu-Chek', 1450.00, 120, '2026-06-30', 'GLU2024020', FALSE),
('Glucometer Digital', 'Digital blood glucose monitoring device', 4, 'OneTouch', 3200.00, 45, '2027-12-31', 'GLC2024021', FALSE),

-- Heart & Blood Pressure (Prescription Required)
('Atorvastatin 20mg', 'Cholesterol-lowering statin', 5, 'Lipitor', 320.00, 100, '2025-11-05', 'ATO2024022', TRUE),
('Amlodipine 5mg', 'Calcium channel blocker for hypertension', 5, 'Norvasc', 250.00, 120, '2025-10-22', 'AML2024023', TRUE),
('Losartan 50mg', 'Blood pressure medication', 5, 'Cozaar', 380.00, 95, '2025-12-08', 'LOS2024024', TRUE),
('Aspirin 75mg Low-Dose', 'Heart attack and stroke prevention', 5, 'Cardio Aspirin', 85.00, 150, '2025-07-10', 'ASP2024025', FALSE),
('Clopidogrel 75mg', 'Blood thinner for heart disease', 5, 'Plavix', 680.00, 60, '2025-09-15', 'CLO2024026', TRUE),

-- Respiratory (Mixed)
('Salbutamol Inhaler', 'Bronchodilator for asthma relief', 6, 'Ventolin', 1250.00, 50, '2025-11-25', 'SAL2024027', TRUE),
('Budesonide Inhaler', 'Preventive asthma medication', 6, 'Pulmicort', 1850.00, 35, '2025-10-18', 'BUD2024028', TRUE),
('Montelukast 10mg', 'Asthma and allergy prevention', 6, 'Singulair', 420.00, 75, '2026-01-20', 'MON2024029', TRUE),
('Steam Inhaler', 'Natural congestion relief device', 6, 'Vicks', 850.00, 40, '2027-12-31', 'STM2024030', FALSE),

-- Digestive Health (Mixed)
('Omeprazole 20mg', 'Acid reflux and heartburn treatment', 7, 'Prilosec', 320.00, 90, '2025-08-18', 'OME2024031', FALSE),
('Ranitidine 150mg', 'Heartburn and ulcer treatment', 7, 'Zantac', 180.00, 110, '2025-09-25', 'RAN2024032', FALSE),
('Loperamide 2mg', 'Anti-diarrheal medication', 7, 'Imodium', 120.00, 140, '2025-11-10', 'LOP2024033', FALSE),
('Probiotic Capsules', 'Digestive health and gut flora support', 7, 'Culturelle', 950.00, 80, '2026-02-15', 'PRO2024034', FALSE),
('Antacid Tablets', 'Quick heartburn and indigestion relief', 7, 'Gaviscon', 95.00, 160, '2025-12-30', 'ANT2024035', FALSE),
('Lactulose Syrup', 'Laxative for constipation relief', 7, 'Duphalac', 280.00, 70, '2025-10-05', 'LAC2024036', FALSE),

-- Skin Care (Mixed)
('Hydrocortisone Cream 1%', 'Anti-inflammatory for skin irritation', 8, 'Cortizone-10', 180.00, 60, '2025-05-30', 'HYD2024037', FALSE),
('Clotrimazole Cream', 'Antifungal for athlete''s foot and ringworm', 8, 'Lotrimin', 220.00, 85, '2025-08-12', 'CLO2024038', FALSE),
('Betamethasone Cream', 'Strong topical steroid for eczema', 8, 'Betnovate', 350.00, 50, '2025-07-20', 'BET2024039', TRUE),
('Benzoyl Peroxide Gel', 'Acne treatment gel', 8, 'Clearasil', 280.00, 95, '2025-09-18', 'BEN2024040', FALSE),
('Moisturizing Cream', 'Dry skin and eczema moisturizer', 8, 'Cetaphil', 450.00, 120, '2026-01-25', 'MOI2024041', FALSE),
('Sunscreen SPF 50', 'Broad spectrum sun protection', 8, 'Neutrogena', 680.00, 100, '2026-06-30', 'SUN2024042', FALSE),

-- Cold & Flu (OTC)
('Loratadine 10mg', 'Non-drowsy antihistamine', 9, 'Claritin', 180.00, 130, '2025-11-15', 'LOR2024043', FALSE),
('Cetirizine 10mg', 'Allergy relief 24-hour', 9, 'Zyrtec', 195.00, 145, '2025-10-20', 'CET2024044', FALSE),
('Phenylephrine Nasal Spray', 'Nasal congestion relief', 9, 'Sudafed', 220.00, 90, '2025-08-25', 'PHE2024045', FALSE),
('Cough Syrup', 'Dry cough relief syrup', 9, 'Benylin', 285.00, 110, '2025-12-10', 'COU2024046', FALSE),
('Throat Lozenges', 'Sore throat and cough relief', 9, 'Strepsils', 120.00, 180, '2026-03-30', 'THR2024047', FALSE),
('Vitamin C + Zinc Effervescent', 'Immune support fizzy tablets', 9, 'Berocca', 380.00, 100, '2026-02-28', 'VCZ2024048', FALSE),

-- Allergy Relief (OTC)
('Diphenhydramine 25mg', 'Antihistamine for allergies and sleep aid', 10, 'Benadryl', 150.00, 120, '2025-09-15', 'DIP2024049', FALSE),
('Fexofenadine 120mg', 'Non-drowsy allergy relief', 10, 'Allegra', 280.00, 90, '2025-11-08', 'FEX2024050', FALSE),
('Eye Drops Allergy Relief', 'Antihistamine eye drops', 10, 'Visine', 320.00, 75, '2025-10-12', 'EYE2024051', FALSE),

-- Eye & Ear Care (OTC)
('Artificial Tears', 'Lubricating eye drops for dry eyes', 11, 'Systane', 280.00, 110, '2025-12-20', 'ART2024052', FALSE),
('Ear Wax Removal Drops', 'Softens and removes ear wax', 11, 'Debrox', 250.00, 85, '2025-11-05', 'EAR2024053', FALSE),
('Antibiotic Eye Drops', 'Treats bacterial eye infections', 11, 'Chloramphenicol', 420.00, 60, '2025-08-30', 'ANE2024054', TRUE),

-- Women's Health (Mixed)
('Prenatal Vitamins', 'Complete vitamins for pregnancy', 12, 'Elevit', 1280.00, 70, '2026-04-15', 'PRE2024055', FALSE),
('Folic Acid 5mg', 'Pre-conception and pregnancy supplement', 12, 'Femibion', 280.00, 95, '2026-01-20', 'FOL2024056', FALSE),
('Mefenamic Acid 500mg', 'Menstrual pain relief', 12, 'Ponstan', 180.00, 80, '2025-10-25', 'MEF2024057', TRUE),
('Iron + Folic Acid', 'Anemia prevention during pregnancy', 12, 'Sangobion', 320.00, 100, '2025-12-15', 'IRF2024058', FALSE),

-- Baby & Child Care (OTC)
('Paracetamol Syrup (Children)', 'Fever and pain relief for children', 13, 'Calpol', 180.00, 150, '2025-11-30', 'PAC2024059', FALSE),
('Gripe Water', 'Colic and gas relief for babies', 13, 'Woodward''s', 220.00, 120, '2026-02-10', 'GRI2024060', FALSE),
('Zinc Oxide Diaper Cream', 'Diaper rash treatment', 13, 'Desitin', 280.00, 95, '2026-03-20', 'ZIN2024061', FALSE),
('Oral Rehydration Salts', 'Dehydration treatment for children', 13, 'Pedialyte', 85.00, 160, '2026-05-15', 'ORS2024062', FALSE),

-- First Aid (OTC)
('Antiseptic Solution', 'Wound cleaning and disinfection', 14, 'Dettol', 180.00, 140, '2026-08-30', 'ANS2024063', FALSE),
('Adhesive Bandages (Box)', 'Assorted sizes wound plasters', 14, 'Band-Aid', 120.00, 200, '2027-12-31', 'ADH2024064', FALSE),
('Hydrogen Peroxide 3%', 'Wound cleaning and disinfectant', 14, 'Medichem', 95.00, 110, '2026-06-15', 'HYP2024065', FALSE),
('Gauze Bandage Roll', 'Sterile wound dressing', 14, 'Johnson & Johnson', 150.00, 180, '2027-12-31', 'GAU2024066', FALSE),
('Medical Tape', 'Surgical adhesive tape', 14, 'Micropore', 85.00, 220, '2027-12-31', 'MED2024067', FALSE),

-- Personal Care (OTC)
('Hand Sanitizer 500ml', 'Antibacterial hand sanitizer gel', 15, 'Purell', 280.00, 150, '2026-12-31', 'HAN2024068', FALSE),
('Thermometer Digital', 'Accurate body temperature measurement', 15, 'Omron', 850.00, 80, '2028-12-31', 'THE2024069', FALSE),
('Blood Pressure Monitor', 'Home blood pressure monitoring device', 15, 'Omron', 4500.00, 35, '2028-12-31', 'BPM2024070', FALSE),

-- Mental Health (Prescription Required)
('Sertraline 50mg', 'Antidepressant for depression and anxiety', 16, 'Zoloft', 420.00, 60, '2025-11-20', 'SER2024071', TRUE),
('Escitalopram 10mg', 'Selective serotonin reuptake inhibitor', 16, 'Lexapro', 480.00, 50, '2025-10-15', 'ESC2024072', TRUE),
('Alprazolam 0.5mg', 'Anxiety and panic disorder treatment', 16, 'Xanax', 350.00, 40, '2025-09-10', 'ALP2024073', TRUE),
('Diazepam 5mg', 'Anxiety, muscle spasm, and seizure treatment', 16, 'Valium', 280.00, 45, '2025-08-20', 'DIA2024074', TRUE);

-- Insert sample customers
INSERT INTO customers (first_name, last_name, email, password, phone, address, date_of_birth, gender, emergency_contact, medical_history) VALUES 
('John', 'Smith', 'john.smith@email.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '555-0101', '123 Main St, City, State', '1985-03-15', 'male', '555-0102', 'Diabetes Type 2'),
('Sarah', 'Johnson', 'sarah.johnson@email.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '555-0201', '456 Oak Ave, City, State', '1990-07-22', 'female', '555-0202', 'Asthma'),
('Michael', 'Brown', 'michael.brown@email.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '555-0301', '789 Pine Rd, City, State', '1978-11-08', 'male', '555-0302', 'Hypertension'),
('Emily', 'Davis', 'emily.davis@email.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '555-0401', '321 Elm St, City, State', '1995-01-30', 'female', '555-0402', 'None'),
('David', 'Wilson', 'david.wilson@email.com', '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', '555-0501', '654 Maple Dr, City, State', '1982-09-12', 'male', '555-0502', 'High Cholesterol');

-- Insert sample staff
INSERT INTO staff (first_name, last_name, email, phone, address, position, department, hire_date, salary) VALUES 
('Dr. Alice', 'Anderson', 'alice.anderson@sahanamedicals.com', '555-1001', '100 Medical Center Dr', 'Chief Pharmacist', 'Pharmacy', '2020-01-15', 75000.00),
('Robert', 'Taylor', 'robert.taylor@sahanamedicals.com', '555-1002', '200 Health St', 'Pharmacist', 'Pharmacy', '2021-03-20', 65000.00),
('Lisa', 'Martinez', 'lisa.martinez@sahanamedicals.com', '555-1003', '300 Care Ave', 'Pharmacy Technician', 'Pharmacy', '2022-06-10', 35000.00),
('James', 'Garcia', 'james.garcia@sahanamedicals.com', '555-1004', '400 Wellness Rd', 'Inventory Manager', 'Operations', '2020-11-05', 45000.00),
('Jennifer', 'Lee', 'jennifer.lee@sahanamedicals.com', '555-1005', '500 Treatment Blvd', 'Customer Service Rep', 'Customer Service', '2023-01-15', 32000.00);
