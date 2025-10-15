# 📊 Reports & Analytics System

## Overview
Complete report generation system with PDF and Excel export capabilities showing comprehensive pharmacy summaries.

---

## Features

### 1. **Comprehensive Summary Dashboard**
Real-time overview of all pharmacy operations:
- ✅ Revenue statistics (total, paid, pending)
- ✅ Order statistics
- ✅ Medicine inventory status
- ✅ Customer count
- ✅ Prescription analytics
- ✅ Inventory alerts

### 2. **Export Capabilities**

#### **Export as PDF**
- Professional formatted PDF report
- Complete with all statistics and tables
- Includes company header
- Date range and generation time
- One-click download
- File name: `Sahana_Medicals_Report_YYYY-MM-DD.pdf`

#### **Export as Excel**
- Multi-sheet Excel workbook
- Sheet 1: Summary (KPIs, revenue, prescriptions, alerts)
- Sheet 2: Top Selling Medicines
- Sheet 3: Recent Orders
- Fully formatted and ready for analysis
- File name: `Sahana_Medicals_Report_YYYY-MM-DD.xlsx`

### 3. **Date Range Filtering**
- Filter reports by custom date range
- Default: Current month to today
- Update all statistics based on selected dates
- Real-time filtering

---

## Report Sections

### **Key Performance Indicators (KPIs)**
1. **Total Revenue** - Sum of all orders in date range
2. **Total Orders** - Number of orders placed
3. **Total Medicines** - Active medicines in inventory
4. **Active Customers** - Total active customer accounts

### **Revenue Breakdown**
- Paid Revenue (completed payments)
- Pending Revenue (awaiting payment)
- Total Revenue summary

### **Prescription Statistics**
- Pending prescriptions
- Approved prescriptions
- Rejected prescriptions
- Filled prescriptions
- Total count

### **Inventory Alerts**
- ⚠️ Low Stock: Medicines with quantity < 10
- 🛑 Expired: Past expiry date medicines
- ⏰ Expiring Soon: Within 30 days

### **Top 10 Selling Medicines**
Table showing:
- Medicine name
- Quantity sold
- Revenue generated

### **Categories Summary**
Overview of all medicine categories:
- Number of medicines per category
- Total quantity in each category

### **Recent Orders**
Last 20 orders showing:
- Order number
- Customer name
- Date
- Amount
- Order status
- Payment status

---

## How to Use

### **Access Reports:**
1. Click **"Reports"** in the admin sidebar
2. Report page loads with current month data

### **Filter by Date:**
1. Select **Start Date**
2. Select **End Date**
3. Click **"Apply Filter"**
4. Report updates instantly

### **Export as PDF:**
1. Click **"Export as PDF"** button
2. Wait for generation (shows loading spinner)
3. PDF downloads automatically
4. Open and review/print

### **Export as Excel:**
1. Click **"Export as Excel"** button
2. Excel file downloads instantly
3. Open in Microsoft Excel or Google Sheets
4. Analyze data with pivot tables, charts, etc.

---

## Use Cases

### **Daily Management**
- Check daily revenue
- Monitor pending prescriptions
- Review low stock alerts
- Track new orders

### **Weekly Reviews**
- Analyze weekly sales trends
- Identify top selling medicines
- Review customer activity
- Plan inventory restocking

### **Monthly Reports**
- Generate monthly revenue reports
- Export for accounting
- Performance analysis
- Stakeholder presentations

### **Custom Analysis**
- Export to Excel
- Create custom charts
- Perform advanced analytics
- Compare different periods

---

## Technical Details

### **PDF Generation**
- Library: html2pdf.js
- Format: A4 portrait
- Quality: High (0.98)
- Includes all visual elements
- Print-optimized styles

### **Excel Generation**
- Library: SheetJS (xlsx)
- Format: .xlsx (Excel 2007+)
- Multiple worksheets
- Formatted data
- Compatible with all spreadsheet software

### **Data Sources**
```sql
- medicines table (inventory, categories)
- customers table (active count)
- orders table (revenue, order count)
- order_items table (sales analytics)
- prescriptions table (prescription stats)
```

### **Date Filtering**
All queries filtered by:
```php
WHERE DATE(created_at) BETWEEN $start_date AND $end_date
```

---

## Report Examples

### **Summary Section:**
```
Total Revenue: LKR 1,250,000.00
Total Orders: 347
Total Medicines: 1,234
Active Customers: 856
```

### **Revenue Breakdown:**
```
Paid Revenue: LKR 980,000.00
Pending Revenue: LKR 270,000.00
Total Revenue: LKR 1,250,000.00
```

### **Top Medicines:**
```
1. Paracetamol 500mg - 450 units - LKR 45,000
2. Amoxicillin 250mg - 320 units - LKR 96,000
3. Aspirin 75mg - 280 units - LKR 42,000
```

---

## Benefits

✅ **Data-Driven Decisions** - Make informed business decisions  
✅ **Time Saving** - Automated report generation  
✅ **Professional Reports** - Share with stakeholders  
✅ **Inventory Management** - Proactive stock control  
✅ **Performance Tracking** - Monitor KPIs easily  
✅ **Flexible Analysis** - Excel export for custom insights  
✅ **Compliance** - Ready for audits and reviews  

---

## File Locations

- **Main Page:** `admin/reports.php`
- **Menu Link:** `admin/includes/sidebar.php`
- **Documentation:** `admin/REPORTS_SYSTEM.md`

---

## Dependencies

### JavaScript Libraries:
- **html2pdf.js** - PDF generation (CDN)
- **SheetJS (xlsx)** - Excel generation (CDN)
- **Bootstrap 5** - UI framework
- **Font Awesome** - Icons

All libraries loaded from CDN - no installation required!

---

## Future Enhancements (Optional)

- 📈 Visual charts and graphs
- 📧 Email reports automatically
- 🔄 Scheduled report generation
- 📊 Custom report builder
- 🎯 Sales forecasting
- 💹 Profit margin analysis

---

## Screenshots

**Main Dashboard:**
- KPI cards with icons
- Revenue breakdown
- Prescription statistics
- Inventory alerts

**Export Buttons:**
- PDF button (red, with PDF icon)
- Excel button (green, with Excel icon)
- Located at top right of page

**Tables:**
- Top selling medicines (sortable)
- Recent orders (detailed)
- Categories summary (grouped)

---

*Created: Now*  
*Status: ✅ Fully Functional*  
*Export Formats: PDF + Excel*  
*Real-time Data: Yes*

