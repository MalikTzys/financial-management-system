<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
require_once 'includes/Database.php';

if (!is_logged_in()) {
    redirect('index.php');
}

if ($_SESSION['role'] !== 'group') {
    flash_message('error', 'This feature is only available for group accounts');
    redirect('dashboard.php');
}

$db = Database::getInstance();
$username = $_SESSION['username'];
$user_data = $db->getUserData($username);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('members.php');
    }
    
    $member_name = sanitize_input($_POST['member_name'] ?? '');
    $member_email = sanitize_input($_POST['member_email'] ?? '');
    $member_phone = sanitize_input($_POST['member_phone'] ?? '');
    $member_role = sanitize_input($_POST['member_role'] ?? 'member');
    $monthly_fee = floatval($_POST['monthly_fee'] ?? 0);
    $join_date = sanitize_input($_POST['join_date'] ?? date('Y-m-d'));
    
    if (empty($member_name) || empty($member_email)) {
        flash_message('error', 'Member name and email are required');
        redirect('members.php');
    }
    
    if (!validate_email($member_email)) {
        flash_message('error', 'Invalid email address');
        redirect('members.php');
    }
    
    foreach ($user_data['members'] as $member) {
        if ($member['email'] === $member_email) {
            flash_message('error', 'A member with this email already exists');
            redirect('members.php');
        }
    }
    
    $new_member = [
        'id' => uniqid(),
        'name' => $member_name,
        'email' => $member_email,
        'phone' => $member_phone,
        'role' => $member_role,
        'monthly_fee' => $monthly_fee,
        'join_date' => $join_date,
        'status' => 'active',
        'balance' => 0,
        'payments' => [],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $user_data['members'][] = $new_member;
    
    if ($db->saveUserData($username, $user_data)) {
        log_activity('add_member', "Added member: {$member_name}");
        flash_message('success', 'Member added successfully!');
        
        $subject = 'Welcome to ' . $user_data['profile']['username'] . ' Group';
        $message = "
            <h2>Welcome to the Group!</h2>
            <p>Dear {$member_name},</p>
            <p>You have been added as a member of {$user_data['profile']['username']} group.</p>
            <p><strong>Group Details:</strong></p>
            <ul>
                <li>Role: {$member_role}</li>
                <li>Monthly Fee: " . format_currency($monthly_fee) . "</li>
                <li>Join Date: " . format_date($join_date) . "</li>
            </ul>
            <p>You will receive payment reminders and updates from the group.</p>
            <p>Best regards,<br>{$user_data['profile']['username']} Admin</p>
        ";
        
        send_email($member_email, $subject, $message);
    } else {
        flash_message('error', 'Failed to add member');
    }
    
    redirect('members.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_payment') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('members.php');
    }
    
    $member_id = sanitize_input($_POST['member_id'] ?? '');
    $payment_amount = floatval($_POST['payment_amount'] ?? 0);
    $payment_date = sanitize_input($_POST['payment_date'] ?? date('Y-m-d'));
    $payment_method = sanitize_input($_POST['payment_method'] ?? 'cash');
    $payment_note = sanitize_input($_POST['payment_note'] ?? '');
    
    if (empty($member_id) || $payment_amount <= 0) {
        flash_message('error', 'Invalid payment details');
        redirect('members.php');
    }
    
    foreach ($user_data['members'] as &$member) {
        if ($member['id'] === $member_id) {
            $payment = [
                'id' => uniqid(),
                'amount' => $payment_amount,
                'date' => $payment_date,
                'method' => $payment_method,
                'note' => $payment_note,
                'recorded_by' => $_SESSION['username'],
                'recorded_at' => date('Y-m-d H:i:s')
            ];
            
            $member['payments'][] = $payment;
            $member['balance'] += $payment_amount;
            
            $transaction_data = [
                'date' => $payment_date,
                'amount' => $payment_amount,
                'category' => 'Member Fees',
                'description' => "Payment from {$member['name']} - {$payment_note}"
            ];
            
            $db->addTransaction($username, 'income', $transaction_data);
            
            log_activity('record_payment', "Recorded payment from {$member['name']}: " . format_currency($payment_amount));
            flash_message('success', 'Payment recorded successfully!');
            break;
        }
    }
    
    if ($db->saveUserData($username, $user_data)) {
        foreach ($user_data['members'] as $member) {
            if ($member['id'] === $member_id) {
                $subject = 'Payment Confirmation - ' . $user_data['profile']['username'];
                $message = "
                    <h2>Payment Confirmation</h2>
                    <p>Dear {$member['name']},</p>
                    <p>Your payment has been recorded:</p>
                    <ul>
                        <li>Amount: " . format_currency($payment_amount) . "</li>
                        <li>Date: " . format_date($payment_date) . "</li>
                        <li>Method: {$payment_method}</li>
                        <li>Note: {$payment_note}</li>
                    </ul>
                    <p>Current Balance: " . format_currency($member['balance']) . "</p>
                    <p>Thank you for your payment!</p>
                    <p>Best regards,<br>{$user_data['profile']['username']} Admin</p>
                ";
                
                send_email($member['email'], $subject, $message);
                break;
            }
        }
    } else {
        flash_message('error', 'Failed to record payment');
    }
    
    redirect('members.php');
}

if (isset($_GET['delete'])) {
    $member_id = sanitize_input($_GET['delete']);
    
    foreach ($user_data['members'] as $key => $member) {
        if ($member['id'] === $member_id) {
            unset($user_data['members'][$key]);
            $user_data['members'] = array_values($user_data['members']);
            
            if ($db->saveUserData($username, $user_data)) {
                log_activity('delete_member', "Deleted member: {$member['name']}");
                flash_message('success', 'Member deleted successfully!');
            } else {
                flash_message('error', 'Failed to delete member');
            }
            break;
        }
    }
    
    redirect('members.php');
}

$members = $user_data['members'] ?? [];
$total_members = count($members);
$active_members = count(array_filter($members, function($m) { return $m['status'] === 'active'; }));
$total_balance = array_sum(array_column($members, 'balance'));

$current_month = date('Y-m');
$current_month_payments = 0;
$members_with_payments = 0;

foreach ($members as $member) {
    $member_payments = array_filter($member['payments'], function($p) use ($current_month) {
        return strpos($p['date'], $current_month) === 0;
    });
    
    if (!empty($member_payments)) {
        $members_with_payments++;
        $current_month_payments += array_sum(array_column($member_payments, 'amount'));
    }
}

$page_title = 'Member Status - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Member Status</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Member Status</li>
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
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $total_members; ?></h3>
                        <p>Total Members</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $active_members; ?></h3>
                        <p>Active Members</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $total_members - $members_with_payments; ?></h3>
                        <p>Pending Payments</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo format_currency($current_month_payments); ?></h3>
                        <p>This Month Collections</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-money-bill"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Add New Member</h3>
                    </div>
                    <form action="members.php" method="post" id="addMemberForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <input type="hidden" name="action" value="add_member">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="member_name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="member_name" name="member_name" required>
                            </div>
                            <div class="form-group">
                                <label for="member_email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="member_email" name="member_email" required>
                            </div>
                            <div class="form-group">
                                <label for="member_phone">Phone</label>
                                <input type="tel" class="form-control" id="member_phone" name="member_phone">
                            </div>
                            <div class="form-group">
                                <label for="member_role">Role</label>
                                <select class="form-control" id="member_role" name="member_role">
                                    <option value="member">Member</option>
                                    <option value="admin">Admin</option>
                                    <option value="treasurer">Treasurer</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="monthly_fee">Monthly Fee</label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Rp</span>
                                    </div>
                                    <input type="number" class="form-control" id="monthly_fee" name="monthly_fee" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="join_date">Join Date</label>
                                <input type="date" class="form-control" id="join_date" name="join_date" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Add Member</button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Members List</h3>
                        <div class="card-tools">
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm" onclick="exportMembers()">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="printMembers()">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button type="button" class="btn btn-success btn-sm" onclick="sendPaymentReminders()">
                                    <i class="fas fa-envelope"></i> Send Reminders
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" id="searchInput" placeholder="Search members...">
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" id="paymentFilter">
                                    <option value="">All Members</option>
                                    <option value="paid">Paid This Month</option>
                                    <option value="unpaid">Unpaid This Month</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-default" onclick="resetFilters()">Reset</button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-striped" id="membersTable">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Monthly Fee</th>
                                        <th>Balance</th>
                                        <th>Payment Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($members as $member): 
                                    $has_paid_this_month = false;
                                    foreach ($member['payments'] as $payment) {
                                        if (strpos($payment['date'], $current_month) === 0) {
                                            $has_paid_this_month = true;
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr data-name="<?php echo htmlspecialchars($member['name']); ?>" data-email="<?php echo htmlspecialchars($member['email']); ?>" data-status="<?php echo $member['status']; ?>" data-payment="<?php echo $has_paid_this_month ? 'paid' : 'unpaid'; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($member['name']); ?></strong><br>
                                            <small class="text-muted">Joined: <?php echo format_date($member['join_date']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($member['email']); ?></td>
                                        <td>
                                            <span class="badge badge-info"><?php echo ucfirst($member['role']); ?></span>
                                        </td>
                                        <td><?php echo format_currency($member['monthly_fee']); ?></td>
                                        <td class="<?php echo $member['balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            <?php echo format_currency($member['balance']); ?>
                                        </td>
                                        <td>
                                            <?php if ($has_paid_this_month): ?>
                                                <span class="badge badge-success">Paid</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success" onclick="recordPayment('<?php echo $member['id']; ?>', '<?php echo htmlspecialchars($member['name']); ?>')">
                                                <i class="fas fa-money-bill"></i>
                                            </button>
                                            <button class="btn btn-sm btn-info" onclick="viewMember('<?php echo $member['id']; ?>')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-warning" onclick="editMember('<?php echo $member['id']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteMember('<?php echo $member['id']; ?>', '<?php echo htmlspecialchars($member['name']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($members)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No members found</td>
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

<div class="modal fade" id="paymentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Record Payment</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="members.php" method="post" id="paymentForm">
                <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                <input type="hidden" name="action" value="record_payment">
                <input type="hidden" name="member_id" id="payment_member_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Member</label>
                        <input type="text" class="form-control" id="payment_member_name" readonly>
                    </div>
                    <div class="form-group">
                        <label for="payment_amount">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Rp</span>
                            </div>
                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="payment_date">Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method">
                            <option value="cash">Cash</option>
                            <option value="transfer">Bank Transfer</option>
                            <option value="digital">Digital Wallet</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_note">Note</label>
                        <textarea class="form-control" id="payment_note" name="payment_note" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>


<?php include 'includes/footer.php'; ?>
