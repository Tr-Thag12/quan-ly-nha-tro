<?php
/**
 * =====================================================================
 *  TT001 – SINH HÓA ĐƠN THÁNG
 *    AC1: Chỉ sinh cho phòng có hợp đồng còn hiệu lực
 *    AC2: Tổng tiền = tiền phòng + điện + nước + dịch vụ   (công thức ở includes/hoa_don.php)
 *    AC3: Một phòng một tháng chỉ có một hóa đơn           (và khóa UNIQUE trong CSDL)
 *    AC4: Hóa đơn mới ở trạng thái Chưa thu
 *
 *  Màn hình cho xem trước số tiền dự kiến của từng phòng rồi mới bấm sinh.
 *  Phòng chưa ghi chỉ số tháng đó thì chưa sinh được (chưa tính được tiền điện nước).
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$thang = gia_tri_get('thang', gia_tri_post('thang', thang_hien_tai()));
if (!thang_hop_le($thang) || $thang > thang_hien_tai()) {
    $thang = thang_hien_tai();
}

$loi_don_gia = null;
try {
    $ds = du_kien_hoa_don($thang);
} catch (RuntimeException $e) {
    $ds = [];
    $loi_don_gia = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loi_don_gia === null) {
    check_csrf();
    $so_tao = 0;
    $tong = 0;
    db()->beginTransaction();
    try {
        foreach ($ds as $d) {
            if ($d['tinh_trang'] === 'co_the_tao') {
                tao_hoa_don($d['hop_dong'], $d['chi_so'], $thang);
                $so_tao++;
                $tong += $d['du_kien']['tong_tien'];
            }
        }
        db()->commit();
    } catch (Exception $loi_luu) {
        db()->rollBack();
        throw $loi_luu;
    }
    if ($so_tao) {
        flash('success', 'Đã sinh ' . $so_tao . ' hóa đơn tháng ' . ten_thang($thang) . ', tổng ' . tien($tong)
            . '. Hóa đơn mới ở trạng thái Chưa thu, hạn thu ngày ' . ngay(han_thanh_toan($thang)) . '.');
    } else {
        flash('info', 'Không có hóa đơn nào cần sinh cho tháng ' . ten_thang($thang) . '.');
    }
    redirect('hoa_don.php?thang=' . $thang);
}

$co_the_tao = array_values(array_filter($ds, function ($d) { return $d['tinh_trang'] === 'co_the_tao'; }));
$chua_ghi   = count(array_filter($ds, function ($d) { return $d['tinh_trang'] === 'chua_ghi_chi_so'; }));
$tong_du_kien = array_sum(array_map(function ($d) { return $d['du_kien']['tong_tien']; }, $co_the_tao));

$page_title = 'Sinh hóa đơn tháng ' . ten_thang($thang);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hoa_don.php">Hóa đơn</a> / Sinh hóa đơn</p>
        <h1>Sinh hóa đơn tháng <?= e(ten_thang($thang)) ?></h1>
        <p class="muted">Xem trước số tiền của từng phòng rồi bấm "Sinh hóa đơn". Mỗi phòng mỗi tháng chỉ có một hóa đơn.</p>
    </div>
</div>

<?php if ($loi_don_gia): ?>
    <div class="alert alert-error"><?= e($loi_don_gia) ?></div>
<?php endif; ?>
<?php if ($chua_ghi): ?>
    <div class="alert alert-warning">
        Còn <?= $chua_ghi ?> phòng chưa ghi chỉ số tháng <?= e(ten_thang($thang)) ?> nên chưa sinh được hóa đơn.
        <a href="chi_so.php?thang=<?= e($thang) ?>">Ghi chỉ số ngay</a>.
    </div>
<?php endif; ?>

<div class="card">
    <form class="toolbar" method="get">
        <div class="field">
            <label for="thang">Tháng tính tiền</label>
            <select id="thang" name="thang" data-tu-gui>
                <?php foreach (cac_thang_gan_nhat(12) as $t): ?>
                    <option value="<?= e($t) ?>"<?= $t === $thang ? ' selected' : '' ?>>Tháng <?= e(ten_thang($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>

    <?php if (!$ds): ?>
        <div class="empty-filter">Tháng <?= e(ten_thang($thang)) ?> không có phòng nào đang cho thuê.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Phòng</th>
                        <th>Người đại diện</th>
                        <th>Tình trạng</th>
                        <th class="num">Tiền phòng</th>
                        <th class="num">Điện</th>
                        <th class="num">Nước</th>
                        <th class="num">Dịch vụ</th>
                        <th class="num">Tổng tiền</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $d): $hd = $d['hop_dong']; ?>
                    <?php $t = $d['tinh_trang'] === 'co_the_tao' ? $d['du_kien'] : ($d['tinh_trang'] === 'da_co' ? $d['hoa_don'] : null); ?>
                    <tr class="<?= $d['tinh_trang'] === 'co_the_tao' ? '' : 'row-muted' ?>">
                        <td class="strong"><?= e($hd['ma_phong']) ?></td>
                        <td><?= e($hd['ho_ten']) ?></td>
                        <td>
                            <?php if ($d['tinh_trang'] === 'co_the_tao'): ?>
                                <span class="badge badge-trong">Sẽ sinh hóa đơn</span>
                            <?php elseif ($d['tinh_trang'] === 'da_co'): ?>
                                <a href="hoa_don_chi_tiet.php?id=<?= (int)$d['hoa_don']['id'] ?>">Đã có hóa đơn</a>
                            <?php else: ?>
                                <a class="text-red" href="chi_so_ghi.php?phong_id=<?= (int)$hd['phong_id'] ?>&amp;thang=<?= e($thang) ?>">Chưa ghi chỉ số</a>
                            <?php endif; ?>
                        </td>
                        <?php if ($t): ?>
                            <td class="num"><?= e(tien($t['tien_phong'])) ?></td>
                            <td class="num"><?= e(tien($t['tien_dien'])) ?><span class="sub"><?= $t['dien_moi'] - $t['dien_cu'] ?> kWh</span></td>
                            <td class="num"><?= e(tien($t['tien_nuoc'])) ?><span class="sub"><?= $t['nuoc_moi'] - $t['nuoc_cu'] ?> m³</span></td>
                            <td class="num"><?= e(tien($t['tien_rac'] + $t['tien_wifi'] + $t['tien_gui_xe'])) ?>
                                <span class="sub"><?= (int)$t['so_nguoi'] ?> người · <?= (int)$t['so_xe'] ?> xe</span></td>
                            <td class="num strong"><?= e(tien($t['tong_tien'])) ?></td>
                        <?php else: ?>
                            <td class="num">—</td><td class="num">—</td><td class="num">—</td><td class="num">—</td><td class="num">—</td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="7">Tổng tiền <?= count($co_the_tao) ?> hóa đơn sẽ sinh</td>
                        <td class="num"><?= e(tien($tong_du_kien)) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <form method="post" class="list-foot"
              data-confirm="Sinh <?= count($co_the_tao) ?> hóa đơn tháng <?= e(ten_thang($thang)) ?>, tổng <?= e(tien($tong_du_kien)) ?>?">
            <?= csrf_field() ?>
            <input type="hidden" name="thang" value="<?= e($thang) ?>">
            <span class="page-info">Hạn thu tiền của hóa đơn tháng này: <?= e(ngay(han_thanh_toan($thang))) ?></span>
            <button type="submit" class="btn btn-primary"<?= $co_the_tao && !$loi_don_gia ? '' : ' disabled' ?>>
                Sinh <?= count($co_the_tao) ?> hóa đơn tháng <?= e(ten_thang($thang)) ?>
            </button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
