<?php
/**
 * =====================================================================
 *  TK004 – TẠO TÀI KHOẢN QUẢN LÝ (chỉ Chủ trọ)
 * ---------------------------------------------------------------------
 *  AC1: Nhập họ tên, tài khoản, mật khẩu, số điện thoại
 *  AC2: Trùng tên tài khoản thì báo lỗi
 *  AC3: Tài khoản mới đăng nhập được ngay
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

$loi = [];
$du_lieu = ['ho_ten' => '', 'ten_dang_nhap' => '', 'so_dien_thoai' => '', 'vai_tro' => 'quan_ly'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $mac_dinh) {
        $du_lieu[$ten_o] = trim(isset($_POST[$ten_o]) ? $_POST[$ten_o] : '');
    }
    $mat_khau = isset($_POST['mat_khau']) ? $_POST['mat_khau'] : '';

    // AC1: kiểm tra đủ 4 thông tin bắt buộc
    if ($du_lieu['ho_ten'] === '') {
        $loi['ho_ten'] = 'Vui lòng nhập họ tên.';
    } elseif (do_dai($du_lieu['ho_ten']) > 100) {
        $loi['ho_ten'] = 'Họ tên tối đa 100 ký tự.';
    }

    if ($du_lieu['ten_dang_nhap'] === '') {
        $loi['ten_dang_nhap'] = 'Vui lòng nhập tên đăng nhập.';
    } elseif (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $du_lieu['ten_dang_nhap'])) {
        $loi['ten_dang_nhap'] = 'Tên đăng nhập dài 3–30 ký tự, chỉ gồm chữ không dấu, số, dấu chấm hoặc gạch dưới.';
    } else {
        // AC2: không cho trùng tên đăng nhập
        $stmt = db()->prepare('SELECT COUNT(*) FROM tai_khoan WHERE ten_dang_nhap = ?');
        $stmt->execute([$du_lieu['ten_dang_nhap']]);
        if ($stmt->fetchColumn() > 0) {
            $loi['ten_dang_nhap'] = 'Tên đăng nhập "' . $du_lieu['ten_dang_nhap'] . '" đã tồn tại, vui lòng chọn tên khác.';
        }
    }

    if (do_dai($mat_khau) < MAT_KHAU_TOI_THIEU) {
        $loi['mat_khau'] = 'Mật khẩu phải có ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự.';
    }

    if ($du_lieu['so_dien_thoai'] === '') {
        $loi['so_dien_thoai'] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^0\d{9}$/', $du_lieu['so_dien_thoai'])) {
        $loi['so_dien_thoai'] = 'Số điện thoại gồm đúng 10 chữ số và bắt đầu bằng số 0.';
    }

    if (!array_key_exists($du_lieu['vai_tro'], VAI_TRO)) {
        $loi['vai_tro'] = 'Vai trò không hợp lệ.';
    }

    if (!$loi) {
        // Mật khẩu được mã hóa trước khi lưu, không ai đọc được mật khẩu gốc
        db()->prepare('INSERT INTO tai_khoan (ho_ten, ten_dang_nhap, mat_khau, so_dien_thoai, vai_tro) VALUES (?, ?, ?, ?, ?)')
            ->execute([
                $du_lieu['ho_ten'],
                $du_lieu['ten_dang_nhap'],
                password_hash($mat_khau, PASSWORD_DEFAULT),
                $du_lieu['so_dien_thoai'],
                $du_lieu['vai_tro'],
            ]);
        // AC3: lưu xong là đăng nhập được ngay, không cần kích hoạt
        flash('success', 'Đã tạo tài khoản "' . $du_lieu['ten_dang_nhap'] . '". Tài khoản này có thể đăng nhập ngay.');
        redirect('tai_khoan.php');
    }
}

$page_title = 'Tạo tài khoản';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Tạo tài khoản</h1>
        <p class="muted">Tạo tài khoản cho người quản lý để giao việc vận hành hằng ngày.</p>
    </div>
    <a class="btn" href="tai_khoan.php">&larr; Danh sách tài khoản</a>
</div>

<div class="card form-card">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="ho_ten">Họ tên <span class="required">*</span></label>
                <input type="text" id="ho_ten" name="ho_ten" value="<?= e($du_lieu['ho_ten']) ?>" class="<?= lop_loi($loi, 'ho_ten') ?>">
                <?= loi_o($loi, 'ho_ten') ?>
            </div>
            <div class="field">
                <label for="so_dien_thoai">Số điện thoại <span class="required">*</span></label>
                <input type="text" id="so_dien_thoai" name="so_dien_thoai" inputmode="numeric" maxlength="10"
                       value="<?= e($du_lieu['so_dien_thoai']) ?>" class="<?= lop_loi($loi, 'so_dien_thoai') ?>">
                <?= loi_o($loi, 'so_dien_thoai') ?>
            </div>
            <div class="field">
                <label for="ten_dang_nhap">Tên đăng nhập <span class="required">*</span></label>
                <input type="text" id="ten_dang_nhap" name="ten_dang_nhap" autocomplete="off"
                       value="<?= e($du_lieu['ten_dang_nhap']) ?>" class="<?= lop_loi($loi, 'ten_dang_nhap') ?>">
                <?= loi_o($loi, 'ten_dang_nhap') ?>
            </div>
            <div class="field">
                <label for="mat_khau">Mật khẩu <span class="required">*</span></label>
                <input type="password" id="mat_khau" name="mat_khau" autocomplete="new-password" class="<?= lop_loi($loi, 'mat_khau') ?>">
                <?php if (isset($loi['mat_khau'])): ?>
                    <?= loi_o($loi, 'mat_khau') ?>
                <?php else: ?>
                    <div class="hint">Tối thiểu <?= MAT_KHAU_TOI_THIEU ?> ký tự.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="vai_tro">Vai trò</label>
                <select id="vai_tro" name="vai_tro" class="<?= lop_loi($loi, 'vai_tro') ?>">
                    <?php foreach (VAI_TRO as $ma => $ten): ?>
                        <option value="<?= e($ma) ?>"<?= $ma === $du_lieu['vai_tro'] ? ' selected' : '' ?>><?= e($ten) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= loi_o($loi, 'vai_tro') ?>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="tai_khoan.php">Hủy</a>
            <button type="submit" class="btn btn-primary">Tạo tài khoản</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
