<?php
/**
 * =====================================================================
 *  BC001, BC002 – BÁO CÁO DOANH THU VÀ CÔNG NỢ  (sẽ làm ở Sprint 4)
 * ---------------------------------------------------------------------
 *  Ở Sprint 1, trang này chỉ dùng để kiểm thử TK005 – AC1:
 *  tài khoản Quản lý không vào được mục Báo cáo.
 * =====================================================================
 */
require __DIR__ . '/includes/init.php';
require_role('chu_tro');   // TK005 – AC1

$page_title = 'Báo cáo';
$sprint     = 'Sprint 4';
$stories    = 'BC001 – Báo cáo doanh thu theo tháng, BC002 – Báo cáo công nợ';
$mo_ta      = 'Báo cáo doanh thu và công nợ cần có dữ liệu hóa đơn, nên sẽ được xây dựng ở Sprint 4 sau khi có chức năng sinh hóa đơn. '
            . 'Ở phiên bản hiện tại, trang này dùng để kiểm thử phân quyền: chỉ Chủ trọ mới vào được.';
require __DIR__ . '/includes/sap_co.php';
