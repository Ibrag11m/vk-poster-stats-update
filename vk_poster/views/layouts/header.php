<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title . ' - ' . APP_NAME : APP_NAME; ?></title>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/vk_poster/assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- $additionalScripts НЕ подключаются здесь -->
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="header-content">
                <h1 class="logo"><a href="/vk_poster/">VK Poster</a></h1>
                <?php if (Functions::isLoggedIn()): ?>
                    <nav class="nav">
                        <a href="/vk_poster/" class="nav-link">Панель управления</a>
                        <a href="/vk_poster/views/main/select_groups.php" class="nav-link">Группы</a>
                        <a href="/vk_poster/views/main/create_post.php" class="nav-link">Публикация</a>
                        <a href="/vk_poster/views/main/edit_posts_task.php" class="nav-link">Редактирование</a>
                        <a href="/vk_poster/views/main/scheduled_posts.php" class="nav-link">Запланированные</a>
                        <a href="/vk_poster/views/main/stats.php" class="nav-link">Статистика</a>
                        <a href="/vk_poster/views/auth/logout.php" class="nav-link logout">Выйти</a>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <main class="main">
        <div class="container">
            <?php if (Session::getFlash('success')): ?>
                <div class="alert alert-success"><?php echo Session::getFlash('success'); ?></div>
            <?php endif; ?>
            <?php if (Session::getFlash('error')): ?>
                <div class="alert alert-error"><?php echo Session::getFlash('error'); ?></div>
            <?php endif; ?>