/* The Hashir — site scripts (jQuery + Bootstrap 5) */
(function ($) {
  'use strict';

  var BASE = document.body.getAttribute('data-base') || '';
  function u(path) { return BASE + path; }

  // -------------------------------------------------------------- beta bar
  var betaBar = document.querySelector('[data-beta-bar]');
  if (betaBar) {
    try { if (sessionStorage.getItem('th_beta_hidden') === '1') { betaBar.classList.add('is-hidden'); } } catch (e) {}
    var betaClose = betaBar.querySelector('[data-beta-close]');
    if (betaClose) {
      betaClose.addEventListener('click', function () {
        betaBar.classList.add('is-hidden');
        try { sessionStorage.setItem('th_beta_hidden', '1'); } catch (e) {}
      });
    }
  }

  // ---------------------------------------------------------------- header
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 40); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  // ---------------------------------------------------------- city picker
  function setCookie(name, value, days) {
    var d = new Date(); d.setTime(d.getTime() + days * 864e5);
    document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
  }
  function slug(s) { return String(s).trim().replace(/\s+/g, '-'); }

  var $modal = $('#cityModal');
  var $results = $modal.find('[data-city-results]');
  var $status = $modal.find('[data-location-status]');

  function showStep(step) {
    $modal.find('[data-city-step]').attr('hidden', true);
    $modal.find('[data-city-step="' + step + '"]').attr('hidden', false);
  }

  function chooseCity(city) {
    setCookie('th_city', city.id, 180);
    setCookie('th_city_name', city.name, 180);
    try { localStorage.setItem('th_city', JSON.stringify(city)); } catch (e) {}
    $('[data-city-label]').text(city.name);
    $modal.find('[data-city-done-name]').text(city.label || city.name);
    $modal.find('[data-city-link="mosques"]').attr('href', u('/mosques?city=' + city.id));
    $modal.find('[data-city-link="prayer"]').attr('href', u('/prayer-times/' + slug(city.country || 'Canada') + '/' + slug(city.name) + '/' + city.id));
    showStep('done');
  }

  function renderCities(list, emptyText) {
    $results.empty();
    if (!list.length) {
      $results.append($('<p class="small text-muted mt-2 mb-0">').text(emptyText || 'No matching cities with listings.'));
      return;
    }
    list.forEach(function (c) {
      var $b = $('<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">');
      $b.append($('<span>').text(c.city_title).append(' ').append($('<small>').text(c.state_title ? '· ' + c.state_title : '')));
      $b.append($('<span class="badge rounded-pill text-bg-light">').text(c.n + ' listed'));
      $b.on('click', function () {
        chooseCity({ id: c.city_id, name: c.city_title, label: c.city_title + (c.state_title ? ', ' + c.state_title : ''), country: c.country_title });
      });
      $results.append($b);
    });
  }

  var cityTimer = null;
  $modal.find('[data-city-search]').on('input', function () {
    var q = this.value.trim();
    clearTimeout(cityTimer);
    if (q.length < 2) { $results.empty(); return; }
    cityTimer = setTimeout(function () {
      $.getJSON(u('/api/cities'), { q: q }).done(function (r) { renderCities(r.results || []); });
    }, 220);
  });

  $modal.find('[data-city-reset]').on('click', function () { showStep('pick'); });
  $modal.on('show.bs.modal', function () { showStep('pick'); $status.text(''); });

  $modal.find('[data-use-location]').on('click', function () {
    if (!navigator.geolocation) { $status.text('Location is not available in this browser. Please search for your city.'); return; }
    $status.text('Finding your location…');
    navigator.geolocation.getCurrentPosition(function (pos) {
      var lat = pos.coords.latitude, lng = pos.coords.longitude;
      $.getJSON('https://api.bigdatacloud.net/data/reverse-geocode-client', { latitude: lat, longitude: lng, localityLanguage: 'en' })
        .done(function (g) {
          var name = g.city || g.locality || '';
          if (!name) { $status.text('We could not detect your city. Please search for it below.'); return; }
          $.getJSON(u('/api/cities'), { q: name, exact: 1 }).done(function (r) {
            var list = r.results || [];
            if (list.length === 1) {
              var c = list[0];
              chooseCity({ id: c.city_id, name: c.city_title, label: c.city_title + (c.state_title ? ', ' + c.state_title : ''), country: c.country_title });
            } else if (list.length > 1) {
              $status.text('We found a few matches for ' + name + ':');
              renderCities(list);
            } else {
              $status.text('You appear to be near ' + name + '. We don\'t have listings there yet — search for a nearby city.');
              $modal.find('[data-city-search]').val('').trigger('focus');
            }
          });
        })
        .fail(function () { $status.text('We could not detect your city. Please search for it below.'); });
    }, function () {
      $status.text('Location permission was not given. You can search for your city instead.');
    }, { timeout: 10000, maximumAge: 600000 });
  });

  // ------------------------------------------------------------- select2
  function initCitySelect($el) {
    $el.select2({
      width: '100%',
      allowClear: true,
      placeholder: $el.data('placeholder') || 'Any city',
      minimumInputLength: 2,
      dropdownParent: $el.closest('.modal').length ? $el.closest('.modal') : $(document.body),
      ajax: {
        url: u('/api/cities'),
        dataType: 'json',
        delay: 220,
        data: function (p) { return { q: p.term }; },
        processResults: function (r) {
          return { results: (r.results || []).map(function (c) {
            return { id: c.city_id, text: c.city_title + (c.state_title ? ', ' + c.state_title : '') + ' (' + c.n + ')', country: c.country_title, name: c.city_title };
          }) };
        }
      },
      language: { inputTooShort: function () { return 'Type at least 2 letters'; } }
    });
  }
  $('[data-city-select]').each(function () { initCitySelect($(this)); });

  // Hero: picking a category / city takes you straight there
  $('[data-go-select]').on('change', function () { if (this.value) { window.location.href = this.value; } });
  $('[data-city-go]').on('select2:select', function (e) { window.location.href = u('/mosques?city=' + e.params.data.id); });

  // Auto-submit filter forms when a select changes
  $('[data-autosubmit] select:not([data-go-select])').on('change', function () { $(this).closest('form').trigger('submit'); });

  // --------------------------------------------------------------- share
  var $share = $('#shareModal');
  $(document).on('click', '[data-share-btn]', function (e) {
    var title = $(this).data('title') || document.title;
    var link = window.location.href.split('#')[0];
    if (navigator.share) {
      e.preventDefault();
      navigator.share({ title: title, url: link }).catch(function () {});
      return;
    }
    var t = encodeURIComponent(title), l = encodeURIComponent(link);
    $share.find('[data-share="whatsapp"]').attr('href', 'https://wa.me/?text=' + t + '%20' + l);
    $share.find('[data-share="facebook"]').attr('href', 'https://www.facebook.com/sharer/sharer.php?u=' + l);
    $share.find('[data-share="x"]').attr('href', 'https://twitter.com/intent/tweet?text=' + t + '&url=' + l);
    $share.find('[data-share="email"]').attr('href', 'mailto:?subject=' + t + '&body=' + l);
    $share.data('link', link);
    bootstrap.Modal.getOrCreateInstance($share[0]).show();
  });
  $share.find('[data-share="copy"]').on('click', function () {
    var link = $share.data('link') || window.location.href;
    var done = function () { Swal.fire({ toast: true, position: 'bottom', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1800 }); };
    if (navigator.clipboard) { navigator.clipboard.writeText(link).then(done); } else { window.prompt('Copy this link', link); }
  });

  // ----------------------------------------------------------- read more
  $('[data-readmore]').each(function () {
    var el = this;
    var $btn = $(el).next('.readmore-btn');
    if (el.scrollHeight <= el.clientHeight + 8) { el.classList.remove('is-collapsed'); $btn.remove(); return; }
    $btn.prop('hidden', false).on('click', function () {
      var collapsed = el.classList.toggle('is-collapsed');
      $btn.text(collapsed ? 'Read more' : 'Read less').attr('aria-expanded', String(!collapsed));
    });
  });

  // ------------------------------------------------- next prayer + countdown
  function nowMinutesIn(tz) {
    try {
      var parts = new Intl.DateTimeFormat('en-US', { timeZone: tz, hour: 'numeric', minute: 'numeric', hour12: false }).formatToParts(new Date());
      var h = 0, m = 0;
      parts.forEach(function (p) { if (p.type === 'hour') { h = parseInt(p.value, 10) % 24; } if (p.type === 'minute') { m = parseInt(p.value, 10); } });
      return h * 60 + m;
    } catch (e) { var d = new Date(); return d.getHours() * 60 + d.getMinutes(); }
  }
  function fmtLeft(min) {
    if (min < 60) { return min + ' min'; }
    var h = Math.floor(min / 60), m = min % 60;
    return h + ' h' + (m ? ' ' + m + ' min' : '');
  }
  $('[data-prayer-card]').each(function () {
    var $card = $(this);
    var tz = $card.data('tz');
    var isToday = String($card.data('today')) === '1';
    if (!isToday) { return; }
    function update() {
      var now = nowMinutesIn(tz);
      var $rows = $card.find('.prayer-row');
      $rows.removeClass('is-next').find('.prayer-countdown').remove();
      var found = null;
      $rows.each(function () {
        if (found) { return; }
        var az = parseInt($(this).data('azan'), 10), iq = parseInt($(this).data('iqama'), 10);
        var target = !isNaN(az) && az > now ? az : (!isNaN(iq) && iq > now ? iq : NaN);
        if (!isNaN(target)) {
          var isAz = !isNaN(az) && az > now;
          found = { row: this, left: target - now, label: isAz ? 'Adhan' : 'Iqamah', time: isAz ? $(this).data('azan-text') : $(this).data('iqama-text') };
        }
      });
      var $banner = $card.find('[data-next-banner]');
      if (found) {
        $(found.row).addClass('is-next');
        $(found.row).find('th, .prayer-times').first().append($('<span class="prayer-countdown">').text('in ' + fmtLeft(found.left)));
        $banner.prop('hidden', false);
        $banner.find('[data-next-name]').text($(found.row).data('name') + ' ' + found.label.toLowerCase());
        $banner.find('[data-next-time]').text(found.time || '');
        $banner.find('[data-next-left]').text('in ' + fmtLeft(found.left));
        $('[data-next-short]').text('Next: ' + $(found.row).data('name') + ' ' + (found.time || ''));
      } else {
        $banner.prop('hidden', true);
        $('[data-next-short]').text('Prayer times');
      }
    }
    update();
    setInterval(update, 30000);
  });

  // ------------------------------------------------------ profile tabs
  var $tabs = $('.profile-tabs a');
  if ($tabs.length && 'IntersectionObserver' in window) {
    var map = {};
    $tabs.each(function () { map[this.getAttribute('href').slice(1)] = this; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && map[en.target.id]) {
          $tabs.removeClass('active').removeAttr('aria-current');
          $(map[en.target.id]).addClass('active').attr('aria-current', 'true');
          var strip = map[en.target.id].parentNode.parentNode;
          if (strip.scrollWidth > strip.clientWidth) { map[en.target.id].scrollIntoView({ block: 'nearest', inline: 'center' }); }
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(map).forEach(function (id) { var s = document.getElementById(id); if (s) { io.observe(s); } });
  }

  // -------------------------------------------------------- contact form
  $(document).on('submit', '[data-contact-form]', function (e) {
    e.preventDefault();
    var form = this, $f = $(form);
    if (!form.checkValidity()) { form.classList.add('was-validated'); $f.find(':invalid').first().trigger('focus'); return; }
    var $btn = $f.find('[type="submit"]').prop('disabled', true).text('Sending…');
    $.ajax({ url: form.action, method: 'POST', data: $f.serialize(), dataType: 'json' })
      .done(function (r) {
        if (r && r.ok) {
          form.reset(); form.classList.remove('was-validated');
          Swal.fire({ icon: 'success', title: 'Message sent', text: r.message || 'Thank you — we will get back to you soon.', confirmButtonColor: '#005957' });
        } else {
          Swal.fire({ icon: 'error', title: 'Not sent', text: (r && r.message) || 'Please check the form and try again.', confirmButtonColor: '#005957' });
        }
      })
      .fail(function (x) {
        var msg = (x.responseJSON && x.responseJSON.message) || 'Something went wrong. Please email ' + 'info@thehashir.com' + ' instead.';
        Swal.fire({ icon: 'error', title: 'Not sent', text: msg, confirmButtonColor: '#005957' });
      })
      .always(function () { $btn.prop('disabled', false).text('Send message'); });
  });

  // Prefill contact topic from links like ?topic=listing#contact
  $(document).on('click', '[data-contact-topic]', function () {
    var topic = $(this).data('contact-topic');
    $('[data-contact-form] select[name="topic"]').val(topic);
  });

  // --------------------------------------------------------------- follow
  $('[data-follow-btn]').each(function () {
    var $b = $(this), id = $b.data('venue');
    function paint(r) {
      $b.attr('aria-pressed', r.following ? 'true' : 'false').toggleClass('btn-primary', !r.following).toggleClass('btn-success', !!r.following);
      $b.find('[data-follow-label]').text(r.following ? 'Following' : 'Follow');
      $b.find('i').attr('class', 'bi me-1 ' + (r.following ? 'bi-bell-fill' : 'bi-bell'));
      $b.find('[data-follow-count]').text(r.count ? r.count.toLocaleString() : '');
    }
    $.getJSON(u('/api/follow'), { venue_id: id }).done(paint);
    $b.on('click', function () {
      var on = $b.attr('aria-pressed') !== 'true';
      var token = $('[data-contact-form] [name="csrf"]').val() || $('[data-newsletter] [name="csrf"]').val();
      $.post(u('/api/follow'), { venue_id: id, follow: on ? 1 : 0, csrf: token }, null, 'json').done(function (r) {
        paint(r);
        Swal.fire({ toast: true, position: 'bottom', icon: 'success', title: on ? 'You are following this profile on this device' : 'Unfollowed', showConfirmButton: false, timer: 2200 });
      }).fail(function (x) { Swal.fire({ toast: true, position: 'bottom', icon: 'error', title: (x.responseJSON && x.responseJSON.message) || 'Please try again.', showConfirmButton: false, timer: 2200 }); });
    });
  });

  // ----------------------------------------------------------- newsletter
  $(document).on('submit', '[data-newsletter]', function (e) {
    e.preventDefault();
    var form = this;
    if (!form.checkValidity()) { form.reportValidity(); return; }
    $.post(form.action, $(form).serialize(), null, 'json').done(function (r) {
      form.reset();
      Swal.fire({ icon: 'success', title: 'Subscribed', text: r.message, confirmButtonColor: '#005957' });
    }).fail(function (x) { Swal.fire({ icon: 'error', text: (x.responseJSON && x.responseJSON.message) || 'Please try again.', confirmButtonColor: '#005957' }); });
  });

  // ---------------------------------------------------------- yt lite
  $(document).on('click', '.yt-play', function () {
    var id = $(this).closest('[data-yt]').data('yt');
    $(this).replaceWith($('<iframe allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen>').attr({ src: 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0', title: 'YouTube video' }));
  });

  // ----------------------------------------------------- mosque carousel
  $('[data-mc-carousel]').each(function () {
    var $c = $(this), track = $c.find('.mc-track')[0];
    function step(dir) { var card = track.querySelector('.mc-card'); var w = card ? card.getBoundingClientRect().width + 16 : 300; track.scrollBy({ left: dir * w, behavior: 'smooth' }); }
    function upd() { $c.find('[data-mc-prev]').prop('disabled', track.scrollLeft < 5); $c.find('[data-mc-next]').prop('disabled', track.scrollLeft + track.clientWidth >= track.scrollWidth - 5); }
    $c.find('[data-mc-prev]').on('click', function () { step(-1); });
    $c.find('[data-mc-next]').on('click', function () { step(1); });
    track.addEventListener('scroll', upd, { passive: true });
    $(window).on('resize', upd); upd();
  });

  // --------------------------------------------------------------- maps
  function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
  function popup(p) {
    return '<div class="map-pop">' + (p.logo ? '<img src="' + esc(p.logo) + '" alt="" onerror="this.remove()">' : '') +
      '<div><strong>' + esc(p.title) + '</strong><br><span>' + esc(p.address || [p.city, p.state].filter(Boolean).join(', ')) + '</span>' +
      (p.km != null ? '<br><span>' + p.km + ' km away</span>' : '') + '<br><a href="' + esc(p.href) + '">View profile &rarr;</a></div></div>';
  }
  function tiles(cfg) { return L.tileLayer(cfg.tiles || 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: cfg.attribution || '&copy; OpenStreetMap contributors' }); }
  function initMaps() {
    if (!window.L) { return; }
    $('[data-blog-map]').each(function () {
      var cfg = $(this).data('blog-map');
      var map = L.map(this, { scrollWheelZoom: false });
      tiles(cfg).addTo(map);
      var pts = [];
      (cfg.points || []).forEach(function (p) { pts.push([p.lat, p.lng]); L.marker([p.lat, p.lng], { title: p.title, alt: p.title }).bindPopup(popup(p)).addTo(map); });
      if (cfg.center && cfg.zoom) { map.setView([cfg.center.lat, cfg.center.lng], cfg.zoom); }
      else if (pts.length > 1) { map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 }); }
      else if (pts.length) { map.setView(pts[0], 15); }
    });
    $('[data-discovery-map]').each(function () {
      var cfg = $(this).data('discovery-map');
      var c = cfg.center || { lat: 43.65, lng: -79.38, zoom: 9 };
      var map = L.map(this).setView([c.lat, c.lng], c.zoom);
      tiles(cfg).addTo(map);
      var layer = L.featureGroup().addTo(map);
      var $list = $('[data-near-list]'), $st = $('[data-near-status]');
      function draw(rows, fit) {
        layer.clearLayers();
        rows.forEach(function (p) { L.marker([p.lat, p.lng], { title: p.title }).bindPopup(popup(p)).addTo(layer); });
        if (fit && rows.length) { map.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 14 }); }
      }
      function list(rows) {
        $list.empty();
        rows.forEach(function (p) {
          $list.append($('<li class="near-item">').append($('<a>').attr('href', p.href).append($('<strong>').text(p.title), $('<span>').text([p.city, p.state].filter(Boolean).join(', ') + (p.km != null ? ' · ' + p.km + ' km' : '')))));
        });
      }
      $.getJSON(cfg.api, { type: 'mosques' }).done(function (r) { draw(r.results || [], (r.results || []).length > 0); });
      $('[data-near-me]').on('click', function () {
        if (!navigator.geolocation) { $st.text('Location is not available in this browser.'); return; }
        $st.text('Finding your location…');
        navigator.geolocation.getCurrentPosition(function (pos) {
          var lat = pos.coords.latitude, lng = pos.coords.longitude;
          L.circleMarker([lat, lng], { radius: 8, color: '#005957', fillColor: '#f5a524', fillOpacity: 1 }).addTo(map).bindTooltip('You are here');
          $.getJSON(cfg.api, { type: 'mosques', lat: lat, lng: lng, limit: 15 }).done(function (r) {
            var rows = r.results || [];
            list(rows);
            $st.text(rows.length ? 'Closest mosques with a confirmed location:' : 'No mosques with a confirmed location yet. Browse by city instead.');
            if (rows.length) { map.fitBounds(L.latLngBounds(rows.slice(0, 6).map(function (p) { return [p.lat, p.lng]; }).concat([[lat, lng]])), { padding: [40, 40], maxZoom: 14 }); }
          });
        }, function () { $st.text('Location permission was not given. Explore the map or browse by city.'); }, { timeout: 10000 });
      });
    });
  }
  if (document.readyState === 'complete') { initMaps(); } else { window.addEventListener('load', initMaps); }

  // Home: mosques near you (no map needed)
  $('[data-near-home]').on('click', function () {
    var $w = $(this).closest('[data-home-nearby]'), $st = $w.find('[data-near-home-status]'), $l = $w.find('[data-near-home-list]');
    if (!navigator.geolocation) { $st.text('Location is not available in this browser.'); return; }
    $st.text('Finding your location…');
    navigator.geolocation.getCurrentPosition(function (pos) {
      $.getJSON($w.data('api'), { type: 'mosques', lat: pos.coords.latitude, lng: pos.coords.longitude, limit: 6 }).done(function (r) {
        var rows = r.results || [];
        $l.empty();
        rows.forEach(function (p) { $l.append($('<li class="near-item">').append($('<a>').attr('href', p.href).append($('<strong>').text(p.title), $('<span>').text(p.city + ' · ' + p.km + ' km')))); });
        $st.text(rows.length ? '' : 'No mosques with a confirmed location near you yet. Choose your city instead.');
      });
    }, function () { $st.text('Location permission was not given. You can choose your city instead.'); }, { timeout: 10000 });
  });
})(jQuery);
