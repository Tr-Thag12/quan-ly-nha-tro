<?php
/**
 * =====================================================================
 *  HD005 – THANH LÝ HỢP ĐỒNG
 *    AC1: Còn hóa đơn chưa thu thì cảnh báo trước khi thanh lý
 *    AC2: Thanh lý xong phòng tự chuyển sang Trống
 *    AC3: Ghi lại số tiền cọc thực hoàn
 *
 *  Có thể trừ tiền nợ vào tiền cọc: hệ thống tự ghi các lần thanh toán
 *  hình thức "Trừ tiền cọc" cho hóa đơn còn nợ, cũ nhất trừ trước.
 *  Mọi thao tác nằm trong một giao dịch: hoặc làm hết, hoặc không làm gì.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$hd = lay_hop_dong(gia_tri_get('id', gia_tri_post('id', '0')));
if (!$hd) {
    flash('error', 'Không tìm thấy hợp đồng.');
    redirect('hop_dong.php');
}
if ($hd['trang_thai'] !== 'hieu_luc') {
    flash('error', 'Hợp đồng ' . $hd['ma_hop_dong'] . ' đã được thanh lý trước đó.');
    redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
}

// AC1: các hóa đơn còn nợ, cũ nhất trước
$stmt = db()->prepare('SELECT * FROM hoa_don WHERE hop_dong_id = ? AND da_thu < tong_tien ORDER BY thang');
$stmt->execute([$hd['id']]);
$hoa_don_no = $stmt->fetchAll();
$tong_no = 0;
foreach ($hoa_don_no as $h) {
    $tong_no += (int)$h['tong_tien'] - (int)$h['da_thu'];
}
$tien_coc = (int)$hd['tien_coc'];

// Tháng này đã ghi chỉ số / lập hóa đơn chưa? (nhắc chốt số trước khi khách đi)
$thang_nay = thang_hien_tai();
$da_chot_thang_nay = (bool)chi_so_thang($hd['phong_id'], $thang_nay);

$du_lieu = [
    'ngay_thanh_ly' => hom_nay(),
    'tru_coc'       => $tong_no > 0 ? '1' : '0',
    'tien_coc_hoan' => number_format(max(0, $tien_coc - $tong_no), 0, ',', '.'),
    'ghi_chu'       => '',
    'xac_nhan_no'   => '0',
];
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o, in_array($ten_o, ['tru_coc', 'xac_nhan_no'], true) ? '0' : '');
    }
    $tru_coc = $du_lieu['tru_coc'] === '1';

    if (!ngay_hop_le($du_lieu['ngay_thanh_ly'])) {
        $loi['ngay_thanh_ly'] = 'Vui lòng chọn ngày thanh lý.';
    } elseif ($du_lieu['ngay_thanh_ly'] > hom_nay()) {
        $loi['ngay_thanh_ly'] = 'Ngày thanh lý không được sau hôm nay (thanh lý vào đúng ngày khách trả phòng).';
    } elseif ($du_lieu['ngay_thanh_ly'] < $hd['ngay_bat_dau']) {
        $loi['ngay_thanh_ly'] = 'Ngày thanh lý không được trước ngày bắt đầu hợp đồng.';
    }

    // Số tiền sẽ trừ vào cọc: tối đa bằng tiền cọc
    $se_tru = $tru_coc ? min($tong_no, $tien_coc) : 0;
    $no_con_lai = $tong_no - $se_tru;

    // AC1: còn nợ mà không trừ hết vào cọc thì phải xác nhận đã biết
    if ($no_con_lai > 0 && $du_lieu['xac_nhan_no'] !== '1') {
        $loi['xac_nhan_no'] = 'Khách vẫn còn nợ ' . tien($no_con_lai) . '. Hãy tích xác nhận nếu vẫn muốn thanh lý.';
    }

    // AC3: tiền cọc thực hoàn, không vượt quá phần cọc còn lại sau khi trừ nợ
    $hoan = doc_so_tien($du_lieu['tien_coc_hoan']);
    $hoan_toi_da = $tien_coc - $se_tru;
    if ($hoan === null) {
        $loi['tien_coc_hoan'] = 'Nhập số tiền cọc hoàn lại cho khách (nhập 0 nếu không hoàn).';
    } elseif ($hoan > $hoan_toi_da) {
        $loi['tien_coc_hoan'] = 'Tối đa ' . tien($hoan_toi_da) . ' (tiền cọc ' . tien($tien_coc)
            . ($se_tru ? ' trừ ' . tien($se_tru) . ' tiền nợ' : '') . ').';
    }
    if ($hoan !== null && $hoan < $hoan_toi_da && trim($du_lieu['ghi_chu']) === '') {
        $loi['ghi_chu'] = 'Hoàn ít hơn ' . tien($hoan_toi_da) . ' thì cần ghi lý do (ví dụ: trừ tiền làm hỏng đồ).';
    }
    if (do_dai($du_lieu['ghi_chu']) > 255) {
        $loi['ghi_chu'] = 'Ghi chú tối đa 255 ký tự.';
    }

    if (!$loi) {
        db()->beginTransaction();
        try {
            // Trừ nợ vào tiền cọc, hóa đơn cũ nhất trừ trước
            $con_coc = $se_tru;
            foreach ($hoa_don_no as $h) {
                if ($con_coc <= 0) {
                    break;
                }
                $tru = min($con_coc, (int)$h['tong_tien'] - (int)$h['da_thu']);
                ghi_thanh_toan($h, $tru, $du_lieu['ngay_thanh_ly'], 'tru_coc', 'Trừ vào tiền cọc khi thanh lý hợp đồng');
                $con_coc -= $tru;
            }

            db()->prepare("UPDATE hop_dong SET trang_thai = 'da_thanh_ly', ngay_thanh_ly = ?, tien_coc_hoan = ?,
                                               ghi_chu_thanh_ly = ?
                           WHERE id = ?")
                ->execute([$du_lieu['ngay_thanh_ly'], $hoan, $du_lieu['ghi_chu'] !== '' ? $du_lieu['ghi_chu'] : null, $hd['id']]);

            // Mọi người trong phòng rời đi cùng ngày thanh lý
            db()->prepare('UPDATE nguoi_o SET ngay_roi = GREATEST(ngay_vao, ?) WHERE hop_dong_id = ? AND ngay_roi IS NULL')
                ->execute([$du_lieu['ngay_thanh_ly'], $hd['id']]);

            // AC2: phòng chuyển sang Trống
            db()->prepare("UPDATE phong SET trang_thai = 'trong' WHERE id = ?")->execute([$hd['phong_id']]);

            db()->commit();
        } catch (Exception $loi_luu) {
            db()->rollBack();
            throw $loi_luu;
        }

        $tin = 'Đã thanh lý hợp đồng ' . $hd['ma_hop_dong'] . '. Phòng ' . $hd['ma_phong'] . ' đã chuyển sang Trống. '
             . 'Tiền cọc hoàn cho khách: ' . tien($hoan) . '.';
        if ($se_tru) {
            $tin .= ' Đã trừ ' . tien($se_tru) . ' tiền nợ vào tiền cọc.';
        }
        if ($no_con_lai > 0) {
            $tin .= ' Khách còn nợ ' . tien($no_con_lai) . ', vẫn hiện trong báo cáo công nợ.';
        }
        flash('success', $tin);
        redirect('hop_dong_chi_tiet.php?id=' . $hd['id']);
    }
}

$page_title = 'Thanh lý ' . $hd['ma_hop_dong'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="hop_dong.php">Hợp đồng</a> /
            <a href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>"><?= e($hd['ma_hop_dong']) ?></a> / Thanh lý</p>
        <h1>Thanh lý hợp đồng <?= e($hd['ma_hop_dong']) ?></h1>
        <p class="muted">Phòng <?= e($hd['ma_phong']) ?> · Đứng tên <?= e($hd['ho_ten']) ?> · Tiền cọc <?= e(tien($tien_coc)) ?></p>
    </div>
</div>

<?php if (!$da_chot_thang_nay): ?>
    <div class="alert alert-info">
        Nếu khách trả phòng giữa tháng, hãy <a href="chi_so_ghi.php?phong_id=<?= (int)$hd['phong_id'] ?>&amp;thang=<?= e($thang_nay) ?>">ghi chỉ số</a>
        và lập hóa đơn tháng <?= e(ten_thang($thang_nay)) ?> trước khi thanh lý, để tính đủ tiền điện nước.
    </div>
<?php endif; ?>

<?php if ($hoa_don_no): ?>
    <!-- AC1: cảnh báo còn hóa đơn chưa thu -->
    <div class="box-warn">
        <strong>Khách còn <?= count($hoa_don_no) ?> hóa đơn chưa thu, tổng nợ <?= e(tien($tong_no)) ?>:</strong>
        <ul>
            <?php foreach ($hoa_don_no as $h): ?>
                <li><a href="hoa_don_chi_tiet.php?id=<?= (int)$h['id'] ?>">Tháng <?= e(ten_thang($h['thang'])) ?></a>:
                    còn nợ <?= e(tien($h['tong_tien'] - $h['da_thu'])) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="card form-card">
    <form method="post" novalidate
          data-confirm="Thanh lý hợp đồng <?= e($hd['ma_hop_dong']) ?> và trả phòng <?= e($hd['ma_phong']) ?>? Sau khi thanh lý không mở lại được hợp đồng.">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$hd['id'] ?>">
        <div class="form-grid">
            <div class="field">
                <label for="ngay_thanh_ly">Ngày thanh lý (ngày khách trả phòng) <span class="required">*</span></label>
                <input type="date" id="ngay_thanh_ly" name="ngay_thanh_ly" value="<?= e($du_lieu['ngay_thanh_ly']) ?>" max="<?= e(hom_nay()) ?>"
                       class="<?= lop_loi($loi, 'ngay_thanh_ly') ?>">
                <?= loi_o($loi, 'ngay_thanh_ly') ?>
            </div>
            <div class="field">
                <label for="tien_coc_hoan">Tiền cọc thực hoàn cho khách (đ) <span class="required">*</span></label>
                <input type="text" id="tien_coc_hoan" name="tien_coc_hoan" inputmode="numeric" value="<?= e($du_lieu['tien_coc_hoan']) ?>"
                       class="<?= lop_loi($loi, 'tien_coc_hoan') ?>">
                <?php if (isset($loi['tien_coc_hoan'])): ?>
                    <?= loi_o($loi, 'tien_coc_hoan') ?>
                <?php else: ?>
                    <div class="hint">Gợi ý sẵn: tiền cọc trừ tiền nợ.</div>
                <?php endif; ?>
            </div>
            <?php if ($tong_no > 0): ?>
                <div class="field full">
                    <label class="check-row">
                        <input type="checkbox" name="tru_coc" value="1"<?= $du_lieu['tru_coc'] === '1' ? ' checked' : '' ?>>
                        <span>Trừ tiền nợ vào tiền cọc (trừ tối đa <?= e(tien(min($tong_no, $tien_coc))) ?>; hệ thống tự ghi thanh toán
                            "Trừ tiền cọc" cho các hóa đơn còn nợ)</span>
                    </label>
                </div>
                <div class="field full">
                    <label class="check-row">
                        <input type="checkbox" name="xac_nhan_no" value="1"<?= $du_lieu['xac_nhan_no'] === '1' ? ' checked' : '' ?>>
                        <span>Tôi đã biết khách còn nợ và vẫn muốn thanh lý hợp đồng</span>
                    </label>
                    <?= loi_o($loi, 'xac_nhan_no') ?>
                </div>
            <?php endif; ?>
            <div class="field full">
                <label for="ghi_chu">Ghi chú</label>
                <input type="text" id="ghi_chu" name="ghi_chu" maxlength="255" value="<?= e($du_lieu['ghi_chu']) ?>"
                       placeholder="Ví dụ: Khách trả phòng đúng hạn, phòng sạch sẽ" class="<?= lop_loi($loi, 'ghi_chu') ?>">
                <?= loi_o($loi, 'ghi_chu') ?>
            </div>
        </div>
        <p class="small muted">Sau khi thanh lý: hợp đồng khóa lại, mọi người trong phòng được ghi là đã rời đi,
            phòng <?= e($hd['ma_phong']) ?> chuyển sang Trống để cho khách mới thuê.</p>
        <div class="form-actions">
            <a class="btn" href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>">Hủy</a>
            <button type="submit" class="btn btn-danger">Xác nhận thanh lý</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
