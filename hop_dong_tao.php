<?php
/**
 * =====================================================================
 *  HD001 – TẠO HỢP ĐỒNG THUÊ
 *    AC1: Chỉ chọn được phòng đang Trống
 *    AC2: Tạo xong phòng tự chuyển sang Đang thuê
 *    AC3: Bắt buộc có ngày bắt đầu, thời hạn và tiền cọc
 *
 *  Lưu cùng lúc 3 việc trong một giao dịch (transaction) để không bao giờ
 *  bị "tạo hợp đồng xong mà phòng vẫn Trống":
 *    1. thêm hợp đồng   2. thêm người đứng tên vào bảng nguoi_o   3. đổi trạng thái phòng
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

// AC1: chỉ lấy phòng đang Trống
$phong_trong = db()->query("SELECT * FROM phong WHERE trang_thai = 'trong' ORDER BY tang, ma_phong")->fetchAll();
$ds_khach    = khach_chua_o_dau();

$du_lieu = [
    'phong_id'        => gia_tri_get('phong_id', ''),
    'khach_thue_id'   => gia_tri_get('khach_id', ''),
    'ngay_bat_dau'    => hom_nay(),
    'thoi_han'        => '12',
    'gia_thue'        => '',
    'tien_coc'        => '',
    'so_xe'           => '1',
    'chi_so_dien_dau' => '',
    'chi_so_nuoc_dau' => '',
];

// Gợi ý sẵn giá thuê, tiền cọc và chỉ số công tơ gần nhất của từng phòng
$goi_y = [];
foreach ($phong_trong as $p) {
    $cs = chi_so_gan_nhat_cua_phong($p['id']);
    $goi_y[$p['id']] = [
        'gia_thue'        => number_format($p['gia_thue'], 0, ',', '.'),
        'tien_coc'        => number_format($p['gia_thue'], 0, ',', '.'),
        'chi_so_dien_dau' => $cs ? (int)$cs['dien_moi'] : 0,
        'chi_so_nuoc_dau' => $cs ? (int)$cs['nuoc_moi'] : 0,
        'chi_so_toi_thieu' => $cs ? [(int)$cs['dien_moi'], (int)$cs['nuoc_moi']] : [0, 0],
    ];
}
if ($du_lieu['phong_id'] !== '' && isset($goi_y[(int)$du_lieu['phong_id']])) {
    foreach (['gia_thue', 'tien_coc', 'chi_so_dien_dau', 'chi_so_nuoc_dau'] as $o) {
        $du_lieu[$o] = (string)$goi_y[(int)$du_lieu['phong_id']][$o];
    }
}

$loi = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $gia_tri_cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o);
    }

    // AC1: phòng phải đang Trống (kiểm tra lại lúc lưu, phòng có thể vừa được người khác cho thuê)
    $stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
    $stmt->execute([(int)$du_lieu['phong_id']]);
    $phong = $stmt->fetch();
    if (!$phong) {
        $loi['phong_id'] = 'Vui lòng chọn phòng.';
    } elseif ($phong['trang_thai'] !== 'trong') {
        $loi['phong_id'] = 'Phòng ' . $phong['ma_phong'] . ' không còn Trống, hãy chọn phòng khác.';
    }

    $stmt = db()->prepare('SELECT * FROM khach_thue WHERE id = ?');
    $stmt->execute([(int)$du_lieu['khach_thue_id']]);
    $khach = $stmt->fetch();
    if (!$khach) {
        $loi['khach_thue_id'] = 'Vui lòng chọn người đứng tên hợp đồng.';
    } elseif (($dang_o = phong_dang_o_cua_khach($khach['id'])) !== null) {
        $loi['khach_thue_id'] = $khach['ho_ten'] . ' đang ở phòng ' . $dang_o . ', không thể đứng tên thêm phòng khác.';
    }

    // AC3: bắt buộc ngày bắt đầu, thời hạn, tiền cọc
    if (!ngay_hop_le($du_lieu['ngay_bat_dau'])) {
        $loi['ngay_bat_dau'] = 'Vui lòng chọn ngày bắt đầu.';
    } elseif ($du_lieu['ngay_bat_dau'] > date('Y-m-d', strtotime('+60 day'))) {
        $loi['ngay_bat_dau'] = 'Ngày bắt đầu không được quá 60 ngày tính từ hôm nay.';
    }
    if (!ctype_digit($du_lieu['thoi_han']) || (int)$du_lieu['thoi_han'] < 1 || (int)$du_lieu['thoi_han'] > 60) {
        $loi['thoi_han'] = 'Vui lòng chọn thời hạn hợp đồng (1–60 tháng).';
    }
    $tien_coc = doc_so_tien($du_lieu['tien_coc']);
    if ($du_lieu['tien_coc'] === '') {
        $loi['tien_coc'] = 'Vui lòng nhập tiền cọc (nhập 0 nếu không đặt cọc).';
    } elseif ($tien_coc === null || $tien_coc > 100000000) {
        $loi['tien_coc'] = 'Tiền cọc phải là số từ 0 đến 100.000.000 đ.';
    }

    $gia_thue = doc_so_tien($du_lieu['gia_thue']);
    if ($gia_thue === null || $gia_thue <= 0 || $gia_thue > 100000000) {
        $loi['gia_thue'] = 'Giá thuê phải là số dương (ví dụ: 2.500.000).';
    }
    if (!ctype_digit($du_lieu['so_xe']) || (int)$du_lieu['so_xe'] > 10) {
        $loi['so_xe'] = 'Số xe từ 0 đến 10.';
    }

    // Chỉ số lúc nhận phòng: công tơ không chạy lùi so với lần ghi gần nhất của phòng
    $toi_thieu = $phong && isset($goi_y[$phong['id']]) ? $goi_y[$phong['id']]['chi_so_toi_thieu'] : [0, 0];
    if (!ctype_digit($du_lieu['chi_so_dien_dau'])) {
        $loi['chi_so_dien_dau'] = 'Nhập chỉ số điện hiện tại của công tơ (số nguyên).';
    } elseif ((int)$du_lieu['chi_so_dien_dau'] < $toi_thieu[0]) {
        $loi['chi_so_dien_dau'] = 'Không được nhỏ hơn chỉ số đã ghi lần trước (' . $toi_thieu[0] . ').';
    }
    if (!ctype_digit($du_lieu['chi_so_nuoc_dau'])) {
        $loi['chi_so_nuoc_dau'] = 'Nhập chỉ số nước hiện tại của công tơ (số nguyên).';
    } elseif ((int)$du_lieu['chi_so_nuoc_dau'] < $toi_thieu[1]) {
        $loi['chi_so_nuoc_dau'] = 'Không được nhỏ hơn chỉ số đã ghi lần trước (' . $toi_thieu[1] . ').';
    }

    if (!$loi) {
        // Ví dụ bắt đầu 01/10/2026, thời hạn 12 tháng -> hết hạn 30/09/2027
        $ngay_ket_thuc = date('Y-m-d', strtotime(cong_thang_cho_ngay($du_lieu['ngay_bat_dau'], (int)$du_lieu['thoi_han']) . ' -1 day'));
        $ma = ma_hop_dong_moi();

        db()->beginTransaction();
        try {
            // 1. Thêm hợp đồng (giá thuê được chốt tại đây)
            db()->prepare('INSERT INTO hop_dong (ma_hop_dong, phong_id, khach_thue_id, ngay_bat_dau, ngay_ket_thuc,
                                                 gia_thue, tien_coc, so_xe, chi_so_dien_dau, chi_so_nuoc_dau)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$ma, $phong['id'], $khach['id'], $du_lieu['ngay_bat_dau'], $ngay_ket_thuc,
                           $gia_thue, $tien_coc, (int)$du_lieu['so_xe'],
                           (int)$du_lieu['chi_so_dien_dau'], (int)$du_lieu['chi_so_nuoc_dau']]);
            $hop_dong_id = (int)db()->lastInsertId();

            // 2. Người đứng tên cũng là một người ở trong phòng (KT005: đánh dấu người đại diện)
            db()->prepare('INSERT INTO nguoi_o (hop_dong_id, khach_thue_id, la_dai_dien, ngay_vao) VALUES (?, ?, 1, ?)')
                ->execute([$hop_dong_id, $khach['id'], $du_lieu['ngay_bat_dau']]);

            // 3. AC2: phòng chuyển sang Đang thuê
            db()->prepare("UPDATE phong SET trang_thai = 'dang_thue' WHERE id = ?")->execute([$phong['id']]);

            db()->commit();
        } catch (Exception $loi_luu) {
            db()->rollBack();
            throw $loi_luu;
        }

        flash('success', 'Đã tạo hợp đồng ' . $ma . ' cho ' . $khach['ho_ten'] . '. Phòng ' . $phong['ma_phong']
            . ' đã chuyển sang Đang thuê.');
        redirect('hop_dong_chi_tiet.php?id=' . $hop_dong_id);
    }
}

$page_title = 'Tạo hợp đồng';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Tạo hợp đồng thuê phòng</h1>
        <p class="muted">Chỉ phòng đang Trống mới được chọn. Tạo xong, phòng tự chuyển sang Đang thuê.</p>
    </div>
    <a class="btn" href="hop_dong.php">&larr; Danh sách hợp đồng</a>
</div>

<?php if (!$phong_trong): ?>
    <div class="card empty-state">
        <h2>Hiện không có phòng Trống</h2>
        <p class="muted">Tất cả phòng đang cho thuê hoặc đang sửa. Hãy thanh lý hợp đồng cũ hoặc chuyển phòng sửa xong sang Trống.</p>
        <a class="btn" href="phong.php">Xem danh sách phòng</a>
    </div>
<?php else: ?>
<div class="card form-card">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="phong_id">Phòng <span class="required">*</span></label>
                <select id="phong_id" name="phong_id" data-autofill class="<?= lop_loi($loi, 'phong_id') ?>">
                    <option value="">— Chọn phòng đang Trống —</option>
                    <?php foreach ($phong_trong as $p): ?>
                        <?php $g = $goi_y[$p['id']]; ?>
                        <option value="<?= (int)$p['id'] ?>"<?= (string)$p['id'] === $du_lieu['phong_id'] ? ' selected' : '' ?>
                                data-fill-gia_thue="<?= e($g['gia_thue']) ?>" data-fill-tien_coc="<?= e($g['tien_coc']) ?>"
                                data-fill-chi_so_dien_dau="<?= $g['chi_so_dien_dau'] ?>"
                                data-fill-chi_so_nuoc_dau="<?= $g['chi_so_nuoc_dau'] ?>">
                            <?= e($p['ma_phong']) ?> – <?= e(tien($p['gia_thue'])) ?> – <?= e(dien_tich($p['dien_tich'])) ?>, tối đa <?= (int)$p['so_nguoi_toi_da'] ?> người
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= loi_o($loi, 'phong_id') ?>
            </div>
            <div class="field">
                <label for="khach_thue_id">Người đứng tên hợp đồng <span class="required">*</span></label>
                <select id="khach_thue_id" name="khach_thue_id" class="<?= lop_loi($loi, 'khach_thue_id') ?>">
                    <option value="">— Chọn khách chưa ở phòng nào —</option>
                    <?php foreach ($ds_khach as $k): ?>
                        <option value="<?= (int)$k['id'] ?>"<?= (string)$k['id'] === $du_lieu['khach_thue_id'] ? ' selected' : '' ?>>
                            <?= e($k['ho_ten']) ?> – <?= e(sdt($k['so_dien_thoai'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($loi['khach_thue_id'])): ?>
                    <?= loi_o($loi, 'khach_thue_id') ?>
                <?php else: ?>
                    <div class="hint">Khách mới? <a href="khach_thue_form.php?quay_lai=hop_dong_tao&amp;phong_id=<?= (int)$du_lieu['phong_id'] ?>">Thêm khách thuê trước</a>, xong sẽ quay lại đây.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="ngay_bat_dau">Ngày bắt đầu <span class="required">*</span></label>
                <input type="date" id="ngay_bat_dau" name="ngay_bat_dau" value="<?= e($du_lieu['ngay_bat_dau']) ?>"
                       class="<?= lop_loi($loi, 'ngay_bat_dau') ?>">
                <?= loi_o($loi, 'ngay_bat_dau') ?>
            </div>
            <div class="field">
                <label for="thoi_han">Thời hạn <span class="required">*</span></label>
                <select id="thoi_han" name="thoi_han" class="<?= lop_loi($loi, 'thoi_han') ?>">
                    <option value="">— Chọn thời hạn —</option>
                    <?php foreach ([3, 6, 12, 18, 24] as $t): ?>
                        <option value="<?= $t ?>"<?= (string)$t === $du_lieu['thoi_han'] ? ' selected' : '' ?>><?= $t ?> tháng</option>
                    <?php endforeach; ?>
                </select>
                <?= loi_o($loi, 'thoi_han') ?>
            </div>
            <div class="field">
                <label for="gia_thue">Giá thuê / tháng (đ) <span class="required">*</span></label>
                <input type="text" id="gia_thue" name="gia_thue" inputmode="numeric" value="<?= e($du_lieu['gia_thue']) ?>"
                       class="<?= lop_loi($loi, 'gia_thue') ?>">
                <?php if (isset($loi['gia_thue'])): ?>
                    <?= loi_o($loi, 'gia_thue') ?>
                <?php else: ?>
                    <div class="hint">Tự điền theo giá niêm yết, sửa được nếu hai bên thỏa thuận giá khác.</div>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="tien_coc">Tiền cọc (đ) <span class="required">*</span></label>
                <input type="text" id="tien_coc" name="tien_coc" inputmode="numeric" value="<?= e($du_lieu['tien_coc']) ?>"
                       class="<?= lop_loi($loi, 'tien_coc') ?>">
                <?= loi_o($loi, 'tien_coc') ?>
            </div>
            <div class="field">
                <label for="so_xe">Số xe gửi</label>
                <input type="text" id="so_xe" name="so_xe" inputmode="numeric" maxlength="2" value="<?= e($du_lieu['so_xe']) ?>"
                       class="<?= lop_loi($loi, 'so_xe') ?>">
                <?= loi_o($loi, 'so_xe') ?>
            </div>
            <div class="field"></div>
            <div class="field">
                <label for="chi_so_dien_dau">Chỉ số điện lúc nhận phòng <span class="required">*</span></label>
                <input type="text" id="chi_so_dien_dau" name="chi_so_dien_dau" inputmode="numeric" value="<?= e($du_lieu['chi_so_dien_dau']) ?>"
                       class="<?= lop_loi($loi, 'chi_so_dien_dau') ?>">
                <?= loi_o($loi, 'chi_so_dien_dau') ?>
            </div>
            <div class="field">
                <label for="chi_so_nuoc_dau">Chỉ số nước lúc nhận phòng <span class="required">*</span></label>
                <input type="text" id="chi_so_nuoc_dau" name="chi_so_nuoc_dau" inputmode="numeric" value="<?= e($du_lieu['chi_so_nuoc_dau']) ?>"
                       class="<?= lop_loi($loi, 'chi_so_nuoc_dau') ?>">
                <?php if (isset($loi['chi_so_nuoc_dau'])): ?>
                    <?= loi_o($loi, 'chi_so_nuoc_dau') ?>
                <?php else: ?>
                    <div class="hint">Đọc số trên công tơ khi bàn giao phòng; tháng đầu tính tiền từ số này.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="hop_dong.php">Hủy</a>
            <button type="submit" class="btn btn-primary">Tạo hợp đồng</button>
        </div>
    </form>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
