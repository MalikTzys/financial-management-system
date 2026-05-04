<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';

if (!is_logged_in()) {
    redirect('../index.php');
}

$db = Database::getInstance();
$username = $_SESSION['username'];
$user_data = $db->getUserData($username);

// Get selected year and semester
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$selected_semester = isset($_GET['semester']) ? intval($_GET['semester']) : (date('n') <= 6 ? 1 : 2);

// Validate inputs
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = date('Y');
}
if ($selected_semester < 1 || $selected_semester > 2) {
    $selected_semester = 1;
}

// Get date range
list($start_date, $end_date) = get_semester_range($selected_year, $selected_semester);

// Get transactions
$income = $db->getTransactions($username, 'income', $start_date, $end_date);
$expense = $db->getTransactions($username, 'expense', $start_date, $end_date);

// Get summary
$summary = $db->getFinancialSummary($username, $start_date, $end_date);

// Get monthly breakdown for the semester
$semester_months = $selected_semester == 1 ? [1, 2, 3, 4, 5, 6] : [7, 8, 9, 10, 11, 12];
$monthly_data = [];

foreach ($semester_months as $month) {
    list($month_start, $month_end) = get_month_range($selected_year, $month);
    $month_summary = $db->getFinancialSummary($username, $month_start, $month_end);
    $monthly_data[$month] = [
        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
        'income' => $month_summary['total_income'],
        'expense' => $month_summary['total_expense'],
        'balance' => $month_summary['balance']
    ];
}

// Get category breakdown
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

// Calculate metrics
$avg_monthly_income = $summary['total_income'] / 6;
$avg_monthly_expense = $summary['total_expense'] / 6;
$savings_rate = $summary['total_income'] > 0 ? (($summary['total_income'] - $summary['total_expense']) / $summary['total_income']) * 100 : 0;

// Find best and worst months
$best_month_data = $monthly_data[array_keys($monthly_data, max(array_column($monthly_data, 'balance')))[0]];
$worst_month_data = $monthly_data[array_keys($monthly_data, min(array_column($monthly_data, 'balance')))[0]];

$page_title = 'Semester Report - ' . APP_NAME;
include '../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Semester Financial Report</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Home</a></li>
                    <li class="breadcrumb-item">Reports</li>
                    <li class="breadcrumb-item active">Semester</li>
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
                <h3 class="card-title">Select Semester</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
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
                    <div class="form-group mr-2">
                        <label for="semester" class="mr-2">Semester:</label>
                        <select name="semester" id="semester" class="form-control">
                            <option value="1" <?php echo $selected_semester == 1 ? 'selected' : ''; ?>>Semester 1 (Jan-Jun)</option>
                            <option value="2" <?php echo $selected_semester == 2 ? 'selected' : ''; ?>>Semester 2 (Jul-Dec)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                    <button type="button" class="btn btn-success ml-2" onclick="exportReport()">
                        <i class="fas fa-download"></i> Export PDF
                    </button>
                    <button type="button" class="btn btn-info ml-2" onclick="printReport()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </form>
            </div>
        </div>

        <!-- Semester Header -->
        <div class="callout <?php echo $summary['balance'] >= 0 ? 'callout-success' : 'callout-danger'; ?>">
            <h5>Semester <?php echo $selected_semester; ?> - <?php echo $selected_year; ?></h5>
            <p>
                <strong>Period:</strong> <?php echo format_date($start_date); ?> to <?php echo format_date($end_date); ?><br>
                <strong>Net Result:</strong> <?php echo format_currency($summary['balance']); ?> 
                (<?php echo $summary['balance'] >= 0 ? 'Positive' : 'Negative'; ?> Balance)
            </p>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo format_currency($summary['total_income']); ?></h3>
                        <p>Total Income</p>
                        <small>Avg: <?php echo format_currency($avg_monthly_income); ?>/month</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?php echo format_currency($summary['total_expense']); ?></h3>
                        <p>Total Expense</p>
                        <small>Avg: <?php echo format_currency($avg_monthly_expense); ?>/month</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box <?php echo $summary['balance'] >= 0 ? 'bg-info' : 'bg-warning'; ?>">
                    <div class="inner">
                        <h3><?php echo format_currency($summary['balance']); ?></h3>
                        <p>Net Balance</p>
                        <small>Rate: <?php echo number_format($savings_rate, 1); ?>% savings</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo $summary['income_count'] + $summary['expense_count']; ?></h3>
                        <p>Transactions</p>
                        <small>Avg: <?php echo round(($summary['income_count'] + $summary['expense_count']) / 6); ?>/month</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Insights -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Performance</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Income</th>
                                        <th>Expense</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_data as $month => $data): ?>
                                    <tr>
                                        <td><?php echo $data['month_name']; ?></td>
                                        <td class="text-success"><?php echo format_currency($data['income']); ?></td>
                                        <td class="text-danger"><?php echo format_currency($data['expense']); ?></td>
                                        <td class="<?php echo $data['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo format_currency($data['balance']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $data['balance'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $data['balance'] >= 0 ? 'Positive' : 'Negative'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td>Total</td>
                                        <td class="text-success"><?php echo format_currency($summary['total_income']); ?></td>
                                        <td class="text-danger"><?php echo format_currency($summary['total_expense']); ?></td>
                                        <td class="<?php echo $summary['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo format_currency($summary['balance']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $summary['balance'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo $summary['balance'] >= 0 ? 'Positive' : 'Negative'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Semester Insights</h3>
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <span class="info-box-icon bg-info"><i class="fas fa-trophy"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Best Month</span>
                                <span class="info-box-number"><?php echo $best_month_data['month_name']; ?></span>
                                <span class="progress-description">
                                    Balance: <?php echo format_currency($best_month_data['balance']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-box">
                            <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Challenging Month</span>
                                <span class="info-box-number"><?php echo $worst_month_data['month_name']; ?></span>
                                <span class="progress-description">
                                    Balance: <?php echo format_currency($worst_month_data['balance']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="info-box">
                            <span class="info-box-icon bg-success"><i class="fas fa-chart-line"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Savings Rate</span>
                                <span class="info-box-number"><?php echo number_format($savings_rate, 1); ?>%</span>
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
                        <div class="info-box">
                            <span class="info-box-icon bg-danger"><i class="fas fa-fire"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Monthly Burn Rate</span>
                                <span class="info-box-number"><?php echo format_currency($avg_monthly_expense); ?></span>
                                <span class="progress-description">
                                    Average monthly expenses
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Trend Analysis</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="monthlyTrendChart" style="height: 400px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income vs Expense</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="comparisonChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Analysis -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income Categories</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="incomeCategoryChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Expense Categories</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="expenseCategoryChart" style="height: 250px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recommendations -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Financial Recommendations</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($savings_rate < 10): ?>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <h5><i class="icon fas fa-exclamation-triangle"></i> Increase Savings Rate</h5>
                                    <p>Your current savings rate is <?php echo number_format($savings_rate, 1); ?>%. Consider reducing expenses or increasing income to achieve at least 10% savings rate.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($summary['balance'] < 0): ?>
                            <div class="col-md-6">
                                <div class="alert alert-danger">
                                    <h5><i class="icon fas fa-times-circle"></i> Negative Balance</h5>
                                    <p>You have a negative balance this semester. Review your expenses and create a budget to improve your financial situation.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($savings_rate >= 20): ?>
                            <div class="col-md-6">
                                <div class="alert alert-success">
                                    <h5><i class="icon fas fa-check-circle"></i> Excellent Savings</h5>
                                    <p>Great job! Your savings rate of <?php echo number_format($savings_rate, 1); ?>% is excellent. Consider investing your surplus for better returns.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h5><i class="icon fas fa-lightbulb"></i> Budget Planning</h5>
                                    <p>Based on your average monthly expense of <?php echo format_currency($avg_monthly_expense); ?>, consider setting a monthly budget to better control your spending.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include '../includes/footer.php'; ?>
