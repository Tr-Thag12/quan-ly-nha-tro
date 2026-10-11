<?php
/**
 * =====================================================================
 *  HD002 – XEM DANH SÁCH HỢP ĐỒNG
 *    AC1: Hiển thị mã HĐ, phòng, khách, ngày bắt đầu, ngày hết hạn
 *    AC2: Lọc được theo còn hiệu lực / đã thanh lý
 *
 *  HD006 – CẢNH BÁO HỢP ĐỒNG SẮP HẾT HẠN   (hop_dong.php?loc=sap_het_han)
 *    AC2: Bấm vào xem được danh sách chi tiết kèm số ngày còn lại
 *    (AC1 – con số trên trang chủ – nằm ở index.php)
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$loc     = gia_tri_get('loc', 'hieu_luc');   // hieu_luc | sap_het_han | da_thanh_ly | tat_ca
$tu_khoa = gia_tri_get('q');
$cac_loc = [
    'hieu_luc'    => 'Còn hiệu lực',
    'sap_het_han' => 'Sắp hết hạn',
    'da_thanh_ly' => 'Đã thanh lý',
    'tat_ca'      => 'Tất cả',
];
if (!isset($cac_loc[$loc])) {
    $loc = 'hieu_luc';
}

$sql = "SELECT h.*, p.ma_phong, p.so_nguoi_toi_da, k.ho_ten,
               (SELECT COUNT(*) FROM nguoi_o n WHERE n.hop_dong_id = h.id AND n.ngay_roi IS NULL) AS so_nguoi
        FROM hop_dong h
        JOIN phong p      ON p.id = h.phong_id
        JOIN khach_thue k ON k.id = h.khach_thue_id
        WHERE 1 = 1";
$tham_so = [];
if ($loc === 'hieu_luc') {
    $sql .= " AND h.trang_thai = 'hieu_luc'";
} elseif ($loc === 'da_thanh_ly') {
    $sql .= " AND h.trang_thai = 'da_thanh_ly'";
} elseif ($loc === 'sap_het_han') {
    // HD006: còn hiệu lực và hết hạn trong 30 ngày tới (kể cả đã quá hạn mà chưa thanh lý)
    $sql .= " AND h.trang_thai = 'hieu_luc' AND h.ngay_ket_thuc <= ?";
    $tham_so[] = date('Y-m-d', strtotime('+' . SO_NGAY_SAP_HET_HAN . ' day'));
}
if ($tu_khoa !== '') {
    $mau = '%' . addcslashes($tu_khoa, '%_\\') . '%';
    $sql .= ' AND (h.ma_hop_dong LIKE ? OR p.ma_phong LIKE ? OR k.ho_ten LIKE ?)';
    array_push($tham_so, $mau, $mau, $mau);
}
$sql .= $loc === 'sap_het_han' ? ' ORDER BY h.ngay_ket_thuc' : ' ORDER BY h.trang_thai = \'hieu_luc\' DESC, h.ngay_bat_dau DESC';
$stmt = db()->prepare($sql);
$stmt->execute($tham_so);
$ds = $stmt->fetchAll();

$so_sap_het_han = dem_hop_dong_sap_het_han();

$page_title = 'Hợp đồng';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hợp đồng thuê phòng</h1>
        <p class="muted">Bấm vào mã hợp đồng để xem chi tiết, người ở cùng, gia hạn hoặc thanh lý.</p>
    </div>
    <a class="btn btn-primary" href="hop_dong_tao.php">+ Tạo hợp đồng</a>
</div>

<nav class="tabs">
    <?php foreach ($cac_loc as $ma => $ten): ?>
        <a href="hop_dong.php?loc=<?= $ma ?>"<?= $loc === $ma ? ' class="active"' : '' ?>>
            <?= e($ten) ?><?= $ma === 'sap_het_han' && $so_sap_het_han ? ' (' . $so_sap_het_han . ')' : '' ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($loc === 'sap_het_han'): ?>
    <div class="alert alert-warning">
        Các hợp đồng dưới đây hết hạn trong <?= SO_NGAY_SAP_HET_HAN ?> ngày tới. Hãy hỏi khách muốn
        <strong>gia hạn</strong> hay <strong>trả phòng</strong> để chủ động tìm khách mới.
    </div>
<?php endif; ?>

<div class="card">
    <form class="toolbar" method="get">
        <input type="hidden" name="loc" value="<?= e($loc) ?>">
        <div class="field grow">
            <label for="q">Tìm hợp đồng</label>
            <input type="text" id="q" name="q" value="<?= e($tu_khoa) ?>" placeholder="Mã hợp đồng, mã phòng hoặc tên khách">
        </div>
        <button type="submit" class="btn btn-primary">Tìm</button>
    </form>

    <?php if (!$ds): ?>
        <div class="empty-filter">
            <?= $tu_khoa !== '' ? 'Không tìm thấy hợp đồng nào khớp với "' . e($tu_khoa) . '".' : 'Không có hợp đồng nào trong mục này.' ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã HĐ</th>
                        <th>Phòng</th>
                        <th>Người đứng tên</th>
                        <th class="center">Số người</th>
                        <th>Bắt đầu</th>
                        <th>Hết hạn</th>
                        <th>Còn lại</th>
                        <th class="num">Giá thuê</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $h): ?>
                    <tr>
                        <td><a class="strong" href="hop_dong_chi_tiet.php?id=<?= (int)$h['id'] ?>"><?= e($h['ma_hop_dong']) ?></a></td>
                        <td><a href="phong_chi_tiet.php?id=<?= (int)$h['phong_id'] ?>"><?= e($h['ma_phong']) ?></a></td>
                        <td><?= e($h['ho_ten']) ?></td>
                        <td class="center">
                            <?= $h['trang_thai'] === 'hieu_luc' ? so_nguoi($h['so_nguoi'], $h['so_nguoi_toi_da']) : '<span class="muted">—</span>' ?>
                        </td>
                        <td class="nowrap"><?= e(ngay($h['ngay_bat_dau'])) ?></td>
                        <td class="nowrap"><?= e(ngay($h['ngay_ket_thuc'])) ?></td>
                        <td class="nowrap">
                            <?php if ($h['trang_thai'] === 'hieu_luc'): ?>
                                <?= nhan_con_lai($h['ngay_ket_thuc']) ?>
                            <?php else: ?>
                                <span class="muted">Thanh lý <?= e(ngay($h['ngay_thanh_ly'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="num"><?= e(tien($h['gia_thue'])) ?></td>
                        <td><?= nhan($h['trang_thai'], TRANG_THAI_HOP_DONG) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot"><span class="page-info"><?= count($ds) ?> hợp đồng</span></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
