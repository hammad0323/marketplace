/* The Hashir — section-based blog / page builder */
(function ($) {
  'use strict';
  var $form = $('[data-blog-builder]');
  if (!$form.length) { return; }
  var CFG = JSON.parse($form.find('[data-builder-config]').text() || '{}');
  var blocks = JSON.parse($form.find('[data-blocks-initial]').text() || '[]');
  var $list = $form.find('[data-block-list]');
  var CSRF = window.TH_CSRF;
  var TEAL = '#005957';
  var insertAfter = null;

  function uid() { return 'b' + Math.random().toString(36).slice(2, 10); }
  function htmlToText(h) { return $('<div>').html(String(h || '').replace(/<\/p>\s*<p>/gi, '\n\n').replace(/<br\s*\/?>/gi, '\n')).text(); }
  function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
  function mediaUrl(p) {
    if (!p) { return ''; }
    if (/^https?:\/\//i.test(p)) { return p; }
    if (p.indexOf('images/') !== 0) { p = 'images/blogs/' + p; }
    return (CFG.mediaBase || '') + '/' + p.split('/').map(encodeURIComponent).join('/');
  }

  var gcOf = { related_programs: 2, related_services: 3, related_events: 1 };
  var SCHEMA = {
    heading: [{ k: 'text', t: 'text', label: 'Heading', ph: 'Section heading' }],
    subheading: [{ k: 'text', t: 'text', label: 'Subheading' }],
    richtext: [{ k: 'html', t: 'rich' }],
    paragraph: [{ k: 'text', t: 'textarea', label: 'Paragraph', rows: 4 }],
    image: [{ k: 'src', t: 'image', label: 'Image' }, { k: 'alt', t: 'text', label: 'Alt text', ph: 'Describe the image for screen readers and Google' }, { k: 'caption', t: 'text', label: 'Caption' }, { k: 'link', t: 'text', label: 'Link (optional)' }, { k: 'width', t: 'select', label: 'Width', options: { normal: 'Normal', wide: 'Wide' } }],
    gallery: [{ k: 'images', t: 'images', label: 'Images' }, { k: 'columns', t: 'select', label: 'Columns', options: { 2: '2', 3: '3', 4: '4' }, def: '3' }, { k: 'caption', t: 'text', label: 'Caption' }],
    video: [{ k: 'url', t: 'text', label: 'Video URL', ph: 'YouTube, Vimeo player or .mp4 link' }, { k: 'caption', t: 'text', label: 'Caption' }],
    youtube: [{ k: 'url', t: 'text', label: 'YouTube URL', ph: 'https://www.youtube.com/watch?v=…' }, { k: 'caption', t: 'text', label: 'Title / caption' }],
    quote: [{ k: 'text', t: 'textarea', label: 'Quote', rows: 3 }, { k: 'cite', t: 'text', label: 'Source' }],
    highlight: [{ k: 'style', t: 'select', label: 'Style', options: { info: 'Information', success: 'Good to know', warning: 'Important', note: 'Tip' } }, { k: 'title', t: 'text', label: 'Title' }, { k: 'html', t: 'rich' }],
    bullets: [{ k: 'items', t: 'lines', label: 'Items (one per line)' }],
    numbered: [{ k: 'items', t: 'lines', label: 'Steps (one per line)' }],
    faq: [{ k: 'title', t: 'text', label: 'Section title', def: 'Frequently asked questions' }, { k: 'items', t: 'qa', label: 'Questions' }],
    accordion: [{ k: 'title', t: 'text', label: 'Section title (optional)' }, { k: 'items', t: 'qa', label: 'Panels', qLabel: 'Panel title', aLabel: 'Panel content' }],
    cta: [{ k: 'title', t: 'text', label: 'Title' }, { k: 'text', t: 'textarea', label: 'Text', rows: 2 }, { k: 'button', t: 'text', label: 'Button text' }, { k: 'link', t: 'text', label: 'Button link', ph: '/mosques or https://…' }],
    html: [{ k: 'html', t: 'code', label: 'HTML', help: 'Scripts, forms and event handlers are removed. Iframes are allowed from YouTube, Vimeo and Google Maps.' }],
    mosque_map: [{ k: 'title', t: 'text', label: 'Title (optional)' }, { k: 'venue_ids', t: 'venues', label: 'Mosques on the map', map: true }, { k: 'height', t: 'number', label: 'Height (px)', def: 420, min: 260, max: 700 }],
    mosque_carousel: [{ k: 'title', t: 'text', label: 'Title (optional)' }, { k: 'venue_ids', t: 'venues', label: 'Mosques in the carousel' }, { k: 'visible', t: 'select', label: 'Cards visible on desktop', options: { 2: '2', 3: '3', 4: '4' }, def: '3' }, { k: 'layout', t: 'select', label: 'Card style', options: { card: 'Card with cover image', compact: 'Compact' } }],
    related_blogs: [{ k: 'title', t: 'text', label: 'Title', def: 'Related articles' }, { k: 'limit', t: 'number', label: 'How many', def: 3, min: 1, max: 9 }],
    related_programs: [{ k: 'title', t: 'text', label: 'Title' }, { k: 'category', t: 'category', label: 'Category' }, { k: 'city', t: 'city', label: 'City' }, { k: 'ids', t: 'details', label: 'Or pick specific ones' }, { k: 'limit', t: 'number', label: 'How many', def: 6, min: 1, max: 12 }],
    related_services: null, related_events: null,
    legacy_guide: [{ t: 'note', text: 'Shows the original city-guide content of this blog (quick answer, map, featured mosques, comparison table, summary and FAQ), built live from the database. Edit its text under “City guide data” on the right.' }]
  };
  SCHEMA.related_services = SCHEMA.related_programs;
  SCHEMA.related_events = SCHEMA.related_programs;

  function summary(b) {
    var d = b.data || {};
    if (d.text) { return String(d.text).slice(0, 70); }
    if (d.title) { return String(d.title).slice(0, 70); }
    if (d.html) { return $('<div>').html(d.html).text().slice(0, 70); }
    if (d.venue_ids && d.venue_ids.length) { return d.venue_ids.length + ' mosque(s)'; }
    if (d.items && d.items.length) { return d.items.length + ' item(s)'; }
    if (d.url) { return d.url; }
    return '';
  }

  // ------------------------------------------------------------------ fields
  function field(b, f, $body) {
    var d = b.data;
    var val = d[f.k] !== undefined ? d[f.k] : (f.def !== undefined ? f.def : '');
    if (d[f.k] === undefined && f.def !== undefined) { d[f.k] = f.def; }
    var id = b.id + '_' + (f.k || 'n');
    var $w = $('<div class="mb-3">');
    if (f.label && f.t !== 'note') { $w.append($('<label class="form-label small fw-semibold">').attr('for', id).text(f.label)); }
    var set = function (v) { d[f.k] = v; refreshHead(b); };
    switch (f.t) {
      case 'text':
        $w.append($('<input class="form-control">').attr({ id: id, placeholder: f.ph || '' }).val(val).on('input', function () { set(this.value); }));
        break;
      case 'number':
        $w.append($('<input type="number" class="form-control" style="max-width:160px">').attr({ id: id, min: f.min, max: f.max }).val(val).on('input', function () { set(this.value === '' ? '' : parseInt(this.value, 10)); }));
        break;
      case 'textarea':
        $w.append($('<textarea class="form-control">').attr({ id: id, rows: f.rows || 3 }).val(val).on('input', function () { set(this.value); }));
        break;
      case 'code':
        $w.append($('<textarea class="form-control font-monospace small" rows="8" spellcheck="false">').attr('id', id).val(val).on('input', function () { set(this.value); }));
        break;
      case 'lines':
        $w.append($('<textarea class="form-control" rows="5">').attr('id', id).val((val || []).join('\n')).on('input', function () { set(this.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean)); }));
        break;
      case 'select':
        var $s = $('<select class="form-select" style="max-width:320px">').attr('id', id);
        $.each(f.options, function (k, l) { $s.append($('<option>').val(k).text(l)); });
        $s.val(String(val || Object.keys(f.options)[0])).on('change', function () { set(this.value); });
        if (!d[f.k]) { d[f.k] = $s.val(); }
        $w.append($s);
        break;
      case 'rich':
        var $holder = $('<div class="rich-editor" style="--h:180px">');
        var ta = $('<textarea class="d-none">').val(val)[0];
        $w.append($holder, ta);
        setTimeout(function () { var q = window.thMakeEditor($holder[0], ta); $(ta).on('change', function () { set(ta.value); }); if (!q) { $(ta).on('input', function () { set(ta.value); }); } }, 0);
        break;
      case 'image':
        $w.append(imageField(val, function (p) { set(p); }));
        break;
      case 'images':
        $w.append(imagesField(b, f));
        break;
      case 'qa':
        $w.append(qaField(b, f));
        break;
      case 'venues':
        $w.append(venuesField(b, f));
        break;
      case 'category':
        var gc = gcOf[b.type];
        var $c = $('<select class="form-select" style="max-width:320px">').attr('id', id).append('<option value="">Any category</option>');
        (CFG.categories[gc] || []).forEach(function (c) { $c.append($('<option>').val(c.id).text(c.name)); });
        $c.val(String(val || '')).on('change', function () { set(this.value ? parseInt(this.value, 10) : ''); });
        $w.append($c);
        break;
      case 'city':
        var $city = $('<select class="form-select">').attr('id', id).append('<option value="">Any city</option>');
        if (val) { $city.append($('<option selected>').val(val).text(CFG.cityNames[val] || ('City #' + val))); }
        $w.append($city);
        setTimeout(function () {
          $city.select2({ width: '100%', allowClear: true, placeholder: 'Any city', minimumInputLength: 2,
            ajax: { url: (document.body.getAttribute('data-base') || '') + '/api/cities', dataType: 'json', delay: 200, data: function (p) { return { q: p.term, all: 1 }; },
              processResults: function (r) { return { results: (r.results || []).map(function (c) { return { id: c.city_id, text: c.city_title + ', ' + c.state_title }; }) }; } } })
            .on('change', function () { set(this.value ? parseInt(this.value, 10) : ''); });
        }, 0);
        break;
      case 'details':
        var $dsel = $('<select class="form-select" multiple>').attr('id', id);
        (val || []).forEach(function (x) { $dsel.append($('<option selected>').val(x).text('#' + x)); });
        $w.append($dsel, '<div class="form-text">If you pick specific ones, category and city are ignored.</div>');
        setTimeout(function () {
          $dsel.select2({ width: '100%', placeholder: 'Search by title…', minimumInputLength: 2,
            ajax: { url: CFG.api, dataType: 'json', delay: 200, data: function (p) { return { do: 'details', q: p.term, gc: gcOf[b.type] }; }, processResults: function (r) { return { results: r.results || [] }; } } })
            .on('change', function () { set(($(this).val() || []).map(function (x) { return parseInt(x, 10); })); });
        }, 0);
        break;
      case 'note':
        $w.append($('<div class="alert alert-light border small mb-0">').text(f.text));
        break;
    }
    if (f.help) { $w.append($('<div class="form-text">').text(f.help)); }
    $body.append($w);
  }

  function uploadFile(file) {
    var fd = new FormData();
    fd.append('file', file);
    fd.append('csrf', CSRF);
    return $.ajax({ url: CFG.upload, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' });
  }
  function imageField(val, onChange) {
    var $box = $('<div class="d-flex gap-3 align-items-start">');
    var $prev = $('<div class="img-thumb">');
    var $in = $('<input class="form-control form-control-sm mb-2" placeholder="images/blogs/… or https://…">').val(val || '');
    var draw = function () { $prev.empty(); if ($in.val()) { $prev.append($('<img alt="">').attr('src', mediaUrl($in.val()))); } };
    $in.on('input', function () { onChange(this.value); draw(); });
    var $up = $('<label class="btn btn-sm btn-outline-primary mb-0"><i class="bi bi-upload me-1"></i>Upload<input type="file" accept="image/*" hidden></label>');
    $up.find('input').on('change', function () {
      var f = this.files[0]; if (!f) { return; }
      $up.addClass('disabled');
      uploadFile(f).done(function (r) { $in.val(r.path); onChange(r.path); draw(); })
        .fail(function (x) { Swal.fire({ icon: 'error', text: (x.responseJSON && x.responseJSON.message) || 'Upload failed.', confirmButtonColor: TEAL }); })
        .always(function () { $up.removeClass('disabled'); });
    });
    draw();
    return $box.append($prev, $('<div class="flex-grow-1">').append($in, $up));
  }
  function imagesField(b, f) {
    var d = b.data; d[f.k] = d[f.k] || [];
    var $wrap = $('<div>');
    var draw = function () {
      $wrap.empty();
      var $grid = $('<div class="gallery-edit">');
      d[f.k].forEach(function (img, i) {
        var $it = $('<div class="gallery-edit-item">').append($('<img alt="">').attr('src', mediaUrl(img.src)));
        $it.append($('<input class="form-control form-control-sm mt-1" placeholder="Alt text">').val(img.alt || '').on('input', function () { img.alt = this.value; }));
        $it.append($('<button type="button" class="btn btn-sm btn-light text-danger gallery-del" aria-label="Remove image"><i class="bi bi-x-lg"></i></button>').on('click', function () { d[f.k].splice(i, 1); draw(); refreshHead(b); }));
        $grid.append($it);
      });
      $wrap.append($grid);
      var $up = $('<label class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-upload me-1"></i>Add images<input type="file" accept="image/*" multiple hidden></label>');
      $up.find('input').on('change', function () {
        var files = Array.prototype.slice.call(this.files);
        var next = function () {
          var file = files.shift(); if (!file) { draw(); refreshHead(b); return; }
          uploadFile(file).done(function (r) { d[f.k].push({ src: r.path, alt: '' }); }).always(next);
        };
        next();
      });
      $wrap.append($up);
    };
    draw();
    return $wrap;
  }
  function qaField(b, f) {
    var d = b.data; d[f.k] = d[f.k] || [{ q: '', a: '' }];
    var $wrap = $('<div>');
    var draw = function () {
      $wrap.empty();
      d[f.k].forEach(function (it, i) {
        var $row = $('<div class="qa-row">');
        $row.append($('<input class="form-control mb-1">').attr('placeholder', f.qLabel || 'Question').val(it.q || '').on('input', function () { it.q = this.value; refreshHead(b); }));
        $row.append($('<textarea class="form-control" rows="2">').attr('placeholder', f.aLabel || 'Answer').val(htmlToText(it.a))
          .on('input', function () { it.a = this.value.split(/\n{2,}/).map(function (p) { return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>'; }).join(''); }));
        $row.append($('<button type="button" class="btn btn-sm btn-link text-danger px-0">Remove</button>').on('click', function () { d[f.k].splice(i, 1); draw(); refreshHead(b); }));
        $wrap.append($row);
      });
      $wrap.append($('<button type="button" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>').on('click', function () { d[f.k].push({ q: '', a: '' }); draw(); }));
    };
    draw();
    return $wrap;
  }

  // Mosque picker (search existing records, reorder, remove) + map preview
  var venueCache = {};
  function venuesField(b, f) {
    var d = b.data; d[f.k] = (d[f.k] || []).map(function (x) { return parseInt(x, 10); });
    var $wrap = $('<div class="venue-picker">');
    var $sel = $('<select class="form-select">').append('<option></option>');
    var $ul = $('<ul class="venue-picked list-unstyled">');
    var $map = f.map ? $('<div class="picker-map">') : null;
    var map = null, layer = null;
    var $mapTools = f.map ? $('<div class="d-flex flex-wrap gap-2 align-items-center mt-2 small">') : null;
    $wrap.append($sel, $ul);
    if ($map) {
      $wrap.append($map, $mapTools);
      $mapTools.append($('<button type="button" class="btn btn-sm btn-outline-secondary">Use the current view as the starting view</button>').on('click', function () {
        if (!map) { return; } var c = map.getCenter(); d.center = { lat: +c.lat.toFixed(5), lng: +c.lng.toFixed(5) }; d.zoom = map.getZoom(); $info.text('Starting view saved (zoom ' + d.zoom + ').');
      }), $('<button type="button" class="btn btn-sm btn-light">Fit all markers</button>').on('click', function () { delete d.center; d.zoom = 0; $info.text('The map fits all markers.'); drawMap(true); }));
      var $info = $('<span class="text-muted">').text(d.center ? 'Starting view saved (zoom ' + d.zoom + ').' : 'The map fits all markers.');
      $mapTools.append($info);
    }
    function row(v) {
      var $li = $('<li class="venue-picked-item">').attr('data-id', v.id);
      $li.append('<i class="bi bi-grip-vertical handle" aria-hidden="true"></i>');
      $li.append($('<span class="flex-grow-1">').append($('<strong>').text(v.title), $('<span class="text-muted small ms-2">').text(v.city || '')));
      if (f.map) { $li.append(v.lat ? '<span class="badge text-bg-success-subtle text-success-emphasis">on map</span>' : $('<a class="badge text-bg-warning-subtle text-warning-emphasis text-decoration-none" target="_blank" rel="noopener">no location — set it</a>').attr('href', (document.body.getAttribute('data-bo-base') || '') + '/venue-edit?id=' + v.id)); }
      if (!v.published) { $li.append('<span class="badge text-bg-secondary">hidden profile</span>'); }
      $li.append($('<button type="button" class="btn btn-sm btn-light text-danger" aria-label="Remove"><i class="bi bi-x-lg"></i></button>').on('click', function () {
        d[f.k] = d[f.k].filter(function (x) { return x !== v.id; }); $li.remove(); drawMap(true); refreshHead(b);
      }));
      return $li;
    }
    function drawList() {
      $ul.empty();
      d[f.k].forEach(function (id) { if (venueCache[id]) { $ul.append(row(venueCache[id])); } });
    }
    function drawMap(fit) {
      if (!$map || !window.L) { return; }
      if (!map) {
        map = L.map($map[0], { scrollWheelZoom: false });
        window.thTileLayer().addTo(map);
        layer = L.featureGroup().addTo(map);
      }
      layer.clearLayers();
      var pts = [];
      d[f.k].forEach(function (id) {
        var v = venueCache[id];
        if (v && v.lat) { pts.push([v.lat, v.lng]); L.marker([v.lat, v.lng]).bindPopup('<strong>' + esc(v.title) + '</strong><br>' + esc(v.city)).addTo(layer); }
      });
      if (d.center && d.zoom && !fit) { map.setView([d.center.lat, d.center.lng], d.zoom); }
      else if (pts.length) { map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 }); }
      else { var c = (window.TH_MAP && window.TH_MAP.center) || { lat: 43.65, lng: -79.38, zoom: 9 }; map.setView([c.lat, c.lng], c.zoom); }
      setTimeout(function () { map.invalidateSize(); }, 50);
    }
    var missing = d[f.k].filter(function (id) { return !venueCache[id]; });
    if (missing.length) {
      $.getJSON(CFG.api, { do: 'venues', ids: missing.join(',') }).done(function (r) {
        (r.results || []).forEach(function (v) { venueCache[v.id] = v; });
        drawList(); drawMap(false);
      });
    } else { setTimeout(function () { drawList(); drawMap(false); }, 0); }
    setTimeout(function () {
      $sel.select2({ width: '100%', placeholder: 'Search mosques by name or city to add…', minimumInputLength: 2,
        ajax: { url: CFG.api, dataType: 'json', delay: 200, data: function (p) { return { do: 'venues', q: p.term, role: '1' }; },
          processResults: function (r) { (r.results || []).forEach(function (v) { venueCache[v.id] = v; }); return { results: r.results || [] }; } } })
        .on('select2:select', function (e) {
          var id = parseInt(e.params.data.id, 10);
          if (d[f.k].indexOf(id) === -1) { d[f.k].push(id); $ul.append(row(venueCache[id])); drawMap(true); refreshHead(b); }
          $sel.val(null).trigger('change');
        });
      if (window.Sortable) {
        Sortable.create($ul[0], { handle: '.handle', animation: 150, onEnd: function () {
          d[f.k] = $ul.children().map(function () { return parseInt($(this).attr('data-id'), 10); }).get();
        } });
      }
    }, 0);
    b._redrawMap = function () { drawMap(false); };
    return $wrap;
  }

  // ------------------------------------------------------------------ blocks
  function refreshHead(b) {
    var $c = $list.find('[data-bid="' + b.id + '"]');
    $c.find('.blk-summary').text(summary(b));
  }
  function card(b) {
    var t = CFG.types[b.type] || [b.type, 'bi-square'];
    b.data = b.data && !Array.isArray(b.data) ? b.data : {};
    var $c = $('<div class="blk-card">').attr('data-bid', b.id);
    var $h = $('<div class="blk-head">');
    $h.append('<i class="bi bi-grip-vertical handle" title="Drag to reorder" aria-hidden="true"></i>');
    $h.append($('<button type="button" class="blk-toggle">').append($('<i class="bi">').addClass(t[1]), $('<span class="fw-semibold">').text(t[0]), $('<span class="blk-summary text-muted small">').text(summary(b))));
    var $tools = $('<div class="blk-tools">');
    $tools.append($('<button type="button" class="btn btn-sm btn-light" title="Move up" aria-label="Move up"><i class="bi bi-arrow-up"></i></button>').on('click', function () { move(b, -1); }));
    $tools.append($('<button type="button" class="btn btn-sm btn-light" title="Move down" aria-label="Move down"><i class="bi bi-arrow-down"></i></button>').on('click', function () { move(b, 1); }));
    $tools.append($('<button type="button" class="btn btn-sm btn-light" title="Add a section below" aria-label="Add a section below"><i class="bi bi-plus-lg"></i></button>').on('click', function () { insertAfter = b.id; openPicker(); }));
    if (b.type !== 'legacy_guide') { $tools.append($('<button type="button" class="btn btn-sm btn-light" title="Duplicate" aria-label="Duplicate"><i class="bi bi-copy"></i></button>').on('click', function () { dup(b); })); }
    $tools.append($('<button type="button" class="btn btn-sm btn-light text-danger" title="Remove" aria-label="Remove"><i class="bi bi-trash"></i></button>').on('click', function () { remove(b); }));
    $h.append($tools);
    var $body = $('<div class="blk-body">');
    (SCHEMA[b.type] || []).forEach(function (f) { field(b, f, $body); });
    $c.append($h, $body);
    $h.find('.blk-toggle').on('click', function () { $c.toggleClass('is-collapsed'); if (!$c.hasClass('is-collapsed') && b._redrawMap) { b._redrawMap(); } });
    return $c;
  }
  function render() {
    $list.empty();
    blocks.forEach(function (b) { if (!b.id) { b.id = uid(); } $list.append(card(b)); });
    update();
  }
  function update() {
    $form.find('[data-block-empty]').prop('hidden', blocks.length > 0);
    $form.find('[data-block-count]').text(blocks.length ? '(' + blocks.length + ')' : '');
  }
  function indexOf(b) { return blocks.indexOf(b); }
  function move(b, dir) {
    var i = indexOf(b), j = i + dir;
    if (j < 0 || j >= blocks.length) { return; }
    blocks.splice(i, 1); blocks.splice(j, 0, b);
    var $c = $list.find('[data-bid="' + b.id + '"]');
    if (dir < 0) { $c.insertBefore($c.prev()); } else { $c.insertAfter($c.next()); }
  }
  function dup(b) {
    var copy = JSON.parse(JSON.stringify({ type: b.type, data: b.data }));
    copy.id = uid();
    blocks.splice(indexOf(b) + 1, 0, copy);
    $list.find('[data-bid="' + b.id + '"]').after(card(copy));
    update();
  }
  function remove(b) {
    window.thConfirm('Remove this section?').then(function (ok) {
      if (!ok) { return; }
      blocks.splice(indexOf(b), 1);
      $list.find('[data-bid="' + b.id + '"]').remove();
      update();
    });
  }
  function add(type) {
    var b = { id: uid(), type: type, data: {} };
    var idx = blocks.length;
    if (insertAfter) { var after = blocks.filter(function (x) { return x.id === insertAfter; })[0]; if (after) { idx = indexOf(after) + 1; } }
    blocks.splice(idx, 0, b);
    var $c = card(b);
    if (idx === blocks.length - 1) { $list.append($c); } else { $list.find('[data-bid="' + blocks[idx + 1].id + '"]').before($c); }
    insertAfter = null;
    update();
    $('html, body').animate({ scrollTop: $c.offset().top - 90 }, 250);
    setTimeout(function () { $c.find('input, textarea').first().trigger('focus'); }, 260);
  }

  // Picker
  var $picker = $form.closest('.bo-content').find('[data-block-picker]');
  if (!$picker.length) { $picker = $('[data-block-picker]'); }
  var groups = [
    ['Text', ['heading', 'subheading', 'richtext', 'paragraph', 'quote', 'highlight', 'bullets', 'numbered']],
    ['Media', ['image', 'gallery', 'video', 'youtube']],
    ['Interactive', ['faq', 'accordion', 'cta', 'html']],
    ['Mosques & community', ['mosque_map', 'mosque_carousel', 'related_programs', 'related_services', 'related_events', 'related_blogs', 'legacy_guide']]
  ];
  groups.forEach(function (g) {
    var items = g[1].filter(function (t) { return CFG.types[t] && (t !== 'legacy_guide' || CFG.hasLegacy); });
    if (!items.length) { return; }
    $picker.append($('<h3 class="h6 text-muted mt-2">').text(g[0]));
    var $g = $('<div class="picker-grid">');
    items.forEach(function (t) {
      $g.append($('<button type="button" class="picker-item">').append($('<i class="bi">').addClass(CFG.types[t][1]), $('<span>').text(CFG.types[t][0])).on('click', function () {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('blockPicker')).hide();
        add(t);
      }));
    });
    $picker.append($g);
  });
  function openPicker() { bootstrap.Modal.getOrCreateInstance(document.getElementById('blockPicker')).show(); }
  $form.find('[data-add-block]').on('click', function () { insertAfter = null; openPicker(); });
  $form.find('[data-collapse-all]').on('click', function () {
    var anyOpen = $list.find('.blk-card:not(.is-collapsed)').length > 0;
    $list.find('.blk-card').toggleClass('is-collapsed', anyOpen);
    $(this).text(anyOpen ? 'Expand all' : 'Collapse all');
  });
  if (window.Sortable) {
    Sortable.create($list[0], { handle: '.blk-head > .handle', animation: 150, onEnd: function () {
      var order = $list.children().map(function () { return $(this).attr('data-bid'); }).get();
      blocks.sort(function (a, b) { return order.indexOf(a.id) - order.indexOf(b.id); });
    } });
  }

  // Serialize before submit
  function serialize() {
    var clean = blocks.map(function (b) { return { id: b.id, type: b.type, data: b.data }; });
    $form.find('[data-blocks-json]').val(JSON.stringify(clean));
  }
  $form.on('th:serialize', serialize);
  $form.on('submit', function () { serialize(); dirty = false; });

  // Preview (saves a private preview copy, never changes the live page)
  $form.find('[data-preview]').on('click', function () {
    serialize();
    var data = $form.serializeArray();
    data.push({ name: 'action', value: 'preview' });
    var win = window.open('about:blank', '_blank');
    $.ajax({ url: window.location.href, method: 'POST', data: $.param(data), dataType: 'json', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .done(function (r) {
        if (r.ok) {
          if (win) { win.location = r.url; } else { window.open(r.url, '_blank'); }
          if (r.key && window.location.search.indexOf('key=') === -1) { history.replaceState(null, '', window.location.pathname + '?key=' + encodeURIComponent(r.key)); }
        } else { if (win) { win.close(); } Swal.fire({ icon: 'error', text: r.message, confirmButtonColor: TEAL }); }
      })
      .fail(function (x) { if (win) { win.close(); } Swal.fire({ icon: 'error', text: (x.responseJSON && x.responseJSON.message) || 'Preview failed.', confirmButtonColor: TEAL }); });
  });

  // Publish box
  $form.find('[data-status-select]').on('change', function () { $form.find('[data-schedule-box]').prop('hidden', this.value !== 'scheduled'); });
  // Featured image
  $form.find('[data-upload-to]').on('change', function () {
    var f = this.files[0], $in = $($(this).data('upload-to')), $prev = $($(this).data('preview-to'));
    if (!f) { return; }
    uploadFile(f).done(function (r) { $in.val(r.path); $prev.empty().append($('<img alt="">').attr('src', r.url)); })
      .fail(function (x) { Swal.fire({ icon: 'error', text: (x.responseJSON && x.responseJSON.message) || 'Upload failed.', confirmButtonColor: TEAL }); });
  });
  $form.find('[data-clear-featured]').on('click', function () { $form.find('[data-featured-input]').val(''); $form.find('[data-featured-preview]').empty(); });
  // Search result preview
  function serp() {
    var t = $form.find('[name="seo[title]"]').val() || (($form.find('[name="heading"]').val() || $form.find('[name="title"]').val() || '') + ' | The Hashir');
    var d = $form.find('[name="seo[description]"]').val() || $form.find('[name="excerpt"]').val() || '';
    $form.find('[data-serp-title]').text(t.slice(0, 65));
    $form.find('[data-serp-desc]').text(d.slice(0, 160));
  }
  $form.on('input', 'input, textarea', serp); serp();

  // Unsaved-changes warning
  var dirty = false;
  $form.on('input change', function () { dirty = true; });
  $list.on('click', 'button', function () { dirty = true; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  render();
})(jQuery);
