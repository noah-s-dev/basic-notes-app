<?php
require_once 'includes/config.php';
requireLogin();

$note_id = intval($_GET['id'] ?? 0);
$error = '';
$note = null;

// Get the note to verify ownership
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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
            
            if ($stmt->execute([$note_id, $_SESSION['user_id']])) {
                header('Location: dashboard.php?deleted=1');
                exit();
            } else {
                $error = 'Failed to delete note. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Note - <?php echo APP_NAME; ?></title>
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
            <div class="col-md-6 mx-auto">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-trash text-danger"></i> Delete Note</h2>
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($note): ?>
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Confirm Deletion</h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Are you sure you want to delete this note? This action cannot be undone.</p>
                            
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo sanitizeInput($note['title']); ?></h6>
                                    <p class="card-text text-muted">
                                        <?php 
                                        $preview = strlen($note['content']) > 200 ? 
                                                  substr($note['content'], 0, 200) . '...' : 
                                                  $note['content'];
                                        echo nl2br(sanitizeInput($preview)); 
                                        ?>
                                    </p>
                                    <small class="text-muted">
                                        Created: <?php echo date('M j, Y g:i A', strtotime($note['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                
                                <div class="d-flex justify-content-between">
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="bi bi-arrow-left"></i> Cancel
                                    </a>
                                    <div>
                                        <a href="edit_note.php?id=<?php echo $note['id']; ?>" class="btn btn-outline-primary me-2">
                                            <i class="bi bi-pencil"></i> Edit Instead
                                        </a>
                                        <button type="submit" class="btn btn-danger">
                                            <i class="bi bi-trash"></i> Delete Note
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

