<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $conn;
    private $table_name = "users";
    
    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (username, email, password, vk_token, created_at, verification_token, is_verified, timezone) 
                  VALUES (:username, :email, :password, :vk_token, NOW(), :verification_token, 1, :timezone)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':username', $data['username']);
        $stmt->bindParam(':email', $data['email']);
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':vk_token', $data['vk_token']);
        $stmt->bindParam(':verification_token', $data['verification_token']);
        $stmt->bindParam(':timezone', $data['timezone']);
        
        return $stmt->execute();
    }
    
    public function findByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function updateVKToken($userId, $token) {
        $query = "UPDATE " . $this->table_name . " SET vk_token = :token WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':id', $userId);
        
        return $stmt->execute();
    }
    
    public function updateTimezone($userId, $timezone) {
        $query = "UPDATE " . $this->table_name . " SET timezone = :timezone WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':timezone', $timezone);
        $stmt->bindParam(':id', $userId);
        
        return $stmt->execute();
    }
    
    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    public function updatePassword($email, $newPassword) {
        $query = "UPDATE " . $this->table_name . " SET password = :password WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        
        return $stmt->execute();
    }
    
    public function generateVerificationToken($email) {
        $token = bin2hex(random_bytes(32));
        $query = "UPDATE " . $this->table_name . " SET verification_token = :token WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $token;
    }
    
    public function verifyToken($token) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE verification_token = :token";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function findByUsername($username) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>