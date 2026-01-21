<?php
// vk_poster/models/Statistics.php
require_once __DIR__ . '/../config/database.php';

class Statistics {
    private $conn;
    private $table_name = "post_statistics"; // Основная таблица
    private $snapshots_table_name = "post_statistics_snapshots"; // Таблица снимков

    public function __construct() {
        $database = Database::getInstance();
        $this->conn = $database->getConnection();
    }

    /**
     * Получить последнюю статистику для конкретного поста из основной таблицы
     */
    public function getLastStatsForPost($vkPostId) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE post_vk_id = :post_vk_id
                  ORDER BY saved_at DESC
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_vk_id', $vkPostId);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Получить все снимки статистики для конкретного поста (например, на моменты редактирования)
     */
    public function getAllSnapshotsForPost($vkPostId) {
        $query = "SELECT * FROM " . $this->snapshots_table_name . "
                  WHERE post_vk_id = :post_vk_id
                  ORDER BY captured_at DESC"; // Сортируем по времени снятия, новейшие первыми
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_vk_id', $vkPostId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Получить последний снимок статистики для конкретного поста
     */
    public function getLastSnapshotForPost($vkPostId) {
        $query = "SELECT * FROM " . $this->snapshots_table_name . "
                  WHERE post_vk_id = :post_vk_id
                  ORDER BY captured_at DESC
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_vk_id', $vkPostId);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Сохранить новый снимок статистики (например, на момент редактирования)
     */
    public function saveSnapshot($vkPostId, $stats) {
        $query = "INSERT INTO " . $this->snapshots_table_name . "
                  (post_vk_id, views, likes, reposts, comments, coverage, virality_reach, screenshot_path)
                  VALUES (:post_vk_id, :views, :likes, :reposts, :comments, :coverage, :virality_reach, :screenshot_path)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_vk_id', $vkPostId);
        $stmt->bindParam(':views', $stats['views'] ?? 0);
        $stmt->bindParam(':likes', $stats['likes'] ?? 0);
        $stmt->bindParam(':reposts', $stats['reposts'] ?? 0);
        $stmt->bindParam(':comments', $stats['comments'] ?? 0);
        $stmt->bindParam(':coverage', $stats['coverage'] ?? 0);
        $stmt->bindParam(':virality_reach', $stats['virality_reach'] ?? 0);
        $stmt->bindParam(':screenshot_path', $stats['screenshot_path'] ?? null); // Пока null, если скриншоты не реализованы
        return $stmt->execute();
    }

    /**
     * Обновить или создать запись статистики в основной таблице
     */
    public function updateOrCreate($vkPostId, $stats) {
        $query = "INSERT INTO " . $this->table_name . "
                  (post_vk_id, views, likes, reposts, comments, coverage, virality_reach, screenshot_path)
                  VALUES (:post_vk_id, :views, :likes, :reposts, :comments, :coverage, :virality_reach, :screenshot_path)
                  ON DUPLICATE KEY UPDATE
                  views = VALUES(views),
                  likes = VALUES(likes),
                  reposts = VALUES(reposts),
                  comments = VALUES(comments),
                  coverage = VALUES(coverage),
                  virality_reach = VALUES(virality_reach),
                  screenshot_path = VALUES(screenshot_path),
                  saved_at = NOW()"; // Обновляем время сохранения
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':post_vk_id', $vkPostId);
        $stmt->bindParam(':views', $stats['views'] ?? 0);
        $stmt->bindParam(':likes', $stats['likes'] ?? 0);
        $stmt->bindParam(':reposts', $stats['reposts'] ?? 0);
        $stmt->bindParam(':comments', $stats['comments'] ?? 0);
        $stmt->bindParam(':coverage', $stats['coverage'] ?? 0);
        $stmt->bindParam(':virality_reach', $stats['virality_reach'] ?? 0);
        $stmt->bindParam(':screenshot_path', $stats['screenshot_path'] ?? null); // Пока null
        return $stmt->execute();
    }

    /**
     * Получить все задачи (группировки по VK ID) для отображения в таблице статистики
     * Объединяет опубликованные и отредактированные посты по VK ID
     */
    public function getAllStatsForUserTasks($userId, $page = 1, $limit = 10, $filters = [], $sort = []) {
        $offset = ($page - 1) * $limit;

        // Формируем подзапрос как строку, подставляя userId как число (безопасно для INT)
        $userIdLiteral = intval($userId);
        $subQuery = "
            SELECT DISTINCT p.vk_post_id as task_id, p.id as original_post_id, p.created_at as post_created_at
            FROM posts p
            WHERE p.user_id = " . $userIdLiteral . "
            UNION
            SELECT pet.post_vk_id as task_id, NULL as original_post_id, pet.created_at as post_created_at
            FROM post_editing_tasks pet
            WHERE pet.user_id = " . $userIdLiteral . "
        ";

        // Основной запрос теперь не использует :user_id в WHERE после подзапроса
        $query = "
            SELECT
                sub.task_id,
                ANY_VALUE(sub.original_post_id) as original_post_id,
                MAX(sub.post_created_at) as created_at,
                COUNT(DISTINCT COALESCE(p.group_id, pet.group_id)) as community_count,
                CASE
                    WHEN MAX(ss.captured_at) IS NOT NULL THEN 'edited'
                    WHEN MAX(s.saved_at) IS NOT NULL THEN 'published_with_stats'
                    ELSE 'published'
                END as status
            FROM (" . $subQuery . ") sub
            LEFT JOIN posts p ON p.vk_post_id = sub.task_id AND p.user_id = :user_id_p -- <-- Новый параметр
            LEFT JOIN post_editing_tasks pet ON pet.post_vk_id = sub.task_id AND pet.user_id = :user_id_pet -- <-- Новый параметр
            LEFT JOIN (
                SELECT post_vk_id, MAX(saved_at) as saved_at
                FROM post_statistics
                GROUP BY post_vk_id
            ) s ON sub.task_id = s.post_vk_id
            LEFT JOIN (
                SELECT post_vk_id, MAX(captured_at) as captured_at
                FROM post_statistics_snapshots
                GROUP BY post_vk_id
            ) ss ON sub.task_id = ss.post_vk_id
            -- WHERE больше не нужен для фильтрации по пользователю, так как подзапрос уже отфильтрован
        ";

        $whereClause = " WHERE 1=1 "; // <-- Инициализируем WHERE
        $havingClause = "";
        // $params теперь не включает :user_id, так как он в подзапросе
        $params = [
            ':user_id_p' => $userId, // <-- Добавлено
            ':user_id_pet' => $userId // <-- Добавлено
        ]; 

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'published':
                    $havingClause = " HAVING status = 'published' OR status = 'published_with_stats' ";
                    break;
                case 'edited':
                    $havingClause = " HAVING status = 'edited' ";
                    break;
                case 'published_with_stats':
                    $havingClause = " HAVING status = 'published_with_stats' ";
                    break;
            }
        }

        if (!empty($filters['date_from'])) {
            $whereClause .= " AND MAX(sub.post_created_at) >= :date_from ";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND MAX(sub.post_created_at) <= :date_to ";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $query .= $whereClause . " GROUP BY sub.task_id "; // <-- GROUP BY после WHERE
        if ($havingClause) {
            $query .= $havingClause;
        }

        $orderByClause = " ORDER BY created_at DESC";
        if (!empty($sort['column']) && in_array($sort['column'], ['task_id', 'created_at', 'community_count', 'status'])) {
            $direction = strtoupper($sort['direction']) === 'ASC' ? 'ASC' : 'DESC';
            $orderByClause = " ORDER BY " . $sort['column'] . " " . $direction;
        }
        $query .= $orderByClause;

        $query .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $dataType = PDO::PARAM_STR;
            if ($key === ':limit' || $key === ':offset') {
                $dataType = PDO::PARAM_INT;
            }
            $stmt->bindValue($key, $value, $dataType);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

        /**
     * Получить общее количество задач для пагинации
     * ВАЖНО: Должно учитывать те же фильтры, что и getAllStatsForUserTasks
     */
    public function getTotalStatsTasksCount($userId, $filters = []) {
        $userIdLiteral = intval($userId);
        // Используем тот же подзапрос, что и в getAllStatsForUserTasks
        $subQuery = "
            SELECT DISTINCT p.vk_post_id as task_id, p.id as original_post_id, p.created_at as post_created_at
            FROM posts p
            WHERE p.user_id = " . $userIdLiteral . "
            UNION
            SELECT pet.post_vk_id as task_id, NULL as original_post_id, pet.created_at as post_created_at
            FROM post_editing_tasks pet
            WHERE pet.user_id = " . $userIdLiteral . "
        ";

        // Запрос должен повторять основной запрос, но с COUNT(*)
        // Вычисляем статус внутри подзапроса для использования в HAVING
        $query = "
            SELECT COUNT(DISTINCT counted_tasks.task_id) as total
            FROM (
                SELECT sub.task_id,
                       CASE
                           WHEN MAX(ss.captured_at) IS NOT NULL THEN 'edited'
                           WHEN MAX(s.saved_at) IS NOT NULL THEN 'published_with_stats'
                           ELSE 'published'
                       END as status_calc -- <-- Алиас для вычисленного статуса
                FROM (" . $subQuery . ") sub
                LEFT JOIN posts p ON p.vk_post_id = sub.task_id
                LEFT JOIN post_editing_tasks pet ON pet.post_vk_id = sub.task_id
                LEFT JOIN (
                    SELECT post_vk_id, MAX(saved_at) as saved_at
                    FROM post_statistics
                    GROUP BY post_vk_id
                ) s ON sub.task_id = s.post_vk_id
                LEFT JOIN (
                    SELECT post_vk_id, MAX(captured_at) as captured_at
                    FROM post_statistics_snapshots
                    GROUP BY post_vk_id
                ) ss ON sub.task_id = ss.post_vk_id
                WHERE p.user_id = :user_id_p OR pet.user_id = :user_id_pet
                GROUP BY sub.task_id
        ";

        $params = [
            ':user_id_p' => $userId, // <-- Параметры для WHERE
            ':user_id_pet' => $userId
        ];

        $havingClause = " HAVING 1=1 "; // <-- Инициализируем HAVING

        // Применяем фильтр по статусу
        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'published':
                    // Статус 'published' или 'published_with_stats' (но published_with_stats != published, так что это может быть не совсем так)
                    // Нужно быть точнее: статус 'published' означает, что не было редактирования (no 'edited') и нет статистики (not 'published_with_stats')
                    // Или 'published' означает все, что не 'edited'? Смотря как вы определяете статус в getAllStatsForUserTasks.
                    // Для простоты, пусть будет как в предыдущем варианте, но с HAVING по вычисленному полю
                    $havingClause = " HAVING status_calc = 'published' ";
                    break;
                case 'edited':
                    $havingClause = " HAVING status_calc = 'edited' ";
                    break;
                case 'published_with_stats':
                    $havingClause = " HAVING status_calc = 'published_with_stats' ";
                    break;
                default:
                    $havingClause = " HAVING 1=1 "; // Не фильтруем по статусу
                    break;
            }
        }

        // Применяем фильтры по дате
        if (!empty($filters['date_from'])) {
            $query .= " AND MAX(sub.post_created_at) >= :date_from "; // <-- Параметр в WHERE основного запроса (в подзапросе)
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND MAX(sub.post_created_at) <= :date_to "; // <-- Параметр в WHERE основного запроса (в подзапросе)
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $query .= $havingClause . ") counted_tasks"; // <-- Закрываем подзапрос

        // Теперь $query готов
        // echo $query; die(); // <-- ВРЕМЕННО: раскомментируйте, чтобы посмотреть итоговый SQL
        // var_dump($params); // <-- ВРЕМЕННО: раскомментируйте, чтобы посмотреть параметры

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
             $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchColumn();
    }


    /**
     * Получить все посты, связанные с конкретной задачей (VK ID)
     * Это включает как оригинальный пост (из posts), так и информацию из задач редактирования (post_editing_tasks)
     */
    public function getPostsForTask($vkPostId, $userId) {
        $query = "
            SELECT
                p.id as post_id,
                p.vk_post_id,
                p.group_id,
                p.message,
                p.attachments,
                p.status as post_status, -- Статус поста
                p.created_at as post_created_at,
                s.views as current_views,
                s.likes as current_likes,
                s.reposts as current_reposts,
                s.comments as current_comments,
                s.coverage as current_coverage,
                s.virality_reach as current_virality_reach,
                s.saved_at as current_stats_updated_at,
                s.screenshot_path as current_screenshot_path,
                ss.views as edit_views,
                ss.likes as edit_likes,
                ss.reposts as edit_reposts,
                ss.comments as edit_comments,
                ss.coverage as edit_coverage,
                ss.virality_reach as edit_virality_reach,
                ss.captured_at as edit_stats_captured_at,
                ss.screenshot_path as edit_screenshot_path,
                CASE WHEN ss.id IS NOT NULL THEN 1 ELSE 0 END as has_edit_history
            FROM posts p
            LEFT JOIN post_statistics s ON p.vk_post_id = s.post_vk_id -- Присоединяем текущую статистику
            LEFT JOIN post_statistics_snapshots ss ON p.vk_post_id = ss.post_vk_id -- Присоединяем снимок статистики
            WHERE p.vk_post_id = :vk_post_id AND p.user_id = :user_id
            ORDER BY p.created_at DESC -- Сортируем по дате создания поста
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':vk_post_id', $vkPostId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

}