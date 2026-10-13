<?php
/**
 * =====================================================================
 *  TT007 – NHẮC NỢ QUÁ HẠN
 *    AC1: Hiển thị phòng, khách, số tiền nợ, số ngày quá hạn
 *         (những phòng còn nợ quá SO_NGAY_NHAC_NO = 5 ngày kể từ hạn thu)
 *    AC2: Sắp xếp giảm dần theo số ngày quá hạn
 *  Gộp theo hợp đồng: một phòng nợ nhiều tháng chỉ hiện một dòng, kèm số hóa đơn nợ.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$moc = date('Y-m-d', strtotime('-' . SO_NGAY_NHAC_NO . ' day'));   // hạn thu trước mốc này là quá hạn hơn 5 ngày

$stmt = db()->prepare("SELECT h.id AS hop_dong_id, h.ma_hop_dong, h.trang_thai AS trang_thai_hd,
                              p.id AS phong_id, p.ma_phong, k.ho_ten, k.so_dien_thoai,
                              COUNT(*) AS so_hoa_don,
                              SUM(hd.tong_tien - hd.da_thu) AS so_tien_no,
                              MIN(hd.han_thanh_toan) AS han_som_nhat,
                              GROUP_CONCAT(DATE_FORMAT(CONCAT(hd.thang, '-01'), '%m/%Y') ORDER BY hd.thang SEPARATOR ', ') AS cac_thang
                       FROM hoa_don hd
                       JOIN hop_dong h   ON h.id = hd.hop_dong_id
                       JOIN phong p      ON p.id = hd.phong_id
                       JOIN khach_thue k ON k.id = h.khach_thue_id
                       WHERE hd.da_thu < hd.tong_tien AND hd.han_thanh_toan < ?
                       GROUP BY h.id, h.ma_hop_dong, h.trang_thai, p.id, p.ma_phong, k.ho_ten, k.so_dien_thoai
                       ORDER BY han_som_nhat, p.ma_phong");
$stmt->execute([$moc]);
$ds = $stmt->fetchAll();

$tong_no = 0;
foreach ($ds as $i => $d) {
    $ds[$i]['so_ngay_qua_han'] = so_ngay($d['han_som_nhat'], hom_nay());
    $tong_no += (int)$d['so_tien_no'];
}
// AC2: quá hạn lâu nhất lên đầu (đã sắp theo hạn sớm nhất ở câu SQL)

$page_title = 'Nhắc nợ quá hạn';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hoa_don.php">Hóa đơn</a> / Nhắc nợ</p>
        <h1>Nhắc nợ quá hạn</h1>
        <p class="muted">Các phòng còn nợ quá <?= SO_NGAY_NHAC_NO ?> ngày kể từ hạn thu, quá hạn lâu nhất đứng đầu.</p>
    </div>
</div>

<div class="card">
    <?php if (!$ds): ?>
        <div class="empty-filter">Không có phòng nào nợ quá hạn.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Phòng</th>
                        <th>Người đại diện</th>
                        <th>Số điện thoại</th>
                        <th>Nợ các tháng</th>
                        <th class="num">Số tiền nợ</th>
                        <th class="num">Quá hạn</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $d): ?>
                    <tr>
                        <td class="strong"><?= e($d['ma_phong']) ?>
                            <?php if ($d['trang_thai_hd'] !== 'hieu_luc'): ?><span class="sub">HĐ đã thanh lý</span><?php endif; ?></td>
                        <td><?= e($d['ho_ten']) ?></td>
                        <td class="nowrap"><a href="tel:<?= e($d['so_dien_thoai']) ?>"><?= e(sdt($d['so_dien_thoai'])) ?></a></td>
                        <td><?= e($d['cac_thang']) ?> <span class="sub"><?= (int)$d['so_hoa_don'] ?> hóa đơn</span></td>
                        <td class="num text-red strong"><?= e(tien($d['so_tien_no'])) ?></td>
                        <td class="num"><span class="badge badge-qua_han"><?= (int)$d['so_ngay_qua_han'] ?> ngày</span></td>
                        <td class="actions">
                            <a class="btn btn-sm" href="hoa_don.php?thang=tat_ca&amp;trang_thai=con_no&amp;q=<?= e(urlencode($d['ma_phong'])) ?>">Xem hóa đơn</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><td colspan="4">Tổng nợ quá hạn của <?= count($ds) ?> phòng</td><td class="num text-red"><?= e(tien($tong_no)) ?></td><td colspan="2"></td></tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
