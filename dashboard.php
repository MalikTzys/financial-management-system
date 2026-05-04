<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/Database.php';

if (!is_logged_in()) {
    redirect('index.php');
}

$db = Database::getInstance();
$username = $_SESSION['username'];
$user_data = $db->getUserData($username);

$current_month = date('m');
$current_year = date('Y');
list($start_date, $end_date) = get_month_range($current_year, $current_month);
$monthly_summary = $db->getFinancialSummary($username, $start_date, $end_date);

list($year_start, $year_end) = get_year_range($current_year);
$yearly_summary = $db->getFinancialSummary($username, $year_start, $year_end);

$recent_income = array_slice($db->getTransactions($username, 'income'), -5);
$recent_expense = array_slice($db->getTransactions($username, 'expense'), -5);

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

$page_title = 'Dashboard - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Dashboard</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Dashboard</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Flash Messages -->
        <?php if (has_flash_message('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo get_flash_message('success'); ?>
            </div>
        <?php endif; ?>
        
        <?php if (has_flash_message('error')): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo get_flash_message('error'); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-3 col-6">
                <!-- small box -->
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3 id="total-income"><?php echo format_currency($yearly_summary['total_income']); ?></h3>
                        <p>Total Income (<?php echo $current_year; ?>)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <a href="income.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3 id="total-expense"><?php echo format_currency($yearly_summary['total_expense']); ?></h3>
                        <p>Total Expense (<?php echo $current_year; ?>)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <a href="expense.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box <?php echo $yearly_summary['balance'] >= 0 ? 'bg-success' : 'bg-warning'; ?>">
                    <div class="inner">
                        <h3 id="balance"><?php echo format_currency($yearly_summary['balance']); ?></h3>
                        <p>Balance (<?php echo $current_year; ?>)</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <a href="statistics.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-secondary">
                    <div class="inner">
                        <h3><?php echo $yearly_summary['income_count'] + $yearly_summary['expense_count']; ?></h3>
                        <p>Total Transactions</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <a href="reports/monthly.php" class="small-box-footer">More info <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income by Category (<?php echo $current_year; ?>)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="incomeChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Expense by Category (<?php echo $current_year; ?>)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="expenseChart" style="height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Income</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table m-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($recent_income) as $income): ?>
                                    <tr>
                                        <td><?php echo format_date($income['date']); ?></td>
                                        <td><?php echo htmlspecialchars($income['category']); ?></td>
                                        <td class="text-success">+<?php echo format_currency($income['amount']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewTransaction('income', '<?php echo $income['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_income)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No income records found</td>
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
                        <h3 class="card-title">Recent Expense</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table m-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_reverse($recent_expense) as $expense): ?>
                                    <tr>
                                        <td><?php echo format_date($expense['date']); ?></td>
                                        <td><?php echo htmlspecialchars($expense['category']); ?></td>
                                        <td class="text-danger">-<?php echo format_currency($expense['amount']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="viewTransaction('expense', '<?php echo $expense['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($recent_expense)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No expense records found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Trend (<?php echo $current_year; ?>)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" style="height: 400px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<?php include 'includes/footer.php'; ?>
