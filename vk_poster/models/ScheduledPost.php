<?php
require_once __DIR__ . '/../config/database.php';

class ScheduledPost {
    private $conn;
    private $table_name = "scheduled_posts";
    
    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, group_id, message, attachments, temp_attachments, schedule_time, created_at) 
                  VALUES (:user_id, :group_id, :message, :attachments, :temp_attachments, :schedule_time, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':group_id', $data['group_id']);
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':attachments', $data['attachments']);
        $stmt->bindParam(':temp_attachments', $data['temp_attachments']);
        $stmt->bindParam(':schedule_time', $data['schedule_time']);
        
        return $stmt->execute();
    }
    
    public function getPending() {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE status = 'pending' AND schedule_time <= NOW() 
                  ORDER BY schedule_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getPendingByUser($userId) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND status = 'pending' 
                  ORDER BY schedule_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
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
    
    public function deleteTempAttachments($id) {
        $query = "UPDATE " . $this->table_name . " SET temp_attachments = NULL WHERE id = :id";
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
    
    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . " 
                  SET group_id = :group_id, message = :message, 
                      schedule_time = :schedule_time 
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':group_id', $data['group_id']);
        $stmt->bindParam(':message', $data['message']);
        $stmt->bindParam(':schedule_time', $data['schedule_time']);
        
        return $stmt->execute();
    }
    
    public function getUserScheduledPosts($userId) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id 
                  ORDER BY schedule_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getUserPendingScheduledPosts($userId) {
        $query = "SELECT * FROM " . $this->table_name . " 
                  WHERE user_id = :user_id AND status = 'pending'
                  ORDER BY schedule_time ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>