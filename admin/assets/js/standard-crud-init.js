/**
 * Standard CRUD AJAX Initialization
 * This function sets up AJAX for any CRUD page with standard form IDs
 */
function initStandardCRUD(tableName, mainFormId = null, deleteFormId = 'deleteForm', mainModalId = null) {
    document.addEventListener('DOMContentLoaded', function() {
        // Handle main form (create/update) if provided
        if (mainFormId) {
            const mainForm = document.getElementById(mainFormId);
            if (mainForm) {
                mainForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('table', tableName);
                    
                    try {
                        const response = await fetch('ajax_handler.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            showToast(data.message, 'success');
                            if (mainModalId) {
                                const modal = bootstrap.Modal.getInstance(document.getElementById(mainModalId));
                                if (modal) modal.hide();
                            }
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast(data.message, 'error');
                        }
                    } catch (error) {
                        console.error('AJAX error:', error);
                        showToast('An error occurred. Please try again.', 'error');
                    }
                });
            }
        }
        
        // Handle delete form
        const deleteForm = document.getElementById(deleteFormId);
        if (deleteForm) {
            deleteForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('table', tableName);
                
                try {
                    const response = await fetch('ajax_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast(data.message, 'success');
                        const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                        if (modal) modal.hide();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message, 'error');
                    }
                } catch (error) {
                    console.error('AJAX error:', error);
                    showToast('An error occurred. Please try again.', 'error');
                }
            });
        }
    });
}

