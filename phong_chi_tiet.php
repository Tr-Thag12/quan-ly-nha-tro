<?php
/**
 * =====================================================================
 *  PH006 – XEM CHI TIẾT PHÒNG
 *    AC1: Hiển thị thông tin phòng, khách đang ở, danh sách hợp đồng cũ
 *    AC2: Hiển thị các sự cố đã từng báo của phòng
 *  v0.2: khung "Người đang ở" liệt kê đủ mọi người trong phòng (bảng nguoi_o),
 *        đánh dấu người đại diện, khớp với con số 3/4 ở danh sách phòng.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();   // Chủ trọ và Quản lý đều xem được

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
$stmt->execute([$id]);
$phong = $stmt->fetch();
if (!$phong) {
    flash('error', 'Không tìm thấy phòng.');
    redirect('phong.php');
}

// AC1: toàn bộ hợp đồng của phòng, mới nhất lên đầu
$stmt = db()->prepare('SELECT h.*, k.ho_ten, k.so_dien_thoai, k.cccd, k.que_quan
                       FROM hop_dong h JOIN khach_thue k ON k.id = h.khach_thue_id
                       WHERE h.phong_id = ?
                       ORDER BY h.ngay_bat_dau DESC');
$stmt->execute([$id]);
$ds_hop_dong = $stmt->fetchAll();

// Hợp đồng còn hiệu lực = khách đang ở
$hop_dong_hien_tai = null;
foreach ($ds_hop_dong as $hd) {
    if ($hd['trang_thai'] === 'hieu_luc') {
        $hop_dong_hien_tai = $hd;
        break;
    }
}

// v0.2: mọi người đang ở theo hợp đồng hiện tại, người đại diện lên đầu
$ds_nguoi_o = [];
if ($hop_dong_hien_tai) {
    $stmt = db()->prepare('SELECT k.ho_ten, k.so_dien_thoai, k.cccd, k.que_quan, n.la_dai_dien, n.ngay_vao
                           FROM nguoi_o n JOIN khach_thue k ON k.id = n.khach_thue_id
                           WHERE n.hop_dong_id = ? AND n.ngay_roi IS NULL
                           ORDER BY n.la_dai_dien DESC, n.ngay_vao, k.ho_ten');
    $stmt->execute([$hop_dong_hien_tai['id']]);
    $ds_nguoi_o = $stmt->fetchAll();
}

// AC2: các sự cố đã báo của phòng, mới nhất lên đầu
$stmt = db()->prepare('SELECT * FROM su_co WHERE phong_id = ? ORDER BY ngay_bao DESC');
$stmt->execute([$id]);
$ds_su_co = $stmt->fetchAll();

$page_title = 'Phòng ' . $phong['ma_phong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="phong.php">Danh sách phòng</a> / <?= e($phong['ma_phong']) ?></p>
        <h1>Phòng <?= e($phong['ma_phong']) ?> <?= nhan($phong['trang_thai'], TRANG_THAI_PHONG) ?></h1>
        <p class="muted">Tầng <?= (int)$phong['tang'] ?> &middot; <?= e(dien_tich($phong['dien_tich'])) ?>
            &middot; Tối đa <?= (int)$phong['so_nguoi_toi_da'] ?> người</p>
    </div>
    <div class="head-actions">
        <?php if ($hop_dong_hien_tai): ?>
            <a class="btn" href="hop_dong_chi_tiet.php?id=<?= (int)$hop_dong_hien_tai['id'] ?>">Xem hợp đồng</a>
        <?php elseif ($phong['trang_thai'] === 'trong'): ?>
            <a class="btn btn-primary" href="hop_dong_tao.php?phong_id=<?= (int)$phong['id'] ?>">+ Tạo hợp đồng</a>
        <?php endif; ?>
        <a class="btn" href="chi_so_lich_su.php?phong_id=<?= (int)$phong['id'] ?>">Lịch sử điện nước</a>
        <?php if (is_chu_tro()): ?>
            <a class="btn" href="phong_form.php?id=<?= (int)$phong['id'] ?>">Sửa thông tin</a>
            <form method="post" action="phong_xoa.php" class="inline"
                  data-confirm="Bạn có chắc muốn xóa phòng <?= e($phong['ma_phong']) ?>? Thao tác này không thể hoàn tác.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$phong['id'] ?>">
                <input type="hidden" name="tu" value="chi_tiet">
                <button type="submit" class="btn btn-danger">Xóa phòng</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2 even">
    <!-- Thông tin phòng -->
    <div class="card">
        <div class="card-head"><h2>Thông tin phòng</h2></div>
        <div class="card-body">
            <dl class="info-list">
                <dt>Mã phòng</dt>          <dd><?= e($phong['ma_phong']) ?></dd>
                <dt>Tầng</dt>              <dd><?= (int)$phong['tang'] ?></dd>
                <dt>Diện tích</dt>         <dd><?= e(dien_tich($phong['dien_tich'])) ?></dd>
                <dt>Giá thuê niêm yết</dt> <dd><strong><?= e(tien($phong['gia_thue'])) ?></strong> / tháng</dd>
                <dt>Số người tối đa</dt>   <dd><?= (int)$phong['so_nguoi_toi_da'] ?> người</dd>
                <dt>Tiện nghi</dt>         <dd><?= $phong['tien_nghi'] ? e($phong['tien_nghi']) : '<span class="muted">Chưa cập nhật</span>' ?></dd>
                <dt>Trạng thái</dt>        <dd><?= nhan($phong['trang_thai'], TRANG_THAI_PHONG) ?></dd>
            </dl>
        </div>
    </div>

    <!-- Người đang ở (AC1, v0.2) -->
    <div class="card">
        <div class="card-head">
            <h2>Người đang ở</h2>
            <span class="muted"><?= so_nguoi(count($ds_nguoi_o), $phong['so_nguoi_toi_da']) ?> người</span>
        </div>
        <div class="card-body">
            <?php if ($hop_dong_hien_tai): ?>
                <?php
                $hd = $hop_dong_hien_tai;
                $con_lai = (int)floor((strtotime($hd['ngay_ket_thuc']) - strtotime(date('Y-m-d'))) / 86400);
                ?>
                <?php if ($ds_nguoi_o): ?>
                    <ul class="people">
                        <?php foreach ($ds_nguoi_o as $ng): ?>
                            <li class="tenant">
                                <span class="avatar"><?= e(chu_cai_dau($ng['ho_ten'])) ?></span>
                                <div>
                                    <strong><?= e($ng['ho_ten']) ?></strong>
                                    <?php if ((int)$ng['la_dai_dien'] === 1): ?>
                                        <span class="badge badge-dai_dien">Đại diện</span>
                                    <?php endif; ?>
                                    <div class="muted small">
                                        <?= e(sdt($ng['so_dien_thoai'])) ?> &middot; CCCD <?= e($ng['cccd']) ?> &middot; <?= e($ng['que_quan']) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-box people-empty">Chưa ghi nhận người ở cho hợp đồng này.</div>
                <?php endif; ?>
                <dl class="info-list">
                    <dt>Hợp đồng</dt>          <dd><?= e($hd['ma_hop_dong']) ?> <span class="muted">(đứng tên: <?= e($hd['ho_ten']) ?>)</span></dd>
                    <dt>Thời hạn</dt>
                    <dd>
                        <?= e(ngay($hd['ngay_bat_dau'])) ?> – <?= e(ngay($hd['ngay_ket_thuc'])) ?>
                        <?php if ($con_lai <= 30): ?>
                            <br><span class="badge badge-dang_sua">Sắp hết hạn: còn <?= max(0, $con_lai) ?> ngày</span>
                        <?php else: ?>
                            <span class="muted">(còn <?= $con_lai ?> ngày)</span>
                        <?php endif; ?>
                    </dd>
                    <dt>Giá theo hợp đồng</dt> <dd><strong><?= e(tien($hd['gia_thue'])) ?></strong> / tháng</dd>
                    <dt>Tiền cọc</dt>          <dd><?= e(tien($hd['tien_coc'])) ?></dd>
                </dl>
                <?php if ((int)$hd['gia_thue'] !== (int)$phong['gia_thue']): ?>
                    <p class="note">Giá theo hợp đồng khác giá niêm yết hiện tại vì giá được chốt tại lúc ký hợp đồng.</p>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-box">
                    <?= $phong['trang_thai'] === 'dang_sua' ? 'Phòng đang sửa chữa, chưa cho thuê.' : 'Phòng đang trống, chưa có khách ở.' ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Lịch sử hợp đồng (AC1) -->
<div class="card section">
    <div class="card-head">
        <h2>Lịch sử hợp đồng</h2>
        <span class="muted"><?= count($ds_hop_dong) ?> hợp đồng</span>
    </div>
    <?php if ($ds_hop_dong): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã hợp đồng</th>
                        <th>Khách thuê</th>
                        <th>Số điện thoại</th>
                        <th>Thời hạn</th>
                        <th class="num">Giá thuê</th>
                        <th class="num">Tiền cọc</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ds_hop_dong as $hd): ?>
                    <tr>
                        <td><?= e($hd['ma_hop_dong']) ?></td>
                        <td><?= e($hd['ho_ten']) ?></td>
                        <td><?= e(sdt($hd['so_dien_thoai'])) ?></td>
                        <td><?= e(ngay($hd['ngay_bat_dau'])) ?> – <?= e(ngay($hd['ngay_ket_thuc'])) ?></td>
                        <td class="num"><?= e(tien($hd['gia_thue'])) ?></td>
                        <td class="num"><?= e(tien($hd['tien_coc'])) ?></td>
                        <td><?= nhan($hd['trang_thai'], TRANG_THAI_HOP_DONG) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body"><div class="empty-box">Phòng chưa từng có hợp đồng nào.</div></div>
    <?php endif; ?>
</div>


<!-- Sự cố đã báo (AC2) -->
<div class="card section">
    <div class="card-head">
        <h2>Sự cố đã báo</h2>
        <span class="muted"><?= count($ds_su_co) ?> sự cố
            · <a href="bao_su_co.php?phong_id=<?= (int)$phong['id'] ?>">+ Ghi nhận sự cố</a>
        </span>
    </div>

    <?php if ($ds_su_co): ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mã theo dõi</th>
                        <th>Ngày báo</th>
                        <th>Loại</th>
                        <th>Mô tả</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ds_su_co as $sc): ?>
                        <tr>
                            <td class="nowrap">
                                <a href="su_co_chi_tiet.php?id=<?= (int)$sc['id'] ?>">
                                    <?= e($sc['ma_theo_doi']) ?>
                                </a>
                            </td>
                            <td class="nowrap"><?= e(ngay_gio($sc['ngay_bao'])) ?></td>
                            <td><?= e($sc['loai']) ?></td>
                            <td><?= e($sc['mo_ta']) ?></td>
                            <td><?= nhan($sc['trang_thai'], TRANG_THAI_SU_CO) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body">
            <div class="empty-box">Phòng chưa có sự cố nào được báo.</div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
