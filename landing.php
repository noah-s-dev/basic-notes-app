<?php
require_once 'includes/config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Professional Note Taking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-journal-text"></i> <?php echo APP_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a href="login.php" class="nav-link">Sign In</a>
                <a href="register.php" class="nav-link">Register</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-3 fw-bold mb-4 fade-in">
                        Professional Note Taking for <span class="text-warning">Modern Teams</span>
                    </h1>
                    <p class="lead mb-4 fs-5 fade-in">
                        Organize your thoughts, collaborate with colleagues, and never lose important information again. 
                        <?php echo APP_NAME; ?> is the ultimate note-taking solution for professionals.
                    </p>
                    <div class="d-flex gap-4 fade-in">
                        <a href="register.php" class="btn btn-light btn-lg px-4 py-3">
                            <i class="bi bi-person-plus me-2"></i> Get Started Free
                        </a>
                        <a href="#features" class="btn btn-outline-light btn-lg px-4 py-3">
                            <i class="bi bi-play-circle me-2"></i> Learn More
                        </a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="text-center">
                        <i class="bi bi-journal-text display-1 text-light opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold">Powerful Features</h2>
                <p class="lead text-muted">Everything you need for professional note-taking</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon text-primary">
                                <i class="bi bi-pencil-square"></i>
                            </div>
                            <h5 class="card-title">Rich Text Editor</h5>
                            <p class="card-text">Create beautiful notes with our powerful rich text editor. Format text, add links, and organize your content.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon text-success">
                                <i class="bi bi-folder"></i>
                            </div>
                            <h5 class="card-title">Smart Organization</h5>
                            <p class="card-text">Organize notes with categories, tags, and folders. Find what you need instantly with powerful search.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon text-info">
                                <i class="bi bi-share"></i>
                            </div>
                            <h5 class="card-title">Team Collaboration</h5>
                            <p class="card-text">Share notes with your team, collaborate in real-time, and keep everyone on the same page.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="display-4 fw-bold">Simple Pricing</h2>
                <p class="lead text-muted">Choose the plan that fits your needs</p>
            </div>
            
            <div class="row g-4 justify-content-center">
                <div class="col-md-4">
                    <div class="card pricing-card h-100">
                        <div class="card-header text-center">
                            <h5 class="mb-0">Free</h5>
                            <div class="mt-2">
                                <span class="h2">$0</span>
                                <small>/month</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Up to 50 notes</li>
                                <li><i class="bi bi-check text-success"></i> Basic categories</li>
                                <li><i class="bi bi-check text-success"></i> 100MB storage</li>
                                <li><i class="bi bi-check text-success"></i> Email support</li>
                            </ul>
                            <a href="register.php" class="btn btn-outline-primary w-100">Get Started</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card pricing-card featured h-100">
                        <div class="card-header text-center bg-primary text-white">
                            <h5 class="mb-0 text-primary">Premium</h5>
                            <div class="mt-2">
                                <span class="h2 text-primary">$9.99</span>
                                <small class="text-primary">/month</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Unlimited notes</li>
                                <li><i class="bi bi-check text-success"></i> Advanced categories</li>
                                <li><i class="bi bi-check text-success"></i> 10GB storage</li>
                                <li><i class="bi bi-check text-success"></i> Team collaboration</li>
                                <li><i class="bi bi-check text-success"></i> Priority support</li>
                            </ul>
                            <a href="register.php" class="btn btn-primary w-100">Get Premium</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card pricing-card h-100">
                        <div class="card-header text-center">
                            <h5 class="mb-0">Enterprise</h5>
                            <div class="mt-2">
                                <span class="h2">$29.99</span>
                                <small>/month</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="bi bi-check text-success"></i> Everything in Premium</li>
                                <li><i class="bi bi-check text-success"></i> Unlimited storage</li>
                                <li><i class="bi bi-check text-success"></i> Advanced analytics</li>
                                <li><i class="bi bi-check text-success"></i> Custom integrations</li>
                                <li><i class="bi bi-check text-success"></i> 24/7 support</li>
                            </ul>
                            <a href="register.php" class="btn btn-outline-primary w-100">Contact Sales</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
</body>
</html> 