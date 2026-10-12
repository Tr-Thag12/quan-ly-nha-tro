<?php
/**
 * =====================================================================
 *  TT004 – XEM DANH SÁCH HÓA ĐƠN
 *    AC1: Hiển thị phòng, tháng, tổng tiền, đã thu, còn nợ
 *    AC2: Có dòng tổng cộng ở cuối bảng
 *
 *  TT005 – LỌC HÓA ĐƠN THEO TRẠNG THÁI
 *    AC1: Lọc kết hợp được theo tháng và theo trạng thái
 *    AC2: Hiển thị tổng số tiền của kết quả lọc   -> dòng tổng cộng tính trên kết quả đã lọc
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$cac_thang = db()->query('SELECT DISTINCT thang FROM hoa_don ORDER BY thang DESC')->fetchAll(PDO::FETCH_COLUMN);
$thang = gia_tri_get('thang', $cac_thang ? $cac_thang[0] : thang_hien_tai());   // mặc định: tháng mới nhất có hóa đơn
if ($thang !== 'tat_ca' && !thang_hop_le($thang)) {
    $thang = $cac_thang ? $cac_thang[0] : thang_hien_tai();
}
$trang_thai = gia_tri_get('trang_thai', 'tat_ca');
$cac_trang_thai = ['tat_ca' => 'Tất cả', 'con_no' => 'Còn nợ (chưa thu đủ)'] + TRANG_THAI_HOA_DON;
if (!isset($cac_trang_thai[$trang_thai])) {
    $trang_thai = 'tat_ca';
}
$tu_khoa = gia_tri_get('q');

$sql = 'SELECT hd.*, p.ma_phong, k.ho_ten
        FROM hoa_don hd
        JOIN phong p      ON p.id = hd.phong_id
        JOIN hop_dong h   ON h.id = hd.hop_dong_id
        JOIN khach_thue k ON k.id = h.khach_thue_id
        WHERE 1 = 1';
$tham_so = [];
if ($thang !== 'tat_ca') {                         // AC1: lọc theo tháng
    $sql .= ' AND hd.thang = ?';
    $tham_so[] = $thang;
}
if ($trang_thai === 'con_no') {                    // AC1: ... kết hợp theo trạng thái
    $sql .= ' AND hd.da_thu < hd.tong_tien';
} elseif ($trang_thai !== 'tat_ca') {
    $sql .= ' AND hd.trang_thai = ?';
    $tham_so[] = $trang_thai;
}
if ($tu_khoa !== '') {
    $mau = '%' . addcslashes($tu_khoa, '%_\\') . '%';
    $sql .= ' AND (p.ma_phong LIKE ? OR k.ho_ten LIKE ? OR hd.ma_hoa_don LIKE ?)';
    array_push($tham_so, $mau, $mau, $mau);
}
$sql .= ' ORDER BY hd.thang DESC, p.tang, p.ma_phong';
$stmt = db()->prepare($sql);
$stmt->execute($tham_so);
$ds = $stmt->fetchAll();

// AC2 (TT004) và AC2 (TT005): cộng trên chính kết quả đã lọc
$tong = ['tong_tien' => 0, 'da_thu' => 0, 'con_no' => 0];
foreach ($ds as $h) {
    $tong['tong_tien'] += (int)$h['tong_tien'];
    $tong['da_thu']    += (int)$h['da_thu'];
    $tong['con_no']    += max(0, (int)$h['tong_tien'] - (int)$h['da_thu']);
}

$page_title = 'Hóa đơn';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Hóa đơn</h1>
        <p class="muted">Lọc kết hợp theo tháng và trạng thái; dòng tổng cộng luôn tính trên kết quả đang hiển thị.</p>
    </div>
    <div class="head-actions">
        <a class="btn" href="nhac_no.php">Nhắc nợ quá hạn</a>
        <a class="btn btn-primary" href="hoa_don_tao.php">+ Sinh hóa đơn tháng</a>
    </div>
</div>

<div class="card">
    <form class="toolbar" method="get">
        <div class="field">
            <label for="thang">Tháng</label>
            <select id="thang" name="thang" data-tu-gui>
                <option value="tat_ca"<?= $thang === 'tat_ca' ? ' selected' : '' ?>>Tất cả các tháng</option>
                <?php foreach ($cac_thang as $t): ?>
                    <option value="<?= e($t) ?>"<?= $t === $thang ? ' selected' : '' ?>>Tháng <?= e(ten_thang($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="trang_thai">Trạng thái</label>
            <select id="trang_thai" name="trang_thai" data-tu-gui>
                <?php foreach ($cac_trang_thai as $ma => $ten): ?>
                    <option value="<?= e($ma) ?>"<?= $ma === $trang_thai ? ' selected' : '' ?>><?= e($ten) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field grow">
            <label for="q">Tìm phòng hoặc tên khách</label>
            <input type="text" id="q" name="q" value="<?= e($tu_khoa) ?>" placeholder="Ví dụ: P101">
        </div>
        <button type="submit" class="btn btn-primary">Lọc</button>
    </form>

    <?php if (!$ds): ?>
        <div class="empty-filter">Không có hóa đơn nào khớp với bộ lọc.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã hóa đơn</th>
                        <th>Phòng</th>
                        <th>Tháng</th>
                        <th>Người đại diện</th>
                        <th class="num">Tổng tiền</th>
                        <th class="num">Đã thu</th>
                        <th class="num">Còn nợ</th>
                        <th>Hạn thu</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $h): $no = (int)$h['tong_tien'] - (int)$h['da_thu']; ?>
                    <tr>
                        <td><a class="strong" href="hoa_don_chi_tiet.php?id=<?= (int)$h['id'] ?>"><?= e($h['ma_hoa_don']) ?></a></td>
                        <td><?= e($h['ma_phong']) ?></td>
                        <td><?= e(ten_thang($h['thang'])) ?></td>
                        <td><?= e($h['ho_ten']) ?></td>
                        <td class="num"><?= e(tien($h['tong_tien'])) ?></td>
                        <td class="num"><?= e(tien($h['da_thu'])) ?></td>
                        <td class="num <?= $no > 0 ? 'text-red strong' : '' ?>"><?= $no > 0 ? e(tien($no)) : '—' ?></td>
                        <td class="nowrap">
                            <?= e(ngay($h['han_thanh_toan'])) ?>
                            <?php if ($no > 0 && $h['han_thanh_toan'] < hom_nay()): ?>
                                <span class="badge badge-qua_han">Quá hạn</span>
                            <?php endif; ?>
                        </td>
                        <td><?= nhan($h['trang_thai'], TRANG_THAI_HOA_DON) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4">Tổng cộng <?= count($ds) ?> hóa đơn</td>
                        <td class="num"><?= e(tien($tong['tong_tien'])) ?></td>
                        <td class="num"><?= e(tien($tong['da_thu'])) ?></td>
                        <td class="num text-red"><?= e(tien($tong['con_no'])) ?></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
