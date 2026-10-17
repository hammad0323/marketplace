/* Proposal viewer: responsive sheet scaling, signature pad, accept / reject */
(function ($) {
    'use strict';
    var V = window.PMV;

    /* --- scale A4 sheets to fit the screen --- */
    function fit() {
        var avail = Math.min(document.documentElement.clientWidth - 24, 793);
        var s = avail / 793;
        $('.sheet-wrap').each(function () {
            var sheet = this.firstElementChild;
            if (!sheet) return;
            sheet.style.transform = s < 1 ? 'scale(' + s + ')' : '';
            this.style.width = (793 * s) + 'px';
            this.style.height = (1122 * s) + 'px';
        });
    }
    fit();
    $(window).on('resize', fit);
    if (V.print) {
        $(window).on('load', function () { setTimeout(function () { window.print(); }, 400); });
    }
    // in-document links to #accept / #pm-respond
    $(document).on('click', 'a[href="#pm-respond"]', function (e) {
        var t = document.getElementById('pm-respond');
        if (t) { e.preventDefault(); t.scrollIntoView({ behavior: 'smooth' }); }
    });

    /* --- signature --- */
    var sigMode = 'typed', drawn = false, uploaded = null;
    $('[data-sig]').on('shown.bs.tab', function () { sigMode = $(this).data('sig'); if (sigMode === 'drawn') setupPad(); });
    $('#sigText').on('input', function () { $('#sigTyped').text(this.value); });
    $('#acceptModal').on('shown.bs.modal', function () { $('#sigText').val($('#acceptForm [name=name]').val()).trigger('input'); });

    var pad = document.getElementById('sigPad'), ctx = null, drawing = false, last = null;
    function setupPad() {
        if (!pad) return;
        var ratio = window.devicePixelRatio || 1, w = pad.offsetWidth, h = pad.offsetHeight;
        if (pad.width === Math.round(w * ratio)) return;
        pad.width = w * ratio; pad.height = h * ratio;
        ctx = pad.getContext('2d');
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
        drawn = false;
    }
    function pos(e) {
        var r = pad.getBoundingClientRect(), t = e.touches ? e.touches[0] : e;
        return { x: t.clientX - r.left, y: t.clientY - r.top };
    }
    if (pad) {
        var start = function (e) { e.preventDefault(); setupPad(); drawing = true; last = pos(e); };
        var move = function (e) {
            if (!drawing) return; e.preventDefault();
            var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; drawn = true;
        };
        var end = function () { drawing = false; };
        pad.addEventListener('mousedown', start); pad.addEventListener('mousemove', move); window.addEventListener('mouseup', end);
        pad.addEventListener('touchstart', start, { passive: false }); pad.addEventListener('touchmove', move, { passive: false }); pad.addEventListener('touchend', end);
    }
    $('#sigClear').on('click', function () { if (ctx) { ctx.clearRect(0, 0, pad.width, pad.height); drawn = false; } });
    $('#sigFile').on('change', function () {
        var f = this.files[0];
        if (!f) return;
        if (f.size > 2 * 1024 * 1024) { Swal.fire({ icon: 'error', title: 'Image too large (max 2 MB)' }); return; }
        var reader = new FileReader();
        reader.onload = function () {
            // normalise to PNG through a canvas
            var img = new Image();
            img.onload = function () {
                var c = document.createElement('canvas'), sc = Math.min(1, 600 / img.width);
                c.width = img.width * sc; c.height = img.height * sc;
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                uploaded = c.toDataURL('image/png');
                $('#sigPreview').attr('src', uploaded).removeClass('d-none');
            };
            img.src = reader.result;
        };
        reader.readAsDataURL(f);
    });

    function send(action, $form, extra) {
        var data = $.extend({ action: action, token: V.token, _csrf: V.csrf }, extra);
        $form.serializeArray().forEach(function (x) { data[x.name] = x.value; });
        var $btn = $form.find('button[type!=button]').last().prop('disabled', true);
        return $.ajax({ url: V.endpoint, method: 'POST', data: data, dataType: 'json' })
            .done(function () {
                $('.modal').modal('hide');
                Swal.fire({
                    icon: action === 'accept' ? 'success' : 'info',
                    title: action === 'accept' ? 'Proposal accepted!' : 'Response recorded',
                    text: action === 'accept' ? 'Thank you — we will be in touch shortly with next steps.' : 'Thank you for your feedback.',
                    confirmButtonColor: V.color
                }).then(function () { location.reload(); });
            })
            .fail(function (x) { Swal.fire({ icon: 'error', title: 'Oops', text: (x.responseJSON && x.responseJSON.message) || 'Something went wrong.' }); })
            .always(function () { $btn.prop('disabled', false); });
    }

    $('#acceptForm').on('submit', function (e) {
        e.preventDefault();
        var sig = { signature_type: '', signature: '' };
        if (sigMode === 'typed' && $('#sigText').val().trim()) sig = { signature_type: 'typed', signature: $('#sigText').val().trim() };
        if (sigMode === 'drawn' && drawn) sig = { signature_type: 'drawn', signature: pad.toDataURL('image/png') };
        if (sigMode === 'uploaded' && uploaded) sig = { signature_type: 'uploaded', signature: uploaded };
        if (V.requireSig && !sig.signature) { Swal.fire({ icon: 'warning', title: 'Please add your signature' }); return; }
        send('accept', $(this), sig);
    });
    $('#rejectForm').on('submit', function (e) { e.preventDefault(); send('reject', $(this), {}); });
})(jQuery);
