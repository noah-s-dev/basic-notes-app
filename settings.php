<?php
require_once 'includes/config.php';
requireLogin();

$user_prefs = getUserPreferences();
$current_subscription = getUserSubscription();
$error = '';
$success = '';

// Get user information
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $user = null;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_preferences') {
            $preferences = [
                'theme' => $_POST['theme'] ?? 'light',
                'font_size' => $_POST['font_size'] ?? 'medium',
                'editor_type' => $_POST['editor_type'] ?? 'rich',
                'auto_save_interval' => (int)($_POST['auto_save_interval'] ?? 30),
                'email_notifications' => isset($_POST['email_notifications']),
                'push_notifications' => isset($_POST['push_notifications'])
            ];
            
            if (updateUserPreferences($preferences)) {
                $success = 'Preferences updated successfully!';
                $user_prefs = getUserPreferences(); // Refresh preferences
            } else {
                $error = 'Error updating preferences. Please try again.';
            }
        }
        
        if ($action === 'update_profile') {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $timezone = $_POST['timezone'] ?? 'UTC';
            $language = $_POST['language'] ?? 'en';
            
            if (empty($email) || !isValidEmail($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET first_name = ?, last_name = ?, email = ?, bio = ?, timezone = ?, language = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$first_name, $last_name, $email, $bio, $timezone, $language, $_SESSION['user_id']]);
                    
                    $success = 'Profile updated successfully!';
                    logUserActivity('profile_updated');
                } catch (PDOException $e) {
                    $error = 'Error updating profile. Please try again.';
                }
            }
        }
        
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = 'All password fields are required.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'New passwords do not match.';
            } elseif (strlen($new_password) < PASSWORD_MIN_LENGTH) {
                $error = 'New password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
            } elseif (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                try {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_password_hash, $_SESSION['user_id']]);
                    
                    $success = 'Password changed successfully!';
                    logUserActivity('password_changed');
                } catch (PDOException $e) {
                    $error = 'Error changing password. Please try again.';
                }
            }
        }
        
        if ($action === 'delete_account') {
            $confirm_delete = $_POST['confirm_delete'] ?? '';
            $password = $_POST['delete_password'] ?? '';
            
            if ($confirm_delete !== 'DELETE') {
                $error = 'Please type DELETE to confirm account deletion.';
            } elseif (!password_verify($password, $user['password_hash'])) {
                $error = 'Password is incorrect.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Delete user data (cascade will handle related records)
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    
                    $pdo->commit();
                    
                    // Logout and redirect
                    session_destroy();
                    header('Location: index.php?account_deleted=1');
                    exit();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = 'Error deleting account. Please try again.';
                }
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
    <title>Settings - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .settings-nav {
            border-right: 1px solid #dee2e6;
        }
        .settings-nav .nav-link {
            border-radius: 0;
            border-left: 3px solid transparent;
        }
        .settings-nav .nav-link.active {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        .preview-theme {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }
        .preview-theme.light {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border: 2px solid #dee2e6;
        }
        .preview-theme.dark {
            background: linear-gradient(45deg, #343a40, #495057);
            border: 2px solid #6c757d;
        }
        .danger-zone {
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            background: #fff5f5;
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
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="bi bi-gear"></i> Settings</h2>
                <p class="text-muted">Manage your account preferences and settings</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Settings Navigation -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="nav flex-column settings-nav" id="settingsTabs" role="tablist">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                                <i class="bi bi-person"></i> Profile
                            </button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#preferences" type="button">
                                <i class="bi bi-sliders"></i> Preferences
                            </button>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#security" type="button">
                                <i class="bi bi-shield-lock"></i> Security
                            </button>
                            <?php if ($current_subscription): ?>
                                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#subscription" type="button">
                                    <i class="bi bi-star"></i> Subscription
                                </button>
                            <?php endif; ?>
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#danger" type="button">
                                <i class="bi bi-exclamation-triangle text-danger"></i> Danger Zone
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-md-9">
                <div class="tab-content">
                    <!-- Profile Tab -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="first_name" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo sanitizeInput($user['first_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="last_name" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo sanitizeInput($user['last_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo sanitizeInput($user['email'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bio" class="form-label">Bio</label>
                                        <textarea class="form-control" id="bio" name="bio" rows="3" 
                                                  placeholder="Tell us about yourself..."><?php echo sanitizeInput($user['bio'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-select" id="timezone" name="timezone">
                                                <option value="UTC" <?php echo ($user['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                                <option value="America/New_York" <?php echo ($user['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                                <option value="America/Chicago" <?php echo ($user['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                                <option value="America/Denver" <?php echo ($user['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                                <option value="America/Los_Angeles" <?php echo ($user['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                                <option value="Europe/London" <?php echo ($user['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                                <option value="Europe/Paris" <?php echo ($user['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="language" class="form-label">Language</label>
                                            <select class="form-select" id="language" name="language">
                                                <option value="en" <?php echo ($user['language'] ?? 'en') === 'en' ? 'selected' : ''; ?>>English</option>
                                                <option value="es" <?php echo ($user['language'] ?? '') === 'es' ? 'selected' : ''; ?>>Español</option>
                                                <option value="fr" <?php echo ($user['language'] ?? '') === 'fr' ? 'selected' : ''; ?>>Français</option>
                                                <option value="de" <?php echo ($user['language'] ?? '') === 'de' ? 'selected' : ''; ?>>Deutsch</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-sliders"></i> Application Preferences</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="update_preferences">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Theme</label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="theme" id="theme_light" value="light" 
                                                       <?php echo ($user_prefs['theme'] ?? 'light') === 'light' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_light">
                                                    <span class="preview-theme light"></span> Light
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="theme" id="theme_dark" value="dark" 
                                                       <?php echo ($user_prefs['theme'] ?? '') === 'dark' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_dark">
                                                    <span class="preview-theme dark"></span> Dark
                                                </label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="theme" id="theme_auto" value="auto" 
                                                       <?php echo ($user_prefs['theme'] ?? '') === 'auto' ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="theme_auto">
                                                    <i class="bi bi-circle-half"></i> Auto
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="font_size" class="form-label">Font Size</label>
                                        <select class="form-select" id="font_size" name="font_size">
                                            <option value="small" <?php echo ($user_prefs['font_size'] ?? '') === 'small' ? 'selected' : ''; ?>>Small</option>
                                            <option value="medium" <?php echo ($user_prefs['font_size'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="large" <?php echo ($user_prefs['font_size'] ?? '') === 'large' ? 'selected' : ''; ?>>Large</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="editor_type" class="form-label">Editor Type</label>
                                        <select class="form-select" id="editor_type" name="editor_type">
                                            <option value="rich" <?php echo ($user_prefs['editor_type'] ?? 'rich') === 'rich' ? 'selected' : ''; ?>>Rich Text Editor</option>
                                            <option value="markdown" <?php echo ($user_prefs['editor_type'] ?? '') === 'markdown' ? 'selected' : ''; ?>>Markdown</option>
                                            <option value="plain" <?php echo ($user_prefs['editor_type'] ?? '') === 'plain' ? 'selected' : ''; ?>>Plain Text</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="auto_save_interval" class="form-label">Auto-save Interval (seconds)</label>
                                        <input type="number" class="form-control" id="auto_save_interval" name="auto_save_interval" 
                                               value="<?php echo $user_prefs['auto_save_interval'] ?? 30; ?>" min="10" max="300">
                                        <div class="form-text">How often to auto-save your notes while editing</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" 
                                                   <?php echo ($user_prefs['email_notifications'] ?? true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_notifications">
                                                Email Notifications
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="push_notifications" name="push_notifications" 
                                                   <?php echo ($user_prefs['push_notifications'] ?? false) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="push_notifications">
                                                Push Notifications
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Save Preferences
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="security">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" 
                                               minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" required>
                                        <div class="form-text">Minimum <?php echo PASSWORD_MIN_LENGTH; ?> characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Subscription Tab -->
                    <?php if ($current_subscription): ?>
                        <div class="tab-pane fade" id="subscription">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-star"></i> Subscription Management</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6>Current Plan</h6>
                                            <p class="text-muted"><?php echo $current_subscription['plan_name']; ?></p>
                                            <p class="text-muted">$<?php echo number_format($current_subscription['price'], 2); ?> / <?php echo $current_subscription['billing_cycle']; ?></p>
                                            <p class="text-muted">Expires: <?php echo date('M j, Y', strtotime($current_subscription['end_date'])); ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Actions</h6>
                                            <a href="subscription.php" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-arrow-up-circle"></i> Upgrade Plan
                                            </a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="action" value="cancel">
                                                <button type="submit" class="btn btn-outline-warning btn-sm" 
                                                        onclick="return confirm('Are you sure you want to cancel auto-renewal?')">
                                                    <i class="bi bi-x-circle"></i> Cancel Auto-renewal
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Danger Zone Tab -->
                    <div class="tab-pane fade" id="danger">
                        <div class="danger-zone">
                            <h5 class="text-danger"><i class="bi bi-exclamation-triangle"></i> Danger Zone</h5>
                            <p class="text-muted">These actions cannot be undone. Please proceed with caution.</p>
                            
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="action" value="delete_account">
                                
                                <div class="mb-3">
                                    <label for="confirm_delete" class="form-label">Type DELETE to confirm</label>
                                    <input type="text" class="form-control" id="confirm_delete" name="confirm_delete" 
                                           placeholder="DELETE" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="delete_password" class="form-label">Enter your password</label>
                                    <input type="password" class="form-control" id="delete_password" name="delete_password" required>
                                </div>
                                
                                <button type="submit" class="btn btn-danger" 
                                        onclick="return confirm('Are you absolutely sure? This will permanently delete your account and all data.')">
                                    <i class="bi bi-trash"></i> Delete Account
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
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
    <script>
        // Theme preview
        document.querySelectorAll('input[name="theme"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.body.className = this.value + '-theme';
            });
        });
        
        // Password confirmation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 