<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class Post {
    private $conn;
    private $table_name = "posts";
    
    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, group_id, message, attachments, status, scheduled_time, created_at, vk_post_id) 
                  VALUES (:user_id, :group_id, :message, :attachments, :status, :scheduled_time, NOW(), :vk_post_id)";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':group_id', $data['group_id']);
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':attachments', $data['attachments']);
        $stmt->bindParam(':status', $data['status']);
        $stmt->bindParam(':scheduled_time', $data['scheduled_time']);
        $stmt->bindParam(':vk_post_id', $data['vk_post_id']);
        
        return $stmt->execute();
    }
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET message = :message, attachments = :attachments 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':attachments', $data['attachments']);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function getByUserId($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getByVkPostId($vkPostId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE vk_post_id = :vk_post_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':vk_post_id', $vkPostId);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function getByUserIdWithUserTimezone($userId) {
        $query = "SELECT p.* FROM " . $this->table_name . " p
                  WHERE p.user_id = :user_id 
                  ORDER BY p.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        $posts = $stmt->fetchAll();
        
        // Получаем пользовательский часовой пояс
        $user = Functions::getCurrentUser();
        $userTimezone = $user['timezone'] ?? 'UTC';
        
        // Преобразуем время в пользовательский часовой пояс
        foreach ($posts as &$post) {
            $post['created_at_formatted'] = Functions::formatUserTime($post['created_at'], $userTimezone);
        }
        
        return $posts;
    }
}
?>