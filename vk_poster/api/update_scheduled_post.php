<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/ScheduledPost.php';

header('Content-Type: application/json');

if (!Functions::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Неавторизованный доступ']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit;
}

if (!isset($_POST['post_id']) || !isset($_POST['csrf_token']) || !Functions::validateCSRFToken($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Неверный токен безопасности']);
    exit;
}

try {
    $postId = (int)$_POST['post_id'];
    $groupId = (int)$_POST['group_id'];
    $message = Functions::sanitizeInput($_POST['message']);
    $scheduleTime = $_POST['schedule_time'];
    $userId = Functions::getCurrentUserId();
    
    // Проверяем, что пост принадлежит пользователю
    $scheduledPostModel = new ScheduledPost();
    $post = $scheduledPostModel->getById($postId);
    
    if (!$post || $post['user_id'] != $userId || $post['status'] != 'pending') {
        echo json_encode(['success' => false, 'message' => 'Пост не найден или не может быть отредактирован']);
        exit;
    }
    
    // Преобразуем пользовательское время в серверное время
    $user = Functions::getCurrentUser();
    $userTimezone = $user['timezone'] ?? 'UTC';
    
    // Преобразуем время из пользовательского в серверное
    if (preg_match('/^UTC([+-])(\d+)$/', $userTimezone, $matches)) {
        // Преобразуем UTC+5 в формат +05:00
        $sign = $matches[1];
        $offset = $matches[2];
        if (strlen($offset) == 1) {
            $offset = '0' . $offset;
        }
        $offset = $sign . $offset . ':00';
        $userDateTime = new DateTime($scheduleTime, new DateTimeZone($offset));
    } else {
        // Используем обычный часовой пояс
        $userDateTime = new DateTime($scheduleTime, new DateTimeZone($userTimezone));
    }
    
    $serverDateTime = clone $userDateTime;
    $serverDateTime->setTimezone(new DateTimeZone(date_default_timezone_get()));
    $serverTime = $serverDateTime->format('Y-m-d H:i:s');
    
    // Проверяем, что время не раньше текущего
    $currentTime = new DateTime();
    if ($serverDateTime < $currentTime) {
        echo json_encode(['success' => false, 'message' => 'Нельзя запланировать пост на прошедшее время']);
        exit;
    }
    
    // Обновляем пост
    $updateData = [
        'group_id' => $groupId,
        'message' => $message,
        'schedule_time' => $serverTime
    ];
    
    $result = $scheduledPostModel->update($postId, $updateData);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Пост успешно обновлен']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при обновлении поста']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?>