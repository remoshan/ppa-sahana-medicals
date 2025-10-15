/**
 * Universal CRUD AJAX Handler
 * Automatically detects and handles all CRUD forms on admin pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Get table name from page URL or data attribute
    const pathParts = window.location.pathname.split('/');
    const page = pathParts[pathParts.length - 1].replace('.php', '');
    
    // Map page names to table names
    const tableMap = {
        'categories': 'categories',
        'medicines': 'medicines',
        'customers': 'customers',
        'staff': 'staff',
        'suppliers': 'suppliers',
        'prescriptions': 'prescriptions',
        'orders': 'orders'
    };
    
    const tableName = tableMap[page];
    
    if (!tableName) return;
    
    // Find all forms in modals and convert them to AJAX
    const modalForms = document.querySelectorAll('.modal form[method="POST"]');
    
    modalForms.forEach(form => {
        // Skip if already has AJAX handler
        if (form.dataset.ajaxEnabled) return;
        form.dataset.ajaxEnabled = 'true';
        
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('table', tableName);
            
            // Find submit button and disable it
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
            
            try {
                const response = await fetch('ajax_handler.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    
                    // Close modal
                    const modalElement = this.closest('.modal');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) modal.hide();
                    }
                    
                    // Reload page after short delay
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                showToast('An error occurred. Please try again.', 'error');
                console.error('Error:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    });
});

