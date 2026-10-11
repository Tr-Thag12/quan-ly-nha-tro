<?php
/**
 * =====================================================================
 *  NGHIỆP VỤ ĐƠN GIÁ, HÓA ĐƠN VÀ THU TIỀN  (DN004, TT001 – TT003)
 *
 *  CÔNG THỨC TÍNH HÓA ĐƠN THÁNG (TT001 – AC2):
 *    Tổng tiền = tiền phòng + tiền điện + tiền nước + tiền dịch vụ
 *      tiền phòng  = giá thuê CHỐT trong hợp đồng
 *      tiền điện   = (chỉ số điện mới - chỉ số điện cũ) x đơn giá điện
 *      tiền nước   = (chỉ số nước mới - chỉ số nước cũ) x đơn giá nước
 *      tiền dịch vụ = số người ở x giá rác + giá wifi + số xe x giá gửi xe
 *  Đơn giá dùng mức đang áp dụng vào NGÀY CUỐI của tháng tính tiền, và được
 *  chép vào hóa đơn lúc lập, nên đổi giá sau này không làm đổi hóa đơn cũ.
 * =====================================================================
 */

/** DN004: đơn giá của một dịch vụ áp dụng vào ngày $ngay (mức gần nhất không sau ngày đó). */
function don_gia_ap_dung($dich_vu, $ngay)
{
    $stmt = db()->prepare('SELECT don_gia FROM don_gia
                           WHERE dich_vu = ? AND ap_dung_tu <= ?
                           ORDER BY ap_dung_tu DESC LIMIT 1');
    $stmt->execute([$dich_vu, $ngay]);
    $gia = $stmt->fetchColumn();
    return $gia === false ? null : (int)$gia;
}

/**
 * Bảng đơn giá của cả 5 dịch vụ vào ngày $ngay.
 * Trả về [bảng giá, danh sách tên dịch vụ chưa có giá].
 */
function bang_don_gia($ngay)
{
    $bang = [];
    $thieu = [];
    foreach (DICH_VU as $ma => $dv) {
        $bang[$ma] = don_gia_ap_dung($ma, $ngay);
        if ($bang[$ma] === null) {
            $thieu[] = $dv['ten'];
        }
    }
    return [$bang, $thieu];
}

/**
 * Số người ở tính tiền rác của tháng: người có mặt ít nhất một ngày trong tháng
 * (vào ở trước ngày cuối tháng và chưa rời đi trước ngày đầu tháng).
 */
function so_nguoi_trong_thang($hop_dong_id, $thang)
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM nguoi_o
                           WHERE hop_dong_id = ? AND ngay_vao <= ?
                             AND (ngay_roi IS NULL OR ngay_roi >= ?)');
    $stmt->execute([(int)$hop_dong_id, ngay_cuoi_thang($thang), ngay_dau_thang($thang)]);
    return max(1, (int)$stmt->fetchColumn());
}

/**
 * TT001 – AC2: tính mọi khoản của hóa đơn tháng $thang.
 * $hop_dong: một dòng bảng hop_dong; $chi_so: một dòng bảng chi_so của tháng đó.
 * Trả về mảng có đủ các cột tiền của bảng hoa_don (chưa lưu vào CSDL).
 */
function tinh_hoa_don($hop_dong, $chi_so, $thang)
{
    list($gia, $thieu) = bang_don_gia(ngay_cuoi_thang($thang));
    if ($thieu) {
        throw new RuntimeException('Chưa có đơn giá ' . implode(', ', $thieu) . ' cho tháng ' . ten_thang($thang)
            . '. Chủ trọ cần vào mục Đơn giá để cài đặt trước.');
    }
    $so_nguoi = so_nguoi_trong_thang($hop_dong['id'], $thang);
    $so_xe    = (int)$hop_dong['so_xe'];
    $dien     = (int)$chi_so['dien_moi'] - (int)$chi_so['dien_cu'];
    $nuoc     = (int)$chi_so['nuoc_moi'] - (int)$chi_so['nuoc_cu'];

    $hd = [
        'tien_phong'     => (int)$hop_dong['gia_thue'],
        'dien_cu'        => (int)$chi_so['dien_cu'],
        'dien_moi'       => (int)$chi_so['dien_moi'],
        'don_gia_dien'   => $gia['dien'],
        'tien_dien'      => $dien * $gia['dien'],
        'nuoc_cu'        => (int)$chi_so['nuoc_cu'],
        'nuoc_moi'       => (int)$chi_so['nuoc_moi'],
        'don_gia_nuoc'   => $gia['nuoc'],
        'tien_nuoc'      => $nuoc * $gia['nuoc'],
        'so_nguoi'       => $so_nguoi,
        'don_gia_rac'    => $gia['rac'],
        'tien_rac'       => $so_nguoi * $gia['rac'],
        'don_gia_wifi'   => $gia['wifi'],
        'tien_wifi'      => $gia['wifi'],
        'so_xe'          => $so_xe,
        'don_gia_gui_xe' => $gia['gui_xe'],
        'tien_gui_xe'    => $so_xe * $gia['gui_xe'],
    ];
    $hd['tong_tien'] = $hd['tien_phong'] + $hd['tien_dien'] + $hd['tien_nuoc']
                     + $hd['tien_rac'] + $hd['tien_wifi'] + $hd['tien_gui_xe'];
    return $hd;
}

/** Hạn thu: ngày 10 của tháng sau; nếu hóa đơn lập muộn thì hạn ít nhất 5 ngày kể từ hôm nay. */
function han_thanh_toan($thang)
{
    $han = date('Y-m-d', strtotime(thang_cong($thang, 1) . '-01 +' . (NGAY_HAN_THANH_TOAN - 1) . ' day'));
    $it_nhat = date('Y-m-d', strtotime('+5 day'));
    return max($han, $it_nhat);
}

/** Trạng thái hóa đơn theo số tiền đã thu. */
function trang_thai_hoa_don($tong_tien, $da_thu)
{
    if ($da_thu <= 0) {
        return 'chua_thu';
    }
    return $da_thu >= $tong_tien ? 'da_thu' : 'thu_mot_phan';
}

/**
 * TT001: danh sách hợp đồng của tháng $thang kèm tình trạng lập hóa đơn:
 *   'co_the_tao'      – đã ghi chỉ số, chưa có hóa đơn: sinh được
 *   'da_co'           – đã có hóa đơn (TT001 – AC3: một phòng một tháng chỉ một hóa đơn)
 *   'chua_ghi_chi_so' – chưa ghi chỉ số tháng này nên chưa tính được tiền điện nước
 * Chỉ xét hợp đồng còn hiệu lực (TT001 – AC1).
 */
function du_kien_hoa_don($thang)
{
    $ket_qua = [];
    foreach (hop_dong_can_ghi_chi_so($thang) as $hd) {
        $dong = ['hop_dong' => $hd, 'chi_so' => chi_so_thang($hd['phong_id'], $thang),
                 'hoa_don' => false, 'du_kien' => null, 'tinh_trang' => 'chua_ghi_chi_so'];
        $stmt = db()->prepare('SELECT * FROM hoa_don WHERE phong_id = ? AND thang = ?');
        $stmt->execute([$hd['phong_id'], $thang]);
        $dong['hoa_don'] = $stmt->fetch();
        if ($dong['hoa_don']) {
            $dong['tinh_trang'] = 'da_co';
        } elseif ($dong['chi_so']) {
            $dong['tinh_trang'] = 'co_the_tao';
            $dong['du_kien'] = tinh_hoa_don($hd, $dong['chi_so'], $thang);
        }
        $ket_qua[] = $dong;
    }
    return $ket_qua;
}

/** TT001: lưu hóa đơn tháng $thang của một hợp đồng. Trả về id hóa đơn. */
function tao_hoa_don($hop_dong, $chi_so, $thang)
{
    $tien = tinh_hoa_don($hop_dong, $chi_so, $thang);
    $cot = array_merge([
        'ma_hoa_don'     => $hop_dong['ma_phong'] . '-' . str_replace('-', '', $thang),
        'hop_dong_id'    => (int)$hop_dong['id'],
        'phong_id'       => (int)$hop_dong['phong_id'],
        'thang'          => $thang,
        'chi_so_id'      => (int)$chi_so['id'],
    ], $tien, [
        'da_thu'         => 0,
        'trang_thai'     => 'chua_thu',          // TT001 – AC4: hóa đơn mới ở trạng thái Chưa thu
        'han_thanh_toan' => han_thanh_toan($thang),
        'nguoi_tao'      => id_nguoi_dung(),
    ]);
    $sql = 'INSERT INTO hoa_don (' . implode(', ', array_keys($cot)) . ')
            VALUES (' . implode(', ', array_fill(0, count($cot), '?')) . ')';
    db()->prepare($sql)->execute(array_values($cot));
    return (int)db()->lastInsertId();
}

/**
 * DN003 – AC2: sửa chỉ số thì hóa đơn chưa thu tự tính lại.
 * Giữ nguyên đơn giá đã chép trong hóa đơn (DN004 – AC2), chỉ tính lại theo số mới.
 */
function tinh_lai_hoa_don_theo_chi_so($phong_id, $thang)
{
    $stmt = db()->prepare('SELECT * FROM hoa_don WHERE phong_id = ? AND thang = ?');
    $stmt->execute([(int)$phong_id, $thang]);
    $hd = $stmt->fetch();
    if (!$hd) {
        return false;
    }
    $cs = chi_so_thang($phong_id, $thang);
    $tien_dien = ((int)$cs['dien_moi'] - (int)$cs['dien_cu']) * (int)$hd['don_gia_dien'];
    $tien_nuoc = ((int)$cs['nuoc_moi'] - (int)$cs['nuoc_cu']) * (int)$hd['don_gia_nuoc'];
    $tong = (int)$hd['tien_phong'] + $tien_dien + $tien_nuoc
          + (int)$hd['tien_rac'] + (int)$hd['tien_wifi'] + (int)$hd['tien_gui_xe'];
    db()->prepare('UPDATE hoa_don SET dien_cu = ?, dien_moi = ?, tien_dien = ?,
                                      nuoc_cu = ?, nuoc_moi = ?, tien_nuoc = ?,
                                      tong_tien = ?, trang_thai = ?
                   WHERE id = ?')
        ->execute([$cs['dien_cu'], $cs['dien_moi'], $tien_dien, $cs['nuoc_cu'], $cs['nuoc_moi'], $tien_nuoc,
                   $tong, trang_thai_hoa_don($tong, (int)$hd['da_thu']), $hd['id']]);
    return ['cu' => (int)$hd['tong_tien'], 'moi' => $tong, 'ma' => $hd['ma_hoa_don']];
}

/**
 * TT003: ghi một lần thu tiền (thu đủ hoặc thu một phần) và cập nhật hóa đơn.
 * Người gọi cần bọc trong transaction nếu làm nhiều việc cùng lúc.
 */
function ghi_thanh_toan($hoa_don, $so_tien, $ngay_thu, $hinh_thuc, $ghi_chu)
{
    db()->prepare('INSERT INTO thanh_toan (hoa_don_id, so_tien, ngay_thu, hinh_thuc, ghi_chu, nguoi_thu)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$hoa_don['id'], (int)$so_tien, $ngay_thu, $hinh_thuc, $ghi_chu !== '' ? $ghi_chu : null,
                   id_nguoi_dung()]);
    $da_thu = (int)$hoa_don['da_thu'] + (int)$so_tien;
    db()->prepare('UPDATE hoa_don SET da_thu = ?, trang_thai = ? WHERE id = ?')
        ->execute([$da_thu, trang_thai_hoa_don((int)$hoa_don['tong_tien'], $da_thu), $hoa_don['id']]);
    return $da_thu;
}

/** Tổng tiền khách còn nợ của một hợp đồng (mọi hóa đơn chưa thu đủ). */
function tong_no_hop_dong($hop_dong_id)
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(tong_tien - da_thu), 0) FROM hoa_don
                           WHERE hop_dong_id = ? AND da_thu < tong_tien');
    $stmt->execute([(int)$hop_dong_id]);
    return (int)$stmt->fetchColumn();
}

/**
 * BC001: doanh thu từng tháng trong khoảng từ $tu đến $den (tính theo tháng của hóa đơn).
 * Tách riêng tiền phòng, tiền điện nước, tiền dịch vụ. Tháng không có hóa đơn vẫn có dòng, số liệu bằng 0.
 * Dùng chung cho màn hình báo cáo (bao_cao.php) và file Excel xuất ra (bao_cao_xuat.php).
 */
function doanh_thu_theo_thang($tu, $den)
{
    $stmt = db()->prepare('SELECT thang, COUNT(*) AS so_hoa_don,
                                  SUM(tien_phong) AS tien_phong,
                                  SUM(tien_dien + tien_nuoc) AS dien_nuoc,
                                  SUM(tien_rac + tien_wifi + tien_gui_xe) AS dich_vu,
                                  SUM(tong_tien) AS tong, SUM(da_thu) AS da_thu
                           FROM hoa_don WHERE thang BETWEEN ? AND ?
                           GROUP BY thang');
    $stmt->execute([$tu, $den]);
    $theo_thang = [];
    foreach ($stmt->fetchAll() as $d) {
        $theo_thang[$d['thang']] = $d;
    }
    $ket_qua = [];
    for ($t = $tu; $t <= $den; $t = thang_cong($t, 1)) {
        $d = isset($theo_thang[$t]) ? $theo_thang[$t] : [];
        $so = function ($cot) use ($d) { return isset($d[$cot]) ? (int)$d[$cot] : 0; };
        $ket_qua[] = [
            'thang'      => $t,
            'so_hoa_don' => $so('so_hoa_don'),
            'tien_phong' => $so('tien_phong'),
            'dien_nuoc'  => $so('dien_nuoc'),
            'dich_vu'    => $so('dich_vu'),
            'tong'       => $so('tong'),
            'da_thu'     => $so('da_thu'),
            'con_no'     => $so('tong') - $so('da_thu'),
        ];
    }
    return $ket_qua;
}

/** BC001: đọc khoảng tháng từ địa chỉ trang, mặc định 12 tháng gần nhất có hóa đơn. */
function khoang_thang_bao_cao()
{
    $moi_nhat = db()->query('SELECT MAX(thang) FROM hoa_don')->fetchColumn() ?: thang_hien_tai();
    $den = gia_tri_get('den', $moi_nhat);
    $tu  = gia_tri_get('tu', '');
    if (!thang_hop_le($den)) {
        $den = $moi_nhat;
    }
    if (!thang_hop_le($tu)) {
        $tu = thang_cong($den, -11);
    }
    if ($tu > $den) {
        list($tu, $den) = [$den, $tu];
    }
    if ($tu < thang_cong($den, -35)) {
        $tu = thang_cong($den, -35);   // xem tối đa 36 tháng một lần
    }
    return [$tu, $den];
}
