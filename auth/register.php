<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

if (is_logged_in()) {
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('../register.php');
    }
    
    $username = sanitize_input($_POST['username'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = sanitize_input($_POST['role'] ?? 'individual');
    $terms = isset($_POST['terms']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        flash_message('error', 'All fields are required');
        redirect('../register.php');
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        flash_message('error', 'Username should be 3-20 characters, letters, numbers, and underscores only');
        redirect('../register.php');
    }
    
    if (!validate_email($email)) {
        flash_message('error', 'Invalid email address');
        redirect('../register.php');
    }
    
    if (strlen($password) < 8) {
        flash_message('error', 'Password must be at least 8 characters');
        redirect('../register.php');
    }
    
    if ($password !== $confirm_password) {
        flash_message('error', 'Passwords do not match');
        redirect('../register.php');
    }
    
    if (!$terms) {
        flash_message('error', 'You must agree to the terms and conditions');
        redirect('../register.php');
    }
    
    $db = Database::getInstance();
    
    // Check if username already exists
    if ($db->userExists($username)) {
        flash_message('error', 'Username already exists');
        redirect('../register.php');
    }
    
    // Check if email already exists
    if ($db->emailExists($email)) {
        flash_message('error', 'Email already registered');
        redirect('../register.php');
    }
    
    // Create user
    if ($db->createUserData($username, $email, $password, $role)) {
        // Send welcome email
        $subject = 'Welcome to ' . APP_NAME;
        $message = "
            <h2>Welcome to " . APP_NAME . "!</h2>
            <p>Dear {$username},</p>
            <p>Thank you for registering with " . APP_NAME . ". Your account has been successfully created.</p>
            <p><strong>Account Details:</strong></p>
            <ul>
                <li>Username: {$username}</li>
                <li>Email: {$email}</li>
                <li>Account Type: " . ucfirst($role) . "</li>
            </ul>
            <p>You can now login to your account and start managing your finances.</p>
            <p>Login URL: <a href='" . BASE_URL . "'>" . BASE_URL . "</a></p>
            <p>Best regards,<br>" . APP_NAME . " Team</p>
        ";
        
        send_email($email, $subject, $message);
        
        flash_message('success', 'Registration successful! Please check your email for welcome message.');
        redirect('../index.php');
    } else {
        flash_message('error', 'Registration failed. Please try again.');
        redirect('../register.php');
    }
} else {
    redirect('../register.php');
}
?>
