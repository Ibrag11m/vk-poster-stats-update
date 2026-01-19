<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../controllers/PostController.php';
require_once __DIR__ . '/../models/User.php';

try {
    $postController = new PostController();
    $postController->processScheduledPosts();
    
    echo "Scheduled posts processed successfully\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>