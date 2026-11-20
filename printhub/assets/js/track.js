/* Order tracking page: progress, quote approval, advance payment, cancellation. Refreshes automatically. */
(function () {
    'use strict';
    var root = document.querySelector('[data-track]');
    if (!root) { return; }
    var number = root.dataset.number, t = root.dataset.t, lastStatus = null;
    var icons = { submitted: 'send', awaiting_approval: 'hourglass-split', quoted: 'chat-square-text', accepted: 'check2-circle', awaiting_payment: 'wallet2', payment_confirmed: 'cash-coin',
        printing: 'printer', ready: 'bag-check', completed: 'emoji-smile', rejected: 'x-octagon', cancelled: 'x-circle' };
    var label = function (v) { return { bw: 'Black & White', color: 'Colour', single: 'Single-sided', double: 'Double-sided' }[v] || v; };

    function render(o) {
        var stepIndex = o.steps.map(function (s) { return s.key; }).indexOf(o.status);
        var closedBad = o.status === 'rejected' || o.status === 'cancelled';
        var hist = {};
        o.history.forEach(function (h) { hist[h.status] = h; });
        var h = '<div class="c-card"><div class="c-status-big"><i class="bi bi-' + (icons[o.status] || 'circle') + '"></i><div><small class="c-muted">Order ' + PH.esc(o.order_number) + '</small><div class="fs-5 fw-bold">' + PH.esc(o.status_label) + '</div>'
            + '<small class="c-muted">' + PH.esc(o.shop.name) + '</small></div></div>';
        if (o.rejection_reason) { h += '<div class="c-notice mt-3">' + PH.esc(o.rejection_reason) + '</div>'; }
        h += '</div>';
        // amount & actions
        h += '<div class="c-card"><div class="d-flex align-items-center"><div class="c-price">' + (o.amount !== null ? PH.money(o.amount) : '—') + '<small>' + (o.final_amount !== null ? 'Final price' : (o.quoted_amount !== null ? 'Price quoted by the shop' : (o.estimated_amount !== null ? 'Estimated price' : 'The shop will send you the price'))) + '</small></div>'
            + '<span class="c-chip ms-auto">' + { unpaid: 'Not paid', pending_verification: 'Payment being checked', paid: 'Paid', refunded: 'Refunded' }[o.payment_status] + '</span></div>';
        if (o.can_accept_quote) {
            h += '<div class="d-flex gap-2 mt-3"><button class="c-btn flex-grow-1" data-act="accept_quote">Accept ' + PH.money(o.quoted_amount) + '</button><button class="c-btn c-btn-light" data-act="decline_quote">Decline</button></div>';
        }
        if (o.can_submit_payment && o.status !== 'quoted') {
            h += '<div class="mt-3 p-3 rounded-4" style="background:#F8FAFC;border:1px solid var(--c-border)"><div class="fw-bold mb-1"><i class="bi bi-wallet2"></i> ' + (o.payment_mode === 'advance_required' ? 'Advance payment required' : 'Pay in advance (optional)') + '</div>'
                + (o.shop.payment_instructions ? '<p class="small mb-2" style="white-space:pre-line">' + PH.esc(o.shop.payment_instructions) + '</p>' : '')
                + (o.payment_status === 'pending_verification' ? '<p class="small text-success mb-0"><i class="bi bi-check2"></i> Thanks — the shop is checking your payment.</p>'
                : '<form data-pay class="row g-2"><div class="col-6"><select class="form-select" name="method"><option value="jazzcash">JazzCash</option><option value="easypaisa">Easypaisa</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></div>'
                + '<div class="col-6"><input class="form-control" name="reference" placeholder="Transaction ID"></div><div class="col-12"><label class="form-label small mb-1">Screenshot (optional)</label><input class="form-control" type="file" name="proof" accept="image/*,application/pdf"></div>'
                + '<div class="col-12"><button class="c-btn c-btn-block">I have paid</button></div></form>') + '</div>';
        }
        h += '</div>';
        // progress
        if (!closedBad) {
            h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-list-check"></i> Progress</div><ol class="c-track">';
            o.steps.forEach(function (s, i) {
                var done = i <= stepIndex, at = hist[s.key];
                h += '<li class="' + (done ? 'is-done' : '') + (i === stepIndex ? ' is-current' : '') + '">' + PH.esc(s.label) + (at && done ? '<small>' + new Date(at.created_at.replace(' ', 'T')).toLocaleString() + (at.note ? ' · ' + PH.esc(at.note) : '') + '</small>' : '') + '</li>';
            });
            h += '</ol></div>';
        }
        // items
        h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-files"></i> Items</div><div class="c-items">';
        o.items.forEach(function (it) {
            var bits = [it.paper_size || it.custom_size, it.color ? label(it.color) : null, it.sides ? label(it.sides) : null, it.paper_type, it.gsm].filter(Boolean).join(' · ');
            h += '<div class="c-item"><i class="bi bi-file-earmark-text fs-4" style="color:var(--c-brand)"></i><div class="flex-grow-1"><strong class="d-block">' + PH.esc(it.title) + '</strong><small class="c-muted">' + PH.esc(it.category || '') + ' · ' + PH.esc(bits)
                + '<br>' + (it.quantity > 1 ? it.quantity + ' pieces' : it.pages + ' page' + (it.pages > 1 ? 's' : '') + ' × ' + it.copies) + (it.finishing.length ? ' · ' + PH.esc(it.finishing.join(', ')) : '') + '</small></div>'
                + (it.estimated_price !== null ? '<strong>' + PH.money(it.estimated_price) + '</strong>' : '') + '</div>';
        });
        h += '</div>' + (o.special_instructions ? '<p class="small c-muted mt-2 mb-0"><strong>Your note:</strong> ' + PH.esc(o.special_instructions) + '</p>' : '') + '</div>';
        // shop
        var wa = (o.shop.whatsapp || o.shop.phone || '').replace(/\D/g, '').replace(/^0/, '92');
        h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-shop"></i> ' + PH.esc(o.shop.name) + '</div><p class="small c-muted mb-2">' + PH.esc(o.shop.address) + '</p><div class="d-flex gap-2 flex-wrap">'
            + '<a class="c-btn c-btn-sm c-btn-light" href="tel:' + PH.esc(o.shop.phone) + '"><i class="bi bi-telephone"></i> Call</a>'
            + (wa ? '<a class="c-btn c-btn-sm c-btn-light" target="_blank" rel="noopener" href="https://wa.me/' + wa + '?text=' + encodeURIComponent('About my order ' + o.order_number) + '"><i class="bi bi-whatsapp"></i> WhatsApp</a>' : '')
            + '<button class="c-btn c-btn-sm c-btn-light" data-copy="' + PH.esc(o.tracking_url) + '"><i class="bi bi-link-45deg"></i> Copy tracking link</button>'
            + '<button class="c-btn c-btn-sm c-btn-light" onclick="PH.enableNotifications()"><i class="bi bi-bell"></i> Notify me</button></div></div>';
        if (o.can_cancel) { h += '<button class="c-btn c-btn-danger c-btn-block" data-act="cancel"><i class="bi bi-x-circle"></i> Cancel order</button>'; }
        root.innerHTML = h;
    }
    function load(quiet) {
        PH.api('GET', '/orders/' + encodeURIComponent(number) + '?t=' + encodeURIComponent(t)).then(function (r) {
            if (!r.status) { if (!quiet) { root.innerHTML = '<div class="c-notice">' + PH.esc(r.message) + '</div>'; } return; }
            if (lastStatus && lastStatus !== r.data.status) {
                PH.toast(r.data.status_label, 'info');
                if (window.Notification && Notification.permission === 'granted') { try { new Notification('Order ' + r.data.order_number, { body: r.data.status_label }); } catch (e) { /* ignore */ } }
            }
            lastStatus = r.data.status;
            render(r.data);
        });
    }
    root.addEventListener('click', function (e) {
        var b = e.target.closest('[data-act]');
        if (!b) { return; }
        var act = b.dataset.act, msg = { cancel: 'Cancel this order?', decline_quote: 'Decline the price? The order will be cancelled.', accept_quote: 'Accept this price and confirm the order?' }[act];
        Swal.fire({ text: msg, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes', confirmButtonColor: getComputedStyle(document.documentElement).getPropertyValue('--c-brand') }).then(function (res) {
            if (!res.isConfirmed) { return; }
            b.classList.add('is-loading');
            PH.api('POST', '/orders/' + encodeURIComponent(number) + '/action', { action: act, t: t }).then(function (r) {
                b.classList.remove('is-loading');
                if (!r.status) { PH.alert(r.message); return; }
                PH.toast(r.message); render(r.data); lastStatus = r.data.status;
            });
        });
    });
    root.addEventListener('submit', function (e) {
        var f = e.target.closest('[data-pay]');
        if (!f) { return; }
        e.preventDefault();
        var fd = new FormData(f), btn = f.querySelector('button');
        fd.append('t', t);
        btn.classList.add('is-loading');
        PH.upload('/orders/' + encodeURIComponent(number) + '/payment', fd).then(function (r) {
            btn.classList.remove('is-loading');
            if (!r.status) { PH.alert(r.message); return; }
            PH.toast(r.message); render(r.data);
        });
    });
    load();
    setInterval(function () { if (!document.hidden) { load(true); } }, 20000);
    document.addEventListener('ph:notifications', function () { load(true); });
})();
