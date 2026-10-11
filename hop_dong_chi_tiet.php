<?php
/**
 * =====================================================================
 *  HD003 – XEM CHI TIẾT HỢP ĐỒNG
 *    AC1: Hiển thị đủ thông tin phòng, khách, cọc, đơn giá áp dụng
 *    AC2: In được hợp đồng ra giấy  -> nút "In hợp đồng" mở hop_dong_in.php
 *
 *  Trang này cũng là nơi quản lý:
 *    KT005 – người ở cùng phòng (thêm người, người rời đi)
 *    HD004 – lịch sử gia hạn        HD005 – nút thanh lý
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$hd = lay_hop_dong(gia_tri_get('id', '0'));
if (!$hd) {
    flash('error', 'Không tìm thấy hợp đồng.');
    redirect('hop_dong.php');
}
$con_hieu_luc = $hd['trang_thai'] === 'hieu_luc';

// KT005: người đang ở và người đã rời đi
$dang_o = nguoi_dang_o($hd['id']);
$stmt = db()->prepare('SELECT n.*, k.ho_ten, k.so_dien_thoai FROM nguoi_o n JOIN khach_thue k ON k.id = n.khach_thue_id
                       WHERE n.hop_dong_id = ? AND n.ngay_roi IS NOT NULL ORDER BY n.ngay_roi DESC');
$stmt->execute([$hd['id']]);
$da_roi = $stmt->fetchAll();

// HD004: lịch sử gia hạn
$stmt = db()->prepare('SELECT g.*, t.ho_ten AS nguoi FROM gia_han g LEFT JOIN tai_khoan t ON t.id = g.nguoi_thuc_hien
                       WHERE g.hop_dong_id = ? ORDER BY g.thoi_diem DESC');
$stmt->execute([$hd['id']]);
$ds_gia_han = $stmt->fetchAll();

// Hóa đơn của hợp đồng
$stmt = db()->prepare('SELECT * FROM hoa_don WHERE hop_dong_id = ? ORDER BY thang DESC');
$stmt->execute([$hd['id']]);
$ds_hoa_don = $stmt->fetchAll();
$tong_no = tong_no_hop_dong($hd['id']);

// AC1: đơn giá dịch vụ đang áp dụng hôm nay
list($don_gia_nay) = bang_don_gia(hom_nay());

$page_title = 'Hợp đồng ' . $hd['ma_hop_dong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hop_dong.php">Hợp đồng</a> / <?= e($hd['ma_hop_dong']) ?></p>
        <h1>Hợp đồng <?= e($hd['ma_hop_dong']) ?> <?= nhan($hd['trang_thai'], TRANG_THAI_HOP_DONG) ?>
            <?php if ($con_hieu_luc): ?><?= nhan_con_lai($hd['ngay_ket_thuc']) ?><?php endif; ?>
        </h1>
        <p class="muted">Phòng <?= e($hd['ma_phong']) ?> &middot; Đứng tên <?= e($hd['ho_ten']) ?>
            &middot; <?= e(ngay($hd['ngay_bat_dau'])) ?> – <?= e(ngay($hd['ngay_ket_thuc'])) ?></p>
    </div>
    <div class="head-actions">
        <a class="btn" href="hop_dong_in.php?id=<?= (int)$hd['id'] ?>" target="_blank">In hợp đồng</a>
        <?php if ($con_hieu_luc): ?>
            <a class="btn" href="hop_dong_gia_han.php?id=<?= (int)$hd['id'] ?>">Gia hạn</a>
            <a class="btn btn-danger" href="hop_dong_thanh_ly.php?id=<?= (int)$hd['id'] ?>">Thanh lý</a>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2 even">
    <!-- AC1: thông tin phòng, khách, cọc -->
    <div class="card">
        <div class="card-head"><h2>Thông tin hợp đồng</h2></div>
        <div class="card-body">
            <dl class="info-list">
                <dt>Phòng</dt>
                <dd><a href="phong_chi_tiet.php?id=<?= (int)$hd['phong_id'] ?>"><?= e($hd['ma_phong']) ?></a>
                    <span class="muted">(tối đa <?= (int)$hd['so_nguoi_toi_da'] ?> người)</span></dd>
                <dt>Người đứng tên</dt>
                <dd><a href="khach_thue_chi_tiet.php?id=<?= (int)$hd['khach_thue_id'] ?>"><?= e($hd['ho_ten']) ?></a>
                    <span class="muted">· <?= e(sdt($hd['so_dien_thoai'])) ?> · CCCD <?= e($hd['cccd']) ?></span></dd>
                <dt>Thời hạn</dt>
                <dd><?= e(ngay($hd['ngay_bat_dau'])) ?> – <?= e(ngay($hd['ngay_ket_thuc'])) ?></dd>
                <dt>Giá thuê chốt</dt>
                <dd><strong><?= e(tien($hd['gia_thue'])) ?></strong> / tháng
                    <?php if ((int)$hd['gia_thue'] !== (int)$hd['gia_niem_yet']): ?>
                        <span class="muted">(giá niêm yết hiện tại <?= e(tien($hd['gia_niem_yet'])) ?>)</span>
                    <?php endif; ?></dd>
                <dt>Tiền cọc</dt>          <dd><?= e(tien($hd['tien_coc'])) ?></dd>
                <dt>Số xe gửi</dt>         <dd><?= (int)$hd['so_xe'] ?> xe</dd>
                <dt>Chỉ số nhận phòng</dt> <dd>Điện <?= (int)$hd['chi_so_dien_dau'] ?> · Nước <?= (int)$hd['chi_so_nuoc_dau'] ?></dd>
                <?php if (!$con_hieu_luc): ?>
                    <dt>Ngày thanh lý</dt>   <dd><?= e(ngay($hd['ngay_thanh_ly'])) ?></dd>
                    <dt>Tiền cọc đã hoàn</dt><dd><strong><?= e(tien($hd['tien_coc_hoan'])) ?></strong></dd>
                    <dt>Ghi chú</dt>         <dd><?= $hd['ghi_chu_thanh_ly'] ? e($hd['ghi_chu_thanh_ly']) : '<span class="muted">—</span>' ?></dd>
                <?php endif; ?>
                <dt>Còn nợ</dt>
                <dd><?= $tong_no > 0 ? '<strong class="text-red">' . e(tien($tong_no)) . '</strong>' : '<span class="text-green">Không nợ</span>' ?></dd>
            </dl>
        </div>
    </div>

    <!-- AC1: đơn giá áp dụng -->
    <div class="card">
        <div class="card-head"><h2>Đơn giá dịch vụ đang áp dụng</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Dịch vụ</th><th class="num">Đơn giá</th><th>Cách tính</th></tr></thead>
                <tbody>
                    <tr><td>Tiền phòng</td><td class="num"><?= e(tien($hd['gia_thue'])) ?></td><td class="muted">theo hợp đồng</td></tr>
                    <?php foreach (DICH_VU as $ma => $dv): ?>
                        <tr>
                            <td><?= e($dv['ten']) ?></td>
                            <td class="num"><?= $don_gia_nay[$ma] !== null ? e(tien($don_gia_nay[$ma])) : '<span class="text-red">Chưa đặt</span>' ?></td>
                            <td class="muted"><?= e($dv['don_vi']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- KT005: người ở cùng phòng -->
<div class="card section">
    <div class="card-head">
        <h2>Người ở trong phòng</h2>
        <div class="head-actions">
            <span class="muted"><?= so_nguoi(count($dang_o), $hd['so_nguoi_toi_da']) ?> người</span>
            <?php if ($con_hieu_luc): ?>
                <?php if (count($dang_o) < (int)$hd['so_nguoi_toi_da']): ?>
                    <a class="btn btn-sm btn-primary" href="nguoi_o_them.php?hop_dong_id=<?= (int)$hd['id'] ?>">+ Thêm người ở cùng</a>
                <?php else: ?>
                    <span class="badge badge-sap_het_han">Phòng đã đủ <?= (int)$hd['so_nguoi_toi_da'] ?> người</span>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($dang_o): ?>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Họ tên</th><th>Số điện thoại</th><th>Số CCCD</th><th>Vào ở từ</th><th class="actions">Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($dang_o as $ng): ?>
                    <tr>
                        <td><a href="khach_thue_chi_tiet.php?id=<?= (int)$ng['id'] ?>"><?= e($ng['ho_ten']) ?></a>
                            <?php if ((int)$ng['la_dai_dien'] === 1): ?><span class="badge badge-dai_dien">Đại diện</span><?php endif; ?></td>
                        <td class="nowrap"><?= e(sdt($ng['so_dien_thoai'])) ?></td>
                        <td><?= e($ng['cccd']) ?></td>
                        <td><?= e(ngay($ng['ngay_vao'])) ?></td>
                        <td class="actions">
                            <?php if ($con_hieu_luc && (int)$ng['la_dai_dien'] !== 1): ?>
                                <form method="post" action="nguoi_o_roi.php" class="inline"
                                      data-confirm="Xác nhận <?= e($ng['ho_ten']) ?> đã rời phòng <?= e($hd['ma_phong']) ?> hôm nay?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="nguoi_o_id" value="<?= (int)$ng['nguoi_o_id'] ?>">
                                    <button type="submit" class="btn btn-sm">Rời phòng</button>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="card-body"><div class="empty-box">Không còn ai ở theo hợp đồng này.</div></div>
    <?php endif; ?>
    <?php if ($da_roi): ?>
        <div class="card-body">
            <p class="small muted" style="margin:0">Đã rời đi:
                <?php foreach ($da_roi as $i => $r): ?>
                    <?= $i ? ', ' : '' ?><?= e($r['ho_ten']) ?> (<?= e(ngay($r['ngay_vao'])) ?> – <?= e(ngay($r['ngay_roi'])) ?>)
                <?php endforeach; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<div class="grid-2 section">
    <!-- Hóa đơn -->
    <div class="card">
        <div class="card-head"><h2>Hóa đơn</h2><span class="muted"><?= count($ds_hoa_don) ?> hóa đơn</span></div>
        <?php if ($ds_hoa_don): ?>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Tháng</th><th class="num">Tổng tiền</th><th class="num">Còn nợ</th><th>Trạng thái</th></tr></thead>
                    <tbody>
                    <?php foreach ($ds_hoa_don as $h): ?>
                        <tr>
                            <td><a href="hoa_don_chi_tiet.php?id=<?= (int)$h['id'] ?>"><?= e(ten_thang($h['thang'])) ?></a></td>
                            <td class="num"><?= e(tien($h['tong_tien'])) ?></td>
                            <td class="num"><?= (int)$h['tong_tien'] > (int)$h['da_thu'] ? e(tien($h['tong_tien'] - $h['da_thu'])) : '<span class="muted">—</span>' ?></td>
                            <td><?= nhan($h['trang_thai'], TRANG_THAI_HOA_DON) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card-body"><div class="empty-box">Chưa có hóa đơn. Hóa đơn được lập sau khi ghi chỉ số cuối tháng.</div></div>
        <?php endif; ?>
    </div>

    <!-- HD004: lịch sử gia hạn -->
    <div class="card">
        <div class="card-head"><h2>Lịch sử gia hạn</h2><span class="muted"><?= count($ds_gia_han) ?> lần</span></div>
        <div class="card-body">
            <?php if ($ds_gia_han): ?>
                <ul class="timeline">
                    <?php foreach ($ds_gia_han as $g): ?>
                        <li>
                            <strong><?= e(ngay($g['ngay_ket_thuc_cu'])) ?> &rarr; <?= e(ngay($g['ngay_ket_thuc_moi'])) ?></strong>
                            <div class="when"><?= e(ngay_gio($g['thoi_diem'])) ?><?= $g['nguoi'] ? ' · ' . e($g['nguoi']) : '' ?></div>
                            <?php if ($g['ghi_chu']): ?><div class="small"><?= e($g['ghi_chu']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-box">Hợp đồng chưa gia hạn lần nào.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
