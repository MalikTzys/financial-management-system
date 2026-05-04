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
        redirect('../index.php');
    }
    
    $username = sanitize_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        flash_message('error', 'Username and password are required');
        redirect('../index.php');
    }
    
    $db = Database::getInstance();
    $user_data = $db->authenticateUser($username, $password);
    
    if ($user_data) {
        $_SESSION['user_id'] = $user_data['profile']['username'];
        $_SESSION['username'] = $user_data['profile']['username'];
        $_SESSION['email'] = $user_data['profile']['email'];
        $_SESSION['role'] = $user_data['profile']['role'];
        $_SESSION['login_time'] = time();
        
        // Update last login
        $user_data['profile']['last_login'] = date('Y-m-d H:i:s');
        $db->saveUserData($username, $user_data);
        
        // Set remember me cookie
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + SESSION_LIFETIME;
            
            setcookie('remember_token', $token, $expires, '/', '', false, true);
            setcookie('remember_username', $username, $expires, '/', '', false, true);
            
            // Store token in user data
            $user_data['profile']['remember_token'] = $token;
            $user_data['profile']['remember_expires'] = date('Y-m-d H:i:s', $expires);
            $db->saveUserData($username, $user_data);
        }
        
        // Send login notification email
        if ($user_data['settings']['notifications']['email_login'] ?? true) {
            $subject = 'Login Notification - ' . APP_NAME;
            $message = "
                <h2>Login Notification</h2>
                <p>Dear {$user_data['profile']['username']},</p>
                <p>Your account was logged in from:</p>
                <ul>
                    <li>IP Address: " . get_client_ip() . "</li>
                    <li>Date/Time: " . date('Y-m-d H:i:s') . "</li>
                    <li>Device: " . $_SERVER['HTTP_USER_AGENT'] . "</li>
                </ul>
                <p>If this wasn't you, please change your password immediately.</p>
                <p>Best regards,<br>" . APP_NAME . " Team</p>
            ";
            
            send_email($user_data['profile']['email'], $subject, $message);
        }
        
        // Log activity
        log_activity('login', 'User logged in from IP: ' . get_client_ip());
        
        flash_message('success', 'Welcome back, ' . $username . '!');
        redirect('../dashboard.php');
    } else {
        flash_message('error', 'Invalid username or password');
        redirect('../index.php');
    }
} else {
    redirect('../index.php');
}
?>
