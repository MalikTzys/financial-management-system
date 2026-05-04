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
    $rows = $db->getTransactions($username, 'income');
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
    export_to_csv($export_rows, 'income-' . date('Ymd-His') . '.csv', ['ID', 'Date', 'Category', 'Description', 'Amount']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('income.php');
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
        redirect('income.php');
    }
    
    if (!validate_date($date)) {
        flash_message('error', 'Invalid date format');
        redirect('income.php');
    }
    
    $transaction_data = [
        'date' => $date,
        'amount' => $amount,
        'category' => $category,
        'description' => $description
    ];
    
    if ($action === 'edit' && $transaction_id !== '') {
        $saved = $db->updateTransaction($username, 'income', $transaction_id, $transaction_data);
        if ($saved) {
            $db->addCategory($username, 'income', $category);
            log_activity('edit_income', "Edited income: {$transaction_id}");
            flash_message('success', 'Income updated successfully!');
        } else {
            flash_message('error', 'Failed to update income');
        }
    } else {
        if ($db->addTransaction($username, 'income', $transaction_data)) {
            $db->addCategory($username, 'income', $category);
            log_activity('add_income', "Added income: {$category} - " . format_currency($amount));
            flash_message('success', 'Income added successfully!');

            if ($user_data['settings']['notifications']['email_transaction'] ?? true) {
                $subject = 'New Income Added - ' . APP_NAME;
                $message = "
                    <h2>New Income Added</h2>
                    <p>Dear {$username},</p>
                    <p>A new income has been added to your account:</p>
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
            flash_message('error', 'Failed to add income');
        }
    }
    
    redirect('income.php');
}

if (isset($_GET['delete'])) {
    $transaction_id = sanitize_input($_GET['delete']);
    
    if ($db->deleteTransaction($username, 'income', $transaction_id)) {
        log_activity('delete_income', "Deleted income transaction: {$transaction_id}");
        flash_message('success', 'Income deleted successfully!');
    } else {
        flash_message('error', 'Failed to delete income');
    }
    
    redirect('income.php');
}

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$all_income = $db->getTransactions($username, 'income');
$paginated_income = paginate($all_income, $page, $per_page);

$categories = $user_data['finance']['categories']['income'] ?? [];

$page_title = 'Kelola Pemasukan - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Kelola Pemasukan</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Kelola Pemasukan</li>
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

        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Add New Income</h3>
                    </div>
                    <form action="income.php" method="post" id="incomeForm">
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
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Add Income</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income Records</h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm" onclick="exportIncome()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="printIncome()">
                                    <i class="fas fa-print"></i> Print
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
                                <button class="btn btn-default" onclick="resetFilters()">Reset</button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped" id="incomeTable">
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
                                    <?php foreach ($paginated_income['data'] as $income): ?>
                                    <tr data-date="<?php echo $income['date']; ?>" data-category="<?php echo htmlspecialchars($income['category']); ?>" data-id="<?php echo htmlspecialchars($income['id']); ?>" data-description="<?php echo htmlspecialchars($income['description'] ?: ''); ?>" data-amount="<?php echo htmlspecialchars($income['amount']); ?>">
                                        <td><?php echo format_date($income['date']); ?></td>
                                        <td>
                                            <span class="badge badge-success"><?php echo htmlspecialchars($income['category']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($income['description'] ?: '-'); ?></td>
                                        <td class="text-success font-weight-bold"><?php echo format_currency($income['amount']); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="viewTransaction('income', '<?php echo $income['id']; ?>')" title="Lihat Detail">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="editIncome('<?php echo $income['id']; ?>')" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteIncome('<?php echo $income['id']; ?>')" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($paginated_income['data'])): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No income records found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($paginated_income['total_pages'] > 1): ?>
                        <div class="d-flex justify-content-between">
                            <div>
                                Showing <?php echo ($paginated_income['current_page'] - 1) * $paginated_income['per_page'] + 1; ?> 
                                to <?php echo min($paginated_income['current_page'] * $paginated_income['per_page'], $paginated_income['total']); ?> 
                                of <?php echo $paginated_income['total']; ?> entries
                            </div>
                            <nav>
                                <ul class="pagination pagination-sm">
                                    <?php if ($paginated_income['current_page'] > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $paginated_income['current_page'] - 1; ?>">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $paginated_income['total_pages']; $i++): ?>
                                        <li class="page-item <?php echo $i == $paginated_income['current_page'] ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($paginated_income['current_page'] < $paginated_income['total_pages']): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $paginated_income['current_page'] + 1; ?>">Next</a>
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

<div class="modal fade" id="editIncomeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="post" id="editIncomeForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="transaction_id" id="edit_income_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pemasukan</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="date" class="form-control" name="date" id="edit_income_date" required>
                    </div>
                    <div class="form-group">
                        <label>Nominal</label>
                        <input type="number" class="form-control" name="amount" id="edit_income_amount" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Kategori</label>
                        <input type="text" class="form-control" name="category" id="edit_income_category" required>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <textarea class="form-control" name="description" id="edit_income_description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>
