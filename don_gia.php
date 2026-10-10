<?php
/**
 * =====================================================================
 *  DN004 – CẤU HÌNH ĐƠN GIÁ DỊCH VỤ  (sẽ làm ở Sprint 3)
 * ---------------------------------------------------------------------
 *  Ở Sprint 1, trang này chỉ dùng để kiểm thử TK005 – AC1:
 *  tài khoản Quản lý không vào được mục Cấu hình đơn giá.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');   // TK005 – AC1

$page_title = 'Cấu hình đơn giá dịch vụ';
$sprint     = 'Sprint 3';
$stories    = 'DN004 – Cấu hình đơn giá dịch vụ';
$mo_ta      = 'Chức năng đặt đơn giá điện, nước, rác, wifi, gửi xe sẽ được xây dựng ở Sprint 3. '
            . 'Ở phiên bản hiện tại, trang này dùng để kiểm thử phân quyền: chỉ Chủ trọ mới vào được.';
require __DIR__ . '/includes/sap_co.php';
