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

// Get current month and year
$current_month = date('m');
$current_year = date('Y');

// Get current month summary
list($start_date, $end_date) = get_month_range($current_year, $current_month);
$monthly_summary = $db->getFinancialSummary($username, $start_date, $end_date);

// Get current year summary
list($year_start, $year_end) = get_year_range($current_year);
$yearly_summary = $db->getFinancialSummary($username, $year_start, $year_end);

// Get recent transactions
$recent_income = array_slice($db->getTransactions($username, 'income'), -3);
$recent_expense = array_slice($db->getTransactions($username, 'expense'), -3);

// Get category data for charts
$all_income = $db->getTransactions($username, 'income', $year_start, $year_end);
$all_expense = $db->getTransactions($username, 'expense', $year_start, $year_end);

$income_by_category = [];
$expense_by_category = [];

foreach ($all_income as $income) {
    $category = $income['category'];
    if (!isset($income_by_category[$category])) {
        $income_by_category[$category] = 0;
    }
    $income_by_category[$category] += $income['amount'];
}

foreach ($all_expense as $expense) {
    $category = $expense['category'];
    if (!isset($expense_by_category[$category])) {
        $expense_by_category[$category] = 0;
    }
    $expense_by_category[$category] += $expense['amount'];
}

// Prepare response with error handling
$response = [
    'success' => true,
    'data' => [
        'summary' => [
            'monthly' => $monthly_summary ?: [
                'total_income' => 0,
                'total_expense' => 0,
                'balance' => 0,
                'income_count' => 0,
                'expense_count' => 0
            ],
            'yearly' => $yearly_summary ?: [
                'total_income' => 0,
                'total_expense' => 0,
                'balance' => 0,
                'income_count' => 0,
                'expense_count' => 0
            ]
        ],
        'recent_transactions' => [
            'income' => array_map(function($item) {
                return [
                    'id' => $item['id'] ?? '',
                    'date' => format_date($item['date'] ?? ''),
                    'category' => $item['category'] ?? 'Unknown',
                    'amount' => format_currency($item['amount'] ?? 0),
                    'description' => $item['description'] ?: '-'
                ];
            }, array_reverse($recent_income ?: [])),
            'expense' => array_map(function($item) {
                return [
                    'id' => $item['id'] ?? '',
                    'date' => format_date($item['date'] ?? ''),
                    'category' => $item['category'] ?? 'Unknown',
                    'amount' => format_currency($item['amount'] ?? 0),
                    'description' => $item['description'] ?: '-'
                ];
            }, array_reverse($recent_expense ?: []))
        ],
        'charts' => [
            'income_by_category' => $income_by_category ?: [],
            'expense_by_category' => $expense_by_category ?: []
        ]
    ],
    'timestamp' => date('Y-m-d H:i:s')
];

json_response($response);
?>
