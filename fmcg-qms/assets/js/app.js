/* QualityCore - shared front-end helpers */
(function () {
  'use strict';

  window.QMS = window.QMS || {};
  QMS.baseUrl = document.documentElement.getAttribute('data-base-url') || '';
  QMS.csrfToken = document.documentElement.getAttribute('data-csrf') || '';

  if (window.jQuery) {
    jQuery.ajaxSetup({
      headers: { 'X-CSRF-Token': QMS.csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var toggleBtn = document.getElementById('sidebarToggle');
    var sidebar = document.querySelector('.app-sidebar');
    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    }

    initNotifications();
    initRevealOnScroll();
    initGlobalSearch();
  });

  function initNotifications() {
    var bellBadge = document.getElementById('notifCount');
    var bellMenu = document.getElementById('notifList');
    if (!bellBadge || !window.jQuery) return;

    function load() {
      jQuery.getJSON(QMS.baseUrl + '/ajax/common/notifications.php', { action: 'list' }, function (res) {
        if (!res || !res.success) return;
        bellBadge.textContent = res.unread > 99 ? '99+' : res.unread;
        bellBadge.style.display = res.unread > 0 ? 'flex' : 'none';
        if (bellMenu) {
          bellMenu.innerHTML = res.items.length ? res.items.map(renderNotifItem).join('') :
            '<div class="text-center text-muted py-3 small">No notifications yet</div>';
        }
      });
    }
    function renderNotifItem(n) {
      var sevIcon = { danger: 'bi-exclamation-octagon-fill text-danger', warning: 'bi-exclamation-triangle-fill text-warning',
        success: 'bi-check-circle-fill text-success', info: 'bi-info-circle-fill text-primary' }[n.severity] || 'bi-bell-fill text-primary';
      return '<a href="' + (n.link || '#') + '" class="dropdown-item py-2 border-bottom ' + (n.is_read == 0 ? 'bg-light' : '') + '">' +
        '<div class="d-flex gap-2"><i class="bi ' + sevIcon + ' mt-1"></i><div><div class="small fw-semibold">' + escapeHtml(n.title) +
        '</div><div class="small text-muted">' + escapeHtml(n.message || '') + '</div><div class="small text-muted">' + n.time_ago + '</div></div></div></a>';
    }
    load();
    setInterval(load, 45000);
    var markAllBtn = document.getElementById('markAllRead');
    if (markAllBtn) {
      markAllBtn.addEventListener('click', function (e) {
        e.preventDefault();
        jQuery.post(QMS.baseUrl + '/ajax/common/notifications.php', { action: 'mark_all_read', csrf_token: QMS.csrfToken }, load);
      });
    }
  }

  function initGlobalSearch() {
    var input = document.getElementById('globalSearchInput');
    var results = document.getElementById('globalSearchResults');
    if (!input || !results || !window.jQuery) return;
    var timer = null;
    input.addEventListener('input', function () {
      clearTimeout(timer);
      var q = input.value.trim();
      if (q.length < 2) { results.classList.add('d-none'); return; }
      timer = setTimeout(function () {
        jQuery.getJSON(QMS.baseUrl + '/ajax/common/global_search.php', { q: q }, function (res) {
          if (!res || !res.success) return;
          if (!res.items.length) {
            results.innerHTML = '<div class="p-3 text-muted small">No matches for "' + escapeHtml(q) + '"</div>';
          } else {
            results.innerHTML = res.items.map(function (it) {
              return '<a href="' + it.url + '" class="dropdown-item py-2"><span class="badge bg-secondary-subtle text-secondary me-2">' +
                escapeHtml(it.type) + '</span>' + escapeHtml(it.label) + '</a>';
            }).join('');
          }
          results.classList.remove('d-none');
        });
      }, 250);
    });
    document.addEventListener('click', function (e) {
      if (!results.contains(e.target) && e.target !== input) results.classList.add('d-none');
    });
  }

  function initRevealOnScroll() {
    var els = document.querySelectorAll('.reveal');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) { els.forEach(function (el) { el.classList.add('in'); }); return; }
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) { if (entry.isIntersecting) entry.target.classList.add('in'); });
    }, { threshold: 0.15 });
    els.forEach(function (el) { obs.observe(el); });
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  QMS.escapeHtml = escapeHtml;

  QMS.confirmAction = function (opts, onConfirm) {
    if (window.Swal) {
      Swal.fire({
        title: opts.title || 'Are you sure?', text: opts.text || '', icon: opts.icon || 'warning',
        showCancelButton: true, confirmButtonColor: '#2563EB', cancelButtonColor: '#94A3B8',
        confirmButtonText: opts.confirmText || 'Yes, continue'
      }).then(function (r) { if (r.isConfirmed) onConfirm(); });
    } else if (confirm(opts.text || 'Are you sure?')) {
      onConfirm();
    }
  };

  QMS.toast = function (icon, title) {
    if (window.Swal) {
      Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true })
        .fire({ icon: icon, title: title });
    } else {
      alert(title);
    }
  };
})();
