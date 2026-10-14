<?php
/**
 * =====================================================================
 *  BC003 – THỐNG KÊ TỶ LỆ LẤP ĐẦY PHÒNG (chỉ Chủ trọ)
 *    AC1: Tỷ lệ = số phòng đang thuê chia tổng số phòng
 *    AC2: Hiển thị theo 12 tháng gần nhất
 *  Một phòng tính là "có khách" trong tháng nếu vào ngày cuối tháng (tháng này: hôm nay)
 *  phòng đang có hợp đồng: đã bắt đầu và chưa thanh lý.
 *  Tổng số phòng lấy theo số phòng hiện có của nhà trọ.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

$tong_phong = (int)db()->query('SELECT COUNT(*) FROM phong')->fetchColumn();

$stmt = db()->prepare('SELECT COUNT(DISTINCT phong_id) FROM hop_dong
                       WHERE ngay_bat_dau <= ? AND (ngay_thanh_ly IS NULL OR ngay_thanh_ly >= ?)');
$ds = [];
foreach (array_reverse(cac_thang_gan_nhat(12)) as $thang) {                 // AC2: 12 tháng
    $ngay_xet = min(ngay_cuoi_thang($thang), hom_nay());
    $stmt->execute([$ngay_xet, $ngay_xet]);
    $co_khach = (int)$stmt->fetchColumn();
    $ds[] = [
        'thang'    => $thang,
        'ngay_xet' => $ngay_xet,
        'co_khach' => $co_khach,
        'ty_le'    => $tong_phong ? round($co_khach * 100 / $tong_phong, 1) : 0,   // AC1
    ];
}
$trung_binh = $ds ? round(array_sum(array_column($ds, 'ty_le')) / count($ds), 1) : 0;
$hien_tai = end($ds);

$page_title = 'Tỷ lệ lấp đầy phòng';
$tab_bao_cao = 'lap_day';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Báo cáo</h1>
        <p class="muted">Tỷ lệ lấp đầy = số phòng có khách ÷ tổng <?= $tong_phong ?> phòng. Dùng để quyết định có nên giảm giá hút khách.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/bao_cao_tabs.php'; ?>

<div class="stats">
    <div class="stat-card stat-ok">
        <span class="stat-label">Tháng này</span>
        <span class="stat-value"><?= number_format($hien_tai['ty_le'], 1, ',', '.') ?>%</span>
        <span class="stat-sub"><?= $hien_tai['co_khach'] ?>/<?= $tong_phong ?> phòng có khách</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Trung bình 12 tháng</span>
        <span class="stat-value"><?= number_format($trung_binh, 1, ',', '.') ?>%</span>
        <span class="stat-sub">Của <?= count($ds) ?> tháng gần nhất</span>
    </div>
</div>

<div class="card">
    <div class="chart-wrap">
        <div class="chart" role="img" aria-label="Biểu đồ tỷ lệ lấp đầy 12 tháng">
            <?php foreach ($ds as $d): ?>
                <div class="col" title="Tháng <?= e(ten_thang($d['thang'])) ?>: <?= $d['co_khach'] ?>/<?= $tong_phong ?> phòng">
                    <span class="val"><?= number_format($d['ty_le'], 0, ',', '.') ?>%</span>
                    <div class="bar single" style="height: <?= max(0.5, $d['ty_le']) ?>%"></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-labels">
            <?php foreach ($ds as $d): ?><span><?= e(substr($d['thang'], 5, 2) . '/' . substr($d['thang'], 2, 2)) ?></span><?php endforeach; ?>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tháng</th><th>Tính tại ngày</th><th class="num">Phòng có khách</th><th class="num">Tổng số phòng</th><th class="num">Tỷ lệ lấp đầy</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($ds) as $d): ?>
                <tr>
                    <td class="strong"><?= e(ten_thang($d['thang'])) ?></td>
                    <td><?= e(ngay($d['ngay_xet'])) ?></td>
                    <td class="num"><?= $d['co_khach'] ?></td>
                    <td class="num"><?= $tong_phong ?></td>
                    <td class="num strong"><?= number_format($d['ty_le'], 1, ',', '.') ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
