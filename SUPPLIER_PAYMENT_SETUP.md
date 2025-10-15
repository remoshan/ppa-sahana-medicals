# Supplier & Payment Management System - Setup Guide

## 🚀 Features Added

### 1. **Suppliers Management**
- Complete supplier database with contact information
- Active/Inactive/Blocked status management
- Payment terms tracking

### 2. **Supplier Products Integration** ⭐
- Link medicines to suppliers with custom pricing
- Set minimum order quantities and lead times
- Mark preferred suppliers for each medicine
- Track supplier vs retail pricing margins

### 3. **Purchase Orders (PO) System**
- Create purchase orders to suppliers
- Auto-generated PO numbers (PO-1, PO-2, etc.)
- Track order status: Draft → Pending → Confirmed → Partially Received → Received
- Monitor expected vs actual delivery dates

### 4. **Stock Receiving & Auto-Update** 🎯
**This is the key integration!** When you receive stock from a supplier:
- Update the quantity received in the purchase order
- **Automatically updates medicine stock in the Medicines page**
- Track batch numbers and expiry dates
- Partial receiving supported (receive items in multiple shipments)

### 5. **Customer Payment Management** 💰
- Automatically records payment when customer checks out
- Track payment methods (cash on delivery, bank transfer, credit card, etc.)
- View all customer payment history
- Update payment status (pending, completed, failed, refunded)
- Monthly payment statistics dashboard
- Link payments to specific orders

## 📋 Installation Steps

### Step 1: Run Database Schema
Execute the SQL file to create all required tables:

```bash
# In phpMyAdmin or MySQL command line, run:
mysql -u root -p PPA_Sahana_Medicals < config/suppliers_schema.sql
```

Or manually:
1. Open phpMyAdmin
2. Select your database: `PPA_Sahana_Medicals`
3. Go to SQL tab
4. Copy and paste the contents of `config/suppliers_schema.sql`
5. Click "Go" to execute

### Step 2: Verify Tables Created
Check that these new tables exist:
- `suppliers`
- `supplier_products`
- `purchase_orders`
- `purchase_order_items`
- `customer_payments` ← **For customer checkout payments**

### Step 3: Access New Pages
The following pages are now available in the admin panel:

**Main Pages:**
- **Suppliers** - http://localhost/PPA%20(Sahana%20Medicals)/admin/suppliers.php
- **Payments** - http://localhost/PPA%20(Sahana%20Medicals)/admin/payments.php *(Customer Payments)*
- **Purchase Orders** - http://localhost/PPA%20(Sahana%20Medicals)/admin/purchase_orders.php

**Related Pages:**
- Supplier Products - `admin/supplier_products.php?supplier_id=X`

## 🔄 How the Stock Integration Works

### Workflow Example:

1. **Add a Supplier**
   - Go to Suppliers page
   - Click "Add Supplier"
   - Fill in company details, payment terms
   - Save

2. **Link Products to Supplier**
   - In Suppliers table, click the box icon (Manage Products)
   - Click "Add Product"
   - Select medicine
   - Enter supplier price (system suggests 65% of retail price)
   - Set minimum order quantity and lead time
   - Save

3. **Create Purchase Order**
   - Go to Purchase Orders page (or click "Purchase Orders" button from Suppliers)
   - Click "New Purchase Order"
   - Select supplier (only products from this supplier will appear)
   - Add items with quantities
   - System calculates totals
   - Create PO

4. **Receive Stock (Critical Step!)** 🚀
   - When supplier delivers, go to Purchase Orders
   - Find the PO and click the box-open icon
   - Enter quantities received for each item
   - Add batch number and expiry date
   - Click "Confirm Receipt & Update Stock"
   - **✅ Medicine stock is automatically updated!**

5. **Customer Payments (Automatic)** 💳
   - When a customer completes checkout on the website
   - Payment record is **automatically created** in `customer_payments` table
   - Payment shows up in Admin → Payments page
   - Admin can view, update status, add transaction IDs

## 🎯 Key Benefits

### For Stock Management:
- ✅ No manual stock updates needed
- ✅ Automatic quantity tracking from supplier receipts
- ✅ Batch number and expiry date recording
- ✅ Real-time stock visibility in Medicines page

### For Financial Tracking:
- ✅ Complete customer payment history
- ✅ Automatic payment recording at checkout
- ✅ Payment status tracking (pending, completed, failed, refunded)
- ✅ Monthly financial statistics
- ✅ Search and filter payments

### For Supplier Relations:
- ✅ Track multiple suppliers per medicine
- ✅ Compare supplier pricing
- ✅ Monitor supplier performance (delivery times)
- ✅ Maintain supplier contact details

## 📊 Sample Data Included

The schema includes 5 sample suppliers:
1. MediPharm Distributors
2. HealthCare Supplies Ltd
3. Global Pharma International
4. Local Medicine Distributors
5. Premium Medical Supplies

These are automatically linked to some existing medicines with sample pricing.

## 💳 Customer Payment Flow

```
Customer adds items to cart
        ↓
Goes to checkout
        ↓
Fills shipping address & payment method
        ↓
Places order
        ↓
✅ Order created in database
✅ Payment record automatically created
✅ Medicine stock automatically reduced
✅ Customer redirected to "My Orders"
        ↓
Admin can view payment in Payments page
Admin can update payment status if needed
```

## 🔐 Security Features

- All pages use `auth_check.php` - admin-only access
- Session validation on all AJAX endpoints
- SQL injection protection via PDO prepared statements
- Transaction rollback on errors

## 🛠️ Troubleshooting

### If medicines don't show in PO creation:
1. Make sure you've linked products to the supplier first
2. Go to Suppliers → Click box icon → Add products
3. Products must be in "active" status

### If stock doesn't update:
1. Check that the PO status is not already "Received"
2. Verify the medicine IDs match in supplier_products
3. Check browser console for JavaScript errors
4. Review PHP error logs

### If customer payments don't appear:
1. Ensure `customer_payments` table was created
2. Check if checkout completed successfully
3. Look for any errors in browser console during checkout
4. Verify order was created in `orders` table

### Database errors:
- Ensure all tables were created successfully
- Check foreign key constraints
- Verify the `medicines`, `customers`, and `orders` tables exist and have data

## 📝 Payment Management Features

Admin can:
- View all customer payments
- Search by payment number, order number, or customer name
- Filter by status (all, completed, pending, failed, refunded)
- Update payment status
- Add transaction IDs and notes
- View monthly statistics (total payments, completed, pending)
- See payment method details
- Link to related orders

## 📈 Reports Available

The Payments page shows:
- Total payments this month
- Completed payments amount
- Pending payments amount
- Total transaction count
- Individual payment details with customer info

## 🎓 Training Tips

For staff using the system:
1. **Supplier Management:**
   - Start by adding 1-2 suppliers
   - Link a few products to practice
   - Create a test purchase order
   - Practice receiving stock and verify medicine quantities update

2. **Payment Tracking:**
   - Customer payments are automatic - no manual entry needed
   - Review the Payments page daily
   - Update payment status for Cash on Delivery orders when received
   - Add transaction IDs for bank transfers/online payments

---

**System Integration Complete!** 🎉

- ✅ Suppliers → Medicines integration (automatic stock updates)
- ✅ Checkout → Payments integration (automatic payment recording)
- ✅ Admin can manage both suppliers and customer payments from one panel

The system now provides complete tracking from supplier to customer!
