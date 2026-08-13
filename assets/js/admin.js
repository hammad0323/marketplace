/* Toursity admin — live search suggestions for the customer/provider search boxes. */
(function ($) {
  'use strict';

  var statusColor = { active: '#065F46', approved: '#065F46', pending: '#92400E', blocked: '#991B1B', rejected: '#991B1B', suspended: '#991B1B' };

  $('input[data-suggest]').each(function () {
    var $input = $(this);
    var type = $input.data('suggest');
    var $wrap = $input.parent();
    if ($wrap.css('position') === 'static') {
      $wrap.css('position', 'relative');
    }
    var $menu = $('<div class="search-suggest-menu"></div>').appendTo($wrap);
    var timer = null;

    function hide() {
      $menu.hide().empty();
    }

    function render(items) {
      $menu.empty();
      if (!items.length) {
        hide();
        return;
      }
      items.forEach(function (item) {
        var color = statusColor[item.status] || '#7A7590';
        var $row = $(
          '<div class="search-suggest-row">' +
            '<div><strong></strong><div class="sub"></div></div>' +
            '<span class="dot" style="background:' + color + ';"></span>' +
          '</div>'
        );
        $row.find('strong').text(item.label);
        $row.find('.sub').text(item.sublabel);
        $row.on('mousedown', function (e) {
          e.preventDefault();
          $input.val(item.label);
          hide();
          $input.closest('form').trigger('submit');
        });
        $menu.append($row);
      });
      $menu.show();
    }

    $input.on('input', function () {
      var q = $input.val().trim();
      clearTimeout(timer);
      if (q.length < 2) {
        hide();
        return;
      }
      timer = setTimeout(function () {
        $.get(window.appUrl('/ajax/admin-search-suggest.php'), { type: type, q: q }).done(render).fail(hide);
      }, 220);
    });

    $input.on('blur', function () {
      setTimeout(hide, 150);
    });
    $input.on('focus', function () {
      if ($input.val().trim().length >= 2 && $menu.children().length) {
        $menu.show();
      }
    });
  });
})(jQuery);
