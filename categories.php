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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        flash_message('error', 'Token keamanan tidak valid.');
        redirect('categories.php');
    }

    $action = sanitize_input($_POST['action'] ?? '');
    $type = sanitize_input($_POST['type'] ?? '');
    $category_name = sanitize_input($_POST['category_name'] ?? '');

    if (!in_array($type, ['income', 'expense'], true)) {
        flash_message('error', 'Tipe kategori tidak valid.');
        redirect('categories.php');
    }

    if ($action === 'add') {
        if ($db->addCategory($username, $type, $category_name)) {
            flash_message('success', 'Kategori berhasil ditambahkan.');
        } else {
            flash_message('error', 'Gagal menambahkan kategori.');
        }
    } elseif ($action === 'delete') {
        if ($db->deleteCategory($username, $type, $category_name)) {
            flash_message('success', 'Kategori berhasil dihapus.');
        } else {
            flash_message('error', 'Gagal menghapus kategori.');
        }
    }

    redirect('categories.php');
}

$income_categories = $user_data['finance']['categories']['income'] ?? [];
$expense_categories = $user_data['finance']['categories']['expense'] ?? [];
$page_title = 'Kelola Kategori - ' . APP_NAME;
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Kelola Kategori</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Kategori</li>
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

        <div class="callout callout-info">
            <h5>Mode Pengguna: <?php echo $_SESSION['role'] === 'group' ? 'Kelompok' : 'Individu'; ?></h5>
            <p>Kategori pada halaman ini bersifat personal sesuai akun saat ini dan bisa dipakai di form pemasukan/pengeluaran.</p>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card card-success">
                    <div class="card-header">
                        <h3 class="card-title">Kategori Pemasukan</h3>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="type" value="income">
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control" name="category_name" placeholder="Contoh: Freelance, Bonus Tim" required>
                                <div class="input-group-append">
                                    <button class="btn btn-success" type="submit">Tambah</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="card-body pt-0">
                        <ul class="list-group">
                            <?php foreach ($income_categories as $category): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($category); ?>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="income">
                                        <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($category); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirmDelete('Hapus kategori ini?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($income_categories)): ?>
                                <li class="list-group-item text-muted">Belum ada kategori pemasukan.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-danger">
                    <div class="card-header">
                        <h3 class="card-title">Kategori Pengeluaran</h3>
                    </div>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="type" value="expense">
                        <div class="card-body">
                            <div class="input-group">
                                <input type="text" class="form-control" name="category_name" placeholder="Contoh: Operasional, Iuran Kegiatan" required>
                                <div class="input-group-append">
                                    <button class="btn btn-danger" type="submit">Tambah</button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <div class="card-body pt-0">
                        <ul class="list-group">
                            <?php foreach ($expense_categories as $category): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($category); ?>
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="type" value="expense">
                                        <input type="hidden" name="category_name" value="<?php echo htmlspecialchars($category); ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirmDelete('Hapus kategori ini?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                            <?php if (empty($expense_categories)): ?>
                                <li class="list-group-item text-muted">Belum ada kategori pengeluaran.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
