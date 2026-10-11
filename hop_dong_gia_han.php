<?php
/**
 * =====================================================================
 *  HD004 – GIA HẠN HỢP ĐỒNG
 *    AC1: Ngày hết hạn mới phải sau ngày hết hạn cũ
 *    AC2: Lưu lại lịch sử các lần gia hạn (bảng gia_han)
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$hd = lay_hop_dong(gia_tri_get('id', gia_tri_post('id', '0')));
if (!$hd) {
    flash('error', 'Không tìm thấy hợp đồng.');
    redirect('hop_dong.php');
}
if ($hd['trang_thai'] !== 'hieu_luc') {
    flash('error', 'Hợp đồng ' . $hd['ma_hop_dong'] . ' đã thanh lý, không thể gia hạn.');
    redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
}

// Gợi ý: gia hạn thêm 12 tháng tính từ ngày hết hạn cũ
$ngay_moi = cong_thang_cho_ngay($hd['ngay_ket_thuc'], 12);
$ghi_chu  = '';
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $ngay_moi = gia_tri_post('ngay_ket_thuc_moi');
    $ghi_chu  = gia_tri_post('ghi_chu');

    if (!ngay_hop_le($ngay_moi)) {
        $loi['ngay_ket_thuc_moi'] = 'Vui lòng chọn ngày hết hạn mới.';
    } elseif ($ngay_moi <= $hd['ngay_ket_thuc']) {
        // AC1
        $loi['ngay_ket_thuc_moi'] = 'Ngày hết hạn mới phải sau ngày hết hạn cũ (' . ngay($hd['ngay_ket_thuc']) . ').';
    } elseif ($ngay_moi > date('Y-m-d', strtotime($hd['ngay_ket_thuc'] . ' +5 year'))) {
        $loi['ngay_ket_thuc_moi'] = 'Mỗi lần gia hạn tối đa 5 năm.';
    }
    if (do_dai($ghi_chu) > 255) {
        $loi['ghi_chu'] = 'Ghi chú tối đa 255 ký tự.';
    }

    if (!$loi) {
        db()->beginTransaction();
        try {
            // AC2: lưu lịch sử trước, rồi mới đổi ngày hết hạn
            db()->prepare('INSERT INTO gia_han (hop_dong_id, ngay_ket_thuc_cu, ngay_ket_thuc_moi, ghi_chu, nguoi_thuc_hien)
                           VALUES (?, ?, ?, ?, ?)')
                ->execute([$hd['id'], $hd['ngay_ket_thuc'], $ngay_moi, $ghi_chu !== '' ? $ghi_chu : null, id_nguoi_dung()]);
            db()->prepare('UPDATE hop_dong SET ngay_ket_thuc = ? WHERE id = ?')->execute([$ngay_moi, $hd['id']]);
            db()->commit();
        } catch (Exception $loi_luu) {
            db()->rollBack();
            throw $loi_luu;
        }
        flash('success', 'Đã gia hạn hợp đồng ' . $hd['ma_hop_dong'] . ' đến ngày ' . ngay($ngay_moi) . '.');
        redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
    }
}

$page_title = 'Gia hạn ' . $hd['ma_hop_dong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hop_dong.php">Hợp đồng</a> /
            <a href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>"><?= e($hd['ma_hop_dong']) ?></a> / Gia hạn</p>
        <h1>Gia hạn hợp đồng <?= e($hd['ma_hop_dong']) ?></h1>
        <p class="muted">Phòng <?= e($hd['ma_phong']) ?> · Đứng tên <?= e($hd['ho_ten']) ?> ·
            Hết hạn hiện tại <strong><?= e(ngay($hd['ngay_ket_thuc'])) ?></strong> <?= nhan_con_lai($hd['ngay_ket_thuc']) ?></p>
    </div>
</div>

<div class="card form-card narrow">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$hd['id'] ?>">
        <div class="field">
            <label for="ngay_ket_thuc_moi">Ngày hết hạn mới <span class="required">*</span></label>
            <input type="date" id="ngay_ket_thuc_moi" name="ngay_ket_thuc_moi" value="<?= e($ngay_moi) ?>"
                   min="<?= e(date('Y-m-d', strtotime($hd['ngay_ket_thuc'] . ' +1 day'))) ?>"
                   class="<?= lop_loi($loi, 'ngay_ket_thuc_moi') ?>">
            <?php if (isset($loi['ngay_ket_thuc_moi'])): ?>
                <?= loi_o($loi, 'ngay_ket_thuc_moi') ?>
            <?php else: ?>
                <div class="hint">Gợi ý sẵn thêm 12 tháng. Phải sau ngày <?= e(ngay($hd['ngay_ket_thuc'])) ?>.</div>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="ghi_chu">Ghi chú</label>
            <input type="text" id="ghi_chu" name="ghi_chu" maxlength="255" value="<?= e($ghi_chu) ?>"
                   placeholder="Ví dụ: Khách muốn ở thêm 1 năm, giữ nguyên giá" class="<?= lop_loi($loi, 'ghi_chu') ?>">
            <?= loi_o($loi, 'ghi_chu') ?>
        </div>
        <div class="form-actions">
            <a class="btn" href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>">Hủy</a>
            <button type="submit" class="btn btn-primary">Gia hạn</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
