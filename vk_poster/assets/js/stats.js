// vk_poster/assets/js/stats.js
$(document).ready(function() {
    console.log("DEBUG: Document ready fired");
    loadStatsData();

    // Обработчик кнопки применения фильтров
    $('#apply-filters').on('click', function() {
        console.log("DEBUG: Apply filters clicked");
        loadStatsData();
    });

    // Сортировка по клику на заголовке колонки
    $(document).on('click', '#stats-table th[data-sort]', function() {
        console.log("DEBUG: Sort header clicked");
        var column = $(this).data('sort');
        var direction = $(this).hasClass('asc') ? 'desc' : 'asc';

        // Удаляем классы сортировки
        $('#stats-table th').removeClass('asc desc');
        $(this).addClass(direction);

        // Загружаем данные с сортировкой
        loadStatsData({
            sort_column: column,
            sort_direction: direction
        });
    });
});

function loadStatsData(additionalParams) {
    console.log("DEBUG: loadStatsData called with params:", additionalParams);
    if (typeof additionalParams === 'undefined') {
        additionalParams = {};
    }

    var params = {
        page: additionalParams.page || 1,
        status: $('#filter-status').val(),
        date_from: $('#filter-date-from').val(),
        date_to: $('#filter-date-to').val(),
        sort_column: additionalParams.sort_column || '',
        sort_direction: additionalParams.sort_direction || ''
    };

    // Показываем индикатор загрузки
    $('#stats-tbody').html('<tr><td colspan="5" class="text-center">Загрузка...</td></tr>');

    $.ajax({
        url: '/vk_poster/api/get_stats_tasks.php',
        method: 'GET',
        data: params, // <-- ИСПРАВЛЕНО: используем 'data'
        dataType: 'json',
        success: function(response) {
            console.log("DEBUG: AJAX success, response:", response);
            updateStatsTable(response.data);
            updatePagination(response.pagination);
        },
        error: function(xhr, status, error) {
            console.error('DEBUG: AJAX error occurred:', error);
            console.error('DEBUG: XHR status:', xhr.status);
            console.error('DEBUG: XHR response text:', xhr.responseText);
            $('#stats-tbody').html('<tr><td colspan="5" class="text-center text-danger">Ошибка при загрузке данных</td></tr>');
        }
    });
}

function updateStatsTable(data) {
    console.log("DEBUG: updateStatsTable called with ", data);
    var html = '';

    data.forEach(function(task) {
        // Установим флаг expanded в false для всех задач по умолчанию
        if (typeof task.expanded === 'undefined') {
            task.expanded = false;
        }
        html += `
            <tr>
                <td>${task.task_id}</td>
                <td>${formatDate(task.created_at)}</td>
                <td>${task.community_count}</td>
                <td><span class="badge badge-${getStatusBadgeClass(task.status)}">${getStatusText(task.status)}</span></td>
                <td>
                    <button class="btn btn-info btn-sm toggle-task" data-task-id="${task.task_id}">
                        ${task.expanded ? 'Скрыть' : 'Показать'}
                    </button>
                </td>
            </tr>
        `;

        // Отображаем контейнер постов только если задача отмечена как expanded (хотя изначально false)
        if (task.expanded && task.posts && task.posts.length > 0) {
            html += `<tr class="task-posts-row" data-task-id="${task.task_id}" style="display: table-row;">
                        <td colspan="5">
                            <div class="task-posts-container">
                                ${generatePostsHtml(task.posts)}
                            </div>
                        </td>
                     </tr>`;
        } else {
            // Добавим скрытую строку-контейнер для постов, чтобы можно было в нее вставить данные позже
            html += `<tr class="task-posts-row" data-task-id="${task.task_id}" style="display: none;">
                        <td colspan="5">
                            <div class="task-posts-container">
                                <!-- Посты будут загружены сюда -->
                            </div>
                        </td>
                     </tr>`;
        }
    });

    $('#stats-tbody').html(html);
}

function generatePostsHtml(posts) {
    console.log("DEBUG: generatePostsHtml called with posts:", posts);
    var html = '<div class="posts-grid">';

    posts.forEach(function(post) {
        console.log("DEBUG: Generating HTML for post:", post);
        html += `
            <div class="post-card">
                <div class="post-header">
                    <strong>Сообщество ID: ${post.group_id}</strong>
                    <span class="post-status">${getStatusText(post.post_status)}</span>
                </div>

                <div class="post-content">
                    <p><strong>Сообщение:</strong> ${truncateText(post.message, 100)}</p>

                    ${post.attachments ? `<p><strong>Вложения:</strong> ${post.attachments}</p>` : ''}

                    ${(post.current_screenshot_path || post.edit_screenshot_path) ? `
                        <div class="screenshot-preview">
                            <img src="${post.current_screenshot_path || post.edit_screenshot_path}" alt="Скриншот поста"
                                 class="screenshot-thumb"
                                 onclick="showScreenshot('${post.current_screenshot_path || post.edit_screenshot_path}')">
                        </div>
                    ` : ''}
                </div>

                <div class="stats-comparison">
                    ${post.has_edit_history ? `
                        <div class="stats-section">
                            <h5>Статистика на момент редактирования:</h5>
                            <div class="stats-row">
                                <span class="stat-item">Просмотры: <strong>${post.edit_stats.views}</strong></span>
                                <span class="stat-item">Лайки: <strong>${post.edit_stats.likes}</strong></span>
                                <span class="stat-item">Репосты: <strong>${post.edit_stats.reposts}</strong></span>
                                <span class="stat-item">Комментарии: <strong>${post.edit_stats.comments}</strong></span>
                                <span class="stat-item">Охват: <strong>${post.edit_stats.coverage}</strong></span>
                                <span class="stat-item">Виральный охват: <strong>${post.edit_stats.virality_reach}</strong></span>
                            </div>
                            <small class="text-muted">Обновлено: ${formatDate(post.edit_stats_captured_at)}</small>
                        </div>
                    ` : ''}

                    <div class="stats-section">
                        <h5>Текущая статистика:</h5>
                        <div class="stats-row">
                            <span class="stat-item">Просмотры: <strong>${post.current_stats.views}</strong></span>
                            <span class="stat-item">Лайки: <strong>${post.current_stats.likes}</strong></span>
                            <span class="stat-item">Репосты: <strong>${post.current_stats.reposts}</strong></span>
                            <span class="stat-item">Комментарии: <strong>${post.current_stats.comments}</strong></span>
                            <span class="stat-item">Охват: <strong>${post.current_stats.coverage}</strong></span>
                            <span class="stat-item">Виральный охват: <strong>${post.current_stats.virality_reach}</strong></span>
                        </div>
                        <small class="text-muted">Обновлено: ${formatDate(post.current_stats_updated_at)}</small>
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    return html;
}

function updatePagination(pagination) {
    console.log("DEBUG: updatePagination called with pagination:", pagination);
    var html = '';

    if (pagination.pages > 1) {
        // Предыдущая страница
        if (pagination.current_page > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${pagination.current_page - 1})">Предыдущая</a></li>`;
        }

        // Страницы
        var startPage = Math.max(1, pagination.current_page - 2);
        var endPage = Math.min(pagination.pages, pagination.current_page + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(1)">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (var i = startPage; i <= endPage; i++) {
            var activeClass = (i === pagination.current_page) ? 'active' : '';
            html += `<li class="page-item ${activeClass}"><a class="page-link" href="#" onclick="goToPage(${i})">${i}</a></li>`;
        }

        if (endPage < pagination.pages) {
            if (endPage < pagination.pages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${pagination.pages})">${pagination.pages}</a></li>`;
        }

        // Следующая страница
        if (pagination.current_page < pagination.pages) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="goToPage(${pagination.current_page + 1})">Следующая</a></li>`;
        }
    }

    $('#pagination').html(html);
}

function goToPage(page) {
    console.log("DEBUG: goToPage called with page:", page);
    loadStatsData({ page: page });
}

function formatDate(dateString) {
    // console.log("DEBUG: formatDate called with dateString:", dateString); // Может быть много
    if (!dateString) return '-';
    var options = { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' };
    try {
        return new Date(dateString).toLocaleString('ru-RU', options);
    } catch (e) {
        console.error("DEBUG: Error formatting date:", dateString, e);
        return dateString; // Возвращаем как есть в случае ошибки
    }
}

function getStatusText(status) {
    // console.log("DEBUG: getStatusText called with status:", status); // Может быть много
    var statusMap = {
        'published': 'Опубликован',
        'edited': 'Отредактирован',
        'published_with_stats': 'Опубликован (со статистикой)',
        'draft': 'Черновик',
        'scheduled': 'Запланирован',
        'pending': 'В ожидании',
        'completed': 'Выполнен',
        'failed': 'Ошибка',
        'cancelled': 'Отменен'
    };
    return statusMap[status] || status;
}

function getStatusBadgeClass(status) {
    // console.log("DEBUG: getStatusBadgeClass called with status:", status); // Может быть много
    var classMap = {
        'published': 'badge-success',
        'edited': 'badge-warning',
        'published_with_stats': 'badge-info',
        'draft': 'badge-secondary',
        'scheduled': 'badge-info',
        'pending': 'badge-primary',
        'completed': 'badge-success',
        'failed': 'badge-danger',
        'cancelled': 'badge-dark'
    };
    return classMap[status] || 'badge-secondary';
}

function truncateText(text, maxLength) {
    // console.log("DEBUG: truncateText called"); // Может быть много
    if (!text) return '';
    return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
}

function showScreenshot(src) {
    console.log("DEBUG: showScreenshot called with src:", src);
    $('#modal-screenshot-img').attr('src', src);
    $('#screenshotModal').modal('show'); // <-- Это может не работать без Bootstrap JS
}

// Обработчик для раскрытия/скрытия задач
$(document).on('click', '.toggle-task', function() {
    console.log("DEBUG: Toggle task button clicked");
    var taskId = $(this).data('task-id');
    console.log("DEBUG: Task ID:", taskId);
    var row = $(`.task-posts-row[data-task-id="${taskId}"]`);
    console.log("DEBUG: Row found:", row.length > 0);

    if (row.is(':visible')) {
        console.log("DEBUG: Hiding task posts for ID:", taskId);
        row.hide();
        $(this).text('Показать');
    } else {
        console.log("DEBUG: Loading task posts for ID:", taskId);
        // Загружаем данные постов для этой задачи
        loadTaskPosts(taskId, row, $(this));
    }
});

// --- ИСПРАВЛЕННАЯ ФУНКЦИЯ loadTaskPosts (с явным 'data' и логированием) ---
function loadTaskPosts(taskId, row, button) {
    console.log("DEBUG: loadTaskPosts called with taskId:", taskId, ", row exists:", row.length > 0, ", button exists:", button.length > 0);
    $.ajax({
        url: '/vk_poster/api/get_task_posts.php',
        method: 'GET',
        data: { 'task_id': taskId }, // <-- ИСПРАВЛЕНО: используем 'data'
        dataType: 'json',
        success: function(response) {
            console.log("DEBUG: loadTaskPosts AJAX success, raw response:", response);

            // Форматирование данных для HTML (ES5 совместимый способ)
            try {
                var formattedPosts = response.posts.map(function(post) {
                    var newPost = {};
                    // Копируем все свойства из оригинального объекта post
                    for (var key in post) {
                        if (post.hasOwnProperty(key)) {
                            newPost[key] = post[key];
                        }
                    }
                    // Добавляем/перезаписываем объекты статистики
                    newPost.current_stats = {
                        views: post.current_views,
                        likes: post.current_likes,
                        reposts: post.current_reposts,
                        comments: post.current_comments,
                        coverage: post.current_coverage,
                        virality_reach: post.current_virality_reach
                    };
                    newPost.edit_stats = {
                        views: post.edit_views,
                        likes: post.edit_likes,
                        reposts: post.edit_reposts,
                        comments: post.edit_comments,
                        coverage: post.edit_coverage,
                        virality_reach: post.edit_virality_reach
                    };
                    return newPost;
                });

                console.log("DEBUG: Formatted posts for task ID", taskId, ":", formattedPosts);
                var postsHtml = generatePostsHtml(formattedPosts);
                row.find('.task-posts-container').html(postsHtml);
                row.show(); // <-- Показываем строку-контейнер
                button.text('Скрыть');
            } catch (formatError) {
                console.error("DEBUG: Error formatting posts in loadTaskPosts:", formatError);
                row.find('.task-posts-container').html('<div class="alert alert-error">Ошибка при обработке данных постов</div>');
                row.show(); // Показываем даже при ошибке
            }
        },
        error: function(xhr, status, error) {
            console.error('DEBUG: loadTaskPosts AJAX error occurred:', error);
            console.error('DEBUG: XHR status:', xhr.status);
            console.error('DEBUG: XHR response text:', xhr.responseText);
            row.find('.task-posts-container').html('<div class="alert alert-error">Ошибка при загрузке данных постов</div>');
            row.show(); // Показываем даже при ошибке
        }
    });
}
// --- /ИСПРАВЛЕННАЯ ФУНКЦИЯ loadTaskPosts (с явным 'data' и логированием) ---