<?php
/**
 * =====================================================================
 *  PH003 – XÓA PHÒNG (chỉ Chủ trọ)
 *    AC1: Phòng đang có hợp đồng hiệu lực thì không cho xóa
 *    AC2: Có hộp thoại xác nhận trước khi xóa
 *         -> Nút Xóa nằm trong form có thuộc tính data-confirm,
 *            file assets/app.js hiện hộp thoại trước khi gửi form.
 *
 *  Bổ sung phát hiện khi kiểm thử: phòng Trống nhưng đã có lịch sử
 *  (hợp đồng cũ hoặc sự cố) cũng không cho xóa, để không mất lịch sử.
 *  Nhóm đề xuất thêm dòng này vào Acceptance Criteria của PH003.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');

// Chỉ nhận yêu cầu gửi từ form (POST), không xóa qua đường link
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('phong.php');
}
check_csrf();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Xóa từ trang chi tiết mà bị chặn thì quay lại trang chi tiết
$quay_ve = (isset($_POST['tu']) && $_POST['tu'] === 'chi_tiet') ? 'phong_chi_tiet.php?id=' . $id : 'phong.php';

$stmt = db()->prepare('SELECT * FROM phong WHERE id = ?');
$stmt->execute([$id]);
$phong = $stmt->fetch();
if (!$phong) {
    flash('error', 'Không tìm thấy phòng cần xóa.');
    redirect('phong.php');
}

// AC1: đang có hợp đồng còn hiệu lực -> chặn
$stmt = db()->prepare("SELECT h.ma_hop_dong, k.ho_ten
                       FROM hop_dong h JOIN khach_thue k ON k.id = h.khach_thue_id
                       WHERE h.phong_id = ? AND h.trang_thai = 'hieu_luc'");
$stmt->execute([$id]);
$hop_dong = $stmt->fetch();
if ($hop_dong) {
    flash('error', 'Không thể xóa phòng ' . $phong['ma_phong'] . ' vì đang có hợp đồng còn hiệu lực ('
        . $hop_dong['ma_hop_dong'] . ' – ' . $hop_dong['ho_ten'] . ').');
    redirect($quay_ve);
}

// Bổ sung: đã có lịch sử hợp đồng hoặc sự cố -> chặn để giữ lịch sử
$stmt = db()->prepare('SELECT (SELECT COUNT(*) FROM hop_dong WHERE phong_id = ?) AS so_hop_dong,
                              (SELECT COUNT(*) FROM su_co    WHERE phong_id = ?) AS so_su_co');
$stmt->execute([$id, $id]);
$lich_su = $stmt->fetch();
if ($lich_su['so_hop_dong'] > 0 || $lich_su['so_su_co'] > 0) {
    flash('error', 'Không thể xóa phòng ' . $phong['ma_phong'] . ' vì phòng đã có lịch sử ('
        . $lich_su['so_hop_dong'] . ' hợp đồng cũ, ' . $lich_su['so_su_co'] . ' sự cố). '
        . 'Nếu tạm ngừng cho thuê, hãy sửa phòng sang trạng thái Đang sửa.');
    redirect($quay_ve);
}

db()->prepare('DELETE FROM phong WHERE id = ?')->execute([$id]);
flash('success', 'Đã xóa phòng ' . $phong['ma_phong'] . '.');
redirect('phong.php');
