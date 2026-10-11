<?php
/**
 * =====================================================================
 *  DN004 – CẤU HÌNH ĐƠN GIÁ DỊCH VỤ (chỉ Chủ trọ – TK005 AC1)
 *    AC1: Lưu được ngày bắt đầu áp dụng của mỗi mức giá
 *    AC2: Hóa đơn cũ vẫn giữ nguyên đơn giá tại thời điểm lập
 *         -> không sửa mức giá cũ, chỉ thêm mức giá mới có ngày áp dụng;
 *            hóa đơn đã chép đơn giá vào chính nó lúc lập (bảng hoa_don).
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

$du_lieu = [
    'dich_vu'    => 'dien',
    'don_gia'    => '',
    'ap_dung_tu' => date('Y-m-01', strtotime('first day of next month')),
];
$loi = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $hanh_dong = gia_tri_post('hanh_dong');

    if ($hanh_dong === 'xoa') {
        // Chỉ xóa được mức giá chưa tới ngày áp dụng (đặt nhầm), mức giá đã dùng thì giữ lại làm lịch sử
        $stmt = db()->prepare('SELECT * FROM don_gia WHERE id = ?');
        $stmt->execute([(int)gia_tri_post('id', '0')]);
        $gia = $stmt->fetch();
        if ($gia && $gia['ap_dung_tu'] > hom_nay()) {
            db()->prepare('DELETE FROM don_gia WHERE id = ?')->execute([$gia['id']]);
            flash('success', 'Đã xóa mức giá ' . DICH_VU[$gia['dich_vu']]['ten'] . ' dự kiến áp dụng từ ' . ngay($gia['ap_dung_tu']) . '.');
        } else {
            flash('error', 'Chỉ xóa được mức giá chưa tới ngày áp dụng.');
        }
        redirect('don_gia.php');
    }

    foreach ($du_lieu as $ten_o => $cu) {
        $du_lieu[$ten_o] = gia_tri_post($ten_o);
    }
    if (!array_key_exists($du_lieu['dich_vu'], DICH_VU)) {
        $loi['dich_vu'] = 'Vui lòng chọn dịch vụ.';
    }
    $gia_moi = doc_so_tien($du_lieu['don_gia']);
    if ($gia_moi === null || $gia_moi <= 0 || $gia_moi > 10000000) {
        $loi['don_gia'] = 'Đơn giá phải là số dương, tối đa 10.000.000 đ.';
    }
    if (!ngay_hop_le($du_lieu['ap_dung_tu'])) {
        $loi['ap_dung_tu'] = 'Vui lòng chọn ngày bắt đầu áp dụng.';     // AC1
    } elseif (!$loi) {
        $stmt = db()->prepare('SELECT don_gia FROM don_gia WHERE dich_vu = ? AND ap_dung_tu = ?');
        $stmt->execute([$du_lieu['dich_vu'], $du_lieu['ap_dung_tu']]);
        $trung = $stmt->fetchColumn();
        if ($trung !== false) {
            $loi['ap_dung_tu'] = 'Đã có mức giá ' . tien($trung) . ' áp dụng từ ngày này. Chọn ngày khác.';
        }
    }

    if (!$loi) {
        db()->prepare('INSERT INTO don_gia (dich_vu, don_gia, ap_dung_tu, nguoi_tao) VALUES (?, ?, ?, ?)')
            ->execute([$du_lieu['dich_vu'], $gia_moi, $du_lieu['ap_dung_tu'], id_nguoi_dung()]);
        flash('success', 'Đã đặt giá ' . DICH_VU[$du_lieu['dich_vu']]['ten'] . ' ' . tien($gia_moi) . ' (' . DICH_VU[$du_lieu['dich_vu']]['don_vi']
            . ') áp dụng từ ngày ' . ngay($du_lieu['ap_dung_tu']) . '. Các hóa đơn đã lập vẫn giữ giá cũ.');
        redirect('don_gia.php');
    }
}

list($gia_hom_nay) = bang_don_gia(hom_nay());
$lich_su = db()->query("SELECT d.*, t.ho_ten AS nguoi FROM don_gia d LEFT JOIN tai_khoan t ON t.id = d.nguoi_tao
                        ORDER BY FIELD(d.dich_vu, 'dien', 'nuoc', 'rac', 'wifi', 'gui_xe'), d.ap_dung_tu DESC")->fetchAll();

// Mức nào đang áp dụng: mức có ngày áp dụng gần nhất không sau hôm nay, của từng dịch vụ
$dang_ap_dung = [];
foreach ($lich_su as $g) {
    if ($g['ap_dung_tu'] <= hom_nay() && !isset($dang_ap_dung[$g['dich_vu']])) {
        $dang_ap_dung[$g['dich_vu']] = (int)$g['id'];
    }
}

$page_title = 'Đơn giá dịch vụ';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Đơn giá dịch vụ</h1>
        <p class="muted">Đổi giá bằng cách thêm mức giá mới kèm ngày áp dụng. Hóa đơn đã lập luôn giữ đơn giá tại lúc lập.</p>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>Đơn giá đang áp dụng hôm nay</h2><span class="muted"><?= e(ngay(hom_nay())) ?></span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Dịch vụ</th><th class="num">Đơn giá</th><th>Đơn vị tính</th></tr></thead>
                <tbody>
                <?php foreach (DICH_VU as $ma => $dv): ?>
                    <tr>
                        <td class="strong"><?= e($dv['ten']) ?></td>
                        <td class="num"><?= $gia_hom_nay[$ma] !== null ? e(tien($gia_hom_nay[$ma])) : '<span class="text-red">Chưa đặt giá</span>' ?></td>
                        <td class="muted"><?= e($dv['don_vi']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Đặt mức giá mới</h2></div>
        <div class="card-body">
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="field">
                    <label for="dich_vu">Dịch vụ</label>
                    <select id="dich_vu" name="dich_vu" class="<?= lop_loi($loi, 'dich_vu') ?>">
                        <?php foreach (DICH_VU as $ma => $dv): ?>
                            <option value="<?= $ma ?>"<?= $ma === $du_lieu['dich_vu'] ? ' selected' : '' ?>><?= e($dv['ten']) ?> (<?= e($dv['don_vi']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?= loi_o($loi, 'dich_vu') ?>
                </div>
                <div class="field">
                    <label for="don_gia">Đơn giá mới (đ) <span class="required">*</span></label>
                    <input type="text" id="don_gia" name="don_gia" inputmode="numeric" value="<?= e($du_lieu['don_gia']) ?>"
                           placeholder="Ví dụ: 4.000" class="<?= lop_loi($loi, 'don_gia') ?>">
                    <?= loi_o($loi, 'don_gia') ?>
                </div>
                <div class="field">
                    <label for="ap_dung_tu">Áp dụng từ ngày <span class="required">*</span></label>
                    <input type="date" id="ap_dung_tu" name="ap_dung_tu" value="<?= e($du_lieu['ap_dung_tu']) ?>"
                           class="<?= lop_loi($loi, 'ap_dung_tu') ?>">
                    <?php if (isset($loi['ap_dung_tu'])): ?>
                        <?= loi_o($loi, 'ap_dung_tu') ?>
                    <?php else: ?>
                        <div class="hint">Hóa đơn của tháng có ngày cuối tháng từ ngày này trở đi sẽ dùng giá mới.</div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Lưu mức giá mới</button>
            </form>
        </div>
    </div>
</div>

<div class="card section">
    <div class="card-head"><h2>Lịch sử đơn giá</h2><span class="muted"><?= count($lich_su) ?> mức giá</span></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Dịch vụ</th><th class="num">Đơn giá</th><th>Áp dụng từ</th><th>Tình trạng</th><th>Người đặt</th><th class="actions"></th></tr></thead>
            <tbody>
            <?php foreach ($lich_su as $g): ?>
                <tr>
                    <td><?= e(DICH_VU[$g['dich_vu']]['ten']) ?></td>
                    <td class="num"><?= e(tien($g['don_gia'])) ?> <span class="sub"><?= e(DICH_VU[$g['dich_vu']]['don_vi']) ?></span></td>
                    <td><?= e(ngay($g['ap_dung_tu'])) ?></td>
                    <td>
                        <?php if ($g['ap_dung_tu'] > hom_nay()): ?>
                            <span class="badge badge-sap_het_han">Sắp áp dụng</span>
                        <?php elseif (isset($dang_ap_dung[$g['dich_vu']]) && $dang_ap_dung[$g['dich_vu']] === (int)$g['id']): ?>
                            <span class="badge badge-hieu_luc">Đang áp dụng</span>
                        <?php else: ?>
                            <span class="badge">Đã thay bằng giá mới</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($g['nguoi'] ?: '—') ?></td>
                    <td class="actions">
                        <?php if ($g['ap_dung_tu'] > hom_nay()): ?>
                            <form method="post" class="inline" data-confirm="Xóa mức giá dự kiến này?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="hanh_dong" value="xoa">
                                <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
