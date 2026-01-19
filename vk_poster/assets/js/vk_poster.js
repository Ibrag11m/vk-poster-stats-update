document.addEventListener('DOMContentLoaded', function() {
    // Group selection
    const groupCheckboxes = document.querySelectorAll('input[name="group_ids[]"]');
    const selectAllBtn = document.getElementById('select-all');
    
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function() {
            const checked = this.checked;
            groupCheckboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });
        });
    }
    
    // Attachment handling
    const photoInput = document.getElementById('photos');
    const videoInput = document.getElementById('videos');
    
    if (photoInput) {
        photoInput.addEventListener('change', function() {
            const fileCount = this.files.length;
            const label = this.parentNode.querySelector('label');
            if (fileCount > 0) {
                label.textContent = `${fileCount} фото выбрано`;
            } else {
                label.textContent = 'Выберите фото';
            }
        });
    }
    
    if (videoInput) {
        videoInput.addEventListener('change', function() {
            const fileCount = this.files.length;
            const label = this.parentNode.querySelector('label');
            if (fileCount > 0) {
                label.textContent = `${fileCount} видео выбрано`;
            } else {
                label.textContent = 'Выберите видео';
            }
        });
    }
    
    // Character counter for message
    const messageTextarea = document.getElementById('message');
    if (messageTextarea) {
        const counter = document.createElement('div');
        counter.className = 'char-counter';
        counter.textContent = `0 / 10000`;
        messageTextarea.parentNode.appendChild(counter);
        
        messageTextarea.addEventListener('input', function() {
            const length = this.value.length;
            counter.textContent = `${length} / 10000`;
            
            if (length > 9000) {
                counter.style.color = 'orange';
            } else if (length > 9500) {
                counter.style.color = 'red';
            } else {
                counter.style.color = '#666';
            }
        });
    }
    
    // Confirm delete
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            confirmAction('Вы уверены, что хотите удалить этот пост?', function() {
                window.location.href = href;
            });
        });
    });
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