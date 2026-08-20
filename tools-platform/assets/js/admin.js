/**
 * admin.js — shared behavior across the Admin Panel: DataTables init,
 * Select2 init, slug auto-generation with a manual-edit + SEO warning,
 * character counters, FAQ/example/formula repeaters, and the live
 * SEO score widget (AJAX call to admin/ajax/seo-score.php).
 */
$(function () {
  $('.tp-datatable').DataTable({ pageLength: 25, order: [] });
  $('.tp-select2').select2({ width: '100%' });

  // ---- Slug auto-generation ----
  const $nameField = $('[data-slug-source]');
  const $slugField = $('[data-slug-target]');
  let slugTouched = $slugField.data('existing') ? true : false;

  $slugField.on('input', () => { slugTouched = true; });
  $nameField.on('input', function () {
    if (slugTouched) return;
    $slugField.val(slugify($(this).val()));
  });
  function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  if ($slugField.data('existing')) {
    $slugField.on('change', function () {
      if ($(this).val() !== $(this).data('original')) {
        Swal.fire({
          icon: 'warning',
          title: 'Changing the URL slug',
          text: 'This changes the tool/page URL. Search engines that already indexed the old URL will need a 301 redirect (set one up on the Redirects page) or you may lose ranking.',
          confirmButtonText: 'I understand',
        });
      }
    });
  }

  // ---- Character counters ----
  function wireCounter(selector, max) {
    $(selector).each(function () {
      const $el = $(this);
      const $counter = $('<div class="form-text char-counter"></div>').insertAfter($el);
      function update() {
        const len = $el.val().length;
        $counter.text(`${len} / ${max} characters` + (len < max * 0.7 ? ' — recommended ' + Math.round(max * 0.8) + '-' + max : ''));
        $counter.toggleClass('text-danger', len > max);
      }
      $el.on('input', update);
      update();
    });
  }
  wireCounter('[data-counter-seo-title]', 60);
  wireCounter('[data-counter-meta-description]', 160);

  // ---- Repeaters (FAQ / Examples / Formulas) ----
  $(document).on('click', '[data-repeater-add]', function () {
    const targetSel = $(this).data('repeater-add');
    const $container = $(targetSel);
    const $template = $container.find('[data-repeater-template]').first();
    const $clone = $template.clone().removeAttr('data-repeater-template').removeClass('d-none');
    $clone.find('input, textarea').val('');
    $container.append($clone);
  });
  $(document).on('click', '[data-repeater-remove]', function () {
    $(this).closest('[data-repeater-row]').remove();
  });

  // ---- Live SEO score ----
  const $seoForm = $('#tpToolForm, #tpCategoryForm, #tpPageForm').first();
  const $scoreWidget = $('#tpSeoScoreWidget');
  let seoDebounce = null;
  function refreshSeoScore() {
    if (!$scoreWidget.length) return;
    clearTimeout(seoDebounce);
    seoDebounce = setTimeout(() => {
      const payload = {
        title: $('[name=seo_title]').val() || $('[name=name]').val(),
        meta_description: $('[name=meta_description]').val(),
        focus_keyword: $('[name=focus_keyword]').val(),
        slug: $('[data-slug-target]').val(),
        content_text: $('[name=introduction]').val() || $('[name=description]').val() || '',
        h1: $('[name=name]').val(),
        has_faq: $('[data-repeater-row]:visible').filter('[data-repeater-type=faq]').length > 0 ? 1 : 0,
        related_count: $('[name="related_tools[]"]').val() ? $('[name="related_tools[]"]').val().length : 0,
        image: $('[name=featured_image]').val(),
        image_alt: $('[name=image_alt_text]').val(),
        canonical: $('[name=canonical_url]').val(),
        robots: $('[name=robots]').val(),
        schema_type: $('[name=schema_type]').val(),
        og_title: $('[name=og_title]').val(),
        twitter_title: $('[name=twitter_title]').val(),
        has_formula: $('[name=formula]').val() ? 1 : 0,
        has_examples: $('[name="example_input[]"]').val() ? 1 : 0,
        has_how_to: $('[name=how_to_use]').val() ? 1 : 0,
      };
      $.post(window.tpAdminBase + '/admin/ajax/seo-score.php', payload, function (res) {
        renderSeoScore(res);
      }, 'json');
    }, 400);
  }

  function renderSeoScore(res) {
    const color = res.score >= 86 ? '#10B981' : res.score >= 71 ? '#7C3AED' : res.score >= 51 ? '#A78BFA' : res.score >= 31 ? '#D97706' : '#DC2626';
    let html = `<div class="d-flex justify-content-between mb-1"><strong>SEO Score</strong><span>${res.score}% — ${res.grade}</span></div>`;
    html += `<div class="tp-seo-score-bar mb-3"><div class="tp-seo-score-fill" style="width:${res.score}%;background:${color};"></div></div>`;
    const groups = {};
    res.checks.forEach((c) => { (groups[c.group] = groups[c.group] || []).push(c); });
    Object.keys(groups).forEach((g) => {
      html += `<div class="small fw-bold mt-2 mb-1">${g}</div>`;
      groups[g].forEach((c) => {
        html += `<div class="small ${c.pass ? 'tp-check-pass' : 'tp-check-fail'}"><i class="bi ${c.pass ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i> ${c.label}</div>`;
      });
    });
    $scoreWidget.html(html);
  }

  if ($scoreWidget.length) {
    $(document).on('input change', 'input, textarea, select', refreshSeoScore);
    refreshSeoScore();
  }

  // ---- Bulk actions ----
  $('#selectAllRows').on('change', function () {
    $('.row-checkbox').prop('checked', $(this).is(':checked'));
  });
  $('#applyBulkAction').on('click', function (e) {
    const $form = $(this).closest('form');
    const action = $('#bulkActionSelect').val();
    const ids = $('.row-checkbox:checked').map(function () { return $(this).val(); }).get();
    e.preventDefault();
    if (!action || !ids.length) {
      Swal.fire({ icon: 'info', title: 'Select an action and at least one row.' });
      return false;
    }
    if (action === 'delete') {
      Swal.fire({
        icon: 'warning', title: `Delete ${ids.length} item(s)?`, text: 'This cannot be undone.',
        showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#DC2626',
      }).then((r) => { if (r.isConfirmed) $form.get(0).submit(); });
    } else {
      $form.get(0).submit();
    }
  });
});

window.tpAdminBase = document.body.dataset.adminBase || '';
