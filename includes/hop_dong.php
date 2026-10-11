<?php
/**
 * =====================================================================
 *  NGHIỆP VỤ HỢP ĐỒNG VÀ NGƯỜI Ở  (HD001 – HD006, KT005)
 *  Các hàm dùng ở nhiều trang. Phần xử lý riêng của từng màn hình
 *  nằm ngay trong file trang đó (ví dụ hop_dong_tao.php).
 * =====================================================================
 */

/** HD001: mã hợp đồng tiếp theo, dạng HD-0020. */
function ma_hop_dong_moi()
{
    $lon_nhat = db()->query("SELECT MAX(CAST(SUBSTRING(ma_hop_dong, 4) AS UNSIGNED))
                             FROM hop_dong WHERE ma_hop_dong LIKE 'HD-%'")->fetchColumn();
    return 'HD-' . str_pad((string)((int)$lon_nhat + 1), 4, '0', STR_PAD_LEFT);
}

/** Một hợp đồng kèm thông tin phòng và người đứng tên, hoặc false nếu không có. */
function lay_hop_dong($id)
{
    $stmt = db()->prepare('SELECT h.*, p.ma_phong, p.so_nguoi_toi_da, p.gia_thue AS gia_niem_yet,
                                  p.trang_thai AS trang_thai_phong,
                                  k.ho_ten, k.so_dien_thoai, k.cccd, k.que_quan
                           FROM hop_dong h
                           JOIN phong p      ON p.id = h.phong_id
                           JOIN khach_thue k ON k.id = h.khach_thue_id
                           WHERE h.id = ?');
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

/** Hợp đồng còn hiệu lực của một phòng, hoặc false nếu phòng không có ai thuê. */
function hop_dong_hieu_luc_cua_phong($phong_id)
{
    $stmt = db()->prepare("SELECT id FROM hop_dong WHERE phong_id = ? AND trang_thai = 'hieu_luc' LIMIT 1");
    $stmt->execute([(int)$phong_id]);
    $id = $stmt->fetchColumn();
    return $id ? lay_hop_dong($id) : false;
}

/** Số người đang ở theo hợp đồng (những người chưa rời đi). */
function dem_nguoi_dang_o($hop_dong_id)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM nguoi_o WHERE hop_dong_id = ? AND ngay_roi IS NULL');
    $stmt->execute([(int)$hop_dong_id]);
    return (int)$stmt->fetchColumn();
}

/** KT005: danh sách người đang ở theo hợp đồng, người đại diện đứng đầu. */
function nguoi_dang_o($hop_dong_id)
{
    $stmt = db()->prepare('SELECT n.id AS nguoi_o_id, n.la_dai_dien, n.ngay_vao, k.*
                           FROM nguoi_o n JOIN khach_thue k ON k.id = n.khach_thue_id
                           WHERE n.hop_dong_id = ? AND n.ngay_roi IS NULL
                           ORDER BY n.la_dai_dien DESC, n.ngay_vao, k.ho_ten');
    $stmt->execute([(int)$hop_dong_id]);
    return $stmt->fetchAll();
}

/**
 * Khách này đang ở phòng nào (theo hợp đồng còn hiệu lực)?
 * Trả về mã phòng, hoặc null nếu khách chưa ở đâu. Một người không thể ở hai phòng cùng lúc.
 */
function phong_dang_o_cua_khach($khach_thue_id)
{
    $stmt = db()->prepare("SELECT p.ma_phong
                           FROM nguoi_o n
                           JOIN hop_dong h ON h.id = n.hop_dong_id
                           JOIN phong p    ON p.id = h.phong_id
                           WHERE n.khach_thue_id = ? AND n.ngay_roi IS NULL AND h.trang_thai = 'hieu_luc'
                           LIMIT 1");
    $stmt->execute([(int)$khach_thue_id]);
    $ma = $stmt->fetchColumn();
    return $ma === false ? null : $ma;
}

/** Khách thuê chưa ở phòng nào: dùng cho ô chọn khi tạo hợp đồng hoặc thêm người ở cùng. */
function khach_chua_o_dau()
{
    return db()->query("SELECT k.*
                        FROM khach_thue k
                        WHERE NOT EXISTS (
                            SELECT 1 FROM nguoi_o n JOIN hop_dong h ON h.id = n.hop_dong_id
                            WHERE n.khach_thue_id = k.id AND n.ngay_roi IS NULL AND h.trang_thai = 'hieu_luc')
                        ORDER BY k.ho_ten")->fetchAll();
}

/** Lần ghi chỉ số gần nhất của phòng, dùng gợi ý chỉ số lúc nhận phòng khi tạo hợp đồng. */
function chi_so_gan_nhat_cua_phong($phong_id)
{
    $stmt = db()->prepare('SELECT * FROM chi_so WHERE phong_id = ? ORDER BY thang DESC LIMIT 1');
    $stmt->execute([(int)$phong_id]);
    return $stmt->fetch();
}

/** HD006: số hợp đồng còn hiệu lực sẽ hết hạn trong SO_NGAY_SAP_HET_HAN ngày tới (kể cả đã quá hạn). */
function dem_hop_dong_sap_het_han()
{
    $stmt = db()->prepare("SELECT COUNT(*) FROM hop_dong
                           WHERE trang_thai = 'hieu_luc' AND ngay_ket_thuc <= ?");
    $stmt->execute([date('Y-m-d', strtotime('+' . SO_NGAY_SAP_HET_HAN . ' day'))]);
    return (int)$stmt->fetchColumn();
}
