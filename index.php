<?php
/**
 * =====================================================================
 *  TRANG CHỦ – màn hình đầu tiên sau khi đăng nhập (TK001 – AC1)
 *    - Số phòng theo trạng thái (PH004, PH005), phòng trống để trả lời khách hỏi thuê
 *    - HD006 – AC1: số hợp đồng sắp hết hạn; bấm vào ra danh sách kèm số ngày còn lại (AC2)
 *    - Việc cần làm: ghi điện nước, lập hóa đơn, nhắc nợ quá hạn (TT007), sự cố chờ xử lý
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();

// Đếm số phòng theo từng trạng thái, ví dụ: ['trong' => 7, 'dang_thue' => 15, 'dang_sua' => 2]
$so_phong = db()->query('SELECT trang_thai, COUNT(*) FROM phong GROUP BY trang_thai')
                ->fetchAll(PDO::FETCH_KEY_PAIR);
$tong_phong = array_sum($so_phong);

// Danh sách phòng trống để trả lời nhanh khi có khách hỏi thuê
$phong_trong = db()->query("SELECT id, ma_phong, gia_thue, dien_tich FROM phong
                            WHERE trang_thai = 'trong' ORDER BY gia_thue, ma_phong")->fetchAll();

// Người đang ở (mọi người trong các hợp đồng còn hiệu lực)
$so_nguoi_o = (int)db()->query("SELECT COUNT(*) FROM nguoi_o n JOIN hop_dong h ON h.id = n.hop_dong_id
                                 WHERE h.trang_thai = 'hieu_luc' AND n.ngay_roi IS NULL")->fetchColumn();

// HD006 – AC1: hợp đồng hết hạn trong 30 ngày tới
$so_sap_het_han = dem_hop_dong_sap_het_han();

// Hóa đơn còn nợ (chưa thu hoặc thu một phần)
$con_no = db()->query('SELECT COUNT(*) AS so, COALESCE(SUM(tong_tien - da_thu), 0) AS tien
                       FROM hoa_don WHERE da_thu < tong_tien')->fetch();

// TT007: phòng nợ quá hạn hơn 5 ngày
$stmt = db()->prepare('SELECT COUNT(DISTINCT hop_dong_id) AS so_phong, COALESCE(SUM(tong_tien - da_thu), 0) AS tien
                       FROM hoa_don WHERE da_thu < tong_tien AND han_thanh_toan < ?');
$stmt->execute([date('Y-m-d', strtotime('-' . SO_NGAY_NHAC_NO . ' day'))]);
$no_qua_han = $stmt->fetch();

// Sự cố
$dem_su_co = dem_su_co_theo_trang_thai();

/**
 * Điện nước và hóa đơn: tháng cần làm là tháng trước nếu còn phòng chưa ghi
 * (đầu tháng thường đi ghi số của tháng vừa qua), nếu không thì tháng này.
 */
function tinh_hinh_thang($thang)
{
    $stmt = db()->prepare("SELECT COUNT(*) AS can_ghi,
                                  SUM(c.id IS NOT NULL) AS da_ghi,
                                  SUM(c.id IS NOT NULL AND hd.id IS NULL) AS cho_lap_hoa_don
                           FROM hop_dong h
                           LEFT JOIN chi_so c  ON c.phong_id = h.phong_id AND c.thang = ?
                           LEFT JOIN hoa_don hd ON hd.phong_id = h.phong_id AND hd.thang = ?
                           WHERE h.trang_thai = 'hieu_luc' AND h.ngay_bat_dau <= ?");
    $stmt->execute([$thang, $thang, ngay_cuoi_thang($thang)]);
    $d = $stmt->fetch();
    return ['thang' => $thang, 'can_ghi' => (int)$d['can_ghi'], 'da_ghi' => (int)$d['da_ghi'],
            'cho_lap_hoa_don' => (int)$d['cho_lap_hoa_don']];
}
$thang_truoc = tinh_hinh_thang(thang_cong(thang_hien_tai(), -1));
$dien_nuoc = ($thang_truoc['da_ghi'] < $thang_truoc['can_ghi'] || $thang_truoc['cho_lap_hoa_don'] > 0)
    ? $thang_truoc
    : tinh_hinh_thang(thang_hien_tai());
$chua_ghi = $dien_nuoc['can_ghi'] - $dien_nuoc['da_ghi'];

$page_title = 'Trang chủ';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Xin chào, <?= e(current_user()['ho_ten']) ?></h1>
        <p class="muted">
            Bạn đang đăng nhập với vai trò <strong><?= e(VAI_TRO[current_user()['vai_tro']]) ?></strong>
            &middot; Hôm nay <?= date('d/m/Y') ?>
        </p>
    </div>
</div>

<div class="stats">
    <a class="stat-card" href="phong.php">
        <span class="stat-label">Tổng số phòng</span>
        <span class="stat-value"><?= (int)$tong_phong ?></span>
    </a>
    <?php foreach (TRANG_THAI_PHONG as $ma => $ten): ?>
        <a class="stat-card stat-<?= e($ma) ?>" href="phong.php?trang_thai=<?= e($ma) ?>">
            <span class="stat-label"><?= e($ten) ?></span>
            <span class="stat-value"><?= (int)(isset($so_phong[$ma]) ? $so_phong[$ma] : 0) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="stats">
    <a class="stat-card" href="khach_thue.php">
        <span class="stat-label">Người đang ở</span>
        <span class="stat-value"><?= $so_nguoi_o ?></span>
        <span class="stat-sub">Trong <?= (int)(isset($so_phong['dang_thue']) ? $so_phong['dang_thue'] : 0) ?> phòng đang thuê</span>
    </a>
    <!-- HD006 – AC1: số hợp đồng sắp hết hạn, bấm vào xem danh sách (AC2) -->
    <a class="stat-card stat-warn" id="hd-sap-het-han" href="hop_dong.php?loc=sap_het_han">
        <span class="stat-label">Hợp đồng sắp hết hạn</span>
        <span class="stat-value"><?= $so_sap_het_han ?></span>
        <span class="stat-sub">Hết hạn trong <?= SO_NGAY_SAP_HET_HAN ?> ngày tới &rarr;</span>
    </a>
    <a class="stat-card stat-danger" href="hoa_don.php?thang=tat_ca&amp;trang_thai=con_no">
        <span class="stat-label">Hóa đơn còn nợ</span>
        <span class="stat-value"><?= (int)$con_no['so'] ?></span>
        <span class="stat-sub">Tổng <?= e(tien($con_no['tien'])) ?></span>
    </a>
    <a class="stat-card" href="su_co.php">
        <span class="stat-label">Sự cố cần xử lý</span>
        <span class="stat-value"><?= $dem_su_co['cho_xu_ly'] + $dem_su_co['dang_xu_ly'] ?></span>
        <span class="stat-sub"><?= $dem_su_co['cho_xu_ly'] ?> chờ xử lý, <?= $dem_su_co['dang_xu_ly'] ?> đang sửa</span>
    </a>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-head"><h2>Việc cần làm</h2><span class="muted">Cập nhật theo dữ liệu hiện tại</span></div>
        <div class="card-body" style="padding-top:4px;padding-bottom:4px">
            <ul class="todo">
                <!-- DN001 / DN005 -->
                <li>
                    <span class="dot-lg <?= $chua_ghi > 0 ? 'warn' : 'ok' ?>"></span>
                    <a class="grow" href="chi_so.php?thang=<?= e($dien_nuoc['thang']) ?>">
                        Ghi chỉ số điện nước tháng <?= e(ten_thang($dien_nuoc['thang'])) ?>
                        <span class="sub"><?= $chua_ghi > 0
                            ? 'Còn ' . $chua_ghi . '/' . $dien_nuoc['can_ghi'] . ' phòng chưa ghi'
                            : 'Đã ghi đủ ' . $dien_nuoc['can_ghi'] . ' phòng' ?></span>
                    </a>
                    <span class="count"><?= $chua_ghi ?></span>
                </li>
                <!-- TT001 -->
                <li>
                    <span class="dot-lg <?= $dien_nuoc['cho_lap_hoa_don'] > 0 ? 'warn' : 'ok' ?>"></span>
                    <a class="grow" href="hoa_don_tao.php?thang=<?= e($dien_nuoc['thang']) ?>">
                        Lập hóa đơn tháng <?= e(ten_thang($dien_nuoc['thang'])) ?>
                        <span class="sub"><?= $dien_nuoc['cho_lap_hoa_don'] > 0
                            ? $dien_nuoc['cho_lap_hoa_don'] . ' phòng đã ghi chỉ số, chưa có hóa đơn'
                            : ($dien_nuoc['da_ghi'] > 0 ? 'Đã lập đủ hóa đơn cho các phòng đã ghi chỉ số' : 'Ghi chỉ số xong mới lập được hóa đơn') ?></span>
                    </a>
                    <span class="count"><?= $dien_nuoc['cho_lap_hoa_don'] ?></span>
                </li>
                <!-- TT007 -->
                <li>
                    <span class="dot-lg <?= (int)$no_qua_han['so_phong'] > 0 ? 'danger' : 'ok' ?>"></span>
                    <a class="grow" href="nhac_no.php">
                        Nhắc nợ quá hạn
                        <span class="sub"><?= (int)$no_qua_han['so_phong'] > 0
                            ? 'Phòng nợ quá ' . SO_NGAY_NHAC_NO . ' ngày kể từ hạn thu, tổng ' . tien($no_qua_han['tien'])
                            : 'Không phòng nào nợ quá hạn' ?></span>
                    </a>
                    <span class="count"><?= (int)$no_qua_han['so_phong'] ?></span>
                </li>
                <!-- HD006 -->
                <li>
                    <span class="dot-lg <?= $so_sap_het_han > 0 ? 'warn' : 'ok' ?>"></span>
                    <a class="grow" href="hop_dong.php?loc=sap_het_han">
                        Hỏi khách gia hạn hay trả phòng
                        <span class="sub"><?= $so_sap_het_han > 0
                            ? 'Hợp đồng hết hạn trong ' . SO_NGAY_SAP_HET_HAN . ' ngày tới'
                            : 'Không có hợp đồng sắp hết hạn' ?></span>
                    </a>
                    <span class="count"><?= $so_sap_het_han ?></span>
                </li>
                <!-- SC002 -->
                <li>
                    <span class="dot-lg <?= $dem_su_co['cho_xu_ly'] > 0 ? 'danger' : 'ok' ?>"></span>
                    <a class="grow" href="su_co.php?loc=cho_xu_ly">
                        Phân công thợ sửa sự cố
                        <span class="sub"><?= $dem_su_co['cho_xu_ly'] > 0 ? 'Sự cố khách báo đang chờ xử lý' : 'Không có sự cố chờ xử lý' ?></span>
                    </a>
                    <span class="count"><?= $dem_su_co['cho_xu_ly'] ?></span>
                </li>
            </ul>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-head">
                <h2>Phòng trống sẵn sàng cho thuê</h2>
                <span class="muted"><?= count($phong_trong) ?> phòng</span>
            </div>
            <div class="card-body">
                <?php if ($phong_trong): ?>
                    <div class="room-chips">
                        <?php foreach ($phong_trong as $p): ?>
                            <a class="room-chip" href="phong_chi_tiet.php?id=<?= (int)$p['id'] ?>">
                                <strong><?= e($p['ma_phong']) ?></strong>
                                <span><?= e(tien($p['gia_thue'])) ?> &middot; <?= e(dien_tich($p['dien_tich'])) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="muted">Hiện không còn phòng trống.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card section">
            <div class="card-head"><h2>Lối tắt</h2></div>
            <div class="card-body shortcuts">
                <a class="shortcut" href="hop_dong_tao.php">Tạo hợp đồng thuê <span class="arrow">&rarr;</span></a>
                <a class="shortcut" href="khach_thue_form.php">Thêm khách thuê <span class="arrow">&rarr;</span></a>
                <a class="shortcut" href="bao_su_co.php">Ghi nhận sự cố <span class="arrow">&rarr;</span></a>
                <?php if (is_chu_tro()): ?>
                    <a class="shortcut" href="bao_cao.php">Báo cáo doanh thu <span class="arrow">&rarr;</span></a>
                    <a class="shortcut" href="phong_form.php">Thêm phòng mới <span class="arrow">&rarr;</span></a>
                    <a class="shortcut" href="tai_khoan_them.php">Tạo tài khoản quản lý <span class="arrow">&rarr;</span></a>
                <?php endif; ?>
                <a class="shortcut" href="doi_mat_khau.php">Đổi mật khẩu <span class="arrow">&rarr;</span></a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
