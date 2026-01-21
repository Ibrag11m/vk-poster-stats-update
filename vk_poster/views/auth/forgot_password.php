<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

if (Functions::isLoggedIn()) {
    header('Location: /vk_poster/');
    exit();
}

$title = 'Восстановление пароля';
$additionalScripts = ['/vk_poster/assets/js/main.js'];

if ($_POST) {
    Functions::requireCSRFToken(); // Добавлено требование CSRF токена
    
    $email = Functions::sanitizeInput($_POST['email'] ?? '');
    
    $authController = new AuthController();
    $result = $authController->forgotPassword($email);
    
    if ($result['success']) {
        Session::setFlash('success', $result['message']);
        header('Location: login.php');
        exit();
    } else {
        Session::setFlash('error', $result['message']);
    }
}

include '../layouts/header.php';
?>

<div class="auth-container">
    <div class="auth-form">
        <h2>Восстановление пароля</h2>
        
        <?php if ($message = Session::getFlash('error')): ?>
            <div class="alert alert-error"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($message = Session::getFlash('success')): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <p>Введите ваш email, и мы отправим ссылку для сброса пароля.</p>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required class="form-control">
            </div>
            
            <button type="submit" class="btn btn-primary">Отправить</button>
        </form>
        
        <div class="auth-links">
            <a href="login.php">Вернуться к входу</a>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>