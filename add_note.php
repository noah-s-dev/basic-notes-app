<?php
require_once 'includes/config.php';
requireLogin();

// Check user limits
if (!checkUserLimits('notes')) {
    header('Location: subscription.php?limit=notes');
    exit();
}

$user_prefs = getUserPreferences();
$categories = getUserCategories();
$tags = getUserTags();
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $selected_tags = $_POST['tags'] ?? [];
        $is_public = isset($_POST['is_public']);
        $is_pinned = isset($_POST['is_pinned']);
        $is_favorite = isset($_POST['is_favorite']);
        
        // Validate input
        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $error = 'Title must be less than 255 characters.';
        } else {
            try {
                $pdo = getDBConnection();
                $pdo->beginTransaction();
                
                // Calculate note statistics
                $stats = calculateNoteStats($content);
                
                // Insert note
                $stmt = $pdo->prepare("
                    INSERT INTO notes (user_id, category_id, title, content, summary, is_public, is_pinned, is_favorite, 
                                     word_count, character_count, estimated_read_time)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $summary = substr(strip_tags($content), 0, 200);
                $stmt->execute([
                    $_SESSION['user_id'],
                    $category_id > 0 ? $category_id : null,
                    $title,
                    $content,
                    $summary,
                    $is_public,
                    $is_pinned,
                    $is_favorite,
                    $stats['word_count'],
                    $stats['character_count'],
                    $stats['estimated_read_time']
                ]);
                
                $note_id = $pdo->lastInsertId();
                
                // Handle tags
                if (!empty($selected_tags)) {
                    $stmt = $pdo->prepare("INSERT INTO note_tags (note_id, tag_id) VALUES (?, ?)");
                    foreach ($selected_tags as $tag_id) {
                        $stmt->execute([$note_id, $tag_id]);
                    }
                }
                
                // Handle file uploads
                if (ENABLE_FILE_UPLOADS && !empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = UPLOAD_PATH . date('Y/m/');
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO attachments (note_id, user_id, filename, original_filename, file_path, 
                                               file_size, mime_type, file_extension, is_image, image_width, image_height)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file = [
                                'name' => $_FILES['attachments']['name'][$key],
                                'type' => $_FILES['attachments']['type'][$key],
                                'tmp_name' => $tmp_name,
                                'error' => $_FILES['attachments']['error'][$key],
                                'size' => $_FILES['attachments']['size'][$key]
                            ];
                            
                            $validation = validateFileUpload($file);
                            if ($validation['valid']) {
                                $filename = generateUniqueFilename($file['name']);
                                $file_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($tmp_name, $file_path)) {
                                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                                    $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif']);
                                    $image_width = $image_height = null;
                                    
                                    if ($is_image) {
                                        $image_info = getimagesize($file_path);
                                        if ($image_info) {
                                            $image_width = $image_info[0];
                                            $image_height = $image_info[1];
                                        }
                                    }
                                    
                                    $stmt->execute([
                                        $note_id,
                                        $_SESSION['user_id'],
                                        $filename,
                                        $file['name'],
                                        $file_path,
                                        $file['size'],
                                        $file['type'],
                                        $extension,
                                        $is_image,
                                        $image_width,
                                        $image_height
                                    ]);
                                }
                            }
                        }
                    }
                }
                
                // Create note history entry
                if (ENABLE_VERSION_HISTORY) {
                    $stmt = $pdo->prepare("
                        INSERT INTO note_history (note_id, user_id, title, content, version_number, change_summary)
                        VALUES (?, ?, ?, ?, 1, 'Initial version')
                    ");
                    $stmt->execute([$note_id, $_SESSION['user_id'], $title, $content]);
                }
                
                $pdo->commit();
                
                logUserActivity('create_note', ['note_id' => $note_id, 'title' => $title]);
                
                header('Location: dashboard.php?created=1');
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Error creating note. Please try again.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Note - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .editor-toolbar {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom: none;
            padding: 10px;
            border-radius: 5px 5px 0 0;
        }
        .editor-toolbar button {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .content-editor {
            border: 1px solid #dee2e6;
            border-radius: 0 0 5px 5px;
            min-height: 400px;
        }
        .tag-item {
            display: inline-block;
            background: #e9ecef;
            border-radius: 15px;
            padding: 2px 8px;
            margin: 2px;
            font-size: 0.8rem;
        }
        .tag-item.selected {
            background: #007bff;
            color: white;
        }
        .file-preview {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.3s;
        }
        .upload-area:hover {
            border-color: #007bff;
        }
        .upload-area.dragover {
            border-color: #007bff;
            background: #f8f9ff;
        }
    </style>
</head>
<body class="<?php echo $user_prefs['theme'] ?? 'light'; ?>-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-journal-text"></i> <?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                    <h2><i class="bi bi-plus-circle"></i> Add New Note</h2>
                        <p class="text-muted">Create a new note with enhanced features</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <!-- Note Form -->
                <form method="POST" enctype="multipart/form-data" id="noteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <!-- Title -->
                            <div class="mb-3">
                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo sanitizeInput($_POST['title'] ?? ''); ?>" 
                               required maxlength="255" placeholder="Enter note title...">
                                <div class="form-text">Maximum 255 characters</div>
                            </div>
                            
                    <!-- Category -->
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="0">No Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitizeInput($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tags -->
                            <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <div class="tag-container">
                            <?php foreach ($tags as $tag): ?>
                                <span class="tag-item" data-tag-id="<?php echo $tag['id']; ?>" 
                                      style="background-color: <?php echo $tag['color']; ?>; color: white;">
                                    <?php echo sanitizeInput($tag['name']); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="tags" id="selected_tags" value="">
                    </div>

                    <!-- Note Options -->
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                       <?php echo isset($_POST['is_public']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_public">
                                    <i class="bi bi-globe"></i> Make Public
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned" 
                                       <?php echo isset($_POST['is_pinned']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_pinned">
                                    <i class="bi bi-pin-angle"></i> Pin Note
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_favorite" name="is_favorite" 
                                       <?php echo isset($_POST['is_favorite']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_favorite">
                                    <i class="bi bi-heart"></i> Add to Favorites
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Content Editor -->
                            <div class="mb-3">
                                <label for="content" class="form-label">Content</label>
                        <div class="editor-toolbar">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')">
                                <i class="bi bi-type-bold"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')">
                                <i class="bi bi-type-italic"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')">
                                <i class="bi bi-type-underline"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ul')">
                                <i class="bi bi-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertList('ol')">
                                <i class="bi bi-list-ol"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertLink()">
                                <i class="bi bi-link"></i>
                            </button>
                        </div>
                        <div class="content-editor" id="content" contenteditable="true" 
                             data-placeholder="Start writing your note here..."></div>
                        <textarea name="content" id="content_hidden" style="display: none;"></textarea>
                            </div>
                            
                    <!-- File Attachments -->
                    <?php if (ENABLE_FILE_UPLOADS): ?>
                        <div class="mb-3">
                            <label class="form-label">Attachments</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-cloud-upload display-4 text-muted"></i>
                                <p class="mt-2">Drag and drop files here or click to browse</p>
                                <input type="file" id="attachments" name="attachments[]" multiple 
                                       accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.md" style="display: none;">
                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('attachments').click()">
                                    <i class="bi bi-folder-plus"></i> Choose Files
                                </button>
                            </div>
                            <div id="filePreview" class="mt-3"></div>
                            <div class="form-text">
                                Maximum file size: <?php echo (MAX_FILE_SIZE / 1024 / 1024); ?>MB. 
                                Allowed types: <?php echo implode(', ', ALLOWED_FILE_TYPES); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                            <i class="bi bi-save"></i> Save Draft
                        </button>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-danger me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Create Note
                            </button>
                        </div>
                            </div>
                        </form>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <!-- Note Statistics -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Note Statistics</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-2">
                                <div class="text-primary fw-bold" id="wordCount">0</div>
                                <small class="text-muted">Words</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-success fw-bold" id="charCount">0</div>
                                <small class="text-muted">Characters</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-info fw-bold" id="readTime">0</div>
                                <small class="text-muted">Min Read</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-warning fw-bold" id="fileCount">0</div>
                                <small class="text-muted">Files</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="insertTemplate('meeting')">
                                <i class="bi bi-calendar-event"></i> Meeting Template
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="insertTemplate('todo')">
                                <i class="bi bi-check2-square"></i> To-Do Template
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="insertTemplate('project')">
                                <i class="bi bi-folder"></i> Project Template
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tag selection
        document.querySelectorAll('.tag-item').forEach(tag => {
            tag.addEventListener('click', function() {
                this.classList.toggle('selected');
                updateSelectedTags();
            });
        });

        function updateSelectedTags() {
            const selectedTags = Array.from(document.querySelectorAll('.tag-item.selected'))
                .map(tag => tag.dataset.tagId);
            document.getElementById('selected_tags').value = JSON.stringify(selectedTags);
        }

        // Content editor
        const contentEditor = document.getElementById('content');
        const contentHidden = document.getElementById('content_hidden');

        contentEditor.addEventListener('input', function() {
            contentHidden.value = this.innerHTML;
            updateStats();
        });

        function updateStats() {
            const text = contentEditor.innerText;
            const words = text.trim() ? text.trim().split(/\s+/).length : 0;
            const chars = text.length;
            const readTime = Math.max(1, Math.round(words / 200));

            document.getElementById('wordCount').textContent = words;
            document.getElementById('charCount').textContent = chars;
            document.getElementById('readTime').textContent = readTime;
        }

        // Text formatting
        function formatText(command) {
            document.execCommand(command, false, null);
            contentEditor.focus();
        }

        function insertList(type) {
            const list = type === 'ul' ? '<ul><li>Item</li></ul>' : '<ol><li>Item</li></ol>';
            document.execCommand('insertHTML', false, list);
            contentEditor.focus();
        }

        function insertLink() {
            const url = prompt('Enter URL:');
            if (url) {
                document.execCommand('createLink', false, url);
            }
            contentEditor.focus();
        }

        // File upload
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('attachments');
        const filePreview = document.getElementById('filePreview');

        uploadArea.addEventListener('click', () => fileInput.click());

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            filePreview.innerHTML = '';
            document.getElementById('fileCount').textContent = files.length;

            Array.from(files).forEach(file => {
                const div = document.createElement('div');
                div.className = 'd-inline-block me-2 mb-2';
                
                if (file.type.startsWith('image/')) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    img.className = 'file-preview';
                    div.appendChild(img);
                } else {
                    const icon = document.createElement('i');
                    icon.className = 'bi bi-file-earmark display-6 text-muted';
                    div.appendChild(icon);
                }
                
                const name = document.createElement('div');
                name.className = 'small text-muted';
                name.textContent = file.name.length > 15 ? file.name.substring(0, 15) + '...' : file.name;
                div.appendChild(name);
                
                filePreview.appendChild(div);
            });
        }

        // Templates
        function insertTemplate(type) {
            let template = '';
            switch (type) {
                case 'meeting':
                    template = `
                        <h3>Meeting Notes</h3>
                        <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                        <p><strong>Attendees:</strong></p>
                        <ul>
                            <li>Person 1</li>
                            <li>Person 2</li>
                        </ul>
                        <h4>Agenda:</h4>
                        <ol>
                            <li>Item 1</li>
                            <li>Item 2</li>
                        </ol>
                        <h4>Action Items:</h4>
                        <ul>
                            <li>Action 1 - Assigned to: Person 1</li>
                            <li>Action 2 - Assigned to: Person 2</li>
                        </ul>
                    `;
                    break;
                case 'todo':
                    template = `
                        <h3>To-Do List</h3>
                        <ul>
                            <li><input type="checkbox"> Task 1</li>
                            <li><input type="checkbox"> Task 2</li>
                            <li><input type="checkbox"> Task 3</li>
                        </ul>
                        <h4>Notes:</h4>
                        <p>Additional notes here...</p>
                    `;
                    break;
                case 'project':
                    template = `
                        <h3>Project Notes</h3>
                        <p><strong>Project Name:</strong></p>
                        <p><strong>Start Date:</strong> ${new Date().toLocaleDateString()}</p>
                        <p><strong>Deadline:</strong></p>
                        <h4>Objectives:</h4>
                        <ul>
                            <li>Objective 1</li>
                            <li>Objective 2</li>
                        </ul>
                        <h4>Progress:</h4>
                        <p>Current progress and updates...</p>
                    `;
                    break;
            }
            
            contentEditor.innerHTML = template;
            contentHidden.value = template;
            updateStats();
        }

        // Auto-save draft
        let autoSaveTimer;
        function saveDraft() {
            const draft = {
                title: document.getElementById('title').value,
                content: contentEditor.innerHTML,
                category_id: document.getElementById('category_id').value,
                tags: document.getElementById('selected_tags').value,
                timestamp: new Date().toISOString()
            };
            
            localStorage.setItem('note_draft', JSON.stringify(draft));
            alert('Draft saved!');
        }
        
        // Load draft on page load
        window.addEventListener('load', () => {
            const draft = localStorage.getItem('note_draft');
            if (draft) {
                const data = JSON.parse(draft);
                if (confirm('Load saved draft?')) {
                    document.getElementById('title').value = data.title || '';
                    contentEditor.innerHTML = data.content || '';
                    contentHidden.value = data.content || '';
                    document.getElementById('category_id').value = data.category_id || '0';
                    document.getElementById('selected_tags').value = data.tags || '';
                    updateStats();
                }
            }
        });

        // Form submission
        document.getElementById('noteForm').addEventListener('submit', function(e) {
            contentHidden.value = contentEditor.innerHTML;
            localStorage.removeItem('note_draft');
        });
    </script>
</body>
</html>

