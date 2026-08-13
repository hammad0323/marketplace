/* Wanderly — native HTML5 drag-and-drop for the trip itinerary builder. */
(function () {
  'use strict';
  var zones = document.querySelectorAll('.trip-day-dropzone');
  if (!zones.length) return;

  var csrf = document.querySelector('meta[name="csrf-token"]');
  csrf = csrf ? csrf.getAttribute('content') : '';
  var tripId = new URLSearchParams(window.location.search).get('id');
  var dragged = null;

  document.addEventListener('dragstart', function (e) {
    if (e.target.classList.contains('trip-item')) {
      dragged = e.target;
      e.target.style.opacity = '0.4';
    }
  });
  document.addEventListener('dragend', function (e) {
    if (e.target.classList.contains('trip-item')) {
      e.target.style.opacity = '1';
    }
  });

  zones.forEach(function (zone) {
    zone.addEventListener('dragover', function (e) {
      e.preventDefault();
      var afterEl = getDragAfterElement(zone, e.clientY);
      if (!dragged) return;
      if (afterEl == null) {
        zone.appendChild(dragged);
      } else {
        zone.insertBefore(dragged, afterEl);
      }
    });
    zone.addEventListener('drop', function (e) {
      e.preventDefault();
      if (!dragged) return;
      var dayId = zone.getAttribute('data-day-id');
      var itemIds = Array.prototype.map.call(zone.querySelectorAll('.trip-item'), function (el) {
        return el.getAttribute('data-item-id');
      });
      var formData = new FormData();
      formData.append('trip_id', tripId);
      formData.append('day_id', dayId);
      formData.append('csrf_token', csrf);
      itemIds.forEach(function (id) { formData.append('item_ids[]', id); });

      fetch(window.appUrl('/ajax/move-trip-item.php'), { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok && window.showToast) window.showToast('Itinerary updated', 'success');
          else if (!res.ok && window.showToast) window.showToast('Could not save the new order.', 'danger');
        })
        .catch(function () { if (window.showToast) window.showToast('Could not save the new order.', 'danger'); });
    });
  });

  function getDragAfterElement(container, y) {
    var items = Array.prototype.slice.call(container.querySelectorAll('.trip-item:not([style*="opacity: 0.4"])'));
    return items.reduce(function (closest, child) {
      var box = child.getBoundingClientRect();
      var offset = y - box.top - box.height / 2;
      if (offset < 0 && offset > closest.offset) {
        return { offset: offset, element: child };
      }
      return closest;
    }, { offset: -Infinity, element: null }).element;
  }
})();
