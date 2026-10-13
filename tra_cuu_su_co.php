<?php
/**
 * =====================================================================
 *  TRA CỨU TIẾN ĐỘ SỰ CỐ BẰNG MÃ THEO DÕI (dành cho khách thuê, không cần đăng nhập)
 *    SC001 – AC2: mã theo dõi nhận được sau khi gửi báo sự cố dùng ở đây
 *    SC003: "để khách biết được tiến độ mà không phải hỏi lại" – khách thấy
 *           trạng thái hiện tại và từng lần đổi trạng thái kèm thời điểm
 *  Trang chỉ hiện thông tin sự cố, không hiện chi phí hay thông tin cá nhân.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
no_cache();

$ma_go = gia_tri_get('ma');
$su_co = null;
$lich_su = [];
$loi = null;
if ($ma_go !== '') {
    $ma = chuan_hoa_ma_theo_doi($ma_go);
    $su_co = lay_su_co_theo_ma($ma);
    if ($su_co) {
        $lich_su = lich_su_su_co($su_co['id']);
    } else {
        $loi = 'Không tìm thấy sự cố có mã ' . $ma . '. Hãy kiểm tra lại mã theo dõi (ví dụ SC-7KQ2M9).';
    }
}

$page_title = 'Tra cứu sự cố';
$auth_page  = true;
require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card wide">
    <div class="auth-head">
        <span class="brand-logo lg">NT</span>
        <h1>Tra cứu sự cố</h1>
        <p class="muted"><?= e(TEN_NHA_TRO) ?> · Xem sự cố đã được xử lý đến đâu</p>
    </div>

    <form method="get" class="form-inline" style="margin-bottom:18px">
        <div class="field" style="flex:1">
            <label for="ma" class="sr-only">Mã theo dõi</label>
            <input type="text" id="ma" name="ma" value="<?= e($ma_go) ?>" placeholder="Nhập mã theo dõi, ví dụ SC-7KQ2M9"
                   class="<?= $loi ? 'is-invalid' : '' ?>" autocomplete="off" <?= $ma_go === '' ? 'autofocus' : '' ?>>
        </div>
        <button type="submit" class="btn btn-primary">Tra cứu</button>
    </form>

    <?php if ($loi): ?>
        <div class="alert alert-error"><?= e($loi) ?></div>
    <?php endif; ?>

    <?php if ($su_co): ?>
        <div class="track-box">
            <h2>Sự cố <?= e($su_co['ma_theo_doi']) ?> <?= nhan($su_co['trang_thai'], TRANG_THAI_SU_CO) ?></h2>
            <div class="issue-meta">
                <span>Phòng <strong><?= e($su_co['ma_phong']) ?></strong></span>
                <span>Loại: <strong><?= e($su_co['loai']) ?></strong></span>
                <span>Báo lúc <?= e(ngay_gio($su_co['ngay_bao'])) ?></span>
            </div>
            <p class="issue-desc mt"><?= e($su_co['mo_ta']) ?></p>
            <?php if ($su_co['anh']): ?>
                <a class="photo-link" href="<?= e($su_co['anh']) ?>" target="_blank" rel="noopener">
                    <img class="photo" src="<?= e($su_co['anh']) ?>" alt="Ảnh minh họa sự cố" style="max-height:200px"></a>
            <?php endif; ?>
        </div>

        <h2 style="margin-bottom:12px">Tiến độ xử lý</h2>
        <ul class="timeline">
            <?php foreach ($lich_su as $ls): ?>
                <li class="<?= e($ls['trang_thai']) ?>">
                    <strong><?= e(TRANG_THAI_SU_CO[$ls['trang_thai']]) ?></strong>
                    <div class="when"><?= e(ngay_gio($ls['thoi_diem'])) ?></div>
                    <?php if ($ls['ghi_chu']): ?><div class="note-text"><?= e($ls['ghi_chu']) ?></div><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($su_co['trang_thai'] !== 'da_xong'): ?>
            <p class="small muted mt">Cần gấp? Gọi quản lý nhà trọ: <strong><?= e(SDT_NHA_TRO) ?></strong>.</p>
        <?php endif; ?>
    <?php endif; ?>

    <div class="public-links">
        <a href="bao_su_co.php">Báo sự cố mới</a> ·
        <a href="login.php">Đăng nhập dành cho chủ trọ, quản lý</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
