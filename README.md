# Hệ thống quản lý nhà trọ

Đồ án môn Quản lý dự án với Agile, Nhóm 2.

Ứng dụng web giúp chủ nhà trọ quản lý phòng, khách thuê, hợp đồng, chỉ số điện nước, hóa đơn, sự cố và báo cáo. Nhóm làm theo Scrum; Product Backlog, Sprint Backlog và Burndown nằm trong file Excel của nhóm.

## Công nghệ

- PHP 7.4 trở lên, MySQL 5.7 / 8.x hoặc MariaDB
- Chạy trên Laragon hoặc XAMPP, không cần cài thêm thư viện

## Chạy thử

1. Chép code vào thư mục `nhatro` trong `www` (Laragon) hoặc `C:\xampp\htdocs` (XAMPP).
2. Bật Apache và MySQL.
3. Mở `http://localhost/nhatro/install.php`, bấm **Cài đặt dữ liệu mẫu**.
4. Vào `http://localhost/nhatro`, đăng nhập `chutro` / `123456`.

Nếu MySQL có đặt mật khẩu thì sửa `DB_PASS` trong `config.php`.
