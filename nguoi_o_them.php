<?php
/**
 * =====================================================================
 *  KT005 – THÊM NGƯỜI Ở CÙNG PHÒNG
 *    AC1: Một phòng ghi được nhiều người, có đánh dấu người đại diện
 *         -> người đứng tên hợp đồng có la_dai_dien = 1, người ở cùng = 0
 *    AC2: Không vượt quá số người tối đa của phòng
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$hd = lay_hop_dong(gia_tri_get('hop_dong_id', gia_tri_post('hop_dong_id', '0')));
if (!$hd) {
    flash('error', 'Không tìm thấy hợp đồng.');
    redirect('hop_dong.php');
}
if ($hd['trang_thai'] !== 'hieu_luc') {
    flash('error', 'Hợp đồng đã thanh lý, không thể thêm người ở.');
    redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
}

$so_dang_o = dem_nguoi_dang_o($hd['id']);
$toi_da    = (int)$hd['so_nguoi_toi_da'];
$da_du     = $so_dang_o >= $toi_da;     // AC2

$khach_id = gia_tri_get('khach_id', '');
$ngay_vao = hom_nay();
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $khach_id = gia_tri_post('khach_thue_id');
    $ngay_vao = gia_tri_post('ngay_vao');

    // AC2: kiểm tra lại số người lúc lưu
    $so_dang_o = dem_nguoi_dang_o($hd['id']);
    if ($so_dang_o >= $toi_da) {
        $loi['chung'] = 'Phòng ' . $hd['ma_phong'] . ' đã có ' . $so_dang_o . '/' . $toi_da
            . ' người, không thể thêm người vượt quá số người tối đa của phòng.';
    }

    $stmt = db()->prepare('SELECT * FROM khach_thue WHERE id = ?');
    $stmt->execute([(int)$khach_id]);
    $khach = $stmt->fetch();
    if (!$khach) {
        $loi['khach_thue_id'] = 'Vui lòng chọn người ở cùng.';
    } elseif (($phong_khac = phong_dang_o_cua_khach($khach['id'])) !== null) {
        $loi['khach_thue_id'] = $khach['ho_ten'] . ' đang ở phòng ' . $phong_khac . '.';
    }

    if (!ngay_hop_le($ngay_vao)) {
        $loi['ngay_vao'] = 'Vui lòng chọn ngày vào ở.';
    } elseif ($ngay_vao < $hd['ngay_bat_dau']) {
        $loi['ngay_vao'] = 'Ngày vào ở không được trước ngày bắt đầu hợp đồng (' . ngay($hd['ngay_bat_dau']) . ').';
    } elseif ($ngay_vao > date('Y-m-d', strtotime('+30 day'))) {
        $loi['ngay_vao'] = 'Ngày vào ở không được quá 30 ngày tính từ hôm nay.';
    }

    if (!$loi) {
        // Người này có thể từng ở theo chính hợp đồng này rồi rời đi: cho ở lại
        $stmt = db()->prepare('SELECT id FROM nguoi_o WHERE hop_dong_id = ? AND khach_thue_id = ?');
        $stmt->execute([$hd['id'], $khach['id']]);
        $cu = $stmt->fetchColumn();
        if ($cu) {
            db()->prepare('UPDATE nguoi_o SET ngay_vao = ?, ngay_roi = NULL WHERE id = ?')->execute([$ngay_vao, $cu]);
        } else {
            db()->prepare('INSERT INTO nguoi_o (hop_dong_id, khach_thue_id, la_dai_dien, ngay_vao) VALUES (?, ?, 0, ?)')
                ->execute([$hd['id'], $khach['id'], $ngay_vao]);
        }
        flash('success', 'Đã thêm ' . $khach['ho_ten'] . ' ở cùng phòng ' . $hd['ma_phong'] . ' ('
            . ($so_dang_o + 1) . '/' . $toi_da . ' người).');
        redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
    }
}

$ds_khach = khach_chua_o_dau();

$page_title = 'Thêm người ở cùng';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hop_dong.php">Hợp đồng</a> /
            <a href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>"><?= e($hd['ma_hop_dong']) ?></a> / Thêm người ở cùng</p>
        <h1>Thêm người ở cùng phòng <?= e($hd['ma_phong']) ?></h1>
        <p class="muted">Đang có <?= so_nguoi($so_dang_o, $toi_da) ?> người · Người đứng tên: <?= e($hd['ho_ten']) ?></p>
    </div>
</div>

<?php if (isset($loi['chung'])): ?>
    <div class="alert alert-error"><?= e($loi['chung']) ?></div>
<?php endif; ?>

<?php if ($da_du && !isset($loi['chung'])): ?>
    <div class="card empty-state">
        <h2>Phòng đã đủ người</h2>
        <p class="muted">Phòng <?= e($hd['ma_phong']) ?> đã có <?= $so_dang_o ?>/<?= $toi_da ?> người, bằng số người tối đa của phòng.
            Muốn thêm người, chủ trọ cần tăng "số người ở tối đa" của phòng trước.</p>
        <a class="btn" href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>">&larr; Quay lại hợp đồng</a>
    </div>
<?php else: ?>
<div class="card form-card narrow">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="hop_dong_id" value="<?= (int)$hd['id'] ?>">
        <div class="field">
            <label for="khach_thue_id">Người ở cùng <span class="required">*</span></label>
            <select id="khach_thue_id" name="khach_thue_id" class="<?= lop_loi($loi, 'khach_thue_id') ?>">
                <option value="">— Chọn khách chưa ở phòng nào —</option>
                <?php foreach ($ds_khach as $k): ?>
                    <option value="<?= (int)$k['id'] ?>"<?= (string)$k['id'] === (string)$khach_id ? ' selected' : '' ?>>
                        <?= e($k['ho_ten']) ?> – <?= e(sdt($k['so_dien_thoai'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($loi['khach_thue_id'])): ?>
                <?= loi_o($loi, 'khach_thue_id') ?>
            <?php else: ?>
                <div class="hint">Người mới chưa có trong danh sách?
                    <a href="khach_thue_form.php?quay_lai=nguoi_o_them&amp;hop_dong_id=<?= (int)$hd['id'] ?>">Thêm khách thuê mới</a>, xong sẽ quay lại đây.</div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="ngay_vao">Ngày vào ở <span class="required">*</span></label>
            <input type="date" id="ngay_vao" name="ngay_vao" value="<?= e($ngay_vao) ?>" class="<?= lop_loi($loi, 'ngay_vao') ?>">
            <?= loi_o($loi, 'ngay_vao') ?>
        </div>
        <div class="form-actions">
            <a class="btn" href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>">Hủy</a>
            <button type="submit" class="btn btn-primary">Thêm người ở cùng</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
