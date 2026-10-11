<?php
/**
 * =====================================================================
 *  KT005 – NGƯỜI Ở CÙNG RỜI PHÒNG
 *  Ghi ngày rời đi (không xóa, để giữ lịch sử và tính đúng tiền rác
 *  của tháng người đó còn ở). Người đứng tên hợp đồng không rời riêng
 *  được: muốn người đó đi thì phải thanh lý hợp đồng.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('hop_dong.php');
}
check_csrf();

$stmt = db()->prepare('SELECT n.*, k.ho_ten, h.trang_thai AS trang_thai_hd, p.ma_phong
                       FROM nguoi_o n
                       JOIN khach_thue k ON k.id = n.khach_thue_id
                       JOIN hop_dong h   ON h.id = n.hop_dong_id
                       JOIN phong p      ON p.id = h.phong_id
                       WHERE n.id = ?');
$stmt->execute([(int)gia_tri_post('nguoi_o_id', '0')]);
$ng = $stmt->fetch();

if (!$ng) {
    flash('error', 'Không tìm thấy người ở.');
    redirect('hop_dong.php');
}
if ((int)$ng['la_dai_dien'] === 1) {
    flash('error', $ng['ho_ten'] . ' là người đứng tên hợp đồng. Muốn người này rời đi, hãy thanh lý hợp đồng.');
} elseif ($ng['trang_thai_hd'] !== 'hieu_luc' || $ng['ngay_roi'] !== null) {
    flash('error', $ng['ho_ten'] . ' đã rời phòng trước đó.');
} else {
    $ngay_roi = max(hom_nay(), $ng['ngay_vao']);
    db()->prepare('UPDATE nguoi_o SET ngay_roi = ? WHERE id = ?')->execute([$ngay_roi, $ng['id']]);
    flash('success', 'Đã ghi nhận ' . $ng['ho_ten'] . ' rời phòng ' . $ng['ma_phong'] . ' ngày ' . ngay($ngay_roi) . '.');
}
redirect('hop_dong_chi_tiet.php?id=' . (int)$ng['hop_dong_id']);
