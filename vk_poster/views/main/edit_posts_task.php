<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/PostController.php';
require_once __DIR__ . '/../../models/Post.php';

Functions::requireLogin();

// --- Обработка AJAX запросов ---
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_POST && isset($_POST['csrf_token']) && Functions::validateCSRFToken($_POST['csrf_token'])) {
    $triggerType = $_POST['trigger_type'] ?? '';
    $triggerValue = (int)($_POST['trigger_value'] ?? 0);
    $newMessage = Functions::sanitizeInput($_POST['new_message'] ?? '');
    $attachmentAction = $_POST['attachment_action'] ?? 'keep';

    $errors = [];

    if (!in_array($triggerType, ['time', 'views'])) {
        $errors[] = 'Неверный тип триггера';
    }

    if ($triggerValue <= 0) {
        $errors[] = 'Значение триггера должно быть больше 0';
    }

    // Источник данных: только ссылки
    $selectedPostLinks = trim($_POST['post_links'] ?? '');

    if (empty($selectedPostLinks)) {
        $errors[] = 'Введите ссылки на посты';
    }

    if (empty($errors)) {
        require_once __DIR__ . '/../../models/PostEditingTask.php';
        $taskModel = new PostEditingTask();

        $commonNewAttachments = ''; // Будет хранить вложения, общие для всех задач этой отправки формы

        // --- Обработка вложений ---
		if ($attachmentAction === 'add_new') {
			error_log("DEBUG_EDIT_POSTS_TASK: Processing 'add_new' attachments for form submission.");

			// --- НОВАЯ ОБРАБОТКА ВСЕХ ФАЙЛОВ ЧЕРЕЗ PostController ---
			// Подготовка данных из $_FILES в нужном формате для PostController
			$reformattedFilesData = [];

			if (isset($_FILES['attachments']['tmp_name']['photos']) && is_array($_FILES['attachments']['tmp_name']['photos'])) {
				foreach ($_FILES['attachments']['tmp_name']['photos'] as $index => $tmpPath) {
					if ($tmpPath && $_FILES['attachments']['error']['photos'][$index] === UPLOAD_ERR_OK) {
						if (!isset($reformattedFilesData['photos'])) {
							$reformattedFilesData['photos'] = [];
						}
						$reformattedFilesData['photos'][] = $tmpPath;
					} else {
						error_log("DEBUG_EDIT_POSTS_TASK: Error or no file in _FILES for photo index $index: tmp_path='$tmpPath', error_code=" . $_FILES['attachments']['error']['photos'][$index]);
					}
				}
			}

			// Если могут быть видео, добавьте аналогичную логику для videos

			if (!empty($reformattedFilesData)) {
				$postController = new PostController();
				error_log("DEBUG_EDIT_POSTS_TASK: Sending reformatted files to PostController: " . print_r($reformattedFilesData, true));
				$tempAttachments = $postController->uploadTempAttachments($reformattedFilesData);
				$commonNewAttachments = json_encode($tempAttachments);
				error_log("DEBUG_EDIT_POSTS_TASK: Common attachments from all inputs (files/paste/drag): " . print_r($tempAttachments, true));
			} else {
				// Если нет файлов вообще, $commonNewAttachments остаётся пустой строкой
				error_log("DEBUG_EDIT_POSTS_TASK: No files found in _FILES after JS processing.");
				$commonNewAttachments = '';
			}
			// --- /НОВАЯ ОБРАБОТКА ВСЕХ ФАЙЛОВ ЧЕРЕЗ PostController ---
		} else {
			error_log("DEBUG_EDIT_POSTS_TASK: Attachment action is not 'add_new'. Skipping attachment processing.");
			$commonNewAttachments = '';
		}
		// --- /Обработка вложений ---

		if (!empty($errors)) {
			error_log("DEBUG_EDIT_POSTS_TASK: Errors occurred during attachment processing. Stopping task creation.");
			// Если произошли ошибки при обработке вложений, не продолжаем
		} else {
			// Парсим ссылки на посты
			$links = array_filter(array_map('trim', explode("\n", $selectedPostLinks)));
			$validPosts = [];
			foreach ($links as $link) {
				$postInfo = extractVkPostInfo($link);
				if ($postInfo) {
					$validPosts[] = $postInfo;
				} else {
					$errors[] = "Неверный формат ссылки: $link";
				}
			}

			if (empty($validPosts)) {
				$errors[] = 'Не найдено корректных ID постов';
			}

			if (empty($errors)) {
				$successCount = 0;
				error_log("DEBUG_EDIT_POSTS_TASK: Creating tasks for " . count($validPosts) . " valid posts.");
				foreach ($validPosts as $postInfo) {
					// --- КОПИРОВАНИЕ ВЛОЖЕНИЙ ДЛЯ КАЖДОЙ ЗАДАЧИ ---
					$taskSpecificAttachments = $commonNewAttachments;
					if ($attachmentAction === 'add_new' && !empty($commonNewAttachments)) {
						error_log("DEBUG_EDIT_POSTS_TASK: Preparing specific attachments for task targeting post_id: {$postInfo['post_id']}, group_id: {$postInfo['group_id']}");
						// Декодируем общие вложения
						$decodedCommonAttachments = json_decode($commonNewAttachments, true);
						if (is_array($decodedCommonAttachments)) {
							$taskSpecificPaths = [];
							foreach ($decodedCommonAttachments as $attachmentInfo) {
								if (isset($attachmentInfo['path']) && file_exists($attachmentInfo['path'])) {
									$originalPath = $attachmentInfo['path'];
									$originalDir = dirname($originalPath);
									$originalFilename = basename($originalPath);
									$originalNameWithoutExt = pathinfo($originalPath, PATHINFO_FILENAME);
									$originalExt = pathinfo($originalPath, PATHINFO_EXTENSION);

									// Генерируем новое уникальное имя в той же директории
									$newUniqueFileName = uniqid($originalNameWithoutExt . '_', true) . ($originalExt ? '.' . $originalExt : '');
									$newPath = $originalDir . '/' . $newUniqueFileName;

									// Копируем файл
									if (copy($originalPath, $newPath)) {
										 error_log("DEBUG_EDIT_POSTS_TASK: Copied attachment for task: {$postInfo['post_id']} from {$originalPath} to {$newPath}");
										 // --- ОБНОВЛЕНИЕ КОПИРОВАНИЯ ---
										 // Используем ?? (null coalescing operator) для безопасного получения original_name
										 $taskSpecificPaths[] = [
											 'path' => $newPath,
											 'original_name' => $attachmentInfo['original_name'] ?? '', // Если original_name нет, используем пустую строку
											 'type' => $attachmentInfo['type'] ?? 'photo' // Также безопасно получаем type, на всякий случай
										 ];
										 // --- /ОБНОВЛЕНИЕ КОПИРОВАНИЯ ---
									} else {
										 error_log("DEBUG_EDIT_POSTS_TASK: Failed to copy attachment for task: {$postInfo['post_id']}, file: {$originalPath}");
										 // Можно добавить ошибку, но лучше продолжить с другими вложениями
										 // $errors[] = 'Ошибка копирования временного файла.';
										 // Добавляем оригинальный путь, чтобы cron мог обработать другие задачи, если этот файл недоступен
										 $taskSpecificPaths[] = $attachmentInfo;
									}
								} else {
									// Если файл не существует, добавляем информацию как есть (хотя это странно)
									error_log("DEBUG_EDIT_POSTS_TASK: Attachment file does not exist for task: {$postInfo['post_id']}, path: {$attachmentInfo['path']}. Adding original entry to specific attachments.");
									$taskSpecificPaths[] = $attachmentInfo;
								}
							}
							$taskSpecificAttachments = json_encode($taskSpecificPaths);
							error_log("DEBUG_EDIT_POSTS_TASK: Specific attachments for task {$postInfo['post_id']}: " . print_r($taskSpecificPaths, true));
						} else {
							error_log("DEBUG_EDIT_POSTS_TASK: Could not decode common attachments for task {$postInfo['post_id']}. Keeping original common attachments.");
						}
					} else {
						error_log("DEBUG_EDIT_POSTS_TASK: No need to copy attachments for task {$postInfo['post_id']} (action != add_new or no common attachments).");
					}
					// --- /КОПИРОВАНИЕ ВЛОЖЕНИЙ ДЛЯ КАЖДОЙ ЗАДАЧИ ---

					$taskData = [
						'user_id' => Functions::getCurrentUserId(),
						'post_vk_id' => $postInfo['post_id'],
						'group_id' => $postInfo['group_id'], // Оригинальный ID группы (отрицательный)
						'trigger_type' => $triggerType,
						'trigger_value' => $triggerValue,
						'new_message' => $newMessage,
						'new_attachments' => $taskSpecificAttachments, // Используем специфичные вложения для задачи
						'attachment_action' => $attachmentAction
					];

					if ($taskModel->create($taskData)) {
						error_log("DEBUG_EDIT_POSTS_TASK: Created task for post_vk_id: {$postInfo['post_id']}, group_id: {$postInfo['group_id']}, with specific attachments: {$taskSpecificAttachments}");
						$successCount++;
					} else {
						error_log("DEBUG_EDIT_POSTS_TASK: Failed to create task for post_vk_id: {$postInfo['post_id']}");
					}
				}

				if ($successCount > 0) {
					if ($isAjax) {
						echo json_encode(['success' => true, 'message' => "Создано $successCount задач(и) на редактирование постов"]);
						exit;
					} else {
						Session::setFlash('success', "Создано $successCount задач(и) на редактирование постов");
						header('Location: /vk_poster/views/main/edit_posts_task.php');
						exit();
					}
				} else {
					$errors[] = 'Ошибка при создании задач';
				}
			}
		}
    }

    if (!empty($errors)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => implode("\n", $errors)]);
            exit;
        } else {
            $taskErrors = $errors;
        }
    }
}

// Функция для извлечения ID группы и ID поста из ссылки
function extractVkPostInfo($link) {
    // ... (код функции остается без изменений) ...
    // Паттерн для формата public229270625?w=wall-229270625_29
    $pattern = '/public(\d+)\?w=wall-(\d+)_(\d+)/';
    if (preg_match($pattern, $link, $matches)) {
        $groupId = -$matches[1];
        $postId = $matches[3];
        return ['group_id' => $groupId, 'post_id' => $postId];
    }

    // Общий паттерн
    $pattern = '/(?:https?:\/\/)?(?:www\.)?vk\.com\/(?:wall|club|public|id)?(\d+)(?:\?w=wall(-?\d+)_(\d+)|\?w=wall(\d+)_(\d+)|\/wall(-?\d+)_(\d+)|\/wall(\d+)_(\d+)|[-_](\d+)_(\d+))|wall(-?\d+)_(\d+)/';
    if (preg_match($pattern, $link, $matches)) {
        // Ищем ID группы и ID поста в найденных совпадениях
        $groupId = null;
        $postId = null;

        // Индексы, где могут быть ID группы и поста в массиве $matches
        // В зависимости от формата ссылки, они могут быть на разных позициях
        // Проверяем позиции 1, 4, 7, 9, 11 (для group_id)
        // Проверяем позиции 3, 5, 8, 10, 12 (для post_id)
        $groupIndices = [1, 4, 7, 9, 11];
        $postIndices = [3, 5, 8, 10, 12];

        foreach ($groupIndices as $idx) {
            if (isset($matches[$idx]) && is_numeric($matches[$idx])) {
                $groupId = $matches[$idx];
                if ($groupId > 0) $groupId = -$groupId; // Если положительный, делаем отрицательным
                break;
            }
        }

        foreach ($postIndices as $idx) {
            if (isset($matches[$idx]) && is_numeric($matches[$idx])) {
                $postId = $matches[$idx];
                break;
            }
        }

        if ($groupId !== null && $postId !== null) {
            return ['group_id' => $groupId, 'post_id' => $postId];
        }
    }

    // Проверяем формат wall-123456_789
    $pattern = '/wall(-?\d+)_(\d+)/';
    if (preg_match($pattern, $link, $matches)) {
        $groupId = $matches[1];
        $postId = $matches[2];
        if ($groupId > 0) $groupId = -$groupId; // Если положительный, делаем отрицательным
        return ['group_id' => $groupId, 'post_id' => $postId];
    }

    return false;
}

$title = 'Массовое редактирование постов';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';
?>

<div class="dashboard">
    <h2>Массовое редактирование постов</h2>

    <?php if ($message = Session::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (isset($taskErrors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($taskErrors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Форма только для ввода ссылок -->
    <div class="card">
        <div class="card-body">
            <h3>Через ссылки на посты</h3>
            <form method="POST" action="" enctype="multipart/form-data" id="postLinksForm">
                <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="post_links">Ссылки на посты (по одной в строке):</label>
                    <textarea id="post_links" name="post_links" class="form-control" rows="5" placeholder="https://vk.com/wall-123456_789  
https://vk.com/club123456?w=wall-123456_789  
https://vk.com/public229270625?w=wall-229270625_29  "></textarea>
                    <small>Введите ссылки на посты, которые нужно редактировать, по одной в строке</small>
                </div>

                <div class="form-group">
                    <label for="trigger_type">Триггер:</label>
                    <select id="trigger_type" name="trigger_type" class="form-control" required>
                        <option value="time">По времени (часы)</option>
                        <option value="views">По просмотрам</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="trigger_value">Значение триггера:</label>
                    <input type="number" id="trigger_value" name="trigger_value" class="form-control" min="1" required>
                    <small id="trigger_hint">Введите количество часов или просмотров</small>
                </div>

                <div class="form-group">
                    <label for="new_message">Новый текст поста:</label>
                    <div class="post-editor">
                        <div id="new_message_editor" class="editor-content" contenteditable="true" 
                             placeholder="Введите новый текст поста...">
                        </div>
                        <input type="hidden" name="new_message" id="new_message_hidden" value="">
                    </div>
                    <small>Оставьте пустым, чтобы не изменять текст</small>
                </div>

                <div class="form-group">
                    <label>Действие с вложениями:</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="attachment_action" value="keep" checked> Оставить вложения без изменений
                        </label>
                    </div>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="attachment_action" value="remove"> Удалить все вложения
                        </label>
                    </div>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="attachment_action" value="add_new"> Добавить новые вложения (заменить старые)
                        </label>
                    </div>
                </div>

                <div class="form-group" id="attachments_container_links" class="attachments-container" style="display:none;">
                    <label>Новые вложения:</label>
                    <div class="file-upload-controls">
                        <div class="file-upload-container">
                            <div class="file-upload-area" id="file_upload_area_links">
                                <label class="btn btn-primary">
                                    <input type="file" name="attachments[photos][]" id="file_input_links" class="file-input-hidden" multiple accept="image/*,video/*">
                                    Выбрать вложения
                                </label>
                                <p class="file-upload-text">Или перетащите файлы сюда</p>
                            </div>
                            <div id="attachments_preview_links" class="attachments-container">
                                <!-- Предварительные просмотры файлов будут добавляться сюда -->
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Скрытое поле для хранения информации о вставленных изображениях -->
                <input type="hidden" name="pasted_images_json" id="pasted_images_json" value="[]">

                <button type="submit" class="btn btn-primary" id="submit_btn_links">Создать задачу</button>
                <a href="/vk_poster/" class="btn btn-secondary">Назад</a>
                
                <div id="progress_container_links" class="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress_fill_links"></div>
                    </div>
                    <div id="progress_text_links" class="progress-text">Обработка...</div>
                </div>
            </form>
        </div>
    </div>

</div>

<style>
.post-editor {
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 10px;
    min-height: 100px;
    background-color: #fff;
    outline: none;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}
.editor-content[contenteditable="true"]:empty:before {
    content: attr(placeholder);
    color: #999;
    pointer-events: none;
}
.file-upload-controls {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.file-upload-container {
    border: 1px dashed #ccc;
    border-radius: 4px;
    padding: 10px;
    background-color: #f9f9f9;
}
.file-upload-area {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px;
    min-height: 80px;
}
.file-upload-area.drag-over {
    background-color: #f0f8ff;
}
.file-input-hidden {
    display: none;
}
.file-upload-text {
    margin: 0;
    font-size: 0.9em;
    color: #666;
    text-align: center;
}
.attachments-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    min-height: 50px;
    margin-top: 10px;
    padding: 10px;
    background-color: #fafafa;
    border-radius: 4px;
}
.attachment-item {
    position: relative;
    display: inline-block;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 5px;
    background-color: white;
}
.attachment-item img, .attachment-item video {
    max-width: 100px;
    max-height: 100px;
    display: block;
}
.remove-attachment {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.2s;
}
.attachment-item:hover .remove-attachment {
    opacity: 1;
}
.attachment-name {
    font-size: 0.8em;
    text-align: center;
    margin-top: 5px;
    word-break: break-all;
}
.progress-container {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.progress-bar {
    width: 100%;
    height: 20px;
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    background-color: #007bff;
    width: 0%;
    transition: width 0.3s ease;
}
.progress-text {
    font-size: 0.9em;
    color: #666;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- Общие функции для вложений ---
    let attachmentCounter = 0;
    // --- ХРАНИЛИЩЕ ФАЙЛОВ ---
    let globalFileList = new DataTransfer(); // Единый список файлов для отправки
    // --- /ХРАНИЛИЩЕ ФАЙЛОВ ---

    function addAttachment(containerId, src, type, name = '', isPasted = false) {
        const attachmentsContainer = document.getElementById(containerId);
        const attachmentId = 'attachment_' + attachmentCounter++;
        const attachmentItem = document.createElement('div');
        attachmentItem.className = 'attachment-item';
        attachmentItem.dataset.id = attachmentId;
        
        if (type === 'image') {
            const img = document.createElement('img');
            img.src = src;
            img.alt = name || 'Изображение';
            attachmentItem.appendChild(img);
        } else if (type === 'video') {
            const video = document.createElement('video');
            video.src = src;
            video.controls = true;
            video.style.maxWidth = '100px';
            video.style.maxHeight = '100px';
            attachmentItem.appendChild(video);
        }
        
        if (name) {
            const fileNameSpan = document.createElement('div');
            fileNameSpan.className = 'attachment-name';
            fileNameSpan.textContent = name;
            attachmentItem.appendChild(fileNameSpan);
        }
        
        const removeBtn = document.createElement('button');
        removeBtn.className = 'remove-attachment';
        removeBtn.innerHTML = '&times;';
        removeBtn.title = 'Удалить вложение';
        removeBtn.onclick = () => {
            attachmentsContainer.removeChild(attachmentItem);
            if (isPasted) {
                // Если удаляем вставленное изображение, удаляем его из JSON
                removePastedImageFromJson(src);
            }
            // --- УДАЛЕНИЕ ИЗ ГЛОБАЛЬНОГО СПИСКА ---
            // Нужно как-то идентифицировать файл для удаления из globalFileList
            // Например, можно хранить File объект в dataset элемента превью
            const fileToRemove = attachmentItem.dataset.fileObject;
            if (fileToRemove) {
                const fileListArray = Array.from(globalFileList.files);
                const indexToRemove = fileListArray.findIndex(f => f.name === fileToRemove.name && f.size === fileToRemove.size && f.lastModified === fileToRemove.lastModified);
                if (indexToRemove > -1) {
                    fileListArray.splice(indexToRemove, 1);
                    globalFileList = new DataTransfer();
                    fileListArray.forEach(f => globalFileList.items.add(f));
                }
                attachmentItem.dataset.fileObject = undefined; // Очищаем
            }
            // --- /УДАЛЕНИЕ ИЗ ГЛОБАЛЬНОГО СПИСКА ---
        };
        attachmentItem.appendChild(removeBtn);
        
        // --- СОХРАНЕНИЕ FILE OBJECT ---
        // Нужно передать сюда File object, чтобы можно было его удалить позже
        // Это сложно сделать напрямую, так как src - это dataURL или blob URL
        // Лучше передавать File object сюда сверху
        // --- /СОХРАНЕНИЕ FILE OBJECT ---
        
        attachmentsContainer.appendChild(attachmentItem);
    }

    // Функция для добавления вставленного изображения в JSON
    function addPastedImageToJson(dataURL, fileName) {
        const jsonInput = document.getElementById('pasted_images_json');
        let jsonData = JSON.parse(jsonInput.value);
        if (!Array.isArray(jsonData)) jsonData = [];
        jsonData.push({ dataURL: dataURL, fileName: fileName });
        jsonInput.value = JSON.stringify(jsonData);
    }

    // Функция для удаления вставленного изображения из JSON по его dataURL
    function removePastedImageFromJson(dataURL) {
        const jsonInput = document.getElementById('pasted_images_json');
        let jsonData = JSON.parse(jsonInput.value);
        if (!Array.isArray(jsonData)) jsonData = [];
        jsonData = jsonData.filter(img => img.dataURL !== dataURL);
        jsonInput.value = JSON.stringify(jsonData);
    }

    // --- ОБНОВЛЁННАЯ ФУНКЦИЯ handleFiles ---
    function handleFiles(containerId, files, isPasted = false) {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            // --- ДОБАВЛЕНИЕ В ГЛОБАЛЬНЫЙ СПИСОК ---
            globalFileList.items.add(file);
            // --- /ДОБАВЛЕНИЕ В ГЛОБАЛЬНЫЙ СПИСОК ---
            
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    // --- ПЕРЕДАЧА FILE OBJECT ---
                    addAttachment(containerId, event.target.result, 'image', file.name, isPasted);
                    // Для удаления из globalFileList нужно хранить file object где-то
                    const previewItem = document.querySelector(`#${containerId} .attachment-item:last-child`);
                    if (previewItem) {
                        previewItem.dataset.fileObject = JSON.stringify({name: file.name, size: file.size, lastModified: file.lastModified}); // Простое представление
                    }
                    // --- /ПЕРЕДАЧА FILE OBJECT ---
                };
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                const reader = new FileReader();
                reader.onload = (event) => {
                    // --- ПЕРЕДАЧА FILE OBJECT ---
                    addAttachment(containerId, event.target.result, 'video', file.name, isPasted);
                    const previewItem = document.querySelector(`#${containerId} .attachment-item:last-child`);
                    if (previewItem) {
                        previewItem.dataset.fileObject = JSON.stringify({name: file.name, size: file.size, lastModified: file.lastModified});
                    }
                    // --- /ПЕРЕДАЧА FILE OBJECT ---
                };
                reader.readAsDataURL(file);
            }
        }
    }
    // --- /ОБНОВЛЁННАЯ ФУНКЦИЯ handleFiles ---

    // --- Настройка формы по ссылкам ---
    setupForm(
        'postLinksForm', 'submit_btn_links', 'post_links', 'new_message_editor', 'new_message_hidden',
        'file_input_links', 'file_upload_area_links', 'attachments_preview_links', 'attachments_container_links',
        'progress_container_links', 'progress_fill_links', 'progress_text_links'
    );

    // --- Общая функция для настройки формы ---
    function setupForm(formId, submitBtnId, postLinksId, editorId, hiddenInputId, fileInputId, fileUploadAreaId, attachmentsPreviewId, attachmentsContainerId, progressContainerId, progressFillId, progressTextId) {
        const form = document.getElementById(formId);
        const submitBtn = document.getElementById(submitBtnId);
        const editor = document.getElementById(editorId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const fileInput = document.getElementById(fileInputId);
        const fileUploadArea = document.getElementById(fileUploadAreaId);
        const attachmentsPreview = document.getElementById(attachmentsPreviewId);
        const attachmentsContainer = document.getElementById(attachmentsContainerId);
        const progressContainer = document.getElementById(progressContainerId);
        const progressFill = document.getElementById(progressFillId);
        const progressText = document.getElementById(progressTextId);

        // Обработчик вставки изображений в редактор
        if (editor) {
            editor.addEventListener('paste', function(e) {
                const items = (e.clipboardData || e.originalEvent.clipboardData).items;
                let hasImage = false;
                for (let i = 0; i < items.length; i++) {
                    if (items[i].type.indexOf('image') !== -1) {
                        const blob = items[i].getAsFile();
                        if (blob) {
                            // --- ОБРАБОТКА ВСТАВКИ КАК ФАЙЛА ---
                            const file = new File([blob], 'Вставленное изображение ' + (new Date()).getTime(), { type: blob.type });
                            handleFiles(attachmentsPreviewId, [file], true); // isPasted = true
                            // НЕ добавляем в JSON, так как файл теперь в списке
                            // addPastedImageToJson(event.target.result, uniqueFileName); // Удалено
                            // --- /ОБРАБОТКА ВСТАВКИ КАК ФАЙЛА ---
                            hasImage = true;
                        }
                    }
                }
                if (hasImage) {
                    e.preventDefault();
                }
            });
        }

        // Обработчики drag'n'drop для области загрузки
        ['dragover', 'dragenter'].forEach(evt => {
            fileUploadArea.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.add('drag-over');
            });
        });

        ['dragleave', 'dragend'].forEach(evt => {
            fileUploadArea.addEventListener(evt, (e) => {
                e.preventDefault();
                e.stopPropagation();
                fileUploadArea.classList.remove('drag-over');
            });
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            fileUploadArea.classList.remove('drag-over');
            // --- ОБРАБОТКА DROP КАК ФАЙЛОВ ---
            handleFiles(attachmentsPreviewId, e.dataTransfer.files, false); // isPasted = false
            // --- /ОБРАБОТКА DROP КАК ФАЙЛОВ ---
        });

        // Обработчик изменения файла через input
        fileInput.addEventListener('change', function(e) {
            // --- ОБРАБОТКА INPUT CHANGE КАК ФАЙЛОВ ---
            handleFiles(attachmentsPreviewId, e.target.files, false); // isPasted = false
            // --- /ОБРАБОТКА INPUT CHANGE КАК ФАЙЛОВ ---
        });

        // Обработчик изменения радиокнопок действий с вложениями
        const attachmentActionRadios = form.querySelectorAll('input[name="attachment_action"]');
        attachmentActionRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'add_new') {
                    attachmentsContainer.style.display = 'block';
                } else {
                    attachmentsContainer.style.display = 'none';
                }
            });
        });

        // Инициализация видимости контейнера вложений
        const addNewRadio = form.querySelector('input[name="attachment_action"][value="add_new"]');
        if (addNewRadio.checked) {
            attachmentsContainer.style.display = 'block';
        }

        // --- Обработчик отправки формы ---
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const linksTextarea = document.getElementById(postLinksId);
            if (!linksTextarea.value.trim()) {
                 alert('Введите ссылки на посты');
                 return;
            }

            // Обновление скрытого поля с текстом
            if (editor && hiddenInput) {
                hiddenInput.value = editor.innerText;
            }

            // --- ФОРМИРОВАНИЕ FORMDATA ---
            const formData = new FormData(form);
            // Заменяем файлы из fileInput на глобальный список
            const photosKey = 'attachments[photos][]';
            formData.delete(photosKey); // Удаляем старые, если были
            Array.from(globalFileList.files).forEach(file => {
                formData.append(photosKey, file, file.name); // Добавляем из globalFileList
            });
            // Если нужны видео, добавьте аналогично для videosKey
            // --- /ФОРМИРОВАНИЕ FORMDATA ---

            // Показать прогресс, отключить кнопку
            progressContainer.style.display = 'flex';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Создание задач...';

            // Анимация прогресса
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 2;
                if (progress >= 90) {
                    progress = 90;
                    clearInterval(progressInterval);
                }
                progressFill.style.width = progress + '%';
                progressText.textContent = 'Обработка... ' + progress + '%';
            }, 100);

            // Отправка через Fetch API
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                progressFill.style.width = '100%';
                progressText.textContent = data.success ? 'Готово!' : 'Ошибка';
                
                if (data.success) {
                    // --- ОЧИСТКА ГЛОБАЛЬНОГО СПИСКА ПОСЛЕ УСПЕШНОЙ ОТПРАВКИ ---
                    globalFileList = new DataTransfer();
                    // Также очистите превью, если нужно
                    document.getElementById('attachments_preview_links').innerHTML = '';
                    // --- /ОЧИСТКА ГЛОБАЛЬНОГО СПИСКА ---
                    setTimeout(() => {
                        window.location.href = '/vk_poster/views/main/edit_posts_task.php';
                    }, 500);
                } else {
                    alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Создать задачу';
                    progressContainer.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                clearInterval(progressInterval);
                progressFill.style.width = '0%';
                progressText.textContent = 'Ошибка сети';
                alert('Ошибка сети');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Создать задачу';
                progressContainer.style.display = 'none';
            });
        });
    }

    // --- Обработчики для подсказок триггера ---
    const triggerType = document.getElementById('trigger_type');
    const triggerHint = document.getElementById('trigger_hint');

    if (triggerType && triggerHint) {
        triggerType.addEventListener('change', function() {
            if (this.value === 'time') {
                triggerHint.textContent = 'Введите количество часов после публикации';
            } else {
                triggerHint.textContent = 'Введите количество просмотров';
            }
        });
    }

    // Инициализация подсказки
    if (triggerType) triggerType.dispatchEvent(new Event('change'));

});
</script>

<?php include '../layouts/footer.php'; ?>