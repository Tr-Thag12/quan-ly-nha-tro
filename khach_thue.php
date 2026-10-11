<?php
/**
 * =====================================================================
 *  KT003 – XEM DANH SÁCH KHÁCH THUÊ
 *    AC1: Hiển thị họ tên, SĐT, phòng đang ở, ngày vào ở
 *    AC2: Sắp xếp được theo tên hoặc theo phòng
 *
 *  KT004 – TÌM KIẾM KHÁCH THUÊ
 *    AC1: Gõ một phần tên vẫn tìm được (gõ không dấu như "nguyen" vẫn ra "Nguyễn")
 *    AC2: Không tìm thấy thì hiển thị thông báo rõ ràng
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$tu_khoa = gia_tri_get('q');
$loc     = gia_tri_get('loc', 'dang_o');     // dang_o | da_roi | tat_ca
$sap_xep = gia_tri_get('sap_xep', 'ten');    // ten | phong
if (!in_array($loc, ['dang_o', 'da_roi', 'tat_ca'], true)) {
    $loc = 'dang_o';
}
if (!in_array($sap_xep, ['ten', 'phong'], true)) {
    $sap_xep = 'ten';
}

// Mỗi khách kèm phòng đang ở (nếu có) theo hợp đồng còn hiệu lực
$sql = "SELECT k.*, o.ma_phong, o.phong_id, o.tang, o.ngay_vao, o.la_dai_dien
        FROM khach_thue k
        LEFT JOIN (
            SELECT n.khach_thue_id, n.ngay_vao, n.la_dai_dien, p.id AS phong_id, p.ma_phong, p.tang
            FROM nguoi_o n
            JOIN hop_dong h ON h.id = n.hop_dong_id AND h.trang_thai = 'hieu_luc'
            JOIN phong p    ON p.id = h.phong_id
            WHERE n.ngay_roi IS NULL
        ) o ON o.khach_thue_id = k.id
        WHERE 1 = 1";
$tham_so = [];
if ($tu_khoa !== '') {
    // KT004 – AC1: tìm theo một phần họ tên, số điện thoại hoặc số CCCD.
    // Bảng dùng bảng mã utf8mb4_unicode_ci nên so sánh không phân biệt dấu (e = ễ).
    $mau = '%' . addcslashes($tu_khoa, '%_\\') . '%';
    $sql .= ' AND (k.ho_ten LIKE ? OR k.so_dien_thoai LIKE ? OR k.cccd LIKE ?)';
    array_push($tham_so, $mau, $mau, $mau);
}
if ($loc === 'dang_o') {
    $sql .= ' AND o.ma_phong IS NOT NULL';
} elseif ($loc === 'da_roi') {
    $sql .= ' AND o.ma_phong IS NULL';
}
// KT003 – AC2: theo tên (tên là chữ cuối cùng của họ tên, như cách gọi tên người Việt) hoặc theo phòng
$sql .= $sap_xep === 'phong'
    ? ' ORDER BY o.ma_phong IS NULL, o.tang, o.ma_phong, o.la_dai_dien DESC, k.ho_ten'
    : " ORDER BY SUBSTRING_INDEX(k.ho_ten, ' ', -1), k.ho_ten";

$stmt = db()->prepare($sql);
$stmt->execute($tham_so);
$ds_khach = $stmt->fetchAll();

$page_title = 'Khách thuê';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Khách thuê</h1>
        <p class="muted">Tìm theo họ tên, số điện thoại hoặc số CCCD. Gõ không dấu vẫn tìm được.</p>
    </div>
    <a class="btn btn-primary" href="khach_thue_form.php">+ Thêm khách thuê</a>
</div>

<div class="card">
    <form class="toolbar" method="get">
        <div class="field grow">
            <label for="q">Tìm kiếm</label>
            <input type="text" id="q" name="q" value="<?= e($tu_khoa) ?>" placeholder="Ví dụ: nam, 0912, 0362...">
        </div>
        <div class="field">
            <label for="loc">Hiển thị</label>
            <select id="loc" name="loc">
                <option value="dang_o"<?= $loc === 'dang_o' ? ' selected' : '' ?>>Đang ở trọ</option>
                <option value="da_roi"<?= $loc === 'da_roi' ? ' selected' : '' ?>>Đã rời đi / chưa ở</option>
                <option value="tat_ca"<?= $loc === 'tat_ca' ? ' selected' : '' ?>>Tất cả khách</option>
            </select>
        </div>
        <div class="field">
            <label for="sap_xep">Sắp xếp</label>
            <select id="sap_xep" name="sap_xep">
                <option value="ten"<?= $sap_xep === 'ten' ? ' selected' : '' ?>>Theo tên</option>
                <option value="phong"<?= $sap_xep === 'phong' ? ' selected' : '' ?>>Theo phòng</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Tìm</button>
        <?php if ($tu_khoa !== '' || $loc !== 'dang_o' || $sap_xep !== 'ten'): ?>
            <a class="btn" href="khach_thue.php">Xóa bộ lọc</a>
        <?php endif; ?>
    </form>

    <?php if (!$ds_khach): ?>
        <div class="empty-filter">
            <?php if ($tu_khoa !== ''): ?>
                <!-- KT004 – AC2 -->
                Không tìm thấy khách thuê nào khớp với "<strong><?= e($tu_khoa) ?></strong>".
                Hãy thử gõ ít chữ hơn, hoặc chọn "Tất cả khách".
            <?php else: ?>
                Chưa có khách thuê nào trong mục này.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Họ tên</th>
                        <th>Số điện thoại</th>
                        <th>Số CCCD</th>
                        <th>Quê quán</th>
                        <th>Phòng đang ở</th>
                        <th>Ngày vào ở</th>
                        <th class="actions">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds_khach as $k): ?>
                    <tr>
                        <td>
                            <a class="strong" href="khach_thue_chi_tiet.php?id=<?= (int)$k['id'] ?>"><?= e($k['ho_ten']) ?></a>
                            <?php if ($k['ma_phong'] && (int)$k['la_dai_dien'] === 1): ?>
                                <span class="badge badge-dai_dien">Đại diện</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap"><?= e(sdt($k['so_dien_thoai'])) ?></td>
                        <td><?= e($k['cccd']) ?></td>
                        <td><?= e($k['que_quan']) ?></td>
                        <td>
                            <?php if ($k['ma_phong']): ?>
                                <a href="phong_chi_tiet.php?id=<?= (int)$k['phong_id'] ?>"><?= e($k['ma_phong']) ?></a>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap"><?= $k['ngay_vao'] ? e(ngay($k['ngay_vao'])) : '<span class="muted">—</span>' ?></td>
                        <td class="actions">
                            <a class="btn btn-sm" href="khach_thue_chi_tiet.php?id=<?= (int)$k['id'] ?>">Xem</a>
                            <a class="btn btn-sm" href="khach_thue_form.php?id=<?= (int)$k['id'] ?>">Sửa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span class="page-info"><?= count($ds_khach) ?> khách thuê</span>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
