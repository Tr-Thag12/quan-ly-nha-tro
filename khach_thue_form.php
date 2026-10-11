<?php
/**
 * =====================================================================
 *  KT001 – THÊM KHÁCH THUÊ          (khach_thue_form.php)
 *    AC1: Số CCCD không được trùng
 *    AC2: Số điện thoại phải đủ 10 chữ số
 *    AC3: Cho phép tải lên ảnh CCCD
 *
 *  KT002 – SỬA THÔNG TIN KHÁCH THUÊ  (khach_thue_form.php?id=...)
 *    AC1: Sửa xong hiển thị ngay thông tin mới
 *    AC2: Ghi lại thời điểm sửa gần nhất (cột ngay_cap_nhat)
 *
 *  Trang này còn được mở từ màn hình Tạo hợp đồng và Thêm người ở cùng
 *  (tham số quay_lai): thêm khách xong sẽ quay về đúng màn hình đó.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$id     = (int)gia_tri_get('id', '0');
$la_sua = $id > 0;

// Quay về màn hình đã mở trang này (chỉ chấp nhận 2 màn hình quen biết)
$quay_lai    = gia_tri_get('quay_lai', gia_tri_post('quay_lai'));
$phong_id    = (int)gia_tri_get('phong_id', gia_tri_post('phong_id', '0'));
$hop_dong_id = (int)gia_tri_get('hop_dong_id', gia_tri_post('hop_dong_id', '0'));
if (!in_array($quay_lai, ['hop_dong_tao', 'nguoi_o_them'], true)) {
    $quay_lai = '';
}

$khach_cu = null;
if ($la_sua) {
    $stmt = db()->prepare('SELECT * FROM khach_thue WHERE id = ?');
    $stmt->execute([$id]);
    $khach_cu = $stmt->fetch();
    if (!$khach_cu) {
        flash('error', 'Không tìm thấy khách thuê.');
        redirect('khach_thue.php');
    }
}

$du_lieu = [
    'ho_ten'        => $la_sua ? $khach_cu['ho_ten'] : '',
    'cccd'          => $la_sua ? $khach_cu['cccd'] : '',
    'so_dien_thoai' => $la_sua ? $khach_cu['so_dien_thoai'] : '',
    'que_quan'      => $la_sua ? $khach_cu['que_quan'] : '',
];
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $gia_tri_cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o);
    }
    $du_lieu['cccd']          = str_replace(' ', '', $du_lieu['cccd']);
    $du_lieu['so_dien_thoai'] = str_replace([' ', '.', '-'], '', $du_lieu['so_dien_thoai']);

    if ($du_lieu['ho_ten'] === '') {
        $loi['ho_ten'] = 'Vui lòng nhập họ tên.';
    } elseif (do_dai($du_lieu['ho_ten']) > 100) {
        $loi['ho_ten'] = 'Họ tên tối đa 100 ký tự.';
    }

    // AC1: CCCD đủ 12 chữ số và không trùng với khách khác
    if ($du_lieu['cccd'] === '') {
        $loi['cccd'] = 'Vui lòng nhập số CCCD.';
    } elseif (!preg_match('/^\d{12}$/', $du_lieu['cccd'])) {
        $loi['cccd'] = 'Số CCCD gồm đúng 12 chữ số.';
    } else {
        $stmt = db()->prepare('SELECT ho_ten FROM khach_thue WHERE cccd = ? AND id <> ?');
        $stmt->execute([$du_lieu['cccd'], $id]);
        $trung = $stmt->fetchColumn();
        if ($trung !== false) {
            $loi['cccd'] = 'Số CCCD này đã có trong hệ thống (khách ' . $trung . ').';
        }
    }

    // AC2: số điện thoại đủ 10 chữ số
    if ($du_lieu['so_dien_thoai'] === '') {
        $loi['so_dien_thoai'] = 'Vui lòng nhập số điện thoại.';
    } elseif (!preg_match('/^0\d{9}$/', $du_lieu['so_dien_thoai'])) {
        $loi['so_dien_thoai'] = 'Số điện thoại phải đủ 10 chữ số và bắt đầu bằng số 0.';
    }

    if (do_dai($du_lieu['que_quan']) > 100) {
        $loi['que_quan'] = 'Quê quán tối đa 100 ký tự.';
    }

    // AC3: ảnh CCCD (không bắt buộc). Chỉ lưu ảnh khi các ô khác đã đúng, tránh rác trong thư mục.
    $anh_moi = null;
    if (!$loi) {
        list($anh_moi, $loi_anh) = luu_anh_tai_len('anh_cccd', 'cccd');
        if ($loi_anh) {
            $loi['anh_cccd'] = $loi_anh;
        }
    } elseif (!empty($_FILES['anh_cccd']['name'])) {
        $loi['anh_cccd'] = 'Vui lòng chọn lại ảnh sau khi sửa các ô báo lỗi.';
    }

    if (!$loi) {
        $gia_tri = [
            $du_lieu['ho_ten'], $du_lieu['cccd'], $du_lieu['so_dien_thoai'],
            $du_lieu['que_quan'] !== '' ? $du_lieu['que_quan'] : null,
        ];
        if ($la_sua) {
            // KT002 – AC2: ghi lại thời điểm sửa
            $anh = $anh_moi ?: $khach_cu['anh_cccd'];
            db()->prepare('UPDATE khach_thue SET ho_ten = ?, cccd = ?, so_dien_thoai = ?, que_quan = ?,
                                                 anh_cccd = ?, ngay_cap_nhat = NOW()
                           WHERE id = ?')
                ->execute(array_merge($gia_tri, [$anh, $id]));
            flash('success', 'Đã cập nhật thông tin khách ' . $du_lieu['ho_ten'] . '.');
            redirect('khach_thue_chi_tiet.php?id=' . $id);   // KT002 – AC1: hiện ngay thông tin mới
        }

        db()->prepare('INSERT INTO khach_thue (ho_ten, cccd, so_dien_thoai, que_quan, anh_cccd) VALUES (?, ?, ?, ?, ?)')
            ->execute(array_merge($gia_tri, [$anh_moi]));
        $id_moi = (int)db()->lastInsertId();
        flash('success', 'Đã thêm khách thuê ' . $du_lieu['ho_ten'] . '.');
        if ($quay_lai === 'hop_dong_tao') {
            redirect('hop_dong_tao.php?phong_id=' . $phong_id . '&khach_id=' . $id_moi);
        }
        if ($quay_lai === 'nguoi_o_them') {
            redirect('nguoi_o_them.php?hop_dong_id=' . $hop_dong_id . '&khach_id=' . $id_moi);
        }
        redirect('khach_thue_chi_tiet.php?id=' . $id_moi);
    }
}

$page_title = $la_sua ? 'Sửa thông tin khách thuê' : 'Thêm khách thuê';
if ($quay_lai === 'hop_dong_tao') {
    $link_huy = 'hop_dong_tao.php?phong_id=' . $phong_id;
} elseif ($quay_lai === 'nguoi_o_them') {
    $link_huy = 'nguoi_o_them.php?hop_dong_id=' . $hop_dong_id;
} else {
    $link_huy = $la_sua ? 'khach_thue_chi_tiet.php?id=' . $id : 'khach_thue.php';
}
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1><?= e($page_title) ?></h1>
        <p class="muted">
            <?php if ($la_sua && $khach_cu['ngay_cap_nhat']): ?>
                Sửa lần gần nhất lúc <?= e(ngay_gio($khach_cu['ngay_cap_nhat'])) ?>.
            <?php else: ?>
                Các ô có dấu <span class="required">*</span> là bắt buộc.
            <?php endif; ?>
        </p>
    </div>
    <a class="btn" href="<?= e($link_huy) ?>">&larr; Quay lại</a>
</div>

<div class="card form-card">
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="quay_lai" value="<?= e($quay_lai) ?>">
        <input type="hidden" name="phong_id" value="<?= $phong_id ?>">
        <input type="hidden" name="hop_dong_id" value="<?= $hop_dong_id ?>">
        <div class="form-grid">
            <div class="field">
                <label for="ho_ten">Họ tên <span class="required">*</span></label>
                <input type="text" id="ho_ten" name="ho_ten" maxlength="100" value="<?= e($du_lieu['ho_ten']) ?>"
                       class="<?= lop_loi($loi, 'ho_ten') ?>">
                <?= loi_o($loi, 'ho_ten') ?>
            </div>
            <div class="field">
                <label for="cccd">Số CCCD <span class="required">*</span></label>
                <input type="text" id="cccd" name="cccd" inputmode="numeric" maxlength="12" placeholder="12 chữ số"
                       value="<?= e($du_lieu['cccd']) ?>" class="<?= lop_loi($loi, 'cccd') ?>">
                <?= loi_o($loi, 'cccd') ?>
            </div>
            <div class="field">
                <label for="so_dien_thoai">Số điện thoại <span class="required">*</span></label>
                <input type="text" id="so_dien_thoai" name="so_dien_thoai" inputmode="numeric" maxlength="12"
                       placeholder="Ví dụ: 0912345678" value="<?= e($du_lieu['so_dien_thoai']) ?>"
                       class="<?= lop_loi($loi, 'so_dien_thoai') ?>">
                <?= loi_o($loi, 'so_dien_thoai') ?>
            </div>
            <div class="field">
                <label for="que_quan">Quê quán</label>
                <input type="text" id="que_quan" name="que_quan" maxlength="100" value="<?= e($du_lieu['que_quan']) ?>"
                       class="<?= lop_loi($loi, 'que_quan') ?>">
                <?= loi_o($loi, 'que_quan') ?>
            </div>
            <div class="field full">
                <label for="anh_cccd">Ảnh CCCD</label>
                <?php if ($la_sua && $khach_cu['anh_cccd']): ?>
                    <p class="small muted">Đã có ảnh. <a href="<?= e($khach_cu['anh_cccd']) ?>" target="_blank">Xem ảnh hiện tại</a>.
                        Chọn ảnh mới nếu muốn thay.</p>
                <?php endif; ?>
                <input type="file" id="anh_cccd" name="anh_cccd" accept="image/jpeg,image/png,image/webp"
                       class="<?= lop_loi($loi, 'anh_cccd') ?>">
                <?php if (isset($loi['anh_cccd'])): ?>
                    <?= loi_o($loi, 'anh_cccd') ?>
                <?php else: ?>
                    <div class="hint">Ảnh JPG, PNG hoặc WEBP, tối đa <?= ANH_TOI_DA_MB ?> MB. Không bắt buộc.</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn" href="<?= e($link_huy) ?>">Hủy</a>
            <button type="submit" class="btn btn-primary"><?= $la_sua ? 'Lưu thay đổi' : 'Thêm khách thuê' ?></button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
