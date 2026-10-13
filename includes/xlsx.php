<?php
/**
 * =====================================================================
 *  BC005 – TẠO FILE EXCEL (.xlsx) KHÔNG CẦN THƯ VIỆN NGOÀI
 *  File .xlsx thực chất là một file nén ZIP chứa vài file XML.
 *  Hàm tao_xlsx() tự viết các file XML đó, zip_khong_nen() đóng gói lại.
 *  Excel, LibreOffice và Google Sheets đều mở được.
 * =====================================================================
 */

/** Số thứ tự cột (0, 1, 2...) sang tên cột Excel (A, B, C... Z, AA...). */
function xlsx_ten_cot($so)
{
    $ten = '';
    $so++;
    while ($so > 0) {
        $du  = ($so - 1) % 26;
        $ten = chr(65 + $du) . $ten;
        $so  = intdiv($so - 1, 26);
    }
    return $ten;
}

/**
 * Một ô trong trang tính. $kieu là số thứ tự định dạng trong styles.xml:
 *   0 = chữ thường, 1 = chữ đậm, 2 = số có dấu chấm ngăn cách, 3 = số đậm, 4 = tiêu đề lớn
 */
function xlsx_o($cot, $dong, $gia_tri, $kieu)
{
    $vi_tri = xlsx_ten_cot($cot) . $dong;
    if (is_int($gia_tri) || is_float($gia_tri)) {
        return '<c r="' . $vi_tri . '" s="' . $kieu . '"><v>' . $gia_tri . '</v></c>';
    }
    return '<c r="' . $vi_tri . '" s="' . $kieu . '" t="inlineStr"><is><t xml:space="preserve">'
         . htmlspecialchars((string)$gia_tri, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
}

/**
 * Tạo nội dung file .xlsx.
 *   $ten_trang_tinh: tên trang tính (tab) trong Excel
 *   $tieu_de:        dòng tiêu đề lớn trên cùng
 *   $cot:            [['ten' => 'Tháng', 'rong' => 12, 'so' => false], ...]
 *   $cac_dong:       mảng các dòng dữ liệu, mỗi dòng là mảng giá trị theo đúng thứ tự cột
 *   $dong_tong:      dòng tổng cộng (in đậm) hoặc null
 */
function tao_xlsx($ten_trang_tinh, $tieu_de, $cot, $cac_dong, $dong_tong = null)
{
    $xml_dong = ['<row r="1">' . xlsx_o(0, 1, $tieu_de, 4) . '</row>'];
    $so_dong = 3;
    $o = '';
    foreach ($cot as $i => $c) {
        $o .= xlsx_o($i, $so_dong, $c['ten'], 1);
    }
    $xml_dong[] = '<row r="' . $so_dong . '">' . $o . '</row>';
    foreach ($cac_dong as $dong) {
        $so_dong++;
        $o = '';
        foreach (array_values($dong) as $i => $gia_tri) {
            $o .= xlsx_o($i, $so_dong, $gia_tri, !empty($cot[$i]['so']) ? 2 : 0);
        }
        $xml_dong[] = '<row r="' . $so_dong . '">' . $o . '</row>';
    }
    if ($dong_tong) {
        $so_dong++;
        $o = '';
        foreach (array_values($dong_tong) as $i => $gia_tri) {
            $o .= xlsx_o($i, $so_dong, $gia_tri, !empty($cot[$i]['so']) ? 3 : 1);
        }
        $xml_dong[] = '<row r="' . $so_dong . '">' . $o . '</row>';
    }
    $do_rong = '';
    foreach ($cot as $i => $c) {
        $do_rong .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (int)$c['rong'] . '" customWidth="1"/>';
    }

    $dau_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $cac_file = [
        '[Content_Types].xml' => $dau_xml
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => $dau_xml
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => $dau_xml
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . htmlspecialchars($ten_trang_tinh, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>',
        'xl/_rels/workbook.xml.rels' => $dau_xml
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => $dau_xml
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="11"/><name val="Arial"/></font>'
            . '<font><b/><sz val="14"/><name val="Arial"/></font>'
            . '</fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => $dau_xml
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<cols>' . $do_rong . '</cols>'
            . '<sheetData>' . implode('', $xml_dong) . '</sheetData>'
            . '</worksheet>',
    ];
    return zip_khong_nen($cac_file);
}

/** Đóng gói nhiều file thành một file ZIP kiểu "không nén" (stored). */
function zip_khong_nen($cac_file)
{
    $bay_gio = getdate();
    $gio  = ($bay_gio['hours'] << 11) | ($bay_gio['minutes'] << 5) | intdiv($bay_gio['seconds'], 2);
    $ngay = (($bay_gio['year'] - 1980) << 9) | ($bay_gio['mon'] << 5) | $bay_gio['mday'];

    $du_lieu = '';
    $muc_luc = '';
    $vi_tri  = 0;
    foreach ($cac_file as $ten => $noi_dung) {
        $crc = crc32($noi_dung);
        $co  = strlen($noi_dung);
        // Phần đầu của từng file (local file header); cờ 0x0800 = tên file dạng UTF-8
        $dau = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $gio, $ngay, $crc, $co, $co, strlen($ten), 0) . $ten;
        $du_lieu .= $dau . $noi_dung;
        // Dòng mục lục ở cuối file ZIP (central directory)
        $muc_luc .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $gio, $ngay, $crc, $co, $co,
                         strlen($ten), 0, 0, 0, 0, 0, $vi_tri) . $ten;
        $vi_tri += strlen($dau) + $co;
    }
    $so_file = count($cac_file);
    return $du_lieu . $muc_luc . pack('VvvvvVVv', 0x06054b50, 0, 0, $so_file, $so_file, strlen($muc_luc), $vi_tri, 0);
}
