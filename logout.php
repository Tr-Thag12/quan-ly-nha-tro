<?php
/**
 * =====================================================================
 *  TK002 – ĐĂNG XUẤT
 * ---------------------------------------------------------------------
 *  AC1: Bấm Đăng xuất thì quay về màn hình đăng nhập
 *  AC2: Bấm nút Back của trình duyệt không vào lại được trang trong
 *       -> mọi trang trong đều gọi require_login(), hàm này vừa kiểm tra
 *          đăng nhập vừa cấm trình duyệt lưu đệm (xem no_cache()).
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';

dang_xuat();                                  // xóa sạch phiên đăng nhập
redirect('login.php?da_dang_xuat=1');         // AC1
