<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/Database.php';

if (!is_logged_in()) {
    redirect('index.php');
}

// Handle export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    export_statistics_csv($db, $username);
    exit;
}

$db = Database::getInstance();
$username = $_SESSION['username'];
$user_data = $db->getUserData($username);

// Get date ranges
$current_year = date('Y');
$selected_period = isset($_GET['period']) ? sanitize_input($_GET['period']) : 'year';
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : $current_year;

// Validate inputs
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = $current_year;
}

// Get data based on selected period
switch ($selected_period) {
    case 'month':
        $selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
        if ($selected_month < 1 || $selected_month > 12) $selected_month = date('m');
        list($start_date, $end_date) = get_month_range($selected_year, $selected_month);
        $period_label = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
        break;
    case 'quarter':
        $selected_quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : ceil(date('n') / 3);
        if ($selected_quarter < 1 || $selected_quarter > 4) $selected_quarter = 1;
        $quarter_months = [
            1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]
        ];
        $start_date = sprintf('%04d-%02d-01', $selected_year, $quarter_months[$selected_quarter][0]);
        $end_date = sprintf('%04d-%02d-%02d', $selected_year, $quarter_months[$selected_quarter][1], date('t', mktime(0, 0, 0, $quarter_months[$selected_quarter][1], 1, $selected_year)));
        $period_label = "Q{$selected_quarter} {$selected_year}";
        break;
    case 'semester':
        $selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : (date('n') <= 6 ? 1 : 2);
        if ($selected_semester < 1 || $selected_semester > 2) $selected_semester = 1;
        list($start_date, $end_date) = get_semester_range($selected_year, $selected_semester);
        $period_label = "Semester {$selected_semester} {$selected_year}";
        break;
    default: // year
        list($start_date, $end_date) = get_year_range($selected_year);
        $period_label = $selected_year;
        break;
}

// Get transactions
$income = $db->getTransactions($username, 'income', $start_date, $end_date);
$expense = $db->getTransactions($username, 'expense', $start_date, $end_date);
$summary = $db->getFinancialSummary($username, $start_date, $end_date);

// Category breakdown
$income_by_category = [];
$expense_by_category = [];

foreach ($income as $item) {
    $category = $item['category'];
    if (!isset($income_by_category[$category])) {
        $income_by_category[$category] = ['count' => 0, 'total' => 0, 'avg' => 0];
    }
    $income_by_category[$category]['count']++;
    $income_by_category[$category]['total'] += $item['amount'];
}

foreach ($expense as $item) {
    $category = $item['category'];
    if (!isset($expense_by_category[$category])) {
        $expense_by_category[$category] = ['count' => 0, 'total' => 0, 'avg' => 0];
    }
    $expense_by_category[$category]['count']++;
    $expense_by_category[$category]['total'] += $item['amount'];
}

// Calculate averages
foreach ($income_by_category as $category => &$data) {
    $data['avg'] = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
}
foreach ($expense_by_category as $category => &$data) {
    $data['avg'] = $data['count'] > 0 ? $data['total'] / $data['count'] : 0;
}

// Monthly/Quarterly trends for comparison
$comparison_data = [];
if ($selected_period === 'year') {
    for ($month = 1; $month <= 12; $month++) {
        list($month_start, $month_end) = get_month_range($selected_year, $month);
        $month_summary = $db->getFinancialSummary($username, $month_start, $month_end);
        $comparison_data[$month] = [
            'label' => date('M', mktime(0, 0, 0, $month, 1)),
            'income' => $month_summary['total_income'],
            'expense' => $month_summary['total_expense'],
            'balance' => $month_summary['balance']
        ];
    }
} elseif ($selected_period === 'semester') {
    $months = $selected_semester == 1 ? [1, 2, 3, 4, 5, 6] : [7, 8, 9, 10, 11, 12];
    foreach ($months as $month) {
        list($month_start, $month_end) = get_month_range($selected_year, $month);
        $month_summary = $db->getFinancialSummary($username, $month_start, $month_end);
        $comparison_data[$month] = [
            'label' => date('M', mktime(0, 0, 0, $month, 1)),
            'income' => $month_summary['total_income'],
            'expense' => $month_summary['total_expense'],
            'balance' => $month_summary['balance']
        ];
    }
}

// Calculate key metrics
$savings_rate = $summary['total_income'] > 0 ? (($summary['total_income'] - $summary['total_expense']) / $summary['total_income']) * 100 : 0;
$avg_income_transaction = $summary['income_count'] > 0 ? $summary['total_income'] / $summary['income_count'] : 0;
$avg_expense_transaction = $summary['expense_count'] > 0 ? $summary['total_expense'] / $summary['expense_count'] : 0;

// Top categories
uasort($income_by_category, function ($a, $b) {
    return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
});
uasort($expense_by_category, function ($a, $b) {
    return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
});
$top_income_categories = array_slice($income_by_category, 0, 5, true);
$top_expense_categories = array_slice($expense_by_category, 0, 5, true);

$page_title = 'Financial Statistics - ' . APP_NAME;
include 'includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Financial Statistics</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Statistics</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Period Selection -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Select Period</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="period" class="mr-2">Period:</label>
                        <select name="period" id="period" class="form-control" onchange="updatePeriodOptions()">
                            <option value="month" <?php echo $selected_period === 'month' ? 'selected' : ''; ?>>Monthly</option>
                            <option value="quarter" <?php echo $selected_period === 'quarter' ? 'selected' : ''; ?>>Quarterly</option>
                            <option value="semester" <?php echo $selected_period === 'semester' ? 'selected' : ''; ?>>Semester</option>
                            <option value="year" <?php echo $selected_period === 'year' ? 'selected' : ''; ?>>Yearly</option>
                        </select>
                    </div>
                    
                    <div class="form-group mr-2">
                        <label for="year" class="mr-2">Year:</label>
                        <select name="year" id="year" class="form-control">
                            <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                                    <?php echo $y; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mr-2" id="monthGroup" style="display: <?php echo $selected_period === 'month' ? 'block' : 'none'; ?>;">
                        <label for="month" class="mr-2">Month:</label>
                        <select name="month" id="month" class="form-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo ($selected_period === 'month' && $m == ($selected_month ?? date('m'))) ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mr-2" id="quarterGroup" style="display: <?php echo $selected_period === 'quarter' ? 'block' : 'none'; ?>;">
                        <label for="quarter" class="mr-2">Quarter:</label>
                        <select name="quarter" id="quarter" class="form-control">
                            <option value="1" <?php echo ($selected_period === 'quarter' && ($selected_quarter ?? 1) == 1) ? 'selected' : ''; ?>>Q1</option>
                            <option value="2" <?php echo ($selected_period === 'quarter' && ($selected_quarter ?? 1) == 2) ? 'selected' : ''; ?>>Q2</option>
                            <option value="3" <?php echo ($selected_period === 'quarter' && ($selected_quarter ?? 1) == 3) ? 'selected' : ''; ?>>Q3</option>
                            <option value="4" <?php echo ($selected_period === 'quarter' && ($selected_quarter ?? 1) == 4) ? 'selected' : ''; ?>>Q4</option>
                        </select>
                    </div>
                    
                    <div class="form-group mr-2" id="semesterGroup" style="display: <?php echo $selected_period === 'semester' ? 'block' : 'none'; ?>;">
                        <label for="semester" class="mr-2">Semester:</label>
                        <select name="semester" id="semester" class="form-control">
                            <option value="1" <?php echo ($selected_period === 'semester' && ($selected_semester ?? 1) == 1) ? 'selected' : ''; ?>>Semester 1</option>
                            <option value="2" <?php echo ($selected_period === 'semester' && ($selected_semester ?? 1) == 2) ? 'selected' : ''; ?>>Semester 2</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Generate Statistics</button>
                    <button type="button" class="btn btn-success ml-2" onclick="exportStatistics()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </form>
            </div>
        </div>

        <!-- Period Header -->
        <div class="callout callout-info">
            <h5>Financial Statistics: <?php echo $period_label; ?></h5>
            <p>
                <strong>Period:</strong> <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?><br>
                <strong>Net Result:</strong> <?php echo format_currency($summary['balance']); ?> 
                (<?php echo $summary['balance'] >= 0 ? 'Positive' : 'Negative'; ?> Balance)
            </p>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-percentage"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Savings Rate</span>
                        <span class="info-box-number"><?php echo number_format($savings_rate, 1); ?>%</span>
                        <div class="progress">
                            <div class="progress-bar bg-success" style="width: <?php echo min($savings_rate, 100); ?>%"></div>
                        </div>
                        <span class="progress-description">
                            <?php 
                            if ($savings_rate >= 20) echo 'Excellent';
                            elseif ($savings_rate >= 10) echo 'Good';
                            elseif ($savings_rate >= 5) echo 'Fair';
                            else echo 'Needs Improvement';
                            ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-chart-line"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Avg Income/Transaction</span>
                        <span class="info-box-number"><?php echo format_currency($avg_income_transaction); ?></span>
                        <span class="progress-description">
                            From <?php echo $summary['income_count']; ?> transactions
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-shopping-cart"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Avg Expense/Transaction</span>
                        <span class="info-box-number"><?php echo format_currency($avg_expense_transaction); ?></span>
                        <span class="progress-description">
                            From <?php echo $summary['expense_count']; ?> transactions
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-fire"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Burn Rate</span>
                        <span class="info-box-number"><?php echo format_currency($summary['total_expense']); ?></span>
                        <span class="progress-description">
                            Total expenses this period
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income vs Expense Comparison</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container medium">
                            <canvas id="comparisonChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Financial Distribution</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container medium">
                            <canvas id="distributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Trend Chart -->
        <?php if (!empty($comparison_data)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Trend Analysis</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container large">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Category Analysis -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Income Categories</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container small">
                            <canvas id="topIncomeChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                        <th>Total</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_income_categories as $category => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td><?php echo format_currency($data['avg']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Expense Categories</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container small">
                            <canvas id="topExpenseChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Count</th>
                                        <th>Total</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_expense_categories as $category => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td><?php echo format_currency($data['avg']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Insights -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Financial Insights & Recommendations</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($savings_rate < 10): ?>
                            <div class="col-md-4">
                                <div class="alert alert-warning">
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> Low Savings Rate</h5>
                                    <p>Your savings rate is <?php echo number_format($savings_rate, 1); ?>%. Consider reducing expenses or increasing income to achieve at least 10% savings.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($summary['balance'] < 0): ?>
                            <div class="col-md-4">
                                <div class="alert alert-danger">
                                    <h5><i class="icon fas fa-times-circle"></i> Negative Cash Flow</h5>
                                    <p>You're spending more than you earn. Review your budget and identify areas to cut expenses.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($savings_rate >= 20): ?>
                            <div class="col-md-4">
                                <div class="alert alert-success">
                                    <h5><i class="icon fas fa-trophy"></i> Excellent Savings</h5>
                                    <p>Great job! Your savings rate of <?php echo number_format($savings_rate, 1); ?>% is excellent. Consider investment opportunities.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-4">
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-chart-pie"></i> Expense Analysis</h5>
                                    <p>Your top expense category is <?php echo !empty($top_expense_categories) ? key($top_expense_categories) : 'N/A'; ?>. Monitor this category closely.</p>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="alert alert-secondary">
                                    <h5><i class="icon fas fa-calculator"></i> Transaction Patterns</h5>
                                    <p>Average income transaction: <?php echo format_currency($avg_income_transaction); ?><br>
                                    Average expense transaction: <?php echo format_currency($avg_expense_transaction); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
