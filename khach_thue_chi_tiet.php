<?php
/**
 * =====================================================================
 *  CHI TIẾT KHÁCH THUÊ
 *  Thông tin cá nhân, ảnh CCCD (KT001), thời điểm sửa gần nhất (KT002),
 *  phòng đang ở, lịch sử các lần thuê và sự cố khách đã báo.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$id = (int)gia_tri_get('id', '0');
$stmt = db()->prepare('SELECT * FROM khach_thue WHERE id = ?');
$stmt->execute([$id]);
$khach = $stmt->fetch();
if (!$khach) {
    flash('error', 'Không tìm thấy khách thuê.');
    redirect('khach_thue.php');
}

// Lịch sử ở trọ: mỗi lần ở theo một hợp đồng
$stmt = db()->prepare('SELECT n.*, h.ma_hop_dong, h.id AS hop_dong_id, h.trang_thai AS trang_thai_hd,
                              p.id AS phong_id, p.ma_phong
                       FROM nguoi_o n
                       JOIN hop_dong h ON h.id = n.hop_dong_id
                       JOIN phong p    ON p.id = h.phong_id
                       WHERE n.khach_thue_id = ?
                       ORDER BY n.ngay_vao DESC');
$stmt->execute([$id]);
$lich_su = $stmt->fetchAll();

$dang_o = null;
foreach ($lich_su as $ls) {
    if ($ls['ngay_roi'] === null && $ls['trang_thai_hd'] === 'hieu_luc') {
        $dang_o = $ls;
        break;
    }
}

$stmt = db()->prepare('SELECT s.*, p.ma_phong FROM su_co s JOIN phong p ON p.id = s.phong_id
                       WHERE s.khach_thue_id = ? ORDER BY s.ngay_bao DESC');
$stmt->execute([$id]);
$ds_su_co = $stmt->fetchAll();

$page_title = $khach['ho_ten'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="khach_thue.php">Khách thuê</a> / <?= e($khach['ho_ten']) ?></p>
        <h1><?= e($khach['ho_ten']) ?>
            <?php if ($dang_o): ?>
                <span class="badge badge-dang_thue">Đang ở <?= e($dang_o['ma_phong']) ?></span>
            <?php else: ?>
                <span class="badge">Không ở trọ</span>
            <?php endif; ?>
        </h1>
        <p class="muted">
            Thêm vào hệ thống lúc <?= e(ngay_gio($khach['ngay_tao'])) ?>
            <?php if ($khach['ngay_cap_nhat']): ?>
                &middot; <strong>Sửa lần gần nhất lúc <?= e(ngay_gio($khach['ngay_cap_nhat'])) ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <div class="head-actions">
        <a class="btn" href="khach_thue_form.php?id=<?= (int)$khach['id'] ?>">Sửa thông tin</a>
    </div>
</div>

<div class="grid-2 even">
    <div class="card">
        <div class="card-head"><h2>Thông tin cá nhân</h2></div>
        <div class="card-body">
            <dl class="info-list">
                <dt>Họ tên</dt>        <dd><?= e($khach['ho_ten']) ?></dd>
                <dt>Số CCCD</dt>       <dd><?= e($khach['cccd']) ?></dd>
                <dt>Số điện thoại</dt> <dd><?= e(sdt($khach['so_dien_thoai'])) ?></dd>
                <dt>Quê quán</dt>      <dd><?= $khach['que_quan'] ? e($khach['que_quan']) : '<span class="muted">Chưa cập nhật</span>' ?></dd>
                <dt>Phòng đang ở</dt>
                <dd>
                    <?php if ($dang_o): ?>
                        <a href="phong_chi_tiet.php?id=<?= (int)$dang_o['phong_id'] ?>"><?= e($dang_o['ma_phong']) ?></a>
                        (<?= (int)$dang_o['la_dai_dien'] === 1 ? 'người đứng tên hợp đồng' : 'ở cùng' ?>,
                        từ <?= e(ngay($dang_o['ngay_vao'])) ?>)
                    <?php else: ?>
                        <span class="muted">Hiện không ở phòng nào</span>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Ảnh CCCD</h2></div>
        <div class="card-body">
            <?php if ($khach['anh_cccd']): ?>
                <a class="photo-link" href="<?= e($khach['anh_cccd']) ?>" target="_blank">
                    <img class="photo" src="<?= e($khach['anh_cccd']) ?>" alt="Ảnh CCCD của <?= e($khach['ho_ten']) ?>">
                </a>
            <?php else: ?>
                <div class="empty-box">Chưa tải lên ảnh CCCD.
                    <a href="khach_thue_form.php?id=<?= (int)$khach['id'] ?>">Tải ảnh lên</a></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card section">
    <div class="card-head">
        <h2>Lịch sử thuê phòng</h2>
        <span class="muted"><?= count($lich_su) ?> lần</span>
    </div>
    <?php if ($lich_su): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Phòng</th><th>Hợp đồng</th><th>Vai trò</th><th>Từ ngày</th><th>Đến ngày</th></tr></thead>
                <tbody>
                <?php foreach ($lich_su as $ls): ?>
                    <tr>
                        <td><a href="phong_chi_tiet.php?id=<?= (int)$ls['phong_id'] ?>"><?= e($ls['ma_phong']) ?></a></td>
                        <td><a href="hop_dong_chi_tiet.php?id=<?= (int)$ls['hop_dong_id'] ?>"><?= e($ls['ma_hop_dong']) ?></a></td>
                        <td><?= (int)$ls['la_dai_dien'] === 1 ? '<span class="badge badge-dai_dien">Đại diện</span>' : 'Ở cùng' ?></td>
                        <td><?= e(ngay($ls['ngay_vao'])) ?></td>
                        <td><?= $ls['ngay_roi'] ? e(ngay($ls['ngay_roi'])) : '<span class="badge badge-dang_thue">Đang ở</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body"><div class="empty-box">Khách chưa thuê phòng nào.</div></div>
    <?php endif; ?>
</div>

<div class="card section">
    <div class="card-head"><h2>Sự cố đã báo</h2><span class="muted"><?= count($ds_su_co) ?> sự cố</span></div>
    <?php if ($ds_su_co): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Mã</th><th>Phòng</th><th>Loại</th><th>Mô tả</th><th>Ngày báo</th><th>Trạng thái</th></tr></thead>
                <tbody>
                <?php foreach ($ds_su_co as $sc): ?>
                    <tr>
                        <td><a href="su_co_chi_tiet.php?id=<?= (int)$sc['id'] ?>"><?= e($sc['ma_theo_doi']) ?></a></td>
                        <td><?= e($sc['ma_phong']) ?></td>
                        <td><?= e($sc['loai']) ?></td>
                        <td><?= e($sc['mo_ta']) ?></td>
                        <td class="nowrap"><?= e(ngay_gio($sc['ngay_bao'])) ?></td>
                        <td><?= nhan($sc['trang_thai'], TRANG_THAI_SU_CO) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body"><div class="empty-box">Khách chưa báo sự cố nào.</div></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
