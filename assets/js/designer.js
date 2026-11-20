/*
 * Urban Collections — Design Studio.
 * Customers place images and text on the front and back of a T-shirt / hoodie, remove image
 * backgrounds, preview the result in 3D and order or request a quote. Everything is drawn into a
 * 600 × 700 garment space; element positions are fractions of the print area (cx/cy = centre,
 * w/h = size), which is exactly what the server validates and prices.
 */
(function ($) {
    'use strict';

    var GW = 600, GH = 700;

    // ------------------------------------------------------------ garments
    var GARMENTS = {
        tshirt: {
            body: {
                front: 'M222,48 Q300,124 378,48 L470,86 Q520,120 568,166 L524,282 L462,250 L462,664 Q300,688 138,664 L138,250 L76,282 L32,166 Q80,120 130,86 Z',
                back: 'M222,48 Q300,74 378,48 L470,86 Q520,120 568,166 L524,282 L462,250 L462,664 Q300,688 138,664 L138,250 L76,282 L32,166 Q80,120 130,86 Z'
            },
            inner: { front: 'M222,48 Q300,22 378,48 Q300,124 222,48 Z' },
            rib: { front: 'M222,48 Q300,124 378,48', back: 'M222,48 Q300,74 378,48' },
            seams: ['M462,250 Q470,160 470,86', 'M138,250 Q130,160 130,86'],
            hems: ['M529,266 L466,236', 'M71,266 L134,236', 'M140,650 Q300,674 460,650'],
            area: { front: [195, 140, 210, 280], back: [195, 118, 210, 280] }
        },
        oversized: {
            body: {
                front: 'M232,56 Q300,128 368,56 L488,90 Q540,150 580,232 L522,314 L476,268 L480,672 Q300,696 120,672 L124,268 L78,314 L20,232 Q60,150 112,90 Z',
                back: 'M232,56 Q300,82 368,56 L488,90 Q540,150 580,232 L522,314 L476,268 L480,672 Q300,696 120,672 L124,268 L78,314 L20,232 Q60,150 112,90 Z'
            },
            inner: { front: 'M232,56 Q300,30 368,56 Q300,128 232,56 Z' },
            rib: { front: 'M232,56 Q300,128 368,56', back: 'M232,56 Q300,82 368,56' },
            seams: ['M476,268 Q482,170 462,94', 'M124,268 Q118,170 138,94'],
            hems: ['M534,296 L470,262', 'M66,296 L130,262', 'M124,656 Q300,680 476,656'],
            area: { front: [190, 150, 220, 289], back: [190, 130, 220, 289] }
        },
        hoodie: {
            body: {
                front: 'M200,78 L400,78 L458,94 Q522,118 544,210 L584,600 L588,650 L526,658 L520,612 L474,306 L472,646 L476,690 Q300,700 124,690 L128,646 L126,306 L80,612 L74,658 L12,650 L16,600 L56,210 Q78,118 142,94 Z ' +
                    'M196,86 C186,30 236,2 300,2 C364,2 414,30 404,86 C392,120 352,140 300,140 C248,140 208,120 196,86 Z',
                back: 'M200,78 L400,78 L458,94 Q522,118 544,210 L584,600 L588,650 L526,658 L520,612 L474,306 L472,646 L476,690 Q300,700 124,690 L128,646 L126,306 L80,612 L74,658 L12,650 L16,600 L56,210 Q78,118 142,94 Z ' +
                    'M196,86 C186,30 236,2 300,2 C364,2 414,30 404,86 C392,120 352,140 300,140 C248,140 208,120 196,86 Z'
            },
            inner: { front: 'M226,82 C224,44 256,24 300,24 C344,24 376,44 374,82 C364,112 336,124 300,124 C264,124 236,112 226,82 Z' },
            rib: { front: 'M226,82 C224,44 256,24 300,24 C344,24 376,44 374,82 C364,112 336,124 300,124 C264,124 236,112 226,82 Z' },
            bands: ['M128,646 L472,646 L476,690 Q300,700 124,690 Z', 'M520,612 L584,600 L588,650 L526,658 Z', 'M80,612 L16,600 L12,650 L74,658 Z'],
            panels: { front: ['M184,470 L416,470 L446,612 L154,612 Z'], back: ['M200,82 C196,170 236,236 300,242 C364,236 404,170 400,82 Z'] },
            seams: ['M474,306 Q470,180 458,94', 'M126,306 Q130,180 142,94', 'M184,470 L154,612', 'M416,470 L446,612'],
            backSeams: ['M300,4 L300,240'],
            cords: true,
            area: { front: [198, 256, 204, 190], back: [195, 262, 210, 250] }
        }
    };
    var pathCache = {};
    function P(d) { return pathCache[d] || (pathCache[d] = new Path2D(d)); }

    function hexToRgb(h) { var n = parseInt(h.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    function shade(hex, pct) {
        var c = hexToRgb(hex), t = pct < 0 ? 0 : 255, p = Math.abs(pct) / 100;
        return 'rgb(' + c.map(function (v) { return Math.round((t - v) * p + v); }).join(',') + ')';
    }
    function lum(hex) { var c = hexToRgb(hex); return (0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2]); }

    /**
     * Draws a garment with its design into ctx (already scaled to the 600 × 700 space).
     * opts.images: {assetToken: HTMLImageElement}, opts.fonts: {name: {css, weight}}.
     */
    function drawGarment(ctx, type, side, color, elements, opts) {
        var g = GARMENTS[type] || GARMENTS.tshirt, body = P(g.body[side]), light = lum(color) > 150, a = g.area[side];
        opts = opts || {};
        ctx.save();
        ctx.fillStyle = color;
        ctx.fill(body);
        ctx.save();
        ctx.clip(body);
        if (g.panels && g.panels[side]) {
            g.panels[side].forEach(function (d) { ctx.fillStyle = shade(color, light ? -4 : 6); ctx.fill(P(d)); });
        }
        // design, clipped to the print area
        if (elements && elements.length) {
            ctx.save();
            ctx.beginPath();
            ctx.rect(a[0], a[1], a[2], a[3]);
            ctx.clip();
            elements.forEach(function (el) { if (el.side === side) { drawElement(ctx, el, a, opts); } });
            ctx.restore();
        }
        // fabric shading over the print so it looks printed on, not pasted on
        var lg = ctx.createLinearGradient(0, 0, GW, 0), edge = light ? 0.13 : 0.32;
        lg.addColorStop(0, 'rgba(0,0,0,' + edge + ')'); lg.addColorStop(0.24, 'rgba(0,0,0,0)');
        lg.addColorStop(0.76, 'rgba(0,0,0,0)'); lg.addColorStop(1, 'rgba(0,0,0,' + edge + ')');
        ctx.fillStyle = lg; ctx.fillRect(0, 0, GW, GH);
        var rg = ctx.createRadialGradient(300, 300, 20, 300, 330, 300);
        rg.addColorStop(0, 'rgba(255,255,255,' + (light ? 0.10 : 0.07) + ')'); rg.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = rg; ctx.fillRect(0, 0, GW, GH);
        var vg = ctx.createLinearGradient(0, 0, 0, GH);
        vg.addColorStop(0, 'rgba(0,0,0,0)'); vg.addColorStop(0.85, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,0,0,' + (light ? 0.06 : 0.14) + ')');
        ctx.fillStyle = vg; ctx.fillRect(0, 0, GW, GH);
        ctx.lineCap = 'round';
        [[205, 520, 236, 668, 26], [392, 500, 372, 668, 22], [170, 300, 222, 380, 16], [430, 300, 380, 380, 16]].forEach(function (f) {
            ctx.strokeStyle = 'rgba(0,0,0,' + (light ? 0.035 : 0.09) + ')'; ctx.lineWidth = f[4];
            ctx.beginPath(); ctx.moveTo(f[0], f[1]); ctx.lineTo(f[2], f[3]); ctx.stroke();
        });
        if (g.bands) { g.bands.forEach(function (d) { ctx.fillStyle = 'rgba(0,0,0,' + (light ? 0.06 : 0.16) + ')'; ctx.fill(P(d)); }); }
        ctx.restore();
        if (g.inner && g.inner[side]) { ctx.fillStyle = shade(color, light ? -22 : -35); ctx.fill(P(g.inner[side])); }
        var stitch = light ? 'rgba(0,0,0,0.16)' : 'rgba(255,255,255,0.13)';
        ctx.lineWidth = 2; ctx.strokeStyle = stitch;
        (g.seams || []).concat(side === 'back' ? (g.backSeams || []) : []).forEach(function (d) { ctx.stroke(P(d)); });
        ctx.setLineDash([5, 4]);
        (g.hems || []).forEach(function (d) { ctx.stroke(P(d)); });
        ctx.setLineDash([]);
        if (g.rib && g.rib[side]) { ctx.lineWidth = type === 'hoodie' ? 7 : 11; ctx.strokeStyle = shade(color, light ? -10 : 12); ctx.stroke(P(g.rib[side])); }
        if (g.cords && side === 'front') {
            ctx.lineWidth = 5; ctx.strokeStyle = light ? shade(color, -28) : shade(color, 45);
            ctx.beginPath(); ctx.moveTo(282, 124); ctx.lineTo(277, 236); ctx.moveTo(318, 124); ctx.lineTo(323, 236); ctx.stroke();
            ctx.fillStyle = light ? '#555' : '#ddd'; ctx.fillRect(273, 230, 8, 16); ctx.fillRect(319, 230, 8, 16);
        }
        ctx.restore();
    }

    function drawElement(ctx, el, a, opts) {
        var w = el.w * a[2], h = el.h * a[3];
        ctx.save();
        ctx.translate(a[0] + el.cx * a[2], a[1] + el.cy * a[3]);
        ctx.rotate((el.rot || 0) * Math.PI / 180);
        if (el.kind === 'text') {
            var f = (opts.fonts || {})[el.font] || { css: 'Arial', weight: 700 };
            ctx.font = f.weight + ' 100px ' + f.css;
            var mw = ctx.measureText(el.text).width || 1, px = 100 * w / mw;
            ctx.font = f.weight + ' ' + px + 'px ' + f.css;
            ctx.fillStyle = el.color;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(el.text, 0, 0);
        } else {
            var img = (opts.images || {})[el.asset];
            if (img && img.complete && img.naturalWidth) {
                if (el.flip) { ctx.scale(-1, 1); }
                ctx.drawImage(img, -w / 2, -h / 2, w, h);
            }
        }
        ctx.restore();
    }

    /** Renders one side to a new canvas of the given width (used for previews, 3D textures and product images). */
    function renderSide(type, side, color, elements, width, opts) {
        var c = document.createElement('canvas'), s = width / GW;
        c.width = width; c.height = Math.round(GH * s);
        var ctx = c.getContext('2d');
        ctx.scale(s, s);
        drawGarment(ctx, type, side, color, elements, opts);
        return c;
    }
    window.UCDesignerDraw = { render: renderSide, garments: GARMENTS };

    var CFG = window.UC_DESIGNER;
    var $root = $('[data-designer]');
    if (!CFG || !$root.length) { return; }

    // ------------------------------------------------------------ state
    var UC = window.UC || {};
    var currency = UC.currency || 'Rs.';
    function money(n) { return currency + ' ' + Math.round(n).toLocaleString('en-US'); }
    function esc(s) { return $('<i>').text(s).html(); }
    var FONTS = {};
    CFG.fonts.forEach(function (f) { FONTS[f.name] = f; });
    var images = {};
    var state = { blank: CFG.selected || 0, color: null, side: 'front', els: [], sel: null, sizes: {}, uid: 1 };
    var $stage = $root.find('[data-stage]'), canvas = $root.find('[data-garment]')[0], $area = $root.find('[data-area]');
    var $content = $root.find('[data-content]'), $ui = $root.find('[data-ui]');

    function blank() { return CFG.blanks[state.blank]; }
    function colorHex() { var c = blank().colors.filter(function (x) { return x.name === state.color; })[0]; return c ? c.hex : '#ffffff'; }
    function areaOf(side) { return GARMENTS[blank().type].area[side || state.side]; }
    function selected() { return state.els.filter(function (e) { return e.id === state.sel; })[0] || null; }
    function sideEls(side) { return state.els.filter(function (e) { return e.side === side; }); }

    function loadImage(token, url) {
        if (images[token]) { return images[token]; }
        var img = new Image();
        img.decoding = 'async';
        img.onload = function () { requestDraw(); };
        img.src = url;
        images[token] = img;
        return img;
    }
    function imagesReady() {
        return Promise.all(state.els.filter(function (e) { return e.kind === 'image'; }).map(function (e) {
            var img = loadImage(e.asset, e.url);
            return img.complete ? Promise.resolve() : new Promise(function (res) { img.addEventListener('load', res); img.addEventListener('error', res); });
        }));
    }
    function fontsReady() {
        if (!document.fonts || !document.fonts.load) { return Promise.resolve(); }
        var used = {};
        state.els.forEach(function (e) { if (e.kind === 'text') { used[e.font] = true; } });
        return Promise.all(Object.keys(used).map(function (n) { var f = FONTS[n]; return f ? document.fonts.load(f.weight + ' 60px ' + f.css).catch(function () {}) : null; }));
    }

    // ------------------------------------------------------------ drawing
    var drawQueued = false;
    function requestDraw() {
        if (drawQueued) { return; }
        drawQueued = true;
        window.requestAnimationFrame(function () { drawQueued = false; draw(); });
    }
    function draw() {
        var r = $stage[0].getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
        var w = Math.round(r.width * dpr), h = Math.round(w * GH / GW);
        if (canvas.width !== w) { canvas.width = w; canvas.height = h; }
        var ctx = canvas.getContext('2d');
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.clearRect(0, 0, w, h);
        ctx.scale(w / GW, w / GW);
        drawGarment(ctx, blank().type, state.side, colorHex(), state.els, { images: images, fonts: FONTS });
    }

    function placeArea() {
        var a = areaOf();
        $area.css({ left: a[0] / GW * 100 + '%', top: a[1] / GH * 100 + '%', width: a[2] / GW * 100 + '%', height: a[3] / GH * 100 + '%' });
    }

    function boxCss(el) {
        return { left: (el.cx - el.w / 2) * 100 + '%', top: (el.cy - el.h / 2) * 100 + '%', width: el.w * 100 + '%', height: el.h * 100 + '%',
            transform: 'rotate(' + (el.rot || 0) + 'deg)' };
    }

    /** Invisible hit boxes for every element on this side, plus the selection frame with handles. */
    function renderBoxes() {
        $content.empty();
        sideEls(state.side).forEach(function (el) {
            $('<div class="uc-dz-box" tabindex="-1">').attr('data-el', el.id).toggleClass('is-text', el.kind === 'text').css(boxCss(el)).appendTo($content);
        });
        $ui.empty();
        var el = selected();
        if (el && el.side === state.side) {
            $('<div class="uc-dz-frame" data-frame>').css(boxCss(el)).append(
                '<span class="uc-dz-h is-rot" data-handle="rot" title="Rotate"><i class="bi bi-arrow-clockwise"></i></span>' +
                '<span class="uc-dz-h is-nw" data-handle="size"></span><span class="uc-dz-h is-ne" data-handle="size"></span>' +
                '<span class="uc-dz-h is-sw" data-handle="size"></span><span class="uc-dz-h is-se" data-handle="size"></span>' +
                '<button type="button" class="uc-dz-h is-del" data-del title="Delete"><i class="bi bi-x"></i></button>'
            ).appendTo($ui);
        }
        $root.find('[data-empty]').toggle(!sideEls(state.side).length);
    }

    function refresh(full) {
        renderBoxes();
        requestDraw();
        if (full !== false) { renderLayers(); renderProps(); renderPrice(); saveDraft(); }
        $root.find('[data-side-count]').each(function () { var n = sideEls($(this).data('side-count')).length; $(this).text(n ? '(' + n + ')' : ''); });
    }

    // ------------------------------------------------------------ garment, colour, sizes
    function renderColors() {
        var b = blank();
        if (!b.colors.some(function (c) { return c.name === state.color; })) { state.color = b.colors[0].name; }
        $root.find('[data-colors]').html(b.colors.map(function (c) {
            return '<button type="button" class="uc-opt-color' + (c.name === state.color ? ' is-active' : '') + '" data-color="' + esc(c.name) + '" title="' + esc(c.name) + '" aria-pressed="' + (c.name === state.color) + '">' +
                '<span class="uc-swatch-dot" style="--sw:' + c.hex + '"></span><span class="visually-hidden">' + esc(c.name) + '</span></button>';
        }).join(''));
        $root.find('[data-color-name]').text(state.color);
    }
    function renderSizes() {
        var b = blank(), stock = b.stock[state.color] || {}, html = '';
        Object.keys(state.sizes).forEach(function (s) { if (b.sizes.indexOf(s) < 0 || !(stock[s] > 0)) { delete state.sizes[s]; } });
        if (!Object.keys(state.sizes).length) {
            var pick = b.sizes.filter(function (s) { return stock[s] > 0; });
            var def = pick.indexOf('M') >= 0 ? 'M' : (pick.indexOf('L') >= 0 ? 'L' : pick[0]);
            if (def) { state.sizes[def] = 1; }
        }
        b.sizes.forEach(function (s) {
            var avail = stock[s] || 0, q = state.sizes[s] || 0;
            html += '<div class="uc-dz-size' + (q ? ' is-on' : '') + (avail > 0 ? '' : ' is-out') + '"><span>' + esc(s) + '</span>' +
                (avail > 0 ? '<div class="uc-mstepper"><button type="button" data-size-step="-1" data-size="' + esc(s) + '" aria-label="Fewer ' + esc(s) + '">−</button>' +
                    '<input type="number" min="0" max="' + Math.min(20, avail) + '" value="' + q + '" data-size-input="' + esc(s) + '" aria-label="Quantity in size ' + esc(s) + '">' +
                    '<button type="button" data-size-step="1" data-size="' + esc(s) + '" aria-label="More ' + esc(s) + '">+</button></div>' : '<small>Sold out</small>') + '</div>';
        });
        $root.find('[data-sizes]').html(html);
    }
    function setBlank(i) {
        state.blank = i;
        $root.find('[data-blank]').each(function () { var on = +$(this).data('blank') === i; $(this).toggleClass('is-active', on).attr('aria-pressed', on); });
        renderColors();
        renderSizes();
        placeArea();
        refresh();
    }

    // ------------------------------------------------------------ pricing (mirrors design_price() in includes/designer.php)
    function sizeClass(el) {
        var p = CFG.pricing, pct = Math.max(0, Math.min(1, el.w)) * Math.max(0, Math.min(1, el.h)) * 100;
        return pct <= p.small_max ? 'small' : (pct <= p.medium_max ? 'medium' : 'large');
    }
    function printPrice() {
        var p = CFG.pricing, img = 0, txt = 0, n = 0, sides = {};
        state.els.forEach(function (el) {
            sides[el.side] = 1;
            if (el.kind === 'text') { txt += p.text; } else { img += p[sizeClass(el)]; n++; }
        });
        var discount = n >= 2 ? Math.round(img * p.multi_discount) / 100 : 0;
        var sidesFee = Object.keys(sides).length > 1 ? p.both_sides_fee : 0;
        var total = img + txt - discount + sidesFee, min = false;
        if (state.els.length && total < p.min_print) { total = p.min_print; min = true; }
        return { img: img, txt: txt, discount: discount, sidesFee: sidesFee, total: total, min: min, images: n };
    }
    function qtyTotal() { var t = 0; Object.keys(state.sizes).forEach(function (s) { t += state.sizes[s]; }); return t; }
    function renderPrice() {
        var qty = qtyTotal(), b = blank();
        if (!CFG.pricing) { $root.find('[data-mobile-total]').text(qty + ' pc'); return; }
        var pp = printPrice(), unit = b.price + pp.total, html = '';
        html += '<dt>' + esc(b.label) + '</dt><dd>' + money(b.price) + '</dd>';
        state.els.forEach(function (el) {
            html += '<dt class="is-sub">' + (el.side === 'back' ? 'Back' : 'Front') + ' · ' + (el.kind === 'text' ? 'text' : sizeClass(el) + ' image') + '</dt><dd>' + money(el.kind === 'text' ? CFG.pricing.text : CFG.pricing[sizeClass(el)]) + '</dd>';
        });
        if (pp.discount) { html += '<dt class="is-sub">Multi-image discount (' + CFG.pricing.multi_discount + '%)</dt><dd class="is-discount">−' + money(pp.discount) + '</dd>'; }
        if (pp.sidesFee) { html += '<dt class="is-sub">Front &amp; back printing</dt><dd>' + money(pp.sidesFee) + '</dd>'; }
        if (pp.min) { html += '<dt class="is-sub">Minimum print charge applies</dt><dd></dd>'; }
        html += '<dt>Price per piece</dt><dd>' + money(unit) + '</dd>';
        html += '<dt class="is-total">Total (' + qty + ' pc)</dt><dd class="is-total">' + money(unit * qty) + '</dd>';
        $root.find('[data-price-lines]').html(html);
        $root.find('[data-add-bag]').html('Add to bag — ' + money(unit * qty));
        $root.find('[data-mobile-total]').text(money(unit * qty));
    }

    // ------------------------------------------------------------ layers & properties
    function labelOf(el) { return el.kind === 'text' ? '“' + el.text + '”' : (el.name || 'Image'); }
    function renderLayers() {
        var html = state.els.map(function (el) {
            var thumb = el.kind === 'text' ? '<span class="uc-dz-thumb is-text" style="font-family:' + esc(FONTS[el.font].css) + ';color:' + el.color + '">Aa</span>' : '<span class="uc-dz-thumb"><img src="' + esc(el.url) + '" alt=""></span>';
            var cls = CFG.pricing && el.kind === 'image' ? '<small class="uc-dz-tag">' + sizeClass(el) + '</small>' : '';
            return '<li class="' + (el.id === state.sel ? 'is-active' : '') + '"><button type="button" data-select="' + el.id + '">' + thumb +
                '<span><strong>' + esc(labelOf(el)) + '</strong><small>' + (el.side === 'back' ? 'Back' : 'Front') + '</small></span>' + cls + '</button>' +
                '<button type="button" class="uc-dz-x" data-remove="' + el.id + '" aria-label="Remove"><i class="bi bi-trash3"></i></button></li>';
        }).join('');
        $root.find('[data-layers]').html(html);
        $root.find('[data-side-label]').text('on the ' + state.side);
    }
    function printSize(el) {
        var cm = blank().area_cm[el.side];
        return Math.round(el.w * cm[0]) + ' × ' + Math.round(el.h * cm[1]) + ' cm';
    }
    function renderProps() {
        var el = selected(), $p = $root.find('[data-props]');
        if (!el) { $p.prop('hidden', true).empty(); return; }
        var html = '<div class="uc-dz-props-head"><strong>' + esc(labelOf(el)) + '</strong><small>Print size ≈ ' + printSize(el) + (CFG.pricing && el.kind === 'image' ? ' · ' + sizeClass(el) : '') + '</small></div>';
        if (el.kind === 'text') {
            html += '<label class="uc-field"><span>Text</span><input class="form-control" maxlength="40" value="' + esc(el.text) + '" data-prop="text"></label>';
            html += '<div class="row g-2"><div class="col-7"><label class="uc-field"><span>Font</span><select class="form-select" data-prop="font">' +
                CFG.fonts.map(function (f) { return '<option' + (f.name === el.font ? ' selected' : '') + ' style="font-family:' + esc(f.css) + '">' + esc(f.name) + '</option>'; }).join('') + '</select></label></div>' +
                '<div class="col-5"><label class="uc-field"><span>Colour</span><input type="color" class="form-control form-control-color w-100" value="' + el.color + '" data-prop="color"></label></div></div>';
            html += '<div class="uc-dz-inks">' + ['#111111', '#FFFFFF', '#C62828', '#B69B63', '#1F2A44', '#2E7D32', '#F4CD28', '#E2582C'].map(function (c) {
                return '<button type="button" data-ink="' + c + '" style="--sw:' + c + '" class="uc-swatch-dot" title="' + c + '"></button>'; }).join('') + '</div>';
        } else {
            html += '<div class="uc-dz-bg"><p class="uc-opt-label mb-1">Background</p><div class="d-flex flex-wrap gap-2">' +
                '<button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-bg-quick><i class="bi bi-magic"></i> ' + (el.orig ? 'Redo' : 'Remove background') + '</button>' +
                (CFG.aiBg ? '<button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-bg-ai><i class="bi bi-stars"></i> AI remove</button>' : '') +
                (el.orig ? '<button type="button" class="uc-link-btn" data-bg-restore>Restore original</button>' : '') + '</div>' +
                (el.orig && el.bgMode === 'quick' ? '<label class="uc-field mt-2"><span>Strength <small class="text-muted">— raise it if background is left, lower it if your image gets cut</small></span><input type="range" class="form-range" min="6" max="60" value="' + el.tol + '" data-tol></label>' : '') +
                '<p class="small text-muted mb-0 mt-1">Quick remove works best on plain backgrounds (logos, product photos, white or coloured walls).' + (CFG.aiBg ? ' Use AI remove for people and busy backgrounds.' : '') + '</p></div>';
        }
        html += '<div class="uc-dz-tools">' +
            '<button type="button" data-tool="center" title="Centre"><i class="bi bi-align-center"></i><span>Centre</span></button>' +
            (el.kind === 'image' ? '<button type="button" data-tool="flip" title="Mirror"><i class="bi bi-symmetry-vertical"></i><span>Mirror</span></button>' : '') +
            '<button type="button" data-tool="up" title="Bring forward"><i class="bi bi-layer-forward"></i><span>Forward</span></button>' +
            '<button type="button" data-tool="down" title="Send backward"><i class="bi bi-layer-backward"></i><span>Back</span></button>' +
            '<button type="button" data-tool="dup" title="Duplicate"><i class="bi bi-copy"></i><span>Copy</span></button>' +
            '<button type="button" data-tool="side" title="Move to other side"><i class="bi bi-arrow-left-right"></i><span>' + (el.side === 'front' ? 'To back' : 'To front') + '</span></button>' +
            '<button type="button" data-tool="del" title="Delete" class="is-danger"><i class="bi bi-trash3"></i><span>Delete</span></button></div>';
        $p.html(html).prop('hidden', false);
    }
    function select(id) {
        state.sel = id;
        var el = selected();
        if (el && el.side !== state.side) { setSide(el.side); return; }
        refresh();
    }
    function setSide(side) {
        state.side = side;
        $root.find('[data-side]').each(function () { $(this).toggleClass('is-active', $(this).data('side') === side); });
        if (selected() && selected().side !== side) { state.sel = null; }
        placeArea();
        refresh();
    }

    // ------------------------------------------------------------ adding elements
    function canAdd() {
        if (state.els.length >= CFG.maxElements) {
            window.Swal && Swal.fire({ icon: 'info', text: 'A design can have up to ' + CFG.maxElements + ' images and texts.' });
            return false;
        }
        return true;
    }
    function fitBox(el, natW, natH, maxW, maxH) {
        var a = areaOf(el.side), w = maxW, h = w * a[2] * natH / natW / a[3];
        if (h > maxH) { h = maxH; w = h * a[3] * natW / natH / a[2]; }
        el.w = w; el.h = h;
    }
    function addImage(asset, name) {
        var el = { id: state.uid++, side: state.side, kind: 'image', cx: 0.5, cy: 0.42, rot: 0, flip: false, asset: asset.token, url: asset.url, nat: [asset.w, asset.h], name: name || 'Image', tol: 26, orig: null };
        fitBox(el, asset.w, asset.h, 0.6, 0.6);
        loadImage(asset.token, asset.url);
        state.els.push(el);
        select(el.id);
    }
    function textAspect(el) {
        var c = document.createElement('canvas').getContext('2d'), f = FONTS[el.font];
        c.font = f.weight + ' 100px ' + f.css;
        return (c.measureText(el.text).width || 100) / 120;
    }
    function setTextBox(el, keepWidth) {
        var a = areaOf(el.side), asp = textAspect(el);
        if (!keepWidth) { el.w = Math.min(0.8, Math.max(0.2, 0.09 * el.text.length)); }
        el.h = el.w * a[2] / asp / a[3];
        if (el.h > 0.5) { el.h = 0.5; el.w = el.h * a[3] * asp / a[2]; }
    }
    function addText() {
        if (!canAdd()) { return; }
        var el = { id: state.uid++, side: state.side, kind: 'text', text: 'Your text', font: CFG.fonts[0].name, cx: 0.5, cy: sideEls(state.side).length ? 0.82 : 0.3, rot: 0,
            color: lum(colorHex()) > 150 ? '#111111' : '#FFFFFF' };
        setTextBox(el);
        state.els.push(el);
        select(el.id);
        setTimeout(function () { $root.find('[data-prop=text]').trigger('focus').select(); }, 30);
    }

    // ------------------------------------------------------------ uploads
    function busy(on, text) { $root.find('[data-busy]').prop('hidden', !on); $root.find('[data-busy-text]').text(text || 'Working…'); }
    function fail(x, fallback) {
        var m = (x && x.responseJSON && x.responseJSON.message) || (x && x.message) || fallback || 'Something went wrong. Please try again.';
        window.Swal ? Swal.fire({ icon: 'warning', text: m }) : alert(m);
    }
    function post(data, isForm) {
        var opts = { url: CFG.endpoint, method: 'POST', data: data, dataType: 'json' };
        if (isForm) { opts.processData = false; opts.contentType = false; }
        return $.ajax(opts);
    }
    function uploadBlob(blob, name, source) {
        var fd = new FormData();
        fd.append('action', 'upload');
        fd.append('csrf_token', UC.csrf);
        fd.append('file', blob, name);
        if (source) { fd.append('source', source); }
        return post(fd, true);
    }
    /** Shrinks very large photos in the browser so uploads stay fast and under the size limit. */
    function prepareFile(file) {
        return new Promise(function (resolve, reject) {
            if (!/^image\/(png|jpeg|webp)$/.test(file.type)) { reject(new Error('Please choose a JPG, PNG or WebP image.')); return; }
            var url = URL.createObjectURL(file), img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                var max = 2400, s = Math.min(1, max / Math.max(img.width, img.height));
                if (s === 1 && file.size < CFG.maxUploadMb * 1048576 * 0.9) { resolve(file); return; }
                var c = document.createElement('canvas');
                c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
                c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                var type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/png';
                c.toBlob(function (b) { b ? resolve(b) : reject(new Error('Could not read this image.')); }, type, 0.92);
            };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Could not read this image.')); };
            img.src = url;
        });
    }
    function handleFiles(files) {
        files = Array.prototype.slice.call(files || []);
        var next = function () {
            var f = files.shift();
            if (!f) { busy(false); return; }
            if (!canAdd()) { busy(false); return; }
            busy(true, 'Uploading ' + f.name + '…');
            prepareFile(f).then(function (blob) {
                return uploadBlob(blob, f.name.replace(/\.[^.]+$/, '') + (blob.type === 'image/jpeg' ? '.jpg' : '.png'));
            }).then(function (r) {
                if (!r.ok) { throw new Error(r.message); }
                addImage(r.asset, f.name.replace(/\.[^.]+$/, '').slice(0, 30));
                next();
            }, function (e) { busy(false); fail(e); });
        };
        next();
    }

    // ------------------------------------------------------------ background removal
    /**
     * Quick remove: flood-fills from the image edges, removing colours close to the background,
     * softens the cut edge and trims the empty margins. Runs entirely in the browser.
     */
    function quickRemove(img, tol) {
        var max = 1600, s = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        var w = Math.round(img.naturalWidth * s), h = Math.round(img.naturalHeight * s);
        var c = document.createElement('canvas'); c.width = w; c.height = h;
        var ctx = c.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);
        var id = ctx.getImageData(0, 0, w, h), d = id.data, src = new Uint8ClampedArray(d), N = w * h;
        var removed = new Uint8Array(N), thr = tol * 2.6, thr2 = thr * thr;
        function similar(p, r, g, b) {
            var i = p * 4;
            if (src[i + 3] < 24) { return true; }
            var dr = src[i] - r, dg = src[i + 1] - g, db = src[i + 2] - b;
            return dr * dr + dg * dg + db * db <= thr2;
        }
        function fill(start) {
            if (removed[start]) { return; }
            var i0 = start * 4, r = src[i0], g = src[i0 + 1], b = src[i0 + 2], stack = [start];
            while (stack.length) {
                var p = stack.pop();
                if (removed[p] || !similar(p, r, g, b)) { continue; }
                removed[p] = 1;
                var x = p % w;
                if (x > 0) { stack.push(p - 1); }
                if (x < w - 1) { stack.push(p + 1); }
                if (p >= w) { stack.push(p - w); }
                if (p < N - w) { stack.push(p + w); }
            }
        }
        var x, y;
        for (x = 0; x < w; x++) { fill(x); fill(N - w + x); }
        for (y = 0; y < h; y++) { fill(y * w); fill(y * w + w - 1); }
        var count = 0, minX = w, minY = h, maxX = -1, maxY = -1;
        for (var p = 0; p < N; p++) {
            if (removed[p]) { d[p * 4 + 3] = 0; count++; continue; }
            x = p % w; y = (p - x) / w;
            var edge = (x > 0 && removed[p - 1]) || (x < w - 1 && removed[p + 1]) || (p >= w && removed[p - w]) || (p < N - w && removed[p + w]);
            if (edge) {
                var q = x > 0 && removed[p - 1] ? p - 1 : (x < w - 1 && removed[p + 1] ? p + 1 : (p >= w && removed[p - w] ? p - w : p + w));
                var i = p * 4, j = q * 4, dr = src[i] - src[j], dg = src[i + 1] - src[j + 1], db = src[i + 2] - src[j + 2];
                var dist = Math.sqrt(dr * dr + dg * dg + db * db);
                d[i + 3] = Math.round(src[i + 3] * Math.max(0.15, Math.min(1, (dist - thr * 0.6) / (thr * 0.9))));
            }
            if (x < minX) { minX = x; } if (x > maxX) { maxX = x; }
            if (y < minY) { minY = y; } if (y > maxY) { maxY = y; }
        }
        if (count === 0) { throw new Error('No plain background was found around the edges of this image. Try a higher strength' + (CFG.aiBg ? ' or AI remove.' : '.')); }
        if (maxX < 0 || (maxX - minX) * (maxY - minY) < N * 0.002) { throw new Error('That removed almost everything. Lower the strength and try again.'); }
        ctx.putImageData(id, 0, 0);
        minX = Math.max(0, minX - 2); minY = Math.max(0, minY - 2); maxX = Math.min(w - 1, maxX + 2); maxY = Math.min(h - 1, maxY + 2);
        var out = document.createElement('canvas'); out.width = maxX - minX + 1; out.height = maxY - minY + 1;
        out.getContext('2d').drawImage(c, minX, minY, out.width, out.height, 0, 0, out.width, out.height);
        return { canvas: out, crop: [minX / w, minY / h, out.width / w, out.height / h] };
    }
    /** Keeps the visible part of the image where it was after trimming (also when rotated). */
    function applyCrop(el, crop) {
        var a = areaOf(el.side), wpx = el.w * a[2], hpx = el.h * a[3];
        var ox = (crop[0] + crop[2] / 2 - 0.5) * wpx * (el.flip ? -1 : 1), oy = (crop[1] + crop[3] / 2 - 0.5) * hpx, r = el.rot * Math.PI / 180;
        el.cx += (ox * Math.cos(r) - oy * Math.sin(r)) / a[2];
        el.cy += (ox * Math.sin(r) + oy * Math.cos(r)) / a[3];
        el.w *= crop[2]; el.h *= crop[3];
    }
    function originalOf(el) { return el.orig || { asset: el.asset, url: el.url, w: el.w, h: el.h, cx: el.cx, cy: el.cy, nat: el.nat }; }
    function runQuick(el) {
        var o = originalOf(el), img = loadImage(o.asset, o.url);
        busy(true, 'Removing background…');
        var go = function () {
            var res;
            try { res = quickRemove(img, el.tol); } catch (e) { busy(false); fail(e); return; }
            res.canvas.toBlob(function (blob) {
                uploadBlob(blob, 'cutout.png', o.asset).done(function (r) {
                    if (!r.ok) { busy(false); fail(null, r.message); return; }
                    el.orig = o;
                    el.w = o.w; el.h = o.h; el.cx = o.cx; el.cy = o.cy;
                    applyCrop(el, res.crop);
                    el.asset = r.asset.token; el.url = r.asset.url; el.nat = [r.asset.w, r.asset.h]; el.bgMode = 'quick';
                    loadImage(el.asset, el.url).onload = function () { busy(false); refresh(); };
                    refresh();
                }).fail(function (x) { busy(false); fail(x); });
            }, 'image/png');
        };
        img.complete && img.naturalWidth ? go() : $(img).one('load', go);
    }
    function runAi(el) {
        var o = originalOf(el);
        busy(true, 'Removing background with AI… this can take a few seconds');
        post({ action: 'remove_bg', asset: o.asset, csrf_token: UC.csrf }).done(function (r) {
            if (!r.ok) { busy(false); fail(null, r.message); return; }
            el.orig = o;
            el.w = o.w; el.h = o.h; el.cx = o.cx; el.cy = o.cy;
            el.asset = r.asset.token; el.url = r.asset.url; el.nat = [r.asset.w, r.asset.h]; el.bgMode = 'ai';
            loadImage(el.asset, el.url).onload = function () { busy(false); refresh(); };
        }).fail(function (x) { busy(false); fail(x); });
    }

    // ------------------------------------------------------------ pointer interaction
    var drag = null;
    function areaRect() { return $area[0].getBoundingClientRect(); }
    function startDrag(e, mode, el) {
        var r = areaRect();
        drag = { mode: mode, el: el, r: r, x: e.clientX, y: e.clientY, cx: el.cx, cy: el.cy, w: el.w, h: el.h, rot: el.rot,
            ccx: r.left + el.cx * r.width, ccy: r.top + el.cy * r.height };
        drag.d0 = Math.hypot(e.clientX - drag.ccx, e.clientY - drag.ccy) || 1;
        drag.a0 = Math.atan2(e.clientY - drag.ccy, e.clientX - drag.ccx);
        e.currentTarget.setPointerCapture && e.currentTarget.setPointerCapture(e.pointerId);
        e.preventDefault();
    }
    function moveDrag(e) {
        if (!drag) { return; }
        var el = drag.el, r = drag.r, gv = false, gh = false;
        if (drag.mode === 'move') {
            el.cx = drag.cx + (e.clientX - drag.x) / r.width;
            el.cy = drag.cy + (e.clientY - drag.y) / r.height;
            if (Math.abs(el.cx - 0.5) < 0.015) { el.cx = 0.5; gv = true; }
            if (Math.abs(el.cy - 0.5) < 0.015) { el.cy = 0.5; gh = true; }
            el.cx = Math.max(-0.2, Math.min(1.2, el.cx)); el.cy = Math.max(-0.2, Math.min(1.2, el.cy));
        } else if (drag.mode === 'size') {
            var k = Math.hypot(e.clientX - drag.ccx, e.clientY - drag.ccy) / drag.d0;
            k = Math.max(0.04 / drag.w, Math.min(1.5 / Math.max(drag.w, drag.h), k));
            el.w = drag.w * k; el.h = drag.h * k;
        } else if (drag.mode === 'rot') {
            var deg = drag.rot + (Math.atan2(e.clientY - drag.ccy, e.clientX - drag.ccx) - drag.a0) * 180 / Math.PI;
            deg = ((deg % 360) + 540) % 360 - 180;
            [-180, -90, 0, 90, 180].forEach(function (s) { if (Math.abs(deg - s) < 4) { deg = s; } });
            el.rot = Math.round(deg * 10) / 10;
        }
        $root.find('[data-guide-v]').toggleClass('is-on', gv);
        $root.find('[data-guide-h]').toggleClass('is-on', gh);
        refresh(false);
    }
    function endDrag() {
        if (!drag) { return; }
        drag = null;
        $root.find('[data-guide-v], [data-guide-h]').removeClass('is-on');
        refresh();
    }
    $content.on('pointerdown', '.uc-dz-box', function (e) {
        var id = +$(this).data('el'), el = state.els.filter(function (x) { return x.id === id; })[0];
        if (!el) { return; }
        if (state.sel !== id) { state.sel = id; refresh(); }
        startDrag(e, 'move', el);
    });
    $ui.on('pointerdown', '[data-frame]', function (e) {
        if ($(e.target).closest('[data-del]').length) { return; }
        var mode = $(e.target).closest('[data-handle]').data('handle') || 'move';
        startDrag(e, mode, selected());
    });
    $(document).on('pointermove', moveDrag).on('pointerup pointercancel', endDrag);
    $ui.on('click', '[data-del]', function () { removeEl(state.sel); });
    $stage.on('pointerdown', function (e) {
        if (!$(e.target).closest('.uc-dz-box, [data-frame]').length && state.sel) { state.sel = null; refresh(); }
    });
    $stage.on('keydown', function (e) {
        var el = selected();
        if (!el || $(e.target).is('input, textarea, select')) { return; }
        var step = e.shiftKey ? 0.05 : 0.01, k = e.key;
        if (k === 'Delete' || k === 'Backspace') { removeEl(el.id); e.preventDefault(); return; }
        var mv = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[k];
        if (mv) { el.cx += mv[0]; el.cy += mv[1]; refresh(); e.preventDefault(); }
    });
    // drag & drop files onto the garment
    $stage.on('dragover', function (e) { e.preventDefault(); $stage.addClass('is-drop'); })
        .on('dragleave drop', function () { $stage.removeClass('is-drop'); })
        .on('drop', function (e) { e.preventDefault(); handleFiles(e.originalEvent.dataTransfer.files); });

    function removeEl(id) {
        state.els = state.els.filter(function (x) { return x.id !== id; });
        if (state.sel === id) { state.sel = null; }
        refresh();
    }

    // ------------------------------------------------------------ panel events
    $root.on('click', '[data-blank]', function () { setBlank(+$(this).data('blank')); });
    $root.on('click', '[data-color]', function () { state.color = String($(this).data('color')); renderColors(); renderSizes(); refresh(); });
    $root.on('click', '[data-side]', function () { setSide($(this).data('side')); });
    $root.on('change', '[data-upload]', function () { handleFiles(this.files); this.value = ''; });
    $root.on('click', '[data-add-text]', addText);
    $root.on('click', '[data-select]', function () { select(+$(this).data('select')); });
    $root.on('click', '[data-remove]', function () { removeEl(+$(this).data('remove')); });
    $root.on('input', '[data-prop=text]', function () {
        var el = selected(); if (!el) { return; }
        el.text = this.value.slice(0, 40) || ' ';
        setTextBox(el, true);
        $root.find('.uc-dz-props-head strong').text(labelOf(el));
        refresh(false); renderLayers(); renderPrice(); saveDraft();
    });
    $root.on('change', '[data-prop=font]', function () {
        var el = selected(); if (!el) { return; }
        el.font = this.value;
        var f = FONTS[el.font];
        var apply = function () { setTextBox(el, true); refresh(); };
        document.fonts && document.fonts.load ? document.fonts.load(f.weight + ' 60px ' + f.css).then(apply, apply) : apply();
    });
    $root.on('input', '[data-prop=color]', function () { var el = selected(); if (el) { el.color = this.value.toUpperCase(); refresh(false); saveDraft(); } });
    $root.on('click', '[data-ink]', function () { var el = selected(); if (el) { el.color = $(this).data('ink'); refresh(); } });
    $root.on('click', '[data-bg-quick]', function () { var el = selected(); if (el) { runQuick(el); } });
    $root.on('click', '[data-bg-ai]', function () { var el = selected(); if (el) { runAi(el); } });
    $root.on('change', '[data-tol]', function () { var el = selected(); if (el) { el.tol = +this.value; runQuick(el); } });
    $root.on('click', '[data-bg-restore]', function () {
        var el = selected(); if (!el || !el.orig) { return; }
        var o = el.orig;
        el.asset = o.asset; el.url = o.url; el.nat = o.nat; el.w = o.w; el.h = o.h; el.cx = o.cx; el.cy = o.cy; el.orig = null; el.bgMode = null;
        loadImage(el.asset, el.url);
        refresh();
    });
    $root.on('click', '[data-tool]', function () {
        var el = selected(); if (!el) { return; }
        var t = $(this).data('tool'), i = state.els.indexOf(el);
        if (t === 'center') { el.cx = 0.5; el.rot = 0; }
        if (t === 'flip') { el.flip = !el.flip; }
        if (t === 'up' && i < state.els.length - 1) { state.els.splice(i, 1); state.els.splice(i + 1, 0, el); }
        if (t === 'down' && i > 0) { state.els.splice(i, 1); state.els.splice(i - 1, 0, el); }
        if (t === 'dup' && canAdd()) { var c = $.extend(true, {}, el, { id: state.uid++, cx: Math.min(1, el.cx + 0.06), cy: Math.min(1, el.cy + 0.06) }); state.els.push(c); state.sel = c.id; }
        if (t === 'side') { el.side = el.side === 'front' ? 'back' : 'front'; setSide(el.side); return; }
        if (t === 'del') { removeEl(el.id); return; }
        refresh();
    });
    $root.on('click', '[data-size-step]', function () {
        var s = String($(this).data('size')), avail = (blank().stock[state.color] || {})[s] || 0;
        var q = Math.max(0, Math.min(Math.min(20, avail), (state.sizes[s] || 0) + +$(this).data('size-step')));
        if (q) { state.sizes[s] = q; } else { delete state.sizes[s]; }
        renderSizes(); renderPrice(); saveDraft();
    });
    $root.on('change', '[data-size-input]', function () {
        var s = String($(this).data('size-input')), avail = (blank().stock[state.color] || {})[s] || 0;
        var q = Math.max(0, Math.min(Math.min(20, avail), parseInt(this.value, 10) || 0));
        if (q) { state.sizes[s] = q; } else { delete state.sizes[s]; }
        renderSizes(); renderPrice(); saveDraft();
    });
    $root.on('click', '[data-jump-summary]', function () { $root.find('[data-summary]')[0].scrollIntoView({ behavior: 'smooth', block: 'start' }); });
    $(window).on('resize', function () { requestDraw(); });

    // ------------------------------------------------------------ saving & ordering
    function payload(extra) {
        var b = blank(), data = $.extend({ product_id: b.id, color: state.color, csrf_token: UC.csrf }, extra || {});
        data.elements = JSON.stringify(state.els.map(function (el) {
            var o = { side: el.side, kind: el.kind, cx: +el.cx.toFixed(4), cy: +el.cy.toFixed(4), w: +el.w.toFixed(4), h: +el.h.toFixed(4), rot: el.rot || 0 };
            if (el.kind === 'text') { o.text = el.text; o.font = el.font; o.color = el.color; } else { o.asset = el.asset; o.flip = !!el.flip; }
            return o;
        }));
        Object.keys(state.sizes).forEach(function (s) { data['sizes[' + s + ']'] = state.sizes[s]; });
        return data;
    }
    function previews() {
        return Promise.all([imagesReady(), fontsReady()]).then(function () {
            var opts = { images: images, fonts: FONTS }, b = blank(), out = {};
            ['front', 'back'].forEach(function (side) {
                var c = renderSide(b.type, side, colorHex(), state.els, 840, opts), url = c.toDataURL('image/webp', 0.88);
                out['preview_' + side] = url.indexOf('data:image/webp') === 0 ? url : c.toDataURL('image/png');
            });
            return out;
        });
    }
    function checkReady() {
        if (!state.els.length) { fail(null, 'Add at least one image or text to your design first.'); return false; }
        if (!qtyTotal()) { fail(null, 'Choose a size and quantity.'); return false; }
        return true;
    }
    $root.on('click', '[data-add-bag]', function () {
        if (!checkReady()) { return; }
        var $b = $(this).addClass('is-loading').prop('disabled', true);
        previews().then(function (pv) {
            return post(payload($.extend({ action: 'add' }, pv)));
        }).then(function (r) {
            $b.removeClass('is-loading').prop('disabled', false);
            if (!r.ok) { fail(null, r.message); return; }
            $('[data-cart-count]').text(r.count).prop('hidden', !r.count);
            clearDraft();
            window.Swal && Swal.fire({ icon: 'success', title: 'Added to your bag', text: r.message, showCancelButton: true, confirmButtonText: 'Checkout', cancelButtonText: 'Keep designing' })
                .then(function (res) { if (res.isConfirmed) { window.location.href = UC.base + 'checkout'; } });
        }, function (x) { $b.removeClass('is-loading').prop('disabled', false); fail(x); });
    });
    $root.on('submit', '[data-quote-form]', function (e) {
        e.preventDefault();
        if (!checkReady()) { return; }
        var $f = $(this), $b = $f.find('[data-submit]').addClass('is-loading').prop('disabled', true), fields = {};
        $f.serializeArray().forEach(function (x) { fields[x.name] = x.value; });
        previews().then(function (pv) {
            return post(payload($.extend({ action: 'request' }, fields, pv)));
        }).then(function (r) {
            if (!r.ok) { $b.removeClass('is-loading').prop('disabled', false); fail(null, r.message); return; }
            clearDraft();
            window.location.href = r.redirect;
        }, function (x) { $b.removeClass('is-loading').prop('disabled', false); fail(x); });
    });

    // ------------------------------------------------------------ draft (per browser) so a refresh never loses work
    var DRAFT = 'uc_design_draft';
    function saveDraft() {
        try {
            localStorage.setItem(DRAFT, JSON.stringify({ blank: blank().id, color: state.color, sizes: state.sizes,
                els: state.els.map(function (el) { var c = $.extend({}, el); return c; }) }));
        } catch (e) { /* storage unavailable */ }
    }
    function clearDraft() { try { localStorage.removeItem(DRAFT); } catch (e) { /* ignore */ } }
    function loadDraft() {
        try {
            var d = JSON.parse(localStorage.getItem(DRAFT) || 'null');
            if (!d || !d.els || !d.els.length) { return; }
            var idx = -1;
            CFG.blanks.forEach(function (b, i) { if (b.id === d.blank) { idx = i; } });
            if (idx < 0 || (CFG.fromSlug && CFG.blanks[CFG.selected].id !== d.blank)) { return; }
            state.blank = idx; state.color = d.color; state.sizes = d.sizes || {};
            state.els = d.els.filter(function (el) { return el.kind === 'text' ? FONTS[el.font] : el.asset; });
            state.els.forEach(function (el) { el.id = state.uid++; if (el.kind === 'image') { loadImage(el.asset, el.url); } });
        } catch (e) { /* ignore broken drafts */ }
    }

    // ------------------------------------------------------------ 3D preview (three.js, loaded on demand)
    var three = null;
    function loadThree() {
        if (window.THREE) { return Promise.resolve(); }
        return new Promise(function (res, rej) {
            var s = document.createElement('script');
            s.src = CFG.three; s.onload = res; s.onerror = function () { rej(new Error('3D preview could not load.')); };
            document.head.appendChild(s);
        });
    }
    /** Distance (in mask pixels) from every inside pixel to the garment edge — makes the fabric puff out. */
    function edgeDistance(type, side, mw, mh) {
        var c = document.createElement('canvas'); c.width = mw; c.height = mh;
        var ctx = c.getContext('2d'); ctx.scale(mw / GW, mh / GH); ctx.fill(P(GARMENTS[type].body[side]));
        var a = ctx.getImageData(0, 0, mw, mh).data, N = mw * mh, d = new Float32Array(N), INF = 1e6, x, y, i;
        for (i = 0; i < N; i++) { d[i] = a[i * 4 + 3] > 127 ? INF : 0; }
        for (y = 0; y < mh; y++) { for (x = 0; x < mw; x++) { i = y * mw + x; if (!d[i]) { continue; }
            var v = d[i]; if (x > 0) { v = Math.min(v, d[i - 1] + 1); } if (y > 0) { v = Math.min(v, d[i - mw] + 1); }
            if (x > 0 && y > 0) { v = Math.min(v, d[i - mw - 1] + 1.414); } if (x < mw - 1 && y > 0) { v = Math.min(v, d[i - mw + 1] + 1.414); }
            if (x === 0 || y === 0 || x === mw - 1) { v = Math.min(v, 1); } d[i] = v; } }
        for (y = mh - 1; y >= 0; y--) { for (x = mw - 1; x >= 0; x--) { i = y * mw + x; if (!d[i]) { continue; }
            var u = d[i]; if (x < mw - 1) { u = Math.min(u, d[i + 1] + 1); } if (y < mh - 1) { u = Math.min(u, d[i + mw] + 1); }
            if (x < mw - 1 && y < mh - 1) { u = Math.min(u, d[i + mw + 1] + 1.414); } if (x > 0 && y < mh - 1) { u = Math.min(u, d[i + mw - 1] + 1.414); }
            if (y === mh - 1) { u = Math.min(u, 1); } d[i] = u; } }
        return d;
    }
    function puffGeometry(type, side) {
        var T = window.THREE, NX = 120, NY = 140, geo = new T.PlaneGeometry(6, 7, NX, NY), pos = geo.attributes.position;
        var mw = 240, mh = 280, dist = edgeDistance(type, side, mw, mh), depth = type === 'hoodie' ? 0.62 : 0.5, cap = 20;
        var sample = function (fx, fy) { // bilinear, so the puff has no steps along the outline
            fx = Math.max(0, Math.min(mw - 1.001, fx)); fy = Math.max(0, Math.min(mh - 1.001, fy));
            var x0 = Math.floor(fx), y0 = Math.floor(fy), tx = fx - x0, ty = fy - y0, k = y0 * mw + x0;
            return (dist[k] * (1 - tx) + dist[k + 1] * tx) * (1 - ty) + (dist[k + mw] * (1 - tx) + dist[k + mw + 1] * tx) * ty;
        };
        for (var i = 0; i < pos.count; i++) {
            var x = pos.getX(i), y = pos.getY(i);
            var dd = Math.max(0, sample((x + 3) / 6 * (mw - 1), (3.5 - y) / 7 * (mh - 1)) - 0.8), t = Math.min(1, dd / cap);
            pos.setZ(i, depth * (1 - (1 - t) * (1 - t)) * (0.8 + 0.2 * Math.min(1, dd / (cap * 4))));
        }
        geo.computeVertexNormals();
        return geo;
    }
    function build3D() {
        var T = window.THREE, $view = $('[data-3d-view]'), w = $view.width(), h = $view.height();
        var renderer;
        try { renderer = new T.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true }); } catch (e) { return null; }
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(w, h);
        renderer.outputEncoding = T.sRGBEncoding;
        $view.empty().append(renderer.domElement);
        var scene = new T.Scene(), camera = new T.PerspectiveCamera(30, w / h, 0.1, 100);
        camera.position.set(0, 0.2, 15.5);
        scene.add(new T.HemisphereLight(0xffffff, 0x9a9a9a, 0.75));
        var key = new T.DirectionalLight(0xffffff, 0.55); key.position.set(3, 4, 8); scene.add(key);
        var back = new T.DirectionalLight(0xffffff, 0.55); back.position.set(-3, 3, -8); scene.add(back);
        var group = new T.Group(); scene.add(group);
        return { T: T, renderer: renderer, scene: scene, camera: camera, group: group, view: $view, rotY: 0.35, target: null, vel: 0, spin: true, raf: 0 };
    }
    function texture(c) {
        var T = window.THREE, t = new T.CanvasTexture(c);
        t.encoding = T.sRGBEncoding;
        t.anisotropy = 4;
        return t;
    }
    function updateModel() {
        var T = three.T, b = blank(), opts = { images: images, fonts: FONTS };
        while (three.group.children.length) { var m = three.group.children.pop(); m.geometry.dispose(); m.material.map && m.material.map.dispose(); m.material.dispose(); }
        ['front', 'back'].forEach(function (side) {
            var tex = texture(renderSide(b.type, side, colorHex(), state.els, 1024, opts));
            var mat = new T.MeshStandardMaterial({ map: tex, alphaTest: 0.5, side: T.DoubleSide, roughness: 0.92, metalness: 0 });
            var mesh = new T.Mesh(puffGeometry(b.type, side), mat);
            if (side === 'back') { mesh.rotation.y = Math.PI; }
            three.group.add(mesh);
        });
    }
    function loop() {
        if (!three) { return; }
        if (three.target !== null) {
            var diff = three.target - three.rotY;
            three.rotY += diff * 0.12;
            if (Math.abs(diff) < 0.002) { three.rotY = three.target; three.target = null; }
        } else if (three.spin) {
            three.rotY += 0.008;
        } else {
            three.rotY += three.vel; three.vel *= 0.94;
        }
        three.group.rotation.y = three.rotY;
        three.renderer.render(three.scene, three.camera);
        three.raf = window.requestAnimationFrame(loop);
    }
    function open3D() {
        var $m = $('[data-3d]').prop('hidden', false);
        $('body').addClass('uc-dz-modal-open');
        Promise.all([loadThree(), imagesReady(), fontsReady()]).then(function () {
            if (!three) { three = build3D(); }
            if (!three) { throw new Error('webgl'); }
            three.renderer.setSize(three.view.width(), three.view.height());
            three.camera.aspect = three.view.width() / three.view.height();
            three.camera.updateProjectionMatrix();
            updateModel();
            three.rotY = state.side === 'back' ? Math.PI + 0.35 : 0.35; three.spin = true; three.target = null;
            window.cancelAnimationFrame(three.raf);
            loop();
        }).catch(function () {
            // No WebGL: show front and back pictures instead.
            var b = blank(), opts = { images: images, fonts: FONTS };
            $('[data-3d-view]').html('<div class="uc-dz-flat"></div>').find('.uc-dz-flat')
                .append(renderSide(b.type, 'front', colorHex(), state.els, 500, opts)).append(renderSide(b.type, 'back', colorHex(), state.els, 500, opts));
        });
        $m.find('[data-close-3d]').trigger('focus');
    }
    function close3D() {
        $('[data-3d]').prop('hidden', true);
        $('body').removeClass('uc-dz-modal-open');
        if (three) { window.cancelAnimationFrame(three.raf); }
    }
    $root.on('click', '[data-open-3d]', open3D);
    $(document).on('click', '[data-close-3d]', close3D);
    $(document).on('keydown', function (e) { if (e.key === 'Escape' && !$('[data-3d]').prop('hidden')) { close3D(); } });
    $(document).on('click', '[data-3d]', function (e) { if (e.target === this) { close3D(); } });
    $(document).on('click', '[data-3d-turn]', function () {
        if (!three) { return; }
        three.spin = false;
        var base = Math.round(three.rotY / (2 * Math.PI)) * 2 * Math.PI;
        three.target = base + (+$(this).data('3d-turn')) * Math.PI / 180;
    });
    $(document).on('click', '[data-3d-spin]', function () { if (three) { three.spin = true; three.target = null; } });
    var rot = null;
    $(document).on('pointerdown', '[data-3d-view] canvas', function (e) { if (!three) { return; } rot = { x: e.clientX, r: three.rotY }; three.spin = false; three.target = null; this.setPointerCapture(e.pointerId); });
    $(document).on('pointermove', '[data-3d-view] canvas', function (e) {
        if (!rot) { return; }
        var nr = rot.r + (e.clientX - rot.x) * 0.012;
        three.vel = nr - three.rotY; three.rotY = nr;
    });
    $(document).on('pointerup pointercancel', '[data-3d-view] canvas', function () { rot = null; });
    $(window).on('resize', function () {
        if (three && !$('[data-3d]').prop('hidden')) {
            three.renderer.setSize(three.view.width(), three.view.height());
            three.camera.aspect = three.view.width() / three.view.height();
            three.camera.updateProjectionMatrix();
        }
    });

    // ------------------------------------------------------------ start
    $root.find('[data-blank-icon]').each(function () {
        var ctx = this.getContext('2d'); ctx.scale(this.width / GW, this.width / GW);
        drawGarment(ctx, $(this).data('blank-icon'), 'front', '#E9E6DF', [], {});
    });
    loadDraft();
    setBlank(state.blank);
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(requestDraw); }
})(jQuery);
