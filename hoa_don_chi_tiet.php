<?php
/**
 * =====================================================================
 *  TT002 – XEM CHI TIẾT HÓA ĐƠN
 *    AC1: Hiển thị chỉ số đầu, chỉ số cuối, đơn giá của từng khoản
 *    AC2: Hiển thị rõ tổng tiền phải thu
 *
 *  TT003 – GHI NHẬN THANH TOÁN (form ở cuối trang)
 *    AC1: Ghi nhận được thu đủ hoặc thu một phần
 *    AC2: Ghi ngày thu và người thu
 *    AC3: Hóa đơn đã thu đủ thì không sửa được nữa
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$id = (int)gia_tri_get('id', gia_tri_post('id', '0'));

/** Lấy hóa đơn kèm phòng và người đại diện. */
function lay_hoa_don($id)
{
    $stmt = db()->prepare('SELECT hd.*, p.ma_phong, h.ma_hop_dong, h.id AS hop_dong_id, k.ho_ten, k.so_dien_thoai
                           FROM hoa_don hd
                           JOIN phong p      ON p.id = hd.phong_id
                           JOIN hop_dong h   ON h.id = hd.hop_dong_id
                           JOIN khach_thue k ON k.id = h.khach_thue_id
                           WHERE hd.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

$hd = lay_hoa_don($id);
if (!$hd) {
    flash('error', 'Không tìm thấy hóa đơn.');
    redirect('hoa_don.php');
}

$con_no = (int)$hd['tong_tien'] - (int)$hd['da_thu'];
$du_lieu = [
    'so_tien'   => number_format(max(0, $con_no), 0, ',', '.'),
    'ngay_thu'  => hom_nay(),
    'hinh_thuc' => 'tien_mat',
    'ghi_chu'   => '',
];
$loi = [];

// TT003: ghi nhận thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o);
    }
    if ($con_no <= 0) {
        // AC3: đã thu đủ thì khóa
        $loi['chung'] = 'Hóa đơn này đã thu đủ, không thể ghi thêm thanh toán.';
    }
    $so_tien = doc_so_tien($du_lieu['so_tien']);
    if ($so_tien === null || $so_tien <= 0) {
        $loi['so_tien'] = 'Số tiền thu phải là số dương.';
    } elseif ($so_tien > $con_no) {
        $loi['so_tien'] = 'Số tiền thu không được lớn hơn số còn nợ (' . tien($con_no) . ').';
    }
    // AC2: ngày thu
    if (!ngay_hop_le($du_lieu['ngay_thu'])) {
        $loi['ngay_thu'] = 'Vui lòng chọn ngày thu.';
    } elseif ($du_lieu['ngay_thu'] > hom_nay()) {
        $loi['ngay_thu'] = 'Ngày thu không được sau hôm nay.';
    } elseif ($du_lieu['ngay_thu'] < substr($hd['ngay_tao'], 0, 10)) {
        $loi['ngay_thu'] = 'Ngày thu không được trước ngày lập hóa đơn (' . ngay($hd['ngay_tao']) . ').';
    }
    if (!in_array($du_lieu['hinh_thuc'], ['tien_mat', 'chuyen_khoan'], true)) {
        $loi['hinh_thuc'] = 'Vui lòng chọn hình thức thanh toán.';
    }
    if (do_dai($du_lieu['ghi_chu']) > 255) {
        $loi['ghi_chu'] = 'Ghi chú tối đa 255 ký tự.';
    }

    if (!$loi) {
        db()->beginTransaction();
        try {
            $da_thu = ghi_thanh_toan($hd, $so_tien, $du_lieu['ngay_thu'], $du_lieu['hinh_thuc'], $du_lieu['ghi_chu']);
            db()->commit();
        } catch (Exception $loi_luu) {
            db()->rollBack();
            throw $loi_luu;
        }
        $con_lai = (int)$hd['tong_tien'] - $da_thu;
        flash('success', 'Đã ghi nhận thu ' . tien($so_tien) . ' của phòng ' . $hd['ma_phong'] . '. '
            . ($con_lai > 0 ? 'Còn nợ ' . tien($con_lai) . '.' : 'Hóa đơn đã thu đủ.'));
        redirect('hoa_don_chi_tiet.php?id=' . $hd['id']);
    }
}

$stmt = db()->prepare('SELECT t.*, tk.ho_ten AS nguoi FROM thanh_toan t LEFT JOIN tai_khoan tk ON tk.id = t.nguoi_thu
                       WHERE t.hoa_don_id = ? ORDER BY t.ngay_thu, t.id');
$stmt->execute([$hd['id']]);
$ds_thanh_toan = $stmt->fetchAll();

$qua_han = $con_no > 0 ? so_ngay($hd['han_thanh_toan'], hom_nay()) : 0;

// DN003: còn sửa chỉ số được không (hóa đơn chưa thu và là lần ghi mới nhất)
$cs = chi_so_thang($hd['phong_id'], $hd['thang']);
$sua_chi_so = $cs && ly_do_khong_sua_chi_so($cs) === null;

$page_title = 'Hóa đơn ' . $hd['ma_phong'] . ' tháng ' . ten_thang($hd['thang']);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hoa_don.php?thang=<?= e($hd['thang']) ?>">Hóa đơn tháng <?= e(ten_thang($hd['thang'])) ?></a> / <?= e($hd['ma_hoa_don']) ?></p>
        <h1>Hóa đơn phòng <?= e($hd['ma_phong']) ?> – tháng <?= e(ten_thang($hd['thang'])) ?>
            <?= nhan($hd['trang_thai'], TRANG_THAI_HOA_DON) ?></h1>
        <p class="muted">Mã <?= e($hd['ma_hoa_don']) ?> · Hợp đồng
            <a href="hop_dong_chi_tiet.php?id=<?= (int)$hd['hop_dong_id'] ?>"><?= e($hd['ma_hop_dong']) ?></a>
            · Đại diện <?= e($hd['ho_ten']) ?> (<?= e(sdt($hd['so_dien_thoai'])) ?>) · Lập ngày <?= e(ngay($hd['ngay_tao'])) ?></p>
    </div>
    <div class="head-actions">
        <?php if ($sua_chi_so): ?>
            <a class="btn" href="chi_so_ghi.php?phong_id=<?= (int)$hd['phong_id'] ?>&amp;thang=<?= e($hd['thang']) ?>">Sửa chỉ số</a>
        <?php endif; ?>
        <a class="btn btn-primary" href="hoa_don_in.php?id=<?= (int)$hd['id'] ?>" target="_blank">In hóa đơn</a>
    </div>
</div>

<div class="grid-2">
    <!-- TT002: chi tiết khoản mục -->
    <div class="card">
        <div class="card-head"><h2>Chi tiết các khoản</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Khoản</th><th class="num">Chỉ số đầu</th><th class="num">Chỉ số cuối</th><th class="num">Sử dụng</th>
                        <th class="num">Đơn giá</th><th class="num">Thành tiền</th></tr>
                </thead>
                <tbody>
                    <tr><td>Tiền phòng</td><td class="num">—</td><td class="num">—</td><td class="num">1 tháng</td>
                        <td class="num"><?= e(tien($hd['tien_phong'])) ?></td><td class="num"><?= e(tien($hd['tien_phong'])) ?></td></tr>
                    <tr><td>Điện</td><td class="num"><?= (int)$hd['dien_cu'] ?></td><td class="num"><?= (int)$hd['dien_moi'] ?></td>
                        <td class="num"><?= $hd['dien_moi'] - $hd['dien_cu'] ?> kWh</td>
                        <td class="num"><?= e(tien($hd['don_gia_dien'])) ?></td><td class="num"><?= e(tien($hd['tien_dien'])) ?></td></tr>
                    <tr><td>Nước</td><td class="num"><?= (int)$hd['nuoc_cu'] ?></td><td class="num"><?= (int)$hd['nuoc_moi'] ?></td>
                        <td class="num"><?= $hd['nuoc_moi'] - $hd['nuoc_cu'] ?> m³</td>
                        <td class="num"><?= e(tien($hd['don_gia_nuoc'])) ?></td><td class="num"><?= e(tien($hd['tien_nuoc'])) ?></td></tr>
                    <tr><td>Rác</td><td class="num">—</td><td class="num">—</td><td class="num"><?= (int)$hd['so_nguoi'] ?> người</td>
                        <td class="num"><?= e(tien($hd['don_gia_rac'])) ?></td><td class="num"><?= e(tien($hd['tien_rac'])) ?></td></tr>
                    <tr><td>Wifi</td><td class="num">—</td><td class="num">—</td><td class="num">1 phòng</td>
                        <td class="num"><?= e(tien($hd['don_gia_wifi'])) ?></td><td class="num"><?= e(tien($hd['tien_wifi'])) ?></td></tr>
                    <tr><td>Gửi xe</td><td class="num">—</td><td class="num">—</td><td class="num"><?= (int)$hd['so_xe'] ?> xe</td>
                        <td class="num"><?= e(tien($hd['don_gia_gui_xe'])) ?></td><td class="num"><?= e(tien($hd['tien_gui_xe'])) ?></td></tr>
                </tbody>
                <tfoot>
                    <!-- AC2: tổng tiền phải thu -->
                    <tr><td colspan="5">Tổng tiền phải thu</td><td class="num"><?= e(tien($hd['tong_tien'])) ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- TT003: thanh toán -->
    <div class="card">
        <div class="card-head"><h2>Thanh toán</h2></div>
        <div class="card-body">
            <dl class="info-list">
                <dt>Tổng tiền</dt><dd class="strong"><?= e(tien($hd['tong_tien'])) ?></dd>
                <dt>Đã thu</dt><dd class="text-green strong"><?= e(tien($hd['da_thu'])) ?></dd>
                <dt>Còn nợ</dt><dd class="<?= $con_no > 0 ? 'text-red strong' : '' ?>"><?= e(tien(max(0, $con_no))) ?></dd>
                <dt>Hạn thu</dt>
                <dd><?= e(ngay($hd['han_thanh_toan'])) ?>
                    <?php if ($con_no > 0 && $qua_han > 0): ?><span class="badge badge-qua_han">Quá hạn <?= $qua_han ?> ngày</span><?php endif; ?></dd>
            </dl>

            <?php if ($ds_thanh_toan): ?>
                <ul class="timeline mt">
                    <?php foreach ($ds_thanh_toan as $t): ?>
                        <li class="da_xong">
                            <strong><?= e(tien($t['so_tien'])) ?></strong> · <?= e(HINH_THUC_THANH_TOAN[$t['hinh_thuc']]) ?>
                            <div class="when">Ngày <?= e(ngay($t['ngay_thu'])) ?> · Người thu: <?= e($t['nguoi'] ?: '—') ?></div>
                            <?php if ($t['ghi_chu']): ?><div class="small"><?= e($t['ghi_chu']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($con_no <= 0): ?>
                <div class="alert alert-success mt">Hóa đơn đã thu đủ. Không thể ghi thêm thanh toán hay sửa chỉ số của tháng này.</div>
            <?php else: ?>
                <form method="post" class="form-section" novalidate>
                    <h3>Ghi nhận thanh toán</h3>
                    <?php if (isset($loi['chung'])): ?><div class="alert alert-error"><?= e($loi['chung']) ?></div><?php endif; ?>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$hd['id'] ?>">
                    <div class="form-grid">
                        <div class="field">
                            <label for="so_tien">Số tiền thu (đ) <span class="required">*</span></label>
                            <input type="text" id="so_tien" name="so_tien" inputmode="numeric" value="<?= e($du_lieu['so_tien']) ?>"
                                   class="<?= lop_loi($loi, 'so_tien') ?>">
                            <?php if (isset($loi['so_tien'])): ?>
                                <?= loi_o($loi, 'so_tien') ?>
                            <?php else: ?>
                                <div class="hint">Thu đủ hoặc gõ số nhỏ hơn để thu một phần.</div>
                            <?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="ngay_thu">Ngày thu <span class="required">*</span></label>
                            <input type="date" id="ngay_thu" name="ngay_thu" value="<?= e($du_lieu['ngay_thu']) ?>" max="<?= e(hom_nay()) ?>"
                                   class="<?= lop_loi($loi, 'ngay_thu') ?>">
                            <?= loi_o($loi, 'ngay_thu') ?>
                        </div>
                        <div class="field">
                            <label for="hinh_thuc">Hình thức</label>
                            <select id="hinh_thuc" name="hinh_thuc" class="<?= lop_loi($loi, 'hinh_thuc') ?>">
                                <option value="tien_mat"<?= $du_lieu['hinh_thuc'] === 'tien_mat' ? ' selected' : '' ?>>Tiền mặt</option>
                                <option value="chuyen_khoan"<?= $du_lieu['hinh_thuc'] === 'chuyen_khoan' ? ' selected' : '' ?>>Chuyển khoản</option>
                            </select>
                            <?= loi_o($loi, 'hinh_thuc') ?>
                        </div>
                        <div class="field">
                            <label for="ghi_chu">Ghi chú</label>
                            <input type="text" id="ghi_chu" name="ghi_chu" maxlength="255" value="<?= e($du_lieu['ghi_chu']) ?>"
                                   class="<?= lop_loi($loi, 'ghi_chu') ?>">
                            <?= loi_o($loi, 'ghi_chu') ?>
                        </div>
                    </div>
                    <p class="small muted">Người thu được ghi là <strong><?= e(current_user()['ho_ten']) ?></strong>.</p>
                    <button type="submit" class="btn btn-primary">Ghi nhận thanh toán</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
