<?php
require_once 'includes/config.php';
requireLogin();

$error = '';
$note = null;
$note_id = intval($_GET['id'] ?? 0);

// Get the note
if ($note_id > 0) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM notes WHERE id = ? AND user_id = ?");
        $stmt->execute([$note_id, $_SESSION['user_id']]);
        $note = $stmt->fetch();
        
        if (!$note) {
            header('Location: dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = 'Error loading note. Please try again.';
    }
} else {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $title = sanitizeInput($_POST['title'] ?? '');
        $content = sanitizeInput($_POST['content'] ?? '');
        
        // Validation
        if (empty($title)) {
            $error = 'Title is required.';
        } elseif (strlen($title) > 255) {
            $error = 'Title must be 255 characters or less.';
        } else {
            try {
                $pdo = getDBConnection();
                $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
                
                if ($stmt->execute([$title, $content, $note_id, $_SESSION['user_id']])) {
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Failed to update note. Please try again.';
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Note - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-journal-text"></i> <?php echo APP_NAME; ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo sanitizeInput($_SESSION['username']); ?>!
                </span>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-pencil"></i> Edit Note</h2>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($note): ?>
                    <div class="card">
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo isset($_POST['title']) ? sanitizeInput($_POST['title']) : sanitizeInput($note['title']); ?>" 
                                           maxlength="255" required>
                                    <div class="form-text">Maximum 255 characters</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content</label>
                                    <textarea class="form-control" id="content" name="content" rows="10" 
                                              placeholder="Write your note here..."><?php echo isset($_POST['content']) ? sanitizeInput($_POST['content']) : sanitizeInput($note['content']); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">
                                        Created: <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                                        <?php if ($note['created_at'] !== $note['updated_at']): ?>
                                            | Last updated: <?php echo date('M j, Y g:i A', strtotime($note['updated_at'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                        <a href="delete_note.php?id=<?php echo $note['id']; ?>" 
                                           class="btn btn-outline-danger ms-2"
                                           onclick="return confirm('Are you sure you want to delete this note?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Update Note
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Auto-save draft functionality -->
    <script>
        // Simple auto-save to localStorage
        const titleInput = document.getElementById('title');
        const contentInput = document.getElementById('content');
        const noteId = <?php echo $note_id; ?>;
        
        function saveDraft() {
            localStorage.setItem('note_edit_draft_title_' + noteId, titleInput.value);
            localStorage.setItem('note_edit_draft_content_' + noteId, contentInput.value);
        }
        
        function clearDraft() {
            localStorage.removeItem('note_edit_draft_title_' + noteId);
            localStorage.removeItem('note_edit_draft_content_' + noteId);
        }
        
        // Save draft on input
        titleInput.addEventListener('input', saveDraft);
        contentInput.addEventListener('input', saveDraft);
        
        // Clear draft on successful submit
        document.querySelector('form').addEventListener('submit', function() {
            setTimeout(clearDraft, 100);
        });
        
        // Warn about unsaved changes
        let originalTitle = titleInput.value;
        let originalContent = contentInput.value;
        
        window.addEventListener('beforeunload', function(e) {
            if (titleInput.value !== originalTitle || contentInput.value !== originalContent) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>

