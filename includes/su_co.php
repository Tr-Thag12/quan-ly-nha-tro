<?php
/**
 * =====================================================================
 *  NGHIỆP VỤ SỰ CỐ VÀ BẢO TRÌ  (SC001 – SC004)
 *    SC001: khách thuê báo sự cố, nhận mã theo dõi
 *    SC002: quản lý xem danh sách sự cố
 *    SC003: đổi trạng thái, mỗi lần đổi ghi lại thời điểm vào bảng su_co_lich_su
 *    SC004: chủ trọ ghi chi phí sửa chữa, cộng tổng theo tháng
 * =====================================================================
 */

/** SC001 – AC2: tạo mã theo dõi chưa trùng, ví dụ SC-7KQ2M9. */
function ma_theo_doi_moi()
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM su_co WHERE ma_theo_doi = ?');
    do {
        $ma = 'SC-' . ma_ngau_nhien(6);
        $stmt->execute([$ma]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $ma;
}

/**
 * Chuẩn hóa mã khách gõ vào ô tra cứu: " sc-7kq2m9 " -> "SC-7KQ2M9".
 * Khách gõ thiếu chữ "SC-" (chỉ gõ 7KQ2M9) vẫn tìm được.
 */
function chuan_hoa_ma_theo_doi($ma)
{
    $ma = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$ma));
    if (strlen($ma) === 8 && strpos($ma, 'SC') === 0) {
        $ma = substr($ma, 2);
    }
    return 'SC-' . $ma;
}

/** Câu truy vấn chung: sự cố kèm mã phòng và người báo. */
function cau_lay_su_co($dieu_kien)
{
    return 'SELECT s.*, p.ma_phong, k.ho_ten AS nguoi_bao, k.so_dien_thoai AS sdt_nguoi_bao
            FROM su_co s
            JOIN phong p ON p.id = s.phong_id
            LEFT JOIN khach_thue k ON k.id = s.khach_thue_id
            WHERE ' . $dieu_kien;
}

function lay_su_co($id)
{
    $stmt = db()->prepare(cau_lay_su_co('s.id = ?'));
    $stmt->execute([(int)$id]);
    return $stmt->fetch();
}

function lay_su_co_theo_ma($ma_theo_doi)
{
    $stmt = db()->prepare(cau_lay_su_co('s.ma_theo_doi = ?'));
    $stmt->execute([$ma_theo_doi]);
    return $stmt->fetch();
}

/** SC003 – AC1: các lần đổi trạng thái, cũ trước mới sau. */
function lich_su_su_co($su_co_id)
{
    $stmt = db()->prepare('SELECT l.*, t.ho_ten AS nguoi, t.vai_tro
                           FROM su_co_lich_su l
                           LEFT JOIN tai_khoan t ON t.id = l.nguoi_cap_nhat
                           WHERE l.su_co_id = ?
                           ORDER BY l.thoi_diem, l.id');
    $stmt->execute([(int)$su_co_id]);
    return $stmt->fetchAll();
}

/**
 * SC001: tìm người đang ở phòng có số điện thoại này, để chắc chắn người báo
 * là khách của phòng đó (người ngoài không gửi bừa được).
 */
function khach_dang_o_phong_theo_sdt($phong_id, $so_dien_thoai)
{
    $stmt = db()->prepare("SELECT k.id, k.ho_ten
                           FROM nguoi_o n
                           JOIN hop_dong h   ON h.id = n.hop_dong_id
                           JOIN khach_thue k ON k.id = n.khach_thue_id
                           WHERE h.phong_id = ? AND h.trang_thai = 'hieu_luc'
                             AND n.ngay_roi IS NULL AND k.so_dien_thoai = ?
                           LIMIT 1");
    $stmt->execute([(int)$phong_id, $so_dien_thoai]);
    return $stmt->fetch();
}

/**
 * SC001: lưu sự cố mới ở trạng thái Chờ xử lý và dòng lịch sử đầu tiên.
 * Trả về mã theo dõi.
 */
function them_su_co($phong_id, $khach_thue_id, $loai, $mo_ta, $anh, $ghi_chu)
{
    $ma = ma_theo_doi_moi();
    db()->beginTransaction();
    try {
        db()->prepare("INSERT INTO su_co (ma_theo_doi, phong_id, khach_thue_id, loai, mo_ta, anh, trang_thai, ngay_bao)
                       VALUES (?, ?, ?, ?, ?, ?, 'cho_xu_ly', NOW())")
            ->execute([$ma, (int)$phong_id, $khach_thue_id, $loai, $mo_ta, $anh]);
        $id = (int)db()->lastInsertId();
        db()->prepare("INSERT INTO su_co_lich_su (su_co_id, trang_thai, ghi_chu, nguoi_cap_nhat, thoi_diem)
                       VALUES (?, 'cho_xu_ly', ?, ?, NOW())")
            ->execute([$id, $ghi_chu, id_nguoi_dung()]);
        db()->commit();
    } catch (Exception $loi) {
        db()->rollBack();
        throw $loi;
    }
    return $ma;
}

/**
 * SC003: đổi trạng thái (hoặc ghi thêm tiến độ nếu giữ nguyên trạng thái).
 *   AC1: mỗi lần cập nhật thêm một dòng lịch sử kèm thời điểm
 *   AC2: chuyển sang Đã xong thì ghi chú xử lý bắt buộc (trang gọi đã kiểm tra)
 */
function cap_nhat_su_co($su_co, $trang_thai, $ghi_chu)
{
    db()->beginTransaction();
    try {
        db()->prepare('UPDATE su_co SET trang_thai = ?, ghi_chu_xu_ly = ?, ngay_cap_nhat = NOW() WHERE id = ?')
            ->execute([$trang_thai, $trang_thai === 'da_xong' ? $ghi_chu : null, $su_co['id']]);
        db()->prepare('INSERT INTO su_co_lich_su (su_co_id, trang_thai, ghi_chu, nguoi_cap_nhat, thoi_diem)
                       VALUES (?, ?, ?, ?, NOW())')
            ->execute([$su_co['id'], $trang_thai, $ghi_chu !== '' ? $ghi_chu : null, id_nguoi_dung()]);
        db()->commit();
    } catch (Exception $loi) {
        db()->rollBack();
        throw $loi;
    }
}

/** Đếm sự cố theo trạng thái: ['cho_xu_ly' => 2, 'dang_xu_ly' => 2, 'da_xong' => 3]. */
function dem_su_co_theo_trang_thai()
{
    $dem = array_fill_keys(array_keys(TRANG_THAI_SU_CO), 0);
    foreach (db()->query('SELECT trang_thai, COUNT(*) AS so FROM su_co GROUP BY trang_thai') as $d) {
        $dem[$d['trang_thai']] = (int)$d['so'];
    }
    return $dem;
}

/**
 * SC004 – AC2: tổng chi phí sửa chữa của từng tháng (theo ngày sửa),
 * $so_thang tháng gần nhất, mới nhất đứng đầu. Tháng không sửa gì vẫn có dòng, bằng 0.
 */
function chi_phi_sua_chua_theo_thang($so_thang)
{
    $cac_thang = cac_thang_gan_nhat($so_thang);
    $stmt = db()->prepare("SELECT DATE_FORMAT(ngay_sua, '%Y-%m') AS thang, COUNT(*) AS so_lan, SUM(chi_phi) AS tong
                           FROM su_co
                           WHERE chi_phi IS NOT NULL AND ngay_sua >= ?
                           GROUP BY DATE_FORMAT(ngay_sua, '%Y-%m')");
    $stmt->execute([ngay_dau_thang(end($cac_thang))]);
    $theo_thang = [];
    foreach ($stmt->fetchAll() as $d) {
        $theo_thang[$d['thang']] = $d;
    }
    $ket_qua = [];
    foreach ($cac_thang as $t) {
        $ket_qua[] = [
            'thang'  => $t,
            'so_lan' => isset($theo_thang[$t]) ? (int)$theo_thang[$t]['so_lan'] : 0,
            'tong'   => isset($theo_thang[$t]) ? (int)$theo_thang[$t]['tong'] : 0,
        ];
    }
    return $ket_qua;
}
