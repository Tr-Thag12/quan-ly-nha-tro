<?php
/**
 * =====================================================================
 *  PH004 – XEM DANH SÁCH PHÒNG
 *    AC1: Hiển thị mã phòng, tầng, giá, trạng thái, tên khách đang ở
 *    AC2: Có phân trang khi quá 20 phòng
 *    AC3: (bổ sung v0.2) Hiển thị số người đang ở so với số người tối đa, ví dụ 3/4
 *         -> số người được ĐẾM từ bảng nguoi_o, không lưu thành một con số riêng
 *
 *  PH005 – LỌC PHÒNG THEO TRẠNG THÁI
 *    AC1: Chọn trạng thái thì danh sách lọc ngay không cần tải lại trang
 *    AC2: Hiển thị số lượng phòng của mỗi trạng thái
 * ---------------------------------------------------------------------
 *  Cách làm: PHP lấy TOÀN BỘ phòng ra một lần. JavaScript (assets/app.js)
 *  ẩn/hiện từng dòng theo trạng thái và chia 20 dòng một trang,
 *  nên bấm lọc hay chuyển trang đều không phải tải lại trang.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_login();   // Chủ trọ và Quản lý đều xem được

// Lấy mọi phòng, kèm người đứng tên hợp đồng còn hiệu lực (nếu có)
// và số người đang ở (AC3): đếm những người của hợp đồng đó chưa rời đi (ngay_roi để trống).
// Phòng không có hợp đồng thì h.id rỗng nên đếm ra 0.
$ds_phong = db()->query("
    SELECT p.*, k.ho_ten AS khach_dang_o,
           (SELECT COUNT(*) FROM nguoi_o n
            WHERE n.hop_dong_id = h.id AND n.ngay_roi IS NULL) AS so_nguoi_dang_o
    FROM phong p
    LEFT JOIN hop_dong h   ON h.phong_id = p.id AND h.trang_thai = 'hieu_luc'
    LEFT JOIN khach_thue k ON k.id = h.khach_thue_id
    ORDER BY p.tang, p.ma_phong
")->fetchAll();

// PH005 – AC2: đếm số phòng của mỗi trạng thái
$so_luong = ['tat_ca' => count($ds_phong)];
foreach (TRANG_THAI_PHONG as $ma => $ten) {
    $so_luong[$ma] = 0;
}
foreach ($ds_phong as $p) {
    $so_luong[$p['trang_thai']]++;
}

// Bộ lọc ban đầu, ví dụ khi bấm thẻ "Trống" ở trang chủ -> phong.php?trang_thai=trong
$loc_ban_dau = 'tat_ca';
if (isset($_GET['trang_thai']) && array_key_exists($_GET['trang_thai'], TRANG_THAI_PHONG)) {
    $loc_ban_dau = $_GET['trang_thai'];
}

// Phòng vừa thêm/sửa sẽ được tô sáng (PH002 – AC2)
$id_moi = isset($_GET['moi']) ? (int)$_GET['moi'] : 0;

$page_title = 'Danh sách phòng';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Danh sách phòng</h1>
        <p class="muted">Bấm vào mã phòng để xem chi tiết, khách đang ở và lịch sử hợp đồng.</p>
    </div>
    <?php if (is_chu_tro()): ?>
        <a class="btn btn-primary" href="phong_form.php">+ Thêm phòng</a>
    <?php endif; ?>
</div>

<div class="card" id="room-list" data-per-page="<?= SO_PHONG_MOI_TRANG ?>" data-initial="<?= e($loc_ban_dau) ?>">
    <!-- PH005: các nút lọc, mỗi nút kèm số lượng phòng -->
    <div class="filter-bar">
        <button type="button" class="chip" data-filter="tat_ca">
            Tất cả <span class="chip-count"><?= $so_luong['tat_ca'] ?></span>
        </button>
        <?php foreach (TRANG_THAI_PHONG as $ma => $ten): ?>
            <button type="button" class="chip" data-filter="<?= e($ma) ?>">
                <span class="dot dot-<?= e($ma) ?>"></span><?= e($ten) ?>
                <span class="chip-count"><?= $so_luong[$ma] ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Mã phòng</th>
                    <th>Tầng</th>
                    <th class="num">Diện tích</th>
                    <th class="num">Giá thuê / tháng</th>
                    <th>Trạng thái</th>
                    <th class="center" title="Số người đang ở / số người tối đa của phòng">Số người</th>
                    <th>Người đại diện</th>
                    <th class="actions">Thao tác</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ds_phong as $p): ?>
                <tr data-status="<?= e($p['trang_thai']) ?>"<?= (int)$p['id'] === $id_moi ? ' class="is-new"' : '' ?>>
                    <td><a class="room-code" href="phong_chi_tiet.php?id=<?= (int)$p['id'] ?>"><?= e($p['ma_phong']) ?></a></td>
                    <td><?= (int)$p['tang'] ?></td>
                    <td class="num"><?= e(dien_tich($p['dien_tich'])) ?></td>
                    <td class="num"><?= e(tien($p['gia_thue'])) ?></td>
                    <td><?= nhan($p['trang_thai'], TRANG_THAI_PHONG) ?></td>
                    <td class="center"><?= so_nguoi($p['so_nguoi_dang_o'], $p['so_nguoi_toi_da']) ?></td>
                    <td><?= $p['khach_dang_o'] ? e($p['khach_dang_o']) : '<span class="muted">—</span>' ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="phong_chi_tiet.php?id=<?= (int)$p['id'] ?>">Xem</a>
                        <?php if (is_chu_tro()): ?>
                            <a class="btn btn-sm" href="phong_form.php?id=<?= (int)$p['id'] ?>">Sửa</a>
                            <form method="post" action="phong_xoa.php" class="inline"
                                  data-confirm="Bạn có chắc muốn xóa phòng <?= e($p['ma_phong']) ?>? Thao tác này không thể hoàn tác.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="empty-filter" hidden>Không có phòng nào ở trạng thái này.</div>

    <!-- PH004: thông tin trang + các nút chuyển trang (do JavaScript vẽ) -->
    <div class="list-foot">
        <span class="page-info">Tổng cộng <?= $so_luong['tat_ca'] ?> phòng</span>
        <div class="pagination"></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
