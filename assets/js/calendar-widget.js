/**
 * Shared full-month date picker used by the booking widget (doctor-profile.php)
 * and the reschedule modal (patient/appointments.php). Renders a real calendar
 * grid (weekday header + every day of the month, correctly aligned, with
 * previous/next month navigation) instead of a flat N-day strip.
 */
(function ($) {
    'use strict';

    function pad(n) { return n < 10 ? '0' + n : '' + n; }
    function isoLocal(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

    window.buildMonthCalendar = function ($container, opts) {
        opts = opts || {};
        var onSelect = opts.onSelect || function () {};
        var maxMonthsAhead = opts.maxMonthsAhead || 3;

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var todayIso = isoLocal(today);
        var minMonthIndex = today.getFullYear() * 12 + today.getMonth();

        var viewYear = today.getFullYear();
        var viewMonth = today.getMonth();

        function render() {
            $container.empty();
            var monthIndex = viewYear * 12 + viewMonth;
            var atMin = monthIndex <= minMonthIndex;
            var atMax = monthIndex >= minMonthIndex + maxMonthsAhead;
            var monthLabel = new Date(viewYear, viewMonth, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' });

            var $head = $('<div class="calendar-head"></div>');
            var $prev = $('<button type="button" class="calendar-nav-btn" aria-label="Previous month"><i class="ri-arrow-left-s-line"></i></button>');
            var $next = $('<button type="button" class="calendar-nav-btn" aria-label="Next month"><i class="ri-arrow-right-s-line"></i></button>');
            $prev.prop('disabled', atMin).on('click', function () { changeMonth(-1); });
            $next.prop('disabled', atMax).on('click', function () { changeMonth(1); });
            $head.append($prev, $('<div class="calendar-month-label"></div>').text(monthLabel), $next);
            $container.append($head);

            var $grid = $('<div class="calendar-grid"></div>');
            ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(function (label) {
                $grid.append($('<div class="dow"></div>').text(label));
            });

            var firstOfMonth = new Date(viewYear, viewMonth, 1);
            var startWeekday = firstOfMonth.getDay();
            var daysInMonth = new Date(viewYear, viewMonth + 1, 0).getDate();

            for (var lead = 0; lead < startWeekday; lead++) {
                $grid.append($('<div class="calendar-day muted"></div>'));
            }
            for (var day = 1; day <= daysInMonth; day++) {
                var d = new Date(viewYear, viewMonth, day);
                var iso = isoLocal(d);
                var isPast = d.getTime() < today.getTime();
                var $day = $('<div class="calendar-day"></div>')
                    .text(day)
                    .attr('data-date', iso)
                    .attr('title', d.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }));
                if (isPast) {
                    $day.addClass('muted blocked');
                } else {
                    $day.addClass('has-slots');
                }
                if (iso === todayIso) $day.addClass('today');
                $grid.append($day);
            }
            $container.append($grid);

            var selectedIso = $container.data('selected-date');
            if (selectedIso) {
                $grid.find('.calendar-day[data-date="' + selectedIso + '"]').addClass('selected');
            }
        }

        function changeMonth(delta) {
            viewMonth += delta;
            if (viewMonth < 0) { viewMonth = 11; viewYear--; }
            if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            render();
        }

        $container.off('click.monthCalendar').on('click.monthCalendar', '.calendar-day.has-slots', function () {
            $container.find('.calendar-day').removeClass('selected');
            $(this).addClass('selected');
            $container.data('selected-date', $(this).data('date'));
            onSelect($(this).data('date'), $(this).attr('title'));
        });

        render();

        return {
            selectToday: function () {
                $container.find('.calendar-day[data-date="' + todayIso + '"]').trigger('click');
            }
        };
    };
})(jQuery);
