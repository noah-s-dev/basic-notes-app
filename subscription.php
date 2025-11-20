<?php
require_once 'includes/config.php';
requireLogin();

$user_prefs = getUserPreferences();
$current_subscription = getUserSubscription();

// Get all available plans
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price");
    $stmt->execute();
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    $plans = [];
}

// Get user's current usage
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as note_count,
            SUM(file_size) as total_storage_bytes
        FROM notes n
        LEFT JOIN attachments a ON n.id = a.note_id
        WHERE n.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $usage = $stmt->fetch();
    $usage['total_storage_mb'] = round(($usage['total_storage_bytes'] ?? 0) / 1024 / 1024, 2);
} catch (PDOException $e) {
    $usage = ['note_count' => 0, 'total_storage_mb' => 0];
}

// Handle subscription changes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'upgrade' && isset($_POST['plan_id'])) {
        $plan_id = (int)$_POST['plan_id'];
        
        try {
            // Get plan details
            $stmt = $pdo->prepare("SELECT * FROM subscription_plans WHERE id = ?");
            $stmt->execute([$plan_id]);
            $plan = $stmt->fetch();
            
            if ($plan) {
                // Create or update subscription
                $start_date = date('Y-m-d H:i:s');
                $end_date = date('Y-m-d H:i:s', strtotime('+1 month'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_subscriptions (user_id, plan_id, status, start_date, end_date, auto_renew)
                    VALUES (?, ?, 'active', ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                    plan_id = VALUES(plan_id),
                    status = 'active',
                    start_date = VALUES(start_date),
                    end_date = VALUES(end_date),
                    auto_renew = 1
                ");
                $stmt->execute([$_SESSION['user_id'], $plan_id, $start_date, $end_date]);
                
                // Update user subscription type
                $stmt = $pdo->prepare("UPDATE users SET subscription_type = ?, is_premium = 1 WHERE id = ?");
                $stmt->execute([strtolower($plan['name']), $_SESSION['user_id']]);
                
                logUserActivity('subscription_upgraded', ['plan' => $plan['name']]);
                
                header('Location: subscription.php?upgraded=1');
                exit();
            }
        } catch (PDOException $e) {
            $error = 'Error processing subscription. Please try again.';
        }
    }
    
    if ($action === 'cancel') {
        try {
            $stmt = $pdo->prepare("UPDATE user_subscriptions SET auto_renew = 0 WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            
            logUserActivity('subscription_cancelled');
            
            header('Location: subscription.php?cancelled=1');
            exit();
        } catch (PDOException $e) {
            $error = 'Error cancelling subscription. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .plan-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .plan-card.featured {
            border-color: #007bff;
            position: relative;
        }
        .plan-card.featured::before {
            content: "Most Popular";
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            background: #007bff;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .feature-list li:last-child {
            border-bottom: none;
        }
        .feature-list li i {
            color: #28a745;
            margin-right: 8px;
        }
        .usage-bar {
            height: 8px;
            border-radius: 4px;
            background: #e9ecef;
            overflow: hidden;
        }
        .usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #28a745, #20c997);
            transition: width 0.3s ease;
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
            <div class="col-md-8">
                <h2><i class="bi bi-star"></i> Subscription Plans</h2>
                <p class="text-muted">Choose the perfect plan for your note-taking needs</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_GET['upgraded'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> Subscription upgraded successfully! Welcome to the premium experience.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['cancelled'])): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> Subscription auto-renewal has been cancelled. Your plan will remain active until the end of the billing period.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Current Usage -->
        <?php if ($current_subscription): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Current Usage</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-primary"><?php echo $usage['note_count']; ?></h4>
                                <small class="text-muted">Notes Created</small>
                                <div class="usage-bar mt-2">
                                    <?php 
                                    $note_percentage = $current_subscription['max_notes'] > 0 ? 
                                        min(100, ($usage['note_count'] / $current_subscription['max_notes']) * 100) : 0;
                                    ?>
                                    <div class="usage-fill" style="width: <?php echo $note_percentage; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $usage['note_count']; ?> / <?php echo $current_subscription['max_notes'] > 0 ? $current_subscription['max_notes'] : '∞'; ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-success"><?php echo $usage['total_storage_mb']; ?> MB</h4>
                                <small class="text-muted">Storage Used</small>
                                <div class="usage-bar mt-2">
                                    <?php 
                                    $storage_percentage = $current_subscription['max_storage_mb'] > 0 ? 
                                        min(100, ($usage['total_storage_mb'] / $current_subscription['max_storage_mb']) * 100) : 0;
                                    ?>
                                    <div class="usage-fill" style="width: <?php echo $storage_percentage; ?>%"></div>
                                </div>
                                <small class="text-muted">
                                    <?php echo $usage['total_storage_mb']; ?> / <?php echo $current_subscription['max_storage_mb'] > 0 ? $current_subscription['max_storage_mb'] : '∞'; ?> MB
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-info"><?php echo $current_subscription['plan_name']; ?></h4>
                                <small class="text-muted">Current Plan</small>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        Expires: <?php echo date('M j, Y', strtotime($current_subscription['end_date'])); ?>
                                    </small>
                                </div>
                                <?php if ($current_subscription['auto_renew']): ?>
                                    <span class="badge bg-success">Auto-renewal enabled</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Auto-renewal disabled</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Subscription Plans -->
        <div class="row">
            <?php foreach ($plans as $plan): ?>
                <div class="col-md-6 col-lg-3 mb-4">
                    <div class="card plan-card h-100 <?php echo $plan['name'] === 'Premium' ? 'featured' : ''; ?>">
                        <div class="card-header text-center">
                            <h5 class="mb-0"><?php echo sanitizeInput($plan['name']); ?></h5>
                            <div class="mt-2">
                                <span class="h3">$<?php echo number_format($plan['price'], 2); ?></span>
                                <small class="text-muted">/<?php echo $plan['billing_cycle']; ?></small>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="text-muted text-center"><?php echo sanitizeInput($plan['description']); ?></p>
                            
                            <ul class="feature-list">
                                <li><i class="bi bi-check-circle"></i> <?php echo $plan['max_notes'] > 0 ? number_format($plan['max_notes']) : 'Unlimited'; ?> notes</li>
                                <li><i class="bi bi-check-circle"></i> <?php echo $plan['max_storage_mb'] > 0 ? number_format($plan['max_storage_mb']) . ' MB' : 'Unlimited'; ?> storage</li>
                                <li><i class="bi bi-check-circle"></i> <?php echo $plan['max_attachments_per_note']; ?> attachments per note</li>
                                <?php 
                                $features = json_decode($plan['features'], true);
                                foreach ($features as $feature):
                                    $feature_names = [
                                        'basic_notes' => 'Basic note creation',
                                        'search' => 'Search functionality',
                                        'categories' => 'Note categories',
                                        'tags' => 'Note tagging',
                                        'attachments' => 'File attachments',
                                        'sharing' => 'Note sharing',
                                        'collaboration' => 'Collaboration tools',
                                        'version_history' => 'Version history',
                                        'advanced_search' => 'Advanced search',
                                        'admin_panel' => 'Admin panel',
                                        'analytics' => 'Usage analytics',
                                        'api_access' => 'API access'
                                    ];
                                ?>
                                    <li><i class="bi bi-check-circle"></i> <?php echo $feature_names[$feature] ?? $feature; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="card-footer text-center">
                            <?php if ($current_subscription && $current_subscription['plan_name'] === $plan['name']): ?>
                                <button class="btn btn-success w-100" disabled>Current Plan</button>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="upgrade">
                                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <?php echo $current_subscription ? 'Upgrade' : 'Get Started'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- FAQ Section -->
        <div class="row mt-5">
            <div class="col-12">
                <h3>Frequently Asked Questions</h3>
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq1">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                Can I change my plan at any time?
                            </button>
                        </h2>
                        <div id="collapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, you can upgrade or downgrade your plan at any time. Changes will be prorated and reflected in your next billing cycle.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq2">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                What happens to my data if I downgrade?
                            </button>
                        </h2>
                        <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Your data is always safe. If you exceed the limits of your new plan, you'll need to reduce usage before you can create new content.
                            </div>
                        </div>
                    </div>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="faq3">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                Can I cancel my subscription?
                            </button>
                        </h2>
                        <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes, you can cancel auto-renewal at any time. Your plan will remain active until the end of the current billing period.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Support -->
        <div class="row mt-5">
            <div class="col-12 text-center">
                <div class="card">
                    <div class="card-body">
                        <h5>Need Help?</h5>
                        <p class="text-muted">Have questions about our plans or need assistance?</p>
                        <a href="mailto:support@notespro.com" class="btn btn-outline-primary">
                            <i class="bi bi-envelope"></i> Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 