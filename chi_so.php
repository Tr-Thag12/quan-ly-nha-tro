<?php
/**
 * =====================================================================
 *  ĐIỆN NƯỚC THEO THÁNG – màn hình chính của Epic "Ghi chỉ số điện nước"
 *
 *  DN005 – NHẬP CHỈ SỐ HÀNG LOẠT
 *    AC1: Hiển thị bảng gồm tất cả phòng đang thuê
 *    AC2: Lưu một lần cho toàn bộ bảng
 *    AC3: Phòng nào nhập sai thì báo riêng dòng đó (dòng tô đỏ, các dòng đúng vẫn được lưu)
 *
 *  DN001 – GHI CHỈ SỐ HẰNG THÁNG (dùng chung hàm với chi_so_ghi.php)
 *    AC1: Tự hiển thị chỉ số tháng trước để đối chiếu   -> cột "cũ"
 *    AC2: Chỉ số mới không được nhỏ hơn chỉ số cũ       -> kiem_tra_chi_so()
 *    AC3: Tự tính ra số điện, số nước đã dùng           -> cột "dùng"
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$thang = gia_tri_get('thang', gia_tri_post('thang', thang_hien_tai()));
if (!thang_hop_le($thang) || $thang > thang_hien_tai()) {
    $thang = thang_hien_tai();
}

/** Dựng lại dữ liệu từng dòng của bảng (gọi lại sau khi lưu để thấy phòng vừa ghi). */
function dung_bang_chi_so($thang)
{
    $bang = [];
    foreach (hop_dong_can_ghi_chi_so($thang) as $hd) {
        $da_ghi   = chi_so_thang($hd['phong_id'], $thang);
        $moi_nhat = thang_ghi_moi_nhat($hd['phong_id']);
        $dong = [
            'hd'       => $hd,
            'da_ghi'   => $da_ghi,
            'moi_nhat' => $moi_nhat,
            // Chỉ số phải ghi theo thứ tự tháng: không ghi bù tháng cũ khi đã có tháng mới hơn
            'nhap_duoc' => !$da_ghi && ($moi_nhat === null || $moi_nhat < $thang),
            'cu'       => $da_ghi ? ['dien' => (int)$da_ghi['dien_cu'], 'nuoc' => (int)$da_ghi['nuoc_cu'], 'nguon' => '']
                                  : chi_so_cu($hd, $thang),
            'sua_duoc' => $da_ghi ? ly_do_khong_sua_chi_so($da_ghi) === null : false,
        ];
        $stmt = db()->prepare('SELECT id, trang_thai FROM hoa_don WHERE phong_id = ? AND thang = ?');
        $stmt->execute([$hd['phong_id'], $thang]);
        $dong['hoa_don'] = $stmt->fetch();
        $bang[$hd['phong_id']] = $dong;
    }
    return $bang;
}

$bang = dung_bang_chi_so($thang);
$nhap = ['dien' => [], 'nuoc' => []];   // giá trị người dùng gõ, giữ lại khi có dòng lỗi
$loi_dong = [];                          // AC3: lỗi của từng phòng
$so_da_luu = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $dien_post = isset($_POST['dien_moi']) && is_array($_POST['dien_moi']) ? $_POST['dien_moi'] : [];
    $nuoc_post = isset($_POST['nuoc_moi']) && is_array($_POST['nuoc_moi']) ? $_POST['nuoc_moi'] : [];

    // AC2: một lần bấm Lưu xử lý cả bảng
    foreach ($bang as $phong_id => $dong) {
        $dien = trim((string)(isset($dien_post[$phong_id]) ? $dien_post[$phong_id] : ''));
        $nuoc = trim((string)(isset($nuoc_post[$phong_id]) ? $nuoc_post[$phong_id] : ''));
        if (!$dong['nhap_duoc'] || ($dien === '' && $nuoc === '')) {
            continue;   // phòng chưa đọc số thì để trống, bỏ qua
        }
        $nhap['dien'][$phong_id] = $dien;
        $nhap['nuoc'][$phong_id] = $nuoc;
        $loi = kiem_tra_chi_so($dien, $nuoc, $dong['cu']);    // DN001 – AC2
        if ($loi) {
            $loi_dong[$phong_id] = $loi;                         // AC3: báo riêng dòng này
            continue;
        }
        them_chi_so($phong_id, $thang, $dong['cu'], $dien, $nuoc);
        $so_da_luu++;
    }

    if (!$loi_dong) {
        flash($so_da_luu ? 'success' : 'info', $so_da_luu
            ? 'Đã lưu chỉ số tháng ' . ten_thang($thang) . ' của ' . $so_da_luu . ' phòng.'
            : 'Chưa có phòng nào được nhập chỉ số.');
        redirect('chi_so.php?thang=' . $thang);
    }
    $bang = dung_bang_chi_so($thang);   // phòng vừa lưu đúng giờ hiện là "đã ghi"
}

$so_phong   = count($bang);
$so_da_ghi  = count(array_filter($bang, function ($d) { return (bool)$d['da_ghi']; }));
$so_co_hd   = count(array_filter($bang, function ($d) { return (bool)$d['hoa_don']; }));
$con_nhap   = count(array_filter($bang, function ($d) { return $d['nhap_duoc']; }));

$page_title = 'Điện nước tháng ' . ten_thang($thang);
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Chỉ số điện nước tháng <?= e(ten_thang($thang)) ?></h1>
        <p class="muted">Đã ghi <strong><?= $so_da_ghi ?>/<?= $so_phong ?></strong> phòng đang thuê
            · Đã lập hóa đơn <?= $so_co_hd ?> phòng. Chỉ số cũ tự lấy từ tháng trước.</p>
    </div>
    <div class="head-actions">
        <?php if ($so_da_ghi > $so_co_hd): ?>
            <a class="btn btn-primary" href="hoa_don_tao.php?thang=<?= e($thang) ?>">Lập hóa đơn tháng <?= e(ten_thang($thang)) ?> &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($loi_dong): ?>
    <div class="alert alert-error">
        <?= $so_da_luu ? 'Đã lưu ' . $so_da_luu . ' phòng nhập đúng. ' : '' ?>
        Có <?= count($loi_dong) ?> phòng nhập sai, xem các dòng tô đỏ bên dưới rồi bấm Lưu lại.
    </div>
<?php endif; ?>

<div class="card">
    <form class="toolbar" method="get">
        <div class="field">
            <label for="thang">Tháng chốt số</label>
            <select id="thang" name="thang" data-tu-gui>
                <?php foreach (cac_thang_gan_nhat(12) as $t): ?>
                    <option value="<?= e($t) ?>"<?= $t === $thang ? ' selected' : '' ?>>Tháng <?= e(ten_thang($t)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="small muted" style="margin:0 0 8px">Gõ chỉ số mới đọc trên công tơ. Phòng nào chưa đọc thì để trống, lần sau nhập tiếp.</p>
    </form>

    <?php if (!$bang): ?>
        <div class="empty-filter">Tháng <?= e(ten_thang($thang)) ?> không có phòng nào đang cho thuê.</div>
    <?php else: ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="thang" value="<?= e($thang) ?>">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Phòng</th>
                        <th>Người đại diện</th>
                        <th class="num">Điện cũ</th>
                        <th class="num">Điện mới</th>
                        <th class="num">Dùng</th>
                        <th class="num">Nước cũ</th>
                        <th class="num">Nước mới</th>
                        <th class="num">Dùng</th>
                        <th>Tình trạng</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($bang as $phong_id => $d): ?>
                    <?php $loi = isset($loi_dong[$phong_id]) ? $loi_dong[$phong_id] : []; ?>
                    <tr class="<?= $loi ? 'row-error' : ($d['nhap_duoc'] ? '' : 'row-muted') ?>">
                        <td><a class="strong" href="chi_so_lich_su.php?phong_id=<?= (int)$phong_id ?>"><?= e($d['hd']['ma_phong']) ?></a></td>
                        <td><?= e($d['hd']['ho_ten']) ?></td>
                        <td class="num"><?= $d['cu']['dien'] ?>
                            <?php if ($d['cu']['nguon'] === 'Lúc nhận phòng'): ?><span class="sub">lúc nhận phòng</span><?php endif; ?></td>
                        <?php if ($d['da_ghi']): ?>
                            <td class="num"><?= (int)$d['da_ghi']['dien_moi'] ?></td>
                            <td class="num strong"><?= $d['da_ghi']['dien_moi'] - $d['da_ghi']['dien_cu'] ?> kWh</td>
                            <td class="num"><?= (int)$d['da_ghi']['nuoc_cu'] ?></td>
                            <td class="num"><?= (int)$d['da_ghi']['nuoc_moi'] ?></td>
                            <td class="num strong"><?= $d['da_ghi']['nuoc_moi'] - $d['da_ghi']['nuoc_cu'] ?> m³</td>
                            <td>
                                <?php if ($d['hoa_don']): ?>
                                    <a href="hoa_don_chi_tiet.php?id=<?= (int)$d['hoa_don']['id'] ?>"><?= nhan($d['hoa_don']['trang_thai'], TRANG_THAI_HOA_DON) ?></a>
                                <?php else: ?>
                                    <span class="badge badge-trong">Đã ghi</span>
                                <?php endif; ?>
                                <?php if ($d['sua_duoc']): ?>
                                    <a class="btn btn-sm" href="chi_so_ghi.php?phong_id=<?= (int)$phong_id ?>&amp;thang=<?= e($thang) ?>">Sửa</a>
                                <?php endif; ?>
                            </td>
                        <?php elseif ($d['nhap_duoc']): ?>
                            <td class="num">
                                <input type="text" class="input-sm <?= lop_loi($loi, 'dien_moi') ?>" inputmode="numeric"
                                       name="dien_moi[<?= (int)$phong_id ?>]" aria-label="Điện mới <?= e($d['hd']['ma_phong']) ?>"
                                       value="<?= e(isset($nhap['dien'][$phong_id]) ? $nhap['dien'][$phong_id] : '') ?>"
                                       data-cu="<?= $d['cu']['dien'] ?>" data-don-vi="kWh" data-ket-qua="dien-<?= (int)$phong_id ?>">
                                <?= loi_o($loi, 'dien_moi') ?>
                            </td>
                            <td class="num"><span id="dien-<?= (int)$phong_id ?>" class="dung"></span></td>
                            <td class="num"><?= $d['cu']['nuoc'] ?></td>
                            <td class="num">
                                <input type="text" class="input-sm <?= lop_loi($loi, 'nuoc_moi') ?>" inputmode="numeric"
                                       name="nuoc_moi[<?= (int)$phong_id ?>]" aria-label="Nước mới <?= e($d['hd']['ma_phong']) ?>"
                                       value="<?= e(isset($nhap['nuoc'][$phong_id]) ? $nhap['nuoc'][$phong_id] : '') ?>"
                                       data-cu="<?= $d['cu']['nuoc'] ?>" data-don-vi="m³" data-ket-qua="nuoc-<?= (int)$phong_id ?>">
                                <?= loi_o($loi, 'nuoc_moi') ?>
                            </td>
                            <td class="num"><span id="nuoc-<?= (int)$phong_id ?>" class="dung"></span></td>
                            <td><span class="badge badge-chua_thu">Chưa ghi</span></td>
                        <?php else: ?>
                            <td class="num">—</td><td></td><td class="num"><?= $d['cu']['nuoc'] ?></td><td class="num">—</td><td></td>
                            <td class="muted small">Đã ghi tới tháng <?= e(ten_thang($d['moi_nhat'])) ?></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="list-foot">
            <span class="page-info">
                <?= $con_nhap ? 'Còn ' . $con_nhap . ' phòng chưa ghi chỉ số tháng này.' : 'Đã ghi đủ chỉ số các phòng tháng này.' ?>
            </span>
            <?php if ($con_nhap): ?>
                <button type="submit" class="btn btn-primary">Lưu chỉ số cả bảng</button>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
