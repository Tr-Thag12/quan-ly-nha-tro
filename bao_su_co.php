<?php
/**
 * =====================================================================
 *  SC001 – GỬI BÁO SỰ CỐ
 *    AC1: Chọn loại sự cố, mô tả và tải được ảnh minh họa
 *    AC2: Gửi xong nhận mã theo dõi
 *
 *  Trang dùng được theo 2 cách:
 *   - Khách thuê (KHÔNG cần đăng nhập): chọn phòng và nhập số điện thoại đã
 *     đăng ký với nhà trọ. Hệ thống chỉ nhận khi số điện thoại đúng là của người
 *     đang ở phòng đó, nên người ngoài không gửi bừa được.
 *   - Quản lý / Chủ trọ đã đăng nhập: ghi nhận sự cố thay khách (khách gọi điện
 *     báo, hoặc tự phát hiện khi đi kiểm tra phòng), không cần số điện thoại.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
no_cache();

$la_quan_ly = current_user() !== null;

// Khách vừa gửi xong: giữ mã theo dõi trong phiên để tải lại trang vẫn còn thấy
if (gia_tri_get('moi') !== '') {
    unset($_SESSION['su_co_vua_gui']);
    redirect('bao_su_co.php');
}
$ma_vua_gui = !$la_quan_ly && isset($_SESSION['su_co_vua_gui']) ? $_SESSION['su_co_vua_gui'] : null;

// Danh sách phòng: khách chỉ chọn được phòng đang có người thuê; quản lý chọn được mọi phòng
$ds_phong = db()->query($la_quan_ly
    ? 'SELECT id, ma_phong, trang_thai FROM phong ORDER BY tang, ma_phong'
    : "SELECT p.id, p.ma_phong, p.trang_thai FROM phong p
       WHERE EXISTS (SELECT 1 FROM hop_dong h WHERE h.phong_id = p.id AND h.trang_thai = 'hieu_luc')
       ORDER BY p.tang, p.ma_phong")->fetchAll();
$phong_theo_id = array_column($ds_phong, null, 'id');

$du_lieu = [
    'phong_id'      => gia_tri_get('phong_id'),
    'so_dien_thoai' => '',
    'loai'          => '',
    'mo_ta'         => '',
];
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($du_lieu as $ten_o => $cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o);
    }
    $du_lieu['so_dien_thoai'] = str_replace([' ', '.', '-'], '', $du_lieu['so_dien_thoai']);

    if (!isset($phong_theo_id[(int)$du_lieu['phong_id']])) {
        $loi['phong_id'] = 'Vui lòng chọn phòng.';
    }

    // Khách thuê: số điện thoại phải là của người đang ở phòng đã chọn
    $khach = null;
    if (!$la_quan_ly) {
        if ($du_lieu['so_dien_thoai'] === '') {
            $loi['so_dien_thoai'] = 'Vui lòng nhập số điện thoại của bạn.';
        } elseif (!preg_match('/^0\d{9}$/', $du_lieu['so_dien_thoai'])) {
            $loi['so_dien_thoai'] = 'Số điện thoại phải đủ 10 chữ số và bắt đầu bằng số 0.';
        } elseif (!isset($loi['phong_id'])) {
            $khach = khach_dang_o_phong_theo_sdt($du_lieu['phong_id'], $du_lieu['so_dien_thoai']);
            if (!$khach) {
                $loi['so_dien_thoai'] = 'Số điện thoại này không khớp với người đang ở phòng '
                    . $phong_theo_id[(int)$du_lieu['phong_id']]['ma_phong']
                    . '. Hãy dùng số đã đăng ký với nhà trọ hoặc gọi ' . SDT_NHA_TRO . '.';
            }
        }
    }

    // AC1: loại sự cố + mô tả
    if (!in_array($du_lieu['loai'], LOAI_SU_CO, true)) {
        $loi['loai'] = 'Vui lòng chọn loại sự cố.';
    }
    if ($du_lieu['mo_ta'] === '') {
        $loi['mo_ta'] = 'Vui lòng mô tả sự cố.';
    } elseif (do_dai($du_lieu['mo_ta']) < 5) {
        $loi['mo_ta'] = 'Mô tả quá ngắn, hãy ghi rõ hơn để thợ chuẩn bị đồ sửa.';
    } elseif (do_dai($du_lieu['mo_ta']) > 500) {
        $loi['mo_ta'] = 'Mô tả tối đa 500 ký tự.';
    }

    // AC1: ảnh minh họa (không bắt buộc). Chỉ lưu khi các ô khác đã đúng.
    $anh = null;
    if (!$loi) {
        list($anh, $loi_anh) = luu_anh_tai_len('anh', 'su_co');
        if ($loi_anh) {
            $loi['anh'] = $loi_anh;
        }
    } elseif (!empty($_FILES['anh']['name'])) {
        $loi['anh'] = 'Vui lòng chọn lại ảnh sau khi sửa các ô báo lỗi.';
    }

    if (!$loi) {
        $ma = them_su_co(
            $du_lieu['phong_id'],
            $khach ? (int)$khach['id'] : null,
            $du_lieu['loai'],
            $du_lieu['mo_ta'],
            $anh,
            $la_quan_ly ? 'Quản lý ghi nhận sự cố' : 'Khách thuê báo sự cố'
        );
        if ($la_quan_ly) {
            $su_co = lay_su_co_theo_ma($ma);
            flash('success', 'Đã ghi nhận sự cố ' . $ma . ' của phòng ' . $su_co['ma_phong'] . '.');
            redirect('su_co_chi_tiet.php?id=' . (int)$su_co['id']);
        }
        $_SESSION['su_co_vua_gui'] = $ma;     // AC2: hiện mã theo dõi cho khách
        redirect('bao_su_co.php');
    }
}

$page_title = 'Báo sự cố';
$auth_page  = !$la_quan_ly;     // khách thuê: trang gọn, không có menu
require __DIR__ . '/includes/header.php';
?>
<?php if ($la_quan_ly): ?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="su_co.php">Sự cố</a> / Ghi nhận sự cố</p>
        <h1>Ghi nhận sự cố</h1>
        <p class="muted">Dùng khi khách gọi điện báo hoặc khi tự phát hiện hỏng hóc lúc kiểm tra phòng.
            Khách thuê tự báo tại <code><?= e(dia_chi_trang('bao_su_co.php')) ?></code> (mở trên điện thoại, không cần đăng nhập).</p>
    </div>
</div>
<div class="card form-card">
<?php else: ?>
<div class="card auth-card wide">
    <div class="auth-head">
        <span class="brand-logo lg">NT</span>
        <h1>Báo sự cố</h1>
        <p class="muted"><?= e(TEN_NHA_TRO) ?> · Dành cho khách thuê</p>
    </div>
<?php endif; ?>

<?php if ($ma_vua_gui): ?>
    <!-- AC2: gửi xong nhận mã theo dõi -->
    <div class="alert alert-success">Đã gửi báo sự cố. Quản lý nhà trọ sẽ sắp xếp thợ đến sửa sớm nhất.</div>
    <p class="center">Mã theo dõi của bạn:</p>
    <div class="code-big" id="ma-theo-doi"><?= e($ma_vua_gui) ?></div>
    <p class="small muted center">Hãy chụp màn hình hoặc ghi lại mã này. Dùng mã để xem sự cố đã được xử lý đến đâu
        mà không phải gọi điện hỏi.</p>
    <a class="btn btn-primary btn-block" href="tra_cuu_su_co.php?ma=<?= e(urlencode($ma_vua_gui)) ?>">Xem tiến độ xử lý</a>
    <a class="btn btn-block mt" href="bao_su_co.php?moi=1">Báo một sự cố khác</a>
<?php else: ?>
    <?php if ($loi): ?>
        <div class="alert alert-error">Chưa gửi được, vui lòng kiểm tra các ô báo lỗi bên dưới.</div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div class="<?= $la_quan_ly ? 'form-grid' : '' ?>">
            <div class="field">
                <label for="phong_id">Phòng <span class="required">*</span></label>
                <select id="phong_id" name="phong_id" class="<?= lop_loi($loi, 'phong_id') ?>">
                    <option value="">— Chọn phòng —</option>
                    <?php foreach ($ds_phong as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"<?= (string)$p['id'] === $du_lieu['phong_id'] ? ' selected' : '' ?>>
                            <?= e($p['ma_phong']) ?><?= $la_quan_ly && $p['trang_thai'] !== 'dang_thue' ? ' (' . e(TRANG_THAI_PHONG[$p['trang_thai']]) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= loi_o($loi, 'phong_id') ?>
            </div>
            <?php if (!$la_quan_ly): ?>
                <div class="field">
                    <label for="so_dien_thoai">Số điện thoại của bạn <span class="required">*</span></label>
                    <input type="tel" id="so_dien_thoai" name="so_dien_thoai" inputmode="numeric" maxlength="13"
                           value="<?= e($du_lieu['so_dien_thoai']) ?>" class="<?= lop_loi($loi, 'so_dien_thoai') ?>"
                           autocomplete="tel" placeholder="Số đã đăng ký với nhà trọ">
                    <?= loi_o($loi, 'so_dien_thoai') ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="field">
            <label id="nhan-loai">Loại sự cố <span class="required">*</span></label>
            <div class="choice-group <?= lop_loi($loi, 'loai') ?>" role="radiogroup" aria-labelledby="nhan-loai">
                <?php foreach (LOAI_SU_CO as $loai): ?>
                    <label><input type="radio" name="loai" value="<?= e($loai) ?>"<?= $du_lieu['loai'] === $loai ? ' checked' : '' ?>> <?= e($loai) ?></label>
                <?php endforeach; ?>
            </div>
            <?= loi_o($loi, 'loai') ?>
        </div>

        <div class="field">
            <label for="mo_ta">Mô tả sự cố <span class="required">*</span> <span class="char-count" id="dem-mo-ta"></span></label>
            <textarea id="mo_ta" name="mo_ta" rows="4" maxlength="500" data-dem="dem-mo-ta"
                      class="<?= lop_loi($loi, 'mo_ta') ?>"
                      placeholder="Ví dụ: Vòi nước trong nhà tắm bị rò, nước chảy liên tục"><?= e($du_lieu['mo_ta']) ?></textarea>
            <?= loi_o($loi, 'mo_ta') ?>
        </div>

        <div class="field">
            <label for="anh">Ảnh minh họa</label>
            <input type="file" id="anh" name="anh" accept="image/jpeg,image/png,image/webp" class="<?= lop_loi($loi, 'anh') ?>">
            <?php if (isset($loi['anh'])): ?>
                <?= loi_o($loi, 'anh') ?>
            <?php else: ?>
                <div class="hint">Không bắt buộc. Ảnh JPG, PNG hoặc WEBP, tối đa <?= ANH_TOI_DA_MB ?> MB.</div>
            <?php endif; ?>
        </div>

        <?php if ($la_quan_ly): ?>
            <div class="form-actions">
                <a class="btn" href="su_co.php">Hủy</a>
                <button type="submit" class="btn btn-primary">Ghi nhận sự cố</button>
            </div>
        <?php else: ?>
            <button type="submit" class="btn btn-primary btn-block">Gửi báo sự cố</button>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php if (!$la_quan_ly): ?>
    <div class="public-links">
        <a href="tra_cuu_su_co.php">Tra cứu tiến độ bằng mã theo dõi</a> ·
        <a href="login.php">Đăng nhập dành cho chủ trọ, quản lý</a>
    </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
