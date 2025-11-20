<?php
require_once 'includes/config.php';
requireLogin();

// Log dashboard visit
logUserActivity('dashboard_view');

$search_query = sanitizeInput($_GET['search'] ?? '');
$category_filter = (int)($_GET['category'] ?? 0);
$tag_filter = (int)($_GET['tag'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'updated_at';
$sort_order = $_GET['order'] ?? 'DESC';

$notes = [];
$categories = getUserCategories();
$tags = getUserTags();
$user_prefs = getUserPreferences();
$subscription = getUserSubscription();

try {
    $pdo = getDBConnection();
    
    // Build the query with filters
    $where_conditions = ['n.user_id = ?'];
    $params = [$_SESSION['user_id']];
    
    if (!empty($search_query)) {
        $where_conditions[] = '(n.title LIKE ? OR n.content LIKE ?)';
        $search_term = '%' . $search_query . '%';
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    if ($category_filter > 0) {
        $where_conditions[] = 'n.category_id = ?';
        $params[] = $category_filter;
    }
    
    if ($tag_filter > 0) {
        $where_conditions[] = 'EXISTS (SELECT 1 FROM note_tags nt WHERE nt.note_id = n.id AND nt.tag_id = ?)';
        $params[] = $tag_filter;
    }
    
    if ($status_filter) {
        switch ($status_filter) {
            case 'pinned':
                $where_conditions[] = 'n.is_pinned = 1';
                break;
            case 'favorite':
                $where_conditions[] = 'n.is_favorite = 1';
                break;
            case 'archived':
                $where_conditions[] = 'n.is_archived = 1';
                break;
            case 'public':
                $where_conditions[] = 'n.is_public = 1';
                break;
        }
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get notes with category and tag information
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            c.name as category_name,
            c.color as category_color,
            c.icon as category_icon,
            GROUP_CONCAT(t.name) as tag_names,
            GROUP_CONCAT(t.color) as tag_colors,
            (SELECT COUNT(*) FROM attachments a WHERE a.note_id = n.id) as attachment_count,
            (SELECT COUNT(*) FROM note_comments nc WHERE nc.note_id = n.id) as comment_count
        FROM notes n
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN note_tags nt ON n.id = nt.note_id
        LEFT JOIN tags t ON nt.tag_id = t.id
        WHERE $where_clause
        GROUP BY n.id
        ORDER BY 
            CASE WHEN n.is_pinned = 1 THEN 0 ELSE 1 END,
            n.$sort_by $sort_order
    ");
    
    $stmt->execute($params);
    $notes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = 'Error loading notes. Please try again.';
}

// Function to truncate content for preview
function truncateContent($content, $length = 100) {
    return strlen($content) > $length ? substr($content, 0, $length) . '...' : $content;
}

// Get user stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_notes,
            SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END) as pinned_notes,
            SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END) as favorite_notes,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived_notes,
            SUM(word_count) as total_words,
            SUM(character_count) as total_characters
        FROM notes 
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_notes' => 0, 'pinned_notes' => 0, 'favorite_notes' => 0, 'archived_notes' => 0, 'total_words' => 0, 'total_characters' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .note-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .note-card.pinned {
            border-left: 4px solid #ffc107;
        }
        .note-card.favorite {
            border-left: 4px solid #dc3545;
        }
        .category-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .tag-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            margin: 0.1rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .premium-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #000;
            font-weight: bold;
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
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?php echo sanitizeInput($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <?php if (!isPremiumUser()): ?>
                            <li><a class="dropdown-item" href="subscription.php"><i class="bi bi-star"></i> Upgrade to Pro</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm">
                            <!-- Search -->
                            <div class="mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" class="form-control form-control-sm" name="search" 
                                       value="<?php echo $search_query; ?>" placeholder="Search notes...">
                            </div>
                            
                            <!-- Category Filter -->
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select form-select-sm" name="category">
                                    <option value="0">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitizeInput($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Tag Filter -->
                            <div class="mb-3">
                                <label class="form-label">Tag</label>
                                <select class="form-select form-select-sm" name="tag">
                                    <option value="0">All Tags</option>
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?php echo $tag['id']; ?>" 
                                                <?php echo $tag_filter == $tag['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitizeInput($tag['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Status Filter -->
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select form-select-sm" name="status">
                                    <option value="">All Notes</option>
                                    <option value="pinned" <?php echo $status_filter == 'pinned' ? 'selected' : ''; ?>>Pinned</option>
                                    <option value="favorite" <?php echo $status_filter == 'favorite' ? 'selected' : ''; ?>>Favorites</option>
                                    <option value="archived" <?php echo $status_filter == 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    <option value="public" <?php echo $status_filter == 'public' ? 'selected' : ''; ?>>Public</option>
                                </select>
                            </div>
                            
                            <!-- Sort Options -->
                            <div class="mb-3">
                                <label class="form-label">Sort By</label>
                                <select class="form-select form-select-sm" name="sort">
                                    <option value="updated_at" <?php echo $sort_by == 'updated_at' ? 'selected' : ''; ?>>Last Updated</option>
                                    <option value="created_at" <?php echo $sort_by == 'created_at' ? 'selected' : ''; ?>>Created Date</option>
                                    <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="word_count" <?php echo $sort_by == 'word_count' ? 'selected' : ''; ?>>Word Count</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Order</label>
                                <select class="form-select form-select-sm" name="order">
                                    <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                    <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-sm w-100">Apply Filters</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">Clear All</a>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Quick Stats</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 mb-2">
                                <div class="text-primary fw-bold"><?php echo $stats['total_notes']; ?></div>
                                <small class="text-muted">Notes</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-warning fw-bold"><?php echo $stats['pinned_notes']; ?></div>
                                <small class="text-muted">Pinned</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-danger fw-bold"><?php echo $stats['favorite_notes']; ?></div>
                                <small class="text-muted">Favorites</small>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="text-info fw-bold"><?php echo number_format($stats['total_words']); ?></div>
                                <small class="text-muted">Words</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9">
                <!-- Header with actions -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2>My Notes</h2>
                        <p class="text-muted mb-0">
                            <?php echo count($notes); ?> note<?php echo count($notes) !== 1 ? 's' : ''; ?> found
                            <?php if ($search_query || $category_filter || $tag_filter || $status_filter): ?>
                                <span class="text-muted">(filtered)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                
                <a href="add_note.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add New Note
                </a>
            </div>
        </div>

                <?php if (isset($_GET['deleted'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> Note deleted successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                <?php if (isset($_GET['created'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> Note created successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle"></i> Note updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Notes display -->
        <?php if (empty($notes)): ?>
                    <div class="text-center py-5 fade-in">
                        <div class="feature-icon mx-auto mb-4">
                            <i class="bi bi-journal-x"></i>
                        </div>
                        <h4 class="text-muted mb-3">
                            <?php echo ($search_query || $category_filter || $tag_filter || $status_filter) ? 'No notes found matching your filters.' : 'No notes yet.'; ?>
                </h4>
                        <p class="text-muted mb-4">
                            <?php echo ($search_query || $category_filter || $tag_filter || $status_filter) ? 'Try adjusting your filters.' : 'Create your first note to get started!'; ?>
                </p>
                        <?php if (!($search_query || $category_filter || $tag_filter || $status_filter)): ?>
                            <a href="add_note.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle"></i> Create Your First Note
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($notes as $note): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card note-card h-100 <?php echo $note['is_pinned'] ? 'pinned' : ''; ?> <?php echo $note['is_favorite'] ? 'favorite' : ''; ?>">
                            <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0"><?php echo sanitizeInput($note['title']); ?></h6>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><a class="dropdown-item" href="edit_note.php?id=<?php echo $note['id']; ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                                    <li><a class="dropdown-item" href="view_note.php?id=<?php echo $note['id']; ?>"><i class="bi bi-eye"></i> View</a></li>
                                                    <?php if (ENABLE_NOTE_SHARING): ?>
                                                        <li><a class="dropdown-item" href="share_note.php?id=<?php echo $note['id']; ?>"><i class="bi bi-share"></i> Share</a></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item text-danger" href="delete_note.php?id=<?php echo $note['id']; ?>" onclick="return confirm('Are you sure you want to delete this note?')"><i class="bi bi-trash"></i> Delete</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text text-muted small">
                                            <?php echo nl2br(sanitizeInput(truncateContent($note['content'], 120))); ?>
                                        </p>
                                        
                                        <!-- Category and Tags -->
                                        <div class="mb-2">
                                            <?php if ($note['category_name']): ?>
                                                <span class="badge category-badge" style="background-color: <?php echo $note['category_color']; ?>">
                                                    <i class="bi <?php echo $note['category_icon']; ?>"></i> <?php echo sanitizeInput($note['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($note['tag_names']): ?>
                                                <?php 
                                                $tag_names = explode(',', $note['tag_names']);
                                                $tag_colors = explode(',', $note['tag_colors']);
                                                for ($i = 0; $i < min(count($tag_names), 3); $i++): 
                                                ?>
                                                    <span class="badge tag-badge" style="background-color: <?php echo $tag_colors[$i] ?? '#6c757d'; ?>">
                                                        <?php echo sanitizeInput($tag_names[$i]); ?>
                                                    </span>
                                                <?php endfor; ?>
                                                <?php if (count($tag_names) > 3): ?>
                                                    <span class="badge tag-badge bg-secondary">+<?php echo count($tag_names) - 3; ?></span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Note stats -->
                                        <div class="row text-center small text-muted mb-2">
                                            <div class="col-4">
                                                <i class="bi bi-file-text"></i> <?php echo $note['word_count']; ?> words
                                            </div>
                                            <div class="col-4">
                                                <i class="bi bi-clock"></i> <?php echo $note['estimated_read_time']; ?> min
                                            </div>
                                            <div class="col-4">
                                                <i class="bi bi-paperclip"></i> <?php echo $note['attachment_count']; ?>
                                            </div>
                                        </div>
                            </div>
                                    
                            <div class="card-footer bg-transparent">
                                        <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                                <?php echo date('M j, Y g:i A', strtotime($note['updated_at'])); ?>
                                </small>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($note['is_public']): ?>
                                                    <span class="badge bg-success" title="Public note"><i class="bi bi-globe"></i></span>
                                                <?php endif; ?>
                                                <?php if ($note['comment_count'] > 0): ?>
                                                    <span class="badge bg-info" title="<?php echo $note['comment_count']; ?> comments"><i class="bi bi-chat"></i> <?php echo $note['comment_count']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

     <!-- Footer -->
     <footer class="bg-dark text-light py-4 mt-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-2"><i class="bi bi-journal-text"></i> <?php echo APP_NAME; ?></h5>
                    <p class="text-muted mb-0">Professional note-taking for modern teams.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="text-center my-2">
                        <div>
                            <span class="text-light">© 2025 . </span>
                            <span class="text-light">Developed by </span>
                            <a href="https://rivertheme.com" class="fw-semibold text-decoration-none fw-bold" target="_blank" rel="noopener" style="color: #5a6fd8;" >RiverTheme</a>
                        </div>
                    </div>
                </div>
            </div>
    </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Auto-submit form when filters change
        document.querySelectorAll('#filterForm select').forEach(select => {
            select.addEventListener('change', () => {
                document.getElementById('filterForm').submit();
            });
        });
        
        // Auto-submit search after delay
        let searchTimeout;
        document.querySelector('input[name="search"]').addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.getElementById('filterForm').submit();
            }, 500);
        });
    </script>
</body>
</html>

