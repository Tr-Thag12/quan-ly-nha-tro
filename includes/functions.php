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
    }
    return $pdo;
}

/** Trang báo lỗi thân thiện, dùng khi chưa cài CSDL hoặc MySQL chưa bật. */
function trang_loi($tieu_de, $noi_dung, $link = null, $chu_link = null)
{
    if (!headers_sent()) {
        http_response_code(500);
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
                'Không kết nối được MySQL. Hãy mở XAMPP Control Panel, bấm Start ở dòng MySQL rồi tải lại trang này.');
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
        trang_loi('Phiên làm việc đã hết hạn', 'Vui lòng quay lại, tải lại trang rồi thao tác lại.', 'index.php', 'Về trang chủ');
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

/** Tô sáng mục menu của trang đang mở. */
function menu_active($tien_to)
{
    $trang = basename(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
    return strpos($trang, $tien_to) === 0 ? ' class="active"' : '';
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
