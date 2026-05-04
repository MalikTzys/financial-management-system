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

// Get selected year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Validate year
if ($selected_year < 2020 || $selected_year > date('Y') + 1) {
    $selected_year = date('Y');
}

// Get date range
list($start_date, $end_date) = get_year_range($selected_year);

// Get transactions
$income = $db->getTransactions($username, 'income', $start_date, $end_date);
$expense = $db->getTransactions($username, 'expense', $start_date, $end_date);

// Get summary
$summary = $db->getFinancialSummary($username, $start_date, $end_date);

// Get monthly breakdown
$monthly_data = [];
for ($month = 1; $month <= 12; $month++) {
    list($month_start, $month_end) = get_month_range($selected_year, $month);
    $month_summary = $db->getFinancialSummary($username, $month_start, $month_end);
    $monthly_data[$month] = [
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

// Calculate averages
$avg_monthly_income = $summary['total_income'] / 12;
$avg_monthly_expense = $summary['total_expense'] / 12;
$savings_rate = $summary['total_income'] > 0 ? (($summary['total_income'] - $summary['total_expense']) / $summary['total_income']) * 100 : 0;

$page_title = 'Yearly Report - ' . APP_NAME;
include '../includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Yearly Financial Report</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Home</a></li>
                    <li class="breadcrumb-item">Reports</li>
                    <li class="breadcrumb-item active">Yearly</li>
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
                <h3 class="card-title">Select Year</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="form-inline">
                    <div class="form-group mr-2">
                        <label for="year" class="mr-2">Year:</label>
                        <select name="year" id="year" class="form-control">
                            <?php for ($y = date('Y') - 10; $y <= date('Y') + 1; $y++): ?>
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
                        <small>Avg: <?php echo round(($summary['income_count'] + $summary['expense_count']) / 12); ?>/month</small>
                    </div>
                    <div class="icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Key Financial Metrics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-chart-line"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Best Month</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $best_month = array_keys($monthly_data, max(array_column($monthly_data, 'balance')))[0];
                                            echo date('F', mktime(0, 0, 0, $best_month, 1, $selected_year));
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-warning"><i class="fas fa-chart-line"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Worst Month</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $worst_month = array_keys($monthly_data, min(array_column($monthly_data, 'balance')))[0];
                                            echo date('F', mktime(0, 0, 0, $worst_month, 1, $selected_year));
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-piggy-bank"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Savings Rate</span>
                                        <span class="info-box-number"><?php echo number_format($savings_rate, 1); ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-danger"><i class="fas fa-fire"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Burn Rate</span>
                                        <span class="info-box-number"><?php echo format_currency($avg_monthly_expense); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trend Chart -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Trend</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 400px; width: 100%;">
                            <canvas id="monthlyTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Analysis -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Top Income Categories</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                            <canvas id="incomeCategoryChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort by total amount descending
                                    uasort($income_by_category, function($a, $b) {
                                        return $b['total'] - $a['total'];
                                    });
                                    $count = 0;
                                    foreach ($income_by_category as $category => $data): 
                                    if ($count >= 5) break;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td><?php echo $summary['total_income'] > 0 ? number_format(($data['total'] / $summary['total_income']) * 100, 1) . '%' : '0%'; ?></td>
                                    </tr>
                                    <?php $count++; endforeach; ?>
                                    <?php if (empty($income_by_category)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No income records</td>
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
                        <h3 class="card-title">Top Expense Categories</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height: 300px; width: 100%;">
                            <canvas id="expenseCategoryChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Total</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Sort by total amount descending
                                    uasort($expense_by_category, function($a, $b) {
                                        return $b['total'] - $a['total'];
                                    });
                                    $count = 0;
                                    foreach ($expense_by_category as $category => $data): 
                                    if ($count >= 5) break;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category); ?></td>
                                        <td><?php echo format_currency($data['total']); ?></td>
                                        <td><?php echo $summary['total_expense'] > 0 ? number_format(($data['total'] / $summary['total_expense']) * 100, 1) . '%' : '0%'; ?></td>
                                    </tr>
                                    <?php $count++; endforeach; ?>
                                    <?php if (empty($expense_by_category)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center">No expense records</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quarterly Analysis -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Quarterly Analysis</h3>
                        <div class="card-tools">
                            <!-- Collapse button temporarily disabled -->
                            <!-- <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php 
                            $quarters = [
                                1 => ['Q1', 1, 3],
                                2 => ['Q2', 4, 6], 
                                3 => ['Q3', 7, 9],
                                4 => ['Q4', 10, 12]
                            ];
                            
                            foreach ($quarters as $q => [$name, $start_month, $end_month]):
                                $quarter_income = 0;
                                $quarter_expense = 0;
                                
                                for ($m = $start_month; $m <= $end_month; $m++) {
                                    $quarter_income += $monthly_data[$m]['income'];
                                    $quarter_expense += $monthly_data[$m]['expense'];
                                }
                                
                                $quarter_balance = $quarter_income - $quarter_expense;
                            ?>
                            <div class="col-md-3">
                                <div class="card <?php echo $quarter_balance >= 0 ? 'bg-success' : 'bg-danger'; ?> text-white">
                                    <div class="card-body">
                                        <h4 class="card-title"><?php echo $name; ?> <?php echo $selected_year; ?></h4>
                                        <p class="card-text">
                                            Income: <?php echo format_currency($quarter_income); ?><br>
                                            Expense: <?php echo format_currency($quarter_expense); ?><br>
                                            <strong>Balance: <?php echo format_currency($quarter_balance); ?></strong>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- jQuery harus dimuat terlebih dahulu -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Monthly Trend Chart
const monthlyCtx = document.getElementById('monthlyTrendChart');
if (monthlyCtx) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const monthlyIncome = <?php echo json_encode(array_column($monthly_data, 'income')); ?>;
    const monthlyExpense = <?php echo json_encode(array_column($monthly_data, 'expense')); ?>;
    const monthlyBalance = <?php echo json_encode(array_column($monthly_data, 'balance')); ?>;

    new Chart(monthlyCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [{
                label: 'Income',
                data: monthlyIncome,
                backgroundColor: 'rgba(40, 167, 69, 0.6)',
                borderColor: '#28a745',
                borderWidth: 1
            }, {
                label: 'Expense',
                data: monthlyExpense,
                backgroundColor: 'rgba(220, 53, 69, 0.6)',
                borderColor: '#dc3545',
                borderWidth: 1
            }, {
                label: 'Balance',
                data: monthlyBalance,
                type: 'line',
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 0,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            },
            animation: {
                duration: 0 // Disable animation to prevent height issues
            }
        }
    });
}

// Income Category Chart
const incomeCatCtx = document.getElementById('incomeCategoryChart');
if (incomeCatCtx) {
    // Sort categories by total amount descending and take top 5
    const incomeData = <?php echo json_encode($income_by_category); ?>;
    let incomeLabels = [];
    let incomeValues = [];
    
    if (incomeData && Object.keys(incomeData).length > 0) {
        const sortedIncome = Object.entries(incomeData)
            .sort((a, b) => b[1].total - a[1].total)
            .slice(0, 5);
        incomeLabels = sortedIncome.map(item => item[0]);
        incomeValues = sortedIncome.map(item => item[1].total);
    }

    new Chart(incomeCatCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: incomeLabels.length > 0 ? incomeLabels : ['No Data'],
            datasets: [{
                data: incomeValues.length > 0 ? incomeValues : [1],
                backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#6f42c1', '#fd7e14']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 0,
            layout: {
                padding: {
                    top: 10,
                    bottom: 10,
                    left: 10,
                    right: 10
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    display: incomeLabels.length > 0,
                    labels: {
                        padding: 15,
                        boxWidth: 12,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + formatCurrency(context.raw);
                        }
                    }
                }
            },
            animation: {
                duration: 0 // Disable animation to prevent height issues
            }
        }
    });
}

// Expense Category Chart
const expenseCatCtx = document.getElementById('expenseCategoryChart');
if (expenseCatCtx) {
    // Sort categories by total amount descending and take top 5
    const expenseData = <?php echo json_encode($expense_by_category); ?>;
    let expenseLabels = [];
    let expenseValues = [];
    
    if (expenseData && Object.keys(expenseData).length > 0) {
        const sortedExpense = Object.entries(expenseData)
            .sort((a, b) => b[1].total - a[1].total)
            .slice(0, 5);
        expenseLabels = sortedExpense.map(item => item[0]);
        expenseValues = sortedExpense.map(item => item[1].total);
    }

    new Chart(expenseCatCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: expenseLabels.length > 0 ? expenseLabels : ['No Data'],
            datasets: [{
                data: expenseValues.length > 0 ? expenseValues : [1],
                backgroundColor: ['#dc3545', '#fd7e14', '#ffc107', '#28a745', '#17a2b8']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 0,
            layout: {
                padding: {
                    top: 10,
                    bottom: 10,
                    left: 10,
                    right: 10
                }
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    display: expenseLabels.length > 0,
                    labels: {
                        padding: 15,
                        boxWidth: 12,
                        font: {
                            size: 11
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.label + ': ' + formatCurrency(context.raw);
                        }
                    }
                }
            },
            animation: {
                duration: 0 // Disable animation to prevent height issues
            }
        }
    });
}

function exportReport() {
    alert('PDF export functionality will be implemented');
}

function printReport() {
    window.print();
}

// Initialize charts when DOM is ready - Vanilla JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Prevent multiple chart initializations
    if (window.chartsInitialized) {
        return;
    }
    window.chartsInitialized = true;
    
    // Small delay to ensure DOM is fully ready
    setTimeout(function() {
        console.log('Charts initialization started');
    }, 100);
});
</script>

<?php include '../includes/footer.php'; ?>
