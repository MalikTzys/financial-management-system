<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title ?? APP_NAME; ?></title>
    
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <?php 
$base_path = (strpos($_SERVER['PHP_SELF'], '/reports/') !== false) ? '../' : '';
?>
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/custom.css">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
</head>
<body data-base-path="<?php echo $base_path; ?>" class="<?php echo isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark-mode' : ''; ?>">
<?php if (!is_logged_in()): ?>
<?php else: ?>
<?php
if (!isset($db)) {
    require_once __DIR__ . '/Database.php';
    $db = Database::getInstance();
}
if (!isset($username)) {
    $username = $_SESSION['username'] ?? '';
}
$header_user_data = $db->getUserData($username);
$header_notifications = [];
if ($header_user_data) {
    $income_items = array_slice($header_user_data['finance']['income'] ?? [], -2);
    $expense_items = array_slice($header_user_data['finance']['expense'] ?? [], -2);

    foreach (array_reverse($income_items) as $item) {
        $header_notifications[] = [
            'icon' => 'fas fa-arrow-up text-success',
            'text' => 'Pemasukan ' . format_currency($item['amount']) . ' (' . htmlspecialchars($item['category']) . ')',
            'time' => time_ago($item['created_at'] ?? date('Y-m-d H:i:s'))
        ];
    }
    foreach (array_reverse($expense_items) as $item) {
        $header_notifications[] = [
            'icon' => 'fas fa-arrow-down text-danger',
            'text' => 'Pengeluaran ' . format_currency($item['amount']) . ' (' . htmlspecialchars($item['category']) . ')',
            'time' => time_ago($item['created_at'] ?? date('Y-m-d H:i:s'))
        ];
    }

    $current_month_summary = $db->getFinancialSummary($username, date('Y-m-01'), date('Y-m-t'));
    if (($current_month_summary['balance'] ?? 0) < 0) {
        $header_notifications[] = [
            'icon' => 'fas fa-exclamation-triangle text-warning',
            'text' => 'Saldo bulan ini minus: ' . format_currency($current_month_summary['balance']),
            'time' => 'sekarang'
        ];
    }
}
$header_notifications = array_slice($header_notifications, 0, 5);
?>
<div class="wrapper">
    <div class="preloader flex-column justify-content-center align-items-center">
        <img class="animation__shake" src="<?php echo $base_path; ?>assets/img/user-icon.jpg" alt="Loading..." height="60" width="60">
    </div>

    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo $base_path; ?>dashboard.php" class="nav-link">Home</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge"><?php echo count($header_notifications); ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header"><?php echo count($header_notifications); ?> Notifikasi</span>
                    <?php if (!empty($header_notifications)): ?>
                        <?php foreach ($header_notifications as $notification): ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo $base_path; ?>dashboard.php" class="dropdown-item">
                                <i class="<?php echo $notification['icon']; ?> mr-2"></i> <?php echo $notification['text']; ?>
                                <span class="float-right text-muted text-sm"><?php echo $notification['time']; ?></span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="dropdown-divider"></div>
                        <span class="dropdown-item text-muted">Belum ada notifikasi.</span>
                    <?php endif; ?>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-widget="fullscreen" href="#" role="button">
                    <i class="fas fa-expand-arrows-alt"></i>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#" onclick="toggleDarkMode()" role="button">
                    <i class="fas fa-moon"></i>
                </a>
            </li>
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="<?php echo $_SESSION['profile_photo'] ?? $base_path . 'assets/img/user-icon.jpg'; ?>" class="user-image img-circle elevation-2" alt="User Image">
                    <span class="d-none d-md-inline"><?php echo $_SESSION['username']; ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header bg-primary">
                        <img src="<?php echo $_SESSION['profile_photo'] ?? $base_path . 'assets/img/user-icon.jpg'; ?>" class="img-circle elevation-2" alt="User Image">
                        <p>
                            <?php echo $_SESSION['username']; ?>
                            <small>Member since <?php echo format_date($_SESSION['created_at'] ?? date('Y-m-d')); ?></small>
                        </p>
                    </li>
                    <li class="user-body">
                        <div class="row">
                            <div class="col-4 text-center">
                                <a href="#">Followers</a>
                            </div>
                            <div class="col-4 text-center">
                                <a href="#">Sales</a>
                            </div>
                            <div class="col-4 text-center">
                                <a href="#">Friends</a>
                            </div>
                        </div>
                    </li>
                    <li class="user-footer">
                        <a href="<?php echo $base_path; ?>settings.php" class="btn btn-default btn-flat">Profile</a>
                        <a href="<?php echo $base_path; ?>auth/logout.php" class="btn btn-default btn-flat float-right">Sign out</a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?php echo $base_path; ?>dashboard.php" class="brand-link">
            <span class="brand-text font-weight-light">FNC Financial</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="<?php echo $_SESSION['profile_photo'] ?? $base_path . 'assets/img/user-icon.jpg'; ?>" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="<?php echo $base_path; ?>settings.php" class="d-block text-white"><?php echo $_SESSION['username']; ?></a>
                </div>
            </div>

            <div class="form-inline">
                <div class="input-group" data-widget="sidebar-search">
                    <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                    <div class="input-group-append">
                        <button class="btn btn-sidebar">
                            <i class="fas fa-search fa-fw"></i>
                        </button>
                    </div>
                </div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>income.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'income.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-plus-circle text-success"></i>
                            <p>Kelola Pemasukan</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>expense.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expense.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-minus-circle text-danger"></i>
                            <p>Kelola Pengeluaran</p>
                        </a>
                    </li>
                    
                    <li class="nav-item <?php echo (strpos($_SERVER['PHP_SELF'], 'reports/') !== false) ? 'menu-open' : ''; ?>">
                        <a href="#" class="nav-link <?php echo (strpos($_SERVER['PHP_SELF'], 'reports/') !== false) ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-file-alt"></i>
                            <p>
                                Laporan Keuangan
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="<?php echo $base_path; ?>reports/monthly.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'monthly.php' ? 'active' : ''; ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laporan Bulanan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $base_path; ?>reports/yearly.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'yearly.php' ? 'active' : ''; ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laporan Tahunan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="<?php echo $base_path; ?>reports/semester.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'semester.php' ? 'active' : ''; ?>">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Laporan Semester</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <?php if ($_SESSION['role'] === 'group'): ?>
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>members.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Status Anggota</p>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>statistics.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-pie"></i>
                            <p>Statistik Keuangan</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>categories.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tags"></i>
                            <p>Kelola Kategori</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="<?php echo $base_path; ?>settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-cog"></i>
                            <p>Pengaturan</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
<?php endif; ?>
