document.addEventListener('DOMContentLoaded', function() {
    // CSRF token for forms
    const csrfToken = document.querySelector('input[name="csrf_token"]');
    if (csrfToken) {
        // Add CSRF token to all forms
        document.querySelectorAll('form').forEach(form => {
            if (!form.querySelector('input[name="csrf_token"]')) {
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = 'csrf_token';
                tokenInput.value = csrfToken.value;
                form.appendChild(tokenInput);
            }
        });
    }
    
    // Form validation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Пожалуйста, заполните все обязательные поля');
            }
        });
    });
    
    // Show/hide password
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                this.textContent = 'Скрыть';
            } else {
                input.type = 'password';
                this.textContent = 'Показать';
            }
        });
    });
    
    // Alert auto-hide
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 500);
        });
    }, 5000);
});

// Utility functions
function showNotification(message, type = 'info') {
    const container = document.createElement('div');
    container.className = `alert alert-${type === 'error' ? 'error' : 'success'}`;
    container.textContent = message;
    document.body.appendChild(container);
    
    setTimeout(() => {
        container.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(container);
        }, 500);
    }, 3000);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}