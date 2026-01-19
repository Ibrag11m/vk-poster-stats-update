<?php
class VKAPI {
    private $token;
    private $apiVersion = '5.199';
    private $baseUrl = 'https://api.vk.com/method/';

    public function __construct($token) {
        $this->token = $token;
    }

    private function makeRequest($method, $params = []) {
        $params['access_token'] = $this->token;
        $params['v'] = $this->apiVersion;

        $url = $this->baseUrl . $method;
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($params),
                'timeout' => 30
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        if ($result === false) {
            throw new Exception('Failed to connect to VK API');
        }

        $decoded = json_decode($result, true);

        if (isset($decoded['error'])) {
            throw new Exception('VK API Error: ' . $decoded['error']['error_msg']);
        }

        return $decoded;
    }

    // Метод для получения групп текущего пользователя
    public function getUserGroups($filter = 'admin,moderator,editor') {
        $params = [
            'filter' => $filter
        ];

        return $this->makeRequest('groups.get', $params);
    }

    // Метод для получения информации о нескольких группах по ID
    public function getGroupsByIds($groupIds) {
        $params = [
            'group_ids' => $groupIds,
            'fields' => 'name,screen_name,is_admin,admin_level'
        ];

        $response = $this->makeRequest('groups.getById', $params);

        // Извлечение массива групп из ответа
        if (isset($response['response']['groups']) && is_array($response['response']['groups'])) {
            // Новый формат
            return $response['response']['groups'];
        } elseif (isset($response['response']['items']) && is_array($response['response']['items'])) {
            // Старый формат (на всякий случай)
            return $response['response']['items'];
        } else {
            // Если ни один из форматов не подошел
            return [];
        }
    }

    // Метод для получения информации о конкретной группе
    public function getGroupById($groupId) {
        $params = [
            'group_id' => abs($groupId),
            'fields' => 'name,screen_name,is_admin,admin_level'
        ];

        $response = $this->makeRequest('groups.getById', $params);

        // Извлечение массива групп и возврат первого элемента
        if (isset($response['response']['groups']) && is_array($response['response']['groups']) && isset($response['response']['groups'][0])) {
            // Новый формат
            return ['response' => ['items' => [$response['response']['groups'][0]]]]; // Обертка для совместимости
        } elseif (isset($response['response']['items']) && is_array($response['response']['items']) && isset($response['response']['items'][0])) {
            // Старый формат
            return $response;
        }

        // Если ничего не нашли
        return ['response' => ['items' => []]];
    }


    public function postToWall($groupId, $message, $attachments = '', $fromGroup = 1) {
        $params = [
            'owner_id' => '-' . $groupId,
            'from_group' => $fromGroup,
            'message' => $message,
            'attachments' => $attachments
        ];

        return $this->makeRequest('wall.post', $params);
    }

    // Исправленный метод editWallPost
    public function editWallPost($postId, $groupId, $message, $attachments = '') {
        $ownerId = '-' . abs($groupId);
        $params = [
            'owner_id' => (int)$ownerId,
            'post_id' => (int)$postId,
            'message' => $message,
            'attachments' => $attachments
        ];

        return $this->makeRequest('wall.edit', $params);
    }

    // Исправленный метод getWallPost
    public function getWallPost($postId, $groupId) {
        $postIdentifier = '-' . abs($groupId) . '_' . $postId;
        $params = [
            'posts' => $postIdentifier,
            'extended' => 1,
            'fields' => 'views'
        ];

        return $this->makeRequest('wall.getById', $params);
    }

    public function uploadPhoto($photoPath, $groupId) {
		error_log("DEBUG: VKAPI::uploadPhoto called with photoPath: $photoPath, groupId: $groupId");
		
		// Проверяем, является ли файл валидным изображением
		$imageInfo = getimagesize($photoPath);
		if ($imageInfo === false) {
			// Проверяем, возможно, это файл, созданный из вставленного изображения
			$fileExtension = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
			$validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
			
			if (in_array($fileExtension, $validExtensions)) {
				// Если расширение валидное, но getimagesize не сработал, возможно, это файл из base64
				// Проверим, содержит ли файл допустимые сигнатуры изображений
				$fileHandle = fopen($photoPath, 'rb');
				if ($fileHandle) {
					$firstBytes = fread($fileHandle, 10);
					fclose($fileHandle);
					
					// Проверим сигнатуры для JPEG, PNG, GIF
					if (strpos($firstBytes, "\xFF\xD8\xFF") === 0 || // JPEG
						strpos($firstBytes, "\x89PNG") === 0 ||      // PNG
						strpos($firstBytes, "GIF") === 0) {         // GIF
						
						error_log("DEBUG: VKAPI::uploadPhoto - File appears to be a valid image based on signature: $photoPath");
					} else {
						error_log("DEBUG: VKAPI::uploadPhoto - File is not a valid image based on signature: $photoPath");
						throw new Exception('File is not a valid image');
					}
				} else {
					error_log("DEBUG: VKAPI::uploadPhoto - Could not read file signature: $photoPath");
					throw new Exception('Could not read file');
				}
			} else {
				error_log("DEBUG: VKAPI::uploadPhoto - File has invalid extension: $fileExtension, path: $photoPath");
				throw new Exception('File is not a valid image');
			}
		} else {
			error_log("DEBUG: VKAPI::uploadPhoto - Valid image detected, MIME: " . $imageInfo['mime']);
		}

		// --- ИСПРАВЛЕНО: Используем photos.getWallUploadServer вместо photos.getUploadServer ---
		$uploadServerResponse = $this->makeRequest('photos.getWallUploadServer', [
			'group_id' => $groupId  // Убираем album_id
		]);
		// ---
		
		if (!isset($uploadServerResponse['response']['upload_url'])) {
			error_log("DEBUG: VKAPI::uploadPhoto - No upload_url in getWallUploadServer response: " . print_r($uploadServerResponse, true));
			throw new Exception('No upload_url in upload server response');
		}
		
		$uploadUrl = $uploadServerResponse['response']['upload_url'];
		error_log("DEBUG: VKAPI::uploadPhoto - Upload URL: $uploadUrl");

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $uploadUrl);
		curl_setopt($ch, CURLOPT_POST, true);

		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mimeType = finfo_file($finfo, $photoPath);
		finfo_close($finfo);

		$extension = $this->getExtensionByMimeType($mimeType);
		$uploadFileName = 'image1.' . $extension;

		$cfile = new CURLFile($photoPath, $mimeType, $uploadFileName);
		$postFields = ['file1' => $cfile];

		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($ch);
		$curlError = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($result === false) {
			error_log("DEBUG: VKAPI::uploadPhoto - cURL execution failed: $curlError");
			throw new Exception('Failed to upload photo to server: ' . $curlError);
		}
		
		error_log("DEBUG: VKAPI::uploadPhoto - Upload result raw: $result (HTTP Code: $httpCode)");

		$uploadResult = json_decode($result, true);

		if (isset($uploadResult['error'])) {
			error_log("DEBUG: VKAPI::uploadPhoto - VK Upload Server Error: " . $uploadResult['error']['error_msg']);
			throw new Exception('VK Upload Server Error: ' . $uploadResult['error']['error_msg']);
		}

		if (!isset($uploadResult['photo']) || empty($uploadResult['photo'])) {
			error_log("DEBUG: VKAPI::uploadPhoto - No photo in upload result: " . print_r($uploadResult, true));
			throw new Exception('No photo in upload result');
		}
		
		error_log("DEBUG: VKAPI::uploadPhoto - Upload result photo: " . $uploadResult['photo']);
		error_log("DEBUG: VKAPI::uploadPhoto - Upload result server: " . $uploadResult['server']);
		error_log("DEBUG: VKAPI::uploadPhoto - Upload result hash: " . $uploadResult['hash']);

		// Добавляем задержку 1 секунду перед вызовом photos.save
		sleep(1);

		// --- ИСПРАВЛЕНО: Используем photos.saveWallPhoto вместо photos.save ---
		$saveResult = $this->makeRequest('photos.saveWallPhoto', [
			'group_id' => $groupId, // Используем group_id вместо album_id
			'server' => $uploadResult['server'],
			'photo' => $uploadResult['photo'], // Используем 'photo' вместо 'photos_list'
			'hash' => $uploadResult['hash']
		]);
		// ---
		
		error_log("DEBUG: VKAPI::uploadPhoto - Photos.saveWallPhoto response: " . print_r($saveResult, true));

		if (!isset($saveResult['response']) || !is_array($saveResult['response']) || !isset($saveResult['response'][0])) {
			error_log("DEBUG: VKAPI::uploadPhoto - Failed to save photo, save response: " . print_r($saveResult, true));
			throw new Exception('Failed to save photo');
		}

		$photo = $saveResult['response'][0];
		$attachmentId = 'photo' . $photo['owner_id'] . '_' . $photo['id'];
		error_log("DEBUG: VKAPI::uploadPhoto - Successfully uploaded photo, attachment ID: $attachmentId");
		return $attachmentId;
	}

    public function uploadVideo($videoPath, $groupId, $name = '', $description = '') {
        $uploadDir = __DIR__ . '/../uploads/videos/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileName = uniqid() . '_' . basename($videoPath);
        $localPath = $uploadDir . $fileName;

        if (!copy($videoPath, $localPath)) {
            throw new Exception('Failed to copy video to local directory');
        }

        $uploadServer = $this->makeRequest('video.save', [
            'group_id' => $groupId,
            'name' => $name ?: 'Video',
            'description' => $description
        ]);

        $uploadUrl = $uploadServer['response']['upload_url'];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $uploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $localPath);
        finfo_close($finfo);

        $extension = $this->getExtensionByMimeType($mimeType);
        $uploadFileName = 'video1.' . $extension;

        $cfile = new CURLFile($localPath, $mimeType, $uploadFileName);
        $postFields = ['video_file' => $cfile];

        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($ch);
        curl_close($ch);

        if (file_exists($localPath)) {
            unlink($localPath);
        }

        if ($result === false) {
            throw new Exception('Failed to upload video to server');
        }

        $uploadResult = json_decode($result, true);

        if (isset($uploadResult['error'])) {
            throw new Exception('VK Video Upload Error: ' . $uploadResult['error']['error_msg']);
        }

        if (!isset($uploadResult['video_id'])) {
            throw new Exception('Failed to get video_id from upload response');
        }

        return 'video' . $groupId . '_' . $uploadResult['video_id'];
    }

    private function getExtensionByMimeType($mimeType) {
        $mimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska' => 'mkv',
            'video/webm' => 'webm'
        ];

        return $mimeTypes[$mimeType] ?? 'jpg';
    }
}
?>