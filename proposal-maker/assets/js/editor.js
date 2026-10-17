/* =====================================================================
   Proposal Builder — visual editor
   State lives in S (proposal + pages + blocks). The server renders the
   HTML for each page (same renderer as preview/PDF); this script handles
   interaction, forms, drag & drop, pagination measurement and saving.
   ===================================================================== */
(function ($) {
    'use strict';

    var B = window.PMED, S = B.state, BL = B.blocks;
    var PID = S.proposal.id;
    var sel = null;            // selected block uid
    var curPage = null;        // current page uid
    var dirty = false, saving = false, saveTimer = null, renderTimers = {}, allTimer = null;
    // PHP sends an empty list as [] — in JavaScript we use an object {}
    if (!S.proposal.settings.breaks || Array.isArray(S.proposal.settings.breaks)) S.proposal.settings.breaks = {};
    var zoom = 1, sheetStats = {}, lastBreaks = JSON.stringify(S.proposal.settings.breaks);
    var renderSeq = 0;
    var fontsReady = false;    // page breaks are only trusted once the fonts are loaded

    /* ------------------------------------------------------------------
     * Helpers
     * ---------------------------------------------------------------- */
    function uid(p) { return (p || 'b') + Math.random().toString(36).slice(2, 12); }
    function clone(o) { return JSON.parse(JSON.stringify(o)); }
    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function pageByUid(u) { for (var i = 0; i < S.pages.length; i++) if (S.pages[i].uid === u) return S.pages[i]; return null; }
    function findBlock(bu) {
        for (var i = 0; i < S.pages.length; i++) {
            var bl = S.pages[i].blocks;
            for (var j = 0; j < bl.length; j++) if (bl[j].uid === bu) return { page: S.pages[i], block: bl[j], index: j };
        }
        return null;
    }
    function defaults(type) { return clone((BL[type] && BL[type].defaults) || {}); }
    function newBlock(type) { return { uid: uid('b'), type: type, data: defaults(type), hidden: false }; }
    function contentPages() { return S.pages.filter(function (p) { return p.page_type !== 'cover'; }); }

    function api(action, data) {
        return $.ajax({ url: PM.url('ajax/editor.php'), method: 'POST', dataType: 'json', data: $.extend({ action: action, id: PID }, data || {}) });
    }
    function stateJSON() { return JSON.stringify({ proposal: S.proposal, pages: S.pages }); }

    /* ------------------------------------------------------------------
     * Dirty / save
     * ---------------------------------------------------------------- */
    function setSaveState(mode, txt) {
        var $s = $('#saveState').removeClass('dirty saving');
        if (mode === 'dirty') $s.addClass('dirty').html('<i class="bi bi-circle-fill" style="font-size:7px"></i> Unsaved changes');
        else if (mode === 'saving') $s.addClass('saving').html('<span class="spinner-border spinner-border-sm" style="width:10px;height:10px"></span> Saving…');
        else if (mode === 'error') $s.addClass('dirty').html('<i class="bi bi-exclamation-triangle"></i> ' + esc(txt || 'Save failed'));
        else $s.html('<i class="bi bi-cloud-check"></i> Saved ' + (txt || ''));
    }
    function markDirty() {
        dirty = true;
        setSaveState('dirty');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 2500);
    }
    function save() {
        clearTimeout(saveTimer);
        if (saving) { saveTimer = setTimeout(save, 800); return $.Deferred().resolve().promise(); }
        saving = true;
        setSaveState('saving');
        var wasDirty = dirty;
        dirty = false;
        return api('save', { state: stateJSON() }).done(function (r) {
            if (r.totals) S.proposal.total = parseFloat(r.totals.total);
            setSaveState(dirty ? 'dirty' : 'saved', r.saved_at ? 'at ' + r.saved_at : '');
        }).fail(function (x) {
            dirty = dirty || wasDirty;
            setSaveState('error', (x.responseJSON && x.responseJSON.message) || 'Save failed');
            if (x.status === 422) PM.error(x);
        }).always(function () { saving = false; });
    }
    function ensureSaved() {
        if (!dirty && !saving) return $.Deferred().resolve().promise();
        return save();
    }
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    /* ------------------------------------------------------------------
     * Rendering
     * ---------------------------------------------------------------- */
    function applyDoc(r) {
        $('#docRoot').attr('class', r.docClass).attr('style', r.docStyle);
    }
    function pageWrapper(pg, idx) {
        var isCover = pg.page_type === 'cover';
        var hidden = pg.settings && pg.settings.hidden;
        return $('<div class="ed-page" data-page="' + pg.uid + '">' +
            '<div class="ed-page-bar"><span class="pn">' + String(idx + 1).padStart(2, '0') + '</span><span class="pt">' + esc(pg.title) + '</span>' +
            '<span class="ps"></span>' + (hidden ? '<span class="badge bg-secondary">Hidden</span>' : '') +
            '<div class="pa">' +
            (isCover ? '' : '<button data-pa="add" title="Add text block"><i class="bi bi-plus-lg"></i></button>') +
            '<button data-pa="up" title="Move page up"><i class="bi bi-chevron-up"></i></button>' +
            '<button data-pa="down" title="Move page down"><i class="bi bi-chevron-down"></i></button>' +
            '<button data-pa="settings" title="Page settings"><i class="bi bi-gear"></i></button>' +
            (isCover ? '' : '<button data-pa="dup" title="Duplicate page"><i class="bi bi-copy"></i></button><button data-pa="del" title="Delete page"><i class="bi bi-trash3"></i></button>') +
            '</div></div><div class="ed-sheet-holder"></div></div>').toggleClass('page-hidden', !!hidden);
    }

    function renderAll() {
        clearTimeout(allTimer);
        var seq = ++renderSeq;
        return api('render', { state: stateJSON() }).done(function (r) {
            if (seq !== renderSeq) return;
            applyDoc(r);
            var $root = $('#docRoot').empty();
            S.pages.forEach(function (pg, i) {
                var $w = pageWrapper(pg, i);
                $w.find('.ed-sheet-holder').html(r.pages[pg.uid] || '');
                $root.append($w);
            });
            afterRender();
        }).fail(function (x) { PM.error(x, 'Could not render the proposal.'); });
    }
    function renderAllSoon(ms) { clearTimeout(allTimer); allTimer = setTimeout(renderAll, ms || 450); }

    function renderPage(pu) {
        var seq = renderSeq;
        return api('render', { state: stateJSON(), only: JSON.stringify([pu]) }).done(function (r) {
            if (seq !== renderSeq) return; // a full render superseded us
            applyDoc(r);
            var $w = $('.ed-page[data-page="' + pu + '"]');
            var pg = pageByUid(pu);
            if (!$w.length || !pg) return renderAll();
            var $n = pageWrapper(pg, S.pages.indexOf(pg));
            $n.find('.ed-sheet-holder').html(r.pages[pu] || '');
            $w.replaceWith($n);
            afterRender();
        });
    }
    function renderPageSoon(pu, ms) {
        clearTimeout(renderTimers[pu]);
        renderTimers[pu] = setTimeout(function () { renderPage(pu); }, ms == null ? 300 : ms);
    }
    function renderBlockSoon(bu) { var f = findBlock(bu); if (f) renderPageSoon(f.page.uid); }

    function afterRender() {
        decorate();
        initSortables();
        $('.ed-page').toggleClass('active', false).filter('[data-page="' + curPage + '"]').addClass('active');
        if (sel) $('[data-uid="' + sel + '"]').addClass('ed-selected');
        paginateSoon();
        $('#docRoot img').each(function () { if (!this.complete) $(this).one('load error', paginateSoon); });
    }

    function decorate() {
        $('#docRoot .pm-body > .pm-block').each(function () {
            var $b = $(this), type = $b.data('type');
            if ($b.children('.blk-tools').length) return;
            var f = findBlock($b.data('uid'));
            var label = (BL[type] && BL[type].label) || type;
            $b.append('<div class="blk-tools"><span class="lbl">' + esc(label) + '</span>' +
                '<button class="drag" title="Drag to move"><i class="bi bi-arrows-move"></i></button>' +
                '<button data-bt="up" title="Move up"><i class="bi bi-arrow-up"></i></button>' +
                '<button data-bt="down" title="Move down"><i class="bi bi-arrow-down"></i></button>' +
                '<button data-bt="dup" title="Duplicate"><i class="bi bi-copy"></i></button>' +
                '<button data-bt="hide" title="' + (f && f.block.hidden ? 'Show' : 'Hide') + '"><i class="bi ' + (f && f.block.hidden ? 'bi-eye' : 'bi-eye-slash') + '"></i></button>' +
                '<button data-bt="lib" title="Save to library"><i class="bi bi-bookmark-plus"></i></button>' +
                '<button data-bt="del" title="Delete"><i class="bi bi-trash3"></i></button></div>');
        });
        $('#docRoot .pm-inner .pm-body').each(function () { $(this).toggleClass('is-empty', !$(this).children('.pm-block').length); });
    }

    /* ------------------------------------------------------------------
     * Pagination — measure blocks and decide where A4 sheets break.
     * The result (settings.breaks) is used by the server for the
     * preview / public page / PDF so they paginate identically.
     * ---------------------------------------------------------------- */
    var pagTimer = null;
    function paginateSoon() { clearTimeout(pagTimer); pagTimer = setTimeout(paginateAll, 120); }

    function relTop(el, body) {
        var t = 0;
        while (el && el !== body) { t += el.offsetTop; el = el.offsetParent; }
        return t;
    }

    function paginateAll() {
        var breaks = {};
        sheetStats = {};
        $('.ed-break, .ed-overflow-note').remove();
        $('.ed-overflow').removeClass('ed-overflow');
        S.pages.forEach(function (pg) {
            if (pg.page_type === 'cover') { sheetStats[pg.uid] = 1; return; }
            var body = $('.ed-page[data-page="' + pg.uid + '"] .pm-body')[0];
            if (!body) return;
            var res = paginateBody(pg, body);
            if (res.breaks.length) breaks[pg.uid] = res.breaks;
            sheetStats[pg.uid] = res.sheets;
        });
        S.proposal.settings.breaks = breaks;
        var js = JSON.stringify(breaks);
        // only save new breaks when fonts are loaded (otherwise text sizes are not final yet)
        if (fontsReady && js !== lastBreaks) { lastBreaks = js; markDirty(); }
        // sheet counters in page bars / list
        var n = 0;
        S.pages.forEach(function (pg) {
            if (pg.settings && pg.settings.hidden) return;
            var c = sheetStats[pg.uid] || 1;
            $('.ed-page[data-page="' + pg.uid + '"] .ps').text(c > 1 ? '· ' + c + ' A4 pages (sheets ' + (n + 1) + '–' + (n + c) + ')' : '· sheet ' + (n + 1));
            n += c;
        });
        renderPageList();
    }

    function paginateBody(pg, body) {
        var cs = getComputedStyle(body);
        var avail = (parseFloat(getComputedStyle(document.getElementById('docRoot')).getPropertyValue('--body-h')) || 900) - 2;
        var base = parseFloat(cs.paddingTop) || 0;
        var breaks = [], sheets = 1, shift = 0, sheetTop = null, forceNext = false;
        var els = $(body).children('.pm-block').toArray();

        function addMarker(topPx, forced, label) {
            $('<div class="ed-break' + (forced ? ' forced' : '') + '"><span>' + label + '</span></div>').css('top', topPx - 8).appendTo(body);
        }
        els.forEach(function (el) {
            var bu = el.getAttribute('data-uid'), f = findBlock(bu);
            if (!f) return;
            var h = el.offsetHeight, mb = parseFloat(getComputedStyle(el).marginBottom) || 0;
            if (f.block.hidden || f.block.type === 'page_break') {
                if (f.block.type === 'page_break' && !f.block.hidden && sheetTop !== null) forceNext = true;
                shift += h + mb;
                return;
            }
            var top = el.offsetTop - base - shift;
            var bottom = top + h;
            if (sheetTop === null) sheetTop = top;
            if (forceNext) {
                forceNext = false;
                sheetTop = top; sheets++;
                addMarker(el.offsetTop, true, 'Page break');
            }
            if (bottom - sheetTop <= avail) return;

            var flow = el.querySelector('.pm-flow');
            var kids = flow ? Array.prototype.filter.call(flow.children, function (k) { return k.nodeType === 1; }) : [];
            if (kids.length > 1) {
                for (var k = 0; k < kids.length; k++) {
                    var kt = relTop(kids[k], body) - base - shift;
                    var kb = kt + kids[k].offsetHeight;
                    if (kb - sheetTop <= avail) continue;
                    if (k === 0) {
                        if (top > sheetTop + 1) { breaks.push({ b: bu, c: 0 }); sheetTop = top; sheets++; addMarker(el.offsetTop, false, 'Continues on next page'); k--; continue; }
                        continue; // oversize first item, let it overflow
                    }
                    if (kt <= sheetTop + 1) continue; // single item taller than a page
                    breaks.push({ b: bu, c: k });
                    sheetTop = kt; sheets++;
                    addMarker(kt + base + shift, false, 'Continues on next page');
                }
                var lastBottom = relTop(kids[kids.length - 1], body) - base - shift + kids[kids.length - 1].offsetHeight;
                if (bottom - sheetTop > avail && bottom - lastBottom > 0) {
                    // trailing chrome (totals) after the last flow item
                    if (bottom - sheetTop > avail + 4) markOverflow(el);
                }
            } else {
                if (top > sheetTop + 1) {
                    breaks.push({ b: bu, c: 0 });
                    sheetTop = top; sheets++;
                    addMarker(el.offsetTop, false, 'Continues on next page');
                }
                if (bottom - sheetTop > avail) markOverflow(el);
            }
        });
        return { breaks: breaks, sheets: sheets };
    }
    function markOverflow(el) {
        $(el).addClass('ed-overflow');
        $('<div class="ed-overflow-note">Taller than one A4 page — content will be cut. Split it or reduce its size.</div>').css('top', el.offsetTop + el.offsetHeight - 22).appendTo(el.parentNode);
    }

    /* ------------------------------------------------------------------
     * Drag & drop (blocks + widgets)
     * ---------------------------------------------------------------- */
    var bodySortables = [];
    function initSortables() {
        bodySortables.forEach(function (s) { try { s.destroy(); } catch (e) { } });
        bodySortables = [];
        $('#docRoot .pm-inner .pm-body').each(function () {
            var body = this;
            bodySortables.push(new Sortable(body, {
                group: { name: 'blocks', pull: true, put: true },
                draggable: '.pm-block',
                filter: '.blk-tools button:not(.drag)',
                preventOnFilter: false,
                animation: 160,
                ghostClass: 'sortable-ghost',
                scroll: document.getElementById('canvas'),
                scrollSensitivity: 90,
                onAdd: onDrop,
                onUpdate: onDrop
            }));
        });
    }
    function onDrop(evt) {
        var item = evt.item, $to = $(evt.to), toPage = $to.closest('.ed-page').data('page');
        var idx = $(item).prevAll('.pm-block').length;
        var pg = pageByUid(toPage);
        if (!pg) return;
        if ($(item).hasClass('wg-item')) {
            var b = newBlock($(item).data('type'));
            $(item).remove();
            pg.blocks.splice(idx, 0, b);
            sel = b.uid; curPage = toPage;
            renderPage(toPage).done(function () { buildBlockForm(); });
            markDirty();
            return;
        }
        var bu = $(item).data('uid'), f = findBlock(bu);
        if (!f) return;
        var fromPage = f.page.uid;
        f.page.blocks.splice(f.index, 1);
        pg.blocks.splice(idx, 0, f.block);
        markDirty();
        if (fromPage !== toPage) { renderPage(fromPage); renderPage(toPage); }
        else paginateSoon();
    }
    document.querySelectorAll('#tWidgets .wg-grid').forEach(function (grid) {
        new Sortable(grid, {
            group: { name: 'blocks', pull: 'clone', put: false },
            draggable: '.wg-item', sort: false, animation: 150
        });
    });

    /* ------------------------------------------------------------------
     * Block actions
     * ---------------------------------------------------------------- */
    function insertBlock(b, pageUid, afterUid) {
        var pg = pageByUid(pageUid);
        if (!pg || pg.page_type === 'cover') {
            pg = contentPages().slice(-1)[0];
            if (!pg) { pg = addPage('New Page', true); }
        }
        var idx = pg.blocks.length;
        if (afterUid) {
            for (var i = 0; i < pg.blocks.length; i++) if (pg.blocks[i].uid === afterUid) idx = i + 1;
        }
        pg.blocks.splice(idx, 0, b);
        return pg;
    }
    function addWidget(type) {
        var b = newBlock(type);
        var target = sel ? findBlock(sel) : null;
        var pg = insertBlock(b, target ? target.page.uid : curPage, target ? target.block.uid : null);
        sel = b.uid; curPage = pg.uid;
        markDirty();
        renderPage(pg.uid).done(function () { buildBlockForm(); scrollToBlock(b.uid); });
    }
    $(document).on('click', '#tWidgets .wg-item', function () { addWidget($(this).data('type')); });

    $(document).on('click', '.blk-tools button[data-bt]', function (e) {
        e.stopPropagation();
        var bu = $(this).closest('.pm-block').data('uid'), f = findBlock(bu), act = $(this).data('bt');
        if (!f) return;
        var pg = f.page;
        if (act === 'up' || act === 'down') {
            var ni = f.index + (act === 'up' ? -1 : 1);
            if (ni < 0 || ni >= pg.blocks.length) {
                // move across pages
                var pi = S.pages.indexOf(pg) + (act === 'up' ? -1 : 1);
                var other = S.pages[pi];
                if (!other || other.page_type === 'cover') return;
                pg.blocks.splice(f.index, 1);
                if (act === 'up') other.blocks.push(f.block); else other.blocks.unshift(f.block);
                renderPage(pg.uid); renderPage(other.uid);
            } else {
                pg.blocks.splice(f.index, 1);
                pg.blocks.splice(ni, 0, f.block);
                renderPage(pg.uid);
            }
        } else if (act === 'dup') {
            var copy = clone(f.block); copy.uid = uid('b');
            pg.blocks.splice(f.index + 1, 0, copy);
            sel = copy.uid;
            renderPage(pg.uid).done(buildBlockForm);
        } else if (act === 'hide') {
            f.block.hidden = !f.block.hidden;
            renderPage(pg.uid);
            PM.toast(f.block.hidden ? 'Block hidden from preview & PDF' : 'Block visible again', 'info');
        } else if (act === 'lib') {
            saveToLibrary([f.block], (BL[f.block.type] || {}).label);
            return;
        } else if (act === 'del') {
            deleteBlock(bu);
            return;
        }
        markDirty();
    });

    function deleteBlock(bu) {
        var f = findBlock(bu);
        if (!f) return;
        f.page.blocks.splice(f.index, 1);
        if (sel === bu) { sel = null; buildBlockForm(); }
        markDirty();
        renderPage(f.page.uid);
        PM.toast('Block deleted', 'info');
    }

    function scrollToBlock(bu) {
        var $b = $('[data-uid="' + bu + '"]');
        if (!$b.length) return;
        var c = document.getElementById('canvas');
        var top = $b[0].getBoundingClientRect().top - c.getBoundingClientRect().top + c.scrollTop - 120;
        $(c).animate({ scrollTop: top }, 250);
    }
    function scrollToPage(pu) {
        var $p = $('.ed-page[data-page="' + pu + '"]');
        if (!$p.length) return;
        var c = document.getElementById('canvas');
        $(c).animate({ scrollTop: $p[0].getBoundingClientRect().top - c.getBoundingClientRect().top + c.scrollTop - 20 }, 250);
    }

    /* selection */
    $(document).on('click', '#docRoot .pm-block', function (e) {
        if ($(e.target).closest('a').length) e.preventDefault();
        e.stopPropagation();
        select($(this).data('uid'));
    });
    $(document).on('click', '#docRoot .pm-cover', function (e) {
        e.preventDefault(); e.stopPropagation();
        var pu = $(this).closest('.ed-page').data('page'), pg = pageByUid(pu);
        var cov = pg && pg.blocks.filter(function (b) { return b.type === 'cover'; })[0];
        if (cov) select(cov.uid);
    });
    $(document).on('click', '#docRoot .ed-page', function (e) {
        var pu = $(this).data('page');
        if (pu !== curPage) { curPage = pu; $('.ed-page').removeClass('active'); $(this).addClass('active'); renderPageList(); }
    });
    function select(bu) {
        sel = bu;
        var f = findBlock(bu);
        if (f) curPage = f.page.uid;
        $('#docRoot .ed-selected').removeClass('ed-selected');
        $('[data-uid="' + bu + '"]').addClass('ed-selected');
        $('.ed-page').removeClass('active').filter('[data-page="' + curPage + '"]').addClass('active');
        bootstrap.Tab.getOrCreateInstance(document.getElementById('blockTab')).show();
        buildBlockForm();
        renderPageList();
    }

    $(document).on('keydown', function (e) {
        var inField = $(e.target).is('input, textarea, select, [contenteditable], .note-editable') || $(e.target).closest('.note-editor, .swal2-container').length;
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); save().done(function () { PM.toast('Saved'); }); }
        if (!inField && sel && (e.key === 'Delete' || e.key === 'Backspace')) {
            var f = findBlock(sel);
            if (f && f.block.type !== 'cover') { e.preventDefault(); deleteBlock(sel); }
        }
        if (!inField && e.key === 'Escape') { sel = null; $('#docRoot .ed-selected').removeClass('ed-selected'); buildBlockForm(); }
    });

    /* ------------------------------------------------------------------
     * Form builder (right panel)
     * ---------------------------------------------------------------- */
    function destroyEditors($scope) {
        $scope.find('.fb-rich').each(function () { try { $(this).summernote('destroy'); } catch (e) { } });
    }

    function field(f, get, set, onChange) {
        var $w = $('<div class="fb-field"></div>');
        var lab = f.l ? '<label>' + esc(f.l) + '</label>' : '';
        var val = get();
        var changed = function (v, now) { set(v); onChange(now); };
        switch (f.t) {
            case 'text':
                $w.append(lab).append($('<input class="form-control">').val(val == null ? '' : val).on('input', function () { changed(this.value); }));
                break;
            case 'textarea':
            case 'code':
                var $ta = $('<textarea class="form-control" rows="' + (f.t === 'code' ? 8 : 3) + '"></textarea>').val(val == null ? '' : val).on('input', function () { changed(this.value); });
                if (f.t === 'code') $ta.css({ fontFamily: 'monospace', fontSize: '12px' });
                $w.append(lab).append($ta);
                break;
            case 'number':
                $w.append(lab).append($('<input type="number" class="form-control">').attr({ min: f.min, max: f.max, step: f.step || 1 }).val(val).on('input', function () { changed(this.value === '' ? 0 : parseFloat(this.value)); }));
                break;
            case 'range':
                var $r = $('<input type="range" class="form-range">').attr({ min: f.min, max: f.max, step: f.step || 1 }).val(val);
                var $n = $('<input type="number" class="form-control form-control-sm rv">').attr({ min: f.min, max: f.max }).val(val);
                $r.on('input', function () { $n.val(this.value); changed(parseFloat(this.value)); });
                $n.on('input', function () { $r.val(this.value); changed(parseFloat(this.value) || 0); });
                $w.append(lab).append($('<div class="range-row"></div>').append($r, $n));
                break;
            case 'select':
                var $s = $('<select class="form-select"></select>');
                $.each(f.o, function (k, l) { $s.append($('<option>').val(k).text(l)); });
                $s.val(val == null ? '' : String(val)).on('change', function () { changed(this.value, true); });
                $w.append(lab).append($s);
                break;
            case 'toggle':
                $w = $('<div class="toggle-field"><label>' + esc(f.l) + '</label><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox"></div></div>');
                $w.find('input').prop('checked', !!val).on('change', function () { changed(this.checked, true); });
                break;
            case 'color':
                var $c = $('<div class="color-mini"><input type="color"><input type="text" class="form-control form-control-sm" placeholder="Default"><button class="btn btn-sm btn-light border" title="Reset"><i class="bi bi-x-lg"></i></button></div>');
                $c.find('[type=color]').val(/^#[0-9a-f]{6}$/i.test(val) ? val : '#ffffff').on('input', function () { $c.find('[type=text]').val(this.value); changed(this.value); });
                $c.find('[type=text]').val(val || '').on('input', function () { var v = this.value.trim(); if (v === '' || /^#[0-9a-f]{6}$/i.test(v)) { if (v) $c.find('[type=color]').val(v); changed(v); } });
                $c.find('button').on('click', function (e) { e.preventDefault(); $c.find('[type=text]').val(''); changed('', true); });
                $w.append(lab).append($c);
                break;
            case 'image':
                $w.addClass('img-field').append(lab);
                var $prev = $('<div class="prev"></div>');
                var $url = $('<input class="form-control form-control-sm mb-1" placeholder="Image URL or upload">').val(val || '');
                var show = function (v) { $prev.html(v ? '<img src="' + esc(/^(https?:|data:)/.test(v) ? v : PM.url(v)) + '">' : '<span class="small text-muted">No image</span>'); };
                show(val);
                $url.on('change', function () { show(this.value); changed(this.value.trim(), true); });
                var $btns = $('<div class="btns"><button class="btn btn-sm btn-brand flex-fill"><i class="bi bi-upload"></i> Upload</button><button class="btn btn-sm btn-light border flex-fill"><i class="bi bi-images"></i> Library</button><button class="btn btn-sm btn-light border" title="Remove"><i class="bi bi-trash3"></i></button></div>');
                $btns.find('button').eq(0).on('click', function (e) { e.preventDefault(); pickFile(function (path) { $url.val(path); show(path); changed(path, true); }); });
                $btns.find('button').eq(1).on('click', function (e) { e.preventDefault(); openMedia(function (path) { $url.val(path); show(path); changed(path, true); }); });
                $btns.find('button').eq(2).on('click', function (e) { e.preventDefault(); $url.val(''); show(''); changed('', true); });
                $w.append($prev, $url, $btns);
                break;
            case 'icon':
                var $ib = $('<button class="icon-btn-field"><i class="bi bi-' + esc(val || 'star') + '"></i><span>' + esc(val || 'Choose icon') + '</span></button>');
                $ib.on('click', function (e) { e.preventDefault(); openIcons(function (name) { $ib.find('i').attr('class', 'bi bi-' + name); $ib.find('span').text(name); changed(name, true); }); });
                $w.append(lab).append($ib);
                break;
            case 'rich':
                var $ed = $('<div class="fb-rich"></div>').html(val || '');
                $w.append(lab).append($ed);
                setTimeout(function () {
                    $ed.summernote({
                        height: 220, dialogsInBody: true, disableDragAndDrop: true,
                        toolbar: [
                            ['style', ['style']], ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                            ['fontsize', ['fontsize']], ['color', ['forecolor', 'backcolor']],
                            ['para', ['ul', 'ol', 'paragraph', 'height']], ['insert', ['link', 'table', 'hr']], ['view', ['codeview']]
                        ],
                        fontSizes: ['10', '11', '12', '13', '14', '16', '18', '20', '24', '28', '32'],
                        lineHeights: ['1.0', '1.2', '1.4', '1.6', '1.8', '2.0', '2.4'],
                        styleTags: ['p', 'h2', 'h3', 'h4', 'blockquote'],
                        callbacks: { onChange: function (html) { changed(html); } }
                    });
                }, 0);
                break;
            case 'repeater':
                $w.append(lab).append(repeater(f, get, set, onChange));
                break;
        }
        return $w;
    }

    function repeater(f, get, set, onChange) {
        var $box = $('<div class="rep"></div>');
        var items = get();
        if (!Array.isArray(items)) { items = []; set(items); }
        var $list = $('<div class="rep-list"></div>');
        function itemTitle(it, i) { var t = it[f.title] || ''; t = String(t).replace(/<[^>]+>/g, '').split('\n')[0]; return (i + 1) + '. ' + (t || 'Item'); }
        function build(openIdx) {
            destroyEditors($list);
            $list.empty();
            items.forEach(function (it, i) {
                var $it = $('<div class="rep-item' + (i === openIdx ? ' open' : '') + '"><div class="rh"><i class="bi bi-grip-vertical h"></i><span class="tt"></span>' +
                    '<button title="Duplicate" data-r="dup"><i class="bi bi-copy"></i></button><button title="Remove" data-r="del"><i class="bi bi-x-lg"></i></button></div><div class="rb"></div></div>');
                $it.find('.tt').text(itemTitle(it, i));
                f.fields.forEach(function (sf) {
                    $it.find('.rb').append(field(sf, function () { return it[sf.k]; }, function (v) { it[sf.k] = v; if (sf.k === f.title) $it.find('.tt').text(itemTitle(it, i)); }, onChange));
                });
                $it.find('.rh').on('click', function (e) { if ($(e.target).closest('button').length) return; $it.toggleClass('open'); });
                $it.find('[data-r=dup]').on('click', function (e) { e.preventDefault(); items.splice(i + 1, 0, clone(it)); build(i + 1); onChange(true); });
                $it.find('[data-r=del]').on('click', function (e) { e.preventDefault(); items.splice(i, 1); build(-1); onChange(true); });
                $list.append($it);
            });
        }
        build(-1);
        new Sortable($list[0], {
            handle: '.h', animation: 150, onEnd: function (ev) {
                var m = items.splice(ev.oldIndex, 1)[0]; items.splice(ev.newIndex, 0, m); build(-1); onChange(true);
            }
        });
        var $add = $('<button class="btn btn-sm btn-light border w-100 mt-1"><i class="bi bi-plus-lg"></i> ' + esc(f.add || 'Add item') + '</button>').on('click', function (e) {
            e.preventDefault();
            var blank = {};
            f.fields.forEach(function (sf) { blank[sf.k] = sf.t === 'number' ? (sf.k === 'qty' ? 1 : 0) : (sf.t === 'toggle' ? false : ''); });
            if (items.length && f.fields.some(function (sf) { return sf.t === 'icon'; })) {
                f.fields.forEach(function (sf) { if (sf.t === 'icon') blank[sf.k] = items[items.length - 1][sf.k] || 'check-circle'; });
            }
            items.push(blank); build(items.length - 1); onChange(true);
        });
        return $box.append($list, $add);
    }

    function section(title, icon, open) {
        var $s = $('<div class="fb-section' + (open ? '' : ' closed') + '"><div class="sh"><i class="bi ' + icon + '"></i>' + esc(title) + '<i class="bi bi-chevron-down chev"></i></div><div class="sb"></div></div>');
        $s.find('.sh').on('click', function () { $s.toggleClass('closed'); });
        return $s;
    }

    function buildBlockForm() {
        var $f = $('#blockForm');
        destroyEditors($f);
        $f.empty();
        var fb = sel ? findBlock(sel) : null;
        if (!fb) {
            $f.html('<div class="ed-empty"><i class="bi bi-hand-index"></i>Select a block on the page to edit its content and style.<div class="mt-3 small">Tip: drag widgets from the left panel onto any page.</div></div>');
            return;
        }
        var b = fb.block, def = BL[b.type] || { label: b.type, fields: [] };
        b.data = $.extend(defaults(b.type), b.data || {});
        $f.append('<div class="fb-head"><div class="ic"><i class="bi ' + esc(def.icon) + '"></i></div><div><h6>' + esc(def.label) + '</h6><small>on page “' + esc(fb.page.title) + '”</small></div></div>');
        var onCh = function (now) { markDirty(); if (b.type === 'cover') renderPageSoon(fb.page.uid, now ? 0 : 300); else renderBlockSoon(b.uid); };
        var $c = section('Content', 'bi-pencil-square', true);
        if (!def.fields.length) $c.find('.sb').append('<div class="small text-muted">This block has no settings. Content below it starts on a new A4 page.</div>');
        def.fields.forEach(function (fd) {
            $c.find('.sb').append(field(fd, function () { return b.data[fd.k]; }, function (v) { b.data[fd.k] = v; }, onCh));
        });
        $f.append($c);
        if (b.type !== 'cover' && b.type !== 'page_break') {
            b.data._s = $.extend({}, B.styleDefaults, b.data._s || {});
            var st = b.data._s;
            var $s = section('Style & spacing', 'bi-brush', false);
            [
                { k: 'bg', t: 'color', l: 'Background colour' }, { k: 'color', t: 'color', l: 'Text colour' },
                { k: 'pad', t: 'range', l: 'Inner padding', min: 0, max: 80 }, { k: 'radius', t: 'range', l: 'Corner radius', min: 0, max: 40 },
                { k: 'mt', t: 'range', l: 'Space above', min: 0, max: 200 }, { k: 'mb', t: 'range', l: 'Space below', min: 0, max: 200 },
                { k: 'align', t: 'select', l: 'Text alignment', o: { '': 'Default', left: 'Left', center: 'Center', right: 'Right' } },
                { k: 'border', t: 'toggle', l: 'Show border' }
            ].forEach(function (fd) { $s.find('.sb').append(field(fd, function () { return st[fd.k]; }, function (v) { st[fd.k] = v; }, onCh)); });
            $f.append($s);
            var $a = $('<div class="d-flex gap-2 mt-2"></div>');
            $a.append($('<button class="btn btn-sm btn-light border flex-fill"><i class="bi bi-copy"></i> Duplicate</button>').on('click', function () { $('[data-uid="' + b.uid + '"] [data-bt=dup]').trigger('click'); }));
            $a.append($('<button class="btn btn-sm btn-light border flex-fill"><i class="bi ' + (b.hidden ? 'bi-eye' : 'bi-eye-slash') + '"></i> ' + (b.hidden ? 'Show' : 'Hide') + '</button>').on('click', function () { b.hidden = !b.hidden; markDirty(); renderPage(fb.page.uid).done(buildBlockForm); }));
            $a.append($('<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3"></i></button>').on('click', function () { deleteBlock(b.uid); }));
            $f.append($a);
        }
    }

    /* ------------------------------------------------------------------
     * Uploads, media library, icon picker
     * ---------------------------------------------------------------- */
    var fileCb = null;
    function pickFile(cb) { fileCb = cb; $('#fileInput').val('').trigger('click'); }
    $('#fileInput').on('change', function () {
        if (!this.files.length) return;
        var fd = new FormData();
        fd.append('action', 'upload'); fd.append('id', PID); fd.append('file', this.files[0]);
        Swal.fire({ title: 'Uploading…', didOpen: function () { Swal.showLoading(); }, allowOutsideClick: false });
        $.ajax({ url: PM.url('ajax/editor.php'), method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
            .done(function (r) { Swal.close(); if (fileCb) fileCb(r.path); PM.toast('Image uploaded & optimised'); })
            .fail(function (x) { PM.error(x, 'Upload failed'); });
    });
    var mediaCb = null;
    function openMedia(cb) {
        mediaCb = cb;
        $('#mediaGrid').html('<div class="text-muted">Loading…</div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('mediaModal')).show();
        api('media').done(function (r) {
            if (!r.items.length) { $('#mediaGrid').html('<div class="text-muted">No uploaded images yet. Use “Upload” to add one.</div>'); return; }
            $('#mediaGrid').empty();
            r.items.forEach(function (it) { $('#mediaGrid').append($('<div class="mi"></div>').css('background-image', 'url("' + it.url + '")').data('path', it.path)); });
        });
    }
    $(document).on('click', '#mediaGrid .mi', function () {
        bootstrap.Modal.getInstance(document.getElementById('mediaModal')).hide();
        if (mediaCb) mediaCb($(this).data('path'));
    });
    var iconCb = null;
    function openIcons(cb) {
        iconCb = cb;
        if (!$('#iconGrid').children().length) {
            B.icons.forEach(function (n) { $('#iconGrid').append('<button data-icon="' + n + '"><i class="bi bi-' + n + '"></i>' + n + '</button>'); });
        }
        $('#iconSearch').val('');
        $('#iconGrid button').show();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('iconModal')).show();
    }
    $('#iconSearch').on('input', function () { var q = this.value.toLowerCase(); $('#iconGrid button').each(function () { $(this).toggle($(this).data('icon').indexOf(q) > -1); }); });
    $(document).on('click', '#iconGrid button', function () {
        bootstrap.Modal.getInstance(document.getElementById('iconModal')).hide();
        if (iconCb) iconCb($(this).data('icon'));
    });

    /* ------------------------------------------------------------------
     * Design panel (template, colours, fonts)
     * ---------------------------------------------------------------- */
    function buildDesignForm() {
        var $f = $('#designForm').empty();
        var P = S.proposal;
        var $t = section('Template', 'bi-layout-text-window-reverse', true);
        var $grid = $('<div class="tpl-pick"></div>');
        $.each(B.templates, function (id, t) {
            var $b = $('<button><span class="text-muted">' + String(id).padStart(2, '0') + '</span> ' + esc(t.name) + '<div class="sw"><span style="background:' + t.colors.primary + '"></span><span style="background:' + t.colors.secondary + '"></span><span style="background:' + t.colors.accent + '"></span><span style="background:' + t.colors.background + ';border:1px solid #e2e8f0"></span></div></button>');
            if (+id === +P.template_id) $b.addClass('active');
            $b.on('click', function () { changeTemplate(+id); });
            $grid.append($b);
        });
        $f.append($t.find('.sb').append($grid).end());

        var $c = section('Colours', 'bi-droplet-half', true);
        $.each(B.colorKeys, function (k, label) {
            var $row = $('<div class="fb-field"><label>' + esc(label) + '</label><div class="color-mini"><input type="color"><input type="text" class="form-control form-control-sm"></div></div>');
            $row.find('[type=color]').val(P.colors[k]).on('input', function () { $row.find('[type=text]').val(this.value); setColor(k, this.value); });
            $row.find('[type=text]').val(P.colors[k]).on('input', function () { if (/^#[0-9a-f]{6}$/i.test(this.value.trim())) { $row.find('[type=color]').val(this.value.trim()); setColor(k, this.value.trim()); } });
            $c.find('.sb').append($row);
        });
        $c.find('.sb').append($('<div class="d-flex gap-2"></div>')
            .append($('<button class="btn btn-sm btn-light border flex-fill"><i class="bi bi-arrow-counterclockwise"></i> Template colours</button>').on('click', function () { P.colors = clone(B.templates[P.template_id].colors); buildDesignForm(); markDirty(); renderAll(); }))
            .append($('<button class="btn btn-sm btn-light border flex-fill"><i class="bi bi-bookmark-plus"></i> Save preset</button>').on('click', savePreset)));
        $f.append($c);

        var $p = section('Brand presets', 'bi-swatch', true);
        if (!B.presets.length) $p.find('.sb').append('<div class="small text-muted">No presets yet.</div>');
        B.presets.forEach(function (pr) {
            var sw = ['primary', 'secondary', 'accent', 'background'].map(function (k) { return '<span style="background:' + (pr.colors[k] || '#fff') + '"></span>'; }).join('');
            $p.find('.sb').append($('<div class="preset-row">' + esc(pr.name) + '<div class="sw">' + sw + '</div></div>').on('click', function () {
                P.colors = $.extend({}, P.colors, pr.colors); buildDesignForm(); markDirty(); renderAll(); PM.toast('Preset “' + pr.name + '” applied');
            }));
        });
        $f.append($p);

        var $fo = section('Fonts', 'bi-fonts', true);
        [['font_heading', 'Heading font'], ['font_body', 'Body font']].forEach(function (x) {
            var $s = $('<select class="form-select form-select-sm"></select>');
            B.fonts.forEach(function (fn) { $s.append($('<option>').val(fn).text(fn).css('font-family', fn)); });
            $s.val(P[x[0]]).on('change', function () { P[x[0]] = this.value; markDirty(); renderAll(); });
            $fo.find('.sb').append($('<div class="fb-field"><label>' + x[1] + '</label></div>').append($s));
        });
        $fo.find('.sb').append($('<button class="btn btn-sm btn-light border w-100"><i class="bi bi-arrow-counterclockwise"></i> Template fonts</button>').on('click', function () {
            var t = B.templates[P.template_id]; P.font_heading = t.fonts.heading; P.font_body = t.fonts.body; buildDesignForm(); markDirty(); renderAll();
        }));
        $f.append($fo);
    }
    var colorTimer = null;
    function setColor(k, v) {
        S.proposal.colors[k] = v;
        var map = { primary: '--primary', secondary: '--secondary', accent: '--accent', text: '--text', heading: '--heading', background: '--bg', card: '--card', border: '--border', button: '--button' };
        document.getElementById('docRoot').style.setProperty(map[k], v);
        markDirty();
        clearTimeout(colorTimer);
        colorTimer = setTimeout(renderAll, 700);
    }
    function changeTemplate(id) {
        var P = S.proposal;
        if (+P.template_id === id) return;
        Swal.fire({
            title: 'Switch to ' + B.templates[id].name + '?', text: 'Would you like to use the template’s colours and fonts, or keep your current ones?',
            icon: 'question', showDenyButton: true, showCancelButton: true,
            confirmButtonText: 'Use template style', denyButtonText: 'Keep my colours', confirmButtonColor: '#015957', denyButtonColor: '#64748b'
        }).then(function (r) {
            if (r.isDismissed) return;
            P.template_id = id;
            if (r.isConfirmed) {
                P.colors = clone(B.templates[id].colors);
                P.font_heading = B.templates[id].fonts.heading;
                P.font_body = B.templates[id].fonts.body;
            }
            buildDesignForm(); markDirty(); renderAll();
        });
    }
    function savePreset() {
        Swal.fire({ title: 'Save colour preset', input: 'text', inputPlaceholder: 'e.g. Blue Corporate', showCancelButton: true, confirmButtonColor: '#015957' }).then(function (r) {
            if (!r.value) return;
            api('preset_save', { name: r.value, colors: JSON.stringify(S.proposal.colors), font_heading: S.proposal.font_heading, font_body: S.proposal.font_body }).done(function (res) {
                B.presets.push({ id: res.id, name: res.name, colors: res.colors }); buildDesignForm(); PM.toast('Preset saved');
            }).fail(PM.error);
        });
    }

    /* ------------------------------------------------------------------
     * Document panel (proposal info, client, header/footer, public page)
     * ---------------------------------------------------------------- */
    function buildDocForm() {
        var $f = $('#docForm').empty(), P = S.proposal, st = P.settings;
        var chg = function (now) { markDirty(); renderAllSoon(now ? 50 : 600); };
        var bind = function (obj, k) { return [function () { return obj[k]; }, function (v) { obj[k] = v; }]; };
        var add = function ($sec, fd, obj) { var g = bind(obj, fd.k); $sec.find('.sb').append(field(fd, g[0], g[1], chg)); };

        var $p = section('Proposal', 'bi-file-earmark-text', true);
        add($p, { k: 'title', t: 'text', l: 'Proposal title' }, P);
        add($p, { k: 'proposal_number', t: 'text', l: 'Proposal number' }, P);
        var $dates = $('<div class="row g-2"></div>');
        [['proposal_date', 'Date'], ['expiry_date', 'Expiry date']].forEach(function (d) {
            $dates.append($('<div class="col-6 fb-field"><label>' + d[1] + '</label></div>').append($('<input type="date" class="form-control">').val(P[d[0]] || '').on('change', function () { P[d[0]] = this.value; chg(true); })));
        });
        $p.find('.sb').append($dates);
        var $cur = $('<select class="form-select"></select>');
        $.each(B.currencies, function (code, c) { $cur.append($('<option>').val(code).text(code + ' — ' + c.name)); });
        $cur.append('<option value="__custom">Custom currency…</option>');
        var known = !!B.currencies[P.currency];
        $cur.val(known ? P.currency : '__custom');
        var $sym = $('<div class="row g-2 mt-1"><div class="col-6"><input class="form-control form-control-sm" placeholder="Code"></div><div class="col-6"><input class="form-control form-control-sm" placeholder="Symbol"></div></div>');
        $sym.find('input').eq(0).val(P.currency).on('input', function () { P.currency = this.value.toUpperCase(); chg(); });
        $sym.find('input').eq(1).val(P.currency_symbol).on('input', function () { P.currency_symbol = this.value; chg(); });
        $cur.on('change', function () {
            if (this.value !== '__custom') { P.currency = this.value; P.currency_symbol = B.currencies[this.value].symbol; $sym.find('input').eq(0).val(P.currency); $sym.find('input').eq(1).val(P.currency_symbol); }
            chg(true);
        });
        $p.find('.sb').append($('<div class="fb-field"><label>Currency</label></div>').append($cur, $sym));
        $f.append($p);

        var $c = section('Client', 'bi-person-vcard', true);
        [['client_name', 'Client name'], ['client_company', 'Company'], ['client_email', 'Email'], ['client_phone', 'Phone'], ['client_website', 'Website'], ['client_address', 'Address']].forEach(function (x) { add($c, { k: x[0], t: 'text', l: x[1] }, P); });
        $f.append($c);

        var $h = section('Header', 'bi-layout-text-sidebar', false);
        [{ k: 'enabled', t: 'toggle', l: 'Show header' }, { k: 'style', t: 'select', l: 'Style', o: { template: 'Template style', line: 'Underlined', bar: 'Colour bar', minimal: 'Minimal' } },
            { k: 'show_logo', t: 'toggle', l: 'Show logo' }, { k: 'logo_height', t: 'range', l: 'Logo height', min: 14, max: 60 },
            { k: 'left', t: 'text', l: 'Left text' }, { k: 'right', t: 'text', l: 'Right text' }].forEach(function (fd) { add($h, fd, st.header); });
        $f.append($h);

        var $ft = section('Footer & page numbers', 'bi-layout-text-window', false);
        [{ k: 'enabled', t: 'toggle', l: 'Show footer' }, { k: 'style', t: 'select', l: 'Style', o: { template: 'Template style', line: 'Top line', bar: 'Dark bar', minimal: 'Minimal' } },
            { k: 'show_company', t: 'toggle', l: 'Company name' }, { k: 'show_website', t: 'toggle', l: 'Website' }, { k: 'show_email', t: 'toggle', l: 'Email' }, { k: 'show_phone', t: 'toggle', l: 'Phone' },
            { k: 'text', t: 'text', l: 'Extra text' }, { k: 'page_numbers', t: 'toggle', l: 'Page numbers' },
            { k: 'format', t: 'text', l: 'Number format ({n}, {nn}, {total})' }].forEach(function (fd) { add($ft, fd, st.footer); });
        $f.append($ft);

        var $pub = section('Client page', 'bi-globe2', false);
        [{ k: 'allow_accept', t: 'toggle', l: 'Allow accept / reject online' }, { k: 'require_signature', t: 'toggle', l: 'Require signature to accept' },
            { k: 'allow_download', t: 'toggle', l: 'Allow PDF download' }].forEach(function (fd) { add($pub, fd, st); });
        $pub.find('.sb').append('<div class="fb-field"><label>Public link</label><div class="input-group input-group-sm"><input class="form-control" readonly value="' + esc(B.publicUrl) + '"><button class="btn btn-light border" id="copyLink"><i class="bi bi-clipboard"></i></button></div></div>');
        $f.append($pub);
    }
    $(document).on('click', '#copyLink', function () { PM.copy(B.publicUrl); });
    $(document).on('input', '#docForm input', function () { $('#edTitle').text(S.proposal.title); $('#edNumber').text(S.proposal.proposal_number); });

    /* ------------------------------------------------------------------
     * Pages
     * ---------------------------------------------------------------- */
    function renderPageList() {
        var $l = $('#pageList').empty();
        S.pages.forEach(function (pg, i) {
            var c = sheetStats[pg.uid] || 1;
            var $it = $('<li class="pg-item' + (pg.uid === curPage ? ' active' : '') + (pg.settings && pg.settings.hidden ? ' is-hidden' : '') + '" data-page="' + pg.uid + '">' +
                '<i class="bi bi-grip-vertical h"></i><span class="n">' + String(i + 1).padStart(2, '0') + '</span>' +
                '<span class="tt">' + esc(pg.title) + '<small>' + (pg.page_type === 'cover' ? 'Cover page' : pg.blocks.length + ' blocks' + (c > 1 ? ' · ' + c + ' sheets' : '')) + '</small></span>' +
                '<span class="acts"><button data-pl="rename" title="Rename"><i class="bi bi-pencil"></i></button>' +
                (pg.page_type === 'cover' ? '' : '<button data-pl="dup" title="Duplicate"><i class="bi bi-copy"></i></button><button data-pl="del" title="Delete"><i class="bi bi-trash3"></i></button>') + '</span></li>');
            $l.append($it);
        });
    }
    new Sortable(document.getElementById('pageList'), {
        handle: '.h', animation: 150, onEnd: function (ev) {
            if (ev.oldIndex === ev.newIndex) return;
            var m = S.pages.splice(ev.oldIndex, 1)[0]; S.pages.splice(ev.newIndex, 0, m);
            markDirty(); renderAll();
        }
    });
    $(document).on('click', '#pageList .pg-item', function (e) {
        var pu = $(this).data('page'), act = $(e.target).closest('button').data('pl');
        if (act === 'rename') return renamePage(pu);
        if (act === 'dup') return duplicatePage(pu);
        if (act === 'del') return deletePage(pu);
        curPage = pu; renderPageList(); scrollToPage(pu);
        $('.ed-page').removeClass('active').filter('[data-page="' + pu + '"]').addClass('active');
    });
    $(document).on('click', '.ed-page-bar .pa button', function (e) {
        e.stopPropagation();
        var pu = $(this).closest('.ed-page').data('page'), act = $(this).data('pa'), pg = pageByUid(pu), i = S.pages.indexOf(pg);
        if (act === 'add') { var b = newBlock('text'); pg.blocks.push(b); sel = b.uid; curPage = pu; markDirty(); renderPage(pu).done(function () { buildBlockForm(); scrollToBlock(b.uid); }); }
        if (act === 'up' && i > 0) { S.pages.splice(i, 1); S.pages.splice(i - 1, 0, pg); markDirty(); renderAll(); }
        if (act === 'down' && i < S.pages.length - 1) { S.pages.splice(i, 1); S.pages.splice(i + 1, 0, pg); markDirty(); renderAll(); }
        if (act === 'settings') pageSettings(pu);
        if (act === 'dup') duplicatePage(pu);
        if (act === 'del') deletePage(pu);
    });
    $(document).on('dblclick', '.ed-page-bar .pt', function () { renamePage($(this).closest('.ed-page').data('page')); });

    function addPage(title, silent, afterUid) {
        var pg = { uid: uid('p'), title: title, page_type: 'content', settings: { hidden: false, show_header: true, show_footer: true, bg: '' }, blocks: [] };
        var idx = S.pages.length;
        if (afterUid) { var a = pageByUid(afterUid); if (a) idx = S.pages.indexOf(a) + 1; }
        S.pages.splice(idx, 0, pg);
        if (!silent) {
            var h = newBlock('heading'); h.data.text = title; pg.blocks.push(h);
            var t = newBlock('text'); pg.blocks.push(t);
            curPage = pg.uid; sel = t.uid;
            markDirty();
            renderAll().done(function () { scrollToPage(pg.uid); buildBlockForm(); });
        }
        return pg;
    }
    $('#addPage').on('click', function () {
        Swal.fire({ title: 'Add a new page', input: 'text', inputValue: 'New Section', inputLabel: 'Page title', showCancelButton: true, confirmButtonText: 'Add page', confirmButtonColor: '#015957' })
            .then(function (r) { if (r.value) addPage(r.value.trim(), false, curPage); });
    });
    function renamePage(pu) {
        var pg = pageByUid(pu);
        Swal.fire({ title: 'Rename page', input: 'text', inputValue: pg.title, showCancelButton: true, confirmButtonColor: '#015957' }).then(function (r) {
            if (!r.value) return; pg.title = r.value.trim(); markDirty(); renderAll();
        });
    }
    function duplicatePage(pu) {
        var pg = pageByUid(pu), c = clone(pg);
        c.uid = uid('p'); c.title = pg.title + ' (copy)';
        c.blocks.forEach(function (b) { b.uid = uid('b'); });
        S.pages.splice(S.pages.indexOf(pg) + 1, 0, c);
        curPage = c.uid; markDirty();
        renderAll().done(function () { scrollToPage(c.uid); });
        PM.toast('Page duplicated');
    }
    function deletePage(pu) {
        var pg = pageByUid(pu);
        if (contentPages().length <= 1) { PM.error('A proposal needs at least one content page.'); return; }
        PM.confirm({ title: 'Delete page “' + pg.title + '”?', text: 'All blocks on this page will be removed.', confirmButtonText: 'Delete', confirmButtonColor: '#dc2626' }).then(function (r) {
            if (!r.isConfirmed) return;
            S.pages.splice(S.pages.indexOf(pg), 1);
            if (sel && !findBlock(sel)) { sel = null; buildBlockForm(); }
            if (curPage === pu) curPage = null;
            markDirty(); renderAll();
        });
    }
    function pageSettings(pu) {
        var pg = pageByUid(pu), s = pg.settings || (pg.settings = {});
        Swal.fire({
            title: 'Page settings',
            html: '<div class="text-start">' +
                '<label class="form-label">Title</label><input id="psT" class="form-control mb-3" value="' + esc(pg.title) + '">' +
                (pg.page_type === 'cover' ? '' :
                    '<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="psH"' + (s.show_header !== false ? ' checked' : '') + '><label class="form-check-label" for="psH">Show header</label></div>' +
                    '<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="psF"' + (s.show_footer !== false ? ' checked' : '') + '><label class="form-check-label" for="psF">Show footer</label></div>' +
                    '<label class="form-label mt-2">Background colour <span class="text-muted small">(empty = template)</span></label><div class="d-flex gap-2"><input type="color" id="psBgC" class="form-control form-control-color" value="' + (s.bg || '#ffffff') + '"><input id="psBg" class="form-control" placeholder="#ffffff" value="' + esc(s.bg || '') + '"></div>') +
                '<div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" id="psX"' + (s.hidden ? ' checked' : '') + '><label class="form-check-label" for="psX">Hide this page from preview, PDF & client link</label></div></div>',
            showCancelButton: true, confirmButtonText: 'Apply', confirmButtonColor: '#015957',
            didOpen: function () { $('#psBgC').on('input', function () { $('#psBg').val(this.value); }); },
            preConfirm: function () {
                return { t: $('#psT').val(), h: $('#psH').is(':checked'), f: $('#psF').is(':checked'), bg: $('#psBg').val(), x: $('#psX').is(':checked') };
            }
        }).then(function (r) {
            if (!r.value) return;
            pg.title = r.value.t.trim() || pg.title;
            if (pg.page_type !== 'cover') { s.show_header = r.value.h; s.show_footer = r.value.f; s.bg = /^#[0-9a-f]{6}$/i.test(r.value.bg) ? r.value.bg : ''; }
            s.hidden = r.value.x;
            markDirty(); renderAll();
        });
    }

    /* ------------------------------------------------------------------
     * Content library
     * ---------------------------------------------------------------- */
    var libLoaded = false;
    function loadLibrary() {
        api('library_list').done(function (r) {
            libLoaded = true;
            var $l = $('#libList').empty();
            if (!r.items.length) { $l.html('<div class="small text-muted">No saved sections yet.</div>'); return; }
            var cat = null;
            r.items.forEach(function (it) {
                if (it.category !== cat) { cat = it.category; $l.append('<div class="wg-group">' + esc(cat || 'General') + '</div>'); }
                $l.append('<div class="lib-item" data-name="' + esc(it.name.toLowerCase()) + '"><div class="tt">' + esc(it.name) + '<small>' + it.count + ' block' + (it.count === 1 ? '' : 's') + '</small></div><button class="btn btn-sm btn-brand" data-lib="' + it.id + '"><i class="bi bi-plus-lg"></i> Insert</button></div>');
            });
        });
    }
    $('#libTab').on('shown.bs.tab', function () { if (!libLoaded) loadLibrary(); });
    $('#libSearch').on('input', function () { var q = this.value.toLowerCase(); $('.lib-item').each(function () { $(this).toggle($(this).data('name').indexOf(q) > -1); }); });
    $(document).on('click', '[data-lib]', function () {
        api('library_get', { lib_id: $(this).data('lib') }).done(function (r) {
            var target = sel ? findBlock(sel) : null;
            var pg = target ? target.page : pageByUid(curPage);
            if (!pg || pg.page_type === 'cover') pg = contentPages().slice(-1)[0];
            var idx = target && target.page === pg ? target.index + 1 : pg.blocks.length;
            Array.prototype.splice.apply(pg.blocks, [idx, 0].concat(r.blocks));
            curPage = pg.uid; sel = r.blocks[0] ? r.blocks[0].uid : sel;
            markDirty();
            renderPage(pg.uid).done(function () { buildBlockForm(); if (sel) scrollToBlock(sel); });
            PM.toast('Section inserted');
        }).fail(PM.error);
    });
    function saveToLibrary(blocks, suggested) {
        Swal.fire({
            title: 'Save to content library',
            html: '<input id="lbN" class="form-control mb-2" placeholder="Section name" value="' + esc(suggested || '') + '"><input id="lbC" class="form-control" placeholder="Category (e.g. Company, Process)" value="General">',
            showCancelButton: true, confirmButtonText: 'Save', confirmButtonColor: '#015957',
            preConfirm: function () { var n = $('#lbN').val().trim(); if (!n) { Swal.showValidationMessage('Name is required'); return false; } return { n: n, c: $('#lbC').val().trim() }; }
        }).then(function (r) {
            if (!r.value) return;
            api('library_save', { name: r.value.n, category: r.value.c, blocks: JSON.stringify(blocks) }).done(function () { PM.toast('Saved to library'); libLoaded = false; if ($('#tLibrary').hasClass('active')) loadLibrary(); }).fail(PM.error);
        });
    }
    $('#saveBlockLib').on('click', function () {
        var f = sel ? findBlock(sel) : null;
        if (!f || f.block.type === 'cover') { PM.error('Select a content block first.'); return; }
        saveToLibrary([f.block], (BL[f.block.type] || {}).label);
    });
    $('#savePageLib').on('click', function () {
        var pg = pageByUid(curPage);
        if (!pg || pg.page_type === 'cover' || !pg.blocks.length) { PM.error('Click on a content page first.'); return; }
        saveToLibrary(pg.blocks, pg.title);
    });

    /* variables */
    B.vars.forEach(function (v) { $('#varList').append('<span class="var-chip" data-v="{{' + v + '}}">{{' + v + '}}</span>'); });
    $(document).on('click', '.var-chip', function () { PM.copy($(this).data('v')); });

    /* ------------------------------------------------------------------
     * Zoom, top-bar actions
     * ---------------------------------------------------------------- */
    function applyZoom() {
        var box = document.getElementById('zoomBox');
        box.style.transform = 'scale(' + zoom + ')';
        box.style.marginBottom = (-(1 - zoom) * box.offsetHeight) + 'px';
        $('#zoomVal').text(Math.round(zoom * 100) + '%');
    }
    function fitZoom() {
        var w = $('#canvas').width() - 30;
        zoom = Math.max(0.4, Math.min(1, w / 793));
        zoom = Math.floor(zoom * 20) / 20;
        applyZoom();
    }
    $('#zoomIn').on('click', function () { zoom = Math.min(1.6, +(zoom + 0.1).toFixed(2)); applyZoom(); });
    $('#zoomOut').on('click', function () { zoom = Math.max(0.3, +(zoom - 0.1).toFixed(2)); applyZoom(); });
    $('#zoomVal').on('click', fitZoom);
    new ResizeObserver(function () { applyZoom(); }).observe(document.getElementById('docRoot'));

    $('#btnSave').on('click', function () { save().done(function () { PM.toast('Proposal saved'); }); });
    $('#btnPreview').on('click', function () { var w = window.open('', '_blank'); ensureSaved().always(function () { w.location = PM.url('preview.php?id=' + PID); }); });
    $('#btnPrint').on('click', function (e) { e.preventDefault(); var w = window.open('', '_blank'); ensureSaved().always(function () { w.location = PM.url('preview.php?id=' + PID + '&print=1'); }); });
    $('#btnPdf').on('click', function () {
        ensureSaved().always(function () {
            PM.toast('Generating PDF…', 'info');
            location.href = PM.url('pdf.php?id=' + PID);
        });
    });
    $('#btnShare').on('click', function () { ensureSaved().always(function () { PM.share(B.publicUrl, PID, S.proposal.client_email, S.proposal.title); }); });

    function openDevice(w) {
        $('#deviceFrame').css('width', Math.min(w + 20, window.innerWidth - 40) + 'px');
        $('#deviceTitle').text(w >= 1200 ? 'Desktop preview' : (w >= 700 ? 'Tablet preview' : 'Mobile preview'));
        bootstrap.Modal.getOrCreateInstance(document.getElementById('deviceModal')).show();
    }
    $(document).on('click', '.device-btn', function () {
        var w = +$(this).data('w');
        ensureSaved().always(function () {
            var src = PM.url('preview.php?id=' + PID + '&embed=1');
            if ($('#deviceIframe').attr('src') !== src) $('#deviceIframe').attr('src', src);
            else document.getElementById('deviceIframe').contentWindow.location.reload();
            openDevice(w);
        });
    });

    $('#btnVersions').on('click', function (e) {
        e.preventDefault();
        ensureSaved().always(function () {
            api('versions').done(function (r) {
                var rows = r.versions.map(function (v) {
                    return '<tr><td class="text-start"><b>' + esc(v.label) + '</b><div class="small text-muted">' + esc(v.when) + (v.by ? ' · ' + esc(v.by) : '') + '</div></td><td class="text-end"><button class="btn btn-sm btn-light border" data-restore="' + v.id + '">Restore</button></td></tr>';
                }).join('');
                Swal.fire({
                    title: 'Version history', width: 600,
                    html: r.versions.length ? '<div style="max-height:380px;overflow:auto"><table class="table table-sm align-middle">' + rows + '</table></div>' : '<p class="text-muted">No versions yet. Versions are created automatically every 10 minutes while you edit.</p>',
                    showConfirmButton: false, showCloseButton: true,
                    didOpen: function () {
                        $('[data-restore]').on('click', function () {
                            var vid = $(this).data('restore');
                            PM.confirm({ title: 'Restore this version?', text: 'Your current content is saved as a version first.' }).then(function (c) {
                                if (!c.isConfirmed) return;
                                PM.post('ajax/proposals.php', { action: 'restore_version', id: PID, version_id: vid }).done(function () { dirty = false; location.reload(); }).fail(PM.error);
                            });
                        });
                    }
                });
            });
        });
    });
    $('#btnSaveVersion').on('click', function (e) {
        e.preventDefault();
        Swal.fire({ title: 'Save a named version', input: 'text', inputPlaceholder: 'e.g. Sent to client v1', showCancelButton: true, confirmButtonColor: '#015957' }).then(function (r) {
            if (!r.value) return;
            ensureSaved().always(function () { api('save_version', { label: r.value }).done(function () { PM.toast('Version saved'); }); });
        });
    });
    $('#btnSaveTemplate').on('click', function (e) {
        e.preventDefault();
        Swal.fire({
            title: 'Save as pre-built proposal', html: '<input id="stN" class="form-control mb-2" placeholder="Name" value="' + esc(S.proposal.title) + '"><input id="stD" class="form-control" placeholder="Short description">',
            showCancelButton: true, confirmButtonColor: '#015957', confirmButtonText: 'Save',
            preConfirm: function () { return { n: $('#stN').val().trim(), d: $('#stD').val().trim() }; }
        }).then(function (r) {
            if (!r.value || !r.value.n) return;
            ensureSaved().always(function () { api('save_as_template', { name: r.value.n, description: r.value.d }).done(function () { PM.toast('Saved — available when creating proposals'); }).fail(PM.error); });
        });
    });

    /* ------------------------------------------------------------------
     * Boot
     * ---------------------------------------------------------------- */
    curPage = (contentPages()[0] || S.pages[0] || {}).uid;
    buildDesignForm();
    buildDocForm();
    renderPageList();
    fitZoom();
    $(window).on('resize', function () { clearTimeout(window._rz); window._rz = setTimeout(fitZoom, 200); });
    renderAll().done(function () {
        var ready = function () { if (fontsReady) return; fontsReady = true; paginateSoon(); };
        if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { setTimeout(ready, 150); });
        setTimeout(ready, 1500);
    });
})(jQuery);
