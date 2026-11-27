/* The Hashir — back office (Super Admin & portal) */
(function ($) {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  var BO = document.body.getAttribute('data-bo-base') || '';
  var CSRF = $('meta[name="csrf-token"]').attr('content');
  var TEAL = '#005957';
  window.TH_CSRF = CSRF;

  function confirmBox(text) {
    return Swal.fire({ icon: 'question', text: text, showCancelButton: true, confirmButtonText: 'Yes, continue', cancelButtonText: 'Cancel', confirmButtonColor: TEAL, reverseButtons: true })
      .then(function (r) { return r.isConfirmed; });
  }
  window.thConfirm = confirmBox;

  // ------------------------------------------------------------- confirmations
  $(document).on('submit', 'form[data-confirm]', function (e) {
    var form = this;
    if (form.__ok) { return; }
    e.preventDefault();
    confirmBox($(form).data('confirm')).then(function (ok) { if (ok) { form.__ok = true; form.submit(); } });
  });
  $(document).on('click', '[data-confirm-click]', function (e) {
    var btn = this;
    if (btn.__ok) { btn.__ok = false; return; }
    e.preventDefault();
    confirmBox($(btn).data('confirm-click')).then(function (ok) {
      if (!ok) { return; }
      var form = btn.form;
      if (form) {
        if (btn.name) { $('<input type="hidden">').attr('name', btn.name).val(btn.value).appendTo(form); }
        $(form).find('[data-blocks-json]').length && $(form).trigger('th:serialize');
        form.__ok = true;
        form.submit();
      }
    });
  });
  // Small POST actions without a form: data-post-action='{"action":"delete","id":3}'
  $(document).on('click', '[data-post-action]', function () {
    var data = $(this).data('post-action');
    var text = $(this).data('confirm-text');
    var go = function () {
      var $f = $('<form method="post">').attr('action', window.location.href);
      $('<input type="hidden" name="csrf">').val(CSRF).appendTo($f);
      $.each(data, function (k, v) { $('<input type="hidden">').attr('name', k).val(v).appendTo($f); });
      $f.appendTo(document.body).trigger('submit');
    };
    if (text) { confirmBox(text).then(function (ok) { if (ok) { go(); } }); } else { go(); }
  });

  // ------------------------------------------------------------------ select2
  $('[data-s2]').each(function () {
    $(this).select2({ width: '100%', placeholder: $(this).data('placeholder') || '', allowClear: !this.multiple && !this.required });
  });
  function initCity($el) {
    $el.select2({
      width: '100%', allowClear: !$el.prop('required'), placeholder: 'Choose a city…', minimumInputLength: 2,
      ajax: { url: BASE + '/api/cities', dataType: 'json', delay: 200, data: function (p) { return { q: p.term, all: 1 }; },
        processResults: function (r) { return { results: (r.results || []).map(function (c) { return { id: c.city_id, text: c.city_title + (c.state_title ? ', ' + c.state_title : '') + (c.country_title ? ' · ' + c.country_title : '') }; }) }; } },
      language: { inputTooShort: function () { return 'Type at least 2 letters'; } }
    });
  }
  $('[data-bo-city]').each(function () { initCity($(this)); });
  function initVenue($el) {
    $el.select2({
      width: '100%', allowClear: !$el.prop('multiple'), placeholder: $el.data('placeholder') || 'Search…', minimumInputLength: 1,
      ajax: { url: BO + '/api', dataType: 'json', delay: 200, data: function (p) { return { do: 'venues', q: p.term, role: $el.data('role') || '' }; },
        processResults: function (r) { return { results: r.results || [] }; } }
    });
  }
  $('[data-bo-venue]').each(function () { initVenue($(this)); });
  // GET filter forms without a submit button submit on change
  $('form[method="get"]').each(function () {
    var $f = $(this);
    if (!$f.find('button, [type="submit"]').length) { $f.find('select').on('change', function () { $f.trigger('submit'); }); }
  });

  // ------------------------------------------------------------- rich editors
  window.thMakeEditor = function (holder, textarea, opts) {
    if (!window.Quill) { $(textarea).removeClass('d-none').addClass('form-control'); $(holder).remove(); return null; }
    var q = new Quill(holder, {
      theme: 'snow',
      placeholder: (opts && opts.placeholder) || 'Write here…',
      modules: { toolbar: [[{ header: [2, 3, 4, false] }], ['bold', 'italic', 'underline'], [{ list: 'ordered' }, { list: 'bullet' }], ['blockquote', 'link'], [{ align: [] }], ['clean']] }
    });
    if (textarea.value) { q.clipboard.dangerouslyPasteHTML(textarea.value); }
    var sync = function () {
      var html = q.root.innerHTML;
      textarea.value = (html === '<p><br></p>' || q.getText().trim() === '') ? '' : html;
      $(textarea).trigger('change');
    };
    q.on('text-change', sync);
    return q;
  };
  $('[data-rich-editor]').each(function () {
    var ta = document.getElementById(this.getAttribute('data-target'));
    if (ta) { window.thMakeEditor(this, ta); }
  });

  // ------------------------------------------------------------- small helpers
  $(document).on('click', '[data-toggle-pass]', function () {
    var $i = $($(this).data('toggle-pass'));
    var show = $i.attr('type') === 'password';
    $i.attr('type', show ? 'text' : 'password');
    $(this).find('i').toggleClass('bi-eye bi-eye-slash');
  });
  $(document).on('click', '[data-gen-pass]', function () {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    var arr = new Uint32Array(14);
    window.crypto.getRandomValues(arr);
    var p = '';
    for (var i = 0; i < arr.length; i++) { p += chars[arr[i] % chars.length]; }
    p = p.slice(0, 11) + (arr[0] % 10) + p.slice(11);
    var sels = String($(this).data('gen-pass')).split(',');
    sels.forEach(function (s) { $(s).val(p).attr('type', 'text'); });
    Swal.fire({ icon: 'info', title: 'New password', html: 'Copy it now and share it securely:<br><code style="font-size:1.1rem">' + p + '</code>', confirmButtonColor: TEAL });
  });
  $(document).on('change', '[data-check-all]', function () {
    $(this).closest('table').find('tbody input[type="checkbox"]').prop('checked', this.checked);
  });
  $('[data-count]').each(function () {
    var $i = $(this), max = parseInt($i.data('count'), 10);
    var $c = $('<div class="form-text text-end char-count"></div>').insertAfter($i);
    var upd = function () { var n = $i.val().length; $c.text(n + ' / ' + max + ' recommended').toggleClass('text-danger', n > max); };
    $i.on('input', upd); upd();
  });
  // Slug from title
  var $slugT = $('[data-slug-target]');
  $slugT.on('input', function () { this.setAttribute('data-touched', '1'); });
  $('[data-slug-source]').on('input', function () {
    if ($slugT.attr('data-touched') === '1') { return; }
    $slugT.val(this.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''));
  });
  // Image previews
  $(document).on('change', '[data-img-input]', function () {
    var f = this.files && this.files[0];
    var $p = $(this).prev('.img-preview');
    if (!f || !$p.length) { return; }
    var r = new FileReader();
    r.onload = function (ev) { $p.find('img, .img-stack').remove(); $p.append($('<img alt="">').attr('src', ev.target.result)); };
    r.readAsDataURL(f);
  });
  // Date navigation
  $('[data-date-nav]').on('change', function () { if (this.value) { window.location.href = $(this).data('date-nav') + '&date=' + this.value; } });
  // Fill prayer form from previous day
  $(document).on('click', '[data-fill-times]', function () {
    var vals = $(this).data('fill-times');
    $.each(vals, function (k, v) { $('[name="t[' + k + ']"]').val(v); });
  });
  // Calculation method -> angles
  $('[data-calc-method]').on('change', function () {
    var $o = $(this).find(':selected'), custom = this.value === 'Custom';
    var $f = $(this).closest('form');
    if (!custom) {
      $f.find('[name="fajr"]').val($o.data('fajr'));
      $f.find('[name="isha"]').val($o.data('isha'));
      $f.find('[name="isha_min"]').val($o.data('isha-min'));
    }
    $f.find('[name="fajr"],[name="isha"],[name="isha_min"]').prop('readonly', !custom);
  }).trigger('change');
  $(document).on('change', '[name="save_mode"]', function () { $('[data-word-box]').prop('hidden', this.value !== 'all'); });
  // Detail form: limit categories to the chosen type
  function filterCats() {
    var gc = $('[data-gc-select]').val();
    var $s = $('[data-cat-select]');
    if (!$s.length) { return; }
    $s.find('option').each(function () {
      var ok = String($(this).data('gc')) === String(gc);
      $(this).prop('disabled', !ok);
      if (!ok) { $(this).prop('selected', false); }
    });
    if ($s.hasClass('select2-hidden-accessible')) { $s.select2('destroy'); }
    $s.select2({ width: '100%', placeholder: 'Choose categories' });
  }
  $('[data-gc-select]').on('change', filterCats);
  filterCats();
  // Blog: city guide fields changed -> save them too
  $('#guideData').on('input change', 'input, textarea', function () { $('[data-guide-flag]').val('1'); });
  // Settings: slides
  $('[data-add-slide]').on('click', function () {
    var $t = $('[data-slide-template]').first();
    $t.clone().removeAttr('data-slide-template').find('input').val('').end().insertBefore($t);
  });

  // --------------------------------------------------------------- map helpers
  function tileLayer() {
    var m = window.TH_MAP || {};
    return L.tileLayer(m.tiles || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: m.attribution || '&copy; OpenStreetMap contributors' });
  }
  window.thTileLayer = tileLayer;
  function defaultCenter() {
    var c = (window.TH_MAP && window.TH_MAP.center) || { lat: 43.65, lng: -79.38, zoom: 9 };
    return c;
  }
  var lastGeo = 0;
  function geocode(q) {
    // Nominatim usage policy: max 1 request per second
    var wait = Math.max(0, 1100 - (Date.now() - lastGeo));
    return new Promise(function (resolve) { setTimeout(resolve, wait); }).then(function () {
      lastGeo = Date.now();
      return $.getJSON('https://nominatim.openstreetmap.org/search', { format: 'json', limit: 1, q: q, addressdetails: 0 });
    });
  }
  window.thGeocode = geocode;

  // Venue form: map pin
  $('[data-geo-map]').each(function () {
    if (!window.L) { return; }
    var el = this, $form = $(el).closest('form');
    var $lat = $form.find('[data-geo-lat]'), $lng = $form.find('[data-geo-lng]'), $status = $form.find('[data-geo-status]');
    var c = defaultCenter();
    var has = $lat.val() !== '' && $lng.val() !== '';
    var map = L.map(el, { scrollWheelZoom: false }).setView(has ? [parseFloat($lat.val()), parseFloat($lng.val())] : [c.lat, c.lng], has ? 16 : c.zoom);
    tileLayer().addTo(map);
    var marker = null;
    function setPin(lat, lng, msg) {
      lat = Math.round(lat * 1e6) / 1e6; lng = Math.round(lng * 1e6) / 1e6;
      $lat.val(lat); $lng.val(lng);
      if (!marker) {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function () { var p = marker.getLatLng(); setPin(p.lat, p.lng, 'Pin moved. Save to confirm.'); });
      } else { marker.setLatLng([lat, lng]); }
      if (msg) { $status.text(msg); }
    }
    if (has) { setPin(parseFloat($lat.val()), parseFloat($lng.val())); }
    map.on('click', function (e) { setPin(e.latlng.lat, e.latlng.lng, 'Pin placed. Save to confirm.'); });
    $lat.add($lng).on('change', function () { if ($lat.val() && $lng.val()) { setPin(parseFloat($lat.val()), parseFloat($lng.val())); map.setView([$lat.val(), $lng.val()], 16); } });
    $form.find('[data-geo-lookup]').on('click', function () {
      var city = $form.find('[data-bo-city]').select2('data');
      var q = [$form.find('[data-geo-street]').val(), city && city[0] ? city[0].text.replace(/\s·.*$/, '') : '', $form.find('[data-geo-postal]').val()].filter(Boolean).join(', ');
      if (!q) { $status.text('Enter the street address and city first.'); return; }
      $status.text('Looking up the address…');
      geocode(q).then(function (r) {
        if (r && r[0]) { setPin(parseFloat(r[0].lat), parseFloat(r[0].lon), 'Suggested from the address — check the pin and drag it if needed, then save.'); map.setView([r[0].lat, r[0].lon], 17); }
        else { $status.text('No match found. Click the map to place the pin.'); }
      }, function () { $status.text('The address service is not reachable. Click the map to place the pin.'); });
    });
    setTimeout(function () { map.invalidateSize(); }, 300);
  });

  // Locations page
  function locRow($row, center) {
    var $m = $row.find('[data-loc-map]').prop('hidden', false);
    if (!$m.data('map')) {
      var map = L.map($m[0], { scrollWheelZoom: false }).setView(center, 17);
      tileLayer().addTo(map);
      var mk = L.marker(center, { draggable: true }).addTo(map);
      mk.on('dragend', function () { var p = mk.getLatLng(); $row.data('pos', [p.lat, p.lng]); $row.find('[data-loc-status]').text('Pin moved'); });
      $m.data('map', map).data('marker', mk);
    } else {
      $m.data('map').setView(center, 17);
      $m.data('marker').setLatLng(center);
    }
    $row.data('pos', center);
    $row.find('[data-loc-save]').prop('hidden', false);
  }
  function lookupRow($row) {
    var $s = $row.find('[data-loc-status]');
    if ($row.data('lat')) { locRow($row, [parseFloat($row.data('lat')), parseFloat($row.data('lng'))]); return Promise.resolve(); }
    $s.text('Looking up…');
    return geocode($row.data('address')).then(function (r) {
      if (r && r[0]) { locRow($row, [parseFloat(r[0].lat), parseFloat(r[0].lon)]); $s.html('<span class="text-warning-emphasis">Check the pin</span>'); }
      else { $s.html('<span class="text-danger">No match — open the profile to place the pin</span>'); }
    }, function () { $s.text('Service not reachable'); });
  }
  $(document).on('click', '[data-loc-lookup]', function () { lookupRow($(this).closest('[data-loc-row]')); });
  $('[data-geo-lookup-all]').on('click', function () {
    var rows = $('[data-loc-row]').toArray(), i = 0;
    var next = function () { if (i < rows.length) { lookupRow($(rows[i++])).then(next); } };
    next();
  });
  $(document).on('click', '[data-loc-save]', function () {
    var $row = $(this).closest('[data-loc-row]'), pos = $row.data('pos');
    if (!pos) { return; }
    $.post(window.location.href, { csrf: CSRF, venue_id: $row.data('id'), lat: pos[0], lng: pos[1] }, null, 'json').done(function (r) {
      if (r.ok) { $row.find('[data-loc-status]').html('<span class="text-success"><i class="bi bi-check-circle-fill"></i> Saved</span>'); $row.find('[data-loc-save]').prop('hidden', true); }
    }).fail(function () { Swal.fire({ icon: 'error', text: 'Could not save the location.', confirmButtonColor: TEAL }); });
  });
})(jQuery);
