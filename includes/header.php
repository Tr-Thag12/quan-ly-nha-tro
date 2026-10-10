<?php
/**
 * Phần đầu trang dùng chung: thanh menu trên cùng + ô thông báo.
 * Mỗi trang đặt biến $page_title trước khi nạp file này.
 * Trang đăng nhập / cài đặt đặt thêm $auth_page = true để dùng bố cục căn giữa.
 */
$nguoi_dung = current_user();
$thong_bao  = lay_flash();
$la_trang_auth = !empty($auth_page);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(isset($page_title) ? $page_title . ' · ' . APP_NAME : APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= $la_trang_auth ? 'auth-page' : '' ?>">
<?php if (!$la_trang_auth): ?>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-logo">NT</span>
            <span><?= e(APP_NAME) ?></span>
        </a>
        <?php if ($nguoi_dung): ?>
            <nav class="menu">
                <a href="index.php"<?= menu_active('index') ?>>Trang chủ</a>
                <a href="phong.php"<?= menu_active('phong') ?>>Phòng trọ</a>
                <?php if (is_chu_tro()): /* TK005: tài khoản Quản lý không thấy 3 mục dưới */ ?>
                    <a href="tai_khoan.php"<?= menu_active('tai_khoan') ?>>Tài khoản</a>
                    <a href="don_gia.php"<?= menu_active('don_gia') ?>>Đơn giá</a>
                    <a href="bao_cao.php"<?= menu_active('bao_cao') ?>>Báo cáo</a>
                <?php endif; ?>
            </nav>
            <div class="user-box">
                <div class="user-info">
                    <span class="user-name"><?= e($nguoi_dung['ho_ten']) ?></span>
                    <span class="user-role"><?= e(VAI_TRO[$nguoi_dung['vai_tro']]) ?></span>
                </div>
                <a class="topbar-link" href="doi_mat_khau.php">Đổi mật khẩu</a>
                <a class="topbar-link logout" href="logout.php">Đăng xuất</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>
<main class="container">
<?php if ($thong_bao && !$la_trang_auth): ?>
    <div class="alert alert-<?= e($thong_bao['loai']) ?>"><?= e($thong_bao['noi_dung']) ?></div>
<?php endif; ?>
