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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('settings.php');
    }
    
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $gender = sanitize_input($_POST['gender'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    
    if (empty($email)) {
        flash_message('error', 'Email is required');
        redirect('settings.php');
    }
    
    if (!validate_email($email)) {
        flash_message('error', 'Invalid email address');
        redirect('settings.php');
    }
    
    if ($email !== $user_data['profile']['email']) {
        if ($db->emailExists($email)) {
            flash_message('error', 'Email already exists');
            redirect('settings.php');
        }
    }
    
    $user_data['profile']['full_name'] = $full_name;
    $user_data['profile']['email'] = $email;
    $user_data['profile']['phone'] = $phone;
    $user_data['profile']['gender'] = $gender;
    $user_data['profile']['description'] = $description;
    
    if ($db->saveUserData($username, $user_data)) {
        $_SESSION['email'] = $email;
        
        log_activity('update_profile', 'Updated profile information');
        flash_message('success', 'Profile updated successfully!');
    } else {
        flash_message('error', 'Failed to update profile');
    }
    
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('settings.php');
    }
    
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_result = upload_file($_FILES['profile_photo'], UPLOAD_PATH);
        
        if ($upload_result['success']) {
            if (!empty($user_data['profile']['profile_photo'])) {
                $old_photo_path = UPLOAD_PATH . '/' . basename($user_data['profile']['profile_photo']);
                delete_file($old_photo_path);
            }
            
            $user_data['profile']['profile_photo'] = 'uploads/' . $upload_result['filename'];
            
            if ($db->saveUserData($username, $user_data)) {
                $_SESSION['profile_photo'] = 'uploads/' . $upload_result['filename'];
                log_activity('upload_photo', 'Updated profile photo');
                flash_message('success', 'Profile photo updated successfully!');
            } else {
                flash_message('error', 'Failed to update profile photo');
            }
        } else {
            flash_message('error', $upload_result['error']);
        }
    } else {
        flash_message('error', 'Please select a photo to upload');
    }
    
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('settings.php');
    }
    
    $currency = sanitize_input($_POST['currency'] ?? 'IDR');
    $language = sanitize_input($_POST['language'] ?? 'id');
    $email_login = isset($_POST['email_login']);
    $email_transaction = isset($_POST['email_transaction']);
    $email_report = isset($_POST['email_report']);
    
    $user_data['settings']['currency'] = $currency;
    $user_data['settings']['language'] = $language;
    $user_data['settings']['notifications']['email_login'] = $email_login;
    $user_data['settings']['notifications']['email_transaction'] = $email_transaction;
    $user_data['settings']['notifications']['email_report'] = $email_report;
    
    if ($db->saveUserData($username, $user_data)) {
        log_activity('update_settings', 'Updated account settings');
        flash_message('success', 'Settings updated successfully!');
    } else {
        flash_message('error', 'Failed to update settings');
    }
    
    redirect('settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Invalid CSRF token');
        redirect('settings.php');
    }
    
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        flash_message('error', 'All password fields are required');
        redirect('settings.php');
    }
    
    if (!password_verify($current_password, $user_data['profile']['password'])) {
        flash_message('error', 'Current password is incorrect');
        redirect('settings.php');
    }
    
    if (strlen($new_password) < 8) {
        flash_message('error', 'New password must be at least 8 characters');
        redirect('settings.php');
    }
    
    if ($new_password !== $confirm_password) {
        flash_message('error', 'New passwords do not match');
        redirect('settings.php');
    }
    
    if (!preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/', $new_password)) {
        flash_message('error', 'New password must contain uppercase, lowercase, and number');
        redirect('settings.php');
    }
    
    $user_data['profile']['password'] = password_hash($new_password, PASSWORD_DEFAULT);
    
    if ($db->saveUserData($username, $user_data)) {
        log_activity('change_password', 'Password changed successfully');
        flash_message('success', 'Password changed successfully!');
        
        $subject = 'Password Changed - ' . APP_NAME;
        $message = "
            <h2>Password Changed</h2>
            <p>Dear {$username},</p>
            <p>Your password has been successfully changed on " . date('Y-m-d H:i:s') . ".</p>
            <p>If you didn't make this change, please contact support immediately.</p>
            <p>Best regards,<br>" . APP_NAME . " Team</p>
        ";
        send_email($user_data['profile']['email'], $subject, $message);
    } else {
        flash_message('error', 'Failed to change password');
    }
    
    redirect('settings.php');
}

$page_title = 'Settings - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Settings</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
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
            <div class="col-md-6">
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Profile Information</h3>
                    </div>
                    <form action="settings.php" method="post" id="profileForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <input type="hidden" name="action" value="update_profile">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($username); ?>" readonly>
                                <small class="form-text text-muted">Username cannot be changed</small>
                            </div>
                            <div class="form-group">
                                <label for="full_name">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_data['profile']['full_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_data['profile']['email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user_data['profile']['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select class="form-control" id="gender" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($user_data['profile']['gender'] ?? '') === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($user_data['profile']['gender'] ?? '') === 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo ($user_data['profile']['gender'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($user_data['profile']['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Account Type</label>
                                <input type="text" class="form-control" value="<?php echo ucfirst($user_data['profile']['role']); ?>" readonly>
                                <small class="form-text text-muted">Account type cannot be changed</small>
                            </div>
                            <div class="form-group">
                                <label>Member Since</label>
                                <input type="text" class="form-control" value="<?php echo format_date($user_data['profile']['created_at']); ?>" readonly>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">Profile Photo</h3>
                    </div>
                    <form action="settings.php" method="post" enctype="multipart/form-data" id="photoForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <input type="hidden" name="action" value="upload_photo">
                        <div class="card-body">
                            <div class="text-center">
                                <img src="<?php echo !empty($user_data['profile']['profile_photo']) ? $user_data['profile']['profile_photo'] : 'assets/img/user-icon.jpg'; ?>" 
                                     class="img-circle img-thumbnail" alt="Profile Photo" style="width: 150px; height: 150px; object-fit: cover;">
                            </div>
                            <div class="form-group mt-3">
                                <label for="profile_photo">Upload New Photo</label>
                                <div class="input-group">
                                    <div class="custom-file">
                                        <input type="file" class="custom-file-input" id="profile_photo" name="profile_photo" accept="image/*">
                                        <label class="custom-file-label" for="profile_photo">Choose file</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Allowed formats: JPG, PNG, GIF. Max size: 5MB</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-info">Upload Photo</button>
                            <?php if (!empty($user_data['profile']['profile_photo'])): ?>
                                <button type="button" class="btn btn-danger" onclick="removePhoto()">Remove Photo</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <div class="card card-warning">
                    <div class="card-header">
                        <h3 class="card-title">Change Password</h3>
                    </div>
                    <form action="settings.php" method="post" id="passwordForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <input type="hidden" name="action" value="change_password">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="current_password">Current Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}">
                                <small class="form-text text-muted">Min 8 characters with uppercase, lowercase, and number</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="form-group">
                                <div class="icheck-primary">
                                    <input type="checkbox" id="show_password">
                                    <label for="show_password">Show passwords</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card card-secondary">
                    <div class="card-header">
                        <h3 class="card-title">Account Settings</h3>
                    </div>
                    <form action="settings.php" method="post" id="settingsForm">
                        <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                        <input type="hidden" name="action" value="update_settings">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="currency">Currency</label>
                                        <select class="form-control" id="currency" name="currency">
                                            <option value="IDR" <?php echo ($user_data['settings']['currency'] ?? 'IDR') === 'IDR' ? 'selected' : ''; ?>>Indonesian Rupiah (IDR)</option>
                                            <option value="USD" <?php echo ($user_data['settings']['currency'] ?? 'IDR') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                            <option value="EUR" <?php echo ($user_data['settings']['currency'] ?? 'IDR') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="language">Language</label>
                                        <select class="form-control" id="language" name="language">
                                            <option value="id" <?php echo ($user_data['settings']['language'] ?? 'id') === 'id' ? 'selected' : ''; ?>>Bahasa Indonesia</option>
                                            <option value="en" <?php echo ($user_data['settings']['language'] ?? 'id') === 'en' ? 'selected' : ''; ?>>English</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            <h5>Email Notifications</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="icheck-primary">
                                            <input type="checkbox" id="email_login" name="email_login" <?php echo ($user_data['settings']['notifications']['email_login'] ?? true) ? 'checked' : ''; ?>>
                                            <label for="email_login">Login Notifications</label>
                                        </div>
                                        <small class="form-text text-muted">Receive email when someone logs into your account</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="icheck-primary">
                                            <input type="checkbox" id="email_transaction" name="email_transaction" <?php echo ($user_data['settings']['notifications']['email_transaction'] ?? true) ? 'checked' : ''; ?>>
                                            <label for="email_transaction">Transaction Notifications</label>
                                        </div>
                                        <small class="form-text text-muted">Receive email for new income/expense transactions</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <div class="icheck-primary">
                                            <input type="checkbox" id="email_report" name="email_report" <?php echo ($user_data['settings']['notifications']['email_report'] ?? false) ? 'checked' : ''; ?>>
                                            <label for="email_report">Monthly Reports</label>
                                        </div>
                                        <small class="form-text text-muted">Receive monthly financial reports via email</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-secondary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Account Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-calendar"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Account Age</span>
                                        <span class="info-box-number">
                                            <?php 
                                            $created = new DateTime($user_data['profile']['created_at']);
                                            $now = new DateTime();
                                            $interval = $created->diff($now);
                                            echo $interval->y . ' years, ' . $interval->m . ' months';
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-success"><i class="fas fa-arrow-up"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Income Records</span>
                                        <span class="info-box-number"><?php echo count($user_data['finance']['income'] ?? []); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-danger"><i class="fas fa-arrow-down"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Expense Records</span>
                                        <span class="info-box-number"><?php echo count($user_data['finance']['expense'] ?? []); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="info-box">
                                    <span class="info-box-icon bg-warning"><i class="fas fa-users"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Members (if group)</span>
                                        <span class="info-box-number"><?php echo count($user_data['members'] ?? []); ?></span>
                                    </div>
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
