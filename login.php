<?php
/**
 * =====================================================================
 *  TK001 – ĐĂNG NHẬP HỆ THỐNG
 * ---------------------------------------------------------------------
 *  AC1: Nhập đúng tài khoản/mật khẩu thì vào được trang chủ
 *  AC2: Nhập sai quá 5 lần thì khóa 5 phút
 *  AC3: Bỏ trống ô nào thì báo lỗi ngay tại ô đó
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
no_cache();

// Đã đăng nhập rồi thì vào thẳng trang chủ
if (current_user()) {
    redirect('index.php');
}

$loi = [];
$ten_dang_nhap = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $ten_dang_nhap = trim(isset($_POST['ten_dang_nhap']) ? $_POST['ten_dang_nhap'] : '');
    $mat_khau      = isset($_POST['mat_khau']) ? $_POST['mat_khau'] : '';

    // AC3: kiểm tra từng ô, ô nào trống thì báo lỗi ngay dưới ô đó
    if ($ten_dang_nhap === '') {
        $loi['ten_dang_nhap'] = 'Vui lòng nhập tên đăng nhập.';
    }
    if ($mat_khau === '') {
        $loi['mat_khau'] = 'Vui lòng nhập mật khẩu.';
    }

    if (!$loi) {
        $stmt = db()->prepare('SELECT * FROM tai_khoan WHERE ten_dang_nhap = ?');
        $stmt->execute([$ten_dang_nhap]);
        $tk = $stmt->fetch();

        if (!$tk) {
            $loi['chung'] = 'Sai tên đăng nhập hoặc mật khẩu.';

        } elseif ($tk['khoa_den'] !== null && strtotime($tk['khoa_den']) > time()) {
            // AC2: tài khoản đang trong thời gian bị khóa
            $con_lai = strtotime($tk['khoa_den']) - time();
            $loi['chung'] = sprintf(
                'Tài khoản đang bị khóa do nhập sai mật khẩu %d lần. Vui lòng thử lại sau %d phút %02d giây.',
                SO_LAN_SAI_TOI_DA, intdiv($con_lai, 60), $con_lai % 60
            );

        } elseif (!password_verify($mat_khau, $tk['mat_khau'])) {
            // AC2: cộng thêm 1 lần sai; đủ 5 lần thì khóa 5 phút và đặt lại bộ đếm
            $so_lan_sai = (int)$tk['so_lan_sai'] + 1;
            if ($so_lan_sai >= SO_LAN_SAI_TOI_DA) {
                $khoa_den = date('Y-m-d H:i:s', time() + PHUT_KHOA * 60);
                db()->prepare('UPDATE tai_khoan SET so_lan_sai = 0, khoa_den = ? WHERE id = ?')
                    ->execute([$khoa_den, $tk['id']]);
                $loi['chung'] = sprintf(
                    'Bạn đã nhập sai mật khẩu %d lần. Tài khoản bị khóa trong %d phút.',
                    SO_LAN_SAI_TOI_DA, PHUT_KHOA
                );
            } else {
                db()->prepare('UPDATE tai_khoan SET so_lan_sai = ? WHERE id = ?')
                    ->execute([$so_lan_sai, $tk['id']]);
                $loi['chung'] = sprintf(
                    'Sai tên đăng nhập hoặc mật khẩu. Còn %d lần thử trước khi tài khoản bị khóa.',
                    SO_LAN_SAI_TOI_DA - $so_lan_sai
                );
            }

        } else {
            // AC1: đúng mật khẩu -> xóa bộ đếm sai, ghi nhớ người dùng vào phiên, vào trang chủ
            db()->prepare('UPDATE tai_khoan SET so_lan_sai = 0, khoa_den = NULL WHERE id = ?')
                ->execute([$tk['id']]);
            session_regenerate_id(true);   // cấp mã phiên mới, chống chiếm phiên
            $_SESSION['user'] = [
                'id'            => (int)$tk['id'],
                'ho_ten'        => $tk['ho_ten'],
                'ten_dang_nhap' => $tk['ten_dang_nhap'],
                'vai_tro'       => $tk['vai_tro'],   // TK005 – AC2: vai trò được chốt tại lúc đăng nhập
            ];
            redirect('index.php');
        }
    }
}

$page_title = 'Đăng nhập';
$auth_page  = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
<aside class="auth-brand">
    <span class="brand-logo lg">NT</span>
    <p class="auth-kicker"><?= e(TEN_NHA_TRO) ?></p>
    <h2><?= e(APP_NAME) ?></h2>
    <p class="auth-lead">Ứng dụng web giúp chủ trọ quản lý phòng, khách thuê, hợp đồng, điện nước, hóa đơn, sự cố và báo cáo.</p>
    <ul class="auth-tags">
        <li>Phòng</li><li>Khách thuê</li><li>Hợp đồng</li><li>Điện nước</li><li>Hóa đơn</li><li>Sự cố</li><li>Báo cáo</li>
    </ul>
</aside>
<div class="card auth-card">
    <div class="auth-head">
        <span class="brand-logo lg">NT</span>
        <h1>Đăng nhập</h1>
        <p class="muted">Dành cho Chủ trọ và Quản lý</p>
    </div>

    <?php if (isset($_GET['da_dang_xuat'])): ?>
        <div class="alert alert-success">Bạn đã đăng xuất khỏi hệ thống.</div>
    <?php elseif (isset($_GET['doi_mat_khau'])): ?>
        <div class="alert alert-success">Đổi mật khẩu thành công. Vui lòng đăng nhập lại bằng mật khẩu mới.</div>
    <?php elseif ($thong_bao): ?>
        <div class="alert alert-<?= e($thong_bao['loai']) ?>"><?= e($thong_bao['noi_dung']) ?></div>
    <?php endif; ?>

    <?php if (isset($loi['chung'])): ?>
        <div class="alert alert-error"><?= e($loi['chung']) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="ten_dang_nhap">Tên đăng nhập</label>
            <input type="text" id="ten_dang_nhap" name="ten_dang_nhap" value="<?= e($ten_dang_nhap) ?>"
                   class="<?= lop_loi($loi, 'ten_dang_nhap') ?>" autocomplete="username" autofocus>
            <?= loi_o($loi, 'ten_dang_nhap') ?>
        </div>
        <div class="field">
            <label for="mat_khau">Mật khẩu</label>
            <input type="password" id="mat_khau" name="mat_khau"
                   class="<?= lop_loi($loi, 'mat_khau') ?>" autocomplete="current-password">
            <?= loi_o($loi, 'mat_khau') ?>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Đăng nhập</button>
    </form>
    <!-- SC001: khách thuê không có tài khoản, báo sự cố và tra cứu tiến độ không cần đăng nhập -->
    <div class="public-links">
        Khách thuê: <a href="bao_su_co.php">Báo sự cố</a> · <a href="tra_cuu_su_co.php">Tra cứu tiến độ</a>
    </div>
    <p class="auth-foot">Phiên bản <?= e(APP_VERSION) ?></p>
</div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
