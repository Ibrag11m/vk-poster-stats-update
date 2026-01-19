<?php
// Основная конфигурация приложения
define('APP_NAME', 'VK Poster');
define('APP_VERSION', '1.0.0');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('PHOTOS_PATH', UPLOAD_PATH . '/photos');
define('VIDEOS_PATH', UPLOAD_PATH . '/videos');

// Настройки сессии
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');

// Настройки безопасности
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', 3600); // 1 час

// Настройки приложения
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_PHOTO_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/quicktime']);
?>