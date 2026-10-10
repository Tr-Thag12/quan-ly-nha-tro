<?php
/**
 * =====================================================================
 *  TRANG CÀI ĐẶT CƠ SỞ DỮ LIỆU
 * ---------------------------------------------------------------------
 *  Chạy file database.sql để tạo CSDL "nhatro", tạo bảng và nạp dữ liệu mẫu.
 *  Dùng khi mới cài, hoặc trước buổi demo để đưa dữ liệu về trạng thái ban đầu.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';

$ket_qua = null;
$loi     = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        // Kết nối vào MySQL nhưng chưa chọn database (vì database có thể chưa tồn tại)
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql = file_get_contents(__DIR__ . '/database.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);             // bỏ các dòng chú thích
        $cac_cau_lenh = preg_split('/;\s*[\r\n]+/', $sql);          // tách theo dấu ; cuối dòng

        $so_cau = 0;
        foreach ($cac_cau_lenh as $cau_lenh) {
            $cau_lenh = rtrim(trim($cau_lenh), ';');
            if ($cau_lenh !== '') {
                $pdo->exec($cau_lenh);
                $so_cau++;
            }
        }
        $ket_qua = $so_cau;
        dang_xuat();   // dữ liệu đã làm mới nên đăng xuất phiên cũ (nếu có)
    } catch (PDOException $e) {
        $loi = $e->getMessage();
    }
}

$page_title = 'Cài đặt';
$auth_page  = true;
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card wide">
    <div class="auth-head">
        <span class="brand-logo lg">NT</span>
        <h1>Cài đặt cơ sở dữ liệu</h1>
        <p class="muted"><?= e(APP_NAME) ?> &middot; <?= e(APP_VERSION) ?></p>
    </div>

    <?php if ($ket_qua !== null): ?>
        <div class="alert alert-success">Cài đặt thành công. Đã chạy <?= (int)$ket_qua ?> câu lệnh SQL.</div>
        <p style="margin-bottom:10px"><strong>Tài khoản dùng thử</strong> (mật khẩu đều là <code>123456</code>):</p>
        <table class="table bordered">
            <thead><tr><th>Tên đăng nhập</th><th>Vai trò</th><th>Ghi chú</th></tr></thead>
            <tbody>
                <tr><td><code>chutro</code></td><td>Chủ trọ</td><td>Toàn quyền</td></tr>
                <tr><td><code>quanly</code></td><td>Quản lý</td><td>Không vào được Tài khoản, Đơn giá, Báo cáo</td></tr>
                <tr><td><code>tuanpm</code></td><td>Quản lý</td><td>Dùng để thử khóa khi nhập sai 5 lần</td></tr>
            </tbody>
        </table>
        <a class="btn btn-primary btn-block" style="margin-top:20px" href="login.php">Vào trang đăng nhập</a>

    <?php else: ?>
        <?php if ($loi !== null): ?>
            <div class="alert alert-error">
                Cài đặt thất bại: <?= e($loi) ?>
            </div>
            <ul class="hint-list">
                <li>Kiểm tra XAMPP Control Panel: dòng <strong>MySQL</strong> phải đang chạy (màu xanh).</li>
                <li>Nếu MySQL có mật khẩu, sửa <code>DB_PASS</code> trong file <code>config.php</code>.</li>
            </ul>
        <?php endif; ?>

        <p>Trang này sẽ tạo cơ sở dữ liệu <code><?= e(DB_NAME) ?></code>, tạo 5 bảng và nạp dữ liệu mẫu gồm 24 phòng, 19 khách thuê, 19 hợp đồng và 3 tài khoản.</p>
        <div class="alert alert-warning" style="margin-top:16px">
            Nếu đã cài trước đó, toàn bộ dữ liệu hiện có sẽ bị xóa và nạp lại từ đầu.
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary btn-block">Cài đặt dữ liệu mẫu</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
