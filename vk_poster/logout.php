<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

Session::destroy();
Session::setFlash('success', 'Вы успешно вышли из системы');

header('Location: /vk_poster/views/auth/login.php');
exit();
?>