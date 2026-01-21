<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../models/User.php';

class Functions {
    public static function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePassword($password) {
        return strlen($password) >= 8;
    }
    
    public static function uploadFile($file, $type = 'photo') {
        $uploadDir = ($type === 'photo') ? PHOTOS_PATH : VIDEOS_PATH;
        
        if (!is_dir($uploadDir)) {
            $result = mkdir($uploadDir, 0755, true);
            if (!$result) {
                error_log("Failed to create upload directory: " . $uploadDir);
                return false;
            }
        }
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        $fileType = $file['type'];
        $allowedTypes = ($type === 'photo') ? ALLOWED_PHOTO_TYPES : ALLOWED_VIDEO_TYPES;
        
        if (!in_array($fileType, $allowedTypes)) {
            return false;
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            return false;
        }
        
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . '/' . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return $fileName;
        }
        
        return false;
    }
    
    public static function isLoggedIn() {
        return Session::has('user_id');
    }
    
	public static function requireLogin() {
		if (!self::isLoggedIn()) {
			$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
					  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

			if ($isAjax) {
				// Ответ для AJAX: JSON + статус 401
				http_response_code(401);
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode([
					'success' => false,
					'message' => 'Требуется авторизация. Перезагрузите страницу.'
				]);
				exit();
			} else {
				// Обычный редирект
				header('Location: /vk_poster/views/auth/login.php');
				exit();
			}
		}
	}
    
    public static function getCurrentUserId() {
        return Session::get('user_id');
    }
    
    public static function getCurrentUser() {
        if (self::isLoggedIn()) {
            $userModel = new User();
            return $userModel->findById(self::getCurrentUserId());
        }
        return null;
    }
    
    public static function requireCSRFToken() {
        if (!isset($_POST['csrf_token']) || !self::validateCSRFToken($_POST['csrf_token'])) {
            Session::setFlash('error', 'Неверный токен безопасности');
            header('Location: /vk_poster/');
            exit();
        }
    }
    
    public static function convertServerTimeToUserTime($serverTime, $userTimezone = 'UTC') {
        // Преобразуем серверное время в пользовательское
        $serverDateTime = new DateTime($serverTime, new DateTimeZone(date_default_timezone_get()));
        $userDateTime = clone $serverDateTime;
        
        // Проверяем формат часового пояса
        if (preg_match('/^UTC([+-])(\d+)$/', $userTimezone, $matches)) {
            // Преобразуем UTC+5 в формат +05:00
            $sign = $matches[1];
            $offset = $matches[2];
            if (strlen($offset) == 1) {
                $offset = '0' . $offset;
            }
            $offset = $sign . $offset . ':00';
            $userDateTime->setTimezone(new DateTimeZone($offset));
        } else {
            // Используем обычный часовой пояс
            $userDateTime->setTimezone(new DateTimeZone($userTimezone));
        }
        
        return $userDateTime->format('Y-m-d H:i:s');
    }
    
    public static function formatUserTime($serverTime, $userTimezone = 'UTC', $format = 'd.m.Y H:i') {
        // Форматируем время в пользовательском часовом поясе
        $serverDateTime = new DateTime($serverTime, new DateTimeZone(date_default_timezone_get()));
        
        // Проверяем формат часового пояса
        if (preg_match('/^UTC([+-])(\d+)$/', $userTimezone, $matches)) {
            // Преобразуем UTC+5 в формат +05:00
            $sign = $matches[1];
            $offset = $matches[2];
            if (strlen($offset) == 1) {
                $offset = '0' . $offset;
            }
            $offset = $sign . $offset . ':00';
            $serverDateTime->setTimezone(new DateTimeZone($offset));
        } else {
            // Используем обычный часовой пояс
            $serverDateTime->setTimezone(new DateTimeZone($userTimezone));
        }
        
        return $serverDateTime->format($format);
    }
	
	public static function convertTimezoneToUTCOffset($timezone) {
		try {
			$tz = new DateTimeZone($timezone);
			$now = new DateTime('now', $tz);
			$offset = $tz->getOffset($now);
			$offsetHours = floor(abs($offset) / 3600);
			$offsetSign = $offset >= 0 ? '+' : '-';
			
			return 'UTC' . $offsetSign . $offsetHours;
		} catch (Exception $e) {
			return 'UTC';
		}
	}
}
?>