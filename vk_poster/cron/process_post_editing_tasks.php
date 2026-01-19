<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/PostEditingTask.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/vk_api.php';
try {
    $taskModel = new PostEditingTask();
    $tasks = $taskModel->getPending();
    
    foreach ($tasks as $task) {
        try {
            print_r("CRON DEBUG: Processing task ID: {$task['id']}");

            // Получаем пользователя
            $userModel = new User();
            $user = $userModel->findById($task['user_id']);
            
            if (!$user || empty($user['vk_token'])) {
                print_r("CRON DEBUG: Task {$task['id']} - No user or VK token found.");
                $taskModel->updateStatus($task['id'], 'failed');
                continue;
            }
            
            $vk = new VKAPI($user['vk_token']);
            
            // Проверяем, нужно ли редактировать пост
            $shouldEdit = false;
            
            if ($task['trigger_type'] === 'time') {
                // ... (проверка времени, как в оригинале) ...
                 $groupId = $task['group_id'];
                $postId = $task['post_vk_id'];
                
                // Получаем информацию о посте
                $postInfo = $vk->getWallPost($postId, $groupId);
                
                if (isset($postInfo['response']['items'][0])) {
                    $postItem = $postInfo['response']['items'][0];
                    $postDate = $postItem['date'];
                    $postTime = date('Y-m-d H:i:s', $postDate);
                    
                    $now = new DateTime();
                    $postDateTime = new DateTime($postTime);
                    $hoursDiff = ($now->getTimestamp() - $postDateTime->getTimestamp()) / 3600;
                    
                    if ($hoursDiff >= $task['trigger_value']) {
                        $shouldEdit = true;
                        print_r("CRON DEBUG: Task {$task['id']} - Time trigger met ({$hoursDiff} hours >= {$task['trigger_value']}). Should edit: YES");
                    } else {
                        print_r("CRON DEBUG: Task {$task['id']} - Time trigger NOT met ({$hoursDiff} hours < {$task['trigger_value']}). Should edit: NO");
                    }
                } else {
                    print_r("CRON DEBUG: Task {$task['id']} - Post info not found via API. Marking as failed.");
                    $taskModel->updateStatus($task['id'], 'failed');
                    continue;
                }
            } elseif ($task['trigger_type'] === 'views') {
                // ... (проверка просмотров, как в оригинале) ...
                $groupId = $task['group_id'];
                $postId = $task['post_vk_id'];
                
                // Получаем информацию о посте
                $postInfo = $vk->getWallPost($postId, $groupId);
                
                if (isset($postInfo['response']['items'][0])) {
                    $postItem = $postInfo['response']['items'][0];
                    $views = $postItem['views']['count'] ?? 0;
                    
                    if ($views >= $task['trigger_value']) {
                        $shouldEdit = true;
                        print_r("CRON DEBUG: Task {$task['id']} - Views trigger met ({$views} views >= {$task['trigger_value']}). Should edit: YES");
                    } else {
                        print_r("CRON DEBUG: Task {$task['id']} - Views trigger NOT met ({$views} views < {$task['trigger_value']}). Should edit: NO");
                    }
                } else {
                    print_r("CRON DEBUG: Task {$task['id']} - Post info not found via API (Views check). Marking as failed.");
                    $taskModel->updateStatus($task['id'], 'failed');
                    continue;
                }
            }
            
            if ($shouldEdit) {
                print_r("CRON DEBUG: Task {$task['id']} - Starting edit process. Attachment Action: {$task['attachment_action']}");
                // Редактируем пост
                $groupId = $task['group_id'];
                $postId = $task['post_vk_id'];
                
                $message = $task['new_message'] ?? '';
                
                // Если сообщение пустое, оставляем старое (логика как в оригинале)
                if (empty($message)) {
                    $postInfo = $vk->getWallPost($postId, $groupId);
                    if (isset($postInfo['response']['items'][0])) {
                        $message = $postInfo['response']['items'][0]['text'] ?? '';
                    } else {
                        print_r("CRON DEBUG: Task {$task['id']} - Post info not found during edit (for old message). Marking as failed.");
                        $taskModel->updateStatus($task['id'], 'failed');
                        continue;
                    }
                }
                
                // --- КОНВЕРТИРУЕМ group_id для загрузки вложений ---
                // $groupId хранится как отрицательный (-123), но для uploadPhoto нужен положительный (123)
                $uploadGroupId = abs((int)$groupId);
                print_r("CRON DEBUG: Task {$task['id']} - Original group_id: {$groupId}, Converted uploadGroupId: {$uploadGroupId}"); // Логируем конверсию
                // --- /КОНВЕРТИРУЕМ group_id для загрузки вложений ---
                
                // Обработка вложений
                $attachments = '';
                
                if ($task['attachment_action'] === 'keep') {
                    // ... (логика keep как в оригинале) ...
                     // Оставляем старые вложения
                    $postInfo = $vk->getWallPost($postId, $groupId);
                    if (isset($postInfo['response']['items'][0]['attachments'])) {
                        $oldAttachments = [];
                        foreach ($postInfo['response']['items'][0]['attachments'] as $attachment) {
                            if ($attachment['type'] === 'photo') {
                                $photo = $attachment['photo'];
                                $oldAttachments[] = 'photo' . $photo['owner_id'] . '_' . $photo['id'];
                            } elseif ($attachment['type'] === 'video') {
                                $video = $attachment['video'];
                                $oldAttachments[] = 'video' . $video['owner_id'] . '_' . $video['id'];
                            }
                        }
                        $attachments = implode(',', $oldAttachments);
                        print_r("CRON DEBUG: Task {$task['id']} - Keeping old attachments: $attachments");
                    }
                } elseif ($task['attachment_action'] === 'remove') {
                    // ... (логика remove как в оригинале) ...
                    // Удаляем все вложения
                    $attachments = '';
                    print_r("CRON DEBUG: Task {$task['id']} - Removing all attachments. New attachments string: '$attachments'");
                } elseif ($task['attachment_action'] === 'add_new') {
                    print_r("CRON DEBUG: Task {$task['id']} - Processing 'add_new' action. Raw new_attachments: {$task['new_attachments']}");
                    // Загружаем новые вложения
                    $tempAttachments = json_decode($task['new_attachments'], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                         print_r("CRON DEBUG: Task {$task['id']} - JSON decode error: " . json_last_error_msg());
                         $taskModel->updateStatus($task['id'], 'failed');
                         continue;
                    }
                    if (is_array($tempAttachments)) {
                        print_r("CRON DEBUG: Task {$task['id']} - Decoded tempAttachments: " . print_r($tempAttachments, true));
                        $newAttachments = [];
                        foreach ($tempAttachments as $tempAttachment) {
                            print_r("CRON DEBUG: Task {$task['id']} - Processing tempAttachment: " . print_r($tempAttachment, true));
                            if (isset($tempAttachment['path'], $tempAttachment['type']) && file_exists($tempAttachment['path'])) {
                                print_r("CRON DEBUG: Task {$task['id']} - File exists: {$tempAttachment['path']}");
                                try {
                                    if ($tempAttachment['type'] === 'photo') {
                                        print_r("CRON DEBUG: Task {$task['id']} - Attempting to upload photo: {$tempAttachment['path']} to group_id: $uploadGroupId"); // Логируем ID группы
                                        $attachment = $vk->uploadPhoto($tempAttachment['path'], $uploadGroupId); // Используем $uploadGroupId
                                        print_r("CRON DEBUG: Task {$task['id']} - Uploaded photo result: $attachment");
                                        $newAttachments[] = $attachment;
                                    } elseif ($tempAttachment['type'] === 'video') {
                                        print_r("CRON DEBUG: Task {$task['id']} - Attempting to upload video: {$tempAttachment['path']} to group_id: $uploadGroupId"); // Логируем ID группы
                                        $attachment = $vk->uploadVideo($tempAttachment['path'], $uploadGroupId); // Используем $uploadGroupId
                                         print_r("CRON DEBUG: Task {$task['id']} - Uploaded video result: $attachment");
                                        $newAttachments[] = $attachment;
                                    }
                                    
                                    // Удаляем временный файл
                                    if (unlink($tempAttachment['path'])) {
                                        print_r("CRON DEBUG: Task {$task['id']} - Temp file deleted: {$tempAttachment['path']}");
                                    } else {
                                        print_r("CRON DEBUG: Task {$task['id']} - FAILED to delete temp file: {$tempAttachment['path']}");
                                    }
                                } catch (Exception $e) {
                                     print_r("CRON DEBUG: Task {$task['id']} - Upload exception: " . $e->getMessage());
                                    // Продолжаем, даже если один файл не загрузился
                                }
                            } else {
                                print_r("CRON DEBUG: Task {$task['id']} - File does not exist OR missing path/type: " . print_r($tempAttachment, true));
                            }
                        }
                        $attachments = implode(',', $newAttachments);
                        print_r("CRON DEBUG: Task {$task['id']} - Final attachments string for edit: '$attachments'");
                    } else {
                         print_r("CRON DEBUG: Task {$task['id']} - Decoded new_attachments is not an array: " . gettype($tempAttachments));
                    }
                }
                
                print_r("CRON DEBUG: Task {$task['id']} - Calling editWallPost with message: '$message', attachments: '$attachments'");
                $result = $vk->editWallPost($postId, $groupId, $message, $attachments); // editWallPost использует $groupId (отрицательный) для поста
                print_r("CRON DEBUG: Task {$task['id']} - editWallPost result: " . print_r($result, true));
                
                if (isset($result['response'])) {
                    print_r("CRON DEBUG: Task {$task['id']} - Edit SUCCESS. Marking as completed.");
                    $taskModel->updateStatus($task['id'], 'completed');
                } else {
                    print_r("CRON DEBUG: Task {$task['id']} - Edit FAILED. Result: " . print_r($result, true) . ". Marking as failed.");
                    $taskModel->updateStatus($task['id'], 'failed');
                }
            } else {
                 print_r("CRON DEBUG: Task {$task['id']} - shouldEdit is FALSE. Skipping edit.");
            }
        } catch (Exception $e) {
            print_r("CRON ERROR: Exception processing task {$task['id']}: " . $e->getMessage());
            $taskModel->updateStatus($task['id'], 'failed');
        }
    }
    
    echo "Post editing tasks processed successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>