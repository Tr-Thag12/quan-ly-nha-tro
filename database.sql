-- =====================================================================
--  CƠ SỞ DỮ LIỆU: HỆ THỐNG QUẢN LÝ NHÀ TRỌ  –  PHIÊN BẢN 1.0
-- ---------------------------------------------------------------------
--  Cách 1 (dễ nhất): mở http://localhost/nhatro/install.php rồi bấm nút.
--  Cách 2: phpMyAdmin > tab Import > chọn file này > bấm Import.
--
--  Tài khoản mẫu (mật khẩu đều là 123456):
--    chutro  – Chủ trọ (toàn quyền)
--    quanly  – Quản lý
--    tuanpm  – Quản lý (dùng để thử khóa tài khoản khi nhập sai 5 lần)
--
--  DỮ LIỆU MẪU LUÔN "MỚI": mọi ngày tháng tính theo ngày cài đặt
--  (@hom_nay = ngày cài, @t = ngày đầu tháng cài). Cài lại vào tháng nào
--  thì hợp đồng, hóa đơn, nợ quá hạn đều như đang diễn ra ở tháng đó.
--  Dữ liệu có sẵn chỉ số và hóa đơn của 12 tháng trước; tháng hiện tại
--  CHƯA ghi chỉ số, để demo ghi điện nước và sinh hóa đơn.
--
--  Lưu ý: chạy lại file này sẽ XÓA dữ liệu cũ và nạp lại dữ liệu mẫu.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS nhatro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE nhatro;

DROP TABLE IF EXISTS su_co_lich_su;
DROP TABLE IF EXISTS su_co;
DROP TABLE IF EXISTS thanh_toan;
DROP TABLE IF EXISTS hoa_don;
DROP TABLE IF EXISTS chi_so;
DROP TABLE IF EXISTS don_gia;
DROP TABLE IF EXISTS nguoi_o;
DROP TABLE IF EXISTS gia_han;
DROP TABLE IF EXISTS hop_dong;
DROP TABLE IF EXISTS khach_thue;
DROP TABLE IF EXISTS phong;
DROP TABLE IF EXISTS tai_khoan;

-- ---------------------------------------------------------------------
--  TÀI KHOẢN  (TK001 – TK005)
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
--  PHÒNG  (PH001 – PH006)
-- ---------------------------------------------------------------------
CREATE TABLE phong (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    ma_phong         VARCHAR(20)  NOT NULL UNIQUE COMMENT 'PH001: mã phòng không được trùng',
    tang             INT          NOT NULL,
    dien_tich        DECIMAL(5,1) NOT NULL COMMENT 'Đơn vị m2',
    gia_thue         INT          NOT NULL COMMENT 'Giá niêm yết VNĐ/tháng; hóa đơn lấy giá chốt trong hợp đồng',
    so_nguoi_toi_da  INT          NOT NULL DEFAULT 2,
    tien_nghi        VARCHAR(255) NULL,
    trang_thai       ENUM('trong', 'dang_thue', 'dang_sua') NOT NULL DEFAULT 'trong',
    ngay_tao         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  KHÁCH THUÊ  (KT001 – KT004)
-- ---------------------------------------------------------------------
CREATE TABLE khach_thue (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    ho_ten         VARCHAR(100) NOT NULL,
    cccd           VARCHAR(12)  NOT NULL UNIQUE COMMENT 'KT001: số CCCD không được trùng',
    so_dien_thoai  VARCHAR(10)  NOT NULL COMMENT 'KT001: đủ 10 chữ số',
    que_quan       VARCHAR(100) NULL,
    anh_cccd       VARCHAR(255) NULL COMMENT 'KT001: đường dẫn ảnh CCCD đã tải lên',
    ngay_tao       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ngay_cap_nhat  DATETIME NULL COMMENT 'KT002: thời điểm sửa gần nhất'
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  HỢP ĐỒNG  (HD001 – HD006)
--  Giá thuê được CHỐT tại lúc ký: hóa đơn lấy giá ở đây, không lấy giá
--  niêm yết trong bảng phong, nên sửa giá phòng không ảnh hưởng dữ liệu cũ.
-- ---------------------------------------------------------------------
CREATE TABLE hop_dong (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    ma_hop_dong       VARCHAR(20) NOT NULL UNIQUE,
    phong_id          INT  NOT NULL,
    khach_thue_id     INT  NOT NULL COMMENT 'Người đứng tên hợp đồng (đại diện)',
    ngay_bat_dau      DATE NOT NULL,
    ngay_ket_thuc     DATE NOT NULL,
    gia_thue          INT  NOT NULL COMMENT 'Giá thuê chốt lúc ký hợp đồng',
    tien_coc          INT  NOT NULL,
    so_xe             INT  NOT NULL DEFAULT 0 COMMENT 'Số xe gửi, dùng tính phí gửi xe',
    chi_so_dien_dau   INT  NOT NULL DEFAULT 0 COMMENT 'Chỉ số điện lúc nhận phòng',
    chi_so_nuoc_dau   INT  NOT NULL DEFAULT 0 COMMENT 'Chỉ số nước lúc nhận phòng',
    trang_thai        ENUM('hieu_luc', 'da_thanh_ly') NOT NULL DEFAULT 'hieu_luc',
    ngay_thanh_ly     DATE NULL,
    tien_coc_hoan     INT  NULL COMMENT 'HD005: số tiền cọc thực hoàn',
    ghi_chu_thanh_ly  VARCHAR(255) NULL,
    ngay_tao          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_hopdong_phong FOREIGN KEY (phong_id) REFERENCES phong (id),
    CONSTRAINT fk_hopdong_khach FOREIGN KEY (khach_thue_id) REFERENCES khach_thue (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- HD004: lịch sử các lần gia hạn hợp đồng
CREATE TABLE gia_han (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    hop_dong_id        INT  NOT NULL,
    ngay_ket_thuc_cu   DATE NOT NULL,
    ngay_ket_thuc_moi  DATE NOT NULL,
    ghi_chu            VARCHAR(255) NULL,
    nguoi_thuc_hien    INT  NULL,
    thoi_diem          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_giahan_hopdong FOREIGN KEY (hop_dong_id) REFERENCES hop_dong (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- KT005, PH004: người ở theo từng hợp đồng. Người đứng tên cũng nằm ở đây
-- với la_dai_dien = 1. Số người trong phòng được ĐẾM từ bảng này.
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
--  ĐIỆN NƯỚC VÀ ĐƠN GIÁ  (DN001 – DN005)
-- ---------------------------------------------------------------------
-- DN004: mỗi mức giá có ngày bắt đầu áp dụng; giá cũ vẫn được giữ lại
CREATE TABLE don_gia (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dich_vu     ENUM('dien', 'nuoc', 'rac', 'wifi', 'gui_xe') NOT NULL,
    don_gia     INT  NOT NULL,
    ap_dung_tu  DATE NOT NULL,
    nguoi_tao   INT  NULL,
    ngay_tao    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dongia_dichvu_ngay (dich_vu, ap_dung_tu)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- DN001: chỉ số công tơ chốt cuối mỗi tháng của từng phòng
CREATE TABLE chi_so (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    phong_id   INT     NOT NULL,
    thang      CHAR(7) NOT NULL COMMENT 'Tháng chốt số, dạng YYYY-MM',
    dien_cu    INT     NOT NULL,
    dien_moi   INT     NOT NULL,
    nuoc_cu    INT     NOT NULL,
    nuoc_moi   INT     NOT NULL,
    ngay_ghi   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nguoi_ghi  INT     NULL,
    ngay_sua   DATETIME NULL COMMENT 'DN003: lần sửa gần nhất',
    UNIQUE KEY uq_chiso_phong_thang (phong_id, thang),
    CONSTRAINT fk_chiso_phong FOREIGN KEY (phong_id) REFERENCES phong (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  HÓA ĐƠN VÀ THU TIỀN  (TT001 – TT007)
--  Hóa đơn lưu lại cả chỉ số và ĐƠN GIÁ tại lúc lập (DN004 – AC2):
--  đổi đơn giá sau này không làm thay đổi hóa đơn cũ.
-- ---------------------------------------------------------------------
CREATE TABLE hoa_don (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ma_hoa_don      VARCHAR(30) NOT NULL UNIQUE,
    hop_dong_id     INT     NOT NULL,
    phong_id        INT     NOT NULL,
    thang           CHAR(7) NOT NULL,
    chi_so_id       INT     NULL,
    tien_phong      INT NOT NULL,
    dien_cu         INT NOT NULL,
    dien_moi        INT NOT NULL,
    don_gia_dien    INT NOT NULL,
    tien_dien       INT NOT NULL,
    nuoc_cu         INT NOT NULL,
    nuoc_moi        INT NOT NULL,
    don_gia_nuoc    INT NOT NULL,
    tien_nuoc       INT NOT NULL,
    so_nguoi        INT NOT NULL,
    don_gia_rac     INT NOT NULL,
    tien_rac        INT NOT NULL,
    don_gia_wifi    INT NOT NULL,
    tien_wifi       INT NOT NULL,
    so_xe           INT NOT NULL,
    don_gia_gui_xe  INT NOT NULL,
    tien_gui_xe     INT NOT NULL,
    tong_tien       INT NOT NULL,
    da_thu          INT NOT NULL DEFAULT 0,
    trang_thai      ENUM('chua_thu', 'thu_mot_phan', 'da_thu') NOT NULL DEFAULT 'chua_thu',
    han_thanh_toan  DATE NOT NULL,
    ngay_tao        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nguoi_tao       INT NULL,
    UNIQUE KEY uq_hoadon_phong_thang (phong_id, thang),
    CONSTRAINT fk_hoadon_hopdong FOREIGN KEY (hop_dong_id) REFERENCES hop_dong (id),
    CONSTRAINT fk_hoadon_phong FOREIGN KEY (phong_id) REFERENCES phong (id),
    CONSTRAINT fk_hoadon_chiso FOREIGN KEY (chi_so_id) REFERENCES chi_so (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- TT003: mỗi lần thu tiền (thu đủ hoặc thu một phần)
CREATE TABLE thanh_toan (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    hoa_don_id  INT  NOT NULL,
    so_tien     INT  NOT NULL,
    ngay_thu    DATE NOT NULL,
    hinh_thuc   ENUM('tien_mat', 'chuyen_khoan', 'tru_coc') NOT NULL DEFAULT 'tien_mat',
    ghi_chu     VARCHAR(255) NULL,
    nguoi_thu   INT  NULL,
    thoi_diem   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_thanhtoan_hoadon FOREIGN KEY (hoa_don_id) REFERENCES hoa_don (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  SỰ CỐ VÀ BẢO TRÌ  (SC001 – SC004)
-- ---------------------------------------------------------------------
CREATE TABLE su_co (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ma_theo_doi     VARCHAR(12)  NOT NULL UNIQUE COMMENT 'SC001: mã theo dõi gửi cho khách',
    phong_id        INT          NOT NULL,
    khach_thue_id   INT          NULL COMMENT 'Người báo; trống nếu quản lý tự ghi nhận',
    loai            VARCHAR(30)  NOT NULL,
    mo_ta           VARCHAR(500) NOT NULL,
    anh             VARCHAR(255) NULL COMMENT 'SC001: ảnh minh họa',
    trang_thai      ENUM('cho_xu_ly', 'dang_xu_ly', 'da_xong') NOT NULL DEFAULT 'cho_xu_ly',
    ngay_bao        DATETIME     NOT NULL,
    ghi_chu_xu_ly   VARCHAR(255) NULL COMMENT 'SC003: bắt buộc khi chuyển sang Đã xong',
    ngay_cap_nhat   DATETIME     NULL,
    chi_phi         INT          NULL COMMENT 'SC004: chi phí sửa chữa',
    ngay_sua        DATE         NULL,
    tho_sua         VARCHAR(100) NULL,
    CONSTRAINT fk_suco_phong FOREIGN KEY (phong_id) REFERENCES phong (id),
    CONSTRAINT fk_suco_khach FOREIGN KEY (khach_thue_id) REFERENCES khach_thue (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- SC003: mỗi lần đổi trạng thái đều ghi lại thời điểm
CREATE TABLE su_co_lich_su (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    su_co_id        INT NOT NULL,
    trang_thai      ENUM('cho_xu_ly', 'dang_xu_ly', 'da_xong') NOT NULL,
    ghi_chu         VARCHAR(255) NULL,
    nguoi_cap_nhat  INT NULL COMMENT 'Trống = khách thuê tự báo',
    thoi_diem       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sucols_suco FOREIGN KEY (su_co_id) REFERENCES su_co (id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================================
--  DỮ LIỆU MẪU
-- =====================================================================

-- Mốc thời gian: ngày cài đặt và ngày đầu tháng cài đặt
SET @hom_nay = CURDATE();
SET @t = DATE_FORMAT(CURDATE(), '%Y-%m-01');

INSERT INTO tai_khoan (id, ho_ten, ten_dang_nhap, mat_khau, so_dien_thoai, vai_tro) VALUES
(1, 'Trần Văn Hùng', 'chutro', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0912345678', 'chu_tro'),
(2, 'Lê Thị Mai', 'quanly', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0987654321', 'quan_ly'),
(3, 'Phạm Minh Tuấn', 'tuanpm', '$2y$12$zjYk7xLejeSbsUuZ3zngHOQdKHBplBC73PgMkYCxObEexklz6B8rK', '0978123456', 'quan_ly');

-- 24 phòng, 3 tầng (có hơn 20 phòng để thấy phân trang ở PH004)
INSERT INTO phong (id, ma_phong, tang, dien_tich, gia_thue, so_nguoi_toi_da, tien_nghi, trang_thai) VALUES
(1, 'P101', 1, 25.0, 3200000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
(2, 'P102', 1, 20.0, 2800000, 3, 'Điều hòa, nóng lạnh, giường, tủ quần áo', 'dang_thue'),
(3, 'P103', 1, 20.0, 2800000, 3, 'Điều hòa, nóng lạnh, giường, tủ quần áo', 'dang_thue'),
(4, 'P104', 1, 22.0, 3000000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
(5, 'P105', 1, 18.0, 2600000, 2, 'Điều hòa, nóng lạnh', 'dang_thue'),
(6, 'P106', 1, 18.0, 2600000, 2, 'Điều hòa, nóng lạnh', 'trong'),
(7, 'P107', 1, 22.0, 3000000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
(8, 'P108', 1, 25.0, 3200000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'trong'),
(9, 'P201', 2, 25.0, 3000000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
(10, 'P202', 2, 20.0, 2600000, 3, 'Điều hòa, nóng lạnh, giường', 'dang_thue'),
(11, 'P203', 2, 20.0, 2600000, 3, 'Điều hòa, nóng lạnh, giường', 'trong'),
(12, 'P204', 2, 22.0, 2800000, 3, 'Điều hòa, nóng lạnh, gác xép', 'dang_thue'),
(13, 'P205', 2, 18.0, 2400000, 2, 'Nóng lạnh, gác xép', 'dang_thue'),
(14, 'P206', 2, 18.0, 2400000, 2, 'Nóng lạnh, gác xép', 'dang_thue'),
(15, 'P207', 2, 22.0, 2800000, 3, 'Điều hòa, nóng lạnh, gác xép', 'trong'),
(16, 'P208', 2, 25.0, 3000000, 4, 'Điều hòa, nóng lạnh, giường, tủ quần áo, ban công', 'dang_thue'),
(17, 'P301', 3, 25.0, 2700000, 4, 'Điều hòa, nóng lạnh, ban công', 'dang_thue'),
(18, 'P302', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'trong'),
(19, 'P303', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'dang_thue'),
(20, 'P304', 3, 16.0, 2000000, 2, 'Nóng lạnh', 'dang_sua'),
(21, 'P305', 3, 16.0, 2000000, 2, 'Nóng lạnh', 'trong'),
(22, 'P306', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'dang_thue'),
(23, 'P307', 3, 20.0, 2400000, 3, 'Điều hòa, nóng lạnh', 'trong'),
(24, 'P308', 3, 25.0, 2700000, 4, 'Điều hòa, nóng lạnh, ban công', 'dang_sua');

-- 35 khách thuê: 31 người đang ở và 4 khách cũ đã trả phòng
INSERT INTO khach_thue (id, ho_ten, cccd, so_dien_thoai, que_quan) VALUES
(1, 'Nguyễn Văn Nam', '036203004521', '0912487356', 'Nam Định'),
(2, 'Trần Thị Hương', '034302001873', '0986215734', 'Thái Bình'),
(3, 'Lê Minh Đức', '038204006157', '0356781249', 'Thanh Hóa'),
(4, 'Phạm Thu Trang', '031303002468', '0967342185', 'Hải Phòng'),
(5, 'Hoàng Quốc Bảo', '040202007315', '0839126457', 'Nghệ An'),
(6, 'Vũ Thị Lan', '030305004692', '0971538624', 'Hải Dương'),
(7, 'Đặng Minh Quân', '001201008934', '0915672348', 'Hà Nội'),
(8, 'Bùi Thị Ngọc', '035304003187', '0348916275', 'Hà Nam'),
(9, 'Đỗ Văn Tùng', '037203005826', '0987451362', 'Ninh Bình'),
(10, 'Hồ Thị Thảo', '042303001459', '0962874513', 'Hà Tĩnh'),
(11, 'Ngô Minh Khoa', '027204002736', '0376159842', 'Bắc Ninh'),
(12, 'Dương Thị Hà', '026305006381', '0918734625', 'Vĩnh Phúc'),
(13, 'Lý Văn Phúc', '024206004158', '0826451397', 'Bắc Giang'),
(14, 'Mai Thị Nhung', '033304007264', '0945128736', 'Hưng Yên'),
(15, 'Trịnh Quang Huy', '025203009513', '0397264815', 'Phú Thọ'),
(16, 'Phan Văn Lợi', '019201003647', '0983561274', 'Thái Nguyên'),
(17, 'Cao Thị Diệu', '022302005918', '0357482196', 'Quảng Ninh'),
(18, 'Đinh Công Minh', '036202001285', '0916357842', 'Nam Định'),
(19, 'Tạ Thị Kim', '034301008462', '0974825163', 'Thái Bình'),
(20, 'Nguyễn Thị Hồng', '036304002917', '0965318472', 'Nam Định'),
(21, 'Trần Văn Khải', '036203007462', '0913764205', 'Nam Định'),
(22, 'Lê Thị Thu', '034303005184', '0387219546', 'Thái Bình'),
(23, 'Phạm Đức Anh', '031204003659', '0934682157', 'Hải Phòng'),
(24, 'Vũ Thị Mai', '030304008127', '0978245163', 'Hải Dương'),
(25, 'Vũ Minh Hiếu', '030206001538', '0356917284', 'Hải Dương'),
(26, 'Đặng Thu Hà', '001303006274', '0916473829', 'Hà Nội'),
(27, 'Nguyễn Hữu Lộc', '001202004819', '0988152736', 'Hà Nội'),
(28, 'Trần Quốc Việt', '001204009361', '0397425168', 'Hà Nội'),
(29, 'Bùi Văn Hải', '035203002746', '0945813627', 'Hà Nam'),
(30, 'Đỗ Thị Hạnh', '037304005293', '0869274531', 'Ninh Bình'),
(31, 'Hồ Văn Dũng', '042204007815', '0917538264', 'Hà Tĩnh'),
(32, 'Dương Minh Tâm', '026203003492', '0326841957', 'Vĩnh Phúc'),
(33, 'Lê Thị Quỳnh', '026304008651', '0976135284', 'Vĩnh Phúc'),
(34, 'Lý Thị Hoa', '024305002374', '0843627195', 'Bắc Giang'),
(35, 'Trịnh Thị Lan Anh', '025304006128', '0962751843', 'Phú Thọ');

-- DN004: đơn giá dịch vụ. Giá điện tăng từ 3.500 lên 3.800 đ/kWh từ 3 tháng trước:
-- hóa đơn các tháng trước đó vẫn giữ giá cũ.
INSERT INTO don_gia (id, dich_vu, don_gia, ap_dung_tu, nguoi_tao) VALUES
(1, 'dien', 3500, @t - INTERVAL 24 MONTH, 1),
(2, 'nuoc', 25000, @t - INTERVAL 24 MONTH, 1),
(3, 'rac', 20000, @t - INTERVAL 24 MONTH, 1),
(4, 'wifi', 100000, @t - INTERVAL 24 MONTH, 1),
(5, 'gui_xe', 100000, @t - INTERVAL 24 MONTH, 1),
(6, 'dien', 3800, @t - INTERVAL 3 MONTH, 1);

-- 19 hợp đồng: 4 đã thanh lý, 15 còn hiệu lực. HD-0005 và HD-0006 luôn sắp hết hạn
-- (trong 30 ngày tới) để demo HD006; HD-0019 mới ký đầu tháng này nên chưa có hóa đơn.
INSERT INTO hop_dong (id, ma_hop_dong, phong_id, khach_thue_id, ngay_bat_dau, ngay_ket_thuc, gia_thue, tien_coc,
                      so_xe, chi_so_dien_dau, chi_so_nuoc_dau, trang_thai, ngay_thanh_ly, tien_coc_hoan,
                      ghi_chu_thanh_ly, ngay_tao) VALUES
(1, 'HD-0001', 1, 16, @t - INTERVAL 24 MONTH, @t - INTERVAL 12 MONTH - INTERVAL 1 DAY, 3000000, 3000000, 1, 1497, 173, 'da_thanh_ly', @t - INTERVAL 12 MONTH - INTERVAL 1 DAY, 3000000, 'Khách chuyển chỗ làm, trả phòng đúng hạn', TIMESTAMP(@t - INTERVAL 24 MONTH, '09:00:00')),
(2, 'HD-0002', 13, 19, @t - INTERVAL 23 MONTH, @t - INTERVAL 11 MONTH - INTERVAL 1 DAY, 2200000, 2200000, 1, 1147, 246, 'da_thanh_ly', @t - INTERVAL 11 MONTH - INTERVAL 1 DAY, 2200000, 'Hết hạn hợp đồng, khách không gia hạn', TIMESTAMP(@t - INTERVAL 23 MONTH, '09:00:00')),
(3, 'HD-0003', 6, 17, @t - INTERVAL 20 MONTH, @t - INTERVAL 2 MONTH - INTERVAL 1 DAY, 2500000, 2500000, 1, 2953, 156, 'da_thanh_ly', @t - INTERVAL 2 MONTH - INTERVAL 1 DAY, 1914000, 'Trừ tiền điện nước tháng cuối vào tiền cọc, hoàn phần còn lại', TIMESTAMP(@t - INTERVAL 20 MONTH, '09:00:00')),
(4, 'HD-0004', 11, 18, @t - INTERVAL 18 MONTH, @t - INTERVAL 1 MONTH - INTERVAL 1 DAY, 2500000, 2500000, 0, 2906, 263, 'da_thanh_ly', @t - INTERVAL 1 MONTH - INTERVAL 1 DAY, 2500000, 'Khách về quê làm việc', TIMESTAMP(@t - INTERVAL 18 MONTH, '09:00:00')),
(5, 'HD-0005', 9, 7, @t - INTERVAL 11 MONTH, @hom_nay + INTERVAL 12 DAY, 3000000, 3000000, 3, 2955, 309, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 11 MONTH, '09:00:00')),
(6, 'HD-0006', 1, 1, @t - INTERVAL 11 MONTH, @t + INTERVAL 1 MONTH - INTERVAL 1 DAY, 3200000, 3200000, 2, 1419, 176, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 11 MONTH, '09:00:00')),
(7, 'HD-0007', 13, 10, @t - INTERVAL 10 MONTH, @t + INTERVAL 8 MONTH - INTERVAL 1 DAY, 2400000, 2400000, 1, 2532, 349, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 10 MONTH, '09:00:00')),
(8, 'HD-0008', 4, 4, @t - INTERVAL 9 MONTH, @t + INTERVAL 3 MONTH - INTERVAL 1 DAY, 3000000, 3000000, 2, 963, 174, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 9 MONTH, '09:00:00')),
(9, 'HD-0009', 2, 2, @t - INTERVAL 8 MONTH, @t + INTERVAL 4 MONTH - INTERVAL 1 DAY, 2800000, 2800000, 1, 3092, 235, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 8 MONTH, '09:00:00')),
(10, 'HD-0010', 19, 14, @t - INTERVAL 8 MONTH, @t + INTERVAL 4 MONTH - INTERVAL 1 DAY, 2400000, 2400000, 1, 3745, 368, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 8 MONTH, '09:00:00')),
(11, 'HD-0011', 10, 8, @t - INTERVAL 7 MONTH, @t + INTERVAL 5 MONTH - INTERVAL 1 DAY, 2600000, 2600000, 2, 1449, 331, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 7 MONTH, '09:00:00')),
(12, 'HD-0012', 3, 3, @t - INTERVAL 6 MONTH, @t + INTERVAL 6 MONTH - INTERVAL 1 DAY, 2800000, 2800000, 1, 2385, 164, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 6 MONTH, '09:00:00')),
(13, 'HD-0013', 22, 15, @t - INTERVAL 6 MONTH, @t + INTERVAL 6 MONTH - INTERVAL 1 DAY, 2400000, 2400000, 1, 2391, 243, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 6 MONTH, '09:00:00')),
(14, 'HD-0014', 12, 9, @t - INTERVAL 5 MONTH, @t + INTERVAL 7 MONTH - INTERVAL 1 DAY, 2800000, 2800000, 2, 1053, 130, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 5 MONTH, '09:00:00')),
(15, 'HD-0015', 14, 11, @t - INTERVAL 4 MONTH, @t + INTERVAL 3 MONTH - INTERVAL 1 DAY, 2400000, 2400000, 1, 2285, 407, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 4 MONTH, '09:00:00')),
(16, 'HD-0016', 5, 5, @t - INTERVAL 3 MONTH, @t + INTERVAL 9 MONTH - INTERVAL 1 DAY, 2600000, 2600000, 1, 4178, 334, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 3 MONTH, '09:00:00')),
(17, 'HD-0017', 16, 12, @t - INTERVAL 2 MONTH, @t + INTERVAL 10 MONTH - INTERVAL 1 DAY, 3000000, 3000000, 2, 2615, 380, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 2 MONTH, '09:00:00')),
(18, 'HD-0018', 7, 6, @t - INTERVAL 1 MONTH, @t + INTERVAL 11 MONTH - INTERVAL 1 DAY, 3000000, 3000000, 3, 1602, 389, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t - INTERVAL 1 MONTH, '09:00:00')),
(19, 'HD-0019', 17, 13, @t, @t + INTERVAL 12 MONTH - INTERVAL 1 DAY, 2700000, 2700000, 2, 1916, 138, 'hieu_luc', NULL, NULL, NULL, TIMESTAMP(@t, '09:00:00'));

-- HD004: HD-0007 ban đầu ký 12 tháng, đã gia hạn thêm 6 tháng
INSERT INTO gia_han (id, hop_dong_id, ngay_ket_thuc_cu, ngay_ket_thuc_moi, ghi_chu, nguoi_thuc_hien, thoi_diem) VALUES
(1, 7, @t + INTERVAL 2 MONTH - INTERVAL 1 DAY, @t + INTERVAL 8 MONTH - INTERVAL 1 DAY, 'Khách muốn ở thêm 6 tháng', 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 14 DAY, '10:20:00'));

-- Người ở theo từng hợp đồng (người đứng tên có la_dai_dien = 1)
INSERT INTO nguoi_o (id, hop_dong_id, khach_thue_id, la_dai_dien, ngay_vao, ngay_roi) VALUES
(1, 1, 16, 1, @t - INTERVAL 24 MONTH, @t - INTERVAL 12 MONTH - INTERVAL 1 DAY),
(2, 2, 19, 1, @t - INTERVAL 23 MONTH, @t - INTERVAL 11 MONTH - INTERVAL 1 DAY),
(3, 3, 17, 1, @t - INTERVAL 20 MONTH, @t - INTERVAL 2 MONTH - INTERVAL 1 DAY),
(4, 4, 18, 1, @t - INTERVAL 18 MONTH, @t - INTERVAL 1 MONTH - INTERVAL 1 DAY),
(5, 5, 7, 1, @t - INTERVAL 11 MONTH, NULL),
(6, 5, 26, 0, @t - INTERVAL 11 MONTH, NULL),
(7, 5, 27, 0, @t - INTERVAL 10 MONTH + INTERVAL 9 DAY, NULL),
(8, 5, 28, 0, @t - INTERVAL 6 MONTH + INTERVAL 14 DAY, NULL),
(9, 6, 1, 1, @t - INTERVAL 11 MONTH, NULL),
(10, 6, 20, 0, @t - INTERVAL 11 MONTH, NULL),
(11, 6, 21, 0, @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, NULL),
(12, 7, 10, 1, @t - INTERVAL 10 MONTH, NULL),
(13, 7, 31, 0, @t - INTERVAL 8 MONTH + INTERVAL 4 DAY, NULL),
(14, 8, 4, 1, @t - INTERVAL 9 MONTH, NULL),
(15, 8, 23, 0, @t - INTERVAL 9 MONTH, NULL),
(16, 9, 2, 1, @t - INTERVAL 8 MONTH, NULL),
(17, 9, 22, 0, @t - INTERVAL 8 MONTH, NULL),
(18, 10, 14, 1, @t - INTERVAL 8 MONTH, NULL),
(19, 11, 8, 1, @t - INTERVAL 7 MONTH, NULL),
(20, 11, 29, 0, @t - INTERVAL 7 MONTH, NULL),
(21, 12, 3, 1, @t - INTERVAL 6 MONTH, NULL),
(22, 13, 15, 1, @t - INTERVAL 6 MONTH, NULL),
(23, 13, 35, 0, @t - INTERVAL 5 MONTH + INTERVAL 19 DAY, NULL),
(24, 14, 9, 1, @t - INTERVAL 5 MONTH, NULL),
(25, 14, 30, 0, @t - INTERVAL 5 MONTH, NULL),
(26, 15, 11, 1, @t - INTERVAL 4 MONTH, NULL),
(27, 16, 5, 1, @t - INTERVAL 3 MONTH, NULL),
(28, 17, 12, 1, @t - INTERVAL 2 MONTH, NULL),
(29, 17, 32, 0, @t - INTERVAL 2 MONTH, NULL),
(30, 17, 33, 0, @t - INTERVAL 1 MONTH + INTERVAL 14 DAY, NULL),
(31, 18, 6, 1, @t - INTERVAL 1 MONTH, NULL),
(32, 18, 24, 0, @t - INTERVAL 1 MONTH, NULL),
(33, 18, 25, 0, @t - INTERVAL 1 MONTH, NULL),
(34, 19, 13, 1, @t, NULL),
(35, 19, 34, 0, @t, NULL);

-- ---------------------------------------------------------------------
--  12 THÁNG LỊCH SỬ: chỉ số điện nước, hóa đơn và các lần thu tiền.
--  Được sinh tự động bằng đúng công thức tính tiền của includes/hoa_don.php.
--  P101 có tháng tiền nước tăng vọt (vòi sen rò rỉ, xem sự cố SC-M3N7RW);
--  P303 nợ 2 tháng, P206 mới trả một phần: dùng để demo nhắc nợ và công nợ.
-- ---------------------------------------------------------------------
INSERT INTO chi_so (id, phong_id, thang, dien_cu, dien_moi, nuoc_cu, nuoc_moi, ngay_ghi, nguoi_ghi) VALUES
(1, 13, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 2467, 2522, 345, 349, TIMESTAMP(@t - INTERVAL 11 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(2, 6, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 3913, 3984, 228, 232, TIMESTAMP(@t - INTERVAL 11 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(3, 11, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 3626, 3701, 317, 321, TIMESTAMP(@t - INTERVAL 11 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(4, 6, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 3984, 4057, 232, 236, TIMESTAMP(@t - INTERVAL 10 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(5, 11, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 3701, 3776, 321, 325, TIMESTAMP(@t - INTERVAL 10 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(6, 9, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 2955, 3076, 309, 316, TIMESTAMP(@t - INTERVAL 10 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(7, 1, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 1419, 1535, 176, 183, TIMESTAMP(@t - INTERVAL 10 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(8, 6, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 4057, 4125, 236, 240, TIMESTAMP(@t - INTERVAL 9 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(9, 11, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 3776, 3851, 325, 329, TIMESTAMP(@t - INTERVAL 9 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(10, 9, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 3076, 3229, 316, 326, TIMESTAMP(@t - INTERVAL 9 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(11, 1, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 1535, 1654, 183, 190, TIMESTAMP(@t - INTERVAL 9 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(12, 13, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 2532, 2590, 349, 353, TIMESTAMP(@t - INTERVAL 9 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(13, 6, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 4125, 4194, 240, 244, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(14, 11, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 3851, 3926, 329, 333, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(15, 9, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 3229, 3376, 326, 336, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(16, 1, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 1654, 1774, 190, 197, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(17, 13, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 2590, 2650, 353, 357, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(18, 4, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 963, 1077, 174, 181, TIMESTAMP(@t - INTERVAL 8 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(19, 6, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 4194, 4263, 244, 248, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(20, 11, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 3926, 4001, 333, 337, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(21, 9, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 3376, 3523, 336, 346, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(22, 1, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 1774, 1892, 197, 204, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(23, 13, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 2650, 2738, 357, 364, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(24, 4, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 1077, 1190, 181, 188, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(25, 2, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 3092, 3202, 235, 242, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(26, 19, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 3745, 3823, 368, 372, TIMESTAMP(@t - INTERVAL 7 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(27, 6, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 4263, 4337, 248, 252, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(28, 11, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 4001, 4076, 337, 341, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(29, 9, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 3523, 3674, 346, 356, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(30, 1, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 1892, 2037, 204, 214, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(31, 13, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 2738, 2829, 364, 371, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(32, 4, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 1190, 1305, 188, 195, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(33, 2, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 3202, 3308, 242, 249, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(34, 19, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 3823, 3901, 372, 376, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(35, 10, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 1449, 1561, 331, 338, TIMESTAMP(@t - INTERVAL 6 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(36, 6, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 4337, 4406, 252, 256, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(37, 11, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 4076, 4150, 341, 345, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(38, 9, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 3674, 3859, 356, 369, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(39, 1, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 2037, 2181, 214, 224, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(40, 13, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 2829, 2920, 371, 378, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(41, 4, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 1305, 1415, 195, 202, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(42, 2, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 3308, 3420, 249, 256, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(43, 19, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 3901, 3975, 376, 380, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(44, 10, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 1561, 1665, 338, 345, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(45, 3, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 2385, 2458, 164, 168, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(46, 22, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 2391, 2467, 243, 247, TIMESTAMP(@t - INTERVAL 5 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(47, 6, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 4406, 4480, 256, 260, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(48, 11, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 4150, 4222, 345, 349, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(49, 9, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 3859, 4052, 369, 382, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(50, 1, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 2181, 2337, 224, 234, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(51, 13, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 2920, 3013, 378, 385, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(52, 4, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 1415, 1523, 202, 209, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(53, 2, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 3420, 3529, 256, 263, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(54, 19, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 3975, 4047, 380, 384, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(55, 10, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 1665, 1776, 345, 352, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(56, 3, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 2458, 2536, 168, 172, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(57, 22, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 2467, 2577, 247, 254, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(58, 12, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 1053, 1160, 130, 137, TIMESTAMP(@t - INTERVAL 4 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(59, 6, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 4480, 4555, 260, 264, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(60, 11, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 4222, 4299, 349, 353, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(61, 9, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 4052, 4235, 382, 395, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(62, 1, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 2337, 2493, 234, 253, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(63, 13, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 3013, 3108, 385, 392, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(64, 4, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 1523, 1635, 209, 216, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(65, 2, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 3529, 3639, 263, 270, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(66, 19, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 4047, 4122, 384, 388, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(67, 10, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 1776, 1882, 352, 359, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(68, 3, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 2536, 2610, 172, 176, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(69, 22, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 2577, 2685, 254, 261, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(70, 12, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 1160, 1275, 137, 144, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(71, 14, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 2285, 2339, 407, 411, TIMESTAMP(@t - INTERVAL 3 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(72, 6, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 4555, 4625, 264, 268, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(73, 11, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 4299, 4378, 353, 357, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(74, 9, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 4235, 4421, 395, 408, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(75, 1, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 2493, 2639, 253, 263, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(76, 13, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 3108, 3201, 392, 399, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(77, 4, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 1635, 1742, 216, 223, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(78, 2, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 3639, 3749, 270, 277, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(79, 19, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 4122, 4196, 388, 392, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(80, 10, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 1882, 1988, 359, 366, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(81, 3, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 2610, 2683, 176, 180, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(82, 22, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 2685, 2792, 261, 268, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(83, 12, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 1275, 1386, 144, 151, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(84, 14, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 2339, 2394, 411, 415, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(85, 5, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 4178, 4249, 334, 338, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(86, 11, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 4378, 4453, 357, 361, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(87, 9, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 4421, 4612, 408, 421, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(88, 1, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 2639, 2795, 263, 273, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(89, 13, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 3201, 3290, 399, 406, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(90, 4, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 1742, 1858, 223, 230, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(91, 2, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 3749, 3858, 277, 284, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(92, 19, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 4196, 4268, 392, 396, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(93, 10, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 1988, 2099, 366, 373, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(94, 3, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 2683, 2754, 180, 184, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(95, 22, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 2792, 2901, 268, 275, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(96, 12, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 1386, 1496, 151, 158, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(97, 14, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 2394, 2450, 415, 419, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(98, 5, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 4249, 4322, 338, 342, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(99, 16, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 2615, 2727, 380, 387, TIMESTAMP(@t - INTERVAL 1 MONTH - INTERVAL 1 DAY, '19:30:00'), 2),
(100, 9, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 4612, 4788, 421, 434, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(101, 1, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2795, 2947, 273, 283, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(102, 13, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 3290, 3385, 406, 413, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(103, 4, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 1858, 1966, 230, 237, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(104, 2, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 3858, 3970, 284, 291, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(105, 19, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 4268, 4347, 396, 400, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(106, 10, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2099, 2213, 373, 380, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(107, 3, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2754, 2830, 184, 188, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(108, 22, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2901, 3005, 275, 282, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(109, 12, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 1496, 1609, 158, 165, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(110, 14, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2450, 2507, 419, 423, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(111, 5, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 4322, 4395, 342, 346, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(112, 16, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 2727, 2877, 387, 397, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2),
(113, 7, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 1602, 1748, 389, 399, TIMESTAMP(@t - INTERVAL 1 DAY, '19:30:00'), 2);

INSERT INTO hoa_don (id, ma_hoa_don, hop_dong_id, phong_id, thang, chi_so_id, tien_phong,
                     dien_cu, dien_moi, don_gia_dien, tien_dien, nuoc_cu, nuoc_moi, don_gia_nuoc, tien_nuoc,
                     so_nguoi, don_gia_rac, tien_rac, don_gia_wifi, tien_wifi, so_xe, don_gia_gui_xe, tien_gui_xe,
                     tong_tien, da_thu, trang_thai, han_thanh_toan, ngay_tao, nguoi_tao) VALUES
(1, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y%m')), 2, 13, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 1, 2200000, 2467, 2522, 3500, 192500, 345, 349, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2712500, 2712500, 'da_thu', @t - INTERVAL 11 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 11 MONTH, '08:15:00'), 2),
(2, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 2, 2500000, 3913, 3984, 3500, 248500, 228, 232, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3068500, 3068500, 'da_thu', @t - INTERVAL 11 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 11 MONTH, '08:15:00'), 2),
(3, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 12 MONTH, '%Y-%m'), 3, 2500000, 3626, 3701, 3500, 262500, 317, 321, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 11 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 11 MONTH, '08:15:00'), 2),
(4, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 4, 2500000, 3984, 4057, 3500, 255500, 232, 236, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3075500, 3075500, 'da_thu', @t - INTERVAL 10 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 10 MONTH, '08:15:00'), 2),
(5, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 5, 2500000, 3701, 3776, 3500, 262500, 321, 325, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 10 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 10 MONTH, '08:15:00'), 2),
(6, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 6, 3000000, 2955, 3076, 3500, 423500, 309, 316, 25000, 175000, 2, 20000, 40000, 100000, 100000, 3, 100000, 300000, 4038500, 4038500, 'da_thu', @t - INTERVAL 10 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 10 MONTH, '08:15:00'), 2),
(7, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 11 MONTH, '%Y-%m'), 7, 3200000, 1419, 1535, 3500, 406000, 176, 183, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 4121000, 4121000, 'da_thu', @t - INTERVAL 10 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 10 MONTH, '08:15:00'), 2),
(8, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 8, 2500000, 4057, 4125, 3500, 238000, 236, 240, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3058000, 3058000, 'da_thu', @t - INTERVAL 9 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 9 MONTH, '08:15:00'), 2),
(9, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 9, 2500000, 3776, 3851, 3500, 262500, 325, 329, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 9 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 9 MONTH, '08:15:00'), 2),
(10, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 10, 3000000, 3076, 3229, 3500, 535500, 316, 326, 25000, 250000, 3, 20000, 60000, 100000, 100000, 3, 100000, 300000, 4245500, 4245500, 'da_thu', @t - INTERVAL 9 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 9 MONTH, '08:15:00'), 2),
(11, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 11, 3200000, 1535, 1654, 3500, 416500, 183, 190, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 4131500, 4131500, 'da_thu', @t - INTERVAL 9 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 9 MONTH, '08:15:00'), 2),
(12, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 10 MONTH, '%Y-%m'), 12, 2400000, 2532, 2590, 3500, 203000, 349, 353, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2923000, 2923000, 'da_thu', @t - INTERVAL 9 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 9 MONTH, '08:15:00'), 2),
(13, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 13, 2500000, 4125, 4194, 3500, 241500, 240, 244, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3061500, 3061500, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(14, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 14, 2500000, 3851, 3926, 3500, 262500, 329, 333, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(15, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 15, 3000000, 3229, 3376, 3500, 514500, 326, 336, 25000, 250000, 3, 20000, 60000, 100000, 100000, 3, 100000, 300000, 4224500, 4224500, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(16, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 16, 3200000, 1654, 1774, 3500, 420000, 190, 197, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 4135000, 4135000, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(17, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 17, 2400000, 2590, 2650, 3500, 210000, 353, 357, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2930000, 2930000, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(18, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 9 MONTH, '%Y-%m'), 18, 3000000, 963, 1077, 3500, 399000, 174, 181, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3914000, 3914000, 'da_thu', @t - INTERVAL 8 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 8 MONTH, '08:15:00'), 2),
(19, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 19, 2500000, 4194, 4263, 3500, 241500, 244, 248, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3061500, 3061500, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(20, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 20, 2500000, 3926, 4001, 3500, 262500, 333, 337, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(21, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 21, 3000000, 3376, 3523, 3500, 514500, 336, 346, 25000, 250000, 3, 20000, 60000, 100000, 100000, 3, 100000, 300000, 4224500, 4224500, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(22, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 22, 3200000, 1774, 1892, 3500, 413000, 197, 204, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 4128000, 4128000, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(23, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 23, 2400000, 2650, 2738, 3500, 308000, 357, 364, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3123000, 3123000, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(24, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 24, 3000000, 1077, 1190, 3500, 395500, 181, 188, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3910500, 3910500, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(25, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 25, 2800000, 3092, 3202, 3500, 385000, 235, 242, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3600000, 3600000, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(26, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 8 MONTH, '%Y-%m'), 26, 2400000, 3745, 3823, 3500, 273000, 368, 372, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2993000, 2993000, 'da_thu', @t - INTERVAL 7 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 7 MONTH, '08:15:00'), 2),
(27, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 27, 2500000, 4263, 4337, 3500, 259000, 248, 252, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3079000, 3079000, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(28, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 28, 2500000, 4001, 4076, 3500, 262500, 337, 341, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2982500, 2982500, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(29, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 29, 3000000, 3523, 3674, 3500, 528500, 346, 356, 25000, 250000, 3, 20000, 60000, 100000, 100000, 3, 100000, 300000, 4238500, 4238500, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(30, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 30, 3200000, 1892, 2037, 3500, 507500, 204, 214, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4317500, 4317500, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(31, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 31, 2400000, 2738, 2829, 3500, 318500, 364, 371, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3133500, 3133500, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(32, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 32, 3000000, 1190, 1305, 3500, 402500, 188, 195, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3917500, 3917500, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(33, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 33, 2800000, 3202, 3308, 3500, 371000, 242, 249, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3586000, 3586000, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(34, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 34, 2400000, 3823, 3901, 3500, 273000, 372, 376, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2993000, 2993000, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(35, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 7 MONTH, '%Y-%m'), 35, 2600000, 1449, 1561, 3500, 392000, 331, 338, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3507000, 3507000, 'da_thu', @t - INTERVAL 6 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 6 MONTH, '08:15:00'), 2),
(36, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 36, 2500000, 4337, 4406, 3500, 241500, 252, 256, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3061500, 3061500, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(37, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 37, 2500000, 4076, 4150, 3500, 259000, 341, 345, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2979000, 2979000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(38, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 38, 3000000, 3674, 3859, 3500, 647500, 356, 369, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4452500, 4452500, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(39, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 39, 3200000, 2037, 2181, 3500, 504000, 214, 224, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4314000, 4314000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(40, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 40, 2400000, 2829, 2920, 3500, 318500, 371, 378, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3133500, 3133500, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(41, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 41, 3000000, 1305, 1415, 3500, 385000, 195, 202, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3900000, 3900000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(42, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 42, 2800000, 3308, 3420, 3500, 392000, 249, 256, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3607000, 3607000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(43, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 43, 2400000, 3901, 3975, 3500, 259000, 376, 380, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2979000, 2979000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(44, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 44, 2600000, 1561, 1665, 3500, 364000, 338, 345, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3479000, 3479000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(45, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 45, 2800000, 2385, 2458, 3500, 255500, 164, 168, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3375500, 3375500, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(46, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 6 MONTH, '%Y-%m'), 46, 2400000, 2391, 2467, 3500, 266000, 243, 247, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2986000, 2986000, 'da_thu', @t - INTERVAL 5 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 5 MONTH, '08:15:00'), 2),
(47, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 47, 2500000, 4406, 4480, 3500, 259000, 256, 260, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3079000, 3079000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(48, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 48, 2500000, 4150, 4222, 3500, 252000, 345, 349, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2972000, 2972000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(49, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 49, 3000000, 3859, 4052, 3500, 675500, 369, 382, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4480500, 4480500, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(50, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 50, 3200000, 2181, 2337, 3500, 546000, 224, 234, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4356000, 4356000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(51, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 51, 2400000, 2920, 3013, 3500, 325500, 378, 385, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3140500, 3140500, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(52, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 52, 3000000, 1415, 1523, 3500, 378000, 202, 209, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3893000, 3893000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(53, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 53, 2800000, 3420, 3529, 3500, 381500, 256, 263, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3596500, 3596500, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(54, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 54, 2400000, 3975, 4047, 3500, 252000, 380, 384, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2972000, 2972000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(55, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 55, 2600000, 1665, 1776, 3500, 388500, 345, 352, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3503500, 3503500, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(56, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 56, 2800000, 2458, 2536, 3500, 273000, 168, 172, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3393000, 3393000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(57, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 57, 2400000, 2467, 2577, 3500, 385000, 247, 254, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3200000, 3200000, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(58, CONCAT('P204-', DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y%m')), 14, 12, DATE_FORMAT(@t - INTERVAL 5 MONTH, '%Y-%m'), 58, 2800000, 1053, 1160, 3500, 374500, 130, 137, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3689500, 3689500, 'da_thu', @t - INTERVAL 4 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 4 MONTH, '08:15:00'), 2),
(59, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 59, 2500000, 4480, 4555, 3500, 262500, 260, 264, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3082500, 3082500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(60, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 60, 2500000, 4222, 4299, 3500, 269500, 349, 353, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 2989500, 2989500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(61, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 61, 3000000, 4052, 4235, 3500, 640500, 382, 395, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4445500, 4445500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(62, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 62, 3200000, 2337, 2493, 3500, 546000, 234, 253, 25000, 475000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4581000, 4581000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(63, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 63, 2400000, 3013, 3108, 3500, 332500, 385, 392, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3147500, 3147500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(64, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 64, 3000000, 1523, 1635, 3500, 392000, 209, 216, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3907000, 3907000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(65, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 65, 2800000, 3529, 3639, 3500, 385000, 263, 270, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3600000, 3600000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(66, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 66, 2400000, 4047, 4122, 3500, 262500, 384, 388, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2982500, 2982500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(67, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 67, 2600000, 1776, 1882, 3500, 371000, 352, 359, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3486000, 3486000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(68, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 68, 2800000, 2536, 2610, 3500, 259000, 172, 176, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3379000, 3379000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(69, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 69, 2400000, 2577, 2685, 3500, 378000, 254, 261, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3193000, 3193000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(70, CONCAT('P204-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 14, 12, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 70, 2800000, 1160, 1275, 3500, 402500, 137, 144, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3717500, 3717500, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(71, CONCAT('P206-', DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y%m')), 15, 14, DATE_FORMAT(@t - INTERVAL 4 MONTH, '%Y-%m'), 71, 2400000, 2285, 2339, 3500, 189000, 407, 411, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2909000, 2909000, 'da_thu', @t - INTERVAL 3 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 3 MONTH, '08:15:00'), 2),
(72, CONCAT('P106-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 3, 6, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 72, 2500000, 4555, 4625, 3800, 266000, 264, 268, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3086000, 3086000, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(73, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 73, 2500000, 4299, 4378, 3800, 300200, 353, 357, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 3020200, 3020200, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(74, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 74, 3000000, 4235, 4421, 3800, 706800, 395, 408, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4511800, 4511800, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(75, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 75, 3200000, 2493, 2639, 3800, 554800, 253, 263, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4364800, 4364800, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(76, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 76, 2400000, 3108, 3201, 3800, 353400, 392, 399, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3168400, 3168400, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(77, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 77, 3000000, 1635, 1742, 3800, 406600, 216, 223, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3921600, 3921600, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(78, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 78, 2800000, 3639, 3749, 3800, 418000, 270, 277, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3633000, 3633000, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(79, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 79, 2400000, 4122, 4196, 3800, 281200, 388, 392, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3001200, 3001200, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(80, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 80, 2600000, 1882, 1988, 3800, 402800, 359, 366, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3517800, 3517800, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(81, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 81, 2800000, 2610, 2683, 3800, 277400, 176, 180, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3397400, 3397400, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(82, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 82, 2400000, 2685, 2792, 3800, 406600, 261, 268, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3221600, 3221600, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(83, CONCAT('P204-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 14, 12, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 83, 2800000, 1275, 1386, 3800, 421800, 144, 151, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3736800, 3736800, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(84, CONCAT('P206-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 15, 14, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 84, 2400000, 2339, 2394, 3800, 209000, 411, 415, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2929000, 2929000, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(85, CONCAT('P105-', DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y%m')), 16, 5, DATE_FORMAT(@t - INTERVAL 3 MONTH, '%Y-%m'), 85, 2600000, 4178, 4249, 3800, 269800, 334, 338, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3189800, 3189800, 'da_thu', @t - INTERVAL 2 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 2 MONTH, '08:15:00'), 2),
(86, CONCAT('P203-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 4, 11, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 86, 2500000, 4378, 4453, 3800, 285000, 357, 361, 25000, 100000, 1, 20000, 20000, 100000, 100000, 0, 100000, 0, 3005000, 3005000, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(87, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 87, 3000000, 4421, 4612, 3800, 725800, 408, 421, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4530800, 4530800, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(88, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 88, 3200000, 2639, 2795, 3800, 592800, 263, 273, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4402800, 4402800, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(89, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 89, 2400000, 3201, 3290, 3800, 338200, 399, 406, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3153200, 3153200, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(90, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 90, 3000000, 1742, 1858, 3800, 440800, 223, 230, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3955800, 3955800, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(91, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 91, 2800000, 3749, 3858, 3800, 414200, 277, 284, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3629200, 3629200, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(92, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 92, 2400000, 4196, 4268, 3800, 273600, 392, 396, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2993600, 0, 'chua_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(93, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 93, 2600000, 1988, 2099, 3800, 421800, 366, 373, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3536800, 3536800, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(94, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 94, 2800000, 2683, 2754, 3800, 269800, 180, 184, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3389800, 3389800, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(95, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 95, 2400000, 2792, 2901, 3800, 414200, 268, 275, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3229200, 3229200, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(96, CONCAT('P204-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 14, 12, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 96, 2800000, 1386, 1496, 3800, 418000, 151, 158, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3733000, 3733000, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(97, CONCAT('P206-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 15, 14, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 97, 2400000, 2394, 2450, 3800, 212800, 415, 419, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2932800, 1500000, 'thu_mot_phan', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(98, CONCAT('P105-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 16, 5, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 98, 2600000, 4249, 4322, 3800, 277400, 338, 342, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3197400, 3197400, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(99, CONCAT('P208-', DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y%m')), 17, 16, DATE_FORMAT(@t - INTERVAL 2 MONTH, '%Y-%m'), 99, 3000000, 2615, 2727, 3800, 425600, 380, 387, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3940600, 3940600, 'da_thu', @t - INTERVAL 1 MONTH + INTERVAL 9 DAY, TIMESTAMP(@t - INTERVAL 1 MONTH, '08:15:00'), 2),
(100, CONCAT('P201-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 5, 9, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 100, 3000000, 4612, 4788, 3800, 668800, 421, 434, 25000, 325000, 4, 20000, 80000, 100000, 100000, 3, 100000, 300000, 4473800, 0, 'chua_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(101, CONCAT('P101-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 6, 1, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 101, 3200000, 2795, 2947, 3800, 577600, 273, 283, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4387600, 4387600, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(102, CONCAT('P205-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 7, 13, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 102, 2400000, 3290, 3385, 3800, 361000, 406, 413, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3176000, 1000000, 'thu_mot_phan', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(103, CONCAT('P104-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 8, 4, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 103, 3000000, 1858, 1966, 3800, 410400, 230, 237, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3925400, 3925400, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(104, CONCAT('P102-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 9, 2, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 104, 2800000, 3858, 3970, 3800, 425600, 284, 291, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3640600, 3640600, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(105, CONCAT('P303-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 10, 19, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 105, 2400000, 4268, 4347, 3800, 300200, 396, 400, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3020200, 0, 'chua_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(106, CONCAT('P202-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 11, 10, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 106, 2600000, 2099, 2213, 3800, 433200, 373, 380, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3548200, 3548200, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(107, CONCAT('P103-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 12, 3, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 107, 2800000, 2754, 2830, 3800, 288800, 184, 188, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3408800, 0, 'chua_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(108, CONCAT('P306-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 13, 22, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 108, 2400000, 2901, 3005, 3800, 395200, 275, 282, 25000, 175000, 2, 20000, 40000, 100000, 100000, 1, 100000, 100000, 3210200, 0, 'chua_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(109, CONCAT('P204-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 14, 12, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 109, 2800000, 1496, 1609, 3800, 429400, 158, 165, 25000, 175000, 2, 20000, 40000, 100000, 100000, 2, 100000, 200000, 3744400, 3744400, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(110, CONCAT('P206-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 15, 14, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 110, 2400000, 2450, 2507, 3800, 216600, 419, 423, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 2936600, 0, 'chua_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(111, CONCAT('P105-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 16, 5, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 111, 2600000, 4322, 4395, 3800, 277400, 342, 346, 25000, 100000, 1, 20000, 20000, 100000, 100000, 1, 100000, 100000, 3197400, 3197400, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(112, CONCAT('P208-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 17, 16, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 112, 3000000, 2727, 2877, 3800, 570000, 387, 397, 25000, 250000, 3, 20000, 60000, 100000, 100000, 2, 100000, 200000, 4180000, 2000000, 'thu_mot_phan', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2),
(113, CONCAT('P107-', DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y%m')), 18, 7, DATE_FORMAT(@t - INTERVAL 1 MONTH, '%Y-%m'), 113, 3000000, 1602, 1748, 3800, 554800, 389, 399, 25000, 250000, 3, 20000, 60000, 100000, 100000, 3, 100000, 300000, 4264800, 4264800, 'da_thu', @t + INTERVAL 9 DAY, TIMESTAMP(@t, '08:15:00'), 2);

INSERT INTO thanh_toan (id, hoa_don_id, so_tien, ngay_thu, hinh_thuc, ghi_chu, nguoi_thu, thoi_diem) VALUES
(1, 1, 2712500, @t - INTERVAL 11 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 11 MONTH + INTERVAL 3 DAY, '08:38:00')),
(2, 2, 3068500, @t - INTERVAL 11 MONTH + INTERVAL 6 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 11 MONTH + INTERVAL 6 DAY, '09:45:00')),
(3, 3, 2982500, @t - INTERVAL 11 MONTH + INTERVAL 2 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 11 MONTH + INTERVAL 2 DAY, '10:52:00')),
(4, 4, 3075500, @t - INTERVAL 10 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 10 MONTH + INTERVAL 7 DAY, '10:48:00')),
(5, 5, 2982500, @t - INTERVAL 10 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 10 MONTH + INTERVAL 3 DAY, '11:55:00')),
(6, 6, 4038500, @t - INTERVAL 10 MONTH + INTERVAL 6 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 10 MONTH + INTERVAL 6 DAY, '12:02:00')),
(7, 7, 4121000, @t - INTERVAL 10 MONTH + INTERVAL 2 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 10 MONTH + INTERVAL 2 DAY, '13:09:00')),
(8, 8, 3058000, @t - INTERVAL 9 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 9 MONTH + INTERVAL 8 DAY, '11:51:00')),
(9, 9, 2982500, @t - INTERVAL 9 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 9 MONTH + INTERVAL 4 DAY, '12:58:00')),
(10, 10, 4245500, @t - INTERVAL 9 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 9 MONTH + INTERVAL 7 DAY, '13:05:00')),
(11, 11, 4131500, @t - INTERVAL 9 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 9 MONTH + INTERVAL 3 DAY, '14:12:00')),
(12, 12, 2923000, @t - INTERVAL 9 MONTH + INTERVAL 6 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 9 MONTH + INTERVAL 6 DAY, '15:19:00')),
(13, 13, 3061500, @t - INTERVAL 8 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 2 DAY, '12:54:00')),
(14, 14, 2982500, @t - INTERVAL 8 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 5 DAY, '13:01:00')),
(15, 15, 4224500, @t - INTERVAL 8 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 8 DAY, '14:08:00')),
(16, 16, 4135000, @t - INTERVAL 8 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 4 DAY, '15:15:00')),
(17, 17, 2930000, @t - INTERVAL 8 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 7 DAY, '16:22:00')),
(18, 18, 3914000, @t - INTERVAL 8 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 8 MONTH + INTERVAL 3 DAY, '17:29:00')),
(19, 19, 3061500, @t - INTERVAL 7 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 3 DAY, '13:57:00')),
(20, 20, 2982500, @t - INTERVAL 7 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 6 DAY, '14:04:00')),
(21, 21, 4224500, @t - INTERVAL 7 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 2 DAY, '15:11:00')),
(22, 22, 4128000, @t - INTERVAL 7 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 5 DAY, '16:18:00')),
(23, 23, 3123000, @t - INTERVAL 7 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 8 DAY, '17:25:00')),
(24, 24, 3910500, @t - INTERVAL 7 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 4 DAY, '08:32:00')),
(25, 25, 3600000, @t - INTERVAL 7 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 7 DAY, '09:39:00')),
(26, 26, 2993000, @t - INTERVAL 7 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 7 MONTH + INTERVAL 3 DAY, '10:46:00')),
(27, 27, 3079000, @t - INTERVAL 6 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 4 DAY, '14:00:00')),
(28, 28, 2982500, @t - INTERVAL 6 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 7 DAY, '15:07:00')),
(29, 29, 4238500, @t - INTERVAL 6 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 3 DAY, '16:14:00')),
(30, 30, 4317500, @t - INTERVAL 6 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 6 DAY, '17:21:00')),
(31, 31, 3133500, @t - INTERVAL 6 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 2 DAY, '08:28:00')),
(32, 32, 3917500, @t - INTERVAL 6 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 5 DAY, '09:35:00')),
(33, 33, 3586000, @t - INTERVAL 6 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 8 DAY, '10:42:00')),
(34, 34, 2993000, @t - INTERVAL 6 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 4 DAY, '11:49:00')),
(35, 35, 3507000, @t - INTERVAL 6 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 7 DAY, '12:56:00')),
(36, 36, 3061500, @t - INTERVAL 5 MONTH + INTERVAL 5 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 5 DAY, '15:03:00')),
(37, 37, 2979000, @t - INTERVAL 5 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 8 DAY, '16:10:00')),
(38, 38, 4452500, @t - INTERVAL 5 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 4 DAY, '17:17:00')),
(39, 39, 4314000, @t - INTERVAL 5 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 7 DAY, '08:24:00')),
(40, 40, 3133500, @t - INTERVAL 5 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 3 DAY, '09:31:00')),
(41, 41, 3900000, @t - INTERVAL 5 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 6 DAY, '10:38:00')),
(42, 42, 3607000, @t - INTERVAL 5 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 2 DAY, '11:45:00')),
(43, 43, 2979000, @t - INTERVAL 5 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 5 DAY, '12:52:00')),
(44, 44, 3479000, @t - INTERVAL 5 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 8 DAY, '13:59:00')),
(45, 45, 3375500, @t - INTERVAL 5 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 4 DAY, '14:06:00')),
(46, 46, 2986000, @t - INTERVAL 5 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 7 DAY, '15:13:00')),
(47, 47, 3079000, @t - INTERVAL 4 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 6 DAY, '16:06:00')),
(48, 48, 2972000, @t - INTERVAL 4 MONTH + INTERVAL 2 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 2 DAY, '17:13:00')),
(49, 49, 4480500, @t - INTERVAL 4 MONTH + INTERVAL 5 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 5 DAY, '08:20:00')),
(50, 50, 4356000, @t - INTERVAL 4 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 8 DAY, '09:27:00')),
(51, 51, 3140500, @t - INTERVAL 4 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 4 DAY, '10:34:00')),
(52, 52, 3893000, @t - INTERVAL 4 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 7 DAY, '11:41:00')),
(53, 53, 3596500, @t - INTERVAL 4 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 3 DAY, '12:48:00')),
(54, 54, 2972000, @t - INTERVAL 4 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 6 DAY, '13:55:00')),
(55, 55, 3503500, @t - INTERVAL 4 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 2 DAY, '14:02:00')),
(56, 56, 3393000, @t - INTERVAL 4 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 5 DAY, '15:09:00')),
(57, 57, 3200000, @t - INTERVAL 4 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 8 DAY, '16:16:00')),
(58, 58, 3689500, @t - INTERVAL 4 MONTH + INTERVAL 4 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 4 MONTH + INTERVAL 4 DAY, '17:23:00')),
(59, 59, 3082500, @t - INTERVAL 3 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 7 DAY, '17:09:00')),
(60, 60, 2989500, @t - INTERVAL 3 MONTH + INTERVAL 3 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 3 DAY, '08:16:00')),
(61, 61, 4445500, @t - INTERVAL 3 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 6 DAY, '09:23:00')),
(62, 62, 4581000, @t - INTERVAL 3 MONTH + INTERVAL 2 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 2 DAY, '10:30:00')),
(63, 63, 3147500, @t - INTERVAL 3 MONTH + INTERVAL 5 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 5 DAY, '11:37:00')),
(64, 64, 3907000, @t - INTERVAL 3 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 8 DAY, '12:44:00')),
(65, 65, 3600000, @t - INTERVAL 3 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 4 DAY, '13:51:00')),
(66, 66, 2982500, @t - INTERVAL 3 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 7 DAY, '14:58:00')),
(67, 67, 3486000, @t - INTERVAL 3 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 3 DAY, '15:05:00')),
(68, 68, 3379000, @t - INTERVAL 3 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 6 DAY, '16:12:00')),
(69, 69, 3193000, @t - INTERVAL 3 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 2 DAY, '17:19:00')),
(70, 70, 3717500, @t - INTERVAL 3 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 5 DAY, '08:26:00')),
(71, 71, 2909000, @t - INTERVAL 3 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 8 DAY, '09:33:00')),
(72, 72, 2500000, @t - INTERVAL 3 MONTH + INTERVAL 4 DAY, 'tien_mat', 'Trả trước tiền phòng tháng cuối', 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 4 DAY, '09:30:00')),
(73, 72, 586000, @t - INTERVAL 2 MONTH - INTERVAL 1 DAY, 'tru_coc', 'Trừ tiền điện nước tháng cuối vào tiền cọc khi thanh lý', 1, TIMESTAMP(@t - INTERVAL 2 MONTH - INTERVAL 1 DAY, '17:00:00')),
(74, 73, 3020200, @t - INTERVAL 2 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 4 DAY, '09:19:00')),
(75, 74, 4511800, @t - INTERVAL 2 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 7 DAY, '10:26:00')),
(76, 75, 4364800, @t - INTERVAL 2 MONTH + INTERVAL 3 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 3 DAY, '11:33:00')),
(77, 76, 3168400, @t - INTERVAL 2 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 6 DAY, '12:40:00')),
(78, 77, 3921600, @t - INTERVAL 2 MONTH + INTERVAL 2 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 2 DAY, '13:47:00')),
(79, 78, 3633000, @t - INTERVAL 2 MONTH + INTERVAL 5 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 5 DAY, '14:54:00')),
(80, 79, 3001200, @t - INTERVAL 2 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 8 DAY, '15:01:00')),
(81, 80, 3517800, @t - INTERVAL 2 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 4 DAY, '16:08:00')),
(82, 81, 3397400, @t - INTERVAL 2 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 7 DAY, '17:15:00')),
(83, 82, 3221600, @t - INTERVAL 2 MONTH + INTERVAL 3 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 3 DAY, '08:22:00')),
(84, 83, 3736800, @t - INTERVAL 2 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 6 DAY, '09:29:00')),
(85, 84, 2929000, @t - INTERVAL 2 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 2 DAY, '10:36:00')),
(86, 85, 3189800, @t - INTERVAL 2 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 2 MONTH + INTERVAL 5 DAY, '11:43:00')),
(87, 86, 3005000, @t - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 5 DAY, '10:22:00')),
(88, 87, 4530800, @t - INTERVAL 1 MONTH + INTERVAL 8 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 8 DAY, '11:29:00')),
(89, 88, 4402800, @t - INTERVAL 1 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 1, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 4 DAY, '12:36:00')),
(90, 89, 3153200, @t - INTERVAL 1 MONTH + INTERVAL 7 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 7 DAY, '13:43:00')),
(91, 90, 3955800, @t - INTERVAL 1 MONTH + INTERVAL 3 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 3 DAY, '14:50:00')),
(92, 91, 3629200, @t - INTERVAL 1 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 6 DAY, '15:57:00')),
(93, 93, 3536800, @t - INTERVAL 1 MONTH + INTERVAL 5 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 5 DAY, '17:11:00')),
(94, 94, 3389800, @t - INTERVAL 1 MONTH + INTERVAL 8 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 8 DAY, '08:18:00')),
(95, 95, 3229200, @t - INTERVAL 1 MONTH + INTERVAL 4 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 4 DAY, '09:25:00')),
(96, 96, 3733000, @t - INTERVAL 1 MONTH + INTERVAL 7 DAY, 'chuyen_khoan', NULL, 1, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 7 DAY, '10:32:00')),
(97, 97, 1500000, @t - INTERVAL 1 MONTH + INTERVAL 7 DAY, 'tien_mat', 'Khách hẹn trả nốt vào cuối tháng', 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 7 DAY, '11:39:00')),
(98, 98, 3197400, @t - INTERVAL 1 MONTH + INTERVAL 6 DAY, 'tien_mat', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 6 DAY, '12:46:00')),
(99, 99, 3940600, @t - INTERVAL 1 MONTH + INTERVAL 2 DAY, 'chuyen_khoan', NULL, 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 2 DAY, '13:53:00')),
(100, 101, 4387600, LEAST(DATE(@t + INTERVAL 1 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 1 DAY), DATE(@hom_nay)), '13:39:00')),
(101, 102, 1000000, LEAST(DATE(@t + INTERVAL 2 DAY), DATE(@hom_nay)), 'tien_mat', 'Thu trước một phần', 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 2 DAY), DATE(@hom_nay)), '14:46:00')),
(102, 103, 3925400, LEAST(DATE(@t + INTERVAL 3 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 3 DAY), DATE(@hom_nay)), '15:53:00')),
(103, 104, 3640600, LEAST(DATE(@t + INTERVAL 4 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 1, TIMESTAMP(LEAST(DATE(@t + INTERVAL 4 DAY), DATE(@hom_nay)), '16:00:00')),
(104, 106, 3548200, LEAST(DATE(@t + INTERVAL 6 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 6 DAY), DATE(@hom_nay)), '08:14:00')),
(105, 109, 3744400, LEAST(DATE(@t + INTERVAL 3 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 3 DAY), DATE(@hom_nay)), '11:35:00')),
(106, 111, 3197400, LEAST(DATE(@t + INTERVAL 5 DAY), DATE(@hom_nay)), 'chuyen_khoan', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 5 DAY), DATE(@hom_nay)), '13:49:00')),
(107, 112, 2000000, LEAST(DATE(@t + INTERVAL 6 DAY), DATE(@hom_nay)), 'tien_mat', 'Thu trước một phần', 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 6 DAY), DATE(@hom_nay)), '14:56:00')),
(108, 113, 4264800, LEAST(DATE(@t + INTERVAL 1 DAY), DATE(@hom_nay)), 'tien_mat', NULL, 2, TIMESTAMP(LEAST(DATE(@t + INTERVAL 1 DAY), DATE(@hom_nay)), '15:03:00'));

-- Sự cố: 3 đã xong (có chi phí sửa chữa), 2 đang xử lý, 2 chờ xử lý
INSERT INTO su_co (id, ma_theo_doi, phong_id, khach_thue_id, loai, mo_ta, trang_thai, ngay_bao,
                   ghi_chu_xu_ly, ngay_cap_nhat, chi_phi, ngay_sua, tho_sua) VALUES
(1, 'SC-7KQ2M9', 1, 1, 'Điện', 'Ổ cắm cạnh bàn học bị chập, có mùi khét', 'da_xong', TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 11 DAY, '20:15:00'), 'Đã thay ổ cắm mới và kiểm tra lại toàn bộ dây điện trong phòng', TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 12 DAY, '16:40:00'), 150000, @t - INTERVAL 6 MONTH + INTERVAL 12 DAY, 'Anh Tuấn – thợ điện'),
(2, 'SC-P4X8TD', 6, 17, 'Nước', 'Bồn cầu bị tắc, nước không thoát', 'da_xong', TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 19 DAY, '18:30:00'), 'Thông tắc bồn cầu, hướng dẫn khách không vứt giấy ướt', TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 20 DAY, '10:15:00'), 200000, @t - INTERVAL 5 MONTH + INTERVAL 20 DAY, 'Anh Hải – thợ nước'),
(3, 'SC-M3N7RW', 1, 20, 'Nước', 'Vòi hoa sen bị rò rỉ, nước chảy liên tục cả đêm', 'da_xong', TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 1 DAY, '08:40:00'), 'Thay bộ vòi sen và gioăng chống rò. Tháng trước tiền nước tăng cao do rò rỉ', TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 2 DAY, '15:20:00'), 350000, @t - INTERVAL 3 MONTH + INTERVAL 2 DAY, 'Anh Hải – thợ nước'),
(4, 'SC-H9C2VB', 20, NULL, 'Nước', 'Trần nhà thấm nước mỗi khi mưa to', 'dang_xu_ly', TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 9 DAY, '07:30:00'), NULL, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 11 DAY, '14:00:00'), NULL, NULL, NULL),
(5, 'SC-T6J4LE', 12, 9, 'Khóa cửa', 'Khóa cửa chính bị kẹt, rất khó mở', 'dang_xu_ly', TIMESTAMP(@hom_nay - INTERVAL 5 DAY, '21:05:00'), NULL, TIMESTAMP(@hom_nay - INTERVAL 4 DAY, '09:00:00'), NULL, NULL, NULL),
(6, 'SC-W2R8QA', 24, NULL, 'Điện', 'Điều hòa không lạnh, cần nạp ga', 'cho_xu_ly', TIMESTAMP(@hom_nay - INTERVAL 2 DAY, '19:20:00'), NULL, NULL, NULL, NULL, NULL),
(7, 'SC-D5Z3KU', 14, 11, 'Internet', 'Wifi trong phòng chập chờn vào buổi tối', 'cho_xu_ly', TIMESTAMP(@hom_nay - INTERVAL 1 DAY, '22:10:00'), NULL, NULL, NULL, NULL, NULL);

INSERT INTO su_co_lich_su (id, su_co_id, trang_thai, ghi_chu, nguoi_cap_nhat, thoi_diem) VALUES
(1, 1, 'cho_xu_ly', 'Khách thuê báo sự cố', NULL, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 11 DAY, '20:15:00')),
(2, 1, 'dang_xu_ly', 'Đã hẹn thợ điện sáng mai', 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 12 DAY, '08:05:00')),
(3, 1, 'da_xong', 'Đã thay ổ cắm mới và kiểm tra lại toàn bộ dây điện trong phòng', 2, TIMESTAMP(@t - INTERVAL 6 MONTH + INTERVAL 12 DAY, '16:40:00')),
(4, 2, 'cho_xu_ly', 'Khách thuê báo sự cố', NULL, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 19 DAY, '18:30:00')),
(5, 2, 'da_xong', 'Thông tắc bồn cầu, hướng dẫn khách không vứt giấy ướt', 2, TIMESTAMP(@t - INTERVAL 5 MONTH + INTERVAL 20 DAY, '10:15:00')),
(6, 3, 'cho_xu_ly', 'Khách thuê báo sự cố', NULL, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 1 DAY, '08:40:00')),
(7, 3, 'dang_xu_ly', 'Đã báo thợ nước, chiều mai đến sửa', 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 1 DAY, '09:10:00')),
(8, 3, 'da_xong', 'Thay bộ vòi sen và gioăng chống rò. Tháng trước tiền nước tăng cao do rò rỉ', 2, TIMESTAMP(@t - INTERVAL 3 MONTH + INTERVAL 2 DAY, '15:20:00')),
(9, 4, 'cho_xu_ly', 'Quản lý ghi nhận khi kiểm tra phòng', 2, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 9 DAY, '07:30:00')),
(10, 4, 'dang_xu_ly', 'Đã gọi đội chống thấm, chờ tạnh mưa để thi công', 1, TIMESTAMP(@t - INTERVAL 1 MONTH + INTERVAL 11 DAY, '14:00:00')),
(11, 5, 'cho_xu_ly', 'Khách thuê báo sự cố', NULL, TIMESTAMP(@hom_nay - INTERVAL 5 DAY, '21:05:00')),
(12, 5, 'dang_xu_ly', 'Đã đặt mua ổ khóa mới, 2 ngày nữa có hàng', 2, TIMESTAMP(@hom_nay - INTERVAL 4 DAY, '09:00:00')),
(13, 6, 'cho_xu_ly', 'Quản lý ghi nhận khi dọn phòng', 2, TIMESTAMP(@hom_nay - INTERVAL 2 DAY, '19:20:00')),
(14, 7, 'cho_xu_ly', 'Khách thuê báo sự cố', NULL, TIMESTAMP(@hom_nay - INTERVAL 1 DAY, '22:10:00'));

SET FOREIGN_KEY_CHECKS = 1;
