<?php
function format_currency($amount, $currency = 'IDR') {
    if ($amount === null || $amount === '') {
        $amount = 0;
    }
    
    $symbols = [
        'IDR' => 'Rp',
        'USD' => '$',
        'EUR' => '€'
    ];
    
    $symbol = $symbols[$currency] ?? $currency;
    
    if ($currency === 'IDR') {
        return $symbol . number_format($amount, 0, ',', '.');
    }
    
    return $symbol . number_format($amount, 2);
}

function format_date($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

function get_month_range($year, $month) {
    $start_date = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
    $end_date = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
    return [$start_date, $end_date];
}

function get_year_range($year) {
    return [$year . '-01-01', $year . '-12-31'];
}

function get_semester_range($year, $semester) {
    if ($semester == 1) {
        return [$year . '-01-01', $year . '-06-30'];
    } else {
        return [$year . '-07-01', $year . '-12-31'];
    }
}

function validate_date($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generate_password_reset_token() {
    return bin2hex(random_bytes(32));
}

function get_client_ip() {
    $ipaddress = '';
    
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    return $ipaddress;
}

function is_ajax_request() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function json_response($data, $status_code = 200) {
    header('Content-Type: application/json');
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function flash_message($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

function get_flash_message($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function has_flash_message($type) {
    return isset($_SESSION['flash'][$type]);
}

function upload_file($file, $upload_dir, $allowed_types = ['jpg', 'jpeg', 'png', 'gif']) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return ['success' => false, 'error' => 'File size too large'];
    }
    
    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
    
    return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
}

function delete_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

function paginate($array, $page = 1, $per_page = 10) {
    $total = count($array);
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    
    return [
        'data' => array_slice($array, $offset, $per_page),
        'current_page' => $page,
        'per_page' => $per_page,
        'total' => $total,
        'total_pages' => $total_pages
    ];
}

function export_to_csv($data, $filename, $headers = []) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($headers)) {
        fputcsv($output, $headers);
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function calculate_age($birth_date) {
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    return $birth->diff($today)->y;
}

function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M d, Y', $time);
    }
}

function export_statistics_csv($db, $username) {
    // Get parameters
    $selected_period = isset($_GET['period']) ? sanitize_input($_GET['period']) : 'year';
    $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Get date ranges
    switch ($selected_period) {
        case 'month':
            $selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
            list($start_date, $end_date) = get_month_range($selected_year, $selected_month);
            $period_label = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
            break;
        case 'quarter':
            $selected_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 1;
            $quarter_months = [1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]];
            $start_date = sprintf('%04d-%02d-01', $selected_year, $quarter_months[$selected_quarter][0]);
            $end_date = sprintf('%04d-%02d-%02d', $selected_year, $quarter_months[$selected_quarter][1], date('t', mktime(0, 0, 0, $quarter_months[$selected_quarter][1], 1, $selected_year)));
            $period_label = "Q{$selected_quarter} {$selected_year}";
            break;
        case 'semester':
            $selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : 1;
            list($start_date, $end_date) = get_semester_range($selected_year, $selected_semester);
            $period_label = "Semester {$selected_semester} {$selected_year}";
            break;
        default:
            list($start_date, $end_date) = get_year_range($selected_year);
            $period_label = $selected_year;
            break;
    }
    
    // Get data
    $income = $db->getTransactions($username, 'income', $start_date, $end_date);
    $expense = $db->getTransactions($username, 'expense', $start_date, $end_date);
    $summary = $db->getFinancialSummary($username, $start_date, $end_date);
    
    // Calculate statistics
    $savings_rate = $summary['total_income'] > 0 ? (($summary['total_income'] - $summary['total_expense']) / $summary['total_income']) * 100 : 0;
    $avg_income_transaction = $summary['income_count'] > 0 ? $summary['total_income'] / $summary['income_count'] : 0;
    $avg_expense_transaction = $summary['expense_count'] > 0 ? $summary['total_expense'] / $summary['expense_count'] : 0;
    
    // Category breakdown
    $income_by_category = [];
    $expense_by_category = [];
    
    foreach ($income as $item) {
        $category = $item['category'];
        if (!isset($income_by_category[$category])) {
            $income_by_category[$category] = ['count' => 0, 'total' => 0];
        }
        $income_by_category[$category]['count']++;
        $income_by_category[$category]['total'] += $item['amount'];
    }
    
    foreach ($expense as $item) {
        $category = $item['category'];
        if (!isset($expense_by_category[$category])) {
            $expense_by_category[$category] = ['count' => 0, 'total' => 0];
        }
        $expense_by_category[$category]['count']++;
        $expense_by_category[$category]['total'] += $item['amount'];
    }
    
    // Set headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="statistics_' . $period_label . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding
    fwrite($output, "\xEF\xBB\xBF");
    
    // Summary section
    fputcsv($output, ['Financial Statistics Report']);
    fputcsv($output, ['Period:', $period_label]);
    fputcsv($output, ['Date Range:', format_date($start_date) . ' to ' . format_date($end_date)]);
    fputcsv($output, []);
    
    // Key metrics
    fputcsv($output, ['Key Metrics']);
    fputcsv($output, ['Total Income', format_currency($summary['total_income'])]);
    fputcsv($output, ['Total Expense', format_currency($summary['total_expense'])]);
    fputcsv($output, ['Net Balance', format_currency($summary['balance'])]);
    fputcsv($output, ['Savings Rate', number_format($savings_rate, 1) . '%']);
    fputcsv($output, ['Average Income Transaction', format_currency($avg_income_transaction)]);
    fputcsv($output, ['Average Expense Transaction', format_currency($avg_expense_transaction)]);
    fputcsv($output, []);
    
    // Income categories
    fputcsv($output, ['Income by Category']);
    fputcsv($output, ['Category', 'Count', 'Total', 'Average']);
    uasort($income_by_category, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    foreach ($income_by_category as $category => $data) {
        fputcsv($output, [
            $category,
            $data['count'],
            format_currency($data['total']),
            format_currency($data['count'] > 0 ? $data['total'] / $data['count'] : 0)
        ]);
    }
    fputcsv($output, []);
    
    // Expense categories
    fputcsv($output, ['Expense by Category']);
    fputcsv($output, ['Category', 'Count', 'Total', 'Average']);
    uasort($expense_by_category, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    foreach ($expense_by_category as $category => $data) {
        fputcsv($output, [
            $category,
            $data['count'],
            format_currency($data['total']),
            format_currency($data['count'] > 0 ? $data['total'] / $data['count'] : 0)
        ]);
    }
    fputcsv($output, []);
    
    // Individual transactions
    fputcsv($output, ['Income Transactions']);
    fputcsv($output, ['Date', 'Category', 'Description', 'Amount']);
    foreach ($income as $item) {
        fputcsv($output, [
            $item['date'],
            $item['category'],
            $item['description'],
            format_currency($item['amount'])
        ]);
    }
    fputcsv($output, []);
    
    fputcsv($output, ['Expense Transactions']);
    fputcsv($output, ['Date', 'Category', 'Description', 'Amount']);
    foreach ($expense as $item) {
        fputcsv($output, [
            $item['date'],
            $item['category'],
            $item['description'],
            format_currency($item['amount'])
        ]);
    }
    
    fclose($output);
    exit;
}

?>
