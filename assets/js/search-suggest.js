(function ($) {
    'use strict';
    var $input = $('#hero-search-input');
    var $box = $('#hero-search-box');
    if (!$input.length) return;

    var $dropdown = $('<div class="search-suggest" id="hero-search-suggest"></div>');
    $box.append($dropdown);

    var debounceTimer = null;

    function render(res) {
        var hasDoctors = res.doctors && res.doctors.length;
        var hasSpecs = res.specializations && res.specializations.length;
        if (!hasDoctors && !hasSpecs) {
            $dropdown.html('<div class="search-suggest-empty">No matches — try a different name, condition, or specialty.</div>').addClass('open');
            return;
        }
        var html = '';
        if (hasSpecs) {
            html += '<div class="search-suggest-group-label">Specialties</div>';
            res.specializations.forEach(function (s) {
                html += '<a class="search-suggest-item" href="/doctors?specialization=' + encodeURIComponent(s.slug) + '">' +
                    '<span class="search-suggest-icon"><i class="ri-stethoscope-line"></i></span>' +
                    '<span><strong>' + escapeHtml(s.name) + '</strong><span>Browse specialists</span></span></a>';
            });
        }
        if (hasDoctors) {
            html += '<div class="search-suggest-group-label">Doctors</div>';
            res.doctors.forEach(function (d) {
                html += '<a class="search-suggest-item" href="/doctors/' + encodeURIComponent(d.slug) + '">' +
                    '<img src="' + d.avatar + '" alt="">' +
                    '<span><strong>' + escapeHtml(d.full_name) + '</strong><span>' + escapeHtml(d.spec_names || 'General') + '</span></span></a>';
            });
        }
        $dropdown.html(html).addClass('open');
    }

    function escapeHtml(str) {
        return $('<div>').text(str || '').html();
    }

    $input.on('input', function () {
        var q = $input.val().trim();
        clearTimeout(debounceTimer);
        if (q.length < 2) {
            $dropdown.removeClass('open').empty();
            return;
        }
        debounceTimer = setTimeout(function () {
            $.getJSON('/ajax/search-suggest.php', { q: q }, function (res) {
                if (res.success) render(res);
            });
        }, 220);
    });

    $(document).on('click', function (e) {
        if (!$box.is(e.target) && $box.has(e.target).length === 0) {
            $dropdown.removeClass('open');
        }
    });

    $input.on('focus', function () {
        if ($dropdown.children().length) $dropdown.addClass('open');
    });
})(jQuery);
