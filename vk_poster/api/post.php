<?php
// Проверяем, что файл подключается напрямую, а не через HTTP-запрос
if (!defined('DIRECT_API_CALL')) {
    // Если файл вызван напрямую через HTTP
    define('DIRECT_API_CALL', false);
    
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../controllers/PostController.php';

    header('Content-Type: application/json');

    if (!Functions::isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Неавторизованный доступ']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
        exit;
    }

    if (!isset($_POST['csrf_token']) || !Functions::validateCSRFToken($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Неверный токен безопасности']);
        exit;
    }

    // Используем $_POST и $_FILES как обычно
    $groupIds = $_POST['group_ids'] ?? [];
    $message = Functions::sanitizeInput($_POST['message']);
    $scheduleTime = $_POST['schedule_time'] ?? '';
    $userTimezone = $_POST['timezone'] ?? 'UTC';
    $filesData = $_FILES; // <-- Берем из $_FILES
} else {
    // Если файл подключается напрямую из create_post.php
    // Используем переданные данные
    global $apiPostData, $apiFilesData;
    $groupIds = $apiPostData['group_ids'] ?? [];
    $message = Functions::sanitizeInput($apiPostData['message']);
    $scheduleTime = $apiPostData['schedule_time'] ?? '';
    $userTimezone = $apiPostData['timezone'] ?? 'UTC';
    $filesData = $apiFilesData; // <-- Берем из переданных данных
}

error_log("DEBUG: API post.php - Processing request, scheduleTime: $scheduleTime, filesData: " . print_r($filesData, true));

// Помечаем, что это прямой вызов
define('DIRECT_API_CALL_INTERNAL', true);

try {
    if (empty($groupIds)) {
        if (!defined('DIRECT_API_CALL') || !DIRECT_API_CALL) {
            echo json_encode(['success' => false, 'message' => 'Выберите хотя бы одно сообщество']);
            exit;
        } else {
            throw new Exception('Выберите хотя бы одно сообщество');
        }
    }
    
    // Преобразуем время пользователя в серверное время
    if (!empty($scheduleTime)) {
        // Преобразуем пользовательское время в серверное
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
            if (!defined('DIRECT_API_CALL') || !DIRECT_API_CALL) {
                echo json_encode(['success' => false, 'message' => 'Нельзя запланировать пост на прошедшее время']);
                exit;
            } else {
                throw new Exception('Нельзя запланировать пост на прошедшее время');
            }
        }
    } else {
        $serverTime = '';
    }
    
    require_once __DIR__ . '/../controllers/PostController.php';
    $postController = new PostController();
    $results = [];
    
    foreach ($groupIds as $groupId) {
        error_log("DEBUG: API post.php - Processing group ID: $groupId, scheduleTime: $serverTime");
        
        if (!empty($serverTime)) {
            // Отложенный пост
            error_log("DEBUG: API post.php - Processing scheduled post");
            $tempAttachments = [];
            if (!empty($filesData)) {
                error_log("DEBUG: API post.php - Files data present for scheduled post, calling uploadTempAttachments");
                $tempAttachments = $postController->uploadTempAttachments($filesData);
                error_log("DEBUG: API post.php - Temp attachments result: " . print_r($tempAttachments, true));
            }
            
            $result = $postController->schedulePost($groupId, $message, $tempAttachments, $serverTime, Functions::getCurrentUser()['vk_token']);
            error_log("DEBUG: API post.php - SchedulePost result: " . print_r($result, true));
        } else {
            // Обычный пост
            error_log("DEBUG: API post.php - Processing immediate post");
            $attachments = [];
            if (!empty($filesData)) { // <-- Используем $filesData, который может быть из $_FILES или из $apiFilesData
                error_log("DEBUG: API post.php - Files data present for immediate post");
                $vkToken = Functions::getCurrentUser()['vk_token'];
                $vk = new VKAPI($vkToken);
                
                if (!empty($filesData['photos'])) {
                    error_log("DEBUG: API post.php - Processing " . count($filesData['photos']) . " photos");
                    foreach ($filesData['photos'] as $photoPath) {
                        error_log("DEBUG: API post.php - Uploading photo: $photoPath");
                        $attachment = $vk->uploadPhoto($photoPath, $groupId);
                        $attachments[] = $attachment;
                        error_log("DEBUG: API post.php - Photo uploaded: $attachment");
                    }
                }
                
                if (isset($filesData['videos']) && is_array($filesData['videos'])) {
                    error_log("DEBUG: API post.php - Processing " . count($filesData['videos']) . " videos");
                    foreach ($filesData['videos'] as $videoPath) {
                        error_log("DEBUG: API post.php - Uploading video: $videoPath");
                        $attachment = $vk->uploadVideo($videoPath, $groupId);
                        $attachments[] = $attachment;
                        error_log("DEBUG: API post.php - Video uploaded: $attachment");
                    }
                }
            }
            
            $result = $postController->createPost($groupId, $message, $attachments, Functions::getCurrentUser()['vk_token'], Functions::getCurrentUserId());
        }
        
        $results[] = $result;
    }
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            $successCount++;
        } else {
            $errorCount++;
        }
    }
    
    if ($successCount > 0) {
        $message = "Пост успешно опубликован в $successCount сообществ(а)";
        if ($errorCount > 0) {
            $message .= " (ошибок: $errorCount)";
        }
        
        if (!defined('DIRECT_API_CALL') || !DIRECT_API_CALL) {
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        } else {
            // Установить флеш-сообщение и выйти
            Session::setFlash('success', $message);
            return; // Завершаем выполнение при прямом вызове
        }
    } else {
        $errorMessage = 'Не удалось опубликовать пост ни в одном сообществе';
        if (!defined('DIRECT_API_CALL') || !DIRECT_API_CALL) {
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
            throw new Exception($errorMessage);
        }
    }
} catch (Exception $e) {
    $errorMessage = 'Ошибка сервера: ' . $e->getMessage();
    error_log("DEBUG: API post.php - Exception: " . $errorMessage);
    
    if (!defined('DIRECT_API_CALL') || !DIRECT_API_CALL) {
        echo json_encode(['success' => false, 'message' => 'Ошибка сервера']);
        exit;
    } else {
        throw $e; // Перебросить исключение в вызывающий скрипт
    }
}
?>