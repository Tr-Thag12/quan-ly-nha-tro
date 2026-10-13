<?php
/** Thanh chuyển giữa các báo cáo (chỉ Chủ trọ). Trang gọi đặt biến $tab_bao_cao. */
$cac_bao_cao = [
    'doanh_thu' => ['bao_cao.php', 'Doanh thu theo tháng'],
    'cong_no'   => ['bao_cao_cong_no.php', 'Công nợ'],
    'lap_day'   => ['bao_cao_lap_day.php', 'Tỷ lệ lấp đầy phòng'],
];
?>
<nav class="tabs">
    <?php foreach ($cac_bao_cao as $ma => $bc): ?>
        <a href="<?= e($bc[0]) ?>"<?= $tab_bao_cao === $ma ? ' class="active"' : '' ?>><?= e($bc[1]) ?></a>
    <?php endforeach; ?>
</nav>
