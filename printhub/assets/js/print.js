/*
 * PrintHub print wizard (customer website). Steps: service → files → options → preview → checkout.
 * Edits are sent as instructions (crop, rotation, page order, layout); the server keeps the original file
 * and builds a separate print-ready PDF. Prices always come from the server.
 */
(function () {
    'use strict';
    var CFG = window.PH_PRINT;
    var root = document.querySelector('[data-wizard]');
    if (!CFG || !root) { return; }
    var code = CFG.shop.shop_code, view = root.querySelector('[data-view]'), stepsBar = root.querySelector('[data-steps]');
    var KEY = 'print_' + code;
    var state = PH.store.get(KEY, null) || { items: [], cur: null, step: 1 };
    var priceTimer = null;

    function save() { PH.store.set(KEY, state); }
    function cat(id) { return CFG.categories.filter(function (c) { return c.id === id; })[0] || null; }
    function opts(c, g) { return (c && c.options && c.options[g]) || []; }
    function optById(c, g, id) { return opts(c, g).filter(function (o) { return o.id === id; })[0] || null; }
    function defOpt(c, g) { var l = opts(c, g); var d = l.filter(function (o) { return o.is_default; })[0]; return d || l[0] || null; }
    function basis(it) {
        var c = cat(it.category_id), sub = c && c.subcategories.filter(function (s) { return s.id === it.subcategory_id; })[0];
        return (sub && sub.pricing_basis) || (c && c.pricing_basis) || 'pages';
    }
    function subCode(it) { var c = cat(it.category_id), s = c && c.subcategories.filter(function (x) { return x.id === it.subcategory_id; })[0]; return s ? s.code : ''; }
    function newItem(catId) {
        var c = cat(catId), it = { category_id: catId, subcategory_id: null, files: [], paper_size_id: null, color: null, sides: null, paper_type_id: null, gsm_id: null,
            orientation: 'portrait', finishing: [], copies: 1, quantity: 1, pages: 1, page_selection: [], layout: { fit: 'fit', margin_mm: 5, scale: 100 }, notes: '' };
        ['paper_size', 'paper_type', 'gsm'].forEach(function (g) { var d = defOpt(c, g); it[g + '_id'] = d ? d.id : null; });
        var col = defOpt(c, 'color'), sides = defOpt(c, 'sides');
        it.color = col ? col.code : null; it.sides = sides ? sides.code : null;
        if (c && c.subcategories.length === 1) { it.subcategory_id = c.subcategories[0].id; }
        return it;
    }
    function pagesFor(it) {
        if (it.cv_token) { return it.cv_pages || 1; }
        var files = it.files, n = 0;
        if (files.length === 1 && files[0].kind !== 'image' && (files[0].pages || files[0].manual_pages)) {
            return it.page_selection.length || files[0].pages || files[0].manual_pages || 1;
        }
        files.forEach(function (f) { n += f.kind === 'image' ? 1 : (f.pages || f.manual_pages || 1); });
        return Math.max(1, n);
    }
    function paperAspect(it) {
        var c = cat(it.category_id), p = optById(c, 'paper_size', it.paper_size_id), w = p && p.width_mm ? p.width_mm : (it.custom_width_mm || 210), h = p && p.height_mm ? p.height_mm : (it.custom_height_mm || 297);
        var portrait = it.orientation !== 'landscape';
        return portrait ? Math.min(w, h) / Math.max(w, h) : Math.max(w, h) / Math.min(w, h);
    }
    function setStep(n) {
        state.step = n; save();
        Array.prototype.forEach.call(stepsBar.children, function (s, i) { s.classList.toggle('is-done', i < n); });
        window.scrollTo({ top: 0, behavior: 'smooth' });
        ({ 1: stepService, 2: stepFiles, 3: stepOptions, 4: stepPreview, 5: stepCheckout })[n]();
    }
    function itemPayload(it) {
        return { category_id: it.category_id, subcategory_id: it.subcategory_id, files: it.files.map(function (f) { return f.token; }), cv_token: it.cv_token || null,
            paper_size_id: it.paper_size_id, custom_width_mm: it.custom_width_mm, custom_height_mm: it.custom_height_mm, color: it.color, sides: it.sides,
            paper_type_id: it.paper_type_id, gsm_id: it.gsm_id, orientation: it.orientation, finishing: it.finishing, copies: it.copies, quantity: it.quantity,
            pages: pagesFor(it), page_selection: it.page_selection, layout: it.layout, notes: it.notes };
    }
    function priceBox(items, el) {
        clearTimeout(priceTimer);
        priceTimer = setTimeout(function () {
            el.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            PH.api('POST', '/shops/' + code + '/price', { items: items.map(itemPayload) }).then(function (r) {
                if (!r.status) { el.innerHTML = '<small class="c-muted">' + PH.esc(r.message) + '</small>'; return; }
                var d = r.data;
                el.innerHTML = d.pricing_mode === 'auto' ? '<div class="c-price">' + PH.money(d.total) + '<small>Estimate' + (d.minimum_adjustment ? ' · incl. minimum' : '') + '</small></div>'
                    : '<div class="c-price c-price-quote">Price on review<small>Shop sends the price</small></div>';
                el.dataset.breakdown = JSON.stringify(d);
            });
        }, 250);
    }

    // ---------------------------------------------------------------- step 1: service
    function stepService() {
        var it = state.cur;
        var h = '<div class="c-step-title">What do you want to print?</div><div class="c-step-sub">Choose a service offered by ' + PH.esc(CFG.shop.name) + '.</div><div class="c-services">';
        CFG.categories.forEach(function (c) {
            h += '<button type="button" class="c-service text-start" data-cat="' + c.id + '"' + (it && it.category_id === c.id ? ' style="border-color:var(--c-brand)"' : '') + '>' + (c.icon_url ? '<img src="' + PH.esc(c.icon_url) + '" alt="">' : '<i class="bi bi-' + PH.esc(c.icon) + '"></i>')
                + '<strong>' + PH.esc(c.name) + '</strong><small>' + PH.esc(c.description || '') + '</small></button>';
        });
        h += '</div>';
        if (it && cat(it.category_id) && cat(it.category_id).subcategories.length > 1) {
            h += '<div class="c-opt-label mt-4">Type of ' + PH.esc(cat(it.category_id).name.toLowerCase()) + '</div><div class="c-choice-grid">';
            cat(it.category_id).subcategories.forEach(function (s) { h += '<button type="button" class="c-choice' + (it.subcategory_id === s.id ? ' is-active' : '') + '" data-sub="' + s.id + '">' + PH.esc(s.name) + '</button>'; });
            h += '</div>';
        }
        if (state.items.length) { h += '<div class="c-sticky-cta"><button class="c-btn c-btn-light c-btn-block" data-go="5">Back to checkout (' + state.items.length + ' item' + (state.items.length > 1 ? 's' : '') + ')</button></div>'; }
        if (it && (it.subcategory_id || !cat(it.category_id).subcategories.length)) { h += '<div class="c-sticky-cta"><button class="c-btn c-btn-block" data-go="2">Continue <i class="bi bi-arrow-right"></i></button></div>'; }
        view.innerHTML = h;
    }
    view.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-cat]'))) {
            var id = +b.dataset.cat;
            if (!state.cur || state.cur.category_id !== id) { var keep = state.cur ? state.cur.files : []; state.cur = newItem(id); state.cur.files = keep; }
            save();
            if (cat(id).subcategories.length <= 1) { setStep(2); } else { stepService(); setTimeout(function () { view.querySelector('[data-sub]').scrollIntoView({ behavior: 'smooth', block: 'center' }); }, 50); }
        } else if ((b = e.target.closest('[data-sub]'))) {
            state.cur.subcategory_id = +b.dataset.sub;
            var sc = subCode(state.cur);
            if (/passport|photo|glossy|matte|4x6|5x7|pvc|invitation/.test(sc)) { var colOpt = opts(cat(state.cur.category_id), 'color').filter(function (o) { return o.code === 'color'; })[0]; if (colOpt) { state.cur.color = 'color'; } }
            if (/passport/.test(sc)) { state.cur.layout = { fit: 'grid', margin_mm: 3, gap_mm: 3, sheet: '4x6' }; var pp = opts(cat(state.cur.category_id), 'paper_size').filter(function (o) { return o.code === 'passport'; })[0]; if (pp) { state.cur.paper_size_id = pp.id; } state.cur.quantity = 8; }
            if (/cnic/.test(sc)) { state.cur.layout = { fit: 'id_pair', margin_mm: 10 }; var ic = opts(cat(state.cur.category_id), 'paper_size').filter(function (o) { return o.code === 'id_card'; })[0]; if (ic) { state.cur.paper_size_id = ic.id; } }
            save(); setStep(2);
        } else if ((b = e.target.closest('[data-go]'))) {
            var to = +b.dataset.go;
            if (to === 3 && !validFiles()) { return; }
            setStep(to);
        }
    });

    // ---------------------------------------------------------------- step 2: files
    function needsFile() { var c = cat(state.cur.category_id); return c && c.accepts_upload && !state.cur.cv_token; }
    function validFiles() {
        var it = state.cur;
        if (it.cv_token) { return true; }
        if (!it.files.length && needsFile() && cat(it.category_id).code !== 'custom') { PH.alert('Please upload the file you want to print.'); return false; }
        if (it.files.some(function (f) { return f.uploading; })) { PH.alert('Please wait for the upload to finish.', 'info'); return false; }
        if (it.files.some(function (f) { return f.needs_page_count && !f.manual_pages; })) { PH.alert('Please enter how many pages your Word file has.'); return false; }
        if (it.layout.fit === 'id_pair' && it.files.filter(function (f) { return f.kind === 'image'; }).length < 2) { PH.alert('Upload two photos: the front and the back of the card.'); return false; }
        return true;
    }
    function stepFiles() {
        var it = state.cur, c = cat(it.category_id);
        var h = '<div class="c-step-title">' + (it.cv_token ? 'Your CV' : 'Upload your files') + '</div><div class="c-step-sub">' + PH.esc(c.name) + (subCode(it) ? ' · ' + PH.esc(c.subcategories.filter(function (s) { return s.id === it.subcategory_id; })[0].name) : '')
            + (it.layout.fit === 'id_pair' ? ' — upload the <strong>front</strong> and <strong>back</strong> photos' : '') + '</div>';
        if (it.cv_token) {
            h += '<div class="c-file"><div class="c-file-thumb"><i class="bi bi-file-person"></i></div><div class="c-file-body"><strong>' + PH.esc(it.cv_title || 'CV') + '</strong><small>' + (it.cv_pages || 1) + ' page(s) · A4 PDF from the CV maker</small></div></div>';
        } else {
            h += '<label class="c-drop" data-drop><input type="file" hidden multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.docx,application/pdf,image/*" data-file-input><i class="bi bi-cloud-arrow-up"></i>'
                + '<div class="fw-bold mt-2">Tap to choose files</div><div class="small c-muted">PDF, photos (JPG, PNG) or Word (.docx) · up to ' + CFG.shop.max_upload_mb + ' MB each</div></label><div data-files></div>';
            if (c.code === 'custom') { h += '<div class="mt-3"><label class="form-label">Describe what you need</label><textarea class="form-control" rows="3" data-notes placeholder="Size, paper, quantity, finishing…">' + PH.esc(it.notes) + '</textarea></div>'; }
        }
        h += '<p class="c-privacy mt-3"><i class="bi bi-shield-lock"></i> ' + PH.esc(CFG.privacy) + '</p>';
        h += '<div class="c-sticky-cta d-flex gap-2"><button class="c-btn c-btn-light" data-go="1"><i class="bi bi-arrow-left"></i></button><button class="c-btn flex-grow-1" data-go="3">Continue <i class="bi bi-arrow-right"></i></button></div>';
        view.innerHTML = h;
        renderFiles();
        var drop = view.querySelector('[data-drop]');
        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
            ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
            drop.addEventListener('drop', function (e) { addFiles(e.dataTransfer.files); });
            view.querySelector('[data-file-input]').addEventListener('change', function () { addFiles(this.files); this.value = ''; });
        }
        var notes = view.querySelector('[data-notes]');
        if (notes) { notes.addEventListener('input', function () { it.notes = this.value; save(); }); }
    }
    function renderFiles() {
        var box = view.querySelector('[data-files]');
        if (!box) { return; }
        var it = state.cur;
        box.innerHTML = it.files.map(function (f, i) {
            var meta = f.uploading ? 'Uploading…' : (f.kind === 'image' ? (f.width + ' × ' + f.height + ' px') : (f.pages ? f.pages + ' page' + (f.pages > 1 ? 's' : '') : 'Word document'));
            if (it.files.length === 1 && f.kind === 'pdf' && it.page_selection.length) { meta += ' · printing ' + it.page_selection.length; }
            if (f.kind === 'image' && it.layout.crop) { meta += ' · cropped'; }
            var tools = f.uploading ? '' : (f.kind === 'image' && it.files.filter(function (x) { return x.kind === 'image'; }).length === 1 ? '<button class="c-btn c-btn-sm c-btn-light" data-edit-img="' + i + '"><i class="bi bi-crop"></i> Crop</button>' : '')
                + ((f.kind === 'pdf' || (f.kind === 'docx' && f.converted)) && it.files.length === 1 ? '<button class="c-btn c-btn-sm c-btn-light" data-edit-pages="' + i + '"><i class="bi bi-files"></i> Pages</button>' : '');
            return '<div class="c-file"><div class="c-file-thumb" data-thumb="' + i + '">' + (f.kind === 'image' && f.url ? '<img src="' + PH.esc(f.url) + '" alt="">' : '<i class="bi bi-file-earmark-' + (f.kind === 'docx' ? 'word' : 'pdf') + '"></i>') + '</div>'
                + '<div class="c-file-body"><strong>' + PH.esc(f.name) + '</strong><small>' + PH.esc(meta) + '</small>'
                + (f.uploading ? '<div class="c-progress"><span style="width:' + Math.round((f.progress || 0) * 100) + '%"></span></div>' : '')
                + (f.needs_page_count ? '<div class="d-flex align-items-center gap-2 mt-1"><small>Pages:</small><input class="form-control form-control-sm" style="max-width:90px;min-height:34px" type="number" min="1" value="' + (f.manual_pages || '') + '" data-manual="' + i + '"></div><small class="text-warning d-block">' + PH.esc(f.notice || 'The shop prints Word files from the original.') + '</small>' : '')
                + '<div class="d-flex gap-2 mt-1 flex-wrap">' + tools + '</div></div>'
                + (f.uploading ? '' : '<button class="c-icon-btn" data-remove="' + i + '" aria-label="Remove"><i class="bi bi-trash3"></i></button>') + '</div>';
        }).join('');
        it.files.forEach(function (f, i) { if ((f.kind === 'pdf' || f.converted) && f.pdf_url && !f.uploading) { thumb(f.pdf_url, box.querySelector('[data-thumb="' + i + '"]')); } });
    }
    function withPdf(cb) { if (window.pdfjsLib) { cb(window.pdfjsLib); } else { document.addEventListener('pdfjs:ready', function () { cb(window.pdfjsLib); }, { once: true }); } }
    function thumb(url, el) {
        if (!el) { return; }
        withPdf(function (lib) {
            lib.getDocument(url).promise.then(function (pdf) { return pdf.getPage(1); }).then(function (page) {
                var vp = page.getViewport({ scale: 0.25 }), cv = document.createElement('canvas');
                cv.width = vp.width; cv.height = vp.height;
                return page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () { el.innerHTML = ''; el.appendChild(cv); });
            }).catch(function () {});
        });
    }
    function addFiles(list) {
        var it = state.cur;
        Array.prototype.forEach.call(list, function (file) {
            if (file.size > CFG.shop.max_upload_mb * 1048576) { PH.alert(file.name + ' is larger than ' + CFG.shop.max_upload_mb + ' MB.'); return; }
            var f = { name: file.name, uploading: true, progress: 0, kind: /pdf/.test(file.type) ? 'pdf' : (/image/.test(file.type) ? 'image' : 'docx') };
            it.files.push(f); renderFiles();
            var fd = new FormData(); fd.append('file', file);
            PH.upload('/shops/' + code + '/uploads', fd, function (p) { f.progress = p; var bar = view.querySelectorAll('.c-progress span')[it.files.indexOf(f)]; if (bar) { bar.style.width = Math.round(p * 100) + '%'; } }).then(function (r) {
                var idx = it.files.indexOf(f);
                if (!r.status) { it.files.splice(idx, 1); PH.alert(file.name + ': ' + r.message); }
                else { r.data.notice = r.data.notice || null; it.files[idx] = r.data; PH.store.set('has_session', 1); }
                if (it.files.length !== 1) { it.page_selection = []; }
                save(); renderFiles();
            });
        });
    }
    view.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-remove]'))) {
            var f = state.cur.files.splice(+b.dataset.remove, 1)[0];
            if (f && f.token) { PH.api('DELETE', '/uploads/' + f.token); }
            state.cur.page_selection = []; delete state.cur.layout.crop; save(); renderFiles();
        } else if ((b = e.target.closest('[data-edit-img]'))) { openCropper(state.cur.files[+b.dataset.editImg]); }
        else if ((b = e.target.closest('[data-edit-pages]'))) { openPages(state.cur.files[+b.dataset.editPages]); }
    });
    view.addEventListener('input', function (e) {
        var m = e.target.closest('[data-manual]');
        if (m) { state.cur.files[+m.dataset.manual].manual_pages = Math.max(1, parseInt(m.value, 10) || 0); save(); }
    });

    // ---------------------------------------------------------------- image editor (Cropper.js)
    var cropper = null, cropModal = document.getElementById('imgEditor');
    function openCropper(f) {
        var img = document.getElementById('cropImg'), it = state.cur, m = bootstrap.Modal.getOrCreateInstance(cropModal);
        img.src = f.url;
        m.show();
        cropModal.addEventListener('shown.bs.modal', function init() {
            cropModal.removeEventListener('shown.bs.modal', init);
            if (cropper) { cropper.destroy(); }
            cropper = new Cropper(img, { viewMode: 1, autoCropArea: 1, background: false, responsive: true, checkOrientation: true, ready: function () {
                var l = it.layout;
                if (l.rotate) { cropper.rotateTo(l.rotate); }
                if (l.flip) { cropper.scaleX(-1); }
                if (l.crop) {
                    var cd = cropper.getCanvasData();
                    cropper.setData({ x: l.crop.x * cd.naturalWidth, y: l.crop.y * cd.naturalHeight, width: l.crop.w * cd.naturalWidth, height: l.crop.h * cd.naturalHeight });
                }
            } });
        });
    }
    cropModal.addEventListener('click', function (e) {
        var b = e.target.closest('[data-crop]'), a = e.target.closest('[data-aspect]');
        if (b && cropper) {
            var act = b.dataset.crop;
            if (act === 'rotate-left') { cropper.rotate(-90); } else if (act === 'rotate-right') { cropper.rotate(90); }
            else if (act === 'flip') { cropper.scaleX(cropper.getData().scaleX === -1 ? 1 : -1); } else if (act === 'zoom-in') { cropper.zoom(0.1); } else if (act === 'zoom-out') { cropper.zoom(-0.1); }
            else if (act === 'reset') { cropper.reset(); cropper.setAspectRatio(NaN); }
        }
        if (a && cropper) {
            cropModal.querySelectorAll('[data-aspect]').forEach(function (x) { x.classList.toggle('is-active', x === a); });
            var v = a.dataset.aspect;
            cropModal.querySelector('[data-custom-aspect]').hidden = v !== 'custom';
            cropper.setAspectRatio(v === 'free' || v === 'custom' ? NaN : (v === 'paper' ? paperAspect(state.cur) : parseFloat(v)));
        }
    });
    cropModal.addEventListener('input', function (e) {
        if (!e.target.matches('[data-cw],[data-ch]') || !cropper) { return; }
        var w = parseFloat(cropModal.querySelector('[data-cw]').value), h = parseFloat(cropModal.querySelector('[data-ch]').value);
        if (w > 0 && h > 0) { cropper.setAspectRatio(w / h); state.cur.layout.item_w_mm = w; state.cur.layout.item_h_mm = h; }
    });
    cropModal.querySelector('[data-crop-save]').addEventListener('click', function () {
        if (!cropper) { return; }
        var d = cropper.getData(true), cd = cropper.getCanvasData(), l = state.cur.layout;
        l.rotate = ((d.rotate % 360) + 360) % 360; l.flip = d.scaleX === -1;
        l.crop = { x: Math.max(0, d.x / cd.naturalWidth), y: Math.max(0, d.y / cd.naturalHeight), w: Math.min(1, d.width / cd.naturalWidth), h: Math.min(1, d.height / cd.naturalHeight) };
        save(); bootstrap.Modal.getInstance(cropModal).hide(); renderFiles(); PH.toast('Crop saved');
    });

    // ---------------------------------------------------------------- PDF page manager (pdf.js)
    var pageModal = document.getElementById('pageEditor'), pageList = [], pageGrid = pageModal.querySelector('[data-pages]'), pageCount = 0, thumbs = {};
    function fillThumb(card) {
        var src = thumbs[card.dataset.pg], holder = card.querySelector('.c-page-canvas');
        if (!src || !holder) { return; }
        var c2 = document.createElement('canvas'); c2.width = src.width; c2.height = src.height; c2.getContext('2d').drawImage(src, 0, 0);
        holder.innerHTML = ''; holder.appendChild(c2); applyRot(card);
    }
    function openPages(f) {
        var it = state.cur;
        pageCount = f.pages || 0;
        pageList = [];
        thumbs = {};
        var sel = it.page_selection.length ? it.page_selection : null;
        if (sel) { sel.forEach(function (p) { pageList.push({ page: p.page, rotate: p.rotate || 0, on: true }); }); }
        for (var i = 1; i <= pageCount; i++) { if (!sel || !sel.some(function (p) { return p.page === i; })) { pageList.push({ page: i, rotate: 0, on: !sel }); } }
        if (sel) { pageList.sort(function (a, b) { return (b.on - a.on); }); }
        bootstrap.Modal.getOrCreateInstance(pageModal).show();
        drawPages();
        withPdf(function (lib) {
            lib.getDocument(f.pdf_url).promise.then(function (pdf) {
                var queue = pageList.slice(0, 300).map(function (p) { return p.page; }), render = function () {
                    var n = queue.shift();
                    if (!n) { return; }
                    pdf.getPage(n).then(function (page) {
                        var vp = page.getViewport({ scale: 0.3 }), cv = document.createElement('canvas');
                        cv.width = vp.width; cv.height = vp.height;
                        page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () {
                            thumbs[n] = cv;
                            pageGrid.querySelectorAll('[data-pg="' + n + '"]').forEach(fillThumb);
                            render();
                        });
                    });
                };
                render(); render();
            });
        });
    }
    function applyRot(card) { var p = pageList[+card.dataset.idx], c = card.querySelector('canvas'); if (c && p) { c.style.transform = 'rotate(' + p.rotate + 'deg) scale(' + (p.rotate % 180 ? 0.75 : 1) + ')'; } }
    function drawPages() {
        pageGrid.innerHTML = pageList.map(function (p, i) {
            return '<div class="c-page ' + (p.on ? 'is-on' : 'is-off') + '" draggable="true" data-idx="' + i + '" data-pg="' + p.page + '"><span class="c-page-no">' + p.page + '</span><div class="c-page-canvas" style="min-height:90px;display:flex;align-items:center;justify-content:center"><span class="spinner-border spinner-border-sm text-secondary"></span></div>'
                + '<div class="c-page-tools"><button type="button" data-move="-1" aria-label="Move left">◀</button><button type="button" data-rot aria-label="Rotate"><i class="bi bi-arrow-clockwise"></i></button><button type="button" data-move="1" aria-label="Move right">▶</button></div></div>';
        }).join('');
        pageModal.querySelector('[data-pages-count]').textContent = pageList.filter(function (p) { return p.on; }).length + ' of ' + pageCount + ' pages selected';
        pageGrid.querySelectorAll('.c-page').forEach(fillThumb);
    }
    pageGrid.addEventListener('click', function (e) {
        var card = e.target.closest('.c-page');
        if (!card) { return; }
        var i = +card.dataset.idx, p = pageList[i], mv = e.target.closest('[data-move]');
        if (e.target.closest('[data-rot]')) { p.rotate = (p.rotate + 90) % 360; applyRot(card); return; }
        if (mv) { var j = i + (+mv.dataset.move); if (j >= 0 && j < pageList.length) { pageList.splice(j, 0, pageList.splice(i, 1)[0]); drawPages(); } return; }
        p.on = !p.on; card.classList.toggle('is-on', p.on); card.classList.toggle('is-off', !p.on);
        pageModal.querySelector('[data-pages-count]').textContent = pageList.filter(function (x) { return x.on; }).length + ' of ' + pageCount + ' pages selected';
    });
    var dragIdx = null;
    pageGrid.addEventListener('dragstart', function (e) { var c = e.target.closest('.c-page'); if (c) { dragIdx = +c.dataset.idx; c.classList.add('is-dragging'); } });
    pageGrid.addEventListener('dragover', function (e) { e.preventDefault(); });
    pageGrid.addEventListener('drop', function (e) {
        e.preventDefault();
        var c = e.target.closest('.c-page');
        if (c && dragIdx !== null) { var to = +c.dataset.idx; pageList.splice(to, 0, pageList.splice(dragIdx, 1)[0]); drawPages(); }
        dragIdx = null;
    });
    pageModal.querySelector('[data-pages-all]').addEventListener('click', function () { pageList.forEach(function (p) { p.on = true; }); drawPages(); });
    pageModal.querySelector('[data-pages-none]').addEventListener('click', function () { pageList.forEach(function (p) { p.on = false; }); drawPages(); });
    pageModal.querySelector('[data-range-apply]').addEventListener('click', function () {
        var want = [];
        pageModal.querySelector('[data-range]').value.split(',').forEach(function (part) {
            var m = part.trim().match(/^(\d+)\s*(?:-\s*(\d+))?$/);
            if (!m) { return; }
            var a = +m[1], b = m[2] ? +m[2] : a;
            for (var k = Math.min(a, b); k <= Math.max(a, b); k++) { if (k >= 1 && k <= pageCount && want.indexOf(k) < 0) { want.push(k); } }
        });
        if (!want.length) { PH.alert('Enter pages like 1-3, 5'); return; }
        pageList.sort(function (a, b) { var ia = want.indexOf(a.page), ib = want.indexOf(b.page); return (ia < 0 ? 1e6 + a.page : ia) - (ib < 0 ? 1e6 + b.page : ib); });
        pageList.forEach(function (p) { p.on = want.indexOf(p.page) >= 0; });
        drawPages();
    });
    pageModal.querySelector('[data-pages-save]').addEventListener('click', function () {
        var sel = pageList.filter(function (p) { return p.on; }).map(function (p) { return { page: p.page, rotate: p.rotate }; });
        if (!sel.length) { PH.alert('Select at least one page.'); return; }
        var unchanged = sel.length === pageCount && sel.every(function (p, i) { return p.page === i + 1 && !p.rotate; });
        state.cur.page_selection = unchanged ? [] : sel;
        save(); bootstrap.Modal.getInstance(pageModal).hide(); renderFiles(); PH.toast(sel.length + ' page' + (sel.length > 1 ? 's' : '') + ' selected');
    });

    // ---------------------------------------------------------------- step 3: options
    function pills(g, label, items, current, attr) {
        if (!items.length) { return ''; }
        return '<div class="c-opt-label">' + label + '</div><div class="c-pills">' + items.map(function (o) {
            return '<button type="button" class="c-pill' + (String(current) === String(o.v) ? ' is-active' : '') + '" data-set="' + attr + '" data-val="' + PH.esc(o.v) + '">' + PH.esc(o.l) + '</button>';
        }).join('') + '</div>';
    }
    function stepOptions() {
        var it = state.cur, c = cat(it.category_id), b = basis(it), images = it.files.filter(function (f) { return f.kind === 'image'; }).length;
        var h = '<div class="c-step-title">Printing options</div><div class="c-step-sub">Only options this shop offers for ' + PH.esc(c.name.toLowerCase()) + ' are shown.</div>';
        h += pills('paper_size', 'Paper size', opts(c, 'paper_size').map(function (o) { return { v: o.id, l: o.label }; }), it.paper_size_id, 'paper_size_id');
        var ps = optById(c, 'paper_size', it.paper_size_id);
        if (ps && ps.code === 'custom') {
            h += '<div class="row g-2 mt-1"><div class="col"><input class="form-control" type="number" min="20" max="1200" placeholder="Width mm" value="' + (it.custom_width_mm || '') + '" data-num="custom_width_mm"></div><div class="col"><input class="form-control" type="number" min="20" max="1200" placeholder="Height mm" value="' + (it.custom_height_mm || '') + '" data-num="custom_height_mm"></div></div>';
        }
        if (opts(c, 'color').length) { h += '<div class="c-opt-label">Colour</div><div class="c-seg">' + opts(c, 'color').map(function (o) { return '<button type="button" class="' + (it.color === o.code ? 'is-active' : '') + '" data-set="color" data-val="' + o.code + '">' + PH.esc(o.label) + '</button>'; }).join('') + '</div>'; }
        if (opts(c, 'sides').length > 1 && b === 'pages') { h += '<div class="c-opt-label">Sides</div><div class="c-seg">' + opts(c, 'sides').map(function (o) { return '<button type="button" class="' + (it.sides === o.code ? 'is-active' : '') + '" data-set="sides" data-val="' + o.code + '">' + PH.esc(o.label) + '</button>'; }).join('') + '</div>'; }
        h += pills('paper_type', 'Paper type', opts(c, 'paper_type').map(function (o) { return { v: o.id, l: o.label }; }), it.paper_type_id, 'paper_type_id');
        h += pills('gsm', 'Paper thickness (GSM)', opts(c, 'gsm').map(function (o) { return { v: o.id, l: o.label }; }), it.gsm_id, 'gsm_id');
        if (b === 'pages' && it.layout.fit !== 'grid' && it.layout.fit !== 'id_pair') {
            h += '<div class="c-opt-label">Orientation</div><div class="c-seg"><button type="button" class="' + (it.orientation === 'portrait' ? 'is-active' : '') + '" data-set="orientation" data-val="portrait"><i class="bi bi-file-earmark"></i> Portrait</button><button type="button" class="' + (it.orientation === 'landscape' ? 'is-active' : '') + '" data-set="orientation" data-val="landscape"><i class="bi bi-file-earmark" style="display:inline-block;transform:rotate(90deg)"></i> Landscape</button></div>';
        }
        if (opts(c, 'finishing').length) {
            h += '<div class="c-opt-label">Finishing <small class="c-muted fw-normal">(optional)</small></div><div class="c-pills">' + opts(c, 'finishing').map(function (o) { return '<button type="button" class="c-pill' + (it.finishing.indexOf(o.id) >= 0 ? ' is-active' : '') + '" data-fin="' + o.id + '">' + PH.esc(o.label) + '</button>'; }).join('') + '</div>';
        }
        if (it.files.length || it.cv_token) {
            var layouts = [['fit', 'Fit to page'], ['fill', 'Fill page'], ['actual', 'Actual size']];
            if (images) { layouts.push(['grid', 'Photo sheet']); if (images >= 2) { layouts.push(['id_pair', 'Card front + back']); } }
            h += '<div class="c-opt-label">Layout</div><div class="c-pills">' + layouts.map(function (l) { return '<button type="button" class="c-pill' + (it.layout.fit === l[0] ? ' is-active' : '') + '" data-layout="' + l[0] + '">' + l[1] + '</button>'; }).join('') + '</div>';
            if (it.layout.fit === 'fit' || it.layout.fit === 'fill' || it.layout.fit === 'actual') {
                h += '<div class="row g-3 mt-1"><div class="col-6"><label class="form-label small">Margins: <span data-lbl="margin_mm">' + (it.layout.margin_mm || 0) + '</span> mm</label><input type="range" class="form-range" min="0" max="25" step="1" value="' + (it.layout.margin_mm || 0) + '" data-lay="margin_mm"></div>'
                    + (it.layout.fit !== 'fill' ? '<div class="col-6"><label class="form-label small">Scale: <span data-lbl="scale">' + (it.layout.scale || 100) + '</span>%</label><input type="range" class="form-range" min="25" max="200" step="5" value="' + (it.layout.scale || 100) + '" data-lay="scale"></div>' : '') + '</div>';
            }
            if (it.layout.fit === 'grid') {
                h += '<div class="c-opt-label">Print sheet</div><div class="c-seg"><button type="button" class="' + (it.layout.sheet !== 'a4' ? 'is-active' : '') + '" data-sheet="4x6">4 × 6 photo paper</button><button type="button" class="' + (it.layout.sheet === 'a4' ? 'is-active' : '') + '" data-sheet="a4">A4</button></div>';
            }
        }
        h += '<div class="row g-3 mt-1">';
        if (b === 'items' || it.layout.fit === 'grid') { h += '<div class="col-auto"><div class="c-opt-label">' + (it.layout.fit === 'grid' ? 'Number of photos' : 'Quantity (pieces)') + '</div>' + stepper('quantity', it.quantity) + '</div>'; }
        if (b === 'pages' && it.layout.fit !== 'grid') { h += '<div class="col-auto"><div class="c-opt-label">Copies</div>' + stepper('copies', it.copies) + '</div><div class="col"><div class="c-opt-label">Pages</div><div class="fw-bold pt-2">' + pagesFor(it) + ' per copy</div></div>'; }
        h += '</div>';
        if (c.code !== 'custom') { h += '<div class="c-opt-label">Note for the shop <small class="c-muted fw-normal">(optional)</small></div><input class="form-control" maxlength="500" data-notes2 value="' + PH.esc(it.notes) + '" placeholder="e.g. staple at top-left">'; }
        h += '<div class="c-sticky-cta"><div class="c-cta-bar"><button class="c-btn c-btn-light" data-go="2" aria-label="Back"><i class="bi bi-arrow-left"></i></button><div class="flex-grow-1" data-price></div><button class="c-btn" data-go="4">Preview <i class="bi bi-arrow-right"></i></button></div></div>';
        view.innerHTML = h;
        priceBox([it], view.querySelector('[data-price]'));
    }
    function stepper(k, v) { return '<div class="c-stepper"><button type="button" data-step="' + k + '" data-d="-1">−</button><input type="number" min="1" max="100000" value="' + v + '" data-num="' + k + '" aria-label="' + k + '"><button type="button" data-step="' + k + '" data-d="1">+</button></div>'; }
    view.addEventListener('click', function (e) {
        var it = state.cur, b;
        if (!it || state.step !== 3) { return; }
        if ((b = e.target.closest('[data-set]'))) { var k = b.dataset.set, v = b.dataset.val; it[k] = /_id$/.test(k) ? +v : v; save(); stepOptions(); }
        else if ((b = e.target.closest('[data-fin]'))) { var id = +b.dataset.fin, i = it.finishing.indexOf(id); if (i >= 0) { it.finishing.splice(i, 1); } else { it.finishing.push(id); } save(); stepOptions(); }
        else if ((b = e.target.closest('[data-layout]'))) {
            it.layout.fit = b.dataset.layout;
            if (it.layout.fit === 'grid') { it.layout.sheet = it.layout.sheet || '4x6'; it.layout.margin_mm = 3; if (it.quantity < 2) { it.quantity = 8; } }
            save(); stepOptions();
        }
        else if ((b = e.target.closest('[data-sheet]'))) { it.layout.sheet = b.dataset.sheet; save(); stepOptions(); }
        else if ((b = e.target.closest('[data-step]'))) { var key = b.dataset.step; it[key] = Math.max(1, Math.min(100000, (it[key] || 1) + (+b.dataset.d))); save(); view.querySelector('[data-num="' + key + '"]').value = it[key]; priceBox([it], view.querySelector('[data-price]')); }
    });
    view.addEventListener('input', function (e) {
        var it = state.cur, t = e.target;
        if (!it || state.step !== 3) { return; }
        if (t.dataset.num) { it[t.dataset.num] = Math.max(t.dataset.num.indexOf('custom') === 0 ? 0 : 1, parseFloat(t.value) || 0); save(); priceBox([it], view.querySelector('[data-price]')); }
        if (t.dataset.lay) { it.layout[t.dataset.lay] = +t.value; view.querySelector('[data-lbl="' + t.dataset.lay + '"]').textContent = t.value; save(); }
        if (t.matches('[data-notes2]')) { it.notes = t.value; save(); }
    });

    // ---------------------------------------------------------------- step 4: preview
    function stepPreview() {
        var it = state.cur, canPreview = it.files.some(function (f) { return f.kind !== 'docx' || f.converted; });
        var h = '<div class="c-step-title">Preview</div><div class="c-step-sub">This is how your pages will be printed.</div>';
        h += canPreview ? '<div class="c-preview-frame" data-preview><div class="c-center c-muted py-5"><span class="spinner-border spinner-border-sm"></span> Preparing preview…</div></div>'
            : '<div class="c-notice">' + (it.cv_token ? 'Your CV will be printed exactly as shown in the CV maker.' : 'Word files are printed by the shop from your original document with the options you chose.') + '</div>';
        h += '<div class="c-card mt-3"><dl class="c-summary row mb-0">' + summary(it) + '</dl></div>';
        h += '<button class="c-btn c-btn-ghost c-btn-block mt-1" data-add-more><i class="bi bi-plus-lg"></i> Add another file to this order</button>';
        h += '<div class="c-sticky-cta"><div class="c-cta-bar"><button class="c-btn c-btn-light" data-go="3" aria-label="Back"><i class="bi bi-arrow-left"></i></button><div class="flex-grow-1" data-price></div><button class="c-btn" data-to-checkout>Checkout <i class="bi bi-arrow-right"></i></button></div></div>';
        view.innerHTML = h;
        priceBox([it], view.querySelector('[data-price]'));
        if (canPreview && !it.cv_token) {
            var p = itemPayload(it);
            PH.api('POST', '/shops/' + code + '/preview', p).then(function (r) {
                var box = view.querySelector('[data-preview]');
                if (!box) { return; }
                if (!r.status) { box.innerHTML = '<div class="c-notice">' + PH.esc(r.message) + '</div>'; return; }
                box.innerHTML = '';
                withPdf(function (lib) {
                    lib.getDocument(r.data.url).promise.then(function (pdf) {
                        var n = Math.min(pdf.numPages, 8);
                        (function next(i) {
                            if (i > n) { if (pdf.numPages > n) { box.insertAdjacentHTML('beforeend', '<p class="c-center small c-muted mb-0">+ ' + (pdf.numPages - n) + ' more page(s)</p>'); } return; }
                            pdf.getPage(i).then(function (page) {
                                var vp = page.getViewport({ scale: Math.min(1.2, (box.clientWidth - 30) / page.getViewport({ scale: 1 }).width) }), cv = document.createElement('canvas');
                                cv.width = vp.width; cv.height = vp.height; box.appendChild(cv);
                                page.render({ canvasContext: cv.getContext('2d'), viewport: vp }).promise.then(function () { next(i + 1); });
                            });
                        })(1);
                    });
                });
            });
        }
    }
    function summary(it) {
        var c = cat(it.category_id), lbl = function (g, id) { var o = optById(c, g, id); return o ? o.label : null; };
        var rows = [['Service', c.name + (it.subcategory_id ? ' · ' + c.subcategories.filter(function (s) { return s.id === it.subcategory_id; })[0].name : '')],
            ['Files', it.cv_token ? (it.cv_title || 'CV') : it.files.map(function (f) { return f.name; }).join(', ') || '—'],
            ['Paper', [lbl('paper_size', it.paper_size_id), it.custom_width_mm ? it.custom_width_mm + '×' + it.custom_height_mm + ' mm' : null, it.orientation].filter(Boolean).join(' · ')],
            ['Print', [it.color === 'color' ? 'Colour' : (it.color ? 'Black & White' : null), it.sides === 'double' ? 'Double-sided' : (it.sides ? 'Single-sided' : null)].filter(Boolean).join(' · ') || '—'],
            ['Paper type', [lbl('paper_type', it.paper_type_id), lbl('gsm', it.gsm_id)].filter(Boolean).join(' · ') || '—'],
            ['Quantity', basis(it) === 'items' || it.layout.fit === 'grid' ? it.quantity + ' pieces' : pagesFor(it) + ' pages × ' + it.copies + ' cop' + (it.copies > 1 ? 'ies' : 'y')],
            ['Finishing', it.finishing.map(function (id) { return lbl('finishing', id); }).join(', ') || 'None']];
        if (it.notes) { rows.push(['Note', it.notes]); }
        return rows.map(function (r) { return '<dt class="col-4">' + r[0] + '</dt><dd class="col-8">' + PH.esc(r[1]) + '</dd>'; }).join('');
    }
    view.addEventListener('click', function (e) {
        if (e.target.closest('[data-add-more]')) { pushCurrent(); state.cur = null; save(); setStep(1); }
        if (e.target.closest('[data-to-checkout]')) { pushCurrent(); save(); setStep(5); }
    });
    function pushCurrent() {
        if (!state.cur) { return; }
        if (state.cur._idx !== undefined && state.items[state.cur._idx]) { var i = state.cur._idx; delete state.cur._idx; state.items[i] = state.cur; }
        else { state.items.push(state.cur); }
        state.cur = null;
    }

    // ---------------------------------------------------------------- step 5: checkout
    function stepCheckout() {
        if (!state.items.length) { setStep(1); return; }
        var who = PH.store.get('customer', CFG.customer || {});
        var h = '<div class="c-step-title">Review &amp; send</div><div class="c-step-sub">Check everything before sending your order to ' + PH.esc(CFG.shop.name) + '.</div>';
        h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-files"></i> Your order</div><div class="c-items">';
        state.items.forEach(function (it, i) {
            var c = cat(it.category_id);
            h += '<div class="c-item"><i class="bi bi-' + PH.esc(c ? c.icon : 'file') + ' fs-4" style="color:var(--c-brand)"></i><div class="flex-grow-1 small"><strong class="d-block">' + PH.esc(it.cv_token ? (it.cv_title || 'CV') : (it.files.map(function (f) { return f.name; }).join(', ') || (c ? c.name : ''))) + '</strong>'
                + '<span class="c-muted">' + PH.esc(c ? c.name : '') + ' · ' + (basis(it) === 'items' || it.layout.fit === 'grid' ? it.quantity + ' pcs' : pagesFor(it) + ' p × ' + it.copies) + '</span></div>'
                + '<button class="c-icon-btn" data-edit-item="' + i + '" aria-label="Edit"><i class="bi bi-pencil"></i></button><button class="c-icon-btn" data-del-item="' + i + '" aria-label="Remove"><i class="bi bi-trash3"></i></button></div>';
        });
        h += '</div><button class="c-btn c-btn-sm c-btn-ghost mt-2" data-add-more2><i class="bi bi-plus-lg"></i> Add another file</button></div>';
        h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-person"></i> Your details</div>'
            + '<div class="mb-3"><label class="form-label" for="cn">Name</label><input class="form-control" id="cn" data-cust="name" value="' + PH.esc(who.name || '') + '" autocomplete="name" required></div>'
            + '<div class="mb-3"><label class="form-label" for="cp">Mobile number</label><input class="form-control" id="cp" data-cust="phone" value="' + PH.esc(who.phone || '') + '" inputmode="tel" autocomplete="tel" placeholder="03xx xxxxxxx" required><small class="c-muted">The shop uses it to contact you about your order.</small></div>'
            + '<div><label class="form-label" for="ci">Instructions for the shop (optional)</label><textarea class="form-control" id="ci" rows="2" data-instr maxlength="2000" placeholder="e.g. Need it by 5 pm">' + PH.esc(state.instructions || '') + '</textarea></div></div>';
        var pay = CFG.shop.payment_mode === 'pay_at_shop' ? 'Pay at the shop when you collect your prints.' : (CFG.shop.payment_mode === 'advance_required' ? 'Advance payment is required before printing. You will see the payment details after the shop confirms the price.' : 'You can pay in advance or at the shop.');
        h += '<div class="c-card"><div class="c-card-title"><i class="bi bi-wallet2"></i> Payment</div><p class="small mb-0">' + PH.esc(pay) + '</p></div>';
        h += '<p class="c-privacy"><i class="bi bi-shield-lock"></i> ' + PH.esc(CFG.privacy) + '</p>';
        h += '<div class="c-sticky-cta"><div class="c-cta-bar"><div class="flex-grow-1" data-price></div><button class="c-btn" data-submit><i class="bi bi-send"></i> Send order</button></div></div>';
        view.innerHTML = h;
        priceBox(state.items, view.querySelector('[data-price]'));
    }
    view.addEventListener('click', function (e) {
        var b;
        if (state.step !== 5) { return; }
        if ((b = e.target.closest('[data-del-item]'))) {
            Swal.fire({ text: 'Remove this item?', icon: 'question', showCancelButton: true }).then(function (r) { if (r.isConfirmed) { state.items.splice(+b.dataset.delItem, 1); save(); stepCheckout(); } });
        } else if ((b = e.target.closest('[data-edit-item]'))) { var i = +b.dataset.editItem; state.cur = state.items[i]; state.cur._idx = i; save(); setStep(3); }
        else if (e.target.closest('[data-add-more2]')) { state.cur = null; save(); setStep(1); }
        else if ((b = e.target.closest('[data-submit]'))) { submit(b); }
    });
    view.addEventListener('input', function (e) {
        if (state.step !== 5) { return; }
        if (e.target.dataset.cust) { var who = PH.store.get('customer', {}); who[e.target.dataset.cust] = e.target.value; PH.store.set('customer', who); }
        if (e.target.matches('[data-instr]')) { state.instructions = e.target.value; save(); }
    });
    function submit(btn) {
        var who = PH.store.get('customer', CFG.customer || {});
        if (!who.name || (who.name || '').trim().length < 2) { PH.alert('Please enter your name.'); return; }
        if ((who.phone || '').replace(/\D/g, '').length < 10) { PH.alert('Please enter a valid mobile number.'); return; }
        btn.classList.add('is-loading');
        PH.api('POST', '/shops/' + code + '/orders', { customer_name: who.name, customer_phone: who.phone, instructions: state.instructions || '', items: state.items.map(itemPayload) }).then(function (r) {
            btn.classList.remove('is-loading');
            if (!r.status) { PH.alert((r.data && r.data.errors ? r.data.errors.join('\n') : r.message)); return; }
            PH.store.set(KEY, null);
            Swal.fire({ icon: 'success', title: 'Order sent!', html: 'Order number <strong>' + PH.esc(r.data.order_number) + '</strong>.<br>' + PH.esc(r.data.pricing_mode === 'quote' ? 'The shop will send you the price shortly.' : 'The shop will confirm it shortly.'),
                confirmButtonText: 'Track my order', allowOutsideClick: false }).then(function () { location.href = r.data.tracking_url; });
        });
    }

    // ---------------------------------------------------------------- start
    if (CFG.cv) {
        var pc = CFG.categories.filter(function (c) { return c.code === 'personal'; })[0] || CFG.categories[0];
        state = { items: state.items || [], cur: newItem(pc.id), step: 3 };
        var cvSub = pc.subcategories.filter(function (s) { return s.code === 'cv'; })[0];
        if (cvSub) { state.cur.subcategory_id = cvSub.id; }
        state.cur.cv_token = CFG.cv.token; state.cur.cv_title = CFG.cv.title; state.cur.cv_pages = CFG.cv.pages;
        var a4 = opts(pc, 'paper_size').filter(function (o) { return o.code === 'a4'; })[0]; if (a4) { state.cur.paper_size_id = a4.id; }
        save();
        history.replaceState(null, '', location.pathname);
    } else if (CFG.preselect && cat(CFG.preselect) && (!state.cur || state.cur.category_id !== CFG.preselect)) {
        state.cur = newItem(CFG.preselect); state.step = 1; save();
        history.replaceState(null, '', location.pathname);
    }
    if (!state.cur && state.step !== 5) { state.step = 1; }
    if (state.cur && !cat(state.cur.category_id)) { state.cur = null; state.step = 1; }
    setStep(state.step || 1);
})();
