(function ($) {
    'use strict';
    var $bell = $('#notif-bell-btn');
    if (!$bell.length) return;

    var lastUnread = null;
    var audioCtx = null;

    // Browsers block audio until a user gesture happens somewhere on the
    // page; lazily create (and keep reusing) one AudioContext on the first
    // click so the "ding" is ready to play once a notification arrives.
    $(document).one('click', function () {
        try { audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { /* no Web Audio support */ }
    });

    function playDing() {
        if (!audioCtx) return;
        var now = audioCtx.currentTime;
        [880, 1318.5].forEach(function (freq, i) {
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            gain.gain.setValueAtTime(0, now + i * 0.09);
            gain.gain.linearRampToValueAtTime(0.18, now + i * 0.09 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.001, now + i * 0.09 + 0.35);
            osc.connect(gain).connect(audioCtx.destination);
            osc.start(now + i * 0.09);
            osc.stop(now + i * 0.09 + 0.4);
        });
    }

    function ringBell() {
        $bell.addClass('ringing');
        setTimeout(function () { $bell.removeClass('ringing'); }, 650);
        playDing();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function renderList(notifications) {
        var $list = $('#notif-list');
        if (!notifications.length) {
            $list.html('<div style="padding:12px;font-size:13px;color:var(--color-text-muted);">No notifications yet.</div>');
            return;
        }
        $list.html(notifications.map(function (n) {
            return '<a href="' + n.link + '" style="display:block;padding:10px 12px;white-space:normal;' + (n.is_read == 0 ? 'background:rgba(12,107,93,0.05);' : '') + '">' +
                '<strong style="display:block;font-size:13px;">' + n.title + '</strong>' +
                '<span style="font-size:12px;color:var(--color-text-muted);">' + n.message + '</span></a>';
        }).join(''));
    }

    function poll() {
        $.getJSON('/ajax/notifications-check.php', function (res) {
            if (!res.success) return;
            renderList(res.notifications);
            $('#notif-badge-dot').toggle(res.unread_count > 0);
            if (lastUnread !== null && res.unread_count > lastUnread) {
                ringBell();
            }
            lastUnread = res.unread_count;
        });
    }

    poll();
    setInterval(poll, 20000);
})(jQuery);
