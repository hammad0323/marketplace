/**
 * Reusable admin calendar widget. Usage:
 *   var cal = WH.initCalendar(document.getElementById('calRoot'), {
 *     hallSelect: document.getElementById('calHallFilter'), // optional
 *     onDateClick: function (dateStr) { ... }
 *   });
 */
window.WH = window.WH || {};

WH.initCalendar = function (root, opts) {
  opts = opts || {};
  var state = { year: new Date().getFullYear(), month: new Date().getMonth() + 1, selected: null };
  var dow = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  function fetchMonth() {
    var hallId = opts.hallSelect ? opts.hallSelect.value : '';
    var base = window.WH_BASE || '';
    var url = base + '/ajax/calendar-data.php?year=' + state.year + '&month=' + state.month + (hallId ? '&hall_id=' + hallId : '');
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
  }

  function pad(n) { return n < 10 ? '0' + n : '' + n; }

  function render() {
    root.innerHTML = '<div class="cal-head">'
      + '<h3 style="margin:0;">' + monthName(state.month) + ' ' + state.year + '</h3>'
      + '<div class="cal-nav">'
      + '<button type="button" class="btn btn-light btn-sm" data-nav="prev"><i class="fa-solid fa-chevron-left"></i></button>'
      + '<button type="button" class="btn btn-light btn-sm" data-nav="today">Today</button>'
      + '<button type="button" class="btn btn-light btn-sm" data-nav="next"><i class="fa-solid fa-chevron-right"></i></button>'
      + '</div></div>'
      + '<div class="cal-grid" id="calGridDow"></div>'
      + '<div class="cal-grid" id="calGridDays" style="margin-top:6px;"></div>';

    var dowRow = root.querySelector('#calGridDow');
    dow.forEach(function (d) { dowRow.innerHTML += '<div class="cal-dow">' + d + '</div>'; });

    root.querySelectorAll('[data-nav]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var nav = btn.getAttribute('data-nav');
        if (nav === 'prev') { state.month--; if (state.month < 1) { state.month = 12; state.year--; } }
        else if (nav === 'next') { state.month++; if (state.month > 12) { state.month = 1; state.year++; } }
        else { var t = new Date(); state.year = t.getFullYear(); state.month = t.getMonth() + 1; }
        load();
      });
    });

    load();
  }

  function monthName(m) {
    return ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][m - 1];
  }

  function load() {
    var head = root.querySelector('.cal-head h3');
    if (head) head.textContent = monthName(state.month) + ' ' + state.year;
    var grid = root.querySelector('#calGridDays');
    grid.innerHTML = '<div class="hint" style="grid-column:1/-1;padding:20px 0;text-align:center;">Loading…</div>';
    fetchMonth().then(function (data) {
      if (data.public_visible === false) {
        grid.innerHTML = '<div class="alert alert-info" style="grid-column:1/-1;">Live availability display is currently turned off. Please contact us directly, or submit an online booking request and we\'ll confirm it for you.</div>';
        return;
      }
      grid.innerHTML = '';
      var firstDay = new Date(state.year, state.month - 1, 1).getDay();
      var daysInMonth = new Date(state.year, state.month, 0).getDate();
      var todayStr = new Date().toISOString().slice(0, 10);
      for (var i = 0; i < firstDay; i++) {
        grid.innerHTML += '<div class="cal-cell empty"></div>';
      }
      for (var d = 1; d <= daysInMonth; d++) {
        var dateStr = state.year + '-' + pad(state.month) + '-' + pad(d);
        var info = data.days && data.days[dateStr];
        var state_ = info ? info.state : 'available';
        var label = state_ === 'full' ? 'Fully Booked' : (state_ === 'partial' ? 'Partially Booked' : 'Available');
        var dotClass = 'state-' + state_;
        var isToday = dateStr === todayStr ? ' today' : '';
        var isPast = dateStr < todayStr ? ' past' : '';
        var cell = document.createElement('div');
        cell.className = 'cal-cell' + isToday + isPast;
        cell.setAttribute('data-date', dateStr);
        cell.innerHTML = '<div class="date-num">' + d + '</div><div class="state-dot ' + dotClass + '">' + label + '</div>';
        cell.addEventListener('click', function () {
          root.querySelectorAll('.cal-cell').forEach(function (c) { c.classList.remove('selected'); });
          this.classList.add('selected');
          state.selected = this.getAttribute('data-date');
          if (opts.onDateClick) opts.onDateClick(state.selected);
        });
        grid.appendChild(cell);
      }
    });
  }

  render();
  if (opts.hallSelect) {
    opts.hallSelect.addEventListener('change', load);
  }
  return { reload: load, getState: function () { return state; } };
};
