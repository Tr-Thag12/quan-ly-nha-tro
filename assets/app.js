/* =====================================================================
 *  JAVASCRIPT DÙNG CHUNG
 *  1. Hộp thoại xác nhận trước khi xóa, thanh lý...  (PH003 – AC2)
 *  2. Tự điền ô khi chọn phòng (tạo hợp đồng)         (HD001)
 *  3. Tự tính số điện, số nước đã dùng khi gõ chỉ số   (DN001 – AC3)
 *  4. Tự gửi form lọc khi đổi lựa chọn
 *     4b. Đếm số ký tự của ô mô tả sự cố              (SC001)
 *  5. Lọc phòng theo trạng thái không tải lại trang    (PH005 – AC1)
 *     và phân trang 20 phòng một trang               (PH004 – AC2)
 * ===================================================================== */
document.addEventListener('DOMContentLoaded', function () {

    // ---------------------------------------------------------------
    // 1. Form nào có thuộc tính data-confirm thì hỏi lại trước khi gửi
    // ---------------------------------------------------------------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();   // bấm Hủy thì không gửi form
            }
        });
    });

    // ---------------------------------------------------------------
    // 2. <select data-autofill>: chọn một dòng thì điền sẵn các ô khác.
    //    Mỗi <option> mang thuộc tính data-fill-<id ô cần điền>="giá trị".
    // ---------------------------------------------------------------
    document.querySelectorAll('select[data-autofill]').forEach(function (chon) {
        chon.addEventListener('change', function () {
            var dong = chon.options[chon.selectedIndex];
            Array.prototype.forEach.call(dong.attributes, function (thuocTinh) {
                if (thuocTinh.name.indexOf('data-fill-') === 0) {
                    var o = document.getElementById(thuocTinh.name.substring(10));
                    if (o) {
                        o.value = thuocTinh.value;
                    }
                }
            });
        });
    });

    // ---------------------------------------------------------------
    // 3. Ô chỉ số mới có data-cu="chỉ số cũ" và data-ket-qua="id ô hiện kết quả":
    //    gõ đến đâu, hiện số đã dùng đến đó (máy chủ vẫn kiểm tra lại khi lưu).
    // ---------------------------------------------------------------
    document.querySelectorAll('input[data-cu]').forEach(function (o) {
        var ketQua = document.getElementById(o.getAttribute('data-ket-qua'));
        if (!ketQua) {
            return;
        }
        var capNhat = function () {
            var moi = o.value.trim();
            var cu = parseInt(o.getAttribute('data-cu'), 10);
            if (moi === '' || !/^\d+$/.test(moi)) {
                ketQua.textContent = '';
                ketQua.className = 'dung';
                return;
            }
            var dung = parseInt(moi, 10) - cu;
            ketQua.textContent = dung < 0 ? 'nhỏ hơn số cũ!' : dung + ' ' + (o.getAttribute('data-don-vi') || '');
            ketQua.className = dung < 0 ? 'dung text-red' : 'dung';
        };
        o.addEventListener('input', capNhat);
        capNhat();
    });

    // ---------------------------------------------------------------
    // 4. <select data-tu-gui>: đổi lựa chọn là tự lọc, không cần bấm nút
    // ---------------------------------------------------------------
    document.querySelectorAll('select[data-tu-gui]').forEach(function (chon) {
        chon.addEventListener('change', function () {
            chon.form.submit();
        });
    });

    // ---------------------------------------------------------------
    // 4b. <textarea maxlength data-dem="id ô hiện số">: đếm số ký tự đã gõ
    //     (mô tả sự cố tối đa 500 ký tự – SC001)
    // ---------------------------------------------------------------
    document.querySelectorAll('textarea[data-dem]').forEach(function (o) {
        var hien = document.getElementById(o.getAttribute('data-dem'));
        if (!hien) {
            return;
        }
        var dem = function () {
            hien.textContent = o.value.length + '/' + o.getAttribute('maxlength');
        };
        o.addEventListener('input', dem);
        dem();
    });

    // ---------------------------------------------------------------
    // 5. Lọc + phân trang danh sách phòng (chỉ chạy ở trang phong.php)
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
