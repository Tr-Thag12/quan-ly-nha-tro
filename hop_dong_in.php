<?php
/**
 * =====================================================================
 *  HD003 – AC2: IN HỢP ĐỒNG RA GIẤY
 *  Trang riêng không có menu, trình bày theo khổ A4. Bấm nút In
 *  (hoặc Ctrl+P) rồi chọn máy in, hoặc chọn "Lưu dưới dạng PDF".
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

$hd = lay_hop_dong(gia_tri_get('id', '0'));
if (!$hd) {
    flash('error', 'Không tìm thấy hợp đồng.');
    redirect('hop_dong.php');
}
$stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
$stmt->execute([$hd['phong_id']]);
$phong = $stmt->fetch();
list($gia) = bang_don_gia(hom_nay());
$so_thang = (int)round(so_ngay($hd['ngay_bat_dau'], $hd['ngay_ket_thuc']) / 30.4);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hợp đồng <?= e($hd['ma_hop_dong']) ?></title>
    <style>
        @page { size: A4; margin: 18mm 18mm 16mm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 15px; line-height: 1.55; color: #000; background: #eee; margin: 0; }
        .giay { background: #fff; width: 210mm; min-height: 297mm; margin: 20px auto; padding: 18mm; box-sizing: border-box; box-shadow: 0 2px 12px rgba(0,0,0,.15); }
        .center { text-align: center; }
        .quoc-hieu { font-weight: bold; margin: 0; }
        .tieu-ngu { margin: 2px 0 0; font-weight: bold; }
        .gach { width: 160px; border-top: 1px solid #000; margin: 4px auto 18px; }
        h1 { font-size: 20px; margin: 10px 0 2px; }
        h2 { font-size: 15px; margin: 16px 0 6px; }
        p { margin: 4px 0; }
        table.gia { border-collapse: collapse; width: 100%; margin: 6px 0; }
        table.gia td { border: 1px solid #555; padding: 4px 8px; }
        .ky { display: flex; justify-content: space-between; margin-top: 30px; text-align: center; }
        .ky div { width: 45%; }
        .ky .ten { margin-top: 70px; font-weight: bold; }
        .nut { text-align: center; margin: 16px; font-family: Arial, sans-serif; }
        .nut button { font-size: 15px; padding: 10px 22px; cursor: pointer; }
        @media print { body { background: #fff; } .giay { margin: 0; width: auto; min-height: 0; padding: 0; box-shadow: none; } .nut { display: none; } }
    </style>
</head>
<body>
<div class="nut">
    <button type="button" onclick="window.print()">In hợp đồng</button>
    <a href="hop_dong_chi_tiet.php?id=<?= (int)$hd['id'] ?>">Quay lại</a>
</div>
<div class="giay">
    <div class="center">
        <p class="quoc-hieu">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
        <p class="tieu-ngu">Độc lập – Tự do – Hạnh phúc</p>
        <div class="gach"></div>
        <h1>HỢP ĐỒNG THUÊ PHÒNG TRỌ</h1>
        <p>Số: <?= e($hd['ma_hop_dong']) ?></p>
    </div>

    <p>Hôm nay, ngày <?= e(ngay($hd['ngay_bat_dau'])) ?>, tại <?= e(TEN_NHA_TRO) ?>, chúng tôi gồm:</p>

    <h2>BÊN CHO THUÊ (BÊN A)</h2>
    <p>Ông/bà: <strong><?= e(CHU_NHA_TRO) ?></strong> – chủ <?= e(TEN_NHA_TRO) ?></p>
    <p>Địa chỉ: <?= e(DIA_CHI_NHA_TRO) ?> &nbsp;·&nbsp; Điện thoại: <?= e(SDT_NHA_TRO) ?></p>

    <h2>BÊN THUÊ (BÊN B)</h2>
    <p>Ông/bà: <strong><?= e($hd['ho_ten']) ?></strong> &nbsp;·&nbsp; Số CCCD: <?= e($hd['cccd']) ?></p>
    <p>Điện thoại: <?= e(sdt($hd['so_dien_thoai'])) ?> &nbsp;·&nbsp; Quê quán: <?= e($hd['que_quan']) ?></p>

    <p>Hai bên thống nhất ký hợp đồng thuê phòng với các điều khoản sau:</p>

    <h2>Điều 1. Phòng cho thuê</h2>
    <p>Bên A cho Bên B thuê phòng <strong><?= e($phong['ma_phong']) ?></strong>, tầng <?= (int)$phong['tang'] ?>,
        diện tích <?= e(dien_tich($phong['dien_tich'])) ?>, ở tối đa <?= (int)$phong['so_nguoi_toi_da'] ?> người.
        Tiện nghi kèm theo: <?= e($phong['tien_nghi'] ?: 'không có') ?>.</p>
    <p>Chỉ số công tơ lúc bàn giao: điện <?= (int)$hd['chi_so_dien_dau'] ?> kWh, nước <?= (int)$hd['chi_so_nuoc_dau'] ?> m³.</p>

    <h2>Điều 2. Giá thuê, tiền cọc và chi phí dịch vụ</h2>
    <table class="gia">
        <tr><td>Giá thuê phòng</td><td><strong><?= e(tien($hd['gia_thue'])) ?></strong> / tháng</td></tr>
        <tr><td>Tiền đặt cọc</td><td><strong><?= e(tien($hd['tien_coc'])) ?></strong>, hoàn lại khi thanh lý hợp đồng sau khi trừ các khoản còn nợ</td></tr>
        <?php foreach (DICH_VU as $ma => $dv): ?>
            <tr><td><?= e($dv['ten']) ?></td><td><?= $gia[$ma] !== null ? e(tien($gia[$ma])) : '—' ?> (<?= e($dv['don_vi']) ?>)</td></tr>
        <?php endforeach; ?>
        <tr><td>Số xe gửi</td><td><?= (int)$hd['so_xe'] ?> xe</td></tr>
    </table>
    <p>Tiền phòng và các chi phí được thanh toán hằng tháng, hạn chót ngày <?= NGAY_HAN_THANH_TOAN ?> của tháng kế tiếp.
        Đơn giá dịch vụ có thể điều chỉnh theo giá nhà nước, Bên A báo trước cho Bên B.</p>

    <h2>Điều 3. Thời hạn thuê</h2>
    <p>Từ ngày <strong><?= e(ngay($hd['ngay_bat_dau'])) ?></strong> đến ngày <strong><?= e(ngay($hd['ngay_ket_thuc'])) ?></strong>
        (khoảng <?= $so_thang ?> tháng). Hết hạn, hai bên có thể gia hạn hợp đồng.</p>

    <h2>Điều 4. Trách nhiệm của hai bên</h2>
    <p>– Bên A bàn giao phòng đúng hiện trạng, sửa chữa hư hỏng không do lỗi của Bên B.</p>
    <p>– Bên B thanh toán đúng hạn, giữ gìn tài sản, giữ trật tự và vệ sinh chung, khai báo tạm trú đầy đủ.</p>
    <p>– Bên nào muốn chấm dứt hợp đồng trước hạn phải báo cho bên kia trước ít nhất 30 ngày.</p>

    <p>Hợp đồng lập thành 02 bản, mỗi bên giữ 01 bản có giá trị như nhau.</p>

    <div class="ky">
        <div><strong>BÊN A</strong><br><em>(Ký, ghi rõ họ tên)</em><div class="ten"><?= e(CHU_NHA_TRO) ?></div></div>
        <div><strong>BÊN B</strong><br><em>(Ký, ghi rõ họ tên)</em><div class="ten"><?= e($hd['ho_ten']) ?></div></div>
    </div>
</div>
</body>
</html>
