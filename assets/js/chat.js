(function ($) {
    'use strict';
    var $shell = $('#chat-shell');
    if (!$shell.length) return;

    var role = window.APP.role; // 'patient' or 'doctor'
    var active = null; // { conversationId, doctorId }
    var lastMessageId = 0;
    var pollTimer = null;
    var listTimer = null;

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function renderList(conversations) {
        var $list = $('#chat-list-items').empty();
        if (!conversations.length) {
            $list.html('<div class="empty-state" style="padding:32px 16px;"><i class="ri-chat-3-line"></i><h4 style="font-size:15px;">No conversations yet</h4></div>');
            return;
        }
        conversations.forEach(function (c) {
            var key = role === 'patient' ? c.doctor_id : c.patient_id;
            var $item = $('<div class="chat-list-item"></div>')
                .attr('data-conversation-id', c.conversation_id)
                .attr('data-key', key);
            if (active && active.conversationId === c.conversation_id) $item.addClass('active');
            var onlineDot = role === 'patient' ? '<span class="status-dot' + (c.online ? ' online' : '') + '" style="margin-right:4px;"></span>' : '';
            $item.html(
                '<img src="' + c.avatar + '" alt="">' +
                '<div class="meta">' +
                '<div class="name-row"><strong>' + onlineDot + escapeHtml(c.name) + '</strong>' + (c.unread_count ? '<span class="unread-badge">' + c.unread_count + '</span>' : '') + '</div>' +
                '<div class="preview">' + escapeHtml(c.last_message) + '</div>' +
                '</div>'
            );
            $list.append($item);
        });
    }

    function loadConversations(selectKey) {
        $.getJSON('/ajax/chat-conversations.php', function (res) {
            if (!res.success) return;
            renderList(res.conversations);
            if (selectKey != null && !active) {
                var match = res.conversations.find(function (c) {
                    return (role === 'patient' ? c.doctor_id : c.patient_id) == selectKey;
                });
                // No conversation yet (e.g. a doctor starting a fresh chat from "My Patients") —
                // open it anyway; fetchMessages/chat-send look up or create it on demand.
                openConversation(match ? match.conversation_id : null, selectKey);
            }
        });
    }

    function bubbleHtml(m) {
        var att = '';
        if (m.attachment_url) {
            if (m.message_type === 'image') {
                att = '<img class="chat-attachment" src="' + m.attachment_url + '" alt="attachment">';
            } else {
                att = '<a class="chat-file" href="' + m.attachment_url + '" target="_blank"><i class="ri-file-line"></i> Attachment</a>';
            }
        }
        return '<div class="chat-bubble-row' + (m.is_mine ? ' mine' : '') + '" data-msg-id="' + m.id + '">' +
            '<div class="chat-bubble">' + att + (m.message ? '<div>' + escapeHtml(m.message) + '</div>' : '') + '<time>' + m.time_label + '</time></div></div>';
    }

    function openConversation(conversationId, key) {
        clearInterval(pollTimer);
        active = { conversationId: conversationId, key: key };
        lastMessageId = 0;
        $('#chat-list-items .chat-list-item').removeClass('active');
        $('#chat-list-items .chat-list-item[data-key="' + key + '"]').addClass('active');
        $shell.removeClass('show-list');

        fetchMessages(true);
        pollTimer = setInterval(function () { fetchMessages(false); }, 4000);
    }

    /** Always builds params from the CURRENT active conversation + lastMessageId — never a stale snapshot, so polling only ever fetches what's new. */
    function fetchMessages(isInitial) {
        if (!active) return;
        var params = role === 'patient' ? { doctor_id: active.key } : { patient_id: active.key };
        if (!isInitial && lastMessageId) params.after_id = lastMessageId;

        $.getJSON('/ajax/chat-messages.php', params, function (res) {
            if (!res.success) {
                if (isInitial) showToast('error', 'Could not load', res.message);
                return;
            }
            active.conversationId = res.conversation_id;
            if (isInitial) {
                $('#chat-main').show();
                $('#chat-empty-state').hide();
                $('#chat-partner-name').text(res.partner.name);
                $('#chat-partner-avatar').attr('src', res.partner.avatar);
                if (role === 'patient') {
                    $('#chat-partner-status').html('<span class="status-dot' + (res.partner.online ? ' online' : '') + '"></span> ' + (res.partner.online ? 'Online now' : res.partner.hours));
                } else {
                    $('#chat-partner-status').text('');
                    active.isBlocked = !!res.partner.is_blocked;
                    updateBlockUi();
                }
                $('#chat-thread').empty();
            }
            if (res.messages.length) {
                var $thread = $('#chat-thread');
                var wasAtBottom = $thread[0].scrollHeight - $thread.scrollTop() - $thread.outerHeight() < 60;
                res.messages.forEach(function (m) {
                    $thread.append(bubbleHtml(m));
                    lastMessageId = Math.max(lastMessageId, m.id);
                });
                if (isInitial || wasAtBottom) $thread.scrollTop($thread[0].scrollHeight);
            }
        });
    }

    // A block only stops the PATIENT from sending further messages; the doctor can still
    // send (e.g. a closing note), so the doctor's own #chat-form always stays enabled.
    function updateBlockUi() {
        var blocked = !!(active && active.isBlocked);
        $('#chat-block-btn').text(blocked ? 'Unblock' : 'Block').toggleClass('btn-outline', !blocked).toggleClass('btn-danger', blocked);
        $('#chat-blocked-notice').toggle(blocked);
    }

    function setBlocked(blocked) {
        if (!active) return;
        $.post('/ajax/doctor-chat-block.php', { csrf_token: window.APP.csrfToken, patient_id: active.key, blocked: blocked ? 1 : 0 }, null, 'json')
            .done(function (res) {
                if (res.success) {
                    active.isBlocked = blocked;
                    updateBlockUi();
                    showToast('success', blocked ? 'Blocked' : 'Unblocked', res.message);
                } else {
                    showToast('error', 'Could not update', res.message);
                }
            }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    }

    $('#chat-block-btn').on('click', function () { setBlocked(!(active && active.isBlocked)); });
    $('#chat-unblock-inline-btn').on('click', function () { setBlocked(false); });

    $(document).on('click', '.chat-list-item', function () {
        openConversation($(this).data('conversation-id'), $(this).data('key'));
    });

    $('#chat-back-btn').on('click', function () { $shell.addClass('show-list'); });

    $('#chat-form').on('submit', function (e) {
        e.preventDefault();
        if (!active) return;
        var text = $('#chat-input').val().trim();
        var file = $('#chat-attach-input')[0].files[0];
        if (!text && !file) return;

        var formData = new FormData();
        formData.append('csrf_token', window.APP.csrfToken);
        formData.append('message', text);
        if (role === 'patient') formData.append('doctor_id', active.key);
        else formData.append('patient_id', active.key);
        if (file) formData.append('attachment', file);

        $('#chat-input').val('').css('height', 'auto');
        $('#chat-attach-input').val('');

        $.ajax({ url: '/ajax/chat-send.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                if (res.success) {
                    active.conversationId = res.conversation_id;
                    fetchMessages(false);
                    loadConversations();
                } else {
                    showToast('error', 'Could not send', res.message);
                }
            }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });

    $('#chat-attach-input').on('change', function () {
        if (this.files[0]) showToast('info', 'Attached', this.files[0].name);
    });

    var urlParams = new URLSearchParams(window.location.search);
    var preselect = role === 'patient' ? urlParams.get('doctor_id') : urlParams.get('patient_id');
    loadConversations(preselect);
    listTimer = setInterval(function () { loadConversations(); }, 12000);
})(jQuery);
