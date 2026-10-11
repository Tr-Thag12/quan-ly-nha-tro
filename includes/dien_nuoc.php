<?php
/**
 * =====================================================================
 *  NGHIỆP VỤ GHI CHỈ SỐ ĐIỆN NƯỚC  (DN001 – DN003, DN005)
 * =====================================================================
 */

/**
 * DN001 – AC1: chỉ số cũ của phòng khi ghi cho tháng $thang.
 * - Nếu trong thời gian hợp đồng hiện tại đã có lần ghi trước tháng này:
 *   lấy chỉ số mới của lần ghi gần nhất (tháng trước).
 * - Nếu chưa có lần ghi nào: lấy chỉ số lúc nhận phòng ghi trong hợp đồng.
 * Trả về ['dien' => ..., 'nuoc' => ..., 'nguon' => 'Tháng 08/2026' | 'Lúc nhận phòng'].
 */
function chi_so_cu($hop_dong, $thang)
{
    $stmt = db()->prepare('SELECT thang, dien_moi, nuoc_moi FROM chi_so
                           WHERE phong_id = ? AND thang < ? AND thang >= ?
                           ORDER BY thang DESC LIMIT 1');
    $stmt->execute([$hop_dong['phong_id'], $thang, substr($hop_dong['ngay_bat_dau'], 0, 7)]);
    $truoc = $stmt->fetch();
    if ($truoc) {
        return ['dien' => (int)$truoc['dien_moi'], 'nuoc' => (int)$truoc['nuoc_moi'],
                'nguon' => 'Tháng ' . ten_thang($truoc['thang'])];
    }
    return ['dien' => (int)$hop_dong['chi_so_dien_dau'], 'nuoc' => (int)$hop_dong['chi_so_nuoc_dau'],
            'nguon' => 'Lúc nhận phòng'];
}

/** Lần ghi chỉ số của phòng trong một tháng, hoặc false nếu chưa ghi. */
function chi_so_thang($phong_id, $thang)
{
    $stmt = db()->prepare('SELECT * FROM chi_so WHERE phong_id = ? AND thang = ?');
    $stmt->execute([(int)$phong_id, $thang]);
    return $stmt->fetch();
}

/** Tháng mới nhất đã ghi chỉ số của phòng, hoặc null. */
function thang_ghi_moi_nhat($phong_id)
{
    $stmt = db()->prepare('SELECT MAX(thang) FROM chi_so WHERE phong_id = ?');
    $stmt->execute([(int)$phong_id]);
    $thang = $stmt->fetchColumn();
    return $thang ?: null;
}

/**
 * Các hợp đồng còn hiệu lực cần ghi chỉ số cho tháng $thang
 * (đã nhận phòng trước ngày cuối của tháng đó), sắp theo tầng và mã phòng.
 */
function hop_dong_can_ghi_chi_so($thang)
{
    $stmt = db()->prepare("SELECT h.*, p.ma_phong, k.ho_ten
                           FROM hop_dong h
                           JOIN phong p      ON p.id = h.phong_id
                           JOIN khach_thue k ON k.id = h.khach_thue_id
                           WHERE h.trang_thai = 'hieu_luc' AND h.ngay_bat_dau <= ?
                           ORDER BY p.tang, p.ma_phong");
    $stmt->execute([ngay_cuoi_thang($thang)]);
    return $stmt->fetchAll();
}

/**
 * DN001 – AC2, DN005 – AC3: kiểm tra chỉ số mới người dùng nhập.
 * $dien_moi, $nuoc_moi là chuỗi người dùng gõ; $cu là kết quả của chi_so_cu().
 * Trả về mảng lỗi theo tên ô ('dien_moi', 'nuoc_moi'); mảng rỗng là hợp lệ.
 */
function kiem_tra_chi_so($dien_moi, $nuoc_moi, $cu)
{
    $loi = [];
    if ($dien_moi === '' || !ctype_digit($dien_moi)) {
        $loi['dien_moi'] = 'Nhập chỉ số điện là số nguyên.';
    } elseif ((int)$dien_moi < $cu['dien']) {
        $loi['dien_moi'] = 'Chỉ số điện mới (' . (int)$dien_moi . ') nhỏ hơn chỉ số cũ (' . $cu['dien'] . ').';
    } elseif ((int)$dien_moi - $cu['dien'] > 3000) {
        // Chặn gõ thừa chữ số: một phòng trọ không dùng quá 3.000 kWh một tháng
        $loi['dien_moi'] = 'Điện tiêu thụ hơn 3.000 kWh, hãy kiểm tra lại số vừa nhập.';
    }
    if ($nuoc_moi === '' || !ctype_digit($nuoc_moi)) {
        $loi['nuoc_moi'] = 'Nhập chỉ số nước là số nguyên.';
    } elseif ((int)$nuoc_moi < $cu['nuoc']) {
        $loi['nuoc_moi'] = 'Chỉ số nước mới (' . (int)$nuoc_moi . ') nhỏ hơn chỉ số cũ (' . $cu['nuoc'] . ').';
    } elseif ((int)$nuoc_moi - $cu['nuoc'] > 300) {
        $loi['nuoc_moi'] = 'Nước tiêu thụ hơn 300 m³, hãy kiểm tra lại số vừa nhập.';
    }
    return $loi;
}

/** DN001: lưu một lần ghi chỉ số mới. Trả về id vừa tạo. */
function them_chi_so($phong_id, $thang, $cu, $dien_moi, $nuoc_moi)
{
    db()->prepare('INSERT INTO chi_so (phong_id, thang, dien_cu, dien_moi, nuoc_cu, nuoc_moi, nguoi_ghi)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([(int)$phong_id, $thang, $cu['dien'], (int)$dien_moi, $cu['nuoc'], (int)$nuoc_moi, id_nguoi_dung()]);
    return (int)db()->lastInsertId();
}

/**
 * DN003 – AC1: lần ghi chỉ số này có sửa được không?
 * - Chỉ sửa lần ghi MỚI NHẤT của phòng (tháng sau lấy số của tháng này làm chỉ số cũ).
 * - Hóa đơn của tháng đó đã thu tiền thì khóa, không cho sửa.
 * Trả về null nếu sửa được, hoặc câu giải thích vì sao không.
 */
function ly_do_khong_sua_chi_so($chi_so)
{
    if (thang_ghi_moi_nhat($chi_so['phong_id']) !== $chi_so['thang']) {
        return 'Chỉ sửa được lần ghi mới nhất của phòng, vì tháng sau đã lấy số này làm chỉ số cũ.';
    }
    $stmt = db()->prepare('SELECT da_thu FROM hoa_don WHERE phong_id = ? AND thang = ?');
    $stmt->execute([$chi_so['phong_id'], $chi_so['thang']]);
    $da_thu = $stmt->fetchColumn();
    if ($da_thu !== false && (int)$da_thu > 0) {
        return 'Hóa đơn tháng ' . ten_thang($chi_so['thang']) . ' đã thu tiền nên chỉ số bị khóa.';
    }
    return null;
}
