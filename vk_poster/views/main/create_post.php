<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../models/UserPostGroup.php';
require_once __DIR__ . '/../../includes/vk_api.php';

Functions::requireLogin();

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_POST && isset($_POST['csrf_token']) && Functions::validateCSRFToken($_POST['csrf_token'])) {
    $message = Functions::sanitizeInput($_POST['message']);
    $scheduleTime = $_POST['schedule_time'] ?? '';
    $timezone = $_POST['timezone'] ?? 'UTC';
    
    $selectedGroups = $_POST['group_ids'] ?? [];
    $selectedPostGroups = $_POST['post_group_ids'] ?? [];
    
    $allSelectedGroups = $selectedGroups;
    
    if (!empty($selectedPostGroups)) {
        $userPostGroup = new UserPostGroup();
        foreach ($selectedPostGroups as $postGroupId) {
            $members = $userPostGroup->getMembers($postGroupId);
            if (empty($members)) {
                $postGroupInfo = $userPostGroup->getById($postGroupId);
                $errors[] = 'Пользовательская группа "' . htmlspecialchars($postGroupInfo['name'], ENT_QUOTES) . '" не содержит сообществ';
                break;
            }
            foreach ($members as $member) {
                if (!in_array($member['group_id'], $allSelectedGroups)) {
                    $allSelectedGroups[] = $member['group_id'];
                }
            }
        }
    }
    
    if (empty($allSelectedGroups)) {
        $errors[] = 'Выберите хотя бы одно сообщество';
    }
    
    if (empty($errors)) {
        // Обработка файлов — теперь через $_FILES['photos']
        $attachments = [];
        
        if (!empty($_FILES['photos']['tmp_name'])) {
            $photos = is_array($_FILES['photos']['tmp_name']) 
                ? $_FILES['photos']['tmp_name'] 
                : [$_FILES['photos']['tmp_name']];
            
            foreach ($photos as $index => $tmpName) {
                if ($tmpName && $_FILES['photos']['error'][$index] === UPLOAD_ERR_OK) {
                    if (!isset($attachments['photos'])) {
                        $attachments['photos'] = [];
                    }
                    $attachments['photos'][] = $tmpName;
                }
            }
        }

        $apiPostData = [
            'group_ids' => $allSelectedGroups,
            'message' => $message,
            'schedule_time' => $scheduleTime,
            'timezone' => $timezone,
            'csrf_token' => Functions::generateCSRFToken()
        ];
        
        $apiFilesData = $attachments;

        define('DIRECT_API_CALL', true);
        global $apiPostData, $apiFilesData;

        try {
            ob_start();
            require_once __DIR__ . '/../../api/post.php';
            $output = ob_get_clean();

            if (isset($_SESSION['flash_success'])) {
                if ($isAjax) {
                    echo json_encode(['success' => true]);
                    exit;
                } else {
                    header('Location: /vk_poster/views/main/create_post.php');
                    exit;
                }
            } else {
                $errors = ['Ошибка при публикации поста'];
            }
        } catch (Exception $e) {
            $errors = [$e->getMessage()];
        }
    }

    if (!empty($errors)) {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => implode("\n", $errors)]);
            exit;
        }
    }
}

$title = 'Создать пост';
$additionalScripts = ['/vk_poster/assets/js/vk_poster.js'];

include '../layouts/header.php';

$vk = new VKAPI(Functions::getCurrentUser()['vk_token']);
$userGroupsInfo = $vk->getUserGroups('admin,moderator,editor');
$userGroups = [];

if (isset($userGroupsInfo['response']['items']) && is_array($userGroupsInfo['response']['items']) && !empty($userGroupsInfo['response']['items'])) {
    $groupIdsStr = implode(',', $userGroupsInfo['response']['items']);
    $groupsDetails = $vk->getGroupsByIds($groupIdsStr);
    
    if (isset($groupsDetails) && is_array($groupsDetails)) {
        foreach ($groupsDetails as $group) {
            $userGroups[] = [
                'group_id' => $group['id'],
                'group_name' => $group['name']
            ];
        }
    }
}

$userPostGroup = new UserPostGroup();
$postGroups = $userPostGroup->getByUserId(Functions::getCurrentUserId());
$hasUserCreatedGroups = !empty($postGroups);
?>

<div class="dashboard">
    <h2>Создать пост</h2>
    
    <?php if ($message = Session::getFlash('success')): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($errors)): ?>
        <div class="alert alert-error">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error, ENT_QUOTES); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="postForm">
                <input type="hidden" name="csrf_token" value="<?php echo Functions::generateCSRFToken(); ?>">
                
                <?php if ($hasUserCreatedGroups): ?>
                    <div class="form-group">
                        <label for="post_group_ids">Выберите пользовательские группы:</label>
                        <?php foreach ($postGroups as $postGroup): ?>
                            <?php
                            $members = $userPostGroup->getMembers($postGroup['id']);
                            $hasMembers = !empty($members);
                            ?>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="post_group_ids[]" value="<?php echo $postGroup['id']; ?>" <?php echo !$hasMembers ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($postGroup['name']); ?>
                                    <?php if (!$hasMembers): ?>
                                        <span class="no-members">(нет сообществ)</span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="group_ids">Выберите сообщества:</label>
                        <?php if (empty($userGroups)): ?>
                            <p>У вас нет доступных сообществ. <a href="/vk_poster/views/main/select_groups.php">Настройте группы</a></p>
                        <?php else: ?>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" id="select-all-groups"> Выбрать все
                                </label>
                            </div>
                            <?php foreach ($userGroups as $group): ?>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="group_ids[]" value="<?php echo $group['group_id']; ?>">
                                        <?php echo htmlspecialchars($group['group_name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="message">Текст поста:</label>
                    <div class="post-editor">
                        <div id="message-editor" class="editor-content" contenteditable="true" 
                             placeholder="Введите текст поста...">
                        </div>
                        <input type="hidden" name="message" id="message-hidden" value="">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Прикрепить файлы:</label>
                    <div class="file-upload-controls">
                        <div class="file-upload-container">
                            <div class="file-upload-area" id="file-upload-area">
                                <label class="btn btn-primary">
                                    <input type="file" name="photos[]" id="file-input" class="file-input-hidden" multiple accept="image/*,video/*">
                                    Выбрать вложения
                                </label>
                                <p class="file-upload-text">Или перетащите файлы сюда</p>
                            </div>
                            <div id="attachments-container" class="attachments-container">
                                <!-- Вложения будут добавляться сюда -->
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="schedule_time">Время публикации (оставьте пустым для немедленной публикации):</label>
                    <input type="datetime-local" id="schedule_time" name="schedule_time" class="form-control">
                    <input type="hidden" name="timezone" id="timezone" value="UTC">
                </div>
                
                <button type="submit" class="btn btn-primary" id="submit-btn">Опубликовать</button>
                <a href="/vk_poster/" class="btn btn-secondary">Назад</a>
                
                <div id="progress-container" class="progress-container" style="display: none;">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill"></div>
                    </div>
                    <div id="progress-text" class="progress-text">Обработка...</div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
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
.no-members {
    color: #666;
    font-size: 0.9em;
    font-style: italic;
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
    const selectAllCheckbox = document.getElementById('select-all-groups');
    const groupCheckboxes = document.querySelectorAll('input[name="group_ids[]"]');
    
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checked = this.checked;
            groupCheckboxes.forEach(checkbox => {
                checkbox.checked = checked;
            });
        });
    }
    
    const timezoneInput = document.getElementById('timezone');
    if (timezoneInput) {
        const now = new Date();
        const offsetMinutes = now.getTimezoneOffset();
        const offsetHours = Math.floor(Math.abs(offsetMinutes) / 60);
        const offsetSign = offsetMinutes <= 0 ? '+' : '-';
        timezoneInput.value = 'UTC' + offsetSign + String(offsetHours).padStart(2, '0');
    }
    
    const editor = document.getElementById('message-editor');
    const attachmentsContainer = document.getElementById('attachments-container');
    const fileUploadArea = document.getElementById('file-upload-area');
    const fileInput = document.getElementById('file-input');
    let attachmentCounter = 0;
    
    function addAttachment(src, type, name = '') {
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
        };
        attachmentItem.appendChild(removeBtn);
        
        attachmentsContainer.appendChild(attachmentItem);
    }
    
    function handleFiles(files) {
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (event) => addAttachment(event.target.result, 'image', file.name);
                reader.readAsDataURL(file);
            } else if (file.type.startsWith('video/')) {
                const reader = new FileReader();
                reader.onload = (event) => addAttachment(event.target.result, 'video', file.name);
                reader.readAsDataURL(file);
            }
        }
    }
    
    editor.addEventListener('paste', function(e) {
        const items = (e.clipboardData || e.originalEvent.clipboardData).items;
        let hasImage = false;
        for (let i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                const blob = items[i].getAsFile();
                const reader = new FileReader();
                reader.onload = (event) => {
                    addAttachment(event.target.result, 'image', 'Вставленное изображение');
                };
                reader.readAsDataURL(blob);
                hasImage = true;
            }
        }
        if (hasImage) {
            e.preventDefault();
        }
    });
    
    editor.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        handleFiles(e.dataTransfer.files);
    });
    
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });
    
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
        handleFiles(e.dataTransfer.files);
    });
    
    const postForm = document.getElementById('postForm');
    const submitBtn = document.getElementById('submit-btn');
    const progressContainer = document.getElementById('progress-container');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    
    postForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const hasUserGroups = <?php echo $hasUserCreatedGroups ? 'true' : 'false'; ?>;
        let selectedItems = hasUserGroups 
            ? document.querySelectorAll('input[name="post_group_ids[]"]:checked')
            : document.querySelectorAll('input[name="group_ids[]"]:checked');
        
        if (selectedItems.length === 0) {
            alert('Выберите хотя бы одно сообщество');
            return;
        }
        
        const editorContent = document.getElementById('message-editor');
        const hiddenInput = document.getElementById('message-hidden');
        hiddenInput.value = editorContent.innerText;
        
        const dataTransfer = new DataTransfer();
        const attachmentItems = attachmentsContainer.querySelectorAll('.attachment-item');
        let pastedImageCount = 0;
        
        attachmentItems.forEach(item => {
            const img = item.querySelector('img');
            if (img && img.src && img.src.startsWith('data:image/')) {
                try {
                    const byteString = atob(img.src.split(',')[1]);
                    const mimeString = img.src.split(',')[0].split(':')[1].split(';')[0];
                    const ab = new ArrayBuffer(byteString.length);
                    const ia = new Uint8Array(ab);
                    for (let i = 0; i < byteString.length; i++) {
                        ia[i] = byteString.charCodeAt(i);
                    }
                    const blob = new Blob([ab], { type: mimeString });
                    const extension = mimeString.split('/')[1] || 'png';
                    const fileName = `pasted_image_${pastedImageCount++}.${extension}`;
                    const file = new File([blob], fileName, { type: mimeString });
                    dataTransfer.items.add(file);
                } catch (err) {
                    console.error('Failed to convert pasted image to File:', err);
                }
            }
        });

        fileInput.files = dataTransfer.files;
        const formData = new FormData(postForm);
        
        progressContainer.style.display = 'flex';
        submitBtn.disabled = true;
        submitBtn.textContent = 'Публикация...';
        
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
                setTimeout(() => {
                    window.location.href = '/vk_poster/views/main/create_post.php';
                }, 500);
            } else {
                alert('Ошибка: ' + (data.message || 'Неизвестная ошибка'));
                submitBtn.disabled = false;
                submitBtn.textContent = 'Опубликовать';
                progressContainer.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Ошибка сети');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Опубликовать';
            progressContainer.style.display = 'none';
        });
    });
});
</script>

<?php include '../layouts/footer.php'; ?>