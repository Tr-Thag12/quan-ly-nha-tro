<?php
/**
 * =====================================================================
 *  TT006 – IN HÓA ĐƠN
 *    AC1: Bản in có tên nhà trọ, tháng, chi tiết khoản mục, tổng tiền
 *    AC2: In vừa khổ giấy A5
 *  Trang riêng không có menu. Bấm "In hóa đơn" (hoặc Ctrl+P) rồi chọn máy in,
 *  hoặc chọn "Lưu dưới dạng PDF" để gửi khách qua Zalo.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$stmt = db()->prepare('SELECT hd.*, p.ma_phong, h.ma_hop_dong, k.ho_ten, k.so_dien_thoai, tk.ho_ten AS nguoi_lap
                       FROM hoa_don hd
                       JOIN phong p      ON p.id = hd.phong_id
                       JOIN hop_dong h   ON h.id = hd.hop_dong_id
                       JOIN khach_thue k ON k.id = h.khach_thue_id
                       LEFT JOIN tai_khoan tk ON tk.id = hd.nguoi_tao
                       WHERE hd.id = ?');
$stmt->execute([(int)gia_tri_get('id', '0')]);
$hd = $stmt->fetch();
if (!$hd) {
    flash('error', 'Không tìm thấy hóa đơn.');
    redirect('hoa_don.php');
}

$stmt = db()->prepare('SELECT MAX(ngay_thu) FROM thanh_toan WHERE hoa_don_id = ?');
$stmt->execute([$hd['id']]);
$ngay_thu_cuoi = $stmt->fetchColumn();

$con_lai = (int)$hd['tong_tien'] - (int)$hd['da_thu'];
$dien = (int)$hd['dien_moi'] - (int)$hd['dien_cu'];
$nuoc = (int)$hd['nuoc_moi'] - (int)$hd['nuoc_cu'];

// AC1: chi tiết khoản mục [tên khoản, chỉ số cũ, chỉ số mới, số lượng, đơn giá, thành tiền]
$cac_khoan = [
    ['Tiền phòng', '', '', '1 tháng', $hd['tien_phong'], $hd['tien_phong']],
    ['Tiền điện', $hd['dien_cu'], $hd['dien_moi'], $dien . ' kWh', $hd['don_gia_dien'], $hd['tien_dien']],
    ['Tiền nước', $hd['nuoc_cu'], $hd['nuoc_moi'], $nuoc . ' m³', $hd['don_gia_nuoc'], $hd['tien_nuoc']],
    ['Rác', '', '', $hd['so_nguoi'] . ' người', $hd['don_gia_rac'], $hd['tien_rac']],
    ['Wifi', '', '', '1 phòng', $hd['don_gia_wifi'], $hd['tien_wifi']],
    ['Gửi xe', '', '', $hd['so_xe'] . ' xe', $hd['don_gia_gui_xe'], $hd['tien_gui_xe']],
];
$so = function ($n) {
    return number_format((float)$n, 0, ',', '.');
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hóa đơn <?= e($hd['ma_hoa_don']) ?></title>
    <style>
        /* AC2: khổ A5 (148 x 210 mm) */
        @page { size: A5 portrait; margin: 9mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, "Segoe UI", sans-serif; font-size: 12px; line-height: 1.45; color: #111; background: #e9ebef; margin: 0; }
        .giay { background: #fff; width: 148mm; min-height: 210mm; margin: 16px auto; padding: 9mm; box-shadow: 0 2px 12px rgba(0,0,0,.18); }
        .dau { display: flex; justify-content: space-between; gap: 10px; border-bottom: 2px solid #1F3864; padding-bottom: 6px; }
        .ten-tro { font-size: 15px; font-weight: bold; color: #1F3864; text-transform: uppercase; margin: 0; }
        .dau p { margin: 1px 0; }
        .so { text-align: right; white-space: nowrap; }
        h1 { font-size: 16px; text-align: center; margin: 12px 0 0; letter-spacing: .3px; }
        .ky-tinh { text-align: center; margin: 2px 0 10px; color: #444; font-style: italic; }
        .khach { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 10px; margin-bottom: 8px; }
        .khach span { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #888; padding: 3px 5px; }
        th { background: #E8EEF8; font-size: 11px; }
        td.so-lieu, th.so-lieu { text-align: right; white-space: nowrap; }
        td.giua { text-align: center; }
        tr.tong td { font-weight: bold; font-size: 13px; background: #F3F5F9; }
        .bang-chu { margin: 6px 0 4px; }
        .thanh-toan { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 10px; margin: 6px 0; }
        .han { border: 1px dashed #1F3864; padding: 5px 8px; margin: 8px 0; text-align: center; }
        .ky { display: flex; justify-content: space-between; text-align: center; margin-top: 12px; }
        .ky div { width: 46%; }
        .ky .ten { margin-top: 42px; font-weight: bold; }
        .con-dau { width: fit-content; margin: 10px auto 4px; transform: rotate(-4deg); border: 3px double #1E7B4F; color: #1E7B4F;
                   padding: 4px 16px; font-weight: bold; font-size: 15px; letter-spacing: .5px; text-align: center; border-radius: 6px; }
        .con-dau small { display: block; font-size: 10px; font-weight: normal; letter-spacing: 0; }
        .nut { text-align: center; margin: 16px; font-family: Arial, sans-serif; }
        .nut button { font-size: 15px; padding: 9px 20px; cursor: pointer; margin-right: 10px; }
        @media print {
            body { background: #fff; }
            .giay { margin: 0; width: auto; min-height: 0; padding: 0; box-shadow: none; }
            .nut { display: none; }
            th, tr.tong td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        @media (max-width: 600px) { .giay { width: auto; min-height: 0; margin: 8px; padding: 14px; } }
    </style>
</head>
<body>
<div class="nut">
    <button type="button" onclick="window.print()">In hóa đơn (khổ A5)</button>
    <a href="hoa_don_chi_tiet.php?id=<?= (int)$hd['id'] ?>">Quay lại hóa đơn</a>
</div>

<div class="giay">
    <!-- AC1: tên nhà trọ -->
    <div class="dau">
        <div>
            <p class="ten-tro"><?= e(TEN_NHA_TRO) ?></p>
            <p><?= e(DIA_CHI_NHA_TRO) ?></p>
            <p>Điện thoại: <?= e(SDT_NHA_TRO) ?></p>
        </div>
        <div class="so">
            <p>Số: <strong><?= e($hd['ma_hoa_don']) ?></strong></p>
            <p>Ngày lập: <?= e(ngay($hd['ngay_tao'])) ?></p>
        </div>
    </div>

    <!-- AC1: tháng -->
    <h1>HÓA ĐƠN TIỀN PHÒNG THÁNG <?= e(ten_thang($hd['thang'])) ?></h1>
    <p class="ky-tinh">Kỳ tính tiền từ <?= e(ngay(ngay_dau_thang($hd['thang']))) ?> đến <?= e(ngay(ngay_cuoi_thang($hd['thang']))) ?></p>

    <div class="khach">
        <div><span>Phòng:</span> <strong><?= e($hd['ma_phong']) ?></strong></div>
        <div><span>Hợp đồng:</span> <?= e($hd['ma_hop_dong']) ?></div>
        <div><span>Người đại diện:</span> <strong><?= e($hd['ho_ten']) ?></strong></div>
        <div><span>Điện thoại:</span> <?= e(sdt($hd['so_dien_thoai'])) ?></div>
    </div>

    <!-- AC1: chi tiết khoản mục -->
    <table>
        <thead>
            <tr><th>STT</th><th>Khoản</th><th class="so-lieu">Số cũ</th><th class="so-lieu">Số mới</th>
                <th class="so-lieu">Số lượng</th><th class="so-lieu">Đơn giá</th><th class="so-lieu">Thành tiền</th></tr>
        </thead>
        <tbody>
        <?php foreach ($cac_khoan as $i => $k): ?>
            <tr>
                <td class="giua"><?= $i + 1 ?></td>
                <td><?= e($k[0]) ?></td>
                <td class="so-lieu"><?= $k[1] === '' ? '' : (int)$k[1] ?></td>
                <td class="so-lieu"><?= $k[2] === '' ? '' : (int)$k[2] ?></td>
                <td class="so-lieu"><?= e($k[3]) ?></td>
                <td class="so-lieu"><?= e($so($k[4])) ?></td>
                <td class="so-lieu"><?= e($so($k[5])) ?></td>
            </tr>
        <?php endforeach; ?>
            <!-- AC1: tổng tiền -->
            <tr class="tong"><td colspan="6">TỔNG CỘNG (đồng)</td><td class="so-lieu"><?= e($so($hd['tong_tien'])) ?></td></tr>
        </tbody>
    </table>
    <p class="bang-chu"><em>Bằng chữ: <?= e(tien_bang_chu($hd['tong_tien'])) ?>.</em></p>

    <?php if ((int)$hd['da_thu'] > 0): ?>
        <div class="thanh-toan">
            <div>Đã thu: <strong><?= e(tien($hd['da_thu'])) ?></strong></div>
            <div>Còn phải thu: <strong><?= e(tien(max(0, $con_lai))) ?></strong></div>
        </div>
    <?php endif; ?>

    <?php if ($con_lai <= 0): ?>
        <div class="con-dau">ĐÃ THANH TOÁN<?php if ($ngay_thu_cuoi): ?><small>Ngày <?= e(ngay($ngay_thu_cuoi)) ?></small><?php endif; ?></div>
    <?php else: ?>
        <div class="han">Vui lòng thanh toán <strong><?= e(tien($con_lai)) ?></strong> trước ngày
            <strong><?= e(ngay($hd['han_thanh_toan'])) ?></strong> bằng tiền mặt hoặc chuyển khoản cho quản lý nhà trọ.</div>
    <?php endif; ?>

    <div class="ky">
        <div><strong>Khách thuê</strong><br><em>(Ký, ghi rõ họ tên)</em><div class="ten"><?= e($hd['ho_ten']) ?></div></div>
        <div><strong>Người lập phiếu</strong><br><em>(Ký, ghi rõ họ tên)</em>
            <div class="ten"><?= e($hd['nguoi_lap'] ?: current_user()['ho_ten']) ?></div></div>
    </div>
</div>
</body>
</html>
