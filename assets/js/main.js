(function () {
    'use strict';

    // ---- Page loader ---------------------------------------------------------
    // This script tag sits at the end of <body>, so the DOM is already parsed
    // by the time it runs — no need to wait on window 'load' (which depends on
    // every subresource, including third-party fonts, finishing).
    (function () {
        var loader = document.getElementById('page-loader');
        if (loader) {
            setTimeout(function () { loader.classList.add('hidden'); }, 250);
        }
    })();

    // ---- Navbar scroll state --------------------------------------------------
    // The initial check runs on the next frame (not synchronously here) so
    // reading window.scrollY doesn't force a layout flush in the middle of
    // this script's own initial execution, right after other DOM writes above.
    var navbar = document.querySelector('.navbar');
    if (navbar) {
        var onScroll = function () {
            navbar.classList.toggle('scrolled', window.scrollY > 12);
        };
        requestAnimationFrame(onScroll);
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    // ---- Mobile nav toggle -----------------------------------------------------
    document.querySelectorAll('[data-nav-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('.nav-links').classList.toggle('mobile-open');
        });
    });

    // ---- Sidebar toggle (dashboards) -------------------------------------------
    document.querySelectorAll('[data-sidebar-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelector('.dash-sidebar').classList.toggle('open');
        });
    });

    // ---- Dropdown menus ---------------------------------------------------------
    // aria-haspopup/aria-expanded are set here in JS (not the markup) so every
    // trigger site-wide — nav, notifications, user menu — gets correct,
    // consistent disclosure-widget semantics without touching each template.
    document.querySelectorAll('[data-dropdown-trigger]').forEach(function (trigger) {
        var menu = document.getElementById(trigger.getAttribute('data-dropdown-trigger'));
        if (!menu) return;
        trigger.setAttribute('aria-haspopup', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
                if (m !== menu) {
                    m.classList.remove('open');
                    var t = document.querySelector('[data-dropdown-trigger="' + m.id + '"]');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });
            var willOpen = !menu.classList.contains('open');
            menu.classList.toggle('open', willOpen);
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });
    document.addEventListener('click', function () {
        document.querySelectorAll('.dropdown-menu.open').forEach(function (m) {
            m.classList.remove('open');
            var t = document.querySelector('[data-dropdown-trigger="' + m.id + '"]');
            if (t) t.setAttribute('aria-expanded', 'false');
        });
    });

    // ---- Tab groups (role="tablist") --------------------------------------------
    // One generic, ARIA-complete implementation shared by every tabbed panel
    // site-wide (doctor/pharmacy profile & storefront, admin settings, public
    // doctor profile) — driven purely by role/aria-controls markup, so the
    // panel container's own class name doesn't matter.
    document.querySelectorAll('[role="tablist"]').forEach(function (tablist) {
        var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
        function activate(tab) {
            tabs.forEach(function (t) {
                var selected = t === tab;
                t.classList.toggle('active', selected);
                t.setAttribute('aria-selected', selected ? 'true' : 'false');
                t.setAttribute('tabindex', selected ? '0' : '-1');
                var panel = document.getElementById(t.getAttribute('aria-controls'));
                if (panel) panel.style.display = selected ? '' : 'none';
            });
        }
        tabs.forEach(function (tab, i) {
            tab.addEventListener('click', function () { activate(tab); });
            tab.addEventListener('keydown', function (e) {
                if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
                e.preventDefault();
                var next = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                next.focus();
                activate(next);
            });
        });
    });

    // ---- FAQ accordions (.faq-q trigger / .faq-a panel, anywhere on the site) --
    document.querySelectorAll('.faq-q').forEach(function (btn, i) {
        var answer = btn.nextElementSibling;
        var icon = btn.querySelector('i');
        var panelId = answer.id || ('faq-panel-' + i + '-' + Math.random().toString(36).slice(2, 7));
        answer.id = panelId;
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', panelId);
        btn.addEventListener('click', function () {
            var isOpen = answer.style.maxHeight && answer.style.maxHeight !== '0px';
            var group = btn.closest('[data-faq-group]') || document;
            group.querySelectorAll('.faq-a').forEach(function (a) { a.style.maxHeight = '0px'; });
            group.querySelectorAll('.faq-q').forEach(function (b) {
                b.querySelector('i').style.transform = 'rotate(0deg)';
                b.setAttribute('aria-expanded', 'false');
            });
            if (!isOpen) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
                icon.style.transform = 'rotate(45deg)';
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    });

    // ---- Weekly availability editor (shared by doctor self-service + admin) --
    // Reads the day-card/avail-block markup from
    // includes/availability-form-fields.php into the flat row array that
    // ajax/doctor-availability-save.php and ajax/admin-doctor-save.php expect.
    window.collectAvailabilitySchedule = function (containerSelector) {
        var schedule = [];
        document.querySelectorAll(containerSelector + ' .availability-day-card').forEach(function (card) {
            var day = card.getAttribute('data-day');
            card.querySelectorAll('.avail-block').forEach(function (block) {
                var enabled = block.querySelector('.avail-enabled');
                if (!enabled || !enabled.checked) return;
                schedule.push({
                    day_of_week: day,
                    consultation_type: block.getAttribute('data-type'),
                    start_time: block.querySelector('.avail-start').value,
                    end_time: block.querySelector('.avail-end').value,
                    slot_duration_mins: block.querySelector('.avail-duration').value
                });
            });
        });
        return schedule;
    };

    // ---- Doctor SEO meta title/description builder (shared by doctor --------
    // self-service profile + admin Add/Edit Doctor) — mirrors
    // doctor_meta_title()/doctor_meta_description() in includes/functions.php
    // so the suggestion shown while editing matches what the live page would
    // fall back to if the field is left blank.
    window.buildDoctorSeoText = function (fullName, designation, qualification, specNames, siteName) {
        fullName = (fullName || '').trim();
        designation = (designation || '').trim();
        qualification = (qualification || '').trim();
        specNames = (specNames || '').trim();
        siteName = siteName || 'DoctorApna';
        if (!fullName) return { title: '', description: '' };
        var role = designation || qualification;
        var title = [fullName, role, specNames].filter(Boolean).join(' — ');
        var description = fullName + ' is a ' + (role || 'doctor') + ' specializing in ' + (specNames || 'multiple specialties')
            + '. View profile, qualifications, specialization and professional details on ' + siteName + '.';
        return { title: title, description: description };
    };

    // ---- "Near Me" geolocation helpers --------------------------------------------
    // Shared by doctors.php/pharmacies.php/products.php (redirect with
    // ?lat=&lng=, sorted server-side by distance) and doctor/pharmacy profile
    // pages (capture the clinic/store's own coordinates into hidden inputs).
    // Both just wrap the browser Geolocation API with the same permission/
    // error handling so every "near me" entry point behaves identically.
    function requestGeolocation(onSuccess, onError) {
        if (!('geolocation' in navigator)) {
            onError('Your browser does not support location — try searching by city instead.');
            return;
        }
        navigator.geolocation.getCurrentPosition(
            function (pos) { onSuccess(pos.coords.latitude, pos.coords.longitude); },
            function (err) {
                var msg = err.code === err.PERMISSION_DENIED
                    ? 'Location permission was denied — allow it in your browser settings, or search by city instead.'
                    : 'Could not get your location. Please try again or search by city.';
                onError(msg);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    window.setupNearMeButton = function (buttonSelector, baseUrl) {
        var btn = document.querySelector(buttonSelector);
        if (!btn) return;
        var originalHtml = btn.innerHTML;
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="loader-ring" style="width:14px;height:14px;border-width:2px;"></span> Locating…';
            requestGeolocation(function (lat, lng) {
                var url = new URL(baseUrl, window.location.origin);
                var params = new URLSearchParams(window.location.search);
                params.delete('page');
                params.set('lat', lat.toFixed(6));
                params.set('lng', lng.toFixed(6));
                url.search = params.toString();
                window.location.href = url.toString();
            }, function (message) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.showToast) showToast('error', 'Location unavailable', message);
                else alert(message);
            });
        });
    };

    window.setupLocationCaptureButton = function (buttonSelector, latInputSelector, lngInputSelector, statusSelector) {
        var btn = document.querySelector(buttonSelector);
        if (!btn) return;
        var originalHtml = btn.innerHTML;
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.innerHTML = '<span class="loader-ring" style="width:14px;height:14px;border-width:2px;"></span> Locating…';
            requestGeolocation(function (lat, lng) {
                document.querySelector(latInputSelector).value = lat.toFixed(7);
                document.querySelector(lngInputSelector).value = lng.toFixed(7);
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                var status = document.querySelector(statusSelector);
                if (status) status.textContent = 'Location captured — click Save to confirm.';
                if (window.showToast) showToast('success', 'Location captured', 'Click Save to confirm your clinic location.');
            }, function (message) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
                if (window.showToast) showToast('error', 'Location unavailable', message);
                else alert(message);
            });
        });
    };

    // ---- Scroll reveal animations ------------------------------------------------
    var revealEls = document.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && revealEls.length) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('in-view');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        revealEls.forEach(function (el) { observer.observe(el); });
    } else {
        revealEls.forEach(function (el) { el.classList.add('in-view'); });
    }

    // ---- Animated counters ---------------------------------------------------------
    var counters = document.querySelectorAll('[data-counter]');
    if ('IntersectionObserver' in window && counters.length) {
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                animateCounter(entry.target);
                counterObserver.unobserve(entry.target);
            });
        }, { threshold: 0.4 });
        counters.forEach(function (el) { counterObserver.observe(el); });
    }
    function animateCounter(el) {
        var target = parseFloat(el.getAttribute('data-counter'));
        var duration = 1400;
        var start = performance.now();
        var suffix = el.getAttribute('data-suffix') || '';
        function step(now) {
            var progress = Math.min((now - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            var value = target * eased;
            el.textContent = (target % 1 === 0 ? Math.round(value) : value.toFixed(1)) + suffix;
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // ---- Button ripple effect -----------------------------------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.btn');
        if (!btn) return;
        var rect = btn.getBoundingClientRect();
        var ripple = document.createElement('span');
        var size = Math.max(rect.width, rect.height);
        ripple.className = 'ripple';
        ripple.style.width = ripple.style.height = size + 'px';
        ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
        ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
        btn.appendChild(ripple);
        setTimeout(function () { ripple.remove(); }, 650);
    });

    // ---- Card tilt effect ---------------------------------------------------------
    document.querySelectorAll('[data-tilt]').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var rect = card.getBoundingClientRect();
            var x = (e.clientX - rect.left) / rect.width - 0.5;
            var y = (e.clientY - rect.top) / rect.height - 0.5;
            card.style.transform = 'perspective(800px) rotateY(' + (x * 8) + 'deg) rotateX(' + (y * -8) + 'deg) translateY(-4px)';
        });
        card.addEventListener('mouseleave', function () {
            card.style.transform = '';
        });
    });

    // ---- Password show/hide toggle -------------------------------------------------
    document.querySelectorAll('[data-toggle-pass]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.getAttribute('data-toggle-pass'));
            if (!input) return;
            var isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.querySelector('i').className = isPass ? 'ri-eye-off-line' : 'ri-eye-line';
        });
    });

    // ---- Password strength meter ----------------------------------------------------
    document.querySelectorAll('[data-pw-strength]').forEach(function (input) {
        var bar = document.querySelector(input.getAttribute('data-pw-strength'));
        if (!bar) return;
        input.addEventListener('input', function () {
            var val = input.value;
            var score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            var pct = (score / 4) * 100;
            var colors = ['#EF4444', '#EF4444', '#F59E0B', '#F59E0B', '#22C55E'];
            bar.style.width = pct + '%';
            bar.style.background = colors[score];
        });
    });

    // ---- Generic horizontal carousel nav (e.g. homepage city carousel) --------
    document.querySelectorAll('[data-carousel]').forEach(function (wrap) {
        var track = wrap.querySelector('[data-carousel-track]');
        if (!track) return;
        var scrollAmount = function () { return Math.min(track.clientWidth * 0.8, 480); };
        var prev = wrap.querySelector('[data-carousel-prev]');
        var next = wrap.querySelector('[data-carousel-next]');
        if (prev) prev.addEventListener('click', function () { track.scrollBy({ left: -scrollAmount(), behavior: 'smooth' }); });
        if (next) next.addEventListener('click', function () { track.scrollBy({ left: scrollAmount(), behavior: 'smooth' }); });
    });
})();
