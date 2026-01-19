<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/controllers/PostController.php';

Functions::requireLogin();

$title = 'Панель управления';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include 'views/layouts/header.php';

$user = Functions::getCurrentUser();
$userTimezone = $user['timezone'] ?? 'UTC';

// Удаляем использование GroupController
// $groupController = new GroupController();
// $userGroups = $groupController->getUserGroups();

$postController = new PostController();
$postModel = new Post();
$userPosts = $postModel->getByUserIdWithUserTimezone(Functions::getCurrentUserId());

// Устанавливаем количество групп равным 0, так как функционал устарел
$userGroups = [];
?>

<div class="dashboard">
    <h2>Добро пожаловать, <?php echo htmlspecialchars($user['username']); ?>!</h2>
    
    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Активные группы</h3>
            <p><?php echo count($userGroups); ?></p>
        </div>
        
        <div class="stat-card">
            <h3>Опубликованные посты</h3>
            <p><?php echo count($userPosts); ?></p>
        </div>
    </div>
    
    <div class="dashboard-actions">
        <a href="/vk_poster/views/main/select_groups.php" class="btn btn-primary">Управление группами</a>
        <a href="/vk_poster/views/main/create_post.php" class="btn btn-secondary">Создать пост</a>
        <a href="/vk_poster/views/main/scheduled_posts.php" class="btn btn-tertiary">Запланированные посты</a>
        <a href="/vk_poster/views/main/edit_posts_task.php" class="btn btn-quaternary">Редактирование</a>
    </div>
    
    <?php if (!empty($userPosts)): ?>
        <div class="recent-posts">
            <h3>Последние посты</h3>
            <table class="posts-table">
                <thead>
                    <tr>
                        <th>Сообщение</th>
                        <th>Группа</th>
                        <th>Дата</th>
                        <th>Статус</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($userPosts, 0, 5) as $post): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(substr($post['message'], 0, 50)) . '...'; ?></td>
                            <td><?php echo htmlspecialchars($post['group_id']); ?></td>
                            <td><?php echo $post['created_at_formatted']; ?></td>
                            <td><?php echo $post['status']; ?></td>
                            <td>
                                <a href="/vk_poster/views/main/edit_post.php?id=<?php echo $post['id']; ?>" class="btn btn-sm">Редактировать</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'views/layouts/footer.php'; ?>