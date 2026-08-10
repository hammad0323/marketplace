/* Wanderly — AJAX filtering for the search results page. */
(function ($) {
  'use strict';

  var $form = $('#filter-form');
  var $results = $('#search-results');
  var $sort = $('#sort-select');
  if (!$form.length) return;

  var debounceTimer = null;

  function currentFilters(page) {
    var params = $form.serializeArray();
    params.push({ name: 'sort', value: $sort.val() });
    params.push({ name: 'page', value: page || 1 });
    return params;
  }

  function runSearch(page) {
    var params = currentFilters(page);
    $results.css('opacity', 0.5);
    $.get('/ajax/search-results.php', $.param(params))
      .done(function (html) {
        $results.html(html);
        $results.css('opacity', 1);
        var qs = $.param(params.filter(function (p) { return p.value !== '' && p.value !== '0'; }));
        var newUrl = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', newUrl);
      })
      .fail(function () {
        $results.css('opacity', 1);
        showToast('Could not load results. Please try again.', 'danger');
      });
  }

  $form.on('change', '.filter-input', function () {
    runSearch(1);
  });
  $form.on('input', 'input[name="min_price"], input[name="max_price"]', function () {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function () { runSearch(1); }, 500);
  });
  $sort.on('change', function () {
    runSearch(1);
  });
  $('#clear-filters').on('click', function () {
    $form[0].reset();
    $form.find('input[type="hidden"]').each(function () {
      if (this.name !== 'destination' && this.name !== 'lat' && this.name !== 'lng') $(this).val('');
    });
    runSearch(1);
  });
  $(document).on('click', '.page-link', function (e) {
    e.preventDefault();
    runSearch($(this).data('page'));
    $('html, body').animate({ scrollTop: $results.offset().top - 100 }, 300);
  });

  // ---- Destination autocomplete (used on homepage + search page) -----
  $(document).on('input', '[data-autocomplete]', function () {
    var $input = $(this);
    var q = $input.val();
    clearTimeout($input.data('debounce'));
    var $box = $input.data('suggest-box');
    if (!$box) {
      $box = $('<div class="autocomplete-box"></div>').css({
        position: 'absolute', top: '100%', left: 0, right: 0, background: '#fff',
        border: '1px solid var(--border)', borderRadius: '12px', boxShadow: 'var(--shadow-lg)',
        marginTop: '6px', zIndex: 50, overflow: 'hidden', display: 'none'
      });
      $input.closest('.search-field').css('position', 'relative').append($box);
      $input.data('suggest-box', $box);
    }
    if (q.length < 2) { $box.hide(); return; }
    $input.data('debounce', setTimeout(function () {
      $.get('/ajax/autocomplete.php', { q: q }).done(function (items) {
        if (!items.length) { $box.hide(); return; }
        $box.empty();
        items.forEach(function (item) {
          $('<a>').css({ display: 'flex', gap: '10px', alignItems: 'center', padding: '10px 14px', fontSize: '13.5px', color: 'var(--ink)' })
            .attr('href', item.url)
            .html('<i class="bi ' + item.icon + '" style="color:var(--purple);"></i> ' + item.label + ' <span style="margin-left:auto;color:var(--ink-mute);font-size:11px;">' + item.type + '</span>')
            .appendTo($box);
        });
        $box.show();
      });
    }, 250));
  });
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.search-field').length) {
      $('.autocomplete-box').hide();
    }
  });
})(jQuery);
