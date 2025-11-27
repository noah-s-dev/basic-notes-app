<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'notes_app');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Notes Pro');
define('APP_VERSION', '2.0.0');
// Update this to match your project directory name if different from 'basic-notes-app'
define('BASE_URL', 'http://localhost/basic-notes-app');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'md']);

// Security configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('CSRF_TOKEN_EXPIRY', 1800); // 30 minutes
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Feature flags
define('ENABLE_PREMIUM_FEATURES', true);
define('ENABLE_FILE_UPLOADS', true);
define('ENABLE_NOTE_SHARING', true);
define('ENABLE_COLLABORATION', true);
define('ENABLE_VERSION_HISTORY', true);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Redirect to login if not authenticated
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Check if user has premium features
function isPremiumUser() {
    if (!isLoggedIn()) return false;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT is_premium, subscription_type FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        return $user && ($user['is_premium'] || in_array($user['subscription_type'], ['premium', 'enterprise']));
    } catch (PDOException $e) {
        return false;
    }
}

// Check user's subscription limits
function checkUserLimits($feature = 'notes') {
    if (!isLoggedIn()) return false;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT u.subscription_type, sp.max_notes, sp.max_storage_mb, sp.max_attachments_per_note
            FROM users u
            LEFT JOIN user_subscriptions us ON u.id = us.user_id
            LEFT JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        switch ($feature) {
            case 'notes':
                if ($user['max_notes'] === -1) return true; // Unlimited
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notes WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $count = $stmt->fetch()['count'];
                return $count < $user['max_notes'];
                
            case 'storage':
                if ($user['max_storage_mb'] === -1) return true; // Unlimited
                $stmt = $pdo->prepare("SELECT SUM(file_size) as total FROM attachments WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $total = $stmt->fetch()['total'] ?? 0;
                return ($total / 1024 / 1024) < $user['max_storage_mb'];
                
            case 'attachments':
                if ($user['max_attachments_per_note'] === -1) return true; // Unlimited
                return true; // This should be checked per note
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Sanitize input to prevent XSS
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_expires']) || 
        time() > $_SESSION['csrf_token_expires']) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + CSRF_TOKEN_EXPIRY;
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && 
           isset($_SESSION['csrf_token_expires']) && 
           time() <= $_SESSION['csrf_token_expires'] && 
           hash_equals($_SESSION['csrf_token'], $token);
}

// Log user activity
function logUserActivity($activity_type, $activity_details = null) {
    if (!isLoggedIn()) return;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO user_activities (user_id, activity_type, activity_details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $activity_type,
            $activity_details ? json_encode($activity_details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Log error silently
    }
}

// Get user preferences
function getUserPreferences() {
    if (!isLoggedIn()) return null;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Update user preferences
function updateUserPreferences($preferences) {
    if (!isLoggedIn()) return false;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO user_preferences (user_id, theme, font_size, editor_type, auto_save_interval, email_notifications, push_notifications) 
            VALUES (?, ?, ?, ?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            theme = VALUES(theme), 
            font_size = VALUES(font_size), 
            editor_type = VALUES(editor_type), 
            auto_save_interval = VALUES(auto_save_interval), 
            email_notifications = VALUES(email_notifications), 
            push_notifications = VALUES(push_notifications)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $preferences['theme'] ?? 'light',
            $preferences['font_size'] ?? 'medium',
            $preferences['editor_type'] ?? 'rich',
            $preferences['auto_save_interval'] ?? 30,
            $preferences['email_notifications'] ?? true,
            $preferences['push_notifications'] ?? false
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Get user categories
function getUserCategories() {
    if (!isLoggedIn()) return [];
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Get user tags
function getUserTags() {
    if (!isLoggedIn()) return [];
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT * FROM tags WHERE user_id = ? ORDER BY name");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// Calculate word count and read time
function calculateNoteStats($content) {
    $word_count = str_word_count(strip_tags($content));
    $character_count = strlen(strip_tags($content));
    $estimated_read_time = max(1, round($word_count / 200)); // Average reading speed: 200 words per minute
    
    return [
        'word_count' => $word_count,
        'character_count' => $character_count,
        'estimated_read_time' => $estimated_read_time
    ];
}

// Validate file upload
function validateFileUpload($file) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_FILE_TYPES)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    return ['valid' => true];
}

// Generate unique filename
function generateUniqueFilename($original_name) {
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . '.' . $extension;
}

// Format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Get user's subscription info
function getUserSubscription() {
    if (!isLoggedIn()) return null;
    
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT us.*, sp.name as plan_name, sp.description, sp.price, sp.features
            FROM user_subscriptions us
            JOIN subscription_plans sp ON us.plan_id = sp.id
            WHERE us.user_id = ? AND us.status = 'active'
            ORDER BY us.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}
?>

