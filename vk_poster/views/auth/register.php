<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

if (Functions::isLoggedIn()) {
    header('Location: /vk_poster/');
    exit();
}

if ($_POST) {
    $username = Functions::sanitizeInput($_POST['username']);
    $email = Functions::sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $vkToken = Functions::sanitizeInput($_POST['vk_token']);
    $timezone = $_POST['timezone'] ?? 'UTC';
    
    // Преобразуем IANA формат в UTC+/- формат
    if (!preg_match('/^UTC([+-])(\d+)$/', $timezone)) {
        // Если это IANA формат, преобразуем в UTC+/- формат
        $timezone = Functions::convertTimezoneToUTCOffset($timezone);
    }
    
    $authController = new AuthController();
    $result = $authController->register($username, $email, $password, $confirmPassword, $vkToken, $timezone);
    
    if ($result['success']) {
        Session::setFlash('success', $result['message']);
        header('Location: /vk_poster/views/auth/login.php');
        exit();
    } else {
        $errors = $result['errors'];
    }
}

$title = 'Регистрация';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';
?>

<div class="form-container">
    <h2>Регистрация</h2>
    
    <?php if ($message = Session::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Имя пользователя:</label>
            <input type="text" id="username" name="username" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="password">Пароль:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Подтвердите пароль:</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
        </div>
        
        <div class="form-group">
            <label for="vk_token">Токен доступа ВКонтакте:</label>
            <input type="text" id="vk_token" name="vk_token" class="form-control" required>
            <small>Получить токен можно на <a href="https://vk.com/dev" target="_blank">https://vk.com/dev</a></small>
        </div>
        
        <input type="hidden" name="timezone" id="timezone" value="UTC">
        
        <button type="submit" class="btn btn-primary">Зарегистрироваться</button>
        <a href="/vk_poster/views/auth/login.php" class="btn btn-secondary">Войти</a>
    </form>
</div>

<script>
// Устанавливаем часовой пояс пользователя
document.addEventListener('DOMContentLoaded', function() {
    const timezoneInput = document.getElementById('timezone');
    if (timezoneInput) {
        // Получаем смещение в формате UTC+/-HH
        const userTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const now = new Date();
        const offsetMinutes = now.getTimezoneOffset();
        const offsetHours = Math.floor(Math.abs(offsetMinutes) / 60);
        const offsetSign = offsetMinutes <= 0 ? '+' : '-';
        const utcOffset = 'UTC' + offsetSign + offsetHours;
        
        timezoneInput.value = utcOffset;
    }
});
</script>

<?php include '../layouts/footer.php'; ?>