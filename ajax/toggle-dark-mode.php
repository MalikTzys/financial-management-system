<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Only allow AJAX requests
if (!is_ajax_request()) {
    http_response_code(403);
    exit('Forbidden');
}

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    json_response(['error' => 'Unauthorized'], 401);
}

// Verify CSRF token
$csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    http_response_code(403);
    json_response(['error' => 'Invalid CSRF token'], 403);
}

// Toggle dark mode preference
if (!isset($_SESSION['dark_mode'])) {
    $_SESSION['dark_mode'] = false;
}

$_SESSION['dark_mode'] = !$_SESSION['dark_mode'];

$response = [
    'success' => true,
    'dark_mode' => $_SESSION['dark_mode'],
    'message' => $_SESSION['dark_mode'] ? 'Dark mode enabled' : 'Dark mode disabled'
];

json_response($response);
?>
