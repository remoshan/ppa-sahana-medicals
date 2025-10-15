<?php
session_start();
include '../config/database.php';
include '../config/functions.php';

// Secure authentication check
include 'auth_check.php';

$message = '';
$error_message = '';

// Handle form submissions (only status updates and delete - prescriptions created by customers)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
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
                $message = 'Prescription status updated successfully!';
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM prescriptions WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = 'Prescription deleted successfully!';
                break;
        }
    } catch (PDOException $e) {
        $error_message = 'Operation failed: ' . $e->getMessage();
    }
}

// Get prescriptions with customer information
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.doctor_name LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter)) {
    $where_conditions[] = "p.status = ?";
    $params[] = $filter;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT p.*, c.first_name, c.last_name, c.email, c.phone
    FROM prescriptions p 
    LEFT JOIN customers c ON p.customer_id = c.id 
    $where_clause
    ORDER BY p.created_at DESC
");
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// Get customers for dropdown
$stmt = $pdo->query("SELECT * FROM customers WHERE status = 'active' ORDER BY first_name, last_name ASC");
$customers = $stmt->fetchAll();

// Get medicines for prescription
$stmt = $pdo->query("SELECT * FROM medicines WHERE status = 'active' ORDER BY name ASC");
$medicines = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions Management - Sahana Medicals</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 admin-content">
                <!-- Header -->
                <div class="admin-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h3 mb-1">Prescriptions Management</h2>
                            <p class="text-muted mb-0">Review and manage customer prescription submissions</p>
                        </div>
                        <div>
                            <a href="reports.php?type=prescriptions" class="btn btn-outline-success">
                                <i class="fas fa-chart-bar me-2"></i>Generate Report
                            </a>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters and Search -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <form method="GET" class="d-flex">
                            <input type="text" class="form-control me-2" name="search" placeholder="Search prescriptions..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <a href="?filter=pending" class="btn btn-outline-warning btn-sm">Pending</a>
                            <a href="?filter=approved" class="btn btn-outline-success btn-sm">Approved</a>
                            <a href="?filter=rejected" class="btn btn-outline-danger btn-sm">Rejected</a>
                            <a href="?filter=filled" class="btn btn-outline-info btn-sm">Filled</a>
                            <a href="?" class="btn btn-outline-secondary btn-sm">All</a>
                        </div>
                    </div>
                </div>

                <!-- Prescriptions Table -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Prescription #</th>
                                        <th>Patient</th>
                                        <th>Doctor</th>
                                        <th>Diagnosis</th>
                                        <th>Date</th>
                                        <th>File</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prescriptions as $prescription): ?>
                                    <?php 
                                    $prescription_data = json_decode($prescription['medicines'], true);
                                    $medicine_count = count($prescription_data['medicines'] ?? []);
                                    ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>#PR<?php echo str_pad($prescription['id'], 4, '0', STR_PAD_LEFT); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $prescription['id']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?></strong>
                                                <?php if ($prescription['email']): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($prescription['email']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($prescription['doctor_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?>
                                                <br>
                                                <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($prescription['created_at'])); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($prescription['prescription_file'])): ?>
                                                <button type="button" class="btn btn-sm btn-success" onclick="viewPrescriptionFile('<?php echo htmlspecialchars($prescription['prescription_file']); ?>', '<?php echo htmlspecialchars($prescription['file_type']); ?>')">
                                                    <i class="fas fa-file-<?php echo $prescription['file_type'] === 'pdf' ? 'pdf' : 'image'; ?>"></i>
                                                    View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted small">No file</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" data-current-status="<?php echo $prescription['status']; ?>" onchange="updatePrescriptionStatus(<?php echo $prescription['id']; ?>, this.value)">
                                                <option value="pending" <?php echo $prescription['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="approved" <?php echo $prescription['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                <option value="rejected" <?php echo $prescription['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                <option value="filled" <?php echo $prescription['status'] === 'filled' ? 'selected' : ''; ?>>Filled</option>
                                                <option value="cancelled" <?php echo $prescription['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-info" data-prescription='<?= json_encode($prescription, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT); ?>' onclick="viewPrescriptionDetails(this)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo $prescription['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Prescription Details Modal -->
    <div class="modal fade" id="prescriptionDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Prescription Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="prescriptionDetailsContent">
                    <!-- Prescription details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Prescription File Viewer Modal -->
    <div class="modal fade" id="prescriptionFileModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Prescription File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center" id="prescriptionFileContent" style="min-height: 400px;">
                    <!-- File content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <a href="#" id="downloadFileBtn" class="btn btn-primary" download>
                        <i class="fas fa-download me-2"></i>Download File
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="deletePrescriptionForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="table" value="prescriptions">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteId">
                        <p>Are you sure you want to delete this prescription? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectionReasonModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="rejectionForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Rejection Reason</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" id="rejectionPrescriptionId">
                        <input type="hidden" name="status" value="rejected">
                        
                        <div class="mb-3">
                            <label class="form-label">Please select a reason for rejection: *</label>
                            <select class="form-select" id="rejectionReasonSelect" required onchange="handleRejectionReasonChange()">
                                <option value="">-- Select Reason --</option>
                                <option value="Doctor seal is not present on the prescription">Doctor seal is not present on the prescription</option>
                                <option value="Prescription image is blurred or unclear">Prescription image is blurred or unclear</option>
                                <option value="Doctor signature is missing">Doctor signature is missing</option>
                                <option value="Prescription date is expired or invalid">Prescription date is expired or invalid</option>
                                <option value="Prescription details are incomplete">Prescription details are incomplete</option>
                                <option value="Prescription appears to be altered or tampered">Prescription appears to be altered or tampered</option>
                                <option value="Doctor license information cannot be verified">Doctor license information cannot be verified</option>
                                <option value="custom">Other (Please specify)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="customReasonDiv" style="display: none;">
                            <label for="customRejectionReason" class="form-label">Custom Reason:</label>
                            <textarea class="form-control" id="customRejectionReason" name="admin_notes" rows="3" placeholder="Please specify the reason..."></textarea>
                        </div>
                        
                        <input type="hidden" name="admin_notes" id="finalRejectionReason">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Prescription</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden form for status update -->
    <form id="updateStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" id="updateStatusId">
        <input type="hidden" name="status" id="updateStatusValue">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/crud-ajax.js"></script>
    <script src="assets/js/universal-crud.js"></script>
    <script>
        function updatePrescriptionStatus(prescriptionId, status) {
            // If status is rejected, show the rejection reason modal
            if (status === 'rejected') {
                document.getElementById('rejectionPrescriptionId').value = prescriptionId;
                const modal = new bootstrap.Modal(document.getElementById('rejectionReasonModal'));
                modal.show();
                // Reset the select dropdown to the current status
                event.target.value = event.target.getAttribute('data-current-status');
            } else {
                // Use AJAX to update status
                updatePrescriptionStatusAjax(prescriptionId, status);
            }
        }
        
        function handleRejectionReasonChange() {
            const select = document.getElementById('rejectionReasonSelect');
            const customDiv = document.getElementById('customReasonDiv');
            const customTextarea = document.getElementById('customRejectionReason');
            const finalInput = document.getElementById('finalRejectionReason');
            
            if (select.value === 'custom') {
                customDiv.style.display = 'block';
                customTextarea.required = true;
                finalInput.name = '';  // Remove name so textarea is used
                customTextarea.name = 'admin_notes';
            } else if (select.value) {
                customDiv.style.display = 'none';
                customTextarea.required = false;
                customTextarea.name = '';  // Remove name from textarea
                finalInput.name = 'admin_notes';  // Add name to hidden input
                finalInput.value = select.value;
            } else {
                customDiv.style.display = 'none';
                customTextarea.required = false;
                finalInput.value = '';
            }
        }
        
        // Store current status in data attribute for all status selects
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.status-select').forEach(function(select) {
                select.setAttribute('data-current-status', select.value);
            });
        });
        
        function viewPrescriptionDetails(buttonEl) {
            const modal = new bootstrap.Modal(document.getElementById('prescriptionDetailsModal'));
            const content = document.getElementById('prescriptionDetailsContent');
            const prescription = JSON.parse(buttonEl.getAttribute('data-prescription'));
            const prescriptionData = prescription.medicines ? JSON.parse(prescription.medicines) : { medicines: [] };
            let medicinesHtml = '';
            if (prescriptionData.medicines && prescriptionData.medicines.length > 0) {
                medicinesHtml = prescriptionData.medicines.map(med => `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        ${med.name}
                        <span class="badge bg-primary rounded-pill">Qty: ${med.quantity}</span>
                    </li>
                `).join('');
            } else {
                medicinesHtml = '<li class="list-group-item text-muted">No medicines specified</li>';
            }
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Patient Information</h6>
                        <p><strong>Name:</strong> ${prescription.first_name} ${prescription.last_name}</p>
                        <p><strong>Email:</strong> ${prescription.email || 'N/A'}</p>
                        <p><strong>Phone:</strong> ${prescription.phone || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Doctor Information</h6>
                        <p><strong>Doctor:</strong> ${prescription.doctor_name}</p>
                        <p><strong>Date:</strong> ${new Date(prescription.prescription_date).toLocaleDateString()}</p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Prescribed Medicines</h6>
                        <ul class="list-group">
                            ${medicinesHtml}
                        </ul>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Instructions</h6>
                        <p>${prescription.instructions || 'No special instructions'}</p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Prescription File</h6>
                        ${prescription.prescription_file ? `
                            <button type="button" class="btn btn-success" onclick="viewPrescriptionFile('${prescription.prescription_file}', '${prescription.file_type}')">
                                <i class="fas fa-file-${prescription.file_type === 'pdf' ? 'pdf' : 'image'}"></i> View Uploaded File
                            </button>
                        ` : '<p class="text-muted">No file uploaded</p>'}
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Status Information</h6>
                        <p><strong>Status:</strong> <span class="badge bg-${prescription.status === 'pending' ? 'warning' : (prescription.status === 'filled' ? 'success' : 'danger')}">${prescription.status.charAt(0).toUpperCase() + prescription.status.slice(1)}</span></p>
                        <p><strong>Created:</strong> ${new Date(prescription.created_at).toLocaleDateString()}</p>
                    </div>
                </div>
            `;
            
            modal.show();
        }
        
        function viewPrescriptionFile(filePath, fileType) {
            const modal = new bootstrap.Modal(document.getElementById('prescriptionFileModal'));
            const content = document.getElementById('prescriptionFileContent');
            const downloadBtn = document.getElementById('downloadFileBtn');
            
            // Set download link
            downloadBtn.href = '../' + filePath;
            
            // Display file based on type
            if (fileType === 'pdf') {
                content.innerHTML = `
                    <embed src="../${filePath}" type="application/pdf" width="100%" height="600px" />
                    <p class="mt-3 text-muted">If the PDF doesn't display, <a href="../${filePath}" target="_blank">click here to open it</a></p>
                `;
            } else {
                content.innerHTML = `
                    <img src="../${filePath}" class="img-fluid" alt="Prescription" style="max-height: 600px; object-fit: contain;" />
                `;
            }
            
            modal.show();
        }
        
        function confirmDelete(id) {
            document.getElementById('deleteId').value = id;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Handle delete form submission via AJAX
        document.getElementById('deletePrescriptionForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(result.message || 'Delete failed', 'error');
                }
            } catch (error) {
                showToast('An error occurred while deleting', 'error');
            }
        });
    </script>
</body>
</html>
