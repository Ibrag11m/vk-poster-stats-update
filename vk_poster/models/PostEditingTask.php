<?php
require_once __DIR__ . '/../config/database.php';

class PostEditingTask {
    private $conn;
    private $table_name = "post_editing_tasks";
    
    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, post_vk_id, group_id, trigger_type, trigger_value, new_message, new_attachments, attachment_action, created_at) 
                  VALUES (:user_id, :post_vk_id, :group_id, :trigger_type, :trigger_value, :new_message, :new_attachments, :attachment_action, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':post_vk_id', $data['post_vk_id']);
        $stmt->bindParam(':group_id', $data['group_id']);
        $stmt->bindParam(':trigger_type', $data['trigger_type']);
        $stmt->bindParam(':trigger_value', $data['trigger_value']);
        $stmt->bindParam(':new_message', $data['new_message']);
        $stmt->bindParam(':new_attachments', $data['new_attachments']);
        $stmt->bindParam(':attachment_action', $data['attachment_action']);
        
        return $stmt->execute();
    }
    
    public function getPending() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE status = 'pending' 
                  ORDER BY created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE " . $this->table_name . " SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function getByUserId($userId) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}
?>