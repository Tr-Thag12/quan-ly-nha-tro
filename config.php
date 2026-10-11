<?php
/**
 * =====================================================================
 *  CẤU HÌNH HỆ THỐNG
 * ---------------------------------------------------------------------
 *  Mặc định của Laragon và XAMPP: tài khoản MySQL là root, mật khẩu trống.
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
define('APP_VERSION', '1.0');

// Thông tin nhà trọ in trên hóa đơn (TT006) và hợp đồng (HD003)
define('TEN_NHA_TRO', 'Nhà trọ An Bình');
define('DIA_CHI_NHA_TRO', 'Số 18, ngõ 72 phố Chùa Láng, Đống Đa, Hà Nội');
define('SDT_NHA_TRO', '0912 345 678');
define('CHU_NHA_TRO', 'Trần Văn Hùng');

// Các con số lấy thẳng từ Acceptance Criteria trong Product Backlog
define('SO_LAN_SAI_TOI_DA', 5);      // TK001 – AC2: sai quá 5 lần thì khóa
define('PHUT_KHOA', 5);              // TK001 – AC2: khóa trong 5 phút
define('MAT_KHAU_TOI_THIEU', 6);     // TK003 – AC2: mật khẩu mới tối thiểu 6 ký tự
define('SO_PHONG_MOI_TRANG', 20);    // PH004 – AC2: quá 20 phòng thì phân trang
define('SO_NGAY_SAP_HET_HAN', 30);   // HD006 – AC1: cảnh báo hợp đồng hết hạn trong 30 ngày tới
define('MUC_TANG_BAT_THUONG', 50);   // DN002 – AC2: đánh dấu tháng dùng tăng trên 50%
define('NGAY_HAN_THANH_TOAN', 10);   // Hóa đơn tháng này có hạn thu vào ngày 10 tháng sau
define('SO_NGAY_NHAC_NO', 5);        // TT007 – AC1: nhắc nợ khi quá hạn hơn 5 ngày
define('ANH_TOI_DA_MB', 2);          // KT001, SC001: ảnh tải lên tối đa 2 MB

date_default_timezone_set('Asia/Ho_Chi_Minh');
