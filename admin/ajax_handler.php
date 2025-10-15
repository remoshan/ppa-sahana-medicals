<?php
session_start();
include '../config/database.php';

// Secure authentication check
include 'auth_check.php';

// Set JSON response header
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = $_POST['action'] ?? '';
    $table = $_POST['table'] ?? '';

    if (empty($action) || empty($table)) {
        throw new Exception('Missing required parameters');
    }

    switch ($table) {
        case 'categories':
            handleCategories($pdo, $action);
            break;
        case 'medicines':
            handleMedicines($pdo, $action);
            break;
        case 'customers':
            handleCustomers($pdo, $action);
            break;
        case 'staff':
            handleStaff($pdo, $action);
            break;
        case 'suppliers':
            handleSuppliers($pdo, $action);
            break;
        case 'prescriptions':
            handlePrescriptions($pdo, $action);
            break;
        case 'orders':
            handleOrders($pdo, $action);
            break;
        case 'payments':
            handlePayments($pdo, $action);
            break;
        case 'purchase_orders':
            handlePurchaseOrders($pdo, $action);
            break;
        case 'supplier_products':
            handleSupplierProducts($pdo, $action);
            break;
        default:
            throw new Exception('Invalid table specified');
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    echo json_encode($response);
    exit;
}

function handleCategories($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'create':
            $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['status']]);
            $response['success'] = true;
            $response['message'] = 'Category added successfully!';
            $response['id'] = $pdo->lastInsertId();
            break;
            
        case 'update':
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['status'], $_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Category updated successfully!';
            break;
            
        case 'delete':
            $check_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medicines WHERE category_id = ?");
            $check_stmt->execute([$_POST['id']]);
            $count = $check_stmt->fetch()['count'];
            
            if ($count > 0) {
                throw new Exception('Cannot delete category. It contains ' . $count . ' medicine(s).');
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Category deleted successfully!';
            break;
    }
    
    echo json_encode($response);
}

function handleMedicines($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'create':
            $stmt = $pdo->prepare("
                INSERT INTO medicines (name, description, category_id, manufacturer, price, quantity, expiry_date, batch_number, prescription_required, status, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['name'], $_POST['description'], $_POST['category_id'], $_POST['manufacturer'],
                $_POST['price'], $_POST['quantity'], $_POST['expiry_date'], $_POST['batch_number'],
                isset($_POST['prescription_required']) ? 1 : 0, $_POST['status'], $_POST['image_url'] ?? ''
            ]);
            $response['success'] = true;
            $response['message'] = 'Medicine added successfully!';
            $response['id'] = $pdo->lastInsertId();
            break;
            
        case 'update':
            $stmt = $pdo->prepare("
                UPDATE medicines 
                SET name = ?, description = ?, category_id = ?, manufacturer = ?, price = ?, quantity = ?, 
                    expiry_date = ?, batch_number = ?, prescription_required = ?, status = ?, image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['name'], $_POST['description'], $_POST['category_id'], $_POST['manufacturer'],
                $_POST['price'], $_POST['quantity'], $_POST['expiry_date'], $_POST['batch_number'],
                isset($_POST['prescription_required']) ? 1 : 0, $_POST['status'], $_POST['image_url'] ?? '', $_POST['id']
            ]);
            $response['success'] = true;
            $response['message'] = 'Medicine updated successfully!';
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM medicines WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Medicine deleted successfully!';
            break;
    }
    
    echo json_encode($response);
}

function handleCustomers($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'create':
            $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO customers (first_name, last_name, email, password, phone, address, date_of_birth, gender, emergency_contact, medical_history, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $password_hash,
                $_POST['phone'], $_POST['address'], $_POST['date_of_birth'], $_POST['gender'],
                $_POST['emergency_contact'], $_POST['medical_history'], $_POST['status']
            ]);
            $response['success'] = true;
            $response['message'] = 'Customer added successfully!';
            $response['id'] = $pdo->lastInsertId();
            break;
            
        case 'update':
            if (!empty($_POST['password'])) {
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET first_name = ?, last_name = ?, email = ?, password = ?, phone = ?, address = ?, 
                        date_of_birth = ?, gender = ?, emergency_contact = ?, medical_history = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['first_name'], $_POST['last_name'], $_POST['email'], $password_hash,
                    $_POST['phone'], $_POST['address'], $_POST['date_of_birth'], $_POST['gender'],
                    $_POST['emergency_contact'], $_POST['medical_history'], $_POST['status'], $_POST['id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE customers 
                    SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, 
                        date_of_birth = ?, gender = ?, emergency_contact = ?, medical_history = ?, status = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['first_name'], $_POST['last_name'], $_POST['email'],
                    $_POST['phone'], $_POST['address'], $_POST['date_of_birth'], $_POST['gender'],
                    $_POST['emergency_contact'], $_POST['medical_history'], $_POST['status'], $_POST['id']
                ]);
            }
            $response['success'] = true;
            $response['message'] = 'Customer updated successfully!';
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Customer deleted successfully!';
            break;
    }
    
    echo json_encode($response);
}

function handleStaff($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'create':
            $stmt = $pdo->prepare("
                INSERT INTO staff (first_name, last_name, email, phone, address, position, department, hire_date, salary, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
                $_POST['address'], $_POST['position'], $_POST['department'], $_POST['hire_date'],
                $_POST['salary'], $_POST['status']
            ]);
            $response['success'] = true;
            $response['message'] = 'Staff member added successfully!';
            $response['id'] = $pdo->lastInsertId();
            break;
            
        case 'update':
            $stmt = $pdo->prepare("
                UPDATE staff 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?, 
                    position = ?, department = ?, hire_date = ?, salary = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['email'], $_POST['phone'],
                $_POST['address'], $_POST['position'], $_POST['department'], $_POST['hire_date'],
                $_POST['salary'], $_POST['status'], $_POST['id']
            ]);
            $response['success'] = true;
            $response['message'] = 'Staff member updated successfully!';
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM staff WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Staff member deleted successfully!';
            break;
    }
    
    echo json_encode($response);
}

function handleSuppliers($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'create':
        case 'create_supplier':
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (company_name, contact_person, email, phone, address, city, country, tax_id, payment_terms, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['company_name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'],
                $_POST['address'], $_POST['city'], $_POST['country'], $_POST['tax_id'],
                $_POST['payment_terms'], $_POST['status'], $_POST['notes']
            ]);
            $response['success'] = true;
            $response['message'] = 'Supplier added successfully!';
            $response['id'] = $pdo->lastInsertId();
            break;
            
        case 'update':
        case 'update_supplier':
            $stmt = $pdo->prepare("
                UPDATE suppliers 
                SET company_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, 
                    city = ?, country = ?, tax_id = ?, payment_terms = ?, status = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['company_name'], $_POST['contact_person'], $_POST['email'], $_POST['phone'],
                $_POST['address'], $_POST['city'], $_POST['country'], $_POST['tax_id'],
                $_POST['payment_terms'], $_POST['status'], $_POST['notes'], $_POST['id']
            ]);
            $response['success'] = true;
            $response['message'] = 'Supplier updated successfully!';
            break;
            
        case 'delete':
        case 'delete_supplier':
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Supplier deleted successfully!';
            break;
    }
    
    echo json_encode($response);
}

function handlePrescriptions($pdo, $action) {
    global $response;
    
    switch ($action) {
        case 'update_status':
            $admin_notes = $_POST['admin_notes'] ?? null;
            if ($admin_notes) {
                $stmt = $pdo->prepare("UPDATE prescriptions SET status = ?, admin_notes = ? WHERE id = ?");
                $stmt->execute([$_POST['status'], $admin_notes, $_POST['id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE prescriptions SET status = ? WHERE id = ?");
                $stmt->execute([$_POST['status'], $_POST['id']]);
            }
            $response['success'] = true;
            $response['message'] = 'Prescription status updated successfully!';
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception('Prescription ID is required');
            }
            $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
            $stmt->execute([$id]);
            $response['success'] = true;
            $response['message'] = 'Prescription deleted successfully!';
            break;
            
        default:
            throw new Exception('Invalid action for prescriptions');
    }
    
    echo json_encode($response);
}
    
function handleOrders($pdo, $action) {
    global $response;
    switch ($action) {
        case 'create_order':
            // Expect: customer_id, payment_method, items[] (medicine_id, quantity, unit_price)
            $pdo->beginTransaction();
            try {
                $customerId = (int)$_POST['customer_id'];
                $paymentMethod = $_POST['payment_method'] ?? 'cash_on_delivery';
                $items = json_decode($_POST['items'] ?? '[]', true) ?: [];
                if (!$customerId || empty($items)) {
                    throw new Exception('Missing customer or items');
                }
                // Calculate totals
                $total = 0.0;
                foreach ($items as $it) {
                    $qty = (int)($it['quantity'] ?? 0);
                    $price = (float)($it['unit_price'] ?? 0);
                    if ($qty <= 0 || $price < 0) continue;
                    $total += $qty * $price;
                }
                // Generate order number
                $orderNumber = 'ORD-' . time();
                // Insert order
                $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_number, total_amount, status, payment_status, payment_method) VALUES (?, ?, ?, 'pending', 'pending', ?)");
                $stmt->execute([$customerId, $orderNumber, $total, $paymentMethod]);
                $orderId = (int)$pdo->lastInsertId();
                // Insert items
                $stmtItem = $pdo->prepare("INSERT INTO order_items (order_id, medicine_id, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)");
                foreach ($items as $it) {
                    $medicineId = (int)$it['medicine_id'];
                    $qty = (int)$it['quantity'];
                    $price = (float)$it['unit_price'];
                    if ($medicineId && $qty > 0) {
                        $stmtItem->execute([$orderId, $medicineId, $qty, $price, $qty * $price]);
                    }
                }
                // Create a pending customer payment linked to the order
                $paymentNumber = 'PAY-' . time();
                $stmtPay = $pdo->prepare("INSERT INTO customer_payments (payment_number, order_id, customer_id, amount, payment_status, payment_method, payment_date) VALUES (?, ?, ?, ?, 'pending', ?, NOW())");
                $stmtPay->execute([$paymentNumber, $orderId, $customerId, $total, $paymentMethod]);
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = 'Order created successfully!';
                $response['order_id'] = $orderId;
            } catch (Exception $ex) {
                $pdo->rollBack();
                $response['success'] = false;
                $response['message'] = 'Create order failed: ' . $ex->getMessage();
            }
            break;
        case 'update_status':
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Order status updated successfully!';
            break;
        case 'update_payment_status':
            $order_id = $_POST['id'];
            $payment_status = $_POST['payment_status'];
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = ?");
            $stmt->execute([$payment_status, $order_id]);
            $mapped = $payment_status === 'paid' ? 'completed' : $payment_status;
            $stmt = $pdo->prepare("UPDATE customer_payments SET payment_status = ? WHERE order_id = ?");
            $stmt->execute([$mapped, $order_id]);
            $response['success'] = true;
            $response['message'] = 'Payment status updated successfully!';
            break;
        case 'delete':
            // Delete payments tied to order
            $stmt = $pdo->prepare("DELETE FROM customer_payments WHERE order_id = ?");
            $stmt->execute([$_POST['id']]);
            // Delete order items
            $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $stmt->execute([$_POST['id']]);
            // Delete order
            $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Order deleted successfully!';
            break;
    }
    echo json_encode($response);
}

function handlePayments($pdo, $action) {
    global $response;
    if ($action === 'update_status') {
        $stmt = $pdo->prepare("UPDATE customer_payments SET payment_status = ?, notes = ?, transaction_id = ? WHERE id = ?");
        $stmt->execute([$_POST['payment_status'], $_POST['notes'] ?? '', $_POST['transaction_id'] ?? '', $_POST['payment_id']]);
        $mapped = $_POST['payment_status'] === 'completed' ? 'paid' : $_POST['payment_status'];
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ? WHERE id = (SELECT order_id FROM customer_payments WHERE id = ?)");
        $stmt->execute([$mapped, $_POST['payment_id']]);
        $response['success'] = true;
        $response['message'] = 'Payment updated successfully!';
    }
    echo json_encode($response);
}

function handlePurchaseOrders($pdo, $action) {
    global $response;
    switch ($action) {
        case 'update_status':
            $stmt = $pdo->prepare("UPDATE purchase_orders SET status = ? WHERE id = ?");
            $stmt->execute([$_POST['status'], $_POST['po_id']]);
            $response['success'] = true;
            $response['message'] = 'Purchase order status updated!';
            break;
        case 'create_po':
            // Not implementing full create via AJAX here; handled in page form
            $response['success'] = false;
            $response['message'] = 'Use page form for creating POs.';
            break;
        case 'receive_stock':
            $response['success'] = false;
            $response['message'] = 'Use the receive stock modal form.';
            break;
    }
    echo json_encode($response);
}

function handleSupplierProducts($pdo, $action) {
    global $response;
    switch ($action) {
        case 'add_product':
            $stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, medicine_id, supplier_price, minimum_order_quantity, lead_time_days, is_preferred, status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE supplier_price = VALUES(supplier_price), minimum_order_quantity = VALUES(minimum_order_quantity), lead_time_days = VALUES(lead_time_days), is_preferred = VALUES(is_preferred), status = VALUES(status)");
            $stmt->execute([
                $_POST['supplier_id'], $_POST['medicine_id'], $_POST['supplier_price'], $_POST['minimum_order_quantity'], $_POST['lead_time_days'], isset($_POST['is_preferred']) ? 1 : 0, $_POST['status']
            ]);
            $response['success'] = true;
            $response['message'] = 'Product added successfully!';
            break;
        case 'remove_product':
            $stmt = $pdo->prepare("DELETE FROM supplier_products WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $response['success'] = true;
            $response['message'] = 'Product removed successfully!';
            break;
    }
    echo json_encode($response);
}

?>
