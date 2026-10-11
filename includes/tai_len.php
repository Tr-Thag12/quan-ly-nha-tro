<?php
/**
 * =====================================================================
 *  LƯU ẢNH TẢI LÊN  (KT001: ảnh CCCD, SC001: ảnh minh họa sự cố)
 *  An toàn: chỉ nhận ảnh thật (JPG, PNG, WEBP), tối đa ANH_TOI_DA_MB MB,
 *  đặt tên file ngẫu nhiên nên không ai đoán được và không thể tải lên file .php.
 * =====================================================================
 */

/**
 * Lưu ảnh từ ô <input type="file" name="$ten_o"> vào thư mục uploads/$thu_muc.
 * Trả về mảng [đường dẫn, lỗi]:
 *   - không chọn ảnh:    [null, null]
 *   - lưu thành công:    ['uploads/cccd/abc123.jpg', null]
 *   - ảnh không hợp lệ:  [null, 'câu báo lỗi']
 */
function luu_anh_tai_len($ten_o, $thu_muc)
{
    if (empty($_FILES[$ten_o]) || $_FILES[$ten_o]['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    $tep = $_FILES[$ten_o];
    $gioi_han = ANH_TOI_DA_MB * 1024 * 1024;

    if ($tep['error'] === UPLOAD_ERR_INI_SIZE || $tep['error'] === UPLOAD_ERR_FORM_SIZE || $tep['size'] > $gioi_han) {
        return [null, 'Ảnh quá lớn, chỉ nhận ảnh tối đa ' . ANH_TOI_DA_MB . ' MB.'];
    }
    if ($tep['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($tep['tmp_name'])) {
        return [null, 'Tải ảnh lên không thành công, vui lòng thử lại.'];
    }

    // Đọc nội dung file để biết có đúng là ảnh không (không tin phần đuôi tên file)
    $thong_tin = @getimagesize($tep['tmp_name']);
    $duoi_file = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if ($thong_tin === false || !isset($duoi_file[$thong_tin[2]])) {
        return [null, 'Chỉ nhận ảnh định dạng JPG, PNG hoặc WEBP.'];
    }

    $thu_muc_luu = __DIR__ . '/../uploads/' . $thu_muc;
    if (!is_dir($thu_muc_luu) && !mkdir($thu_muc_luu, 0775, true)) {
        return [null, 'Không tạo được thư mục lưu ảnh.'];
    }
    $ten_file = bin2hex(random_bytes(12)) . '.' . $duoi_file[$thong_tin[2]];
    if (!move_uploaded_file($tep['tmp_name'], $thu_muc_luu . '/' . $ten_file)) {
        return [null, 'Không lưu được ảnh, hãy kiểm tra quyền ghi của thư mục uploads.'];
    }
    return ['uploads/' . $thu_muc . '/' . $ten_file, null];
}
