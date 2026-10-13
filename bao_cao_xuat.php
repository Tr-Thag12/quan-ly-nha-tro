<?php
/**
 * =====================================================================
 *  BC005 – XUẤT BÁO CÁO RA EXCEL (chỉ Chủ trọ)
 *    AC1: File tải về mở được bằng Excel   -> file .xlsx thật, xem includes/xlsx.php
 *    AC2: Giữ đúng các cột như trên màn hình báo cáo
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/xlsx.php';
require_role('chu_tro');

list($tu, $den) = khoang_thang_bao_cao();
$ds = doanh_thu_theo_thang($tu, $den);

// AC2: đúng các cột của bảng trên màn hình bao_cao.php
$cot = [
    ['ten' => 'Tháng',          'rong' => 11, 'so' => false],
    ['ten' => 'Số hóa đơn',     'rong' => 12, 'so' => true],
    ['ten' => 'Tiền phòng',     'rong' => 16, 'so' => true],
    ['ten' => 'Tiền điện nước', 'rong' => 17, 'so' => true],
    ['ten' => 'Tiền dịch vụ',   'rong' => 15, 'so' => true],
    ['ten' => 'Tổng phát sinh', 'rong' => 17, 'so' => true],
    ['ten' => 'Đã thu',         'rong' => 16, 'so' => true],
    ['ten' => 'Còn nợ',         'rong' => 15, 'so' => true],
];
$cac_dong = [];
$tong = [0, 0, 0, 0, 0, 0, 0];
foreach (array_reverse($ds) as $d) {
    $so = [$d['so_hoa_don'], $d['tien_phong'], $d['dien_nuoc'], $d['dich_vu'], $d['tong'], $d['da_thu'], $d['con_no']];
    foreach ($so as $i => $gia_tri) {
        $tong[$i] += $gia_tri;
    }
    $cac_dong[] = array_merge([ten_thang($d['thang'])], $so);
}
$tieu_de = 'Báo cáo doanh thu ' . TEN_NHA_TRO . ' từ tháng ' . ten_thang($tu) . ' đến tháng ' . ten_thang($den)
         . ' (xuất ngày ' . ngay(hom_nay()) . ')';

$noi_dung = tao_xlsx('Doanh thu', $tieu_de, $cot, $cac_dong, array_merge(['Tổng cộng'], $tong));
$ten_file = 'bao-cao-doanh-thu-' . $tu . '-den-' . $den . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $ten_file . '"');
header('Content-Length: ' . strlen($noi_dung));
header('Cache-Control: no-store');
echo $noi_dung;
