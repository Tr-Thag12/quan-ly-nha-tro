<?php
/**
 * Phần đầu trang dùng chung: thanh trên cùng (tên hệ thống, người dùng) + thanh menu.
 * Mỗi trang đặt biến $page_title trước khi nạp file này.
 * Trang đăng nhập / cài đặt / trang công khai cho khách thuê đặt thêm $auth_page = true
 * để dùng bố cục căn giữa, không có menu.
 */
$nguoi_dung    = current_user();
$thong_bao     = lay_flash();
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
            <span class="brand-text"><?= e(APP_NAME) ?><small><?= e(TEN_NHA_TRO) ?></small></span>
        </a>
        <?php if ($nguoi_dung): ?>
            <div class="user-box">
                <span class="user-avatar" aria-hidden="true"><?= e(chu_cai_dau($nguoi_dung['ho_ten'])) ?></span>
                <div class="user-info">
                    <span class="user-name"><?= e($nguoi_dung['ho_ten']) ?></span>
                    <span class="user-role"><?= e(VAI_TRO[$nguoi_dung['vai_tro']]) ?></span>
                </div>
                <a class="topbar-link" href="doi_mat_khau.php">Đổi mật khẩu</a>
                <a class="topbar-link logout" href="logout.php">Đăng xuất</a>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($nguoi_dung): ?>
        <nav class="menu-bar">
            <div class="menu">
                <a href="index.php"<?= menu_active('index') ?>>Trang chủ</a>
                <a href="phong.php"<?= menu_active('phong') ?>>Phòng</a>
                <a href="khach_thue.php"<?= menu_active('khach_thue') ?>>Khách thuê</a>
                <a href="hop_dong.php"<?= menu_active(['hop_dong', 'nguoi_o']) ?>>Hợp đồng</a>
                <a href="chi_so.php"<?= menu_active('chi_so') ?>>Điện nước</a>
                <a href="hoa_don.php"<?= menu_active(['hoa_don', 'nhac_no']) ?>>Hóa đơn</a>
                <a href="su_co.php"<?= menu_active(['su_co', 'bao_su_co']) ?>>Sự cố</a>
                <?php if (is_chu_tro()): /* TK005: tài khoản Quản lý không thấy 3 mục dưới */ ?>
                    <a href="bao_cao.php"<?= menu_active('bao_cao') ?>>Báo cáo</a>
                    <span class="menu-sep" aria-hidden="true"></span>
                    <a href="don_gia.php"<?= menu_active('don_gia') ?>>Đơn giá</a>
                    <a href="tai_khoan.php"<?= menu_active('tai_khoan') ?>>Tài khoản</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</header>
<?php endif; ?>
<main class="container">
<?php if ($thong_bao && !$la_trang_auth): ?>
    <div class="alert alert-<?= e($thong_bao['loai']) ?>"><?= e($thong_bao['noi_dung']) ?></div>
<?php endif; ?>
