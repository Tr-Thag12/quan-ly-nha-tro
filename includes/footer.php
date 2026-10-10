<?php
/** Phần cuối trang dùng chung. */
?>
</main>
<?php if (empty($auth_page)): ?>
<footer class="footer">
    <?= e(APP_NAME) ?> &middot; Phiên bản <?= e(APP_VERSION) ?>
</footer>
<?php endif; ?>
<script src="assets/app.js"></script>
</body>
</html>
