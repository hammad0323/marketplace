/* =====================================================================
   Urban Collections — Custom Size Studio
   Draws an interactive mannequin (front + back faces on a 3D-rotatable stage) that re-renders
   the garment from the chosen colour, styles and measurements, and highlights where each
   measurement is taken. No external libraries; plain SVG.
   ===================================================================== */
(function (window, $) {
    'use strict';

    var NS = 'http://www.w3.org/2000/svg';
    var W = 320, H = 660, CX = 160, TOP = 28, FIG = 600; // figure occupies TOP..TOP+FIG
    var PPI_H = 8.4; // horizontal pixels per inch (fixed so widths stay comparable)

    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
    function shade(hex, amt) {
        var h = String(hex || '#888888').replace('#', '');
        if (h.length === 3) { h = h.split('').map(function (c) { return c + c; }).join(''); }
        var n = parseInt(h, 16), r = (n >> 16) + amt, g = ((n >> 8) & 255) + amt, b = (n & 255) + amt;
        return '#' + [r, g, b].map(function (x) { return ('0' + clamp(x, 0, 255).toString(16)).slice(-2); }).join('');
    }
    function luminance(hex) {
        var h = String(hex || '#888').replace('#', ''), n = parseInt(h.length === 3 ? h.replace(/(.)/g, '$1$1') : h, 16);
        return (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
    }
    function el(tag, attrs, parent) {
        var e = document.createElementNS(NS, tag);
        for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) { e.setAttribute(k, attrs[k]); } }
        if (parent) { parent.appendChild(e); }
        return e;
    }
    function p(x, y) { return x.toFixed(1) + ' ' + y.toFixed(1); }
    function mirror(x) { return 2 * CX - x; }

    /** Geometry derived from measurements (body measurements in inches). */
    function geometry(st) {
        var m = st.m, isW = st.gender === 'women';
        var height = clamp(+m.height || (isW ? 63 : 68), 54, 84);
        var ppv = FIG / height;
        var y = function (f) { return TOP + f * FIG; };
        var girth = +(isW ? m.bust : m.chest) || (isW ? 36 : 40);
        var fit = st.styles.fit || 'regular';
        var ease = { slim: 1.5, regular: 3, relaxed: 5 }[fit] || 3;
        var g = {
            isW: isW, ppv: ppv, height: height,
            headY: y(0.055), neckBase: y(0.123), shoulderY: y(0.135), armpitY: y(0.215), bustY: y(0.235),
            waistY: y(isW ? 0.375 : 0.385), hipY: y(0.48), crotchY: y(0.515), kneeY: y(0.72), ankleY: y(0.955),
            neckHalf: clamp((+m.neck || (isW ? 13 : 15.5)) * 1.3, 15, 28),
            shHalf: clamp((+m.shoulder || (isW ? 14.5 : 18)) * 0.5 * PPI_H * 0.86, 44, 84),
            chHalf: clamp((girth + ease) * 0.158 * PPI_H, 34, 92),
            waHalf: clamp(((+m.waist || (isW ? 30 : 34)) + ease) * 0.162 * PPI_H, 28, 92),
            hiHalf: clamp(((+m.hip || (isW ? 40 : 40)) + ease) * 0.162 * PPI_H, 34, 96),
            bodyCh: clamp(girth * 0.155 * PPI_H, 32, 88)
        };
        g.hps = g.neckBase - 4; // high point shoulder, where lengths start
        var len = +(m.qameez_length || m.kameez_length || m.shirt_length) || (st.base === 'shirt' ? 30 : 40);
        g.hemY = clamp(g.hps + len * ppv, g.hipY - 10, g.ankleY - 30);
        var sleeve = +m.sleeve || 24;
        if (isW) {
            var s = st.styles.sleeve || 'full';
            if (s === 'short') { sleeve = Math.min(sleeve, 8); }
            if (s === 'three_quarter') { sleeve = Math.min(sleeve, 17); }
            if (s === 'sleeveless') { sleeve = 0; }
        }
        g.sleeve = sleeve;
        g.cuffY = g.shoulderY + sleeve * ppv * 0.97;
        g.wristY = g.shoulderY + (isW ? 22 : 24.5) * ppv * 0.97;
        var bl = +(m.shalwar_length || m.trouser_length) || 39;
        g.bottomY = clamp(g.waistY + bl * ppv, g.kneeY + 40, g.ankleY + 14);
        g.bottomHalf = clamp((+m.bottom || 8) * PPI_H * 0.36, 12, 70);
        return g;
    }

    function defs(svg, color) {
        var d = el('defs', {}, svg);
        var sh = el('linearGradient', { id: 'ucShade', x1: '0', x2: '1', y1: '0', y2: '0' }, d);
        el('stop', { offset: '0', 'stop-color': '#000', 'stop-opacity': '0.28' }, sh);
        el('stop', { offset: '0.28', 'stop-color': '#fff', 'stop-opacity': '0.10' }, sh);
        el('stop', { offset: '0.55', 'stop-color': '#fff', 'stop-opacity': '0.02' }, sh);
        el('stop', { offset: '1', 'stop-color': '#000', 'stop-opacity': '0.32' }, sh);
        var vs = el('linearGradient', { id: 'ucVShade', x1: '0', x2: '0', y1: '0', y2: '1' }, d);
        el('stop', { offset: '0', 'stop-color': '#fff', 'stop-opacity': '0.06' }, vs);
        el('stop', { offset: '1', 'stop-color': '#000', 'stop-opacity': '0.14' }, vs);
        var mk = el('marker', { id: 'ucArrow', viewBox: '0 0 10 10', refX: '5', refY: '5', markerWidth: '5', markerHeight: '5', orient: 'auto-start-reverse' }, d);
        el('path', { d: 'M0 0 L10 5 L0 10 z', fill: '#B69B63' }, mk);
        var fl = el('filter', { id: 'ucSoft', x: '-20%', y: '-20%', width: '140%', height: '140%' }, d);
        el('feDropShadow', { dx: '0', dy: '6', stdDeviation: '6', 'flood-color': '#000', 'flood-opacity': '0.18' }, fl);
        return d;
    }

    function fabric(group, path, color, extraAttrs) {
        var base = el('path', $.extend({ d: path, fill: color, stroke: shade(color, -50), 'stroke-width': '1.1', 'stroke-linejoin': 'round' }, extraAttrs || {}), group);
        el('path', { d: path, fill: 'url(#ucShade)', 'pointer-events': 'none' }, group);
        el('path', { d: path, fill: 'url(#ucVShade)', 'pointer-events': 'none' }, group);
        return base;
    }

    function seam(group, d, color, dash) {
        return el('path', { d: d, fill: 'none', stroke: shade(color, luminance(color) > 0.55 ? -70 : 60), 'stroke-width': '0.9', 'stroke-dasharray': dash || '0', opacity: '0.75' }, group);
    }

    /** The dress-form mannequin underneath the garment. */
    function mannequin(svg, g) {
        var c = '#D9CDBD', s = '#B9AA95', grp = el('g', { class: 'uc-mq' }, svg);
        el('ellipse', { cx: CX, cy: TOP + FIG + 22, rx: 92, ry: 9, fill: '#000', opacity: '0.08' }, grp);
        el('ellipse', { cx: CX, cy: g.headY, rx: g.isW ? 21 : 23, ry: g.isW ? 27 : 29, fill: c, stroke: s }, grp);
        if (g.isW) { el('path', { d: 'M' + p(CX - 22, g.headY - 6) + ' Q ' + p(CX - 26, g.headY - 34) + ' ' + p(CX, g.headY - 31) + ' Q ' + p(CX + 26, g.headY - 34) + ' ' + p(CX + 22, g.headY - 6) + ' Q ' + p(CX + 28, g.headY + 36) + ' ' + p(CX + 14, g.headY + 44) + ' L ' + p(CX - 14, g.headY + 44) + ' Q ' + p(CX - 28, g.headY + 36) + ' ' + p(CX - 22, g.headY - 6) + 'Z', fill: '#4a3a30', opacity: '0.85' }, grp); }
        el('rect', { x: CX - 10, y: g.headY + 24, width: 20, height: g.neckBase - g.headY - 18, rx: 6, fill: c, stroke: s }, grp);
        // torso + legs (visible where the garment does not cover)
        var sh = g.shHalf - 6, ch = g.bodyCh, wa = g.waHalf - 8, hi = g.hiHalf - 6;
        var body = 'M' + p(CX - 12, g.neckBase) + ' Q ' + p(CX - sh, g.shoulderY - 6) + ' ' + p(CX - sh, g.shoulderY + 10) +
            ' L ' + p(CX - ch, g.armpitY + 8) + ' Q ' + p(CX - wa + 4, g.waistY - 20) + ' ' + p(CX - wa, g.waistY) +
            ' Q ' + p(CX - hi - 2, g.hipY - 10) + ' ' + p(CX - hi, g.hipY) + ' L ' + p(CX - hi * 0.62, g.ankleY) + ' L ' + p(CX - 8, g.ankleY) +
            ' L ' + p(CX - 3, g.crotchY) + ' L ' + p(CX + 3, g.crotchY) + ' L ' + p(CX + 8, g.ankleY) + ' L ' + p(CX + hi * 0.62, g.ankleY) +
            ' L ' + p(CX + hi, g.hipY) + ' Q ' + p(CX + hi + 2, g.hipY - 10) + ' ' + p(CX + wa, g.waistY) + ' Q ' + p(CX + wa - 4, g.waistY - 20) + ' ' + p(CX + ch, g.armpitY + 8) +
            ' L ' + p(CX + sh, g.shoulderY + 10) + ' Q ' + p(CX + sh, g.shoulderY - 6) + ' ' + p(CX + 12, g.neckBase) + ' Z';
        el('path', { d: body, fill: c, stroke: s }, grp);
        // arms
        [-1, 1].forEach(function (k) {
            var x0 = CX + k * (sh + 2), xw = CX + k * (sh + 14);
            el('path', { d: 'M' + p(x0, g.shoulderY + 4) + ' Q ' + p(x0 + k * 16, g.armpitY) + ' ' + p(xw, g.wristY) + ' L ' + p(xw - k * 13, g.wristY) + ' Q ' + p(x0 - k * 4, g.armpitY + 30) + ' ' + p(CX + k * ch, g.armpitY + 10) + ' Z', fill: c, stroke: s }, grp);
            el('ellipse', { cx: xw - k * 6, cy: g.wristY + 13, rx: 7.5, ry: 13, fill: c, stroke: s }, grp);
        });
        // shoes
        [-1, 1].forEach(function (k) {
            el('path', { d: 'M' + p(CX + k * 7, g.ankleY + 6) + ' L ' + p(CX + k * 30, g.ankleY + 6) + ' Q ' + p(CX + k * 40, g.ankleY + 22) + ' ' + p(CX + k * 28, g.ankleY + 26) + ' L ' + p(CX + k * 8, g.ankleY + 26) + ' Z', fill: '#2a2623' }, grp);
        });
    }

    function drawBottom(svg, g, st, color) {
        var type = st.styles.bottom || (st.base === 'shirt' ? 'trouser' : 'shalwar');
        var grp = el('g', { class: 'uc-bottom' }, svg);
        var top = g.waistY - 6, hi = g.hiHalf + 2, by = g.bottomY, bw = g.bottomHalf;
        var legX = hi * 0.5; // centre of each leg
        var d, outer, inner;
        if (type === 'shalwar') {
            outer = hi + 26; inner = bw * 0.9;
            d = 'M' + p(CX - hi, top) + ' C ' + p(CX - outer, g.hipY + 20) + ' ' + p(CX - outer + 4, by - 70) + ' ' + p(CX - legX - inner, by - 14) +
                ' L ' + p(CX - legX - inner + 2, by) + ' L ' + p(CX - legX + inner - 2, by) + ' L ' + p(CX - legX + inner, by - 14) +
                ' C ' + p(CX - 6, by - 90) + ' ' + p(CX - 4, g.crotchY + 50) + ' ' + p(CX, g.crotchY + 40) +
                ' C ' + p(CX + 4, g.crotchY + 50) + ' ' + p(CX + 6, by - 90) + ' ' + p(CX + legX - inner, by - 14) +
                ' L ' + p(CX + legX - inner + 2, by) + ' L ' + p(CX + legX + inner - 2, by) + ' L ' + p(CX + legX + inner, by - 14) +
                ' C ' + p(CX + outer - 4, by - 70) + ' ' + p(CX + outer, g.hipY + 20) + ' ' + p(CX + hi, top) + ' Z';
        } else if (type === 'palazzo') {
            outer = hi + 30;
            d = 'M' + p(CX - hi, top) + ' L ' + p(CX - outer - 10, by) + ' L ' + p(CX - 4, by) + ' L ' + p(CX, g.crotchY + 10) +
                ' L ' + p(CX + 4, by) + ' L ' + p(CX + outer + 10, by) + ' L ' + p(CX + hi, top) + ' Z';
        } else {
            var narrow = type === 'churidar' ? 0.6 : (type === 'pajama' ? 0.85 : 1);
            var k = Math.max(bw * narrow, 10);
            d = 'M' + p(CX - hi, top) + ' Q ' + p(CX - hi - 4, g.hipY) + ' ' + p(CX - hi + 2, g.crotchY + 10) +
                ' L ' + p(CX - legX - k, by) + ' L ' + p(CX - legX + k, by) + ' L ' + p(CX - 2, g.crotchY + 12) +
                ' L ' + p(CX + 2, g.crotchY + 12) + ' L ' + p(CX + legX - k, by) + ' L ' + p(CX + legX + k, by) +
                ' L ' + p(CX + hi - 2, g.crotchY + 10) + ' Q ' + p(CX + hi + 4, g.hipY) + ' ' + p(CX + hi, top) + ' Z';
        }
        fabric(grp, d, color);
        if (type === 'shalwar') { // gathers and folds
            [-1, 1].forEach(function (s) {
                for (var i = 0; i < 3; i++) {
                    var x = CX + s * (legX - 14 + i * 12);
                    seam(grp, 'M' + p(x, g.crotchY + 30 + i * 18) + ' Q ' + p(x + s * 10, by - 60) + ' ' + p(x + s * 2, by - 16), color);
                }
                seam(grp, 'M' + p(CX + s * (legX - bw * 0.9), by - 14) + ' L ' + p(CX + s * (legX + bw * 0.9), by - 14), color);
            });
        } else if (type === 'churidar') {
            [-1, 1].forEach(function (s) {
                for (var i = 0; i < 5; i++) { seam(grp, 'M' + p(CX + s * (legX - 12), by - 8 - i * 9) + ' q ' + (s * 12) + ' 5 ' + (s * 24) + ' 0', color); }
            });
        } else if (type === 'trouser') {
            [-1, 1].forEach(function (s) { seam(grp, 'M' + p(CX + s * legX, g.crotchY + 6) + ' L ' + p(CX + s * legX, by - 2), color, '0'); });
        }
    }

    function drawSleeves(svg, g, st, color, back) {
        if (g.sleeve <= 0) { return; }
        var grp = el('g', { class: 'uc-sleeves' }, svg);
        var cuff = st.styles.cuff || 'plain';
        [-1, 1].forEach(function (k) {
            var sx = CX + k * g.shHalf, ax = CX + k * (g.chHalf - 2);
            var t = clamp((g.cuffY - g.shoulderY) / (g.wristY - g.shoulderY), 0, 1.1);
            var ox = sx + k * (8 + 12 * t), ix = ox - k * (g.isW && st.styles.sleeve === 'short' ? 30 : 22);
            var d = 'M' + p(sx, g.shoulderY) + ' Q ' + p(sx + k * 14, g.armpitY - 10) + ' ' + p(ox, g.cuffY) + ' L ' + p(ix, g.cuffY) +
                ' Q ' + p(ax + k * 2, g.armpitY + 30) + ' ' + p(ax, g.armpitY + 4) + ' Z';
            fabric(grp, d, color);
            seam(grp, 'M' + p(sx + k * 3, g.shoulderY + 6) + ' Q ' + p(sx + k * 10, g.armpitY + 4) + ' ' + p((ox + ix) / 2, g.cuffY - 4), color, '2 3');
            if (cuff !== 'plain' && !g.isW) {
                var hgt = cuff === 'french' ? 20 : 14, rx = cuff === 'round' ? 6 : 1.5;
                var x0 = Math.min(ox, ix) - 1, w = Math.abs(ox - ix) + 2;
                fabric(grp, roundedRect(x0, g.cuffY - hgt, w, hgt, rx), shade(color, -8));
                if (cuff === 'french') { el('circle', { cx: (ox + ix) / 2, cy: g.cuffY - 10, r: 2.4, fill: '#C9A85C', stroke: '#8a6d2e' }, grp); seam(grp, 'M' + p(x0, g.cuffY - 10) + ' L ' + p(x0 + w, g.cuffY - 10), color, '1 2'); }
                if (cuff === 'button' || cuff === 'round' || cuff === 'square') { el('circle', { cx: (ox + ix) / 2 + k * 4, cy: g.cuffY - hgt / 2, r: 1.8, fill: shade(color, luminance(color) > 0.5 ? -80 : 80) }, grp); }
            }
        });
    }

    function roundedRect(x, y, w, h, r) {
        r = Math.min(r, h / 2, w / 2);
        return 'M' + p(x + r, y) + ' L ' + p(x + w - r, y) + ' Q ' + p(x + w, y) + ' ' + p(x + w, y + r) + ' L ' + p(x + w, y + h - r) + ' Q ' + p(x + w, y + h) + ' ' + p(x + w - r, y + h) +
            ' L ' + p(x + r, y + h) + ' Q ' + p(x, y + h) + ' ' + p(x, y + h - r) + ' L ' + p(x, y + r) + ' Q ' + p(x, y) + ' ' + p(x + r, y) + ' Z';
    }

    function drawTop(svg, g, st, color, back) {
        var grp = el('g', { class: 'uc-top' }, svg);
        var shirt = st.base === 'shirt';
        var daman = shirt ? 'round' : (st.styles.daman || 'straight');
        var nh = g.neckHalf, flare = { straight: 6, round: 10, a_line: 30 }[daman] || 6;
        if (g.isW) { flare += 6; }
        var hemHalf = Math.max(g.hiHalf, g.chHalf) + flare, waist = g.isW ? g.waHalf : Math.max(g.waHalf, g.chHalf - 6);
        var sideHem = daman === 'round' ? g.hemY - (shirt ? 26 : 16) : g.hemY;
        var left = 'M' + p(CX - nh, g.neckBase) + ' Q ' + p(CX - g.shHalf * 0.6, g.shoulderY - 6) + ' ' + p(CX - g.shHalf, g.shoulderY) +
            ' L ' + p(CX - g.chHalf, g.armpitY + 4) + ' Q ' + p(CX - waist - 2, g.waistY - 30) + ' ' + p(CX - waist, g.waistY) +
            ' Q ' + p(CX - g.hiHalf - 2, g.hipY - 20) + ' ' + p(CX - g.hiHalf - 1, g.hipY) + ' L ' + p(CX - hemHalf, sideHem);
        var hem = daman === 'round'
            ? ' Q ' + p(CX - hemHalf * 0.55, g.hemY + 8) + ' ' + p(CX, g.hemY + 6) + ' Q ' + p(CX + hemHalf * 0.55, g.hemY + 8) + ' ' + p(CX + hemHalf, sideHem)
            : ' L ' + p(CX + hemHalf, sideHem);
        var right = ' L ' + p(CX + g.hiHalf + 1, g.hipY) + ' Q ' + p(CX + g.hiHalf + 2, g.hipY - 20) + ' ' + p(CX + waist, g.waistY) +
            ' Q ' + p(CX + waist + 2, g.waistY - 30) + ' ' + p(CX + g.chHalf, g.armpitY + 4) + ' L ' + p(CX + g.shHalf, g.shoulderY) +
            ' Q ' + p(CX + g.shHalf * 0.6, g.shoulderY - 6) + ' ' + p(CX + nh, g.neckBase);
        var neckCurve = back ? ' Q ' + p(CX, g.neckBase + 3) + ' ' + p(CX - nh, g.neckBase) : ' Q ' + p(CX, g.neckBase + 10) + ' ' + p(CX - nh, g.neckBase);
        var body = left + hem + right + neckCurve + ' Z';
        fabric(grp, body, color, { filter: 'url(#ucSoft)' });
        // side slits (chaak) on kameez
        if (!shirt) {
            [-1, 1].forEach(function (k) { seam(grp, 'M' + p(CX + k * (g.hiHalf + 2), g.hipY + 14) + ' L ' + p(CX + k * (hemHalf - 2), sideHem - 1), color); });
        }
        // waist shaping for women
        if (g.isW) { [-1, 1].forEach(function (k) { seam(grp, 'M' + p(CX + k * (g.chHalf * 0.45), g.bustY + 8) + ' Q ' + p(CX + k * (g.waHalf * 0.5), g.waistY) + ' ' + p(CX + k * (g.chHalf * 0.5), g.hipY), color, '2 3'); }); }
        if (back) {
            seam(grp, 'M' + p(CX - g.shHalf + 6, g.armpitY - 18) + ' Q ' + p(CX, g.armpitY - 6) + ' ' + p(CX + g.shHalf - 6, g.armpitY - 18), color, '2 3'); // yoke
            if (shirt) { seam(grp, 'M' + p(CX, g.armpitY - 10) + ' L ' + p(CX, g.armpitY + 24), color); }
        } else {
            drawFrontDetails(grp, g, st, color, shirt);
        }
        drawNeck(svg, g, st, color, back);
        return { hemHalf: hemHalf };
    }

    function drawFrontDetails(grp, g, st, color, shirt) {
        var btn = shade(color, luminance(color) > 0.5 ? -90 : 90);
        if (!g.isW || st.styles.neck === 'band') {
            var plEnd = shirt ? g.hemY - 6 : g.neckBase + (g.isW ? 60 : 9.5 * g.ppv);
            if (st.styles.collar !== 'sherwani' || shirt) {
                el('rect', { x: CX - 5, y: g.neckBase + 2, width: 10, height: plEnd - g.neckBase - 2, fill: shade(color, -6), stroke: shade(color, -40), 'stroke-width': '0.7' }, grp);
                var n = shirt ? 7 : 4;
                for (var i = 0; i < n; i++) { el('circle', { cx: CX, cy: g.neckBase + 14 + i * ((plEnd - g.neckBase - 22) / Math.max(1, n - 1)), r: 1.8, fill: btn }, grp); }
            } else {
                for (var j = 0; j < 7; j++) { el('circle', { cx: CX, cy: g.neckBase + 12 + j * 18, r: 2.2, fill: '#C9A85C', stroke: '#8a6d2e', 'stroke-width': '0.5' }, grp); }
                seam(grp, 'M' + p(CX, g.neckBase) + ' L ' + p(CX, g.hemY), color);
            }
        }
        var pocket = st.styles.pocket || 'none';
        if (pocket === 'chest' || pocket === 'both') {
            var px = CX + g.chHalf * 0.32, py = g.armpitY - 4;
            el('path', { d: roundedRect(px, py, 22, 26, shirt ? 2 : 1), fill: shade(color, -4), stroke: shade(color, -45), 'stroke-width': '0.8' }, grp);
            seam(grp, 'M' + p(px, py + 6) + ' L ' + p(px + 22, py + 6), color, '1.5 2');
        }
        if (pocket === 'side' || pocket === 'both') {
            [-1, 1].forEach(function (k) { seam(grp, 'M' + p(CX + k * (g.hiHalf - 12), g.hipY - 18) + ' L ' + p(CX + k * (g.hiHalf - 4), g.hipY + 14), color); });
        }
    }

    function drawNeck(svg, g, st, color, back) {
        var grp = el('g', { class: 'uc-collar' }, svg), nh = g.neckHalf, ny = g.neckBase;
        var mq = '#D9CDBD';
        if (g.isW) {
            var neck = st.styles.neck || 'round';
            var dpt = { round: 16, v_neck: 40, boat: 8, sweetheart: 30, band: 0 }[neck];
            if (back) { dpt = Math.min(dpt, 14); }
            if (neck === 'band') { fabric(grp, roundedRect(CX - nh - 1, ny - 13, nh * 2 + 2, 15, 6), shade(color, -10)); return; }
            var wide = neck === 'boat' ? nh + 18 : nh + 4;
            var d;
            if (neck === 'v_neck' && !back) { d = 'M' + p(CX - wide, ny - 2) + ' L ' + p(CX, ny + dpt) + ' L ' + p(CX + wide, ny - 2) + ' Z'; }
            else if (neck === 'sweetheart' && !back) { d = 'M' + p(CX - wide, ny - 2) + ' Q ' + p(CX - wide + 2, ny + dpt) + ' ' + p(CX, ny + dpt - 6) + ' Q ' + p(CX + wide - 2, ny + dpt) + ' ' + p(CX + wide, ny - 2) + ' Z'; }
            else { d = 'M' + p(CX - wide, ny - 2) + ' Q ' + p(CX, ny + dpt * 1.8) + ' ' + p(CX + wide, ny - 2) + ' Z'; }
            el('path', { d: d, fill: mq, stroke: shade(color, -45), 'stroke-width': '1.4' }, grp);
            el('path', { d: d.replace(' Z', ''), fill: 'none', stroke: shade(color, -20), 'stroke-width': '3', opacity: '0.5' }, grp);
            return;
        }
        var collar = st.base === 'shirt' ? (st.styles.collar || 'classic') : (st.styles.collar || 'band');
        if (collar === 'round') {
            el('path', { d: 'M' + p(CX - nh, ny - 1) + ' Q ' + p(CX, ny + (back ? 4 : 14)) + ' ' + p(CX + nh, ny - 1), fill: 'none', stroke: shade(color, -45), 'stroke-width': '3' }, grp);
            return;
        }
        var bandH = collar === 'sherwani' ? 17 : (collar === 'mandarin' ? 10 : 12);
        // band
        var band = 'M' + p(CX - nh - 2, ny + 2) + ' L ' + p(CX - nh, ny - bandH) + ' Q ' + p(CX, ny - bandH - 5) + ' ' + p(CX + nh, ny - bandH) + ' L ' + p(CX + nh + 2, ny + 2) +
            (back ? ' Q ' + p(CX, ny + 4) + ' ' + p(CX - nh - 2, ny + 2) : ' L ' + p(CX + 3, ny + 6) + ' L ' + p(CX - 3, ny + 6)) + ' Z';
        fabric(grp, band, shade(color, -10));
        if (back || ['band', 'mandarin', 'sherwani'].indexOf(collar) > -1) {
            if (!back && collar !== 'sherwani') { seam(grp, 'M' + p(CX, ny - bandH + 2) + ' L ' + p(CX, ny + 5), color); }
            return;
        }
        // collar points (shirt / classic / cutaway / button-down)
        var spread = collar === 'cutaway' ? 22 : 12, drop = collar === 'cutaway' ? 14 : 24;
        [-1, 1].forEach(function (k) {
            var d = 'M' + p(CX + k * (nh + 3), ny - bandH + 2) + ' L ' + p(CX + k * 2, ny + 4) + ' L ' + p(CX + k * (nh - 6 + spread * 0.3), ny + drop) +
                ' L ' + p(CX + k * (nh + spread * 0.5), ny + drop - 6) + ' Z';
            fabric(grp, d, shade(color, 4));
            if (collar === 'button_down') { el('circle', { cx: CX + k * (nh - 3 + spread * 0.3), cy: ny + drop - 6, r: 1.6, fill: shade(color, luminance(color) > 0.5 ? -90 : 90) }, grp); }
        });
    }

    /** Measurement guide lines for the highlighted key. */
    function drawMeasure(svg, g, st, key, geo) {
        if (!key) { return; }
        var grp = el('g', { class: 'uc-measure' }, svg), gold = '#B69B63';
        var line = function (x1, y1, x2, y2) { el('line', { x1: x1, y1: y1, x2: x2, y2: y2, stroke: gold, 'stroke-width': '2.2', 'stroke-dasharray': '6 4', 'marker-start': 'url(#ucArrow)', 'marker-end': 'url(#ucArrow)' }, grp); };
        var ring = function (cy, rx, ry) { el('ellipse', { cx: CX, cy: cy, rx: rx, ry: ry || 7, fill: 'none', stroke: gold, 'stroke-width': '2.4', 'stroke-dasharray': '6 4' }, grp); };
        var label = function (x, y, text) {
            var t = el('text', { x: x, y: y, 'text-anchor': 'middle', 'font-size': '12', 'font-weight': '700', fill: '#111', 'font-family': 'Manrope, Arial, sans-serif' }, grp);
            t.textContent = text;
            var w = text.length * 6.6 + 14;
            grp.insertBefore(el('rect', { x: x - w / 2, y: y - 13, width: w, height: 18, rx: 9, fill: gold }), t);
        };
        var v = st.m[key], txt = (st.labels[key] || key) + (v ? ' ' + v + '"' : '');
        switch (key) {
            case 'height': line(W - 20, TOP + 2, W - 20, TOP + FIG + 20); label(W - 52, TOP + FIG / 2, txt); break;
            case 'neck': ring(g.neckBase - 6, g.neckHalf + 6, 6); label(CX, g.neckBase - 22, txt); break;
            case 'chest': case 'bust': ring(g.isW ? g.bustY : g.armpitY + 12, g.chHalf + 4, 9); label(CX, (g.isW ? g.bustY : g.armpitY + 12) - 16, txt); break;
            case 'shoulder': line(CX - g.shHalf, g.shoulderY - 10, CX + g.shHalf, g.shoulderY - 10); label(CX, g.shoulderY - 20, txt); break;
            case 'sleeve': line(CX - g.shHalf - 2, g.shoulderY, CX - g.shHalf - 22, g.cuffY || g.wristY); label(CX - g.shHalf - 24, (g.shoulderY + (g.cuffY || g.wristY)) / 2, txt); break;
            case 'armhole': el('ellipse', { cx: CX - g.shHalf + 8, cy: g.shoulderY + 22, rx: 13, ry: 26, fill: 'none', stroke: gold, 'stroke-width': '2.4', 'stroke-dasharray': '5 3' }, grp); label(CX - g.shHalf + 8, g.shoulderY - 8, txt); break;
            case 'qameez_length': case 'kameez_length': case 'shirt_length': line(CX - g.neckHalf - 8, g.hps, CX - g.neckHalf - 8, g.hemY + 4); label(CX - g.neckHalf - 8, (g.hps + g.hemY) / 2, txt); break;
            case 'waist': ring(g.waistY, g.waHalf + 4, 8); label(CX, g.waistY - 15, txt); break;
            case 'hip': ring(g.hipY, g.hiHalf + 6, 8); label(CX, g.hipY - 15, txt); break;
            case 'shalwar_length': case 'trouser_length': line(CX + g.hiHalf + 26, g.waistY, CX + g.hiHalf + 26, g.bottomY); label(CX + g.hiHalf + 26, (g.waistY + g.bottomY) / 2, txt); break;
            case 'bottom': line(CX + g.hiHalf * 0.5 - g.bottomHalf, g.bottomY + 10, CX + g.hiHalf * 0.5 + g.bottomHalf, g.bottomY + 10); label(CX + g.hiHalf * 0.5, g.bottomY + 30, txt); break;
        }
    }

    function render(svg, st, back) {
        while (svg.firstChild) { svg.removeChild(svg.firstChild); }
        var color = st.colorHex || '#E9E1D5';
        var bottomColor = st.base === 'shirt' ? '#2b2f3a' : color;
        defs(svg, color);
        var g = geometry(st);
        mannequin(svg, g);
        drawBottom(svg, g, st, bottomColor);
        drawSleeves(svg, g, st, color, back);
        var geo = drawTop(svg, g, st, color, back);
        if (!back) { drawMeasure(svg, g, st, st.highlight, geo); }
        return g;
    }

    /** Mounts a rotatable stage: front & back faces, drag to turn. */
    function Stage(container, state) {
        this.state = state;
        this.angle = 0;
        container.innerHTML = '';
        var scene = document.createElement('div');
        scene.className = 'uc-stage-scene';
        var rot = document.createElement('div');
        rot.className = 'uc-stage-rot';
        var front = document.createElementNS(NS, 'svg');
        var back = document.createElementNS(NS, 'svg');
        [front, back].forEach(function (s, i) {
            s.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
            s.setAttribute('class', 'uc-face ' + (i ? 'is-back' : 'is-front'));
            s.setAttribute('role', 'img');
            s.setAttribute('aria-label', i ? 'Back view of your garment' : 'Front view of your garment');
            rot.appendChild(s);
        });
        scene.appendChild(rot);
        container.appendChild(scene);
        this.front = front; this.back = back; this.rot = rot;
        var self = this, dragging = false, startX = 0, startA = 0;
        scene.addEventListener('pointerdown', function (e) { dragging = true; startX = e.clientX; startA = self.angle; scene.setPointerCapture(e.pointerId); scene.classList.add('is-dragging'); });
        scene.addEventListener('pointermove', function (e) { if (dragging) { self.setAngle(startA + (e.clientX - startX) * 0.8); } });
        scene.addEventListener('pointerup', function () { dragging = false; scene.classList.remove('is-dragging'); });
        scene.addEventListener('pointercancel', function () { dragging = false; scene.classList.remove('is-dragging'); });
        this.redraw();
    }
    Stage.prototype.setAngle = function (a) {
        this.angle = a;
        this.rot.style.transform = 'rotateY(' + a + 'deg)';
    };
    Stage.prototype.redraw = function () {
        render(this.front, this.state, false);
        render(this.back, this.state, true);
    };

    window.UCStudioDraw = { Stage: Stage, render: render };

    // ================================================================= studio page controller
    $(function () {
        var cfg = window.UC_STUDIO;
        var $root = $('[data-studio]');
        if (!cfg || !$root.length) { return; }
        var labels = {};
        $.each(cfg.measurements, function (k, f) { labels[k] = f.label; });
        var state = { gender: cfg.gender, base: cfg.base, m: {}, styles: {}, colorHex: null, color: null, highlight: null, labels: labels };
        // defaults
        $.each(cfg.options, function (group, opts) {
            var def = opts.filter(function (o) { return +o.is_default; })[0] || opts[0];
            state.styles[group] = def.value_key;
        });
        if (cfg.colors.length) { state.color = cfg.colors[0].name; state.colorHex = cfg.colors[0].hex; }
        var startSize = cfg.chart.M ? 'M' : Object.keys(cfg.chart)[0];
        if (startSize) { state.m = $.extend({}, cfg.chart[startSize]); state.baseSize = startSize; }

        var stage = new Stage($root.find('[data-stage]')[0], state);
        var currency = (window.UC && UC.currency) || 'Rs.';
        var fmt = function (n) { return currency + ' ' + Math.round(n).toLocaleString('en-US'); };

        function syncInputs() {
            $.each(state.m, function (k, v) { $root.find('[data-m="' + k + '"]').val(v); });
            $root.find('[data-size-btn]').removeClass('is-active').filter('[data-size-btn="' + (state.baseSize || '') + '"]').addClass('is-active');
            $root.find('[name=base_size]').val(state.baseSize || '');
        }
        function extra() {
            var x = +cfg.fee || 0;
            $.each(state.styles, function (g, v) { (cfg.options[g] || []).forEach(function (o) { if (o.value_key === v) { x += +o.extra_price; } }); });
            return x;
        }
        function refresh() {
            stage.redraw();
            var ex = extra();
            $root.find('[data-total]').text(fmt(cfg.price + ex));
            $root.find('[data-extra-note]').text(ex > 0 ? 'Includes ' + fmt(ex) + ' for selected custom options' : 'Ready-made price — no extra charge for custom size');
            $root.find('[data-color-name]').text(state.color || '');
            // review summary
            var rows = '<dt>Colour</dt><dd>' + $('<i>').text(state.color || '').html() + '</dd>';
            $.each(state.styles, function (g, v) {
                var o = (cfg.options[g] || []).filter(function (x) { return x.value_key === v; })[0];
                rows += '<dt>' + $('<i>').text(cfg.groups[g] || g).html() + '</dt><dd>' + $('<i>').text(o ? o.label : v).html() + (o && +o.extra_price ? ' <small>(+' + fmt(o.extra_price) + ')</small>' : '') + '</dd>';
            });
            var ms = [];
            $.each(cfg.measurements, function (k, f) { if (state.m[k]) { ms.push(f.label + ' ' + state.m[k] + '"'); } });
            rows += '<dt>Measurements</dt><dd>' + $('<i>').text(ms.join(', ')).html() + '</dd>';
            $root.find('[data-review]').html(rows);
        }
        syncInputs();
        refresh();

        // view controls
        $root.on('click', '[data-view]', function () {
            var v = $(this).data('view');
            $root.find('[data-view]').removeClass('is-active');
            $(this).addClass('is-active');
            if (v === 'spin') {
                var a = stage.angle, start = null;
                var step = function (t) { if (!start) { start = t; } var k = Math.min(1, (t - start) / 2400); stage.setAngle(a + 360 * (1 - Math.pow(1 - k, 3))); if (k < 1) { requestAnimationFrame(step); } };
                requestAnimationFrame(step);
            } else { stage.setAngle(v === 'back' ? 180 : 0); }
        });

        // steps
        $root.on('click', '[data-step-go]', function () {
            var n = $(this).data('step-go');
            $root.find('[data-step]').removeClass('is-open').filter('[data-step="' + n + '"]').addClass('is-open');
            var top = $root.find('[data-step="' + n + '"]').offset().top - 110;
            if (window.innerWidth < 992) { $('html, body').animate({ scrollTop: top }, 300); }
        });
        $root.on('click', '[data-step-toggle]', function () { $(this).closest('[data-step]').toggleClass('is-open'); });

        // sizes
        $root.on('click', '[data-size-btn]', function () {
            var s = $(this).data('size-btn');
            var h = +state.m.height || null;
            state.baseSize = s;
            state.m = $.extend({}, cfg.chart[s]);
            if (h && cfg.chart[s].height) { scaleLengths(h); }
            syncInputs(); refresh();
        });
        function scaleLengths(h) {
            var r = h / cfg.chart[state.baseSize].height;
            ['sleeve', 'qameez_length', 'kameez_length', 'shirt_length', 'shalwar_length', 'trouser_length'].forEach(function (k) { if (state.m[k]) { state.m[k] = Math.round(cfg.chart[state.baseSize][k] * r * 2) / 2; } });
            state.m.height = h;
        }
        // size finder
        $root.on('submit', '[data-size-finder]', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var $f = $(this), ft = +$f.find('[name=ft]').val() || 0, inch = +$f.find('[name=in]').val() || 0, kg = +$f.find('[name=kg]').val() || 0;
            var h = ft * 12 + inch;
            if (h < 54 || h > 84 || kg < 30 || kg > 200) { window.Swal && Swal.fire({ text: 'Please enter a realistic height and weight.', icon: 'info' }); return; }
            $.getJSON(UC.base + 'ajax/studio.php', { action: 'recommend', product_id: cfg.productId, height: h, weight: kg, fit: $f.find('[name=fit]').val() }).done(function (r) {
                if (!r.ok) { return; }
                state.baseSize = r.size; state.m = $.extend({}, r.measurements);
                syncInputs(); refresh();
                $f.find('[data-finder-result]').html('<strong>Suggested size: ' + $('<i>').text(r.size).html() + '</strong><br>' + $('<i>').text(r.note).html()).prop('hidden', false);
            });
        });
        // saved profiles
        $root.on('change', '[data-profile]', function () {
            var data = $(this).find(':selected').data('m');
            if (!data) { return; }
            state.m = $.extend({}, state.m, data); state.baseSize = '';
            syncInputs(); refresh();
        });

        // colours & styles
        $root.on('click', '[data-color]', function () {
            state.color = $(this).data('color'); state.colorHex = $(this).data('hex');
            $root.find('[data-color]').removeClass('is-active').attr('aria-pressed', 'false');
            $(this).addClass('is-active').attr('aria-pressed', 'true');
            refresh();
        });
        $root.on('click', '[data-style]', function () {
            var g = $(this).data('style-group');
            state.styles[g] = $(this).data('style');
            $root.find('[data-style-group="' + g + '"]').removeClass('is-active').attr('aria-pressed', 'false');
            $(this).addClass('is-active').attr('aria-pressed', 'true');
            if (g === 'sleeve' || g === 'neck' || g === 'collar') { stage.setAngle(0); }
            refresh();
        });

        // measurements
        $root.on('focus', '[data-m]', function () {
            state.highlight = $(this).data('m');
            stage.setAngle(0);
            $root.find('.uc-mfield').removeClass('is-focus');
            $(this).closest('.uc-mfield').addClass('is-focus');
            refresh();
        });
        $root.on('input change', '[data-m]', function () {
            var k = $(this).data('m'), v = parseFloat(this.value);
            if (!isNaN(v)) { state.m[k] = v; state.baseSize = ''; $root.find('[data-size-btn]').removeClass('is-active'); refresh(); }
            var f = cfg.measurements[k];
            $(this).closest('.uc-mfield').toggleClass('is-invalid', isNaN(v) || v < f.min || v > f.max);
        });
        $root.on('click', '[data-m-step]', function () {
            var $in = $(this).closest('.uc-mfield').find('[data-m]');
            var v = (parseFloat($in.val()) || 0) + parseFloat($(this).data('m-step'));
            $in.val(Math.round(v * 2) / 2).trigger('change').trigger('focus');
        });
        $root.on('click', '[data-m-help]', function () {
            var $f = $(this).closest('.uc-mfield');
            $f.toggleClass('show-help');
            $f.find('[data-m]').trigger('focus');
        });

        // add to bag
        $root.on('submit', '[data-studio-form]', function (e) {
            e.preventDefault();
            if (e.target !== this) { return; }
            var $form = $(this), bad = [];
            $.each(cfg.measurements, function (k, f) { var v = +state.m[k]; if (!(v >= f.min && v <= f.max)) { bad.push(f.label); } });
            if (bad.length) {
                $root.find('[data-step]').removeClass('is-open').filter('[data-step="3"]').addClass('is-open');
                window.Swal && Swal.fire({ icon: 'warning', title: 'Check your measurements', text: 'Please review: ' + bad.join(', ') + '.' });
                return;
            }
            var data = { action: 'add', product_id: cfg.productId, color: state.color, base_size: state.baseSize || '', qty: $form.find('[name=qty]').val(),
                notes: $form.find('[name=notes]').val(), save_profile: $form.find('[name=save_profile]').is(':checked') ? 1 : 0, profile_name: $form.find('[name=profile_name]').val() };
            $.each(state.styles, function (g, v) { data['style[' + g + ']'] = v; });
            $.each(state.m, function (k, v) { data['m[' + k + ']'] = v; });
            var $b = $form.find('[type=submit]').addClass('is-loading');
            $.post(UC.base + 'ajax/studio.php', data).done(function (r) {
                if (!r.ok) { window.Swal && Swal.fire({ icon: 'warning', html: (r.errors || [r.message]).map(function (x) { return $('<i>').text(x).html(); }).join('<br>') }); return; }
                $('[data-cart-count]').text(r.count).prop('hidden', !r.count);
                window.Swal && Swal.fire({
                    icon: 'success', title: 'Added to your bag', text: r.message, showCancelButton: true,
                    confirmButtonText: 'Checkout', cancelButtonText: 'Keep shopping'
                }).then(function (res) { if (res.isConfirmed) { window.location.href = UC.base + 'checkout'; } });
            }).fail(function (x) {
                window.Swal && Swal.fire({ icon: 'error', text: (x.responseJSON && x.responseJSON.message) || 'Something went wrong. Please try again.' });
            }).always(function () { $b.removeClass('is-loading'); });
        });
    });

    // ================================================================= measuring guide (mannequin + cards)
    $(function () {
        $('[data-measure-guide]').each(function () {
            var $g = $(this), cfg = $g.data('config');
            var labels = {};
            $.each(cfg.measurements, function (k, f) { labels[k] = f.label; });
            var state = { gender: cfg.gender, base: cfg.base, m: $.extend({}, cfg.chart), styles: cfg.styles || {}, colorHex: cfg.color || '#E9E1D5', highlight: Object.keys(cfg.measurements)[1], labels: labels };
            var stage = new Stage($g.find('[data-stage]')[0], state);
            $g.on('click mouseenter focusin', '[data-guide-key]', function () {
                state.highlight = $(this).data('guide-key');
                $g.find('[data-guide-key]').removeClass('is-active');
                $(this).addClass('is-active');
                stage.redraw();
            });
            $g.find('[data-guide-key]').first().next().addClass('is-active');
        });
    });
})(window, jQuery);
