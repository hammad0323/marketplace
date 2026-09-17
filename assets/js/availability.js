/**
 * Shared AJAX availability helpers used by /availability.php and /booking.php.
 * Exposes window.WH.fetchAvailability(date, hallId) -> Promise<json>
 */
window.WH = window.WH || {};

WH.fetchAvailability = function (date, hallId) {
  var url = '/ajax/check-availability.php?date=' + encodeURIComponent(date);
  if (hallId) {
    url += '&hall_id=' + encodeURIComponent(hallId);
  }
  return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function (r) { return r.json(); });
};

WH.renderAvailabilityTable = function (container, payload) {
  if (!payload || payload.success === false) {
    container.innerHTML = '<div class="alert alert-error">' + (payload && payload.message ? payload.message : 'Could not load availability. Please try again.') + '</div>';
    return;
  }
  if (!payload.public_visible) {
    container.innerHTML = '<div class="alert alert-info">Live availability display is currently turned off for this website. Please contact us directly to check availability.</div>';
    return;
  }
  if (!payload.halls || !payload.halls.length) {
    container.innerHTML = '<div class="alert alert-info">No halls found.</div>';
    return;
  }
  var html = '';
  payload.halls.forEach(function (hall) {
    html += '<table class="avail-table"><caption style="text-align:left;font-family:var(--font-head);font-size:1.15rem;margin-bottom:10px;">' + hall.name + '</caption><tbody>';
    hall.slots.forEach(function (slot) {
      var pillClass = slot.status === 'available' ? 'pill-available' : 'pill-booked';
      var icon = slot.status === 'available' ? '&#10003;' : '&#10005;';
      var label = slot.status === 'available' ? 'Available' : 'Booked';
      html += '<tr class="avail-row"><td>' + slot.name + ' <span style="color:#8a7d83;font-weight:400;font-size:.82rem;">(' + slot.time_range + ')</span></td>'
        + '<td style="text-align:right"><span class="pill ' + pillClass + '">' + icon + ' ' + label + '</span></td></tr>';
    });
    html += '</tbody></table>';
  });
  container.innerHTML = html;
};
