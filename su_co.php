<?php
/**
 * =====================================================================
 *  SC002 – XEM DANH SÁCH SỰ CỐ
 *    AC1: Hiển thị phòng, loại sự cố, ngày báo, trạng thái
 *    AC2: Sự cố mới nhất nằm trên cùng
 *  Mặc định mở tab "Cần xử lý" (Chờ xử lý + Đang xử lý) để phân công thợ.
 *
 *  SC004 – AC2 (chỉ Chủ trọ): cộng tổng chi phí sửa chữa theo tháng, ở cuối trang.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$cac_tab = [
    'can_xu_ly'  => 'Cần xử lý',
    'cho_xu_ly'  => 'Chờ xử lý',
    'dang_xu_ly' => 'Đang xử lý',
    'da_xong'    => 'Đã xong',
    'tat_ca'     => 'Tất cả',
];
$loc = gia_tri_get('loc', 'can_xu_ly');
if (!isset($cac_tab[$loc])) {
    $loc = 'can_xu_ly';
}
$q = gia_tri_get('q');

$dem = dem_su_co_theo_trang_thai();
$so_theo_tab = [
    'can_xu_ly'  => $dem['cho_xu_ly'] + $dem['dang_xu_ly'],
    'cho_xu_ly'  => $dem['cho_xu_ly'],
    'dang_xu_ly' => $dem['dang_xu_ly'],
    'da_xong'    => $dem['da_xong'],
    'tat_ca'     => array_sum($dem),
];

$dieu_kien = ['1 = 1'];
$tham_so = [];
if ($loc === 'can_xu_ly') {
    $dieu_kien[] = "s.trang_thai IN ('cho_xu_ly', 'dang_xu_ly')";
} elseif ($loc !== 'tat_ca') {
    $dieu_kien[] = 's.trang_thai = ?';
    $tham_so[] = $loc;
}
if ($q !== '') {
    $tu_khoa = '%' . addcslashes($q, '%_\\') . '%';
    $dieu_kien[] = '(s.ma_theo_doi LIKE ? OR p.ma_phong LIKE ? OR s.mo_ta LIKE ? OR s.loai LIKE ?)';
    array_push($tham_so, $tu_khoa, $tu_khoa, $tu_khoa, $tu_khoa);
}
// AC2: mới nhất lên đầu
$stmt = db()->prepare(cau_lay_su_co(implode(' AND ', $dieu_kien)) . ' ORDER BY s.ngay_bao DESC, s.id DESC');
$stmt->execute($tham_so);
$ds = $stmt->fetchAll();

// SC004 – AC2: chi phí sửa chữa theo tháng (chỉ Chủ trọ thấy)
$chi_phi = [];
$tong_chi_phi = 0;
if (is_chu_tro()) {
    $chi_phi = chi_phi_sua_chua_theo_thang(12);
    $tong_chi_phi = array_sum(array_column($chi_phi, 'tong'));
}

// Địa chỉ trang báo sự cố để gửi cho khách thuê (ví dụ http://localhost/nhatro/bao_su_co.php)
$dia_chi_bao = dia_chi_trang('bao_su_co.php');

$page_title = 'Sự cố';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Sự cố và bảo trì</h1>
        <p class="muted">Khách thuê tự báo sự cố tại <code><?= e($dia_chi_bao) ?></code> (không cần đăng nhập),
            nhận mã theo dõi để xem tiến độ.</p>
    </div>
    <div class="head-actions">
        <a class="btn btn-primary" href="bao_su_co.php">+ Ghi nhận sự cố</a>
    </div>
</div>

<div class="stats">
    <a class="stat-card stat-danger" href="su_co.php?loc=cho_xu_ly">
        <span class="stat-label">Chờ xử lý</span>
        <span class="stat-value"><?= $dem['cho_xu_ly'] ?></span>
        <span class="stat-sub">Chưa phân công thợ</span>
    </a>
    <a class="stat-card stat-warn" href="su_co.php?loc=dang_xu_ly">
        <span class="stat-label">Đang xử lý</span>
        <span class="stat-value"><?= $dem['dang_xu_ly'] ?></span>
        <span class="stat-sub">Thợ đang sửa</span>
    </a>
    <a class="stat-card stat-ok" href="su_co.php?loc=da_xong">
        <span class="stat-label">Đã xong</span>
        <span class="stat-value"><?= $dem['da_xong'] ?></span>
        <span class="stat-sub">Tổng <?= array_sum($dem) ?> sự cố đã báo</span>
    </a>
    <?php if (is_chu_tro()): ?>
        <a class="stat-card" href="#chi-phi">
            <span class="stat-label">Chi phí sửa chữa 12 tháng</span>
            <span class="stat-value"><?= e(tien_gon($tong_chi_phi)) ?></span>
            <span class="stat-sub">Tháng này: <?= e(tien($chi_phi[0]['tong'])) ?></span>
        </a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body" style="padding-bottom:0">
        <nav class="tabs" style="margin-bottom:0">
            <?php foreach ($cac_tab as $ma => $ten): ?>
                <a href="su_co.php?loc=<?= e($ma) ?><?= $q !== '' ? '&amp;q=' . e(urlencode($q)) : '' ?>"<?= $ma === $loc ? ' class="active"' : '' ?>>
                    <?= e($ten) ?> <span class="chip-count"><?= $so_theo_tab[$ma] ?></span></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <form class="toolbar" method="get">
        <input type="hidden" name="loc" value="<?= e($loc) ?>">
        <div class="field grow">
            <label for="q">Tìm kiếm</label>
            <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Mã theo dõi, mã phòng, loại hoặc nội dung sự cố">
        </div>
        <button type="submit" class="btn">Tìm</button>
        <?php if ($q !== ''): ?><a class="btn" href="su_co.php?loc=<?= e($loc) ?>">Xóa tìm kiếm</a><?php endif; ?>
    </form>

    <?php if (!$ds): ?>
        <div class="empty-filter">
            <?= $q !== '' ? 'Không tìm thấy sự cố nào khớp với "' . e($q) . '".' : 'Không có sự cố nào trong mục này.' ?>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã theo dõi</th>
                        <th>Phòng</th>
                        <th>Loại</th>
                        <th>Nội dung</th>
                        <th>Người báo</th>
                        <th>Ngày báo</th>
                        <th>Trạng thái</th>
                        <?php if (is_chu_tro()): ?><th class="num">Chi phí</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds as $sc): ?>
                    <tr>
                        <td class="nowrap"><a class="strong" href="su_co_chi_tiet.php?id=<?= (int)$sc['id'] ?>"><?= e($sc['ma_theo_doi']) ?></a></td>
                        <td><a href="phong_chi_tiet.php?id=<?= (int)$sc['phong_id'] ?>"><?= e($sc['ma_phong']) ?></a></td>
                        <td class="nowrap"><?= e($sc['loai']) ?></td>
                        <td><?= e(rut_gon($sc['mo_ta'], 70)) ?>
                            <?php if ($sc['anh']): ?><span class="sub">Có ảnh</span><?php endif; ?></td>
                        <td><?= $sc['nguoi_bao'] ? e($sc['nguoi_bao']) : '<span class="muted">Quản lý ghi nhận</span>' ?></td>
                        <td class="nowrap"><?= e(ngay_gio($sc['ngay_bao'])) ?>
                            <?php if ($sc['trang_thai'] !== 'da_xong'): ?>
                                <?php $cho = so_ngay(substr($sc['ngay_bao'], 0, 10), hom_nay()); ?>
                                <span class="sub <?= $cho >= 3 ? 'text-red' : '' ?>"><?= $cho > 0 ? 'Đã chờ ' . $cho . ' ngày' : 'Báo hôm nay' ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= nhan($sc['trang_thai'], TRANG_THAI_SU_CO) ?></td>
                        <?php if (is_chu_tro()): ?>
                            <td class="num"><?= $sc['chi_phi'] !== null ? e(tien($sc['chi_phi'])) : '<span class="muted">—</span>' ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (is_chu_tro()): ?>
<!-- SC004 – AC2: tổng chi phí sửa chữa theo tháng -->
<div class="card section" id="chi-phi">
    <div class="card-head">
        <h2>Chi phí sửa chữa theo tháng</h2>
        <span class="muted">Tính theo ngày sửa, 12 tháng gần nhất</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tháng</th><th class="num">Số lần sửa có chi phí</th><th class="num">Tổng chi phí</th></tr></thead>
            <tbody>
            <?php foreach ($chi_phi as $cp): ?>
                <tr class="<?= $cp['so_lan'] ? '' : 'row-muted' ?>">
                    <td class="strong"><?= e(ten_thang($cp['thang'])) ?></td>
                    <td class="num"><?= $cp['so_lan'] ?></td>
                    <td class="num"><?= e(tien($cp['tong'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td>Tổng 12 tháng</td>
                    <td class="num"><?= array_sum(array_column($chi_phi, 'so_lan')) ?></td>
                    <td class="num"><?= e(tien($tong_chi_phi)) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
