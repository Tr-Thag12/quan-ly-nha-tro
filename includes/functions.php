<?php
/**
 * =====================================================================
 *  CÁC HÀM DÙNG CHUNG CHO TOÀN HỆ THỐNG
 * =====================================================================
 */

// ---------------------------------------------------------------------
//  1. DANH MỤC CỐ ĐỊNH
//  CSDL lưu mã không dấu (vd: dang_thue), lúc hiển thị mới đổi sang
//  tiếng Việt có dấu. Cách này tránh lỗi font khi so sánh dữ liệu.
// ---------------------------------------------------------------------
const VAI_TRO = [
    'chu_tro' => 'Chủ trọ',
    'quan_ly' => 'Quản lý',
];

const TRANG_THAI_PHONG = [
    'trong'     => 'Trống',
    'dang_thue' => 'Đang thuê',
    'dang_sua'  => 'Đang sửa',
];

const TRANG_THAI_HOP_DONG = [
    'hieu_luc'    => 'Còn hiệu lực',
    'da_thanh_ly' => 'Đã thanh lý',
];

const TRANG_THAI_SU_CO = [
    'cho_xu_ly'  => 'Chờ xử lý',
    'dang_xu_ly' => 'Đang xử lý',
    'da_xong'    => 'Đã xong',
];

const TRANG_THAI_HOA_DON = [
    'chua_thu'     => 'Chưa thu',
    'thu_mot_phan' => 'Thu một phần',
    'da_thu'       => 'Đã thu',
];

const HINH_THUC_THANH_TOAN = [
    'tien_mat'     => 'Tiền mặt',
    'chuyen_khoan' => 'Chuyển khoản',
    'tru_coc'      => 'Trừ tiền cọc',
];

// DN004: 5 loại dịch vụ tính tiền hằng tháng và đơn vị tính
const DICH_VU = [
    'dien'   => ['ten' => 'Điện',   'don_vi' => 'đ/kWh'],
    'nuoc'   => ['ten' => 'Nước',   'don_vi' => 'đ/m³'],
    'rac'    => ['ten' => 'Rác',    'don_vi' => 'đ/người/tháng'],
    'wifi'   => ['ten' => 'Wifi',   'don_vi' => 'đ/phòng/tháng'],
    'gui_xe' => ['ten' => 'Gửi xe', 'don_vi' => 'đ/xe/tháng'],
];

// SC001: các loại sự cố khách thuê được chọn
const LOAI_SU_CO = ['Điện', 'Nước', 'Khóa cửa', 'Internet', 'Khác'];

// ---------------------------------------------------------------------
//  2. PHIÊN ĐĂNG NHẬP (SESSION)
// ---------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');   // JavaScript không đọc được cookie phiên
    session_name('NHATRO_SESSION');            // tên riêng, không lẫn với dự án khác trên localhost
    session_start();
}

// ---------------------------------------------------------------------
//  3. KẾT NỐI CƠ SỞ DỮ LIỆU
//  Dùng PDO + câu lệnh chuẩn bị sẵn (prepare) để chống SQL Injection.
// ---------------------------------------------------------------------
function db()
{
    static $pdo = null;   // chỉ kết nối 1 lần cho mỗi lượt tải trang
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        dong_bo_mui_gio($pdo);
    }
    return $pdo;
}

/**
 * Cho MySQL dùng cùng múi giờ với PHP (giờ Việt Nam, xem config.php),
 * để NOW(), CURDATE() trong câu SQL luôn khớp với ngày giờ PHP hiển thị.
 */
function dong_bo_mui_gio($pdo)
{
    $pdo->exec("SET time_zone = '" . date('P') . "'");
}

/** Trang báo lỗi thân thiện, dùng khi chưa cài CSDL hoặc MySQL chưa bật. */
function trang_loi($tieu_de, $noi_dung, $link = null, $chu_link = null, $ma_http = 500)
{
    if (!headers_sent()) {
        http_response_code($ma_http);
    }
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><title>' . e($tieu_de) . '</title>'
       . '<link rel="stylesheet" href="assets/style.css"></head><body class="auth-page"><main class="container">'
       . '<div class="card auth-card"><div class="auth-head"><span class="brand-logo lg">NT</span>'
       . '<h1>' . e($tieu_de) . '</h1></div><p>' . e($noi_dung) . '</p>';
    if ($link) {
        echo '<p style="margin-top:20px"><a class="btn btn-primary btn-block" href="' . e($link) . '">' . e($chu_link) . '</a></p>';
    }
    echo '</div></main></body></html>';
    exit;
}

// Bắt mọi lỗi CSDL chưa xử lý và giải thích bằng tiếng Việt
set_exception_handler(function ($loi) {
    $tin = $loi->getMessage();
    if ($loi instanceof PDOException) {
        if (strpos($tin, '[1049]') !== false || strpos($tin, '42S02') !== false) {
            trang_loi('Chưa có cơ sở dữ liệu',
                'Cơ sở dữ liệu "' . DB_NAME . '" chưa được tạo hoặc còn thiếu bảng. Bấm nút bên dưới để cài đặt.',
                'install.php', 'Mở trang cài đặt');
        }
        if (strpos($tin, '[2002]') !== false) {
            trang_loi('Chưa bật MySQL',
                'Không kết nối được MySQL. Hãy bật MySQL (Laragon: bấm Start All; XAMPP: bấm Start ở dòng MySQL) rồi tải lại trang này.');
        }
        if (strpos($tin, '[1045]') !== false) {
            trang_loi('Sai tài khoản MySQL', 'Hãy kiểm tra lại DB_USER và DB_PASS trong file config.php.');
        }
    }
    trang_loi('Đã xảy ra lỗi', $tin);
});

// ---------------------------------------------------------------------
//  4. HÀM TIỆN ÍCH
// ---------------------------------------------------------------------

/** Mã hóa ký tự đặc biệt trước khi in ra HTML (chống chèn mã độc – XSS). */
function e($gia_tri)
{
    return htmlspecialchars((string)$gia_tri, ENT_QUOTES, 'UTF-8');
}

/** Chuyển sang trang khác rồi dừng xử lý. */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** Lưu thông báo để hiện ở trang kế tiếp (loại: success, error, warning, info). */
function flash($loai, $noi_dung)
{
    $_SESSION['flash'] = ['loai' => $loai, 'noi_dung' => $noi_dung];
}

/** Lấy thông báo ra (và xóa luôn để không hiện lại). */
function lay_flash()
{
    $tb = isset($_SESSION['flash']) ? $_SESSION['flash'] : null;
    unset($_SESSION['flash']);
    return $tb;
}

/** Đếm số ký tự, đúng cả với chữ có dấu. */
function do_dai($chuoi)
{
    return function_exists('mb_strlen') ? mb_strlen($chuoi, 'UTF-8') : strlen($chuoi);
}

/** Cắt ngắn đoạn văn dài để hiện trong bảng: quá $toi_da ký tự thì cắt và thêm dấu "…". */
function rut_gon($chuoi, $toi_da)
{
    if (do_dai($chuoi) <= $toi_da) {
        return $chuoi;
    }
    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($chuoi, 0, $toi_da - 1, 'UTF-8')) . '…';
    }
    return rtrim(preg_replace('/^(.{' . ($toi_da - 1) . '}).*$/us', '$1', $chuoi)) . '…';
}

/** Lấy giá trị một ô trong form gửi lên (POST), đã cắt khoảng trắng hai đầu. */
function gia_tri_post($ten, $mac_dinh = '')
{
    return isset($_POST[$ten]) && !is_array($_POST[$ten]) ? trim((string)$_POST[$ten]) : $mac_dinh;
}

/** Lấy một tham số trên địa chỉ trang (GET), ví dụ phong.php?trang_thai=trong. */
function gia_tri_get($ten, $mac_dinh = '')
{
    return isset($_GET[$ten]) && !is_array($_GET[$ten]) ? trim((string)$_GET[$ten]) : $mac_dinh;
}

/** Chuỗi ngẫu nhiên dễ đọc (bỏ các ký tự dễ nhầm như 0/O, 1/I), dùng làm mã theo dõi sự cố. */
function ma_ngau_nhien($do_dai)
{
    $ky_tu = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $ma = '';
    for ($i = 0; $i < $do_dai; $i++) {
        $ma .= $ky_tu[random_int(0, strlen($ky_tu) - 1)];
    }
    return $ma;
}

// ---------------------------------------------------------------------
//  5. CHỐNG GIẢ MẠO YÊU CẦU (CSRF)
//  Mỗi form có một mã bí mật; form gửi lên không đúng mã thì từ chối.
// ---------------------------------------------------------------------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf()
{
    if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) {
        // Ảnh quá nặng, vượt giới hạn post_max_size của PHP: trình duyệt gửi lên nhưng PHP bỏ hết dữ liệu form
        if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH'])) {
            trang_loi('Ảnh quá lớn', 'Dữ liệu gửi lên vượt quá giới hạn của máy chủ, thường do ảnh quá nặng. '
                . 'Hãy quay lại và chọn ảnh nhỏ hơn ' . ANH_TOI_DA_MB . ' MB.', $_SERVER['REQUEST_URI'], 'Quay lại', 413);
        }
        trang_loi('Phiên làm việc đã hết hạn', 'Vui lòng quay lại, tải lại trang rồi thao tác lại.', 'index.php', 'Về trang chủ', 403);
    }
}

// ---------------------------------------------------------------------
//  6. ĐĂNG NHẬP VÀ PHÂN QUYỀN
// ---------------------------------------------------------------------

/** Người đang đăng nhập (mảng id, ho_ten, ten_dang_nhap, vai_tro) hoặc null. */
function current_user()
{
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function is_chu_tro()
{
    $nguoi_dung = current_user();
    return $nguoi_dung !== null && $nguoi_dung['vai_tro'] === 'chu_tro';
}

/** id của người đang đăng nhập, dùng để ghi lại ai đã thu tiền, ai đã ghi chỉ số... */
function id_nguoi_dung()
{
    $nguoi_dung = current_user();
    return $nguoi_dung ? (int)$nguoi_dung['id'] : null;
}

/**
 * TK002 – AC2: cấm trình duyệt lưu đệm trang.
 * Sau khi đăng xuất, bấm Back thì trình duyệt phải hỏi lại máy chủ,
 * máy chủ thấy chưa đăng nhập nên đẩy về trang đăng nhập.
 */
function no_cache()
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/** Trang nào gọi hàm này thì bắt buộc phải đăng nhập. */
function require_login()
{
    no_cache();
    if (!current_user()) {
        flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('login.php');
    }
}

/**
 * TK005 – AC1: chỉ vai trò được chỉ định mới vào được trang.
 * Vào sai quyền (kể cả gõ thẳng địa chỉ trang) sẽ gặp trang 403.
 */
function require_role($vai_tro)
{
    require_login();
    if (current_user()['vai_tro'] === $vai_tro) {
        return;
    }
    http_response_code(403);
    $page_title = 'Không có quyền truy cập';
    require __DIR__ . '/header.php';
    ?>
    <div class="card empty-state">
        <div class="empty-code">403</div>
        <h2>Bạn không có quyền truy cập chức năng này</h2>
        <p class="muted">
            Chức năng chỉ dành cho <strong><?= e(VAI_TRO[$vai_tro]) ?></strong>.
            Tài khoản của bạn đang có vai trò <strong><?= e(VAI_TRO[current_user()['vai_tro']]) ?></strong>.
        </p>
        <a class="btn" href="index.php">&larr; Về trang chủ</a>
    </div>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}

/** TK002 + TK003: xóa sạch phiên đăng nhập. */
function dang_xuat()
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------------------------------------------------------------------
//  7. ĐỊNH DẠNG HIỂN THỊ
// ---------------------------------------------------------------------
function tien($so)
{
    return number_format((float)$so, 0, ',', '.') . ' đ';
}

function ngay($chuoi_ngay)
{
    return $chuoi_ngay ? date('d/m/Y', strtotime($chuoi_ngay)) : '';
}

function ngay_gio($chuoi_ngay)
{
    return $chuoi_ngay ? date('H:i · d/m/Y', strtotime($chuoi_ngay)) : '';
}

function dien_tich($so)
{
    return rtrim(rtrim(number_format((float)$so, 1, ',', '.'), '0'), ',') . ' m²';
}

/** 0912487356 -> 0912 487 356 */
function sdt($so)
{
    return preg_replace('/^(\d{4})(\d{3})(\d{3})$/', '$1 $2 $3', (string)$so);
}

/** Chữ cái đầu của tên (Nguyễn Văn Nam -> N) để làm ảnh đại diện. */
function chu_cai_dau($ho_ten)
{
    $cac_tu = explode(' ', trim($ho_ten));
    $ten    = end($cac_tu);
    if (function_exists('mb_substr')) {
        return mb_strtoupper(mb_substr($ten, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($ten, 0, 1));
}

/**
 * Đọc số tiền người dùng nhập: "2500000", "2.500.000", "2,500,000 đ" -> 2500000.
 * Trả về null nếu không phải số nguyên không âm (ví dụ "-5000", "abc").
 */
function doc_so_tien($chuoi)
{
    $chuoi = preg_replace('/\s*(VNĐ|vnđ|VND|vnd|đ|Đ)$/u', '', trim((string)$chuoi));   // bỏ đơn vị ở cuối
    $chuoi = str_replace(['.', ',', ' '], '', $chuoi);                                 // bỏ dấu phân cách
    return preg_match('/^\d{1,12}$/', $chuoi) ? (int)$chuoi : null;
}

/** Nhãn màu cho trạng thái. $danh_muc là một trong các mảng hằng ở mục 1. */
function nhan($ma, $danh_muc)
{
    $ten = isset($danh_muc[$ma]) ? $danh_muc[$ma] : $ma;
    return '<span class="badge badge-' . e($ma) . '">' . e($ten) . '</span>';
}

/**
 * PH004 – AC3 (v0.2): số người đang ở so với số người tối đa, ví dụ 3/4.
 * Phòng chưa có ai ở hiện màu xám, phòng đã đủ người hiện màu cam.
 * Rê chuột vào con số sẽ thấy giải thích đầy đủ.
 */
function so_nguoi($dang_o, $toi_da)
{
    $dang_o = (int)$dang_o;
    $toi_da = (int)$toi_da;
    $lop    = 'occ';
    $giai_thich = $dang_o . ' người đang ở, tối đa ' . $toi_da . ' người';
    if ($dang_o === 0) {
        $lop .= ' occ-empty';
    } elseif ($dang_o >= $toi_da) {
        $lop .= ' occ-full';
        $giai_thich .= ' (đã đủ người)';
    }
    return '<span class="' . $lop . '" title="' . e($giai_thich) . '">'
         . $dang_o . '<small>/' . $toi_da . '</small></span>';
}

/**
 * TT006: đọc số tiền thành chữ để in trên hóa đơn.
 * 3245000 -> "Ba triệu hai trăm bốn mươi lăm nghìn đồng"
 * 1050000 -> "Một triệu không trăm năm mươi nghìn đồng"
 */
function tien_bang_chu($so)
{
    $so = (int)$so;
    if ($so <= 0) {
        return 'Không đồng';
    }
    $chu_so = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    $ten_nhom = ['', ' nghìn', ' triệu', ' tỷ'];

    // Tách thành các nhóm 3 chữ số tính từ phải sang: 3245000 -> [0, 245, 3]
    $nhom = [];
    while ($so > 0) {
        $nhom[] = $so % 1000;
        $so = intdiv($so, 1000);
    }

    // Đọc một nhóm 3 chữ số. $doc_du = true: nhóm đứng sau nhóm khác nên đọc cả "không trăm", "linh"
    $doc_nhom = function ($n, $doc_du) use ($chu_so) {
        $tram = intdiv($n, 100);
        $chuc = intdiv($n % 100, 10);
        $dv   = $n % 10;
        $chu  = [];
        if ($doc_du || $tram > 0) {
            $chu[] = $chu_so[$tram] . ' trăm';
        }
        if ($chuc === 0) {
            if ($dv > 0 && ($doc_du || $tram > 0)) {
                $chu[] = 'linh';
            }
        } elseif ($chuc === 1) {
            $chu[] = 'mười';
        } else {
            $chu[] = $chu_so[$chuc] . ' mươi';
        }
        if ($dv === 1 && $chuc >= 2) {
            $chu[] = 'mốt';
        } elseif ($dv === 5 && $chuc >= 1) {
            $chu[] = 'lăm';
        } elseif ($dv > 0) {
            $chu[] = $chu_so[$dv];
        }
        return implode(' ', $chu);
    };

    $cac_phan = [];
    $nhom_cao_nhat = count($nhom) - 1;
    for ($i = $nhom_cao_nhat; $i >= 0; $i--) {
        if ($nhom[$i] === 0) {
            continue;
        }
        $ten = $i <= 3 ? $ten_nhom[$i] : $ten_nhom[$i - 3] . ' tỷ';   // từ 1.000 tỷ trở lên: "nghìn tỷ"
        $cac_phan[] = $doc_nhom($nhom[$i], $i < $nhom_cao_nhat) . $ten;
    }
    return ucfirst(implode(' ', $cac_phan)) . ' đồng';
}

/** Số tiền rút gọn cho nhãn biểu đồ: 45.600.000 -> "45,6 tr". */
function tien_gon($so)
{
    $so = (float)$so;
    if ($so >= 1000000) {
        return rtrim(rtrim(number_format($so / 1000000, 1, ',', '.'), '0'), ',') . ' tr';
    }
    if ($so >= 1000) {
        return number_format($so / 1000, 0, ',', '.') . ' k';
    }
    return number_format($so, 0, ',', '.');
}

/** Địa chỉ đầy đủ của một trang trong hệ thống, ví dụ http://localhost/nhatro/bao_su_co.php */
function dia_chi_trang($trang)
{
    $giao_thuc = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    $may_chu   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $thu_muc   = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/');
    return $giao_thuc . $may_chu . $thu_muc . '/' . $trang;
}

/** Tô sáng mục menu của trang đang mở. $tien_to có thể là một chuỗi hoặc mảng chuỗi. */
function menu_active($tien_to)
{
    $trang = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    foreach ((array)$tien_to as $t) {
        if (strpos($trang, $t) === 0) {
            return ' class="active"';
        }
    }
    return '';
}

// ---------------------------------------------------------------------
//  7b. NGÀY VÀ THÁNG
//  Tháng luôn viết dạng "YYYY-MM" (ví dụ 2026-09) để so sánh và sắp xếp dễ.
// ---------------------------------------------------------------------
function hom_nay()
{
    return date('Y-m-d');
}

function thang_hien_tai()
{
    return date('Y-m');
}

/** Kiểm tra chuỗi có đúng dạng tháng YYYY-MM không. */
function thang_hop_le($thang)
{
    return is_string($thang) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $thang) === 1;
}

/** Kiểm tra chuỗi có đúng một ngày có thật dạng YYYY-MM-DD không. */
function ngay_hop_le($ngay)
{
    $d = DateTime::createFromFormat('Y-m-d', (string)$ngay);
    return $d !== false && $d->format('Y-m-d') === $ngay;
}

/** Cộng/trừ tháng: thang_cong('2026-01', -1) = '2025-12'. */
function thang_cong($thang, $so_thang)
{
    return date('Y-m', strtotime($thang . '-01 ' . sprintf('%+d', $so_thang) . ' month'));
}

/**
 * Cộng tháng cho một ngày mà không bị tràn sang tháng sau:
 * cong_thang_cho_ngay('2026-08-31', 6) = '2027-02-28' (strtotime sẽ ra 03/03/2027).
 */
function cong_thang_cho_ngay($ngay, $so_thang)
{
    $thang = thang_cong(substr($ngay, 0, 7), $so_thang);
    $ngay_trong_thang = min((int)substr($ngay, 8, 2), (int)date('t', strtotime($thang . '-01')));
    return $thang . '-' . sprintf('%02d', $ngay_trong_thang);
}

function ngay_dau_thang($thang)
{
    return $thang . '-01';
}

function ngay_cuoi_thang($thang)
{
    return date('Y-m-t', strtotime($thang . '-01'));
}

/** '2026-09' -> '09/2026' */
function ten_thang($thang)
{
    return substr($thang, 5, 2) . '/' . substr($thang, 0, 4);
}

/** Số ngày từ ngày $tu đến ngày $den (âm nếu $den trước $tu). */
function so_ngay($tu, $den)
{
    return (int)round((strtotime($den) - strtotime($tu)) / 86400);
}

/** Danh sách $so tháng gần nhất tính đến $den (mới nhất đứng đầu), dùng cho ô chọn tháng. */
function cac_thang_gan_nhat($so, $den = null)
{
    $den = $den ?: thang_hien_tai();
    $ds = [];
    for ($i = 0; $i < $so; $i++) {
        $ds[] = thang_cong($den, -$i);
    }
    return $ds;
}

/** HD006: nhãn số ngày còn lại của hợp đồng. */
function nhan_con_lai($ngay_ket_thuc)
{
    $con = so_ngay(hom_nay(), $ngay_ket_thuc);
    if ($con < 0) {
        return '<span class="badge badge-qua_han">Quá hạn ' . (-$con) . ' ngày</span>';
    }
    if ($con <= SO_NGAY_SAP_HET_HAN) {
        return '<span class="badge badge-sap_het_han">Còn ' . $con . ' ngày</span>';
    }
    return '<span class="muted">Còn ' . $con . ' ngày</span>';
}

// ---------------------------------------------------------------------
//  8. HỖ TRỢ HIỂN THỊ LỖI CỦA FORM
//  Lỗi của ô nào hiện ngay dưới ô đó (TK001 – AC3 và các form khác).
// ---------------------------------------------------------------------
function loi_o($loi, $ten_o)
{
    return isset($loi[$ten_o]) ? '<div class="field-error">' . e($loi[$ten_o]) . '</div>' : '';
}

function lop_loi($loi, $ten_o)
{
    return isset($loi[$ten_o]) ? 'is-invalid' : '';
}
