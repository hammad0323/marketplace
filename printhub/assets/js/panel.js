/* PrintHub panels: confirmations, AJAX helpers, notification polling with browser notifications. */
(function ($) {
    'use strict';
    var csrf = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({ headers: { 'X-CSRF-Token': csrf, 'X-Requested-With': 'XMLHttpRequest' } });

    // Forms / buttons that need confirmation: data-confirm="Message"
    $(document).on('submit', 'form[data-confirm]', function (e) {
        var form = this;
        if (form.dataset.confirmed) { return; }
        e.preventDefault();
        Swal.fire({ text: form.dataset.confirm, icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, continue', confirmButtonColor: '#0F766E' })
            .then(function (r) { if (r.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); } });
    });
    $(document).on('click', '[data-copy]', function () {
        var t = $(this).data('copy'), $b = $(this);
        navigator.clipboard && navigator.clipboard.writeText(String(t)).then(function () {
            var h = $b.html(); $b.html('<i class="bi bi-check2"></i> Copied'); setTimeout(function () { $b.html(h); }, 1500);
        });
    });

    // Notification polling (every 30 s). Shows a browser notification when permission was granted.
    var $bell = $('[data-notif-bell]'), feed = $('body').data('notif-feed') || window.PH_NOTIF_FEED, last = 0, first = true;
    function poll() {
        if (!feed) { return; }
        $.getJSON(feed, { since: last }).done(function (r) {
            if (!r || !r.status) { return; }
            var d = r.data, $c = $bell.find('[data-notif-count]');
            if (d.unread) { if (!$c.length) { $c = $('<em data-notif-count></em>').appendTo($bell); } $c.text(d.unread); } else { $c.remove(); }
            if (d.items.length) {
                if (!first && window.Notification && Notification.permission === 'granted') {
                    d.items.slice(0, 3).forEach(function (n) { try { new Notification(n.title, { body: n.body || '', tag: 'ph-' + n.id }); } catch (e) { /* ignore */ } });
                }
                last = Math.max.apply(null, d.items.map(function (n) { return n.id; }).concat([last]));
                if (!first) { $(document).trigger('ph:notifications', [d.items]); }
            }
            first = false;
        });
    }
    if (feed) { poll(); setInterval(poll, 30000); }
    $(document).on('click', '[data-enable-notifications]', function () {
        if (!window.Notification) { Swal.fire({ text: 'This browser does not support notifications.', icon: 'info' }); return; }
        Notification.requestPermission().then(function (p) {
            Swal.fire({ icon: p === 'granted' ? 'success' : 'info', text: p === 'granted' ? 'Notifications are on for this browser.' : 'Notifications were not allowed. You can still see them in the bell menu.' });
        });
    });
})(jQuery);
