<?php
/**
 * File khởi tạo: mọi trang đều nạp file này ở dòng đầu tiên.
 * Nạp cấu hình, các hàm dùng chung, rồi đến các hàm nghiệp vụ theo từng Epic.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';   // hàm dùng chung, đăng nhập, phân quyền
require_once __DIR__ . '/hop_dong.php';    // Epic Quản lý hợp đồng, người ở cùng
require_once __DIR__ . '/dien_nuoc.php';   // Epic Ghi chỉ số điện nước
require_once __DIR__ . '/hoa_don.php';     // Epic Hóa đơn và thu tiền
require_once __DIR__ . '/su_co.php';       // Epic Sự cố và bảo trì
require_once __DIR__ . '/tai_len.php';     // Lưu ảnh tải lên (ảnh CCCD, ảnh sự cố)
