<?php

// Security Configuration
define('ENCRYPTION_KEY', 'your-32-character-encryption-key-here');
define('HASH_ALGORITHM', 'sha256');
define('SESSION_NAME', 'SESSION');

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_name(SESSION_NAME);
    session_start();
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://code.jquery.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src \'self\' https://cdn.jsdelivr.net https://fonts.gstatic.com; img-src \'self\' data:; connect-src \'self\' https://cdn.jsdelivr.net;');

// Configuration
define('APP_NAME', 'Financial Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/fnc/');
define('DATA_PATH', __DIR__ . '/../user/data/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('SESSION_LIFETIME', 2592000); // 1 month in seconds

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', APP_NAME);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Helper Functions
function encrypt_data($data) {
    $key = base64_encode(ENCRYPTION_KEY);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_data($encrypted_data) {
    $key = base64_encode(ENCRYPTION_KEY);
    $data = base64_decode($encrypted_data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function is_logged_in() {
    return isset($_SESSION['username']) && !empty($_SESSION['username']);
}

function get_user_data() {
    if (!is_logged_in()) {
        return null;
    }
    
    $username = $_SESSION['username'];
    $data_file = DATA_PATH . $username . '/data.json';
    
    if (file_exists($data_file)) {
        $json_data = file_get_contents($data_file);
        return json_decode($json_data, true);
    }
    
    return null;
}

function save_user_data($data) {
    if (!is_logged_in()) {
        return false;
    }
    
    $username = $_SESSION['username'];
    $user_dir = DATA_PATH . $username;
    
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    $data_file = $user_dir . '/data.json';
    $json_data = json_encode($data, JSON_PRETTY_PRINT);
    
    return file_put_contents($data_file, $json_data, LOCK_EX) !== false;
}

function send_email($to, $subject, $message) {
    require_once __DIR__ . '/../includes/PHPMailer.php';
    require_once __DIR__ . '/../includes/SMTP.php';
    require_once __DIR__ . '/../includes/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

function log_activity($action, $details = '') {
    if (!is_logged_in()) {
        return;
    }
    
    $username = $_SESSION['username'];
    $log_file = DATA_PATH . $username . '/activity.log';
    
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'action' => $action,
        'details' => $details
    ];
    
    $log_line = json_encode($log_entry) . "\n";
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
}
?>
