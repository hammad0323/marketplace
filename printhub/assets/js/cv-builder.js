/*
 * CV maker (customer website). The form edits a JSON CV; the server renders the selected template to HTML
 * for the live preview and to an A4 PDF. Drafts auto-save to this device's session.
 */
(function () {
    'use strict';
    var CFG = window.PH_CV, app = document.querySelector('[data-cv-app]');
    if (!CFG || !app) { return; }
    var esc = PH.esc;

    // ---------------------------------------------------------------- list / new
    app.addEventListener('click', function (e) {
        var n = e.target.closest('[data-new]'), d = e.target.closest('[data-del-cv]');
        if (n) {
            n.classList.add('is-active');
            var t = CFG.templates.filter(function (x) { return x.id === +n.dataset.new; })[0];
            PH.api('POST', '/cv', { template_id: t.id, accent_color: t.default_color, data: CFG.default, section_order: Object.keys(CFG.sections), title: 'My CV' }).then(function (r) {
                if (!r.status) { PH.alert(r.message); return; }
                location.href = PH.base + 'cv/' + r.data.token + (CFG.shop ? '?shop=' + CFG.shop : '');
            });
        }
        if (d) {
            Swal.fire({ text: 'Delete this CV?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#BE123C' }).then(function (r) {
                if (r.isConfirmed) { PH.api('DELETE', '/cv/' + d.dataset.delCv).then(function () { location.reload(); }); }
            });
        }
    });
    document.querySelectorAll('.cv-tpl iframe').forEach(function (f) { var w = f.parentNode.clientWidth; f.style.transform = 'scale(' + (w / 794) + ')'; });
    if (!CFG.cv) { return; }

    // ---------------------------------------------------------------- editor
    var cv = CFG.cv, d = cv.data, form = app.querySelector('[data-form]'), frame = app.querySelector('[data-preview]'), saveState = app.querySelector('[data-save-state]');
    var saveTimer, previewTimer, dirty = false;
    var LISTS = {
        experience: { label: 'Work experience', icon: 'briefcase', add: 'Add job', fields: [['role', 'Job title'], ['company', 'Company'], ['location', 'City'], ['start', 'Start (e.g. 2022)'], ['end', 'End'], ['current', 'I work here now', 'check'], ['details', 'What you did — one point per line', 'area']] },
        education: { label: 'Education', icon: 'mortarboard', add: 'Add education', fields: [['degree', 'Degree / class'], ['institute', 'School / college / university'], ['location', 'City'], ['start', 'Start'], ['end', 'End'], ['grade', 'Grade / CGPA'], ['details', 'Details (optional)', 'area']] },
        projects: { label: 'Projects', icon: 'kanban', add: 'Add project', fields: [['name', 'Project name'], ['link', 'Link (optional)'], ['details', 'Description', 'area']] },
        certifications: { label: 'Certifications', icon: 'patch-check', add: 'Add certification', fields: [['name', 'Certificate'], ['issuer', 'Issued by'], ['year', 'Year']] },
        languages: { label: 'Languages', icon: 'translate', add: 'Add language', fields: [['name', 'Language'], ['level', 'Level (e.g. Fluent)']] },
        references: { label: 'References', icon: 'people', add: 'Add reference', fields: [['name', 'Name'], ['position', 'Position'], ['company', 'Company'], ['phone', 'Phone'], ['email', 'Email']] }
    };
    function thumbHtml(x) { return x.preview_is_image ? '<img src="' + esc(x.preview_url) + '" alt="" style="width:100%;height:100%;object-fit:cover">' : '<iframe src="' + esc(x.preview_url) + '?thumb=1" loading="lazy" tabindex="-1" title="' + esc(x.name) + '"></iframe>'; }
    function tpl() { return CFG.templates.filter(function (t) { return t.id === cv.template_id; })[0] || CFG.templates[0]; }
    function input(path, label, value, type) {
        if (type === 'area') { return '<label class="form-label small mb-1">' + label + '</label><textarea class="form-control mb-2" rows="3" data-path="' + path + '">' + esc(value) + '</textarea>'; }
        if (type === 'check') { return '<div class="form-check mb-2"><input class="form-check-input" type="checkbox" data-path="' + path + '"' + (value ? ' checked' : '') + ' id="' + path + '"><label class="form-check-label small" for="' + path + '">' + label + '</label></div>'; }
        return '<label class="form-label small mb-1">' + label + '</label><input class="form-control mb-2" data-path="' + path + '" value="' + esc(value) + '"' + (type ? ' type="' + type + '"' : '') + '>';
    }
    function render() {
        var t = tpl(), p = d.personal, h = '';
        h += '<details class="cv-sec" open><summary><i class="bi bi-palette"></i> Template &amp; colour<span class="ms-auto small c-muted">' + esc(t.name) + '</span></summary><div class="cv-sec-body"><div class="cv-tpls">';
        CFG.templates.forEach(function (x) { h += '<button type="button" class="cv-tpl' + (x.id === cv.template_id ? ' is-active' : '') + '" data-tpl="' + x.id + '"><div class="cv-tpl-img">' + thumbHtml(x) + '</div>' + esc(x.name) + '</button>'; });
        h += '</div><div class="c-opt-label">Accent colour</div><div class="d-flex flex-wrap gap-2 align-items-center">' + ['#0F766E', '#1E3A8A', '#7C3AED', '#DB2777', '#B45309', '#334155', '#000000'].map(function (c) {
            return '<button type="button" data-color="' + c + '" style="width:34px;height:34px;border-radius:50%;border:3px solid ' + (cv.accent_color.toUpperCase() === c ? '#fff;box-shadow:0 0 0 2px ' + c : 'transparent') + ';background:' + c + '" aria-label="' + c + '"></button>';
        }).join('') + '<input type="color" class="form-control form-control-color" data-color-input value="' + esc(cv.accent_color) + '" aria-label="Custom colour"></div></div></details>';
        h += '<details class="cv-sec" open><summary><i class="bi bi-person"></i> Personal details</summary><div class="cv-sec-body"><div class="row g-2">'
            + '<div class="col-12">' + input('personal.full_name', 'Full name', p.full_name) + '</div><div class="col-12">' + input('personal.title', 'Professional title (e.g. Accountant)', p.title) + '</div>'
            + '<div class="col-sm-6">' + input('personal.phone', 'Phone', p.phone, 'tel') + '</div><div class="col-sm-6">' + input('personal.email', 'Email', p.email, 'email') + '</div>'
            + '<div class="col-sm-6">' + input('personal.city', 'City', p.city) + '</div><div class="col-sm-6">' + input('personal.address', 'Address (optional)', p.address) + '</div>'
            + '<div class="col-sm-6">' + input('personal.linkedin', 'LinkedIn (optional)', p.linkedin) + '</div><div class="col-sm-6">' + input('personal.website', 'Website (optional)', p.website) + '</div></div>';
        if (t.supports_photo) {
            h += '<div class="d-flex align-items-center gap-3 my-2">' + (cv.photo_url ? '<img src="' + esc(cv.photo_url) + '" alt="" style="width:60px;height:60px;border-radius:50%;object-fit:cover">' : '<span class="c-file-thumb" style="width:60px;height:60px;border-radius:50%"><i class="bi bi-person"></i></span>')
                + '<label class="c-btn c-btn-sm c-btn-light mb-0"><i class="bi bi-camera"></i> ' + (cv.photo_url ? 'Change photo' : 'Add photo (optional)') + '<input type="file" accept="image/*" hidden data-photo></label>'
                + (cv.photo_url ? '<button type="button" class="c-btn c-btn-sm c-btn-light" data-photo-remove>Remove</button>' : '') + '</div>';
        } else { h += '<p class="small c-muted">This template does not show a photo (recommended for ATS).</p>'; }
        h += input('personal.summary', 'Professional summary — 2–3 sentences about you', p.summary, 'area') + '</div></details>';
        h += '<details class="cv-sec"><summary><i class="bi bi-list-ol"></i> Sections &amp; order</summary><div class="cv-sec-body"><p class="small c-muted">Turn sections on or off and move them up or down.</p>';
        var all = Object.keys(CFG.sections), order = cv.section_order.concat(all.filter(function (k) { return cv.section_order.indexOf(k) < 0; }));
        order.forEach(function (k, i) {
            var on = cv.section_order.indexOf(k) >= 0;
            h += '<div class="d-flex align-items-center gap-2 py-1"><div class="form-check form-switch m-0 flex-grow-1"><input class="form-check-input" type="checkbox" data-sec-toggle="' + k + '"' + (on ? ' checked' : '') + ' id="st' + k + '"><label class="form-check-label" for="st' + k + '">' + esc(CFG.sections[k]) + '</label></div>'
                + (on ? '<button type="button" class="cv-order-btn" data-sec-move="' + k + '" data-d="-1" aria-label="Move up"><i class="bi bi-arrow-up"></i></button><button type="button" class="cv-order-btn" data-sec-move="' + k + '" data-d="1" aria-label="Move down"><i class="bi bi-arrow-down"></i></button>' : '') + '</div>';
        });
        h += '</div></details>';
        Object.keys(LISTS).forEach(function (k) {
            var L = LISTS[k], rows = d[k] || [];
            h += '<details class="cv-sec"' + (rows.length ? '' : '') + '><summary><i class="bi bi-' + L.icon + '"></i> ' + L.label + '<span class="ms-auto small c-muted">' + rows.length + '</span></summary><div class="cv-sec-body">';
            rows.forEach(function (r, i) {
                h += '<div class="cv-entry"><button type="button" class="c-icon-btn cv-remove" data-del="' + k + '.' + i + '" aria-label="Remove"><i class="bi bi-x"></i></button><div class="row g-2">';
                L.fields.forEach(function (f) { var wide = f[2] === 'area' || f[2] === 'check' || ['role', 'company', 'degree', 'institute', 'name'].indexOf(f[0]) >= 0; h += '<div class="' + (wide ? 'col-12' : 'col-6') + '">' + input(k + '.' + i + '.' + f[0], f[1], r[f[0]], f[2]) + '</div>'; });
                h += '</div></div>';
            });
            h += '<button type="button" class="c-btn c-btn-sm c-btn-ghost" data-add="' + k + '"><i class="bi bi-plus-lg"></i> ' + L.add + '</button>';
            if (k === 'references') { h += input('references_on_request', 'Show “References available on request”', d.references_on_request, 'check'); }
            h += '</div></details>';
        });
        h += '<details class="cv-sec"><summary><i class="bi bi-stars"></i> Skills &amp; achievements</summary><div class="cv-sec-body">'
            + '<label class="form-label small mb-1">Skills — one per line</label><textarea class="form-control mb-3" rows="4" data-lines="skills" placeholder="Microsoft Excel&#10;Communication&#10;Urdu typing">' + esc((d.skills || []).join('\n')) + '</textarea>'
            + '<label class="form-label small mb-1">Achievements — one per line</label><textarea class="form-control" rows="3" data-lines="achievements">' + esc((d.achievements || []).join('\n')) + '</textarea></div></details>';
        h += '<div class="c-privacy mt-2"><i class="bi bi-shield-lock"></i> Your CV is saved privately on this device’s session. Only you (and a shop you send it to) can see it.</div>';
        form.innerHTML = h;
        form.querySelectorAll('.cv-tpl iframe').forEach(function (f) { f.style.transform = 'scale(' + (f.parentNode.clientWidth / 794) + ')'; });
    }
    function setPath(path, value) {
        var parts = path.split('.'), o = d;
        for (var i = 0; i < parts.length - 1; i++) { o = o[isNaN(parts[i]) ? parts[i] : +parts[i]]; }
        o[parts[parts.length - 1]] = value;
    }
    function changed(immediate) {
        dirty = true; saveState.textContent = 'Saving…';
        clearTimeout(saveTimer); saveTimer = setTimeout(save, immediate ? 50 : 1200);
        clearTimeout(previewTimer); previewTimer = setTimeout(preview, immediate ? 50 : 500);
    }
    function payload() { return { template_id: cv.template_id, accent_color: cv.accent_color, data: d, section_order: cv.section_order, title: app.querySelector('[data-title]').value }; }
    function save() {
        return PH.api('POST', '/cv/' + cv.token, payload()).then(function (r) {
            saveState.textContent = r.status ? 'Saved' : 'Not saved — ' + r.message;
            if (r.status) { dirty = false; }
            return r;
        });
    }
    /** Shows a true A4 page scaled to the preview width. */
    function a4(html) {
        return html.replace('</head>', '<style>@media screen{html,body{background:#e5e7eb!important}.page{width:794px!important;min-height:1123px!important;margin:12px!important;padding:53px!important;box-shadow:0 2px 12px rgba(0,0,0,.15)!important}}</style>'
            + '<script>function fit(){var z=Math.min(1,(window.innerWidth-24)/794);document.documentElement.style.zoom=z;}window.addEventListener("resize",fit);fit();<\/script></head>');
    }
    function preview() {
        PH.api('POST', '/cv/preview', Object.assign(payload(), { token: cv.token })).then(function (r) {
            if (!r.status) { return; }
            var y = frame.contentWindow ? frame.contentWindow.scrollY : 0;
            frame.srcdoc = a4(r.data.html);
            frame.onload = function () { try { frame.contentWindow.scrollTo(0, y); } catch (e) { /* ignore */ } };
        });
    }
    form.addEventListener('input', function (e) {
        var t = e.target;
        if (t.dataset.path) { setPath(t.dataset.path, t.type === 'checkbox' ? t.checked : t.value); changed(); }
        if (t.dataset.lines) { d[t.dataset.lines] = t.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean); changed(); }
        if (t.matches('[data-color-input]')) { cv.accent_color = t.value.toUpperCase(); changed(); }
    });
    form.addEventListener('change', function (e) {
        var t = e.target;
        if (t.dataset.secToggle) {
            var k = t.dataset.secToggle, i = cv.section_order.indexOf(k);
            if (t.checked && i < 0) { cv.section_order.push(k); } else if (!t.checked && i >= 0) { cv.section_order.splice(i, 1); }
            render(); changed(true);
        }
        if (t.matches('[data-photo]') && t.files[0]) {
            var fd = new FormData(); fd.append('photo', t.files[0]);
            saveState.textContent = 'Uploading photo…';
            PH.upload('/cv/' + cv.token + '/photo', fd).then(function (r) {
                if (!r.status) { PH.alert(r.message); saveState.textContent = 'Saved'; return; }
                cv.photo_url = r.data.photo_url; render(); changed(true);
            });
        }
    });
    form.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-tpl]'))) { cv.template_id = +b.dataset.tpl; var nt = tpl(); if (cv.accent_color === '#0F766E' || !cv.accent_color) { cv.accent_color = nt.default_color; } render(); changed(true); }
        else if ((b = e.target.closest('[data-color]'))) { cv.accent_color = b.dataset.color; render(); changed(true); }
        else if ((b = e.target.closest('[data-add]'))) {
            var k = b.dataset.add, row = {};
            LISTS[k].fields.forEach(function (f) { row[f[0]] = f[2] === 'check' ? false : ''; });
            (d[k] = d[k] || []).push(row);
            if (cv.section_order.indexOf(k) < 0) { cv.section_order.push(k); }
            render(); var sec = form.querySelectorAll('.cv-sec'); sec.forEach(function (s) { if (s.querySelector('[data-add="' + k + '"]')) { s.open = true; var ins = s.querySelectorAll('.cv-entry'); if (ins.length) { ins[ins.length - 1].querySelector('input').focus(); } } });
            changed();
        }
        else if ((b = e.target.closest('[data-del]'))) { var parts = b.dataset.del.split('.'); d[parts[0]].splice(+parts[1], 1); render(); changed(true); }
        else if ((b = e.target.closest('[data-sec-move]'))) {
            var key = b.dataset.secMove, i = cv.section_order.indexOf(key), j = i + (+b.dataset.d);
            if (j >= 0 && j < cv.section_order.length) { cv.section_order.splice(j, 0, cv.section_order.splice(i, 1)[0]); render(); form.querySelectorAll('.cv-sec')[2].open = true; changed(true); }
        }
        else if (e.target.closest('[data-photo-remove]')) { PH.api('DELETE', '/cv/' + cv.token + '/photo').then(function (r) { if (r.status) { cv.photo_url = null; render(); changed(true); } }); }
    });
    app.querySelector('[data-title]').addEventListener('input', function () { changed(); });
    app.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-tab]'))) {
            var show = b.dataset.tab === 'preview';
            app.querySelector('[data-layout]').classList.toggle('show-preview', show);
            app.querySelectorAll('[data-tab]').forEach(function (x) { x.classList.toggle('c-btn-light', x !== b); });
            if (show) { preview(); }
        }
        if (e.target.closest('[data-print]')) { flush().then(function () { window.open(PH.base + 'cv/' + cv.token + '/preview?print=1', '_blank'); }); }
        if ((b = e.target.closest('[data-pdf]'))) { if (dirty) { e.preventDefault(); flush().then(function () { location.href = b.href; }); } }
        if (e.target.closest('[data-share]')) { share(); }
        if (e.target.closest('[data-send]')) { send(); }
    });
    function flush() { clearTimeout(saveTimer); return dirty ? save() : Promise.resolve({ status: true }); }
    function share() {
        flush().then(function () { return fetch(PH.base + 'cv/' + cv.token + '/pdf', { credentials: 'same-origin' }); }).then(function (r) { return r.blob(); }).then(function (blob) {
            var name = (d.personal.full_name || 'My').replace(/[^A-Za-z0-9]+/g, '-') + '-CV.pdf', file = new File([blob], name, { type: 'application/pdf' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) { return navigator.share({ files: [file], title: name }); }
            var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click();
            PH.toast('PDF downloaded — share it from your files.', 'info');
        }).catch(function () {});
    }
    function send() {
        if (!d.personal.full_name) { PH.alert('Add your name first.'); return; }
        flush().then(function () {
            if (CFG.shop) {
                Swal.fire({ text: 'Send this CV to your print shop?', icon: 'question', showCancelButton: true, confirmButtonText: 'Choose printing options', showDenyButton: true, denyButtonText: 'Other shop' }).then(function (r) {
                    if (r.isConfirmed) { location.href = PH.base + 'shop/' + CFG.shop + '/print?cv=' + cv.token; }
                    else if (r.isDenied) { askShop(); }
                });
            } else { askShop(); }
        });
    }
    function askShop() {
        Swal.fire({ title: 'Shop ID', text: 'Enter the ID printed under the shop’s QR code', input: 'text', inputPlaceholder: 'PH-XXXXXX', showCancelButton: true, confirmButtonText: 'Continue',
            preConfirm: function (v) { v = (v || '').trim().toUpperCase(); return PH.api('GET', '/shops/' + encodeURIComponent(v)).then(function (r) { if (!r.status) { Swal.showValidationMessage(r.message); return false; } return v; }); } })
            .then(function (r) { if (r.isConfirmed && r.value) { location.href = PH.base + 'shop/' + r.value + '/print?cv=' + cv.token; } });
    }
    window.addEventListener('beforeunload', function (e) { if (dirty) { save(); } });
    render();
    preview();
})();
