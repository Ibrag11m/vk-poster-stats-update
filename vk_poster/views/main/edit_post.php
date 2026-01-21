<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/Post.php';
require_once __DIR__ . '/../../models/UserPostGroup.php';
require_once __DIR__ . '/../../includes/vk_api.php';

Functions::requireLogin();

// Получаем ID поста из GET параметра
$postId = (int)$_GET['id'];
$postModel = new Post();
$post = $postModel->getById($postId, Functions::getCurrentUserId());

if (!$post) {
    Session::setFlash('error', 'Пост не найден или доступ запрещен');
    header('Location: /vk_poster/');
    exit();
}

if ($_POST && isset($_POST['csrf_token']) && Functions::validateCSRFToken($_POST['csrf_token'])) {
    $message = Functions::sanitizeInput($_POST['message']);
    $groupId = (int)$_POST['group_id'];
    
    // Получаем токен пользователя
    $vkToken = Functions::getCurrentUser()['vk_token'];
    $vk = new VKAPI($vkToken);
    
    // Формируем ID поста для VK API (в формате -groupId_postId)
    $vkPostId = '-' . $post['group_id'] . '_' . $post['id'];
    
    // Редактируем пост
    $result = $vk->editWallPost($post['id'], $post['group_id'], $message);
    
    if (isset($result['response'])) {
        // Обновляем пост в базе данных
        $updateData = [
            'id' => $postId,
            'user_id' => Functions::getCurrentUserId(),
            'message' => $message,
            'attachments' => $post['attachments'] // Оставляем старые вложения
        ];
        
        $postModel->update($updateData);
        
        Session::setFlash('success', 'Пост успешно отредактирован');
        header('Location: /vk_poster/views/main/edit_post.php?id=' . $postId);
        exit();
    } else {
        $errors = ['Ошибка при редактировании поста'];
    }
}

$title = 'Редактировать пост';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';

// Получаем группы через VK API
$vk = new VKAPI(Functions::getCurrentUser()['vk_token']);
$userGroupsInfo = $vk->getUserGroups('admin,moderator,editor');
$userGroups = [];

if (isset($userGroupsInfo['response']['items']) && is_array($userGroupsInfo['response']['items']) && !empty($userGroupsInfo['response']['items'])) {
    $groupIdsStr = implode(',', $userGroupsInfo['response']['items']);
    $groupsDetails = $vk->getGroupsByIds($groupIdsStr);
    
    if (isset($groupsDetails) && is_array($groupsDetails)) {
        foreach ($groupsDetails as $group) {
            $userGroups[] = [
                'group_id' => $group['id'],
                'group_name' => $group['name']
            ];
        }
    }
}

$userPostGroup = new UserPostGroup();
$postGroups = $userPostGroup->getByUserId(Functions::getCurrentUserId());

// Проверяем, есть ли у пользователя созданные группы
$hasUserCreatedGroups = !empty($postGroups);
?>

<div class="form-container">
    <h2>Редактировать пост</h2>
    
    <?php if ($message = Session::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
        
        <?php if ($hasUserCreatedGroups): ?>
            <!-- Если у пользователя есть созданные группы, показываем только их -->
            <div class="form-group">
                <label for="post_group_ids">Выберите пользовательские группы:</label>
                <?php foreach ($postGroups as $postGroup): ?>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="post_group_ids[]" value="<?php echo $postGroup['id']; ?>" 
                                   <?php echo ($post['group_id'] == $postGroup['id']) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($postGroup['name']); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Если нет созданных групп, показываем обычные группы -->
            <div class="form-group">
                <label for="group_id">Сообщество:</label>
                <select id="group_id" name="group_id" class="form-control" required>
                    <?php foreach ($userGroups as $group): ?>
                        <option value="<?php echo $group['group_id']; ?>" 
                                <?php echo ($post['group_id'] == $group['group_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($group['group_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        
        <div class="form-group">
            <label for="message">Текст поста:</label>
            <textarea id="message" name="message" class="form-control" rows="5" required><?php echo htmlspecialchars($post['message']); ?></textarea>
        </div>
        
        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
        <a href="/vk_poster/" class="btn btn-secondary">Назад</a>
    </form>
</div>

<?php include '../layouts/footer.php'; ?>