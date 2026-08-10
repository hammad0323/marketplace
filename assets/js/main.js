/* Wanderly — shared front-end interactions. jQuery + vanilla JS, no build step. */

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
          var params = new URLSearchParams(window.location.search);
          params.set('lat', pos.coords.latitude.toFixed(6));
          params.set('lng', pos.coords.longitude.toFixed(6));
          window.location.search = params.toString();
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
