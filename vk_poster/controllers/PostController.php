<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Post.php';
require_once __DIR__ . '/../models/ScheduledPost.php';
require_once __DIR__ . '/../includes/vk_api.php';

class PostController {
    private $postModel;
    private $scheduledPostModel;
    
    public function __construct() {
        $this->postModel = new Post();
        $this->scheduledPostModel = new ScheduledPost();
    }
    
    public function createPost($groupId, $message, $attachments = [], $vkToken, $userId = null) {
        try {
            $vk = new VKAPI($vkToken);
            
            $attachmentString = '';
            if (!empty($attachments)) {
                $attachmentString = implode(',', $attachments);
            }
            
            $result = $vk->postToWall($groupId, $message, $attachmentString);
            
            if (isset($result['response']['post_id'])) {
                $postId = $result['response']['post_id'];
                
                $postData = [
                    'user_id' => $userId ?? Functions::getCurrentUserId(),
                    'group_id' => $groupId,
                    'message' => $message,
                    'attachments' => $attachmentString,
                    'status' => 'published',
                    'scheduled_time' => date('Y-m-d H:i:s'),
                    'vk_post_id' => $postId
                ];
                
                $this->postModel->create($postData);
                
                return ['success' => true, 'post_id' => $postId, 'message' => 'Пост успешно опубликован'];
            } else {
                return ['success' => false, 'message' => 'Ошибка публикации поста'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }
    
    public function schedulePost($groupId, $message, $tempAttachments = [], $scheduleTime, $vkToken) {
        try {
            $tempAttachmentString = json_encode($tempAttachments);
            
            $scheduledData = [
                'user_id' => Functions::getCurrentUserId(),
                'group_id' => $groupId,
                'message' => $message,
                'attachments' => '',
                'temp_attachments' => $tempAttachmentString,
                'schedule_time' => $scheduleTime
            ];
            
            $this->scheduledPostModel->create($scheduledData);
            
            return ['success' => true, 'message' => 'Пост запланирован на ' . date('d.m.Y H:i', strtotime($scheduleTime))];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }
    
    public function editPost($vkPostId, $groupId, $message, $attachments = [], $vkToken) {
        try {
            $vk = new VKAPI($vkToken);
            
            $attachmentString = '';
            if (!empty($attachments)) {
                $attachmentString = implode(',', $attachments);
            }
            
            $result = $vk->editWallPost($vkPostId, $groupId, $message, $attachmentString);
            
            if (isset($result['response'])) {
                $post = $this->postModel->getByVkPostId($vkPostId);
                if ($post && $post['user_id'] == Functions::getCurrentUserId()) {
                    $postData = [
                        'message' => $message,
                        'attachments' => $attachmentString
                    ];
                    
                    $this->postModel->update($post['id'], $postData);
                }
                
                return ['success' => true, 'message' => 'Пост успешно отредактирован'];
            } else {
                return ['success' => false, 'message' => 'Ошибка редактирования поста'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }
    
    public function getPostsByUser() {
        $userId = Functions::getCurrentUserId();
        return $this->postModel->getByUserIdWithUserTimezone($userId);
    }
    
    public function deletePost($postId) {
        $post = $this->postModel->getById($postId);
        if ($post && $post['user_id'] == Functions::getCurrentUserId()) {
            return $this->postModel->delete($postId);
        }
        return false;
    }
    
    public function uploadTempAttachments($filesData) {
		error_log("DEBUG: PostController::uploadTempAttachments called with filesData: " . print_r($filesData, true));
		
		$tempAttachments = [];
		
		if (isset($filesData['photos']) && is_array($filesData['photos'])) {
			error_log("DEBUG: PostController::uploadTempAttachments processing " . count($filesData['photos']) . " photos");
			foreach ($filesData['photos'] as $tmpPath) {
				error_log("DEBUG: PostController::uploadTempAttachments processing photo: $tmpPath");
				if (file_exists($tmpPath)) {
					// Проверяем, является ли файл валидным изображением
					$imageInfo = getimagesize($tmpPath);
					if ($imageInfo === false) {
						// Проверяем, возможно, это файл, созданный из вставленного изображения
						$fileExtension = pathinfo($tmpPath, PATHINFO_EXTENSION);
						$validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
						
						if (in_array(strtolower($fileExtension), $validExtensions)) {
							// Если расширение валидное, но getimagesize не сработал, возможно, это файл из base64
							// Проверим, содержит ли файл допустимые сигнатуры изображений
							$fileHandle = fopen($tmpPath, 'rb');
							if ($fileHandle) {
								$firstBytes = fread($fileHandle, 10);
								fclose($fileHandle);
								
								// Проверим сигнатуры для JPEG, PNG, GIF
								if (strpos($firstBytes, "\xFF\xD8\xFF") === 0 || // JPEG
									strpos($firstBytes, "\x89PNG") === 0 ||      // PNG
									strpos($firstBytes, "GIF") === 0) {         // GIF
									
									error_log("DEBUG: PostController::uploadTempAttachments - File appears to be a valid image based on signature: $tmpPath");
									$imageInfo = true; // Устанавливаем в true, чтобы пройти проверку
								} else {
									error_log("DEBUG: PostController::uploadTempAttachments - File is not a valid image based on signature: $tmpPath");
									continue; // Пропускаем невалидные файлы
								}
							} else {
								error_log("DEBUG: PostController::uploadTempAttachments - Could not read file signature: $tmpPath");
								continue; // Пропускаем невалидные файлы
							}
						} else {
							error_log("DEBUG: PostController::uploadTempAttachments - File has invalid extension: $fileExtension, path: $tmpPath");
							continue; // Пропускаем невалидные файлы
						}
					} else {
						error_log("DEBUG: PostController::uploadTempAttachments - Valid image detected, MIME: " . $imageInfo['mime']);
					}
					
					$ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
					$newName = uniqid('temp_photo_') . '.' . $ext;
					$destPath = __DIR__ . '/../uploads/temp/' . $newName;
					
					if (copy($tmpPath, $destPath)) {
						error_log("DEBUG: PostController::uploadTempAttachments copied photo to: $destPath");
						$tempAttachments[] = [
							'type' => 'photo',
							'path' => $destPath
						];
					} else {
						error_log("DEBUG: PostController::uploadTempAttachments failed to copy photo: $tmpPath");
					}
				} else {
					error_log("DEBUG: PostController::uploadTempAttachments file does not exist: $tmpPath");
				}
			}
		}
		
		if (isset($filesData['videos']) && is_array($filesData['videos'])) {
			error_log("DEBUG: PostController::uploadTempAttachments processing " . count($filesData['videos']) . " videos");
			foreach ($filesData['videos'] as $tmpPath) {
				error_log("DEBUG: PostController::uploadTempAttachments processing video: $tmpPath");
				if (file_exists($tmpPath)) {
					$ext = pathinfo($tmpPath, PATHINFO_EXTENSION);
					$newName = uniqid('temp_video_') . '.' . $ext;
					$destPath = __DIR__ . '/../uploads/temp/' . $newName;
					
					if (copy($tmpPath, $destPath)) {
						error_log("DEBUG: PostController::uploadTempAttachments copied video to: $destPath");
						$tempAttachments[] = [
							'type' => 'video',
							'path' => $destPath
						];
					} else {
						error_log("DEBUG: PostController::uploadTempAttachments failed to copy video: $tmpPath");
					}
				} else {
					error_log("DEBUG: PostController::uploadTempAttachments file does not exist: $tmpPath");
				}
			}
		}
		
		error_log("DEBUG: PostController::uploadTempAttachments returning: " . print_r($tempAttachments, true));
		return $tempAttachments;
	}
    
    public function processScheduledPosts() {
        $scheduledPosts = $this->scheduledPostModel->getPending();
        
        foreach ($scheduledPosts as $post) {
            try {
                // Получаем токен пользователя
                $userModel = new User();
                $user = $userModel->findById($post['user_id']);
                
                if (!$user || empty($user['vk_token'])) {
                    $this->scheduledPostModel->updateStatus($post['id'], 'failed');
                    continue;
                }
                
                $vkToken = $user['vk_token'];
                
                // Загружаем вложения в момент публикации
                $attachments = [];
                if (!empty($post['temp_attachments'])) {
                    $tempAttachments = json_decode($post['temp_attachments'], true);
                    if (is_array($tempAttachments)) {
                        $vk = new VKAPI($vkToken);
                        
                        foreach ($tempAttachments as $tempAttachment) {
                            if (file_exists($tempAttachment['path'])) {
                                try {
                                    if ($tempAttachment['type'] === 'photo') {
                                        $attachment = $vk->uploadPhoto($tempAttachment['path'], $post['group_id']);
                                        $attachments[] = $attachment;
                                    } elseif ($tempAttachment['type'] === 'video') {
                                        $attachment = $vk->uploadVideo($tempAttachment['path'], $post['group_id']);
                                        $attachments[] = $attachment;
                                    }
                                    
                                    // Удаляем временный файл после загрузки
                                    unlink($tempAttachment['path']);
                                } catch (Exception $e) {
                                    // Продолжаем, даже если один файл не загрузился
                                }
                            }
                        }
                    }
                }
                
                // Публикуем пост с загруженными вложениями
                $result = $this->createPost($post['group_id'], $post['message'], $attachments, $vkToken, $post['user_id']);
                
                if ($result['success']) {
                    $this->scheduledPostModel->updateStatus($post['id'], 'completed');
                } else {
                    $this->scheduledPostModel->updateStatus($post['id'], 'failed');
                }
            } catch (Exception $e) {
                $this->scheduledPostModel->updateStatus($post['id'], 'failed');
            }
        }
    }
}
?>