<?php
// vk_poster/api/get_stats_tasks.php

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_0: At start of script, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

// Добавляем принудительное отключение кеширования для этого запроса
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Попробовать сбросить opcache для текущего файла (только если opcache включен)
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true); // true - force invalidate
}

require_once __DIR__ . '/../config/config.php';

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_1: After require config.php, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

require_once __DIR__ . '/../includes/functions.php';

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_2: After require functions.php, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

require_once __DIR__ . '/../models/Statistics.php';

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_3: After require Statistics.php, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

header('Content-Type: application/json');

if (!Functions::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_4: After isLoggedIn check, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

$userId = Functions::getCurrentUserId();
$statsModel = new Statistics();

// --- ОТЛАДКА ---
error_log("DEBUG_STEP_5: After creating objects, _GET contents: " . print_r($_GET, true));
// --- /ОТЛАДКА ---

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Количество задач на страницу

error_log("DEBUG_STEP_6: Requested page: " . $page); // Проверьте логи PHP

$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}
if (!empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

$sort = [];
if (!empty($_GET['sort_column']) && !empty($_GET['sort_direction'])) {
    $sort['column'] = $_GET['sort_column'];
    $sort['direction'] = $_GET['sort_direction'];
}

try {
    $tasks = $statsModel->getAllStatsForUserTasks($userId, $page, $limit, $filters, $sort);
    $totalCount = $statsModel->getTotalStatsTasksCount($userId, $filters);

    $totalPages = ceil($totalCount / $limit);

    $result = [
        'data' => array_map(function($task) {
            // Добавляем флаг expanded для отслеживания состояния UI
            $task['expanded'] = false;
            return $task;
        }, $tasks),
        'pagination' => [
            'current_page' => $page, // <-- Используем $page из $_GET
            'pages' => $totalPages,
            'total' => $totalCount,
            'per_page' => $limit
        ]
    ];

    error_log("DEBUG_STEP_7: Returning pagination: " . json_encode($result['pagination'])); // Проверьте логи PHP

    echo json_encode($result);
} catch (Exception $e) {
    error_log("API get_stats_tasks error: " . $e->getMessage()); // Лучше в лог
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error']);
}
?>