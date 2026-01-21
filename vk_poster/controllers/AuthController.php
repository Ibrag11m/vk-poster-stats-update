<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    public function register($username, $email, $password, $confirmPassword, $vkToken, $timezone = 'UTC') {
        $errors = [];
        
        if (empty($username) || strlen($username) < 3) {
            $errors[] = 'Имя пользователя должно содержать не менее 3 символов';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Пароль должен содержать не менее 8 символов';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Пароли не совпадают';
        }
        
        // Проверяем, что пользователь с таким именем не существует
        if ($this->userModel->findByUsername($username)) {
            $errors[] = 'Пользователь с таким именем уже существует';
        }
        
        // Проверяем, что пользователь с таким email не существует
        if ($this->userModel->findByEmail($email)) {
            $errors[] = 'Пользователь с таким email уже существует';
        }
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $verificationToken = bin2hex(random_bytes(32));
        
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'vk_token' => $vkToken,
            'verification_token' => $verificationToken,
            'timezone' => $timezone // передаем часовой пояс
        ];
        
        try {
            $result = $this->userModel->create($userData);
            if ($result) {
                return ['success' => true, 'message' => 'Регистрация успешна'];
            } else {
                return ['success' => false, 'errors' => ['Ошибка при регистрации']];
            }
        } catch (Exception $e) {
            return ['success' => false, 'errors' => ['Ошибка при регистрации: ' . $e->getMessage()]];
        }
    }
    
    public function login($email, $password) {
        $user = $this->userModel->verifyPassword($email, $password);
        
        if ($user) {
            if ($user['is_verified'] == 1) {
                Session::set('user_id', $user['id']);
                Session::set('username', $user['username']);
                
                // Обновляем часовой пояс пользователя при входе
                if (isset($_POST['timezone'])) {
                    $this->userModel->updateTimezone($user['id'], $_POST['timezone']);
                }
                
                return ['success' => true, 'message' => 'Вход успешен'];
            } else {
                return ['success' => false, 'errors' => ['Пожалуйста, подтвердите ваш email']];
            }
        } else {
            return ['success' => false, 'errors' => ['Неверный email или пароль']];
        }
    }
    
    public function logout() {
        Session::destroy();
        return ['success' => true, 'message' => 'Выход успешен'];
    }
    
    public function forgotPassword($email) {
        $user = $this->userModel->findByEmail($email);
        
        if ($user) {
            $token = $this->userModel->generateVerificationToken($email);
            // Отправить email с токеном (реализация зависит от вашей системы)
            return ['success' => true, 'message' => 'Ссылка для сброса пароля отправлена на ваш email'];
        } else {
            return ['success' => false, 'errors' => ['Пользователь с таким email не найден']];
        }
    }
    
    public function resetPassword($token, $password, $confirmPassword) {
        if ($password !== $confirmPassword) {
            return ['success' => false, 'errors' => ['Пароли не совпадают']];
        }
        
        $user = $this->userModel->verifyToken($token);
        
        if ($user) {
            $result = $this->userModel->updatePassword($user['email'], $password);
            if ($result) {
                return ['success' => true, 'message' => 'Пароль успешно изменен'];
            } else {
                return ['success' => false, 'errors' => ['Ошибка при изменении пароля']];
            }
        } else {
            return ['success' => false, 'errors' => ['Неверный токен']];
        }
    }
}
?>