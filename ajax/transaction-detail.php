<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['success' => false, 'message' => 'Method not allowed'], 405);
}

$type = sanitize_input($_GET['type'] ?? '');
$id = sanitize_input($_GET['id'] ?? '');

if (!in_array($type, ['income', 'expense'], true) || $id === '') {
    json_response(['success' => false, 'message' => 'Invalid parameters'], 422);
}

$db = Database::getInstance();
$username = $_SESSION['username'];
$transaction = $db->getTransactionById($username, $type, $id);

if (!$transaction) {
    json_response(['success' => false, 'message' => 'Transaction not found'], 404);
}

json_response([
    'success' => true,
    'data' => [
        'id' => $transaction['id'],
        'type' => $type,
        'date' => format_date($transaction['date']),
        'category' => $transaction['category'],
        'amount' => format_currency($transaction['amount']),
        'description' => $transaction['description'] ?: '-',
        'created_at' => format_date($transaction['created_at'] ?? $transaction['date'], 'd M Y H:i')
    ]
]);
