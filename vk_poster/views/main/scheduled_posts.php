<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/ScheduledPost.php';
require_once __DIR__ . '/../../controllers/GroupController.php';

Functions::requireLogin();

$user = Functions::getCurrentUser();
$userTimezone = $user['timezone'] ?? 'UTC';

$title = 'Запланированные посты';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';

$scheduledPostModel = new ScheduledPost();
$userId = Functions::getCurrentUserId();

// Получаем запланированные посты пользователя
$scheduledPosts = $scheduledPostModel->getUserScheduledPosts($userId);

// Получаем доступные группы для редактирования
$groupController = new GroupController();
$userGroups = $groupController->getUserGroups();
?>

<div class="dashboard">
    <h2>Запланированные посты</h2>
    
    <?php if (empty($scheduledPosts)): ?>
        <p>У вас нет запланированных постов.</p>
    <?php else: ?>
        <table class="posts-table">
            <thead>
                <tr>
                    <th>Сообщество</th>
                    <th>Сообщение</th>
                    <th>Время публикации</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduledPosts as $post): ?>
                    <tr id="post-row-<?php echo $post['id']; ?>">
                        <td class="group-name-<?php echo $post['id']; ?>">
                            <?php 
                            $group = null;
                            foreach ($userGroups as $g) {
                                if ($g['group_id'] == $post['group_id']) {
                                    $group = $g;
                                    break;
                                }
                            }
                            echo $group ? htmlspecialchars($group['group_name']) : $post['group_id'];
                            ?>
                        </td>
                        <td class="message-<?php echo $post['id']; ?>">
                            <?php echo htmlspecialchars(substr($post['message'], 0, 50)) . '...'; ?>
                        </td>
                        <td class="time-<?php echo $post['id']; ?>">
                            <?php echo Functions::formatUserTime($post['schedule_time'], $userTimezone); ?>
                        </td>
                        <td>
                            <span class="status-<?php echo $post['status']; ?>">
                                <?php 
                                switch ($post['status']) {
                                    case 'pending':
                                        echo 'Ожидает';
                                        break;
                                    case 'completed':
                                        echo 'Опубликован';
                                        break;
                                    case 'failed':
                                        echo 'Ошибка';
                                        break;
                                    case 'cancelled':
                                        echo 'Отменен';
                                        break;
                                    default:
                                        echo $post['status'];
                                }
                                ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($post['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-secondary edit-scheduled-post" 
                                        data-id="<?php echo $post['id']; ?>"
                                        data-message="<?php echo htmlspecialchars($post['message']); ?>"
                                        data-time="<?php echo Functions::formatUserTime($post['schedule_time'], $userTimezone, 'Y-m-d\\TH:i'); ?>"
                                        data-group="<?php echo $post['group_id']; ?>">Редактировать</button>
                                <button class="btn btn-sm btn-error delete-scheduled-post" 
                                        data-id="<?php echo $post['id']; ?>">Отменить</button>
                            <?php else: ?>
                                <span>Недоступно</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div class="dashboard-actions">
        <a href="/vk_poster/views/main/create_post.php" class="btn btn-primary">Создать пост</a>
        <a href="/vk_poster/" class="btn btn-secondary">Назад</a>
    </div>
</div>

<!-- Модальное окно для редактирования -->
<div id="edit-modal" class="modal" style="display:none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h3>Редактировать пост</h3>
        <form id="edit-form">
            <input type="hidden" id="edit-post-id" name="post_id">
            
            <div class="form-group">
                <label for="edit-group">Сообщество:</label>
                <select id="edit-group" name="group_id" class="form-control" required>
                    <?php foreach ($userGroups as $group): ?>
                        <option value="<?php echo $group['group_id']; ?>">
                            <?php echo htmlspecialchars($group['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit-message">Текст поста:</label>
                <textarea id="edit-message" name="message" class="form-control" rows="5" required></textarea>
            </div>
            
            <div class="form-group">
                <label for="edit-time">Время публикации:</label>
                <input type="datetime-local" id="edit-time" name="schedule_time" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
            <button type="button" class="btn btn-secondary cancel-edit">Отмена</button>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 15% auto;
    padding: 20px;
    border-radius: 8px;
    width: 600px;
    max-width: 90%;
    position: relative;
}

.close {
    position: absolute;
    right: 10px;
    top: 10px;
    font-size: 28px;
    cursor: pointer;
}

.status-pending { color: #007bff; }
.status-completed { color: #28a745; }
.status-failed { color: #dc3545; }
.status-cancelled { color: #6c757d; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Модальное окно
    const modal = document.getElementById('edit-modal');
    const closeBtn = document.querySelector('.close');
    const cancelBtn = document.querySelector('.cancel-edit');
    
    // Открытие модального окна
    document.querySelectorAll('.edit-scheduled-post').forEach(btn => {
        btn.addEventListener('click', function() {
            const postId = this.getAttribute('data-id');
            const message = this.getAttribute('data-message');
            const time = this.getAttribute('data-time');
            const groupId = this.getAttribute('data-group');
            
            document.getElementById('edit-post-id').value = postId;
            document.getElementById('edit-message').value = message;
            document.getElementById('edit-time').value = time;
            document.getElementById('edit-group').value = groupId;
            
            modal.style.display = 'block';
        });
    });
    
    // Закрытие модального окна
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
    // Сохранение изменений
    document.getElementById('edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('csrf_token', '<?php echo Functions::generateCSRFToken(); ?>');
        
        fetch('/vk_poster/api/update_scheduled_post.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Обновляем данные в таблице
                const postId = document.getElementById('edit-post-id').value;
                document.querySelector('.group-name-' + postId).textContent = 
                    document.getElementById('edit-group').options[document.getElementById('edit-group').selectedIndex].text;
                document.querySelector('.message-' + postId).textContent = 
                    document.getElementById('edit-message').value.substring(0, 50) + '...';
                document.querySelector('.time-' + postId).textContent = 
                    new Date(document.getElementById('edit-time').value).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                
                closeModal();
                alert('Пост успешно обновлен');
            } else {
                alert(data.message || 'Ошибка при обновлении поста');
            }
        })
        .catch(error => {
            alert('Ошибка при обновлении поста');
        });
    });
    
    // Подтверждение удаления запланированного поста
    document.querySelectorAll('.delete-scheduled-post').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const postId = this.getAttribute('data-id');
            
            if (confirm('Вы уверены, что хотите отменить этот пост?')) {
                fetch('/vk_poster/api/delete_scheduled_post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'post_id=' + postId + '&csrf_token=' + encodeURIComponent('<?php echo Functions::generateCSRFToken(); ?>')
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Меняем статус на "отменен" вместо удаления
                        const row = document.getElementById('post-row-' + postId);
                        const statusSpan = row.querySelector('td:nth-child(4) span');
                        statusSpan.textContent = 'Отменен';
                        statusSpan.className = 'status-cancelled';
                        
                        // Убираем кнопки редактирования и отмены
                        const actionsCell = row.querySelector('td:nth-child(5)');
                        actionsCell.innerHTML = '<span>Недоступно</span>';
                        
                        alert('Пост успешно отменен');
                    } else {
                        alert(data.message || 'Ошибка при отмене поста');
                    }
                })
                .catch(error => {
                    alert('Ошибка при отмене поста');
                });
            }
        });
    });
});
</script>

<?php include '../layouts/footer.php'; ?>