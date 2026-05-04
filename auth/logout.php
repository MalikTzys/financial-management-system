<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (is_logged_in()) {
    // Log activity
    log_activity('logout', 'User logged out');
    
    // Clear remember me cookies
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    if (isset($_COOKIE['remember_username'])) {
        setcookie('remember_username', '', time() - 3600, '/', '', false, true);
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session for flash message
    session_start();
    flash_message('success', 'You have been logged out successfully.');
}

redirect('../index.php');
?>
