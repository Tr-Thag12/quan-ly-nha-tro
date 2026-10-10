<?php
/**
 * =====================================================================
 *  TRANG CHỦ – màn hình đầu tiên sau khi đăng nhập (TK001 – AC1)
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

<div class="grid-2">
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

    <div class="card">
        <div class="card-head"><h2>Lối tắt</h2></div>
        <div class="card-body shortcuts">
            <a class="shortcut" href="phong.php">Danh sách phòng <span class="arrow">&rarr;</span></a>
            <?php if (is_chu_tro()): ?>
                <a class="shortcut" href="phong_form.php">Thêm phòng mới <span class="arrow">&rarr;</span></a>
                <a class="shortcut" href="tai_khoan_them.php">Tạo tài khoản quản lý <span class="arrow">&rarr;</span></a>
            <?php endif; ?>
            <a class="shortcut" href="doi_mat_khau.php">Đổi mật khẩu <span class="arrow">&rarr;</span></a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
