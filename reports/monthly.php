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

// Get selected month/year
$selected_month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate month/year
if ($selected_month < 1 || $selected_month > 12) {
    $selected_month = date('m');
}
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = date('Y');
}

// Get date range
list($start_date, $end_date) = get_month_range($selected_year, $selected_month);

// Get transactions
$income = $db->getTransactions($username, 'income', $start_date, $end_date);
$expense = $db->getTransactions($username, 'expense', $start_date, $end_date);

// Get summary
$summary = $db->getFinancialSummary($username, $start_date, $end_date);

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

// Get daily breakdown
$daily_income = [];
$daily_expense = [];
$days_in_month = date('t', mktime(0, 0, 0, $selected_month, 1, $selected_year));

for ($day = 1; $day <= $days_in_month; $day++) {
    $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
    $daily_income[$day] = 0;
    $daily_expense[$day] = 0;
}

foreach ($income as $item) {
    $day = intval(date('d', strtotime($item['date'])));
    $daily_income[$day] += $item['amount'];
}

foreach ($expense as $item) {
    $day = intval(date('d', strtotime($item['date'])));
    $daily_expense[$day] += $item['amount'];
}

$page_title = 'Monthly Report - ' . APP_NAME;
include '../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Monthly Financial Report</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Home</a></li>
                    <li class="breadcrumb-item">Reports</li>
                    <li class="breadcrumb-item active">Monthly</li>
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
                        <label for="month" class="mr-2">Month:</label>
                        <select name="month" id="month" class="form-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?php echo $m; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                                </option>
                            <?php endfor; ?>
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

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo format_currency($summary['total_income']); ?></h3>
                        <p>Total Income</p>
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
                    </div>
                    <div class="icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Daily Trend</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyTrendChart" style="height: 400px;"></canvas>
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
                        <canvas id="pieChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income by Category</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Transactions</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($income_by_category as $category => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = $summary['total_income'] > 0 ? ($data['total'] / $summary['total_income']) * 100 : 0;
                                            echo number_format($percentage, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($income_by_category)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No income records</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Expense by Category</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Transactions</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expense_by_category as $category => $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo $data['count']; ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td>
                                            <?php 
                                            $percentage = $summary['total_expense'] > 0 ? ($data['total'] / $summary['total_expense']) * 100 : 0;
                                            echo number_format($percentage, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($expense_by_category)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No expense records</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Transactions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">All Transactions</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $all_transactions = array_merge(
                                        array_map(function($item) { return array_merge($item, ['type' => 'Income']); }, $income),
                                        array_map(function($item) { return array_merge($item, ['type' => 'Expense']); }, $expense)
                                    );
                                    usort($all_transactions, function($a, $b) { return strtotime($b['date']) - strtotime($a['date']); });
                                    
                                    foreach ($all_transactions as $transaction): 
                                    ?>
                                    <tr>
                                        <td><?php echo format_date($transaction['date']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $transaction['type'] === 'Income' ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $transaction['type']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description'] ?: '-'); ?></td>
                                        <td class="<?php echo $transaction['type'] === 'Income' ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo ($transaction['type'] === 'Income' ? '+' : '-') . format_currency($transaction['amount']); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($all_transactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No transactions found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<?php include '../includes/footer.php'; ?>
