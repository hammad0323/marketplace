(function () {
  'use strict';

  // Sidebar toggle (mobile)
  var sidebarToggle = document.querySelector('.sidebar-toggle');
  var sidebar = document.querySelector('.admin-sidebar');
  if (sidebarToggle && sidebar) {
    sidebarToggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
  }

  // Notification dropdown
  var notifBtn = document.querySelector('.notif-btn');
  var notifDropdown = document.querySelector('.notif-dropdown');
  if (notifBtn && notifDropdown) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      notifDropdown.classList.toggle('open');
      if (notifDropdown.classList.contains('open')) {
        fetch((window.WH_BASE || '') + '/ajax/notifications.php?action=mark_read', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function () {
            var badge = notifBtn.querySelector('.notif-badge');
            if (badge) badge.remove();
          });
      }
    });
    document.addEventListener('click', function () { notifDropdown.classList.remove('open'); });
    notifDropdown.addEventListener('click', function (e) { e.stopPropagation(); });
  }

  // Generic modal open/close via data attributes:
  // <button data-modal-open="#modal-id"> / <button data-modal-close>
  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var sel = btn.getAttribute('data-modal-open');
      var modal = document.querySelector(sel);
      if (modal) modal.classList.add('open');
    });
  });
  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var overlay = btn.closest('.modal-overlay');
      if (overlay) overlay.classList.remove('open');
    });
  });
  document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
    overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.classList.remove('open'); });
  });

  // Confirm before destructive actions: <a data-confirm="Are you sure?" href="...">
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!confirm(el.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // Auto-dismiss flash alerts
  document.querySelectorAll('.alert[data-autodismiss]').forEach(function (el) {
    setTimeout(function () { el.style.display = 'none'; }, 5000);
  });
})();
