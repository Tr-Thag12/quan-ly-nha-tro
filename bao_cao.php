<?php
/**
 * =====================================================================
 *  BÁO CÁO DOANH THU (chỉ Chủ trọ – TK005 AC1)
 *
 *  BC001 – BÁO CÁO DOANH THU THEO THÁNG
 *    AC1: Chọn được khoảng thời gian từ tháng nào đến tháng nào
 *    AC2: Tách riêng tiền phòng, tiền điện nước, tiền dịch vụ
 *    AC3: Có dòng tổng cộng
 *
 *  BC004 – BIỂU ĐỒ DOANH THU
 *    AC1: Biểu đồ cột theo tháng, có nhãn số tiền
 *    AC2: Đổi được khoảng thời gian hiển thị (dùng chung bộ chọn tháng với bảng)
 *
 *  BC005 – XUẤT BÁO CÁO RA EXCEL: nút "Xuất Excel" gọi bao_cao_xuat.php
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

list($tu, $den) = khoang_thang_bao_cao();          // AC1
$ds = doanh_thu_theo_thang($tu, $den);

$tong = ['so_hoa_don' => 0, 'tien_phong' => 0, 'dien_nuoc' => 0, 'dich_vu' => 0, 'tong' => 0, 'da_thu' => 0, 'con_no' => 0];
foreach ($ds as $d) {
    foreach ($tong as $k => $v) {
        $tong[$k] += $d[$k];
    }
}
$lon_nhat = max(1, max(array_column($ds, 'tong')));
$cac_thang = cac_thang_gan_nhat(36, max($den, thang_hien_tai()));

$page_title = 'Báo cáo doanh thu';
$tab_bao_cao = 'doanh_thu';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Báo cáo</h1>
        <p class="muted">Doanh thu tính theo tháng của hóa đơn: số tiền phát sinh, số đã thu và số còn nợ.</p>
    </div>
    <a class="btn btn-primary" href="bao_cao_xuat.php?tu=<?= e($tu) ?>&amp;den=<?= e($den) ?>">Xuất Excel</a>
</div>

<?php require __DIR__ . '/includes/bao_cao_tabs.php'; ?>

<div class="card">
    <!-- AC1: chọn khoảng thời gian -->
    <form class="toolbar" method="get">
        <div class="field">
            <label for="tu">Từ tháng</label>
            <select id="tu" name="tu">
                <?php foreach ($cac_thang as $t): ?>
                    <option value="<?= e($t) ?>"<?= $t === $tu ? ' selected' : '' ?>><?= e(ten_thang($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="den">Đến tháng</label>
            <select id="den" name="den">
                <?php foreach ($cac_thang as $t): ?>
                    <option value="<?= e($t) ?>"<?= $t === $den ? ' selected' : '' ?>><?= e(ten_thang($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Xem báo cáo</button>
        <span class="small muted">Tổng phát sinh <?= count($ds) ?> tháng: <strong><?= e(tien($tong['tong'])) ?></strong>,
            đã thu <strong class="text-green"><?= e(tien($tong['da_thu'])) ?></strong></span>
    </form>

    <!-- BC004: biểu đồ cột có nhãn số tiền -->
    <div class="chart-wrap">
        <div class="chart" role="img" aria-label="Biểu đồ doanh thu từ <?= e(ten_thang($tu)) ?> đến <?= e(ten_thang($den)) ?>">
            <?php foreach ($ds as $d): ?>
                <?php $cao = $d['tong'] / $lon_nhat * 100; ?>
                <div class="col" title="Tháng <?= e(ten_thang($d['thang'])) ?>: phát sinh <?= e(tien($d['tong'])) ?>, đã thu <?= e(tien($d['da_thu'])) ?>">
                    <span class="val"><?= $d['tong'] ? e(tien_gon($d['tong'])) : '' ?></span>
                    <div class="bar" style="height: <?= round($cao, 1) ?>%">
                        <?php if ($d['tong'] > 0): ?>
                            <div class="seg-a" style="height: <?= round($d['da_thu'] / $d['tong'] * 100, 1) ?>%"></div>
                            <div class="seg-b" style="height: <?= round($d['con_no'] / $d['tong'] * 100, 1) ?>%"></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="chart-labels">
            <?php foreach ($ds as $d): ?><span><?= e(substr($d['thang'], 5, 2) . '/' . substr($d['thang'], 2, 2)) ?></span><?php endforeach; ?>
        </div>
        <div class="legend">
            <span><i style="background: var(--primary)"></i>Đã thu</span>
            <span><i style="background: #A9C0E4"></i>Còn nợ</span>
            <span>Nhãn trên cột: tổng tiền phát sinh của tháng</span>
        </div>
    </div>

    <!-- BC001: bảng doanh thu, AC2 tách 3 nhóm tiền, AC3 dòng tổng cộng -->
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Tháng</th>
                    <th class="num">Số hóa đơn</th>
                    <th class="num">Tiền phòng</th>
                    <th class="num">Tiền điện nước</th>
                    <th class="num">Tiền dịch vụ</th>
                    <th class="num">Tổng phát sinh</th>
                    <th class="num">Đã thu</th>
                    <th class="num">Còn nợ</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($ds) as $d): ?>
                <tr>
                    <td class="strong"><a href="hoa_don.php?thang=<?= e($d['thang']) ?>"><?= e(ten_thang($d['thang'])) ?></a></td>
                    <td class="num"><?= $d['so_hoa_don'] ?></td>
                    <td class="num"><?= e(tien($d['tien_phong'])) ?></td>
                    <td class="num"><?= e(tien($d['dien_nuoc'])) ?></td>
                    <td class="num"><?= e(tien($d['dich_vu'])) ?></td>
                    <td class="num strong"><?= e(tien($d['tong'])) ?></td>
                    <td class="num text-green"><?= e(tien($d['da_thu'])) ?></td>
                    <td class="num <?= $d['con_no'] > 0 ? 'text-red' : '' ?>"><?= e(tien($d['con_no'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Tổng cộng</td>
                    <td class="num"><?= $tong['so_hoa_don'] ?></td>
                    <td class="num"><?= e(tien($tong['tien_phong'])) ?></td>
                    <td class="num"><?= e(tien($tong['dien_nuoc'])) ?></td>
                    <td class="num"><?= e(tien($tong['dich_vu'])) ?></td>
                    <td class="num"><?= e(tien($tong['tong'])) ?></td>
                    <td class="num text-green"><?= e(tien($tong['da_thu'])) ?></td>
                    <td class="num text-red"><?= e(tien($tong['con_no'])) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
