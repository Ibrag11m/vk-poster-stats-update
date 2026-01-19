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
    $userId = Functions::getCurrentUserId();
    
    // Проверяем, что пост принадлежит пользователю
    $database = Database::getInstance();
    $conn = $database->getConnection();
    
    $query = "SELECT * FROM scheduled_posts WHERE id = :id AND user_id = :user_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':id', $postId);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    
    $post = $stmt->fetch();
    
    if (!$post) {
        echo json_encode(['success' => false, 'message' => 'Пост не найден']);
        exit;
    }
    
    if ($post['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Нельзя отменить уже опубликованный или отмененный пост']);
        exit;
    }
    
    // Меняем статус поста на 'cancelled' вместо удаления
    $scheduledPostModel = new ScheduledPost();
    $result = $scheduledPostModel->updateStatus($postId, 'cancelled');
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Пост успешно отменен']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ошибка при отмене поста']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
}
?>