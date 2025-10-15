/**
 * CRUD AJAX Operations - No Page Refresh
 * Handles create, update, delete operations for all admin pages
 */

// Toast notification system
function showToast(message, type = 'success') {
    // Remove existing toasts
    const existingToast = document.getElementById('crudToast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    // Create toast element
    const toastId = 'crudToast';
    const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${icon} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 3000 });
    toast.show();
    
    // Remove from DOM after hiding
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Handle form submission with AJAX
function handleAjaxForm(formId, table, onSuccess = null) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        formData.append('table', table);
        
        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                
                // Close modal if exists
                const modalElement = form.closest('.modal');
                if (modalElement) {
                    const modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
                
                // Call custom success callback
                if (onSuccess) {
                    onSuccess(data);
                } else {
                    // Default: reload page after short delay
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            console.error('Error:', error);
        }
    });
}

// Handle delete with AJAX
function handleAjaxDelete(id, table, onSuccess = null) {
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('table', table);
    
    fetch('ajax_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            
            // Call custom success callback
            if (onSuccess) {
                onSuccess(data);
            } else {
                // Default: reload page after short delay
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    });
}

// Initialize AJAX for prescription status update
function initPrescriptionStatusAjax() {
    // Handle status change with AJAX for prescriptions
    window.updatePrescriptionStatusAjax = async function(prescriptionId, status) {
        if (status === 'rejected') {
            // Show rejection modal (handled by existing code)
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'update_status');
        formData.append('id', prescriptionId);
        formData.append('status', status);
        formData.append('table', 'prescriptions');
        
        try {
            const response = await fetch('ajax_handler.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showToast(data.message, 'success');
                // Update the data-current-status attribute
                const select = event.target;
                select.setAttribute('data-current-status', status);
            } else {
                showToast(data.message, 'error');
                // Reset select to previous value
                event.target.value = event.target.getAttribute('data-current-status');
            }
        } catch (error) {
            showToast('An error occurred. Please try again.', 'error');
            event.target.value = event.target.getAttribute('data-current-status');
        }
    };
    
    // Handle rejection form with AJAX
    const rejectionForm = document.getElementById('rejectionForm');
    if (rejectionForm) {
        rejectionForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(rejectionForm);
            formData.append('table', 'prescriptions');
            
            try {
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Close modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('rejectionReasonModal'));
                    if (modal) modal.hide();
                    
                    // Update the select dropdown
                    const prescriptionId = formData.get('id');
                    const statusSelect = document.querySelector(`select[onchange*="updatePrescriptionStatus(${prescriptionId}"]`);
                    if (statusSelect) {
                        statusSelect.value = 'rejected';
                        statusSelect.setAttribute('data-current-status', 'rejected');
                    }
                    
                    // Reload page after delay to show updated notes
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(data.message, 'error');
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
            }
        });
    }
}

// Auto-initialize on DOM load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize prescription status AJAX if on prescriptions page
    if (document.getElementById('rejectionForm')) {
        initPrescriptionStatusAjax();
    }
});

