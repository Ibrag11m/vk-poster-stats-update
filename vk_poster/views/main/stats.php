<?php
// vk_poster/views/main/stats.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
Functions::requireLogin(); // Проверка авторизации

$title = 'Статистика постов';
$additionalStyles = ['/vk_poster/assets/css/stats.css']; // Подключаем стили для stats.php
$additionalScripts = ['/vk_poster/assets/js/stats.js']; // Подключаем скрипты для stats.php

include __DIR__ . '/../layouts/header.php';

$user = Functions::getCurrentUser();
$userTimezone = $user['timezone'] ?? 'UTC';
?>

<div class="container">
    <h2>Статистика постов</h2>

    <div class="filters mb-3">
        <div class="row">
            <div class="col-md-2">
                <select id="filter-status" class="form-control">
                    <option value="">Все статусы</option>
                    <option value="published">Опубликованные</option>
                    <option value="edited">Отредактированные</option>
                    <option value="published_with_stats">Со статистикой</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" id="filter-date-from" class="form-control" placeholder="Дата от">
            </div>
            <div class="col-md-2">
                <input type="date" id="filter-date-to" class="form-control" placeholder="Дата до">
            </div>
            <div class="col-md-2">
                <button id="apply-filters" class="btn btn-primary">Применить</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-striped" id="stats-table">
            <thead>
                <tr>
                    <th data-sort="task_id">ID задачи <span class="sort-indicator"></span></th>
                    <th data-sort="created_at">Дата создания <span class="sort-indicator"></span></th>
                    <th>Кол-во сообществ</th>
                    <th data-sort="status">Статус <span class="sort-indicator"></span></th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody id="stats-tbody">
                <!-- Данные будут загружаться через AJAX -->
                <tr><td colspan="5" class="text-center">Загрузка...</td></tr>
            </tbody>
        </table>
    </div>

    <nav aria-label="Страницы">
        <ul class="pagination justify-content-center" id="pagination">
            <!-- Пагинация будет загружаться через AJAX -->
        </ul>
    </nav>
</div>

<!-- Модальное окно для просмотра скриншотов -->
<div class="modal fade" id="screenshotModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Скриншот поста</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="modal-screenshot-img" src="" alt="Скриншот поста" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>