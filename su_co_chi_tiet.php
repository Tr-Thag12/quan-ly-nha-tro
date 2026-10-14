<?php
/**
 * =====================================================================
 *  CHI TIẾT MỘT SỰ CỐ
 *
 *  SC003 – CẬP NHẬT TRẠNG THÁI SỰ CỐ
 *    AC1: Mỗi lần đổi trạng thái đều ghi lại thời điểm (bảng su_co_lich_su)
 *    AC2: Chuyển sang Đã xong thì bắt buộc nhập ghi chú xử lý
 *
 *  SC004 – GHI CHI PHÍ SỬA CHỮA (chỉ Chủ trọ)
 *    AC1: Nhập số tiền, ngày sửa, tên thợ
 *    AC2: Cộng tổng chi phí theo tháng – xem ở cuối trang su_co.php
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$id = (int)gia_tri_get('id', gia_tri_post('id', '0'));
$sc = lay_su_co($id);
if (!$sc) {
    flash('error', 'Không tìm thấy sự cố.');
    redirect('su_co.php');
}

// Trạng thái gợi ý cho lần cập nhật kế tiếp
$ke_tiep = ['cho_xu_ly' => 'dang_xu_ly', 'dang_xu_ly' => 'da_xong', 'da_xong' => 'da_xong'];
$form_tt = ['trang_thai' => $ke_tiep[$sc['trang_thai']], 'ghi_chu' => ''];
$form_cp = [
    'chi_phi' => $sc['chi_phi'] !== null ? number_format($sc['chi_phi'], 0, ',', '.') : '',
    'ngay_sua' => $sc['ngay_sua'] ?: hom_nay(),
    'tho_sua' => (string)$sc['tho_sua'],
];
$loi = [];
$hanh_dong = gia_tri_post('hanh_dong');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if ($hanh_dong === 'trang_thai') {
        // ---------------- SC003 ----------------
        $form_tt = ['trang_thai' => gia_tri_post('trang_thai'), 'ghi_chu' => gia_tri_post('ghi_chu')];
        if (!array_key_exists($form_tt['trang_thai'], TRANG_THAI_SU_CO)) {
            $loi['trang_thai'] = 'Vui lòng chọn trạng thái.';
        } elseif ($form_tt['trang_thai'] === 'da_xong' && $form_tt['ghi_chu'] === '') {
            $loi['ghi_chu'] = 'Chuyển sang Đã xong thì phải ghi chú đã xử lý thế nào (sửa gì, thay gì).';   // AC2
        } elseif ($form_tt['trang_thai'] === $sc['trang_thai'] && $form_tt['ghi_chu'] === '') {
            $loi['trang_thai'] = 'Trạng thái không đổi. Nếu chỉ muốn ghi thêm tiến độ, hãy nhập ghi chú.';
        }
        if (do_dai($form_tt['ghi_chu']) > 255) {
            $loi['ghi_chu'] = 'Ghi chú tối đa 255 ký tự.';
        }
        if (!$loi) {
            cap_nhat_su_co($sc, $form_tt['trang_thai'], $form_tt['ghi_chu']);   // AC1: ghi lại thời điểm
            flash('success', $form_tt['trang_thai'] === $sc['trang_thai']
                ? 'Đã ghi thêm tiến độ cho sự cố ' . $sc['ma_theo_doi'] . '.'
                : 'Đã chuyển sự cố ' . $sc['ma_theo_doi'] . ' sang "' . TRANG_THAI_SU_CO[$form_tt['trang_thai']] . '".');
            redirect('su_co_chi_tiet.php?id=' . $sc['id']);
        }

    } elseif ($hanh_dong === 'chi_phi') {
        // ---------------- SC004: chỉ Chủ trọ ----------------
        require_role('chu_tro');
        $form_cp = ['chi_phi' => gia_tri_post('chi_phi'), 'ngay_sua' => gia_tri_post('ngay_sua'), 'tho_sua' => gia_tri_post('tho_sua')];
        $chi_phi = doc_so_tien($form_cp['chi_phi']);
        if ($form_cp['chi_phi'] === '') {
            $loi['chi_phi'] = 'Vui lòng nhập số tiền sửa chữa.';
        } elseif ($chi_phi === null || $chi_phi <= 0) {
            $loi['chi_phi'] = 'Chi phí phải là số tiền lớn hơn 0.';
        }
        $ngay_bao = substr($sc['ngay_bao'], 0, 10);
        if (!ngay_hop_le($form_cp['ngay_sua'])) {
            $loi['ngay_sua'] = 'Vui lòng chọn ngày sửa.';
        } elseif ($form_cp['ngay_sua'] > hom_nay()) {
            $loi['ngay_sua'] = 'Ngày sửa không được sau hôm nay.';
        } elseif ($form_cp['ngay_sua'] < $ngay_bao) {
            $loi['ngay_sua'] = 'Ngày sửa không được trước ngày báo sự cố (' . ngay($ngay_bao) . ').';
        }
        if ($form_cp['tho_sua'] === '') {
            $loi['tho_sua'] = 'Vui lòng nhập tên thợ hoặc đơn vị sửa.';
        } elseif (do_dai($form_cp['tho_sua']) > 100) {
            $loi['tho_sua'] = 'Tên thợ tối đa 100 ký tự.';
        }
        if (!$loi) {
            db()->prepare('UPDATE su_co SET chi_phi = ?, ngay_sua = ?, tho_sua = ? WHERE id = ?')
                ->execute([$chi_phi, $form_cp['ngay_sua'], $form_cp['tho_sua'], $sc['id']]);
            flash('success', 'Đã lưu chi phí sửa chữa ' . tien($chi_phi) . ' cho sự cố ' . $sc['ma_theo_doi'] . '.');
            redirect('su_co_chi_tiet.php?id=' . $sc['id']);
        }
    }
}

$lich_su = lich_su_su_co($sc['id']);

$page_title = 'Sự cố ' . $sc['ma_theo_doi'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <p class="breadcrumb"><a href="su_co.php">Sự cố</a> / <?= e($sc['ma_theo_doi']) ?></p>
        <h1>Sự cố <?= e($sc['ma_theo_doi']) ?> <?= nhan($sc['trang_thai'], TRANG_THAI_SU_CO) ?></h1>
        <p class="muted">Phòng <?= e($sc['ma_phong']) ?> · <?= e($sc['loai']) ?> · Báo lúc <?= e(ngay_gio($sc['ngay_bao'])) ?></p>
    </div>
    <div class="head-actions">
        <a class="btn" href="phong_chi_tiet.php?id=<?= (int)$sc['phong_id'] ?>">Xem phòng <?= e($sc['ma_phong']) ?></a>
        <a class="btn" href="tra_cuu_su_co.php?ma=<?= e(urlencode($sc['ma_theo_doi'])) ?>" target="_blank" rel="noopener">Trang khách xem tiến độ</a>
    </div>
</div>

<div class="grid-2">
    <div>
        <div class="card">
            <div class="card-head"><h2>Nội dung sự cố</h2></div>
            <div class="card-body">
                <dl class="info-list">
                    <dt>Phòng</dt><dd><a href="phong_chi_tiet.php?id=<?= (int)$sc['phong_id'] ?>"><?= e($sc['ma_phong']) ?></a></dd>
                    <dt>Loại sự cố</dt><dd><?= e($sc['loai']) ?></dd>
                    <dt>Người báo</dt>
                    <dd><?php if ($sc['khach_thue_id']): ?>
                            <a href="khach_thue_chi_tiet.php?id=<?= (int)$sc['khach_thue_id'] ?>"><?= e($sc['nguoi_bao']) ?></a>
                            · <?= e(sdt($sc['sdt_nguoi_bao'])) ?>
                        <?php else: ?>
                            <span class="muted">Quản lý ghi nhận</span>
                        <?php endif; ?></dd>
                    <dt>Ngày báo</dt><dd><?= e(ngay_gio($sc['ngay_bao'])) ?></dd>
                    <dt>Cập nhật gần nhất</dt><dd><?= $sc['ngay_cap_nhat'] ? e(ngay_gio($sc['ngay_cap_nhat'])) : '<span class="muted">Chưa cập nhật</span>' ?></dd>
                    <dt>Mô tả</dt><dd class="issue-desc"><?= e($sc['mo_ta']) ?></dd>
                    <?php if ($sc['trang_thai'] === 'da_xong' && $sc['ghi_chu_xu_ly']): ?>
                        <dt>Ghi chú xử lý</dt><dd class="text-green"><?= e($sc['ghi_chu_xu_ly']) ?></dd>
                    <?php endif; ?>
                    <?php if (is_chu_tro() && $sc['chi_phi'] !== null): ?>
                        <dt>Chi phí sửa chữa</dt>
                        <dd><span class="cost-tag"><?= e(tien($sc['chi_phi'])) ?></span> · ngày <?= e(ngay($sc['ngay_sua'])) ?> · <?= e($sc['tho_sua']) ?></dd>
                    <?php endif; ?>
                </dl>
                <?php if ($sc['anh']): ?>
                    <p class="mt" style="margin-bottom:6px"><strong>Ảnh minh họa</strong></p>
                    <a class="photo-link" href="<?= e($sc['anh']) ?>" target="_blank" rel="noopener">
                        <img class="photo" src="<?= e($sc['anh']) ?>" alt="Ảnh minh họa sự cố <?= e($sc['ma_theo_doi']) ?>"></a>
                <?php endif; ?>
            </div>
        </div>

        <!-- SC003 – AC1: lịch sử đổi trạng thái kèm thời điểm -->
        <div class="card section">
            <div class="card-head"><h2>Lịch sử xử lý</h2><span class="muted"><?= count($lich_su) ?> lần cập nhật</span></div>
            <div class="card-body">
                <ul class="timeline">
                    <?php foreach ($lich_su as $ls): ?>
                        <li class="<?= e($ls['trang_thai']) ?>">
                            <?= nhan($ls['trang_thai'], TRANG_THAI_SU_CO) ?>
                            <div class="when"><?= e(ngay_gio($ls['thoi_diem'])) ?> ·
                                <?= $ls['nguoi'] ? e($ls['nguoi']) . ' (' . e(VAI_TRO[$ls['vai_tro']]) . ')' : 'Khách thuê' ?></div>
                            <?php if ($ls['ghi_chu']): ?><div class="note-text"><?= e($ls['ghi_chu']) ?></div><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div>
        <!-- SC003: đổi trạng thái -->
        <div class="card">
            <div class="card-head"><h2>Cập nhật trạng thái</h2></div>
            <div class="card-body">
                <form method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$sc['id'] ?>">
                    <input type="hidden" name="hanh_dong" value="trang_thai">
                    <div class="field">
                        <label for="trang_thai">Trạng thái mới</label>
                        <select id="trang_thai" name="trang_thai" class="<?= lop_loi($loi, 'trang_thai') ?>">
                            <?php foreach (TRANG_THAI_SU_CO as $ma => $ten): ?>
                                <option value="<?= e($ma) ?>"<?= $ma === $form_tt['trang_thai'] ? ' selected' : '' ?>>
                                    <?= e($ten) ?><?= $ma === $sc['trang_thai'] ? ' (hiện tại)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= loi_o($loi, 'trang_thai') ?>
                    </div>
                    <div class="field">
                        <label for="ghi_chu">Ghi chú</label>
                        <textarea id="ghi_chu" name="ghi_chu" rows="3" maxlength="255"
                                  class="<?= lop_loi($loi, 'ghi_chu') ?>"
                                  placeholder="Ví dụ: Đã hẹn thợ điện 8h sáng mai"><?= e($form_tt['ghi_chu']) ?></textarea>
                        <?php if (isset($loi['ghi_chu'])): ?>
                            <?= loi_o($loi, 'ghi_chu') ?>
                        <?php else: ?>
                            <div class="hint">Bắt buộc khi chuyển sang <strong>Đã xong</strong>. Khách thuê đọc được ghi chú này ở trang tra cứu.</div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Cập nhật</button>
                </form>
            </div>
        </div>

        <?php if (is_chu_tro()): ?>
            <!-- SC004 – AC1: số tiền, ngày sửa, tên thợ -->
            <div class="card section">
                <div class="card-head"><h2>Chi phí sửa chữa</h2><span class="muted">Chỉ chủ trọ thấy</span></div>
                <div class="card-body">
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$sc['id'] ?>">
                        <input type="hidden" name="hanh_dong" value="chi_phi">
                        <div class="field">
                            <label for="chi_phi">Số tiền (đ) <span class="required">*</span></label>
                            <input type="text" id="chi_phi" name="chi_phi" inputmode="numeric" value="<?= e($form_cp['chi_phi']) ?>"
                                   class="<?= lop_loi($loi, 'chi_phi') ?>" placeholder="Ví dụ 350.000">
                            <?= loi_o($loi, 'chi_phi') ?>
                        </div>
                        <div class="field">
                            <label for="ngay_sua">Ngày sửa <span class="required">*</span></label>
                            <input type="date" id="ngay_sua" name="ngay_sua" value="<?= e($form_cp['ngay_sua']) ?>"
                                   min="<?= e(substr($sc['ngay_bao'], 0, 10)) ?>" max="<?= e(hom_nay()) ?>" class="<?= lop_loi($loi, 'ngay_sua') ?>">
                            <?= loi_o($loi, 'ngay_sua') ?>
                        </div>
                        <div class="field">
                            <label for="tho_sua">Thợ sửa <span class="required">*</span></label>
                            <input type="text" id="tho_sua" name="tho_sua" maxlength="100" value="<?= e($form_cp['tho_sua']) ?>"
                                   class="<?= lop_loi($loi, 'tho_sua') ?>" placeholder="Ví dụ: Anh Hải – thợ nước">
                            <?= loi_o($loi, 'tho_sua') ?>
                        </div>
                        <button type="submit" class="btn btn-block"><?= $sc['chi_phi'] !== null ? 'Sửa chi phí' : 'Lưu chi phí' ?></button>
                        <p class="small muted mt">Tổng chi phí từng tháng xem ở <a href="su_co.php#chi-phi">cuối trang Sự cố</a>.</p>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
