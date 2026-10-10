<?php
/**
 * =====================================================================
 *  TK003 – ĐỔI MẬT KHẨU
 * ---------------------------------------------------------------------
 *  AC1: Phải nhập đúng mật khẩu cũ mới đổi được
 *  AC2: Mật khẩu mới tối thiểu 6 ký tự
 *  AC3: Đổi xong bị đăng xuất để đăng nhập lại
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $mat_khau_cu  = isset($_POST['mat_khau_cu']) ? $_POST['mat_khau_cu'] : '';
    $mat_khau_moi = isset($_POST['mat_khau_moi']) ? $_POST['mat_khau_moi'] : '';
    $nhap_lai     = isset($_POST['nhap_lai']) ? $_POST['nhap_lai'] : '';

    $stmt = db()->prepare('SELECT mat_khau FROM tai_khoan WHERE id = ?');
    $stmt->execute([current_user()['id']]);
    $mat_khau_hien_tai = $stmt->fetchColumn();

    // AC1: mật khẩu cũ phải đúng
    if ($mat_khau_cu === '') {
        $loi['mat_khau_cu'] = 'Vui lòng nhập mật khẩu hiện tại.';
    } elseif (!password_verify($mat_khau_cu, $mat_khau_hien_tai)) {
        $loi['mat_khau_cu'] = 'Mật khẩu hiện tại không đúng.';
    }

    // AC2: mật khẩu mới tối thiểu 6 ký tự
    if (do_dai($mat_khau_moi) < MAT_KHAU_TOI_THIEU) {
        $loi['mat_khau_moi'] = 'Mật khẩu mới phải có ít nhất ' . MAT_KHAU_TOI_THIEU . ' ký tự.';
    } elseif ($mat_khau_moi === $mat_khau_cu) {
        $loi['mat_khau_moi'] = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    }

    if ($nhap_lai !== $mat_khau_moi) {
        $loi['nhap_lai'] = 'Mật khẩu nhập lại không khớp.';
    }

    if (!$loi) {
        db()->prepare('UPDATE tai_khoan SET mat_khau = ? WHERE id = ?')
            ->execute([password_hash($mat_khau_moi, PASSWORD_DEFAULT), current_user()['id']]);

        // AC3: đăng xuất ngay để người dùng đăng nhập lại bằng mật khẩu mới
        dang_xuat();
        redirect('login.php?doi_mat_khau=1');
    }
}

$page_title = 'Đổi mật khẩu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Đổi mật khẩu</h1>
        <p class="muted">Đổi thành công thì hệ thống sẽ đăng xuất để bạn đăng nhập lại bằng mật khẩu mới.</p>
    </div>
</div>

<div class="card form-card narrow">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
            <label for="mat_khau_cu">Mật khẩu hiện tại</label>
            <input type="password" id="mat_khau_cu" name="mat_khau_cu" class="<?= lop_loi($loi, 'mat_khau_cu') ?>" autocomplete="current-password">
            <?= loi_o($loi, 'mat_khau_cu') ?>
        </div>
        <div class="field">
            <label for="mat_khau_moi">Mật khẩu mới</label>
            <input type="password" id="mat_khau_moi" name="mat_khau_moi" class="<?= lop_loi($loi, 'mat_khau_moi') ?>" autocomplete="new-password">
            <?php if (isset($loi['mat_khau_moi'])): ?>
                <?= loi_o($loi, 'mat_khau_moi') ?>
            <?php else: ?>
                <div class="hint">Tối thiểu <?= MAT_KHAU_TOI_THIEU ?> ký tự.</div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="nhap_lai">Nhập lại mật khẩu mới</label>
            <input type="password" id="nhap_lai" name="nhap_lai" class="<?= lop_loi($loi, 'nhap_lai') ?>" autocomplete="new-password">
            <?= loi_o($loi, 'nhap_lai') ?>
        </div>
        <div class="form-actions">
            <a class="btn" href="index.php">Hủy</a>
            <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
