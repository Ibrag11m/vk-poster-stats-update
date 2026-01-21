<?php
// vk_poster/api/get_task_posts.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Statistics.php';


header('Content-Type: application/json');

if (!Functions::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = Functions::getCurrentUserId();
$statsModel = new Statistics();

$taskId = isset($_GET['task_id']) ? trim($_GET['task_id']) : '';

if (empty($taskId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Task ID is required']);
    exit;
}

try {
    $posts = $statsModel->getPostsForTask($taskId, $userId);

    echo json_encode(['posts' => $posts]);
} catch (Exception $e) {
    error_log("API get_task_posts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
?>