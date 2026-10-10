<?php
/**
 * =====================================================================
 *  CẤU HÌNH HỆ THỐNG
 * ---------------------------------------------------------------------
 *  Mặc định của XAMPP: tài khoản MySQL là root, mật khẩu để trống.
 *  Nếu MySQL trên máy bạn có đặt mật khẩu thì sửa dòng DB_PASS.
 *  Nếu MySQL chạy cổng khác 3306 thì sửa dòng DB_PORT.
 * =====================================================================
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'nhatro');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Quản lý Nhà trọ');
define('APP_VERSION', 'Sprint 1 (v0.1)');

// Các con số lấy thẳng từ Acceptance Criteria trong Product Backlog
define('SO_LAN_SAI_TOI_DA', 5);      // TK001 – AC2: sai quá 5 lần thì khóa
define('PHUT_KHOA', 5);              // TK001 – AC2: khóa trong 5 phút
define('MAT_KHAU_TOI_THIEU', 6);     // TK003 – AC2: mật khẩu mới tối thiểu 6 ký tự
define('SO_PHONG_MOI_TRANG', 20);    // PH004 – AC2: quá 20 phòng thì phân trang

date_default_timezone_set('Asia/Ho_Chi_Minh');
