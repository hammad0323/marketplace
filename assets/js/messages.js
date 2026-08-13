/* Toursity — message thread send + lightweight polling. */
(function ($) {
  'use strict';
  var $thread = $('#thread');
  var $form = $('#message-form');
  if (!$thread.length) return;

  var conversationId = $thread.data('conversation');
  var csrf = $thread.data('csrf');
  var lastId = 0;
  $thread.find('[data-id]').each(function () { lastId = Math.max(lastId, $(this).data('id')); });

  function scrollToBottom() {
    $thread.scrollTop($thread[0].scrollHeight);
  }
  scrollToBottom();

  function appendMessage(m) {
    var align = m.mine ? 'flex-end' : 'flex-start';
    var bg = m.mine ? 'background:var(--gradient-purple);color:#fff;' : 'background:var(--bg);color:var(--ink);';
    var html = '<div data-id="' + m.id + '" style="display:flex;justify-content:' + align + ';margin-bottom:10px;">'
      + '<div style="max-width:70%;padding:10px 14px;border-radius:14px;font-size:13.5px;' + bg + '">' + m.message_text + '</div></div>';
    $thread.append(html);
    lastId = Math.max(lastId, m.id);
    scrollToBottom();
  }

  $form.on('submit', function (e) {
    e.preventDefault();
    var $input = $form.find('input[name="message_text"]');
    var text = $input.val().trim();
    if (!text) return;
    $input.val('').prop('disabled', true);

    $.post(window.appUrl('/ajax/send-message.php'), { conversation_id: conversationId, message_text: text, csrf_token: csrf })
      .done(function (res) {
        if (res.ok) appendMessage(res.message);
        else showToast(res.error || 'Could not send message.', 'danger');
      })
      .fail(function () { showToast('Could not send message.', 'danger'); })
      .always(function () { $input.prop('disabled', false).focus(); });
  });

  setInterval(function () {
    $.get(window.appUrl('/ajax/messages-poll.php'), { conversation_id: conversationId, after_id: lastId })
      .done(function (res) {
        if (res.ok && res.items.length) {
          res.items.forEach(appendMessage);
        }
      });
  }, 5000);
})(jQuery);
