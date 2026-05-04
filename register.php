<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$page_title = 'Register - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="hold-transition register-page">
    <div class="register-box">
        <div class="register-logo">
            <a href="#"><b>FNC</b> Financial</a>
        </div>
        <div class="card">
            <div class="card-body register-card-body">
                <p class="login-box-msg">Register a new membership</p>
                
                <?php if (has_flash_message('error')): ?>
                    <div class="alert alert-danger">
                        <?php echo get_flash_message('error'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (has_flash_message('success')): ?>
                    <div class="alert alert-success">
                        <?php echo get_flash_message('success'); ?>
                    </div>
                <?php endif; ?>
                
                <form action="auth/register.php" method="post">
                    <?php echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">'; ?>
                    
                    <div class="input-group mb-3">
                        <input type="text" name="username" class="form-control" placeholder="Username" required pattern="[a-zA-Z0-9_]{3,20}" title="Username should be 3-20 characters, letters, numbers, and underscores only">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Email" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-envelope"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Password must be at least 8 characters with uppercase, lowercase, and number">
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="confirm_password" class="form-control" placeholder="Retype password" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Account Type</label>
                        <div class="icheck-primary">
                            <input type="radio" id="individual" name="role" value="individual" checked>
                            <label for="individual">Individual</label>
                        </div>
                        <div class="icheck-primary">
                            <input type="radio" id="group" name="role" value="group">
                            <label for="group">Group/Organization</label>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-8">
                            <div class="icheck-primary">
                                <input type="checkbox" id="agreeTerms" name="terms" required>
                                <label for="agreeTerms">
                                    I agree to the <a href="#">terms</a>
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary btn-block">Register</button>
                        </div>
                    </div>
                </form>
                
                <p class="mb-0">
                    <a href="index.php" class="text-center">I already have a membership</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
