<?php
/**
 * =====================================================================
 *  PH001 – THÊM PHÒNG TRỌ            (phong_form.php)
 *    AC1: Mã phòng không được trùng
 *    AC2: Giá thuê phải là số dương
 *    AC3: Phòng mới mặc định ở trạng thái Trống
 *
 *  PH002 – SỬA THÔNG TIN PHÒNG       (phong_form.php?id=...)
 *    AC1: Sửa giá không làm thay đổi hóa đơn các tháng đã chốt
 *         -> Giá thuê được CHỐT vào bảng hop_dong tại lúc ký hợp đồng.
 *            Hóa đơn (Sprint 3) sẽ lấy giá từ hợp đồng, không lấy từ bảng phong,
 *            nên sửa giá niêm yết ở đây không làm thay đổi dữ liệu cũ.
 *    AC2: Lưu xong danh sách cập nhật ngay
 *         -> Lưu xong quay về danh sách và tô sáng dòng vừa sửa.
 *
 *  Chỉ Chủ trọ được thêm/sửa phòng (cột "As a" trong Product Backlog).
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$la_sua = $id > 0;

$phong_cu          = null;
$hop_dong_hien_tai = null;

if ($la_sua) {
    $stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
    $stmt->execute([$id]);
    $phong_cu = $stmt->fetch();
    if (!$phong_cu) {
        flash('error', 'Không tìm thấy phòng cần sửa.');
        redirect('phong.php');
    }

    // Hợp đồng còn hiệu lực của phòng (nếu có)
    $stmt = db()->prepare("SELECT h.ma_hop_dong, h.gia_thue, k.ho_ten
                           FROM hop_dong h JOIN khach_thue k ON k.id = h.khach_thue_id
                           WHERE h.phong_id = ? AND h.trang_thai = 'hieu_luc'");
    $stmt->execute([$id]);
    $hop_dong_hien_tai = $stmt->fetch();
}

// Dữ liệu hiển thị trên form: sửa thì lấy từ CSDL, thêm mới thì để trống
$du_lieu = $la_sua ? [
    'ma_phong'        => $phong_cu['ma_phong'],
    'tang'            => $phong_cu['tang'],
    'dien_tich'       => rtrim(rtrim($phong_cu['dien_tich'], '0'), '.'),
    'gia_thue'        => number_format($phong_cu['gia_thue'], 0, ',', '.'),
    'so_nguoi_toi_da' => $phong_cu['so_nguoi_toi_da'],
    'tien_nghi'       => $phong_cu['tien_nghi'],
    'trang_thai'      => $phong_cu['trang_thai'],
] : [
    'ma_phong' => '', 'tang' => '', 'dien_tich' => '', 'gia_thue' => '',
    'so_nguoi_toi_da' => '2', 'tien_nghi' => '', 'trang_thai' => 'trong',
];

$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $gia_tri_cu) {
        if (isset($_POST[$ten_o])) {
            $du_lieu[$ten_o] = trim($_POST[$ten_o]);
        }
    }

    // ---- Mã phòng (PH001 – AC1) ----
    $ma_phong = strtoupper($du_lieu['ma_phong']);
    if ($ma_phong === '') {
        $loi['ma_phong'] = 'Vui lòng nhập mã phòng.';
    } elseif (!preg_match('/^[A-Z0-9\-]{2,20}$/', $ma_phong)) {
        $loi['ma_phong'] = 'Mã phòng dài 2–20 ký tự, chỉ gồm chữ không dấu, số và dấu gạch ngang (ví dụ: P109).';
    } else {
        $stmt = db()->prepare('SELECT COUNT(*) FROM phong WHERE ma_phong = ? AND id <> ?');
        $stmt->execute([$ma_phong, $id]);   // khi sửa: bỏ qua chính phòng đang sửa
        if ($stmt->fetchColumn() > 0) {
            $loi['ma_phong'] = 'Mã phòng "' . $ma_phong . '" đã tồn tại, vui lòng chọn mã khác.';
        }
    }

    // ---- Tầng ----
    if (!ctype_digit((string)$du_lieu['tang']) || (int)$du_lieu['tang'] < 1 || (int)$du_lieu['tang'] > 50) {
        $loi['tang'] = 'Tầng phải là số nguyên từ 1 đến 50.';
    }

    // ---- Diện tích: chấp nhận cả 22,5 và 22.5 ----
    $dien_tich = str_replace(',', '.', $du_lieu['dien_tich']);
    if (!is_numeric($dien_tich) || (float)$dien_tich <= 0 || (float)$dien_tich > 500) {
        $loi['dien_tich'] = 'Diện tích phải là số lớn hơn 0 (đơn vị m², ví dụ 22,5).';
    }

    // ---- Giá thuê (PH001 – AC2) ----
    $gia_thue = doc_so_tien($du_lieu['gia_thue']);
    if ($du_lieu['gia_thue'] === '') {
        $loi['gia_thue'] = 'Vui lòng nhập giá thuê.';
    } elseif ($gia_thue === null || $gia_thue <= 0) {
        $loi['gia_thue'] = 'Giá thuê phải là số dương (ví dụ: 2500000 hoặc 2.500.000).';
    } elseif ($gia_thue > 100000000) {
        $loi['gia_thue'] = 'Giá thuê tối đa 100.000.000 đ một tháng.';
    }

    // ---- Số người tối đa ----
    if (!ctype_digit((string)$du_lieu['so_nguoi_toi_da'])
        || (int)$du_lieu['so_nguoi_toi_da'] < 1 || (int)$du_lieu['so_nguoi_toi_da'] > 10) {
        $loi['so_nguoi_toi_da'] = 'Số người tối đa từ 1 đến 10.';
    }

    // ---- Tiện nghi (không bắt buộc) ----
    if (do_dai($du_lieu['tien_nghi']) > 255) {
        $loi['tien_nghi'] = 'Tiện nghi tối đa 255 ký tự.';
    }

    // ---- Trạng thái ----
    // Phòng mới luôn là Trống (PH001 – AC3).
    // Phòng đang có hợp đồng thì giữ Đang thuê; chỉ hợp đồng mới đổi được trạng thái này.
    // Phòng không có hợp đồng thì Chủ trọ chọn Trống hoặc Đang sửa.
    if (!$la_sua) {
        $trang_thai = 'trong';
    } elseif ($hop_dong_hien_tai) {
        $trang_thai = 'dang_thue';
    } else {
        $trang_thai = $du_lieu['trang_thai'];
        if (!in_array($trang_thai, ['trong', 'dang_sua'], true)) {
            $loi['trang_thai'] = 'Trạng thái không hợp lệ.';
        }
    }

    if (!$loi) {
        $gia_tri = [
            $ma_phong,
            (int)$du_lieu['tang'],
            round((float)$dien_tich, 1),
            $gia_thue,
            (int)$du_lieu['so_nguoi_toi_da'],
            $du_lieu['tien_nghi'] !== '' ? $du_lieu['tien_nghi'] : null,
            $trang_thai,
        ];

        if ($la_sua) {
            $gia_tri[] = $id;
            db()->prepare('UPDATE phong SET ma_phong = ?, tang = ?, dien_tich = ?, gia_thue = ?,
                                           so_nguoi_toi_da = ?, tien_nghi = ?, trang_thai = ?
                           WHERE id = ?')->execute($gia_tri);

            $tin = 'Đã cập nhật phòng ' . $ma_phong . '.';
            // PH002 – AC1: nói rõ giá mới không ảnh hưởng hợp đồng đang chạy
            if ($hop_dong_hien_tai && $gia_thue !== (int)$phong_cu['gia_thue']) {
                $tin .= ' Giá mới ' . tien($gia_thue) . ' chỉ áp dụng cho hợp đồng ký sau này; hợp đồng '
                      . $hop_dong_hien_tai['ma_hop_dong'] . ' hiện tại vẫn giữ giá ' . tien($hop_dong_hien_tai['gia_thue']) . '.';
            }
            flash('success', $tin);
            redirect('phong.php?moi=' . $id);   // PH002 – AC2

        } else {
            db()->prepare('INSERT INTO phong (ma_phong, tang, dien_tich, gia_thue, so_nguoi_toi_da, tien_nghi, trang_thai)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')->execute($gia_tri);
            $id_moi = (int)db()->lastInsertId();
            flash('success', 'Đã thêm phòng ' . $ma_phong . '. Phòng mới ở trạng thái Trống, sẵn sàng cho thuê.');
            redirect('phong.php?moi=' . $id_moi);
        }
    }
}

$page_title = $la_sua ? 'Sửa phòng ' . $phong_cu['ma_phong'] : 'Thêm phòng';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($page_title) ?></h1>
        <p class="muted">Các ô có dấu <span class="required">*</span> là bắt buộc.</p>
    </div>
    <a class="btn" href="phong.php">&larr; Danh sách phòng</a>
</div>

<?php if ($hop_dong_hien_tai): ?>
    <div class="alert alert-info form-note">
        Phòng đang cho thuê theo hợp đồng <strong><?= e($hop_dong_hien_tai['ma_hop_dong']) ?></strong>
        (khách <?= e($hop_dong_hien_tai['ho_ten']) ?>, giá <?= e(tien($hop_dong_hien_tai['gia_thue'])) ?>).
        Nếu sửa giá thuê, giá mới chỉ áp dụng cho các hợp đồng ký sau; hợp đồng và hóa đơn đã chốt không thay đổi.
    </div>
<?php endif; ?>

<div class="card form-card">
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field">
                <label for="ma_phong">Mã phòng <span class="required">*</span></label>
                <input type="text" id="ma_phong" name="ma_phong" maxlength="20" placeholder="Ví dụ: P109"
                       value="<?= e($du_lieu['ma_phong']) ?>" class="<?= lop_loi($loi, 'ma_phong') ?>">
                <?= loi_o($loi, 'ma_phong') ?>
            </div>
            <div class="field">
                <label for="tang">Tầng <span class="required">*</span></label>
                <input type="text" id="tang" name="tang" inputmode="numeric" maxlength="2"
                       value="<?= e($du_lieu['tang']) ?>" class="<?= lop_loi($loi, 'tang') ?>">
                <?= loi_o($loi, 'tang') ?>
            </div>
            <div class="field">
                <label for="gia_thue">Giá thuê / tháng (đ) <span class="required">*</span></label>
                <input type="text" id="gia_thue" name="gia_thue" inputmode="numeric" placeholder="Ví dụ: 2.500.000"
                       value="<?= e($du_lieu['gia_thue']) ?>" class="<?= lop_loi($loi, 'gia_thue') ?>">
                <?= loi_o($loi, 'gia_thue') ?>
            </div>
            <div class="field">
                <label for="dien_tich">Diện tích (m²) <span class="required">*</span></label>
                <input type="text" id="dien_tich" name="dien_tich" inputmode="decimal" placeholder="Ví dụ: 22,5"
                       value="<?= e($du_lieu['dien_tich']) ?>" class="<?= lop_loi($loi, 'dien_tich') ?>">
                <?= loi_o($loi, 'dien_tich') ?>
            </div>
            <div class="field">
                <label for="so_nguoi_toi_da">Số người ở tối đa <span class="required">*</span></label>
                <input type="text" id="so_nguoi_toi_da" name="so_nguoi_toi_da" inputmode="numeric" maxlength="2"
                       value="<?= e($du_lieu['so_nguoi_toi_da']) ?>" class="<?= lop_loi($loi, 'so_nguoi_toi_da') ?>">
                <?= loi_o($loi, 'so_nguoi_toi_da') ?>
            </div>
            <div class="field">
                <label for="trang_thai">Trạng thái</label>
                <?php if (!$la_sua): ?>
                    <div class="static-value"><?= nhan('trong', TRANG_THAI_PHONG) ?>
                        <span class="hint inline-hint">Phòng mới luôn ở trạng thái Trống</span></div>
                <?php elseif ($hop_dong_hien_tai): ?>
                    <div class="static-value"><?= nhan('dang_thue', TRANG_THAI_PHONG) ?>
                        <span class="hint inline-hint">Do hợp đồng quyết định</span></div>
                <?php else: ?>
                    <select id="trang_thai" name="trang_thai" class="<?= lop_loi($loi, 'trang_thai') ?>">
                        <?php foreach (['trong', 'dang_sua'] as $ma): ?>
                            <option value="<?= $ma ?>"<?= $du_lieu['trang_thai'] === $ma ? ' selected' : '' ?>><?= e(TRANG_THAI_PHONG[$ma]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= loi_o($loi, 'trang_thai') ?>
                <?php endif; ?>
            </div>
            <div class="field full">
                <label for="tien_nghi">Tiện nghi</label>
                <textarea id="tien_nghi" name="tien_nghi" rows="2" maxlength="255"
                          placeholder="Ví dụ: Điều hòa, nóng lạnh, gác xép"
                          class="<?= lop_loi($loi, 'tien_nghi') ?>"><?= e($du_lieu['tien_nghi']) ?></textarea>
                <?= loi_o($loi, 'tien_nghi') ?>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="<?= $la_sua ? 'phong_chi_tiet.php?id=' . $id : 'phong.php' ?>">Hủy</a>
            <button type="submit" class="btn btn-primary"><?= $la_sua ? 'Lưu thay đổi' : 'Thêm phòng' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
