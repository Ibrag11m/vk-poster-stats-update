<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
// Удаляем строку: require_once __DIR__ . '/../../controllers/GroupController.php';
require_once __DIR__ . '/../../models/UserPostGroup.php';
// Удаляем строку: require_once __DIR__ . '/../../models/Group.php';
require_once __DIR__ . '/../../includes/vk_api.php';

Functions::requireLogin();

if ($_POST && isset($_POST['csrf_token']) && Functions::validateCSRFToken($_POST['csrf_token'])) {
    $userPostGroup = new UserPostGroup();
    $vkToken = Functions::getCurrentUser()['vk_token'];
    $userId = Functions::getCurrentUserId();

    switch ($_POST['action'] ?? '') {
        case 'update_vk_token':
            $newToken = Functions::sanitizeInput($_POST['vk_token']);
            $userModel = new User();
            if ($userModel->updateVKToken($userId, $newToken)) {
                Session::setFlash('success', 'Токен доступа обновлен');
            } else {
                Session::setFlash('error', 'Ошибка при обновлении токена');
            }
            break;

        case 'create_post_group':
            $name = Functions::sanitizeInput($_POST['name']);
            $description = Functions::sanitizeInput($_POST['description']);

            if (!empty($name)) {
                $data = [
                    'user_id' => $userId,
                    'name' => $name,
                    'description' => $description
                ];

                if ($userPostGroup->create($data)) {
                    Session::setFlash('success', 'Группа создана');
                } else {
                    Session::setFlash('error', 'Ошибка при создании группы');
                }
            } else {
                Session::setFlash('error', 'Введите название группы');
            }
            break;

        case 'update_post_group':
            $groupId = (int)$_POST['group_id'];
            $name = Functions::sanitizeInput($_POST['name']);
            $description = Functions::sanitizeInput($_POST['description']);

            $data = [
                'name' => $name,
                'description' => $description
            ];

            if ($userPostGroup->update($groupId, $data, $userId)) {
                Session::setFlash('success', 'Группа обновлена');
            } else {
                Session::setFlash('error', 'Ошибка при обновлении группы');
            }
            break;

        case 'delete_post_group':
            $groupId = (int)$_POST['group_id'];
            if ($userPostGroup->delete($groupId, $userId)) {
                Session::setFlash('success', 'Группа удалена');
            } else {
                Session::setFlash('error', 'Ошибка при удалении группы');
            }
            break;

        case 'add_to_group':
            $groupId = (int)$_POST['group_id'];
            $selectedGroups = $_POST['selected_groups'] ?? [];

            foreach ($selectedGroups as $selectedGroupId) {
                $userPostGroup->addMember($groupId, $selectedGroupId);
            }

            Session::setFlash('success', 'Сообщества добавлены в группу');
            break;

        case 'remove_from_group':
            $groupId = (int)$_POST['group_id'];
            $selectedGroups = $_POST['selected_groups'] ?? [];

            foreach ($selectedGroups as $selectedGroupId) {
                $userPostGroup->removeMember($groupId, $selectedGroupId);
            }

            Session::setFlash('success', 'Сообщества удалены из группы');
            break;
    }

    header('Location: /vk_poster/views/main/select_groups.php');
    exit();
}

$title = 'Управление группами';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';

$userPostGroup = new UserPostGroup();
$postGroups = $userPostGroup->getByUserId(Functions::getCurrentUserId());

$vk = new VKAPI(Functions::getCurrentUser()['vk_token']);

$allAvailableGroups = [];
$vkApiError = null;
$groupsFromAPI = [];

// Проверяем, что токен не пустой
if (!empty(Functions::getCurrentUser()['vk_token'])) {
    try {
        $userGroupsInfo = $vk->getUserGroups('admin,moderator,editor');

        if (isset($userGroupsInfo['response']) && isset($userGroupsInfo['response']['items'])) {
            $groupsFromAPI = $userGroupsInfo['response']['items'];

            if (is_array($groupsFromAPI) && !empty($groupsFromAPI)) {
                $groupIdsStr = implode(',', $groupsFromAPI);
                try {
                    $groupsDetails = $vk->getGroupsByIds($groupIdsStr);

                    if (is_array($groupsDetails) && !empty($groupsDetails)) {
                        foreach ($groupsDetails as $group) {
                             $allAvailableGroups[] = [
                                'id' => $group['id'],
                                'name' => $group['name'],
                                'screen_name' => $group['screen_name']
                            ];
                        }
                    } else {
                        $vkApiError = "Неожиданный формат ответа от groups.getById. Проверьте права доступа токена.";
                    }
                } catch (Exception $e_details) {
                     $vkApiError = "Ошибка при получении деталей групп: " . $e_details->getMessage();
                }
            }
        } else {
            $vkApiError = "Непредвиденная структура ответа API при получении списка групп (groups.get).";
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        $vkApiError = $errorMessage;
        Session::setFlash('error', 'Ошибка при получении списка групп: ' . $errorMessage);
    }
} else {
    $errorMessage = 'Токен доступа не установлен. Пожалуйста, обновите токен.';
    Session::setFlash('error', $errorMessage);
}
?>

<div class="dashboard">
    <h2>Управление токеном и группами сообществ</h2>

    <?php if ($message = Session::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if ($message = Session::getFlash('error')): ?>
        <div class="alert alert-error"><?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Обновление токена -->
    <div class="card">
        <div class="card-header">
            <h3>Обновить токен доступа ВКонтакте</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_vk_token">
                <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="vk_token">Токен доступа:</label>
                    <input type="text" id="vk_token" name="vk_token" class="form-control" value="<?php echo htmlspecialchars(Functions::getCurrentUser()['vk_token'] ?? ''); ?>" required>
                    <small>Получить токен можно на <a href="https://vk.com/dev" target="_blank">https://vk.com/dev</a></small>
                </div>

                <button type="submit" class="btn btn-primary">Обновить токен</button>
            </form>
        </div>
    </div>

    <!-- Создание новой группы -->
    <div class="card">
        <div class="card-header">
            <h3>Создать новую группу сообществ</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="">
                <input type="hidden" name="action" value="create_post_group">
                <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">

                <div class="form-group">
                    <label for="name">Название группы:</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="description">Описание (необязательно):</label>
                    <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Создать группу</button>
            </form>
        </div>
    </div>

    <!-- Список существующих групп -->
    <?php foreach ($postGroups as $group): ?>
        <div class="card group-card">
            <div class="card-header">
                <div class="group-header">
                    <div class="group-info">
                        <h3><?php echo htmlspecialchars($group['name']); ?></h3>
                        <?php if ($group['description']): ?>
                            <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="group-actions">
                        <!-- Кнопка редактирования -->
                        <form method="POST" action="" style="display:inline;">
                            <input type="hidden" name="action" value="update_post_group">
                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
                            
                            <div class="edit-form">
                                <input type="text" name="name" value="<?php echo htmlspecialchars($group['name']); ?>" class="form-control form-control-sm" placeholder="Название группы" required>
                                <button type="submit" class="btn btn-sm btn-secondary">Сохранить</button>
                            </div>
                        </form>
                        
                        <!-- Кнопка удаления -->
                        <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Удалить эту группу?');">
                            <input type="hidden" name="action" value="delete_post_group">
                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
                            <button type="submit" class="btn btn-sm btn-error">Удалить</button>
                        </form>
                    </div>
                </div>
                
                <!-- Кнопка для раскрытия содержимого группы -->
                <button class="toggle-button" onclick="toggleGroup(<?php echo $group['id']; ?>)">
                    <span id="toggle-text-<?php echo $group['id']; ?>">Редактировать</span>
                </button>
            </div>

            <!-- Содержимое группы (изначально скрыто) -->
            <div class="card-body" id="group-<?php echo $group['id']; ?>" style="display: none;">
                <div class="group-content">
                    <h4>Добавить сообщества в группу</h4>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_to_group">
                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">

                        <div class="form-group">
                            <label>Доступные сообщества:</label>
                            <?php if ($vkApiError !== null): ?>
                                <p class="error-message">Ошибка при получении доступных сообществ: <?php echo htmlspecialchars($vkApiError); ?></p>
                            <?php elseif (empty($allAvailableGroups)): ?>
                                <?php if (!empty(Functions::getCurrentUser()['vk_token'])): ?>
                                    <?php if (!empty($groupsFromAPI)): ?>
                                        <p class="info-message">Не удалось получить детали доступных сообществ. <?php echo htmlspecialchars($vkApiError ?: ''); ?></p>
                                    <?php else: ?>
                                        <p class="info-message">У вас нет доступных сообществ с правами администратора, модератора или редактора.</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="info-message">Нет доступных сообществ. Проверьте токен доступа.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="available-groups-list">
                                    <?php
                                    // Получаем уже добавленные в группу сообщества
                                    $addedMembers = $userPostGroup->getMembers($group['id']);
                                    $addedGroupIds = array_column($addedMembers, 'group_id');

                                    $hasAvailableGroups = false;
                                    foreach ($allAvailableGroups as $vkGroup):
                                        // Пропускаем, если уже добавлено в эту группу
                                        if (in_array($vkGroup['id'], $addedGroupIds)) continue;
                                        $hasAvailableGroups = true;
                                    ?>
                                        <div class="checkbox-item">
                                            <label>
                                                <input type="checkbox" name="selected_groups[]" value="<?php echo $vkGroup['id']; ?>">
                                                <span class="group-name"><?php echo htmlspecialchars($vkGroup['name']); ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (!$hasAvailableGroups): ?>
                                        <p class="info-message">Все доступные сообщества уже добавлены в эту группу</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($allAvailableGroups) && $vkApiError === null): ?>
                            <button type="submit" class="btn btn-primary">Добавить в группу</button>
                        <?php endif; ?>
                    </form>

                    <!-- Список сообществ в группе -->
                    <h4>Сообщества в группе</h4>
                    <?php
                    $members = $userPostGroup->getMembers($group['id']);
                    
                    if (empty($members)):
                    ?>
                        <p class="info-message">В группе пока нет сообществ</p>
                    <?php else: ?>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="remove_from_group">
                            <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">

                            <div class="member-groups-list">
                                <?php foreach ($members as $member): ?>
                                    <div class="checkbox-item">
                                        <label>
                                            <input type="checkbox" name="selected_groups[]" value="<?php echo $member['group_id']; ?>">
                                            <span class="group-name"><?php echo htmlspecialchars($member['group_name']); ?></span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button type="submit" class="btn btn-error">Удалить из группы</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($postGroups)): ?>
        <div class="card">
            <div class="card-body">
                <p>У вас пока нет пользовательских групп сообществ. <a href="#" onclick="document.querySelector('input[name=\'name\']').focus(); return false;">Создайте первую группу</a>.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleGroup(groupId) {
    var content = document.getElementById('group-' + groupId);
    var toggleText = document.getElementById('toggle-text-' + groupId);
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        toggleText.textContent = 'Свернуть';
    } else {
        content.style.display = 'none';
        toggleText.textContent = 'Редактировать';
    }
}
</script>

<style>
.dashboard {
    padding: 20px;
}

.card {
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.card-header {
    background-color: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top-left-radius: 8px;
    border-top-right-radius: 8px;
}

.group-header {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
}

.group-info {
    flex-grow: 1;
}

.group-info h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.group-description {
    margin: 5px 0 0;
    font-size: 14px;
    color: #666;
}

.group-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.edit-form {
    display: flex;
    gap: 5px;
    align-items: center;
}

.edit-form input[type="text"] {
    width: 200px;
    padding: 5px;
    font-size: 14px;
}

.toggle-button {
    background-color: #007bff;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
    min-width: 80px;
    text-align: center;
    height: 32px;
}

.toggle-button:hover {
    background-color: #0056b3;
}

.card-body {
    padding: 20px;
    background-color: #fff;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
}

.group-content {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.available-groups-list, .member-groups-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background-color: #f9f9f9;
}

.checkbox-item {
    padding: 8px;
    border-bottom: 1px solid #eee;
}

.checkbox-item:last-child {
    border-bottom: none;
}

.checkbox-item label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
}

.checkbox-item input[type="checkbox"] {
    width: 16px;
    height: 16px;
}

.checkbox-item .group-name {
    font-size: 14px;
    color: #333;
}

.btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
    min-width: 80px;
    text-align: center;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-primary {
    background-color: #007bff;
    color: white;
}

.btn-primary:hover {
    background-color: #0056b3;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
}

.btn-error {
    background-color: #dc3545;
    color: white;
}

.btn-error:hover {
    background-color: #c82333;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    min-width: 60px;
    height: 28px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.error-message {
    color: #dc3545;
    font-weight: 600;
}

.info-message {
    color: #6c757d;
    font-style: italic;
}

/* Адаптивность */
@media (max-width: 768px) {
    .group-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .edit-form {
        width: 100%;
        order: 2;
    }
    
    .group-actions {
        width: 100%;
        justify-content: space-between;
        order: 1;
    }
    
    .toggle-button {
        width: 100%;
        order: 3;
    }
    
    .available-groups-list, .member-groups-list {
        max-height: 150px;
    }
    
    .card-body {
        padding: 15px;
    }
}

/* Для мобильных устройств */
@media (max-width: 480px) {
    .card-header {
        padding: 10px;
    }
    
    .group-info h3 {
        font-size: 16px;
    }
    
    .edit-form input[type="text"] {
        width: 100%;
    }
}
</style>

<?php include '../layouts/footer.php'; ?>