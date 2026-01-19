<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

class UserPostGroup {
    private $conn;
    private $table_name = "user_post_groups";
    private $member_table_name = "user_post_group_members";
    
    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }
    
    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, name, description, created_at) 
                  VALUES (:user_id, :name, :description, NOW())";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        
        return $stmt->execute();
    }
    
    public function update($id, $data, $userId) {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, description = :description 
                  WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        
        return $stmt->execute();
    }
    
    public function delete($id, $userId) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }
    
    public function getById($id, $userId = null) {
        if ($userId) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id AND user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':user_id', $userId);
        } else {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
        }
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function getByUserId($userId) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function addMember($groupId, $groupIdMember) {
        $query = "INSERT INTO " . $this->member_table_name . " 
                  (group_id, group_id_member, created_at) 
                  VALUES (:group_id, :group_id_member, NOW())";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':group_id', $groupId);
        $stmt->bindParam(':group_id_member', $groupIdMember);
        
        return $stmt->execute();
    }
    
    public function removeMember($groupId, $groupIdMember) {
        $query = "DELETE FROM " . $this->member_table_name . " 
                  WHERE group_id = :group_id AND group_id_member = :group_id_member";
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':group_id', $groupId);
        $stmt->bindParam(':group_id_member', $groupIdMember);
        
        return $stmt->execute();
    }
    
    public function getMembers($groupId) {
        // Получаем все group_id_member, связанные с данной пользовательской группой
        $query = "SELECT group_id_member FROM " . $this->member_table_name . " 
                  WHERE group_id = :group_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':group_id', $groupId);
        $stmt->execute();
        
        $groupIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if (empty($groupIds)) {
            return [];
        }
        
        // Получаем информацию о группах из ВКонтакте
        try {
            // Получаем токен текущего пользователя
            $currentUser = Functions::getCurrentUser();
            if (!$currentUser || empty($currentUser['vk_token'])) {
                throw new Exception('No VK token available for current user');
            }
            
            $vk = new VKAPI($currentUser['vk_token']);
            
            // Преобразуем массив ID в строку, разделенную запятыми
            $groupIdsStr = implode(',', $groupIds);
            
            // Вызываем groups.getById
            $groupsDetails = $vk->getGroupsByIds($groupIdsStr);
            
            // Формируем результат в нужном формате
            $result = [];
            foreach ($groupsDetails as $group) {
                $result[] = [
                    'group_id' => $group['id'],
                    'group_name' => $group['name'],
                    'screen_name' => $group['screen_name']
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("ERROR: UserPostGroup::getMembers failed to fetch groups from VK: " . $e->getMessage());
            return [];
        }
    }
    
    public function getAllMembersForUser($userId) {
        $query = "SELECT g.name as group_name, pg.name as post_group_name, ugm.*
                  FROM " . $this->member_table_name . " ugm
                  JOIN user_post_groups pg ON ugm.group_id = pg.id
                  JOIN user_groups g ON ugm.group_id_member = g.group_id
                  WHERE pg.user_id = :user_id
                  ORDER BY pg.name, g.name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
?>