# Hệ thống quản lý nhà trọ

Đồ án môn Quản lý dự án với Agile, Nhóm 2. Phiên bản 1.0, ngày 18/10/2026.

Ứng dụng web giúp chủ nhà trọ quản lý phòng, khách thuê, hợp đồng, chỉ số điện nước, hóa đơn, sự cố và báo cáo. Nhóm làm theo Scrum trong 3 sprint, hoàn thành 43 user story.

## Thành viên

| | Họ tên | Vai trò |
|---|---|---|
| TV1 | Thắng Trịnh | Product Owner, lập trình |
| TV2 | Cung Thành Nguyễn | Scrum Master, lập trình |
| TV3 | Nguyen Anh Tuan | Lập trình |
| TV4 | Đặng Quang Thắng | Lập trình, kiểm thử |

## Công nghệ

- PHP 7.4 trở lên (đã chạy thử trên PHP 8.4), MySQL 5.7 / 8.x hoặc MariaDB
- Chạy trên Laragon hoặc XAMPP, không cần cài thêm thư viện; file Excel ở phần báo cáo do code tự tạo

## Chạy thử

1. Chép code vào thư mục `nhatro` trong `www` (Laragon) hoặc `C:\xampp\htdocs` (XAMPP). Không để lồng hai lớp `nhatro\nhatro`.
2. Bật Apache và MySQL.
3. Mở `http://localhost/nhatro/install.php`, bấm **Cài đặt dữ liệu mẫu**. Ngày tháng trong dữ liệu mẫu tính lùi từ ngày cài, nên trước mỗi buổi demo nên cài lại.
4. Vào `http://localhost/nhatro` và đăng nhập bằng một tài khoản bên dưới.

Nếu MySQL có đặt mật khẩu thì sửa `DB_PASS` trong `config.php`.

### Tài khoản dùng thử

| Tài khoản | Mật khẩu | Vai trò |
|---|---|---|
| `chutro` | `123456` | Chủ trọ, dùng được mọi chức năng |
| `quanly` | `123456` | Quản lý, không vào được Báo cáo, Đơn giá, Tài khoản |
| `tuanpm` | `123456` | Quản lý, dùng để thử khóa tài khoản khi nhập sai 5 lần |

Khách thuê không cần tài khoản: báo sự cố ở `bao_su_co.php` (có nút ở trang đăng nhập) và tra cứu tiến độ bằng mã theo dõi ở `tra_cuu_su_co.php`.

## User story theo sprint

| Sprint | Thời gian | User story |
|---|---|---|
| 1 | 21/09 – 04/10 | TK001–TK005, PH001–PH006 |
| 2 | 05/10 – 11/10 | KT001–KT005, HD001–HD004, DN001, DN003, DN004, TT001, TT002 |
| 3 | 12/10 – 18/10 | HD005, HD006, DN002, DN005, TT003–TT007, SC001–SC004, BC001–BC005 |

| Mã | User story | Sprint | File chính |
|---|---|:-:|---|
| TK001 | Đăng nhập hệ thống | 1 | `login.php` |
| TK002 | Đăng xuất | 1 | `logout.php` |
| TK003 | Đổi mật khẩu | 1 | `doi_mat_khau.php` |
| TK004 | Tạo tài khoản quản lý | 1 | `tai_khoan_them.php` |
| TK005 | Phân quyền tài khoản | 1 | `tai_khoan.php` |
| PH001 | Thêm phòng trọ | 1 | `phong_form.php` |
| PH002 | Sửa thông tin phòng | 1 | `phong_form.php` |
| PH003 | Xóa phòng | 1 | `phong_xoa.php` |
| PH004 | Xem danh sách phòng | 1 | `phong.php` |
| PH005 | Lọc phòng theo trạng thái | 1 | `phong.php` |
| PH006 | Xem chi tiết phòng | 1 | `phong_chi_tiet.php` |
| KT001 | Thêm khách thuê | 2 | `khach_thue_form.php` |
| KT002 | Sửa thông tin khách thuê | 2 | `khach_thue_form.php` |
| KT003 | Xem danh sách khách thuê | 2 | `khach_thue.php` |
| KT004 | Tìm kiếm khách thuê | 2 | `khach_thue.php` |
| KT005 | Thêm người ở cùng phòng | 2 | `nguoi_o_them.php` |
| HD001 | Tạo hợp đồng thuê | 2 | `hop_dong_tao.php` |
| HD002 | Xem danh sách hợp đồng | 2 | `hop_dong.php` |
| HD003 | Xem chi tiết hợp đồng | 2 | `hop_dong_chi_tiet.php` |
| HD004 | Gia hạn hợp đồng | 2 | `hop_dong_gia_han.php` |
| HD005 | Thanh lý hợp đồng | 3 | `hop_dong_thanh_ly.php` |
| HD006 | Cảnh báo hợp đồng sắp hết hạn | 3 | `index.php`, `hop_dong.php` |
| DN001 | Ghi chỉ số điện nước hàng tháng | 2 | `chi_so_ghi.php` |
| DN002 | Xem lịch sử chỉ số | 3 | `chi_so_lich_su.php` |
| DN003 | Sửa chỉ số ghi sai | 2 | `chi_so_ghi.php` |
| DN004 | Cấu hình đơn giá dịch vụ | 2 | `don_gia.php` |
| DN005 | Nhập chỉ số hàng loạt | 3 | `chi_so.php` |
| TT001 | Sinh hóa đơn tháng | 2 | `hoa_don_tao.php` |
| TT002 | Xem chi tiết hóa đơn | 2 | `hoa_don_chi_tiet.php` |
| TT003 | Ghi nhận thanh toán | 3 | `hoa_don_chi_tiet.php` |
| TT004 | Xem danh sách hóa đơn | 3 | `hoa_don.php` |
| TT005 | Lọc hóa đơn theo trạng thái | 3 | `hoa_don.php` |
| TT006 | In hóa đơn | 3 | `hoa_don_in.php` |
| TT007 | Nhắc nợ quá hạn | 3 | `nhac_no.php` |
| SC001 | Gửi báo sự cố | 3 | `bao_su_co.php` |
| SC002 | Xem danh sách sự cố | 3 | `su_co.php` |
| SC003 | Cập nhật trạng thái sự cố | 3 | `su_co_chi_tiet.php` |
| SC004 | Ghi chi phí sửa chữa | 3 | `su_co_chi_tiet.php` |
| BC001 | Báo cáo doanh thu theo tháng | 3 | `bao_cao.php` |
| BC002 | Báo cáo công nợ | 3 | `bao_cao_cong_no.php` |
| BC003 | Thống kê tỷ lệ lấp đầy phòng | 3 | `bao_cao_lap_day.php` |
| BC004 | Biểu đồ doanh thu | 3 | `bao_cao.php` |
| BC005 | Xuất báo cáo ra Excel | 3 | `bao_cao_xuat.php` |

Đầu mỗi file PHP có ghi user story và Acceptance Criteria mà file đó thực hiện.

## Cấu trúc thư mục

```
*.php          Các trang chức năng
includes/      Hàm dùng chung, nghiệp vụ theo từng epic, phần đầu và cuối trang
assets/        style.css, app.js
uploads/       Ảnh CCCD và ảnh sự cố tải lên
database.sql   Tạo bảng và dữ liệu mẫu
install.php    Trang cài đặt dữ liệu mẫu
config.php     Kết nối MySQL và các con số lấy từ Acceptance Criteria
```

## Quản lý dự án

- Bảng Trello: https://trello.com/b/fZ2kHLmv
- Product Backlog, Sprint Backlog, Burndown và Retrospective: file Excel của nhóm
