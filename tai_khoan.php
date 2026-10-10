<?php
/**
 * =====================================================================
 *  QUẢN LÝ TÀI KHOẢN (chỉ Chủ trọ)
 * ---------------------------------------------------------------------
 *  TK004 – Danh sách tài khoản + nút tạo tài khoản (xem tai_khoan_them.php)
 *  TK005 – PHÂN QUYỀN TÀI KHOẢN
 *    AC1: Quản lý không vào được mục Báo cáo và Cấu hình đơn giá
 *         -> các trang đó gọi require_role('chu_tro'), menu cũng ẩn đi
 *    AC2: Đổi quyền xong có hiệu lực ở lần đăng nhập kế tiếp
 *         -> vai trò được lưu vào phiên lúc đăng nhập (login.php),
 *            nên người đang đăng nhập giữ quyền cũ cho đến khi đăng nhập lại
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id        = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
    $hanh_dong = isset($_POST['hanh_dong']) ? $_POST['hanh_dong'] : '';

    $stmt = db()->prepare('SELECT * FROM tai_khoan WHERE id = ?');
    $stmt->execute([$id]);
    $tk = $stmt->fetch();

    if (!$tk) {
        flash('error', 'Không tìm thấy tài khoản.');

    } elseif ($id === current_user()['id']) {
        // Không cho tự hạ quyền mình, tránh trường hợp hệ thống không còn Chủ trọ nào
        flash('error', 'Bạn không thể tự thay đổi tài khoản của chính mình tại đây.');

    } elseif ($hanh_dong === 'doi_vai_tro') {
        $vai_tro = isset($_POST['vai_tro']) ? $_POST['vai_tro'] : '';
        if (!array_key_exists($vai_tro, VAI_TRO)) {
            flash('error', 'Vai trò không hợp lệ.');
        } elseif ($vai_tro === $tk['vai_tro']) {
            flash('info', 'Tài khoản ' . $tk['ten_dang_nhap'] . ' vẫn giữ vai trò ' . VAI_TRO[$vai_tro] . '.');
        } else {
            db()->prepare('UPDATE tai_khoan SET vai_tro = ? WHERE id = ?')->execute([$vai_tro, $id]);
            flash('success', 'Đã đổi vai trò của ' . $tk['ten_dang_nhap'] . ' thành ' . VAI_TRO[$vai_tro]
                . '. Thay đổi có hiệu lực từ lần đăng nhập kế tiếp của tài khoản này.');   // AC2
        }

    } elseif ($hanh_dong === 'mo_khoa') {
        db()->prepare('UPDATE tai_khoan SET so_lan_sai = 0, khoa_den = NULL WHERE id = ?')->execute([$id]);
        flash('success', 'Đã mở khóa tài khoản ' . $tk['ten_dang_nhap'] . '.');
    }

    redirect('tai_khoan.php');
}

$ds_tai_khoan = db()->query("SELECT * FROM tai_khoan ORDER BY vai_tro = 'chu_tro' DESC, ho_ten")->fetchAll();

$page_title = 'Tài khoản';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Tài khoản người dùng</h1>
        <p class="muted"><?= count($ds_tai_khoan) ?> tài khoản &middot; Thay đổi vai trò có hiệu lực từ lần đăng nhập kế tiếp của tài khoản đó.</p>
    </div>
    <a class="btn btn-primary" href="tai_khoan_them.php">+ Tạo tài khoản</a>
</div>

<div class="card table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Họ tên</th>
                <th>Tên đăng nhập</th>
                <th>Số điện thoại</th>
                <th>Vai trò</th>
                <th>Trạng thái</th>
                <th class="actions">Thao tác</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($ds_tai_khoan as $tk): ?>
            <?php
            $la_toi   = (int)$tk['id'] === current_user()['id'];
            $bi_khoa  = $tk['khoa_den'] !== null && strtotime($tk['khoa_den']) > time();
            ?>
            <tr>
                <td><strong><?= e($tk['ho_ten']) ?></strong><?= $la_toi ? ' <span class="muted">(bạn)</span>' : '' ?></td>
                <td><code><?= e($tk['ten_dang_nhap']) ?></code></td>
                <td><?= e($tk['so_dien_thoai']) ?></td>
                <td>
                    <?php if ($la_toi): ?>
                        <span class="role-pill role-<?= e($tk['vai_tro']) ?>"><?= e(VAI_TRO[$tk['vai_tro']]) ?></span>
                    <?php else: ?>
                        <form method="post" class="role-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$tk['id'] ?>">
                            <input type="hidden" name="hanh_dong" value="doi_vai_tro">
                            <select name="vai_tro" aria-label="Vai trò của <?= e($tk['ten_dang_nhap']) ?>">
                                <?php foreach (VAI_TRO as $ma => $ten): ?>
                                    <option value="<?= e($ma) ?>"<?= $ma === $tk['vai_tro'] ? ' selected' : '' ?>><?= e($ten) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-sm">Lưu</button>
                        </form>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($bi_khoa): ?>
                        <span class="badge badge-bi_khoa">Bị khóa đến <?= date('H:i', strtotime($tk['khoa_den'])) ?></span>
                    <?php else: ?>
                        <span class="badge badge-hoat_dong">Hoạt động</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <?php if ($bi_khoa && !$la_toi): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$tk['id'] ?>">
                            <input type="hidden" name="hanh_dong" value="mo_khoa">
                            <button type="submit" class="btn btn-sm">Mở khóa</button>
                        </form>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
