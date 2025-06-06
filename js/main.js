// Main JavaScript functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

// Copy to clipboard function
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        // Use the Clipboard API when available
        navigator.clipboard.writeText(text).then(function() {
            showCopySuccess();
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            fallbackCopyTextToClipboard(text);
        });
    } else {
        // Fallback for older browsers
        fallbackCopyTextToClipboard(text);
    }
}

// Fallback copy function for older browsers
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    
    // Avoid scrolling to bottom
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";
    
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess();
        } else {
            showCopyError();
        }
    } catch (err) {
        console.error('Fallback: Oops, unable to copy', err);
        showCopyError();
    }
    
    document.body.removeChild(textArea);
}

// Show copy success feedback
function showCopySuccess() {
    // Create a temporary toast notification
    const toast = createToast('Link copiado!', 'success');
    showToast(toast);
}

// Show copy error feedback
function showCopyError() {
    const toast = createToast('Erro ao copiar link', 'danger');
    showToast(toast);
}

// Create toast notification
function createToast(message, type = 'info') {
    const toastContainer = getOrCreateToastContainer();
    
    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${getIconForType(type)} me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toastEl);
    return toastEl;
}

// Get or create toast container
function getOrCreateToastContainer() {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    return container;
}

// Show toast notification
function showToast(toastEl) {
    const toast = new bootstrap.Toast(toastEl, {
        autohide: true,
        delay: 3000
    });
    toast.show();
    
    // Remove toast element after it's hidden
    toastEl.addEventListener('hidden.bs.toast', function() {
        toastEl.remove();
    });
}

// Get icon for toast type
function getIconForType(type) {
    switch (type) {
        case 'success':
            return 'check-circle';
        case 'danger':
        case 'error':
            return 'exclamation-triangle';
        case 'warning':
            return 'exclamation-circle';
        case 'info':
        default:
            return 'info-circle';
    }
}

// Format currency for display
function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Validate form inputs
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;
    
    let isValid = true;
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    
    inputs.forEach(function(input) {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Real-time form validation
document.addEventListener('input', function(e) {
    if (e.target.hasAttribute('required')) {
        if (e.target.value.trim()) {
            e.target.classList.remove('is-invalid');
        }
    }
});

// Confirm dialog for dangerous actions
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Auto-refresh status function (can be called periodically)
function refreshPaymentStatus(linkId) {
    fetch('update-status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_cielo_status&link_id=${linkId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the status in the UI
            const statusElements = document.querySelectorAll(`[data-link-id="${linkId}"] .status-badge`);
            statusElements.forEach(el => {
                el.textContent = data.status;
                el.className = `badge ${getStatusBadgeClass(data.status)}`;
            });
            
            const toast = createToast(`Status atualizado: ${data.status}`, 'success');
            showToast(toast);
        }
    })
    .catch(error => {
        console.error('Error refreshing status:', error);
    });
}

// Get CSS class for status badge
function getStatusBadgeClass(status) {
    switch (status) {
        case 'Aguardando Pagamento':
            return 'bg-warning';
        case 'Pago':
            return 'bg-success';
        case 'Crédito Gerado':
            return 'bg-primary';
        default:
            return 'bg-secondary';
    }
}

// Mask for currency input
function applyCurrencyMask(inputElement) {
    inputElement.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = (value / 100).toFixed(2);
        value = value.replace('.', ',');
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        e.target.value = value;
    });
}

// Initialize currency masks on load
document.addEventListener('DOMContentLoaded', function() {
    const currencyInputs = document.querySelectorAll('input[data-currency="true"]');
    currencyInputs.forEach(applyCurrencyMask);
});

// Loading state management
function setLoadingState(element, loading = true) {
    if (loading) {
        element.disabled = true;
        const originalText = element.textContent;
        element.dataset.originalText = originalText;
        element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
    } else {
        element.disabled = false;
        element.textContent = element.dataset.originalText || 'Enviar';
    }
}

// Form submission with loading state
document.addEventListener('submit', function(e) {
    const form = e.target;
    const submitButton = form.querySelector('button[type="submit"]');
    
    if (submitButton) {
        setLoadingState(submitButton, true);
        
        // Reset loading state after 5 seconds as fallback
        setTimeout(() => {
            setLoadingState(submitButton, false);
        }, 5000);
    }
});

// Prevent double form submission
const submittedForms = new Set();

document.addEventListener('submit', function(e) {
    const form = e.target;
    const formId = form.id || form.action;
    
    if (submittedForms.has(formId)) {
        e.preventDefault();
        return false;
    }
    
    submittedForms.add(formId);
    
    // Remove from set after 3 seconds
    setTimeout(() => {
        submittedForms.delete(formId);
    }, 3000);
});
