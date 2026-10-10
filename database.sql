-- =====================================================================
--  CƠ SỞ DỮ LIỆU: HỆ THỐNG QUẢN LÝ NHÀ TRỌ  –  PHIÊN BẢN SPRINT 1
-- ---------------------------------------------------------------------
--  Cách 1 (dễ nhất): mở http://localhost/nhatro/install.php rồi bấm nút.
--  Cách 2: mở phpMyAdmin > tab Import > chọn file này > bấm Go.
--
--  Tài khoản mẫu (mật khẩu đều là 123456):
--    chutro  – Chủ trọ
--    quanly  – Quản lý
--    tuanpm  – Quản lý (dùng để thử chức năng khóa khi nhập sai 5 lần)
--
--  Lưu ý: chạy lại file này sẽ XÓA dữ liệu cũ và nạp lại dữ liệu mẫu.
--
--  v0.2: thêm bảng nguoi_o để đếm số người đang ở mỗi phòng (PH004 – AC3).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS nhatro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nhatro;

DROP TABLE IF EXISTS nguoi_o;
DROP TABLE IF EXISTS su_co;
DROP TABLE IF EXISTS hop_dong;
DROP TABLE IF EXISTS khach_thue;
DROP TABLE IF EXISTS phong;
DROP TABLE IF EXISTS tai_khoan;

-- ---------------------------------------------------------------------
--  BẢNG TÀI KHOẢN  (Epic: Quản lý tài khoản – TK001 đến TK005)
-- ---------------------------------------------------------------------
CREATE TABLE tai_khoan (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ho_ten         VARCHAR(100) NOT NULL,
    ten_dang_nhap  VARCHAR(50)  NOT NULL UNIQUE,
    mat_khau       VARCHAR(255) NOT NULL COMMENT 'Mật khẩu đã mã hóa bcrypt, không lưu mật khẩu gốc',
    so_dien_thoai  VARCHAR(10)  NULL,
    vai_tro        ENUM('chu_tro', 'quan_ly') NOT NULL DEFAULT 'quan_ly' COMMENT 'TK005: phân quyền',
    so_lan_sai     INT NOT NULL DEFAULT 0 COMMENT 'TK001: số lần nhập sai mật khẩu liên tiếp',
    khoa_den       DATETIME NULL COMMENT 'TK001: bị khóa đăng nhập đến thời điểm này',
    ngay_tao       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BẢNG PHÒNG  (Epic: Quản lý phòng – PH001 đến PH006)
-- ---------------------------------------------------------------------
CREATE TABLE phong (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ma_phong         VARCHAR(20)  NOT NULL UNIQUE COMMENT 'PH001: mã phòng không được trùng',
    tang             INT          NOT NULL,
    dien_tich        DECIMAL(5,1) NOT NULL COMMENT 'Đơn vị m2',
    gia_thue         INT          NOT NULL COMMENT 'VNĐ/tháng, phải lớn hơn 0',
    so_nguoi_toi_da  INT          NOT NULL DEFAULT 2,
    tien_nghi        VARCHAR(255) NULL,
    trang_thai       ENUM('trong', 'dang_thue', 'dang_sua') NOT NULL DEFAULT 'trong' COMMENT 'PH001: phòng mới mặc định Trống',
    ngay_tao         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BẢNG KHÁCH THUÊ  (Epic: Quản lý khách thuê – màn hình làm ở Sprint 2)
--  Sprint 1 chỉ đọc bảng này để hiển thị "khách đang ở" (PH004, PH006)
-- ---------------------------------------------------------------------
CREATE TABLE khach_thue (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ho_ten         VARCHAR(100) NOT NULL,
    cccd           VARCHAR(12)  NOT NULL UNIQUE,
    so_dien_thoai  VARCHAR(10)  NOT NULL,
    que_quan       VARCHAR(100) NULL,
    ngay_tao       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BẢNG HỢP ĐỒNG  (Epic: Quản lý hợp đồng – màn hình làm ở Sprint 2)
--  gia_thue được chốt tại thời điểm ký nên khi sửa giá phòng (PH002)
--  thì dữ liệu hợp đồng cũ không bị thay đổi.
-- ---------------------------------------------------------------------
CREATE TABLE hop_dong (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ma_hop_dong    VARCHAR(20) NOT NULL UNIQUE,
    phong_id       INT  NOT NULL,
    khach_thue_id  INT  NOT NULL,
    ngay_bat_dau   DATE NOT NULL,
    ngay_ket_thuc  DATE NOT NULL,
    gia_thue       INT  NOT NULL COMMENT 'Giá thuê chốt lúc ký hợp đồng',
    tien_coc       INT  NOT NULL,
    trang_thai     ENUM('hieu_luc', 'da_thanh_ly') NOT NULL DEFAULT 'hieu_luc',
    CONSTRAINT fk_hopdong_phong FOREIGN KEY (phong_id) REFERENCES phong (id),
    CONSTRAINT fk_hopdong_khach FOREIGN KEY (khach_thue_id) REFERENCES khach_thue (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BẢNG NGƯỜI Ở  (thêm ở v0.2)
--  Mỗi dòng là một người ở theo một hợp đồng. Người đứng tên hợp đồng
--  cũng nằm trong bảng này với la_dai_dien = 1.
--  - PH004 – AC3: đếm số người đang ở (ngay_roi để trống) để hiện "3/4".
--  - KT005 (Sprint 2): màn hình "Thêm người ở cùng phòng" sẽ ghi vào bảng này.
--  Số người được ĐẾM từ danh sách người thật, không gõ tay một con số,
--  nên không bao giờ bị lệch với danh sách người ở.
-- ---------------------------------------------------------------------
CREATE TABLE nguoi_o (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    hop_dong_id    INT  NOT NULL,
    khach_thue_id  INT  NOT NULL,
    la_dai_dien    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = người đứng tên hợp đồng',
    ngay_vao       DATE NOT NULL,
    ngay_roi       DATE NULL COMMENT 'Để trống = vẫn đang ở',
    UNIQUE KEY uq_nguoio_hopdong_khach (hop_dong_id, khach_thue_id),
    CONSTRAINT fk_nguoio_hopdong FOREIGN KEY (hop_dong_id) REFERENCES hop_dong (id),
    CONSTRAINT fk_nguoio_khach FOREIGN KEY (khach_thue_id) REFERENCES khach_thue (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  BẢNG SỰ CỐ  (Epic: Sự cố và bảo trì – màn hình làm ở Sprint 5)
--  Sprint 1 chỉ đọc bảng này để hiển thị lịch sử sự cố của phòng (PH006)
-- ---------------------------------------------------------------------
CREATE TABLE su_co (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    phong_id    INT          NOT NULL,
    loai        VARCHAR(30)  NOT NULL,
    mo_ta       VARCHAR(255) NOT NULL,
    trang_thai  ENUM('cho_xu_ly', 'dang_xu_ly', 'da_xong') NOT NULL DEFAULT 'cho_xu_ly',
    ngay_bao    DATETIME     NOT NULL,
    CONSTRAINT fk_suco_phong FOREIGN KEY (phong_id) REFERENCES phong (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================================
--  DỮ LIỆU MẪU
-- =====================================================================

INSERT INTO tai_khoan (ho_ten, ten_dang_nhap, mat_khau, so_dien_thoai, vai_tro) VALUES
('Trần Văn Hùng',  'chutro', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0912345678', 'chu_tro'),
('Lê Thị Mai',     'quanly', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0987654321', 'quan_ly'),
('Phạm Minh Tuấn', 'tuanpm', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0978123456', 'quan_ly');

-- 24 phòng, 3 tầng. Có 24 phòng để thấy được phân trang 20 phòng/trang (PH004).
INSERT INTO phong (ma_phong, tang, dien_tich, gia_thue, so_nguoi_toi_da, tien_nghi, trang_thai) VALUES
('P101', 1, 25.0, 3200000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
('P102', 1, 20.0, 2800000, 3, 'Điều hòa, nóng lạnh, giường, tủ quần áo', 'dang_thue'),
('P103', 1, 20.0, 2800000, 3, 'Điều hòa, nóng lạnh, giường, tủ quần áo', 'dang_thue'),
('P104', 1, 22.0, 3000000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
('P105', 1, 18.0, 2600000, 2, 'Điều hòa, nóng lạnh', 'dang_thue'),
('P106', 1, 18.0, 2600000, 2, 'Điều hòa, nóng lạnh', 'trong'),
('P107', 1, 22.0, 3000000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
('P108', 1, 25.0, 3200000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'trong'),
('P201', 2, 25.0, 3000000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
('P202', 2, 20.0, 2600000, 3, 'Điều hòa, nóng lạnh, giường', 'dang_thue'),
('P203', 2, 20.0, 2600000, 3, 'Điều hòa, nóng lạnh, giường', 'trong'),
('P204', 2, 22.0, 2800000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
('P205', 2, 18.0, 2400000, 2, 'Nóng lạnh, gác xép', 'dang_thue'),
('P206', 2, 18.0, 2400000, 2, 'Nóng lạnh, gác xép', 'dang_thue'),
('P207', 2, 22.0, 2800000, 3, 'Điều hòa, nóng lạnh, gác xép', 'trong'),
('P208', 2, 25.0, 3000000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
('P301', 3, 25.0, 2700000, 4, 'Điều hòa, nóng lạnh, ban công', 'dang_thue'),
('P302', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'trong'),
('P303', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'dang_thue'),
('P304', 3, 16.0, 2000000, 2, 'Nóng lạnh', 'dang_sua'),
('P305', 3, 16.0, 2000000, 2, 'Nóng lạnh', 'trong'),
('P306', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'dang_thue'),
('P307', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'trong'),
('P308', 3, 25.0, 2700000, 4, 'Điều hòa, nóng lạnh, ban công', 'dang_sua');

-- 15 khách đang ở + 4 khách cũ đã trả phòng (để có lịch sử hợp đồng)
INSERT INTO khach_thue (ho_ten, cccd, so_dien_thoai, que_quan) VALUES
('Nguyễn Văn Nam',  '036203004521', '0912487356', 'Nam Định'),
('Trần Thị Hương',  '034302001873', '0986215734', 'Thái Bình'),
('Lê Minh Đức',     '038204006157', '0356781249', 'Thanh Hóa'),
('Phạm Thu Trang',  '031303002468', '0967342185', 'Hải Phòng'),
('Hoàng Quốc Bảo',  '040202007315', '0839126457', 'Nghệ An'),
('Vũ Thị Lan',      '030305004692', '0971538624', 'Hải Dương'),
('Đặng Minh Quân',  '001201008934', '0915672348', 'Hà Nội'),
('Bùi Thị Ngọc',    '035304003187', '0348916275', 'Hà Nam'),
('Đỗ Văn Tùng',     '037203005826', '0987451362', 'Ninh Bình'),
('Hồ Thị Thảo',     '042303001459', '0962874513', 'Hà Tĩnh'),
('Ngô Minh Khoa',   '027204002736', '0376159842', 'Bắc Ninh'),
('Dương Thị Hà',    '026305006381', '0918734625', 'Vĩnh Phúc'),
('Lý Văn Phúc',     '024206004158', '0826451397', 'Bắc Giang'),
('Mai Thị Nhung',   '033304007264', '0945128736', 'Hưng Yên'),
('Trịnh Quang Huy', '025203009513', '0397264815', 'Phú Thọ'),
('Phan Văn Lợi',    '019201003647', '0983561274', 'Thái Nguyên'),
('Cao Thị Diệu',    '022302005918', '0357482196', 'Quảng Ninh'),
('Đinh Công Minh',  '036202001285', '0916357842', 'Nam Định'),
('Tạ Thị Kim',      '034301008462', '0974825163', 'Thái Bình');

-- v0.2: 16 người ở cùng phòng với người đứng tên hợp đồng (id 20 đến 35)
INSERT INTO khach_thue (ho_ten, cccd, so_dien_thoai, que_quan) VALUES
('Nguyễn Thị Hồng',   '036304002917', '0965318472', 'Nam Định'),
('Trần Văn Khải',     '036203007462', '0913764205', 'Nam Định'),
('Lê Thị Thu',        '034303005184', '0387219546', 'Thái Bình'),
('Phạm Đức Anh',      '031204003659', '0934682157', 'Hải Phòng'),
('Vũ Thị Mai',        '030304008127', '0978245163', 'Hải Dương'),
('Vũ Minh Hiếu',      '030206001538', '0356917284', 'Hải Dương'),
('Đặng Thu Hà',       '001303006274', '0916473829', 'Hà Nội'),
('Nguyễn Hữu Lộc',    '001202004819', '0988152736', 'Hà Nội'),
('Trần Quốc Việt',    '001204009361', '0397425168', 'Hà Nội'),
('Bùi Văn Hải',       '035203002746', '0945813627', 'Hà Nam'),
('Đỗ Thị Hạnh',       '037304005293', '0869274531', 'Ninh Bình'),
('Hồ Văn Dũng',       '042204007815', '0917538264', 'Hà Tĩnh'),
('Dương Minh Tâm',    '026203003492', '0326841957', 'Vĩnh Phúc'),
('Lê Thị Quỳnh',      '026304008651', '0976135284', 'Vĩnh Phúc'),
('Lý Thị Hoa',        '024305002374', '0843627195', 'Bắc Giang'),
('Trịnh Thị Lan Anh', '025304006128', '0962751843', 'Phú Thọ');

-- Cột 2 là id phòng (P101 = 1 ... P308 = 24), cột 3 là id khách thuê.
-- Hợp đồng cũ có giá thấp hơn giá phòng hiện tại: minh họa giá được chốt lúc ký.
INSERT INTO hop_dong (ma_hop_dong, phong_id, khach_thue_id, ngay_bat_dau, ngay_ket_thuc, gia_thue, tien_coc, trang_thai) VALUES
('HD-2024-001',  1, 16, '2024-09-01', '2025-08-31', 3000000, 3000000, 'da_thanh_ly'),
('HD-2024-002', 13, 19, '2024-10-01', '2025-09-30', 2200000, 2200000, 'da_thanh_ly'),
('HD-2025-001',  6, 17, '2025-01-15', '2026-07-14', 2500000, 2500000, 'da_thanh_ly'),
('HD-2025-002', 11, 18, '2025-03-01', '2026-08-31', 2500000, 2500000, 'da_thanh_ly'),
('HD-2025-003',  9,  7, '2025-10-15', '2026-10-14', 3000000, 3000000, 'hieu_luc'),
('HD-2025-004',  1,  1, '2025-11-01', '2026-10-31', 3200000, 3200000, 'hieu_luc'),
('HD-2025-005', 13, 10, '2025-11-01', '2026-10-31', 2400000, 2400000, 'hieu_luc'),
('HD-2025-006',  4,  4, '2025-12-15', '2026-12-14', 3000000, 3000000, 'hieu_luc'),
('HD-2026-001',  2,  2, '2026-01-01', '2026-12-31', 2800000, 2800000, 'hieu_luc'),
('HD-2026-002', 19, 14, '2026-01-15', '2027-01-14', 2400000, 2400000, 'hieu_luc'),
('HD-2026-003', 10,  8, '2026-02-01', '2027-01-31', 2600000, 2600000, 'hieu_luc'),
('HD-2026-004',  3,  3, '2026-03-01', '2027-02-28', 2800000, 2800000, 'hieu_luc'),
('HD-2026-005', 22, 15, '2026-03-15', '2027-03-14', 2400000, 2400000, 'hieu_luc'),
('HD-2026-006', 12,  9, '2026-04-01', '2027-03-31', 2800000, 2800000, 'hieu_luc'),
('HD-2026-007', 14, 11, '2026-05-15', '2027-05-14', 2400000, 2400000, 'hieu_luc'),
('HD-2026-008',  5,  5, '2026-06-01', '2027-05-31', 2600000, 2600000, 'hieu_luc'),
('HD-2026-009', 16, 12, '2026-07-01', '2027-06-30', 3000000, 3000000, 'hieu_luc'),
('HD-2026-010',  7,  6, '2026-08-01', '2027-07-31', 3000000, 3000000, 'hieu_luc'),
('HD-2026-011', 17, 13, '2026-09-01', '2027-08-31', 2700000, 2700000, 'hieu_luc');

-- v0.2: người ở theo từng hợp đồng.
-- Cột 1 là id hợp đồng (theo thứ tự thêm ở trên: HD-2024-001 = 1 ... HD-2026-011 = 19),
-- cột 2 là id khách thuê, cột 3 = 1 nếu là người đứng tên hợp đồng.
INSERT INTO nguoi_o (hop_dong_id, khach_thue_id, la_dai_dien, ngay_vao, ngay_roi) VALUES
-- 4 hợp đồng đã thanh lý: người ở đã rời đi
( 1, 16, 1, '2024-09-01', '2025-08-31'),
( 2, 19, 1, '2024-10-01', '2025-09-30'),
( 3, 17, 1, '2025-01-15', '2026-07-14'),
( 4, 18, 1, '2025-03-01', '2026-08-31'),
-- P201: 4/4 người (đủ)
( 5,  7, 1, '2025-10-15', NULL),
( 5, 26, 0, '2025-10-15', NULL),
( 5, 27, 0, '2025-12-01', NULL),
( 5, 28, 0, '2026-03-10', NULL),
-- P101: 3/4 người
( 6,  1, 1, '2025-11-01', NULL),
( 6, 20, 0, '2025-11-01', NULL),
( 6, 21, 0, '2026-02-10', NULL),
-- P205: 2/2 người (đủ)
( 7, 10, 1, '2025-11-01', NULL),
( 7, 31, 0, '2026-01-05', NULL),
-- P104: 2/3 người
( 8,  4, 1, '2025-12-15', NULL),
( 8, 23, 0, '2025-12-15', NULL),
-- P102: 2/3 người
( 9,  2, 1, '2026-01-01', NULL),
( 9, 22, 0, '2026-01-01', NULL),
-- P303: 1/3 người
(10, 14, 1, '2026-01-15', NULL),
-- P202: 2/3 người
(11,  8, 1, '2026-02-01', NULL),
(11, 29, 0, '2026-02-01', NULL),
-- P103: 1/3 người
(12,  3, 1, '2026-03-01', NULL),
-- P306: 2/3 người
(13, 15, 1, '2026-03-15', NULL),
(13, 35, 0, '2026-04-20', NULL),
-- P204: 2/3 người
(14,  9, 1, '2026-04-01', NULL),
(14, 30, 0, '2026-04-01', NULL),
-- P206: 1/2 người
(15, 11, 1, '2026-05-15', NULL),
-- P105: 1/2 người
(16,  5, 1, '2026-06-01', NULL),
-- P208: 3/4 người
(17, 12, 1, '2026-07-01', NULL),
(17, 32, 0, '2026-07-01', NULL),
(17, 33, 0, '2026-08-15', NULL),
-- P107: 3/3 người (đủ)
(18,  6, 1, '2026-08-01', NULL),
(18, 24, 0, '2026-08-01', NULL),
(18, 25, 0, '2026-08-01', NULL),
-- P301: 2/4 người
(19, 13, 1, '2026-09-01', NULL),
(19, 34, 0, '2026-09-01', NULL);

INSERT INTO su_co (phong_id, loai, mo_ta, trang_thai, ngay_bao) VALUES
( 1, 'Điện',     'Ổ cắm cạnh bàn học bị chập, có mùi khét',   'da_xong',    '2026-03-12 20:15:00'),
( 6, 'Nước',     'Bồn cầu bị tắc',                            'da_xong',    '2026-05-20 18:30:00'),
( 1, 'Nước',     'Vòi hoa sen bị rò rỉ nước',                  'da_xong',    '2026-06-02 08:40:00'),
(20, 'Nước',     'Trần nhà thấm nước khi mưa to',              'dang_xu_ly', '2026-09-10 07:30:00'),
(12, 'Khóa cửa', 'Khóa cửa chính bị kẹt, khó mở',              'dang_xu_ly', '2026-09-24 21:05:00'),
(24, 'Điện',     'Điều hòa không lạnh, cần nạp ga',            'cho_xu_ly',  '2026-09-27 19:20:00'),
(14, 'Khác',     'Wifi trong phòng chập chờn vào buổi tối',    'cho_xu_ly',  '2026-09-28 22:10:00');

SET FOREIGN_KEY_CHECKS = 1;
