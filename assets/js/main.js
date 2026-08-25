/* Toursity — shared front-end interactions. jQuery + vanilla JS, no build step. */

(function ($) {
  'use strict';

  // ---- Navbar scroll shadow -------------------------------------------
  var $navbar = $('.navbar-w');
  function onScroll() {
    if (!$navbar.length) return;
    $navbar.toggleClass('is-scrolled', window.scrollY > 8);
  }
  $(window).on('scroll', onScroll);
  onScroll();

  // ---- Mobile nav toggle -------------------------------------------
  $('.mobile-toggle').on('click', function () {
    $('.nav-links').toggleClass('mobile-open');
  });

  // ---- User dropdown ----------------------------------------------
  $('.user-chip').on('click', function (e) {
    e.stopPropagation();
    $('.user-menu').toggleClass('open');
  });
  $(document).on('click', function () {
    $('.user-menu').removeClass('open');
  });

  // ---- "List your business" category dropdown --------------------------
  $('#list-business-btn').on('click', function (e) {
    e.stopPropagation();
    $('#business-type-menu').toggleClass('open');
  });

  // ---- Admin sidebar toggle (mobile) --------------------------------
  $('.admin-menu-toggle').on('click', function () {
    $('.admin-sidebar').toggleClass('open');
  });

  // ---- Scroll reveal via IntersectionObserver -----------------------
  var revealTargets = document.querySelectorAll('.reveal, .reveal-scale, .stagger');
  if ('IntersectionObserver' in window && revealTargets.length) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add('in-view');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    );
    revealTargets.forEach(function (el) { observer.observe(el); });
  } else {
    revealTargets.forEach(function (el) { el.classList.add('in-view'); });
  }

  // ---- Animated counters --------------------------------------------
  function animateCounter(el) {
    var target = parseFloat(el.getAttribute('data-count') || '0');
    var duration = 1400;
    var start = null;
    var suffix = el.getAttribute('data-suffix') || '';

    function step(ts) {
      if (!start) start = ts;
      var progress = Math.min((ts - start) / duration, 1);
      var eased = 1 - Math.pow(1 - progress, 3);
      var value = Math.floor(eased * target);
      el.textContent = value.toLocaleString() + suffix;
      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        el.textContent = target.toLocaleString() + suffix;
      }
    }
    requestAnimationFrame(step);
  }

  var counters = document.querySelectorAll('[data-count]');
  if ('IntersectionObserver' in window && counters.length) {
    var counterObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            counterObserver.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.4 }
    );
    counters.forEach(function (el) { counterObserver.observe(el); });
  }

  // ---- Hero parallax blobs on mouse move -----------------------------
  var $hero = $('.hero');
  if ($hero.length) {
    $hero.on('mousemove', function (e) {
      var rect = this.getBoundingClientRect();
      var x = (e.clientX - rect.left) / rect.width - 0.5;
      var y = (e.clientY - rect.top) / rect.height - 0.5;
      $('.hero-blob-1').css('transform', 'translate(' + (x * 26) + 'px,' + (y * 26) + 'px)');
      $('.hero-blob-2').css('transform', 'translate(' + (x * -20) + 'px,' + (y * -20) + 'px)');
      $('.hero-blob-3').css('transform', 'translate(' + (x * 34) + 'px,' + (y * 34) + 'px)');
    });
  }

  // ---- AJAX favorites ---------------------------------------------------
  $(document).on('click', '.fav-btn', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $btn = $(this);
    var type = $btn.data('fav-type');
    var id = $btn.data('fav-id');
    var token = $btn.data('csrf');
    if (!type || !id) return;

    if (!token) {
      window.location.href = window.appUrl('/customer/login.php') + '?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
      return;
    }

    $.post(window.appUrl('/ajax/favorite.php'), { type: type, id: id, csrf_token: token })
      .done(function (res) {
        if (res && res.ok) {
          $btn.toggleClass('is-fav', res.favorited);
          $btn.find('i').toggleClass('bi-heart', !res.favorited).toggleClass('bi-heart-fill', res.favorited);
          showToast(res.favorited ? 'Saved to favorites' : 'Removed from favorites', 'success');
        }
      })
      .fail(function (xhr) {
        if (xhr.status === 401) {
          window.location.href = window.appUrl('/customer/login.php') + '?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
        } else {
          showToast('Something went wrong. Please try again.', 'danger');
        }
      });
  });

  // ---- Notification bell -----------------------------------------------
  var $bell = $('#notif-bell');
  var $notifPanel = $('#notif-panel');
  var notifCsrf = null;

  function renderNotifications(data) {
    var $count = $('#notif-count');
    if (data.unread > 0) {
      $count.text(data.unread > 9 ? '9+' : data.unread).show();
    } else {
      $count.hide();
    }
    if (!data.items || !data.items.length) {
      $notifPanel.html('<div style="padding:24px;text-align:center;color:var(--ink-mute);font-size:13.5px;">No notifications yet</div>');
      return;
    }
    var html = '<div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid var(--border);"><strong style="font-size:13px;">Notifications</strong><button id="notif-mark-all" style="border:none;background:none;color:var(--purple-600);font-size:12px;font-weight:600;cursor:pointer;">Mark all read</button></div>';
    data.items.forEach(function (n) {
      html += '<a href="' + (n.link || '#') + '" data-id="' + n.id + '" class="notif-item" style="display:block;padding:10px 12px;border-bottom:1px solid var(--border);' + (n.is_read == 0 ? 'background:var(--purple-50);' : '') + '">'
        + '<div style="font-weight:700;font-size:13px;">' + n.title + '</div>'
        + '<div style="font-size:12.5px;color:var(--ink-mute);margin-top:2px;">' + (n.message || '') + '</div>'
        + '<div style="font-size:11px;color:var(--ink-mute);margin-top:4px;">' + n.time_ago + '</div></a>';
    });
    $notifPanel.html(html);
  }

  function loadNotifications() {
    if (!$bell.length) return;
    $.get(window.appUrl('/ajax/notifications.php')).done(function (data) {
      if (data && data.ok) renderNotifications(data);
    });
  }

  if ($bell.length) {
    loadNotifications();
    setInterval(loadNotifications, 30000);
    $bell.on('click', function (e) {
      e.stopPropagation();
      $notifPanel.toggleClass('open');
    });
    $(document).on('click', '#notif-mark-all', function (e) {
      e.preventDefault();
      $.post(window.appUrl('/ajax/notifications.php'), { action: 'mark_all_read', csrf_token: $('meta[name=csrf-token]').attr('content') });
      $('.notif-item').css('background', 'none');
      $('#notif-count').hide();
    });
    $(document).on('click', '.notif-item', function () {
      $.post(window.appUrl('/ajax/notifications.php'), { action: 'mark_read', id: $(this).data('id'), csrf_token: $('meta[name=csrf-token]').attr('content') });
    });
  }

  // ---- Toasts ---------------------------------------------------------
  window.showToast = function (message, type) {
    type = type || 'default';
    var $stack = $('.toast-stack');
    if (!$stack.length) {
      $stack = $('<div class="toast-stack"></div>').appendTo('body');
    }
    var $toast = $('<div class="toast-w ' + type + '">' + message + '</div>');
    $stack.append($toast);
    setTimeout(function () {
      $toast.fadeOut(250, function () { $(this).remove(); });
    }, 3200);
  };

  // ---- Geolocation-aware "near me" prompt (graceful, non-blocking) ----
  var $geoTrigger = $('[data-geo-trigger]');
  if ($geoTrigger.length && navigator.geolocation) {
    $geoTrigger.on('click', function () {
      var $btn = $(this);
      $btn.prop('disabled', true).text('Detecting your location…');
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          var onSearchPage = window.location.pathname.indexOf('/pages/search') !== -1;
          var params = new URLSearchParams(onSearchPage ? window.location.search : '');
          params.set('lat', pos.coords.latitude.toFixed(6));
          params.set('lng', pos.coords.longitude.toFixed(6));
          params.set('sort', 'distance');
          if (onSearchPage) {
            window.location.search = params.toString();
          } else {
            window.location.href = window.appUrl('/pages/search.php') + '?' + params.toString();
          }
        },
        function () {
          $btn.prop('disabled', false).text('Use my location');
          showToast('Location permission denied — pick a city manually instead.', 'danger');
        },
        { timeout: 8000 }
      );
    });
  }
})(jQuery);
