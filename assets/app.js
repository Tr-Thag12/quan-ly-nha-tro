/* =====================================================================
 *  JAVASCRIPT DÙNG CHUNG
 *  1. Hộp thoại xác nhận trước khi xóa           (PH003 – AC2)
 *  2. Lọc phòng theo trạng thái không tải lại trang (PH005 – AC1)
 *     và phân trang 20 phòng một trang            (PH004 – AC2)
 * ===================================================================== */
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------------
    // 1. Form nào có thuộc tính data-confirm thì hỏi lại trước khi gửi
    // ---------------------------------------------------------------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();   // bấm Hủy thì không gửi form, không xóa
            }
        });
    });

    // ---------------------------------------------------------------
    // 2. Lọc + phân trang danh sách phòng (chỉ chạy ở trang phong.php)
    // ---------------------------------------------------------------
    var danhSach = document.getElementById('room-list');
    if (!danhSach) {
        return;
    }

    var soDongMoiTrang = parseInt(danhSach.getAttribute('data-per-page'), 10) || 20;
    var tatCaDong      = Array.prototype.slice.call(danhSach.querySelectorAll('tbody tr'));
    var cacNutLoc      = danhSach.querySelectorAll('[data-filter]');
    var vungPhanTrang  = danhSach.querySelector('.pagination');
    var thongTinTrang  = danhSach.querySelector('.page-info');
    var thongBaoRong   = danhSach.querySelector('.empty-filter');

    var boLoc        = danhSach.getAttribute('data-initial') || 'tat_ca';
    var trangHienTai = 1;

    // Các dòng khớp với bộ lọc đang chọn
    function cacDongPhuHop() {
        return tatCaDong.filter(function (dong) {
            return boLoc === 'tat_ca' || dong.getAttribute('data-status') === boLoc;
        });
    }

    // Vẽ lại bảng: ẩn hết, rồi chỉ hiện các dòng thuộc trang hiện tại
    function veLai() {
        var dongPhuHop = cacDongPhuHop();
        var soTrang    = Math.max(1, Math.ceil(dongPhuHop.length / soDongMoiTrang));
        if (trangHienTai > soTrang) {
            trangHienTai = soTrang;
        }
        var batDau  = (trangHienTai - 1) * soDongMoiTrang;
        var ketThuc = Math.min(batDau + soDongMoiTrang, dongPhuHop.length);

        tatCaDong.forEach(function (dong) { dong.hidden = true; });
        dongPhuHop.slice(batDau, ketThuc).forEach(function (dong) { dong.hidden = false; });

        cacNutLoc.forEach(function (nut) {
            nut.classList.toggle('active', nut.getAttribute('data-filter') === boLoc);
        });

        thongBaoRong.hidden = dongPhuHop.length > 0;
        thongTinTrang.textContent = dongPhuHop.length === 0 ? ''
            : 'Hiển thị ' + (batDau + 1) + '–' + ketThuc + ' trên ' + dongPhuHop.length + ' phòng';

        // Chỉ hiện nút chuyển trang khi có hơn 1 trang (tức là hơn 20 phòng)
        vungPhanTrang.innerHTML = '';
        if (soTrang > 1) {
            taoNutTrang('‹', trangHienTai - 1, trangHienTai === 1, false);
            for (var i = 1; i <= soTrang; i++) {
                taoNutTrang(String(i), i, false, i === trangHienTai);
            }
            taoNutTrang('›', trangHienTai + 1, trangHienTai === soTrang, false);
        }
    }

    function taoNutTrang(nhan, trang, voHieu, dangChon) {
        var nut = document.createElement('button');
        nut.type        = 'button';
        nut.textContent = nhan;
        nut.className   = 'page-btn' + (dangChon ? ' active' : '');
        nut.disabled    = voHieu;
        nut.addEventListener('click', function () {
            trangHienTai = trang;
            veLai();
        });
        vungPhanTrang.appendChild(nut);
    }

    // Bấm nút lọc: lọc ngay, không tải lại trang
    cacNutLoc.forEach(function (nut) {
        nut.addEventListener('click', function () {
            boLoc        = nut.getAttribute('data-filter');
            trangHienTai = 1;
            veLai();

            // Ghi bộ lọc lên thanh địa chỉ để bấm F5 vẫn giữ bộ lọc
            var url = new URL(window.location.href);
            if (boLoc === 'tat_ca') {
                url.searchParams.delete('trang_thai');
            } else {
                url.searchParams.set('trang_thai', boLoc);
            }
            url.searchParams.delete('moi');
            window.history.replaceState(null, '', url);
        });
    });

    // Vừa thêm/sửa phòng: mở đúng trang có phòng đó (PH002 – AC2)
    var dongMoi = danhSach.querySelector('tr.is-new');
    if (dongMoi) {
        var viTri = cacDongPhuHop().indexOf(dongMoi);
        if (viTri >= 0) {
            trangHienTai = Math.floor(viTri / soDongMoiTrang) + 1;
        }
    }

    veLai();

    if (dongMoi && !dongMoi.hidden) {
        dongMoi.scrollIntoView({ block: 'center' });
    }
});
