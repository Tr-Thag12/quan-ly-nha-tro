<?php
/**
 * =====================================================================
 *  DN001 – GHI CHỈ SỐ ĐIỆN NƯỚC CỦA MỘT PHÒNG   (chi_so_ghi.php?phong_id=&thang=)
 *    AC1: Tự hiển thị chỉ số tháng trước để đối chiếu
 *    AC2: Chỉ số mới không được nhỏ hơn chỉ số cũ
 *    AC3: Tự tính ra số điện, số nước đã dùng
 *
 *  DN003 – SỬA CHỈ SỐ GHI SAI  (cùng trang, khi tháng đó đã có số)
 *    AC1: Chỉ sửa được khi hóa đơn của tháng đó chưa thu tiền
 *    AC2: Sửa xong hóa đơn tự tính lại
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$phong_id = (int)gia_tri_get('phong_id', gia_tri_post('phong_id', '0'));
$thang    = gia_tri_get('thang', gia_tri_post('thang', thang_hien_tai()));

$hd = hop_dong_hieu_luc_cua_phong($phong_id);
if (!$hd) {
    flash('error', 'Phòng này không có hợp đồng còn hiệu lực nên không cần ghi chỉ số.');
    redirect('chi_so.php');
}
if (!thang_hop_le($thang) || $thang > thang_hien_tai() || $thang < substr($hd['ngay_bat_dau'], 0, 7)) {
    flash('error', 'Tháng không hợp lệ: chỉ ghi được từ tháng nhận phòng đến tháng hiện tại.');
    redirect('chi_so.php');
}

$da_ghi = chi_so_thang($phong_id, $thang);
$la_sua = (bool)$da_ghi;

if ($la_sua) {
    // DN003 – AC1: kiểm tra có được sửa không
    $ly_do_khoa = ly_do_khong_sua_chi_so($da_ghi);
    $cu = ['dien' => (int)$da_ghi['dien_cu'], 'nuoc' => (int)$da_ghi['nuoc_cu'], 'nguon' => ''];
    $dien_moi = (string)$da_ghi['dien_moi'];
    $nuoc_moi = (string)$da_ghi['nuoc_moi'];
} else {
    $ly_do_khoa = null;
    $moi_nhat = thang_ghi_moi_nhat($phong_id);
    if ($moi_nhat !== null && $moi_nhat > $thang) {
        $ly_do_khoa = 'Phòng đã ghi chỉ số tới tháng ' . ten_thang($moi_nhat) . ', không ghi bù tháng cũ hơn được.';
    }
    $cu = chi_so_cu($hd, $thang);   // AC1: chỉ số tháng trước
    $dien_moi = '';
    $nuoc_moi = '';
}

$loi = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ly_do_khoa === null) {
    check_csrf();
    $dien_moi = gia_tri_post('dien_moi');
    $nuoc_moi = gia_tri_post('nuoc_moi');
    $loi = kiem_tra_chi_so($dien_moi, $nuoc_moi, $cu);   // AC2

    if (!$loi) {
        if ($la_sua) {
            db()->beginTransaction();
            try {
                db()->prepare('UPDATE chi_so SET dien_moi = ?, nuoc_moi = ?, ngay_sua = NOW() WHERE id = ?')
                    ->execute([(int)$dien_moi, (int)$nuoc_moi, $da_ghi['id']]);
                $tinh_lai = tinh_lai_hoa_don_theo_chi_so($phong_id, $thang);   // DN003 – AC2
                db()->commit();
            } catch (Exception $loi_luu) {
                db()->rollBack();
                throw $loi_luu;
            }
            $tin = 'Đã sửa chỉ số tháng ' . ten_thang($thang) . ' của phòng ' . $hd['ma_phong'] . '.';
            if ($tinh_lai) {
                $tin .= ' Hóa đơn ' . $tinh_lai['ma'] . ' đã tự tính lại: ' . tien($tinh_lai['cu']) . ' → ' . tien($tinh_lai['moi']) . '.';
            }
            flash('success', $tin);
        } else {
            them_chi_so($phong_id, $thang, $cu, $dien_moi, $nuoc_moi);
            flash('success', 'Đã ghi chỉ số tháng ' . ten_thang($thang) . ' của phòng ' . $hd['ma_phong'] . ': dùng '
                . ((int)$dien_moi - $cu['dien']) . ' kWh điện, ' . ((int)$nuoc_moi - $cu['nuoc']) . ' m³ nước.');
        }
        redirect('chi_so.php?thang=' . $thang);
    }
}

$page_title = ($la_sua ? 'Sửa' : 'Ghi') . ' chỉ số ' . $hd['ma_phong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="chi_so.php?thang=<?= e($thang) ?>">Điện nước tháng <?= e(ten_thang($thang)) ?></a> / <?= e($hd['ma_phong']) ?></p>
        <h1><?= $la_sua ? 'Sửa' : 'Ghi' ?> chỉ số phòng <?= e($hd['ma_phong']) ?> – tháng <?= e(ten_thang($thang)) ?></h1>
        <p class="muted">Người đại diện: <?= e($hd['ho_ten']) ?> ·
            <a href="chi_so_lich_su.php?phong_id=<?= $phong_id ?>">Xem lịch sử 12 tháng</a></p>
    </div>
</div>

<?php if ($ly_do_khoa !== null): ?>
    <div class="alert alert-warning"><?= e($ly_do_khoa) ?></div>
<?php endif; ?>

<div class="card form-card">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="phong_id" value="<?= $phong_id ?>">
        <input type="hidden" name="thang" value="<?= e($thang) ?>">
        <div class="form-grid">
            <div class="field">
                <label>Chỉ số điện cũ</label>
                <div class="static-value"><strong><?= $cu['dien'] ?></strong>
                    <?php if ($cu['nguon']): ?><span class="hint inline-hint"><?= e($cu['nguon']) ?></span><?php endif; ?></div>
            </div>
            <div class="field">
                <label>Chỉ số nước cũ</label>
                <div class="static-value"><strong><?= $cu['nuoc'] ?></strong>
                    <?php if ($cu['nguon']): ?><span class="hint inline-hint"><?= e($cu['nguon']) ?></span><?php endif; ?></div>
            </div>
            <div class="field">
                <label for="dien_moi">Chỉ số điện mới <span class="required">*</span></label>
                <input type="text" id="dien_moi" name="dien_moi" inputmode="numeric" value="<?= e($dien_moi) ?>"
                       data-cu="<?= $cu['dien'] ?>" data-don-vi="kWh" data-ket-qua="dung-dien"
                       class="<?= lop_loi($loi, 'dien_moi') ?>"<?= $ly_do_khoa !== null ? ' disabled' : '' ?>>
                <?php if (isset($loi['dien_moi'])): ?>
                    <?= loi_o($loi, 'dien_moi') ?>
                <?php else: ?>
                    <div class="hint">Đã dùng: <strong id="dung-dien" class="dung"></strong></div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="nuoc_moi">Chỉ số nước mới <span class="required">*</span></label>
                <input type="text" id="nuoc_moi" name="nuoc_moi" inputmode="numeric" value="<?= e($nuoc_moi) ?>"
                       data-cu="<?= $cu['nuoc'] ?>" data-don-vi="m³" data-ket-qua="dung-nuoc"
                       class="<?= lop_loi($loi, 'nuoc_moi') ?>"<?= $ly_do_khoa !== null ? ' disabled' : '' ?>>
                <?php if (isset($loi['nuoc_moi'])): ?>
                    <?= loi_o($loi, 'nuoc_moi') ?>
                <?php else: ?>
                    <div class="hint">Đã dùng: <strong id="dung-nuoc" class="dung"></strong></div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($la_sua && $ly_do_khoa === null): ?>
            <p class="small muted">Nếu tháng này đã có hóa đơn (chưa thu tiền), hóa đơn sẽ tự tính lại theo chỉ số mới.</p>
        <?php endif; ?>
        <div class="form-actions">
            <a class="btn" href="chi_so.php?thang=<?= e($thang) ?>">Quay lại</a>
            <?php if ($ly_do_khoa === null): ?>
                <button type="submit" class="btn btn-primary"><?= $la_sua ? 'Lưu chỉ số đã sửa' : 'Lưu chỉ số' ?></button>
            <?php endif; ?>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
