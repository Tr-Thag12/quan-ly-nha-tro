<?php
/**
 * =====================================================================
 *  DN002 – XEM LỊCH SỬ CHỈ SỐ
 *    AC1: Hiển thị bảng chỉ số 12 tháng gần nhất
 *    AC2: Đánh dấu tháng có mức dùng tăng trên 50% so với tháng trước
 *         (giúp phát hiện phòng dùng bất thường hoặc rò rỉ nước)
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$phong_id = (int)gia_tri_get('phong_id', '0');
$stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
$stmt->execute([$phong_id]);
$phong = $stmt->fetch();
if (!$phong) {
    flash('error', 'Không tìm thấy phòng.');
    redirect('chi_so.php');
}

// Lấy 13 tháng để tháng cũ nhất trong 12 tháng vẫn so sánh được với tháng liền trước
$stmt = db()->prepare('SELECT c.*, h.so_nguoi, h.id AS hoa_don_id
                       FROM chi_so c
                       LEFT JOIN hoa_don h ON h.phong_id = c.phong_id AND h.thang = c.thang
                       WHERE c.phong_id = ? AND c.thang >= ?
                       ORDER BY c.thang');
$stmt->execute([$phong_id, thang_cong(thang_hien_tai(), -12)]);
$cac_thang = $stmt->fetchAll();

$dong = [];
$truoc = null;
foreach ($cac_thang as $c) {
    $d = [
        'c'    => $c,
        'dien' => (int)$c['dien_moi'] - (int)$c['dien_cu'],
        'nuoc' => (int)$c['nuoc_moi'] - (int)$c['nuoc_cu'],
        'tang_dien' => null,
        'tang_nuoc' => null,
    ];
    // AC2: chỉ so với tháng liền trước (không so khi bị cách quãng vì phòng trống)
    if ($truoc && $truoc['c']['thang'] === thang_cong($c['thang'], -1)) {
        foreach (['dien', 'nuoc'] as $loai) {
            if ($truoc[$loai] > 0) {
                $phan_tram = (int)round(($d[$loai] - $truoc[$loai]) * 100 / $truoc[$loai]);
                if ($phan_tram > MUC_TANG_BAT_THUONG) {
                    $d['tang_' . $loai] = $phan_tram;
                }
            }
        }
    }
    $dong[] = $d;
    $truoc = $d;
}
// AC1: chỉ hiện 12 tháng gần nhất, mới nhất lên đầu
$dong = array_slice(array_reverse($dong), 0, 12);
$so_thang_bat_thuong = count(array_filter($dong, function ($d) { return $d['tang_dien'] || $d['tang_nuoc']; }));

// DN001 / DN003: nút ghi (hoặc sửa) chỉ số tháng này của riêng phòng này
$thang_nay = thang_hien_tai();
$nut_ghi = null;
if (hop_dong_hieu_luc_cua_phong($phong_id)) {
    $ghi_thang_nay = chi_so_thang($phong_id, $thang_nay);
    if (!$ghi_thang_nay) {
        $nut_ghi = 'Ghi chỉ số tháng ' . ten_thang($thang_nay);
    } elseif (ly_do_khong_sua_chi_so($ghi_thang_nay) === null) {
        $nut_ghi = 'Sửa chỉ số tháng ' . ten_thang($thang_nay);
    }
}

$page_title = 'Lịch sử điện nước ' . $phong['ma_phong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="phong_chi_tiet.php?id=<?= $phong_id ?>">Phòng <?= e($phong['ma_phong']) ?></a> / Lịch sử điện nước</p>
        <h1>Lịch sử điện nước phòng <?= e($phong['ma_phong']) ?></h1>
        <p class="muted">12 tháng gần nhất. Tháng dùng tăng trên <?= MUC_TANG_BAT_THUONG ?>% so với tháng trước được đánh dấu đỏ.</p>
    </div>
    <div class="head-actions">
        <a class="btn" href="chi_so.php">&larr; Điện nước theo tháng</a>
        <?php if ($nut_ghi): ?>
            <a class="btn btn-primary" href="chi_so_ghi.php?phong_id=<?= $phong_id ?>&amp;thang=<?= e($thang_nay) ?>"><?= e($nut_ghi) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($so_thang_bat_thuong): ?>
    <div class="alert alert-warning">
        Có <?= $so_thang_bat_thuong ?> tháng dùng tăng bất thường. Hãy xem số người ở tháng đó có tăng không;
        nếu không, có thể phòng bị rò rỉ nước hoặc thiết bị điện bị hỏng.
    </div>
<?php endif; ?>

<div class="card">
    <?php if (!$dong): ?>
        <div class="empty-filter">Phòng chưa có lần ghi chỉ số nào trong 12 tháng qua.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tháng</th>
                        <th class="num">Điện cũ</th><th class="num">Điện mới</th><th class="num">Điện dùng</th>
                        <th class="num">Nước cũ</th><th class="num">Nước mới</th><th class="num">Nước dùng</th>
                        <th class="center">Số người</th>
                        <th>Hóa đơn</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dong as $d): $c = $d['c']; ?>
                    <tr class="<?= $d['tang_dien'] || $d['tang_nuoc'] ? 'row-error' : '' ?>">
                        <td class="strong"><?= e(ten_thang($c['thang'])) ?></td>
                        <td class="num"><?= (int)$c['dien_cu'] ?></td>
                        <td class="num"><?= (int)$c['dien_moi'] ?></td>
                        <td class="num">
                            <strong><?= $d['dien'] ?> kWh</strong>
                            <?php if ($d['tang_dien']): ?><br><span class="badge badge-tang">Tăng <?= $d['tang_dien'] ?>%</span><?php endif; ?>
                        </td>
                        <td class="num"><?= (int)$c['nuoc_cu'] ?></td>
                        <td class="num"><?= (int)$c['nuoc_moi'] ?></td>
                        <td class="num">
                            <strong><?= $d['nuoc'] ?> m³</strong>
                            <?php if ($d['tang_nuoc']): ?><br><span class="badge badge-tang">Tăng <?= $d['tang_nuoc'] ?>%</span><?php endif; ?>
                        </td>
                        <td class="center"><?= $c['so_nguoi'] !== null ? (int)$c['so_nguoi'] : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php if ($c['hoa_don_id']): ?>
                                <a href="hoa_don_chi_tiet.php?id=<?= (int)$c['hoa_don_id'] ?>">Xem</a>
                            <?php else: ?>
                                <span class="muted">Chưa lập</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
