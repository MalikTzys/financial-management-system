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

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $db->getTransactions($username, 'expense');
    $export_rows = [];
    foreach ($rows as $row) {
        $export_rows[] = [
            $row['id'],
            $row['date'],
            $row['category'],
            $row['description'] ?: '-',
            number_format((float) $row['amount'], 2, '.', '')
        ];
    }
    export_to_csv($export_rows, 'expense-' . date('Ymd-His') . '.csv', ['ID', 'Date', 'Category', 'Description', 'Amount']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('expense.php');
    }
    
    $action = sanitize_input($_POST['action'] ?? 'add');
    $transaction_id = sanitize_input($_POST['transaction_id'] ?? '');
    $date = sanitize_input($_POST['date'] ?? date('Y-m-d'));
    $amount = floatval($_POST['amount'] ?? 0);
    $category = sanitize_input($_POST['category'] ?? '');
    $new_category = sanitize_input($_POST['newCategory'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');

    if ($category === 'other' && $new_category !== '') {
        $category = $new_category;
    }
    
    if (empty($date) || $amount <= 0 || empty($category)) {
        flash_message('error', 'Please fill all required fields with valid values');
        redirect('expense.php');
    }
    
    if (!validate_date($date)) {
        flash_message('error', 'Invalid date format');
        redirect('expense.php');
    }
    
    $transaction_data = [
        'date' => $date,
        'amount' => $amount,
        'category' => $category,
        'description' => $description
    ];
    
    if ($action === 'edit' && $transaction_id !== '') {
        $saved = $db->updateTransaction($username, 'expense', $transaction_id, $transaction_data);
        if ($saved) {
            $db->addCategory($username, 'expense', $category);
            log_activity('edit_expense', "Edited expense: {$transaction_id}");
            flash_message('success', 'Expense updated successfully!');
        } else {
            flash_message('error', 'Failed to update expense');
        }
    } else {
        if ($db->addTransaction($username, 'expense', $transaction_data)) {
            $db->addCategory($username, 'expense', $category);
            log_activity('add_expense', "Added expense: {$category} - " . format_currency($amount));
            flash_message('success', 'Expense added successfully!');
            if ($user_data['settings']['notifications']['email_transaction'] ?? true) {
                $subject = 'New Expense Added - ' . APP_NAME;
                $message = "
                    <h2>New Expense Added</h2>
                    <p>Dear {$username},</p>
                    <p>A new expense has been added to your account:</p>
                    <ul>
                        <li>Date: " . format_date($date) . "</li>
                        <li>Category: {$category}</li>
                        <li>Amount: " . format_currency($amount) . "</li>
                        <li>Description: {$description}</li>
                    </ul>
                    <p>Best regards,<br>" . APP_NAME . " Team</p>
                ";
                send_email($user_data['profile']['email'], $subject, $message);
            }
        } else {
            flash_message('error', 'Failed to add expense');
        }
    }
    
    redirect('expense.php');
}

if (isset($_GET['delete'])) {
    $transaction_id = sanitize_input($_GET['delete']);
    
    if ($db->deleteTransaction($username, 'expense', $transaction_id)) {
        log_activity('delete_expense', "Deleted expense transaction: {$transaction_id}");
        flash_message('success', 'Expense deleted successfully!');
    } else {
        flash_message('error', 'Failed to delete expense');
    }
    
    redirect('expense.php');
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$all_expense = $db->getTransactions($username, 'expense');
$paginated_expense = paginate($all_expense, $page, $per_page);

$categories = $user_data['finance']['categories']['expense'] ?? [];

list($start_date, $end_date) = get_month_range(date('Y'), date('m'));
$monthly_summary = $db->getFinancialSummary($username, $start_date, $end_date);

$page_title = 'Kelola Pengeluaran - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Kelola Pengeluaran</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Kelola Pengeluaran</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
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

        <?php if ($monthly_summary['total_expense'] > $monthly_summary['total_income']): ?>
        <div class="alert alert-warning alert-dismissible">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <h5><i class="icon fas fa-exclamation-triangle"></i> Warning!</h5>
            Your expenses this month exceed your income. Current balance: <strong><?php echo format_currency($monthly_summary['balance']); ?></strong>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">Add New Expense</h3>
                    </div>
                    <form action="expense.php" method="post" id="expenseForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="date">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="amount">Amount <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="category">Category <span class="text-danger">*</span></label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group" id="newCategoryGroup" style="display: none;">
                                <label for="newCategory">New Category</label>
                                <input type="text" class="form-control" id="newCategory" name="newCategory" placeholder="Enter new category">
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <div class="icheck-primary">
                                    <input type="checkbox" id="budgetAlert" name="budgetAlert">
                                    <label for="budgetAlert">This is a budget-critical expense</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-danger">Add Expense</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
                
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">This Month Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <p class="text-center">
                                    <small>Income</small><br>
                                    <strong class="text-success"><?php echo format_currency($monthly_summary['total_income']); ?></strong>
                                </p>
                            </div>
                            <div class="col-6">
                                <p class="text-center">
                                    <small>Expense</small><br>
                                    <strong class="text-danger"><?php echo format_currency($monthly_summary['total_expense']); ?></strong>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <p class="text-center">
                            <small>Balance</small><br>
                            <strong class="<?php echo $monthly_summary['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <?php echo format_currency($monthly_summary['balance']); ?>
                            </strong>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Expense Records</h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm" onclick="exportExpense()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="printExpense()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="showBudgetAnalysis()">
                                    <i class="fas fa-chart-pie"></i> Budget Analysis
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search...">
                            </div>
                            <div class="col-md-3">
                                <input type="month" class="form-control" id="monthFilter">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="amountFilter">
                                    <option value="">All Amounts</option>
                                    <option value="0-100000">Below Rp 100K</option>
                                    <option value="100000-500000">Rp 100K - 500K</option>
                                    <option value="500000-1000000">Rp 500K - 1M</option>
                                    <option value="1000000+">Above Rp 1M</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped" id="expenseTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_expense['data'] as $expense): ?>
                                    <tr data-date="<?php echo $expense['date']; ?>" data-category="<?php echo htmlspecialchars($expense['category']); ?>" data-amount="<?php echo $expense['amount']; ?>" data-id="<?php echo htmlspecialchars($expense['id']); ?>" data-description="<?php echo htmlspecialchars($expense['description'] ?: ''); ?>">
                                        <td><?php echo format_date($expense['date']); ?></td>
                                        <td>
                                            <span class="badge badge-danger"><?php echo htmlspecialchars($expense['category']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($expense['description'] ?: '-'); ?></td>
                                        <td class="text-danger font-weight-bold"><?php echo format_currency($expense['amount']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewTransaction('expense', '<?php echo $expense['id']; ?>')" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="editExpense('<?php echo $expense['id']; ?>')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteExpense('<?php echo $expense['id']; ?>')" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="duplicateExpense('<?php echo $expense['id']; ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($paginated_expense['data'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No expense records found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold">
                                        <td colspan="3">Total</td>
                                        <td class="text-danger" id="totalExpense"><?php echo format_currency(array_sum(array_column($paginated_expense['data'], 'amount'))); ?></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <?php if ($paginated_expense['total_pages'] > 1): ?>
                        <div class="d-flex justify-content-between">
                            <div>
                                Showing <?php echo ($paginated_expense['current_page'] - 1) * $paginated_expense['per_page'] + 1; ?> 
                                to <?php echo min($paginated_expense['current_page'] * $paginated_expense['per_page'], $paginated_expense['total']); ?> 
                                of <?php echo $paginated_expense['total']; ?> entries
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm">
                                    <?php if ($paginated_expense['current_page'] > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $paginated_expense['current_page'] - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $paginated_expense['total_pages']; $i++): ?>
                                        <li class="page-item <?php echo $i == $paginated_expense['current_page'] ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($paginated_expense['current_page'] < $paginated_expense['total_pages']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $paginated_expense['current_page'] + 1; ?>">Next</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="editExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" id="editExpenseForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="transaction_id" id="edit_expense_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pengeluaran</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" class="form-control" name="date" id="edit_expense_date" required>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" class="form-control" name="amount" id="edit_expense_amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" class="form-control" name="category" id="edit_expense_category" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea class="form-control" name="description" id="edit_expense_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>
