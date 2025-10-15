<?php
session_start();
include '../config/database.php';
include '../config/functions.php';
include 'auth_check.php';

$po_id = $_GET['po_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT po.*, s.company_name as supplier_name
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.id
    WHERE po.id = ?
");
$stmt->execute([$po_id]);
$po = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT poi.*, m.name as medicine_name
    FROM purchase_order_items poi
    JOIN medicines m ON poi.medicine_id = m.id
    WHERE poi.purchase_order_id = ?
");
$stmt->execute([$po_id]);
$items = $stmt->fetchAll();

if (!$po) {
    echo '<p class="text-danger">Purchase order not found.</p>';
    exit;
}
?>

<input type="hidden" name="action" value="receive_stock">
<input type="hidden" name="po_id" value="<?php echo $po['id']; ?>">

<div class="alert alert-info">
    <strong>PO #<?php echo htmlspecialchars($po['order_number']); ?></strong> - 
    <?php echo htmlspecialchars($po['supplier_name']); ?>
</div>

<div class="table-responsive">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Medicine</th>
                <th>Ordered</th>
                <th>Already Received</th>
                <th>Receive Now</th>
                <th>Batch Number</th>
                <th>Expiry Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): 
                $remaining = $item['quantity_ordered'] - $item['quantity_received'];
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['medicine_name']); ?></td>
                <td><?php echo $item['quantity_ordered']; ?></td>
                <td><?php echo $item['quantity_received']; ?></td>
                <td>
                    <input type="number" 
                           class="form-control form-control-sm" 
                           name="items[<?php echo $item['id']; ?>][quantity]" 
                           max="<?php echo $remaining; ?>" 
                           value="<?php echo $remaining; ?>" 
                           min="0">
                </td>
                <td>
                    <input type="text" 
                           class="form-control form-control-sm" 
                           name="items[<?php echo $item['id']; ?>][batch_number]" 
                           placeholder="Batch #">
                </td>
                <td>
                    <input type="date" 
                           class="form-control form-control-sm" 
                           name="items[<?php echo $item['id']; ?>][expiry_date]"
                           value="<?php echo date('Y-m-d', strtotime('+2 years')); ?>">
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="alert alert-warning">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Important:</strong> When you confirm receipt, the medicine stock will be automatically updated in the system.
</div>

