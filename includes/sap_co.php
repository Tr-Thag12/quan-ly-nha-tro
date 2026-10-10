<?php
/**
 * Trang giữ chỗ cho chức năng thuộc sprint sau.
 * Trang gọi file này cần đặt sẵn: $page_title, $sprint, $stories, $mo_ta.
 */
require __DIR__ . '/header.php';
?>
<div class="card empty-state">
    <span class="sprint-tag"><?= e($sprint) ?></span>
    <h2><?= e($page_title) ?></h2>
    <p class="muted"><?= e($mo_ta) ?></p>
    <p class="muted small">User story: <?= e($stories) ?></p>
    <a class="btn" href="index.php">&larr; Về trang chủ</a>
</div>
<?php require __DIR__ . '/footer.php'; ?>
