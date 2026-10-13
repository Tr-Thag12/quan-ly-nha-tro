<?php
/**
 * =====================================================================
 *  BC002 – BÁO CÁO CÔNG NỢ (chỉ Chủ trọ)
 *    AC1: Hiển thị theo từng phòng và tổng toàn nhà trọ
 *    AC2: Tách được nợ trong hạn (hóa đơn tháng này, chưa tới hạn thu) và nợ quá hạn
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

$hom_nay = hom_nay();
$stmt = db()->prepare("SELECT h.id AS hop_dong_id, h.ma_hop_dong, h.trang_thai AS trang_thai_hd,
                              p.ma_phong, k.ho_ten, k.so_dien_thoai,
                              COUNT(*) AS so_hoa_don,
                              SUM(CASE WHEN hd.han_thanh_toan >= ? THEN hd.tong_tien - hd.da_thu ELSE 0 END) AS no_trong_han,
                              SUM(CASE WHEN hd.han_thanh_toan <  ? THEN hd.tong_tien - hd.da_thu ELSE 0 END) AS no_qua_han,
                              SUM(hd.tong_tien - hd.da_thu) AS tong_no
                       FROM hoa_don hd
                       JOIN hop_dong h   ON h.id = hd.hop_dong_id
                       JOIN phong p      ON p.id = hd.phong_id
                       JOIN khach_thue k ON k.id = h.khach_thue_id
                       WHERE hd.da_thu < hd.tong_tien
                       GROUP BY h.id, h.ma_hop_dong, h.trang_thai, p.ma_phong, k.ho_ten, k.so_dien_thoai
                       ORDER BY tong_no DESC");
$stmt->execute([$hom_nay, $hom_nay]);
$ds = $stmt->fetchAll();

// AC1: tổng toàn nhà trọ
$tong = ['so_hoa_don' => 0, 'no_trong_han' => 0, 'no_qua_han' => 0, 'tong_no' => 0];
foreach ($ds as $d) {
    foreach ($tong as $k => $v) {
        $tong[$k] += (int)$d[$k];
    }
}

$page_title = 'Báo cáo công nợ';
$tab_bao_cao = 'cong_no';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Báo cáo</h1>
        <p class="muted">Số tiền khách còn nợ tính đến hôm nay <?= e(ngay($hom_nay)) ?>.</p>
    </div>
</div>

<?php require __DIR__ . '/includes/bao_cao_tabs.php'; ?>

<div class="stats">
    <div class="stat-card stat-danger">
        <span class="stat-label">Tổng nợ toàn nhà trọ</span>
        <span class="stat-value"><?= e(tien_gon($tong['tong_no'])) ?></span>
        <span class="stat-sub"><?= e(tien($tong['tong_no'])) ?> · <?= count($ds) ?> phòng</span>
    </div>
    <div class="stat-card stat-warn">
        <span class="stat-label">Nợ trong hạn</span>
        <span class="stat-value"><?= e(tien_gon($tong['no_trong_han'])) ?></span>
        <span class="stat-sub">Chưa tới hạn thu, chưa đáng lo</span>
    </div>
    <div class="stat-card stat-danger">
        <span class="stat-label">Nợ quá hạn</span>
        <span class="stat-value"><?= e(tien_gon($tong['no_qua_han'])) ?></span>
        <span class="stat-sub">Đã qua hạn thu, cần nhắc khách</span>
    </div>
    <a class="stat-card" href="nhac_no.php">
        <span class="stat-label">Danh sách nhắc nợ</span>
        <span class="stat-value">&rarr;</span>
        <span class="stat-sub">Phòng quá hạn hơn <?= SO_NGAY_NHAC_NO ?> ngày</span>
    </a>
</div>

<div class="card">
    <?php if (!$ds): ?>
        <div class="empty-filter">Không phòng nào còn nợ.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Phòng</th>
                        <th>Người đại diện</th>
                        <th>Số điện thoại</th>
                        <th class="num">Số hóa đơn nợ</th>
                        <th class="num">Nợ trong hạn</th>
                        <th class="num">Nợ quá hạn</th>
                        <th class="num">Tổng nợ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $d): ?>
                    <tr>
                        <td class="strong"><a href="hop_dong_chi_tiet.php?id=<?= (int)$d['hop_dong_id'] ?>"><?= e($d['ma_phong']) ?></a>
                            <?php if ($d['trang_thai_hd'] !== 'hieu_luc'): ?><span class="sub">HĐ đã thanh lý</span><?php endif; ?></td>
                        <td><?= e($d['ho_ten']) ?></td>
                        <td class="nowrap"><?= e(sdt($d['so_dien_thoai'])) ?></td>
                        <td class="num"><?= (int)$d['so_hoa_don'] ?></td>
                        <td class="num"><?= (int)$d['no_trong_han'] ? e(tien($d['no_trong_han'])) : '—' ?></td>
                        <td class="num <?= (int)$d['no_qua_han'] ? 'text-red strong' : '' ?>"><?= (int)$d['no_qua_han'] ? e(tien($d['no_qua_han'])) : '—' ?></td>
                        <td class="num strong"><?= e(tien($d['tong_no'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Tổng toàn nhà trọ (<?= count($ds) ?> phòng)</td>
                        <td class="num"><?= $tong['so_hoa_don'] ?></td>
                        <td class="num"><?= e(tien($tong['no_trong_han'])) ?></td>
                        <td class="num text-red"><?= e(tien($tong['no_qua_han'])) ?></td>
                        <td class="num"><?= e(tien($tong['tong_no'])) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
