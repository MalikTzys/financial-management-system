<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

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

$db = Database::getInstance();
$username = $_SESSION['username'];
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate year
if ($year < 2020 || $year > date('Y') + 1) {
    $year = date('Y');
}

// Get monthly data for the entire year
$monthly_income = [];
$monthly_expense = [];

for ($month = 1; $month <= 12; $month++) {
    list($month_start, $month_end) = get_month_range($year, $month);
    $month_summary = $db->getFinancialSummary($username, $month_start, $month_end);
    
    // Ensure we have valid data
    $monthly_income[] = $month_summary['total_income'] ?? 0;
    $monthly_expense[] = $month_summary['total_expense'] ?? 0;
}

$response = [
    'success' => true,
    'data' => [
        'year' => $year,
        'income' => $monthly_income,
        'expense' => $monthly_expense
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

json_response($response);
?>
