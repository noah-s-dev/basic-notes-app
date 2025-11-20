<?php
require_once 'includes/config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// Redirect to landing page
header('Location: landing.php');
exit();