/**
 * Block-based blog post editor. Each post is an ordered array of blocks
 * (`window.blogBlockEditor.getBlocks()`), reordered with up/down (no drag
 * library in this codebase) and rendered live into #blog-blocks-list.
 * `window.blogBlockEditor.load(blocks)` seeds it for Edit; `.reset()` clears
 * it for Add. assets/js/admin-blog.js calls both and reads getBlocks() on
 * submit.
 *
 * Requires window.ALL_DOCTORS = [{id,name,spec}], window.ALL_MEDICINES =
 * [{id,name}], and rich-editor.js loaded first (for window.initRichEditor).
 */
(function ($) {
    'use strict';

    var blocks = [];
    var uidCounter = 0;
    var $list = $('#blog-blocks-list');
    var $empty = $('#blog-blocks-empty');

    function uid() { return 'blk' + (uidCounter++); }

    var BLOCK_META = {
        heading: { label: 'Heading', icon: 'ri-h-1' },
        richtext: { label: 'Text', icon: 'ri-text' },
        image: { label: 'Image', icon: 'ri-image-line' },
        gallery: { label: 'Image Gallery / Carousel', icon: 'ri-gallery-line' },
        video: { label: 'Video', icon: 'ri-video-line' },
        map: { label: 'Map', icon: 'ri-map-pin-line' },
        doctor: { label: 'Doctor Card', icon: 'ri-user-heart-line' },
        doctor_carousel: { label: 'Featured Doctors Carousel', icon: 'ri-team-line' },
        medicine: { label: 'Medicine Card', icon: 'ri-capsule-line' },
        comparison_table: { label: 'Doctor Comparison Table', icon: 'ri-table-line' },
        fact_box: { label: 'Quick Facts Box', icon: 'ri-file-list-3-line' },
        callout: { label: 'Highlight / Callout Box', icon: 'ri-lightbulb-line' },
        faq: { label: 'FAQ Accordion', icon: 'ri-question-line' },
        toc: { label: 'Table of Contents', icon: 'ri-list-check-2' },
        quote: { label: 'Quote', icon: 'ri-double-quotes-l' },
        divider: { label: 'Divider', icon: 'ri-separator' },
    };

    function newBlock(type) {
        var base = { _uid: uid(), type: type };
        switch (type) {
            case 'heading': return $.extend(base, { level: 'h2', text: '' });
            case 'richtext': return $.extend(base, { html: '' });
            case 'image': return $.extend(base, { url: '', alt: '', caption: '' });
            case 'gallery': return $.extend(base, { images: [] });
            case 'video': return $.extend(base, { url: '' });
            case 'map': return $.extend(base, { address: '' });
            case 'doctor': return $.extend(base, { doctor_id: '' });
            case 'doctor_carousel': return $.extend(base, { heading: 'Featured Doctors', doctor_ids: [] });
            case 'medicine': return $.extend(base, { medicine_id: '' });
            case 'comparison_table': return $.extend(base, { heading: 'Compare Doctors', doctor_ids: [] });
            case 'fact_box': return $.extend(base, { heading: 'Quick Facts', facts: [{ label: '', value: '' }] });
            case 'callout': return $.extend(base, { html: '', style: 'info' });
            case 'faq': return $.extend(base, { heading: 'Frequently Asked Questions', items: [{ q: '', a: '' }] });
            case 'toc': return $.extend(base, { heading: 'Table of Contents' });
            case 'quote': return $.extend(base, { text: '', cite: '' });
            case 'divider': return base;
        }
        return base;
    }

    function updateEmptyState() {
        $empty.toggle(blocks.length === 0);
    }

    // ---- Small reusable field builders (each returns a jQuery element and wires its own state sync) ----
    function textField(label, value, onChange, placeholder) {
        var $wrap = $('<div class="form-group" style="margin-bottom:12px;"></div>');
        if (label) $wrap.append($('<label class="form-label" style="font-size:12.5px;"></label>').text(label));
        var $input = $('<input type="text" class="form-control">').val(value || '').attr('placeholder', placeholder || '');
        $input.on('input', function () { onChange($input.val()); });
        $wrap.append($input);
        return $wrap;
    }
    function selectField(label, value, options, onChange) {
        var $wrap = $('<div class="form-group" style="margin-bottom:12px;"></div>');
        if (label) $wrap.append($('<label class="form-label" style="font-size:12.5px;"></label>').text(label));
        var $select = $('<select class="form-control"></select>');
        options.forEach(function (opt) {
            $select.append($('<option></option>').val(opt.value).text(opt.text));
        });
        $select.val(value || '');
        $select.on('change', function () { onChange($select.val()); });
        $wrap.append($select);
        return $wrap;
    }
    function doctorOptions() {
        return (window.ALL_DOCTORS || []).map(function (d) {
            return { value: d.id, text: d.name + (d.spec ? ' — ' + d.spec : '') };
        });
    }
    function medicineOptions() {
        return (window.ALL_MEDICINES || []).map(function (m) {
            return { value: m.id, text: m.name };
        });
    }
    function doctorCheckboxPicker(selectedIds, onChange) {
        var $wrap = $('<div class="form-group" style="margin-bottom:12px;"></div>');
        $wrap.append('<label class="form-label" style="font-size:12.5px;">Select Doctors</label>');
        var $box = $('<div style="max-height:160px;overflow-y:auto;border:1px solid var(--color-border);border-radius:10px;padding:8px 12px;"></div>');
        (window.ALL_DOCTORS || []).forEach(function (d) {
            var checked = (selectedIds || []).indexOf(d.id) !== -1 || (selectedIds || []).map(String).indexOf(String(d.id)) !== -1;
            var $row = $('<label class="checkbox-row" style="padding:5px 0;"></label>');
            var $cb = $('<input type="checkbox">').val(d.id).prop('checked', checked);
            $cb.on('change', function () {
                var ids = [];
                $box.find('input:checked').each(function () { ids.push(parseInt($(this).val(), 10)); });
                onChange(ids);
            });
            $row.append($cb).append(' ' + d.name + (d.spec ? ' (' + d.spec + ')' : ''));
            $box.append($row);
        });
        if (!(window.ALL_DOCTORS || []).length) $box.append('<p style="font-size:13px;color:var(--color-text-muted);margin:0;">No verified doctors yet.</p>');
        $wrap.append($box);
        return $wrap;
    }
    function richTextField(label, html, onChange) {
        var $wrap = $('<div class="form-group" style="margin-bottom:12px;"></div>');
        if (label) $wrap.append($('<label class="form-label" style="font-size:12.5px;"></label>').text(label));
        var editorUid = uid();
        var $host = $('<div data-rich-editor data-target="#rt-' + editorUid + '" data-upload-url="/ajax/medicine-image-upload.php"></div>');
        var $textarea = $('<textarea id="rt-' + editorUid + '" style="display:none;"></textarea>').val(html || '');
        $wrap.append($host).append($textarea);
        setTimeout(function () {
            if (window.initRichEditor) {
                window.initRichEditor($host[0]);
                if ($host[0].richEditorSetContent) $host[0].richEditorSetContent(html || '<p></p>');
            }
            $textarea.on('change', function () { onChange($textarea.val()); });
        }, 0);
        return $wrap;
    }
    function imageUploadField(label, url, onChange) {
        var $wrap = $('<div class="form-group" style="margin-bottom:12px;"></div>');
        if (label) $wrap.append($('<label class="form-label" style="font-size:12.5px;"></label>').text(label));
        var $preview = $('<div style="margin-bottom:8px;"></div>');
        function renderPreview(u) {
            $preview.empty();
            if (u) $preview.append($('<img>').attr('src', u).css({ maxHeight: '110px', borderRadius: '8px', display: 'block' }));
        }
        renderPreview(url);
        var $file = $('<input type="file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">');
        var $status = $('<span style="font-size:12px;color:var(--color-text-muted);"></span>');
        $file.on('change', function () {
            if (!$file[0].files.length) return;
            $status.text('Uploading…');
            var fd = new FormData();
            fd.append('csrf_token', window.APP.csrfToken);
            fd.append('image', $file[0].files[0]);
            $.ajax({ url: '/ajax/medicine-image-upload.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (res) {
                    $status.text('');
                    if (res.success) { renderPreview(res.url); onChange(res.url); }
                    else showToast('error', 'Upload failed', res.message);
                }).fail(function () { $status.text(''); showToast('error', 'Network error', 'Please try again.'); });
        });
        $wrap.append($preview).append($file).append($status);
        return $wrap;
    }

    // ---- Per-type body builders. Each mutates `block` directly via closures. ----
    var BODY_BUILDERS = {
        heading: function (block, $body) {
            $body.append(selectField('Level', block.level, [{ value: 'h2', text: 'Heading (H2)' }, { value: 'h3', text: 'Subheading (H3)' }], function (v) { block.level = v; }));
            $body.append(textField('Text', block.text, function (v) { block.text = v; }, 'e.g. Services Offered'));
        },
        richtext: function (block, $body) {
            $body.append(richTextField(null, block.html, function (v) { block.html = v; }));
        },
        image: function (block, $body) {
            $body.append(imageUploadField('Image', block.url, function (v) { block.url = v; }));
            $body.append(textField('Alt text', block.alt, function (v) { block.alt = v; }, 'Describes the image for accessibility/SEO'));
            $body.append(textField('Caption (optional)', block.caption, function (v) { block.caption = v; }));
        },
        gallery: function (block, $body) {
            var $items = $('<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:10px;"></div>');
            function renderItems() {
                $items.empty();
                block.images.forEach(function (img, i) {
                    var $item = $('<div style="position:relative;"></div>');
                    $item.append($('<img>').attr('src', img.url).css({ width: '90px', height: '90px', objectFit: 'cover', borderRadius: '8px' }));
                    var $rm = $('<button type="button" class="btn-icon" style="position:absolute;top:-8px;right:-8px;width:22px;height:22px;background:#DC2626;color:#fff;"><i class="ri-close-line" style="font-size:13px;"></i></button>');
                    $rm.on('click', function () { block.images.splice(i, 1); renderItems(); });
                    $item.append($rm);
                    $items.append($item);
                });
            }
            renderItems();
            var $file = $('<input type="file" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" multiple>');
            $file.on('change', function () {
                Array.prototype.forEach.call($file[0].files, function (file) {
                    var fd = new FormData();
                    fd.append('csrf_token', window.APP.csrfToken);
                    fd.append('image', file);
                    $.ajax({ url: '/ajax/medicine-image-upload.php', type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                        .done(function (res) {
                            if (res.success) { block.images.push({ url: res.url, alt: '' }); renderItems(); }
                            else showToast('error', 'Upload failed', res.message);
                        });
                });
            });
            $body.append('<label class="form-label" style="font-size:12.5px;">Images</label>').append($items).append($file);
        },
        video: function (block, $body) {
            $body.append(textField('YouTube or Vimeo URL', block.url, function (v) { block.url = v; }, 'https://www.youtube.com/watch?v=...'));
        },
        map: function (block, $body) {
            $body.append(textField('Address', block.address, function (v) { block.address = v; }, 'e.g. Clifton, Karachi, Pakistan'));
        },
        doctor: function (block, $body) {
            $body.append(selectField('Doctor', block.doctor_id, [{ value: '', text: 'Select a doctor…' }].concat(doctorOptions()), function (v) { block.doctor_id = v; }));
        },
        doctor_carousel: function (block, $body) {
            $body.append(textField('Section Heading', block.heading, function (v) { block.heading = v; }, 'e.g. Best Psychiatrists in Karachi'));
            $body.append(doctorCheckboxPicker(block.doctor_ids, function (ids) { block.doctor_ids = ids; }));
        },
        medicine: function (block, $body) {
            $body.append(selectField('Medicine', block.medicine_id, [{ value: '', text: 'Select a medicine…' }].concat(medicineOptions()), function (v) { block.medicine_id = v; }));
        },
        comparison_table: function (block, $body) {
            $body.append(textField('Table Heading', block.heading, function (v) { block.heading = v; }, 'e.g. Compare Top Psychiatrists'));
            $body.append(doctorCheckboxPicker(block.doctor_ids, function (ids) { block.doctor_ids = ids; }));
            $body.append('<p style="font-size:12px;color:var(--color-text-muted);margin-top:-4px;">Columns (specialization, experience, fees, rating) are generated automatically from each doctor’s profile.</p>');
        },
        fact_box: function (block, $body) {
            $body.append(textField('Box Heading', block.heading, function (v) { block.heading = v; }, 'e.g. Quick Facts'));
            var $rows = $('<div style="margin-bottom:10px;"></div>');
            function renderRows() {
                $rows.empty();
                block.facts.forEach(function (f, i) {
                    var $row = $('<div style="display:flex;gap:8px;margin-bottom:8px;"></div>');
                    var $label = $('<input type="text" class="form-control" placeholder="Label, e.g. City">').val(f.label);
                    var $value = $('<input type="text" class="form-control" placeholder="Value, e.g. Karachi">').val(f.value);
                    $label.on('input', function () { f.label = $label.val(); });
                    $value.on('input', function () { f.value = $value.val(); });
                    var $rm = $('<button type="button" class="btn-icon btn-sm" style="flex-shrink:0;"><i class="ri-delete-bin-line"></i></button>');
                    $rm.on('click', function () { block.facts.splice(i, 1); renderRows(); });
                    $row.append($label).append($value).append($rm);
                    $rows.append($row);
                });
            }
            renderRows();
            var $add = $('<button type="button" class="btn btn-outline btn-sm"><i class="ri-add-line"></i> Add Fact</button>');
            $add.on('click', function () { block.facts.push({ label: '', value: '' }); renderRows(); });
            $body.append('<label class="form-label" style="font-size:12.5px;">Facts</label>').append($rows).append($add);
        },
        callout: function (block, $body) {
            $body.append(selectField('Style', block.style, [{ value: 'info', text: 'Info (teal)' }, { value: 'success', text: 'Success (green)' }, { value: 'warning', text: 'Warning (amber)' }], function (v) { block.style = v; }));
            $body.append(richTextField('Text', block.html, function (v) { block.html = v; }));
        },
        faq: function (block, $body) {
            $body.append(textField('Section Heading', block.heading, function (v) { block.heading = v; }, 'e.g. Frequently Asked Questions'));
            var $rows = $('<div style="margin-bottom:10px;"></div>');
            function renderRows() {
                $rows.empty();
                block.items.forEach(function (f, i) {
                    var $row = $('<div class="card" style="padding:12px;margin-bottom:8px;"></div>');
                    var $q = $('<input type="text" class="form-control" placeholder="Question" style="margin-bottom:8px;">').val(f.q);
                    var $a = $('<textarea class="form-control" rows="2" placeholder="Answer"></textarea>').val(f.a);
                    $q.on('input', function () { f.q = $q.val(); });
                    $a.on('input', function () { f.a = $a.val(); });
                    var $rm = $('<button type="button" class="btn-outline btn-sm" style="margin-top:8px;"><i class="ri-delete-bin-line"></i> Remove</button>');
                    $rm.on('click', function () { block.items.splice(i, 1); renderRows(); });
                    $row.append($q).append($a).append($rm);
                    $rows.append($row);
                });
            }
            renderRows();
            var $add = $('<button type="button" class="btn btn-outline btn-sm"><i class="ri-add-line"></i> Add Question</button>');
            $add.on('click', function () { block.items.push({ q: '', a: '' }); renderRows(); });
            $body.append('<label class="form-label" style="font-size:12.5px;">Questions</label>').append($rows).append($add);
        },
        toc: function (block, $body) {
            $body.append(textField('Box Heading', block.heading, function (v) { block.heading = v; }));
            $body.append('<p style="font-size:12px;color:var(--color-text-muted);">Automatically lists every Heading block in this post, in order — add Heading blocks above/below and they’ll show up here.</p>');
        },
        quote: function (block, $body) {
            $body.append(textField('Quote', block.text, function (v) { block.text = v; }));
            $body.append(textField('Attribution (optional)', block.cite, function (v) { block.cite = v; }, 'e.g. Dr. Sarah Chen, Cardiologist'));
        },
        divider: function () {},
    };

    function renderBlockCard(block) {
        var meta = BLOCK_META[block.type] || { label: block.type, icon: 'ri-file-line' };
        var $card = $('<div class="card blog-editor-block" style="padding:16px;margin-bottom:12px;"></div>').attr('data-block-uid', block._uid);
        var $head = $('<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;"></div>');
        $head.append('<strong style="font-size:13px;display:flex;align-items:center;gap:8px;"><i class="' + meta.icon + '"></i> ' + meta.label + '</strong>');
        var $actions = $('<div style="display:flex;gap:4px;"></div>');
        var $up = $('<button type="button" class="btn-icon btn-sm" title="Move up"><i class="ri-arrow-up-line"></i></button>');
        var $down = $('<button type="button" class="btn-icon btn-sm" title="Move down"><i class="ri-arrow-down-line"></i></button>');
        var $del = $('<button type="button" class="btn-icon btn-sm" title="Delete"><i class="ri-delete-bin-line"></i></button>');
        $up.on('click', function () { moveBlock(block._uid, -1); });
        $down.on('click', function () { moveBlock(block._uid, 1); });
        $del.on('click', function () { if (confirm('Remove this block?')) removeBlock(block._uid); });
        $actions.append($up).append($down).append($del);
        $head.append($actions);
        var $body = $('<div></div>');
        (BODY_BUILDERS[block.type] || function () {})(block, $body);
        $card.append($head).append($body);
        return $card;
    }

    function appendBlock(block) {
        blocks.push(block);
        $list.append(renderBlockCard(block));
        updateEmptyState();
    }

    function removeBlock(blockUid) {
        var idx = blocks.findIndex(function (b) { return b._uid === blockUid; });
        if (idx === -1) return;
        blocks.splice(idx, 1);
        $list.find('[data-block-uid="' + blockUid + '"]').remove();
        updateEmptyState();
    }

    function moveBlock(blockUid, dir) {
        var idx = blocks.findIndex(function (b) { return b._uid === blockUid; });
        var newIdx = idx + dir;
        if (idx === -1 || newIdx < 0 || newIdx >= blocks.length) return;
        var tmp = blocks[idx]; blocks[idx] = blocks[newIdx]; blocks[newIdx] = tmp;
        var $card = $list.find('[data-block-uid="' + blockUid + '"]');
        if (dir < 0) $card.insertBefore($card.prev()); else $card.insertAfter($card.next());
    }

    function reset() {
        blocks = [];
        $list.empty();
        updateEmptyState();
    }

    function load(savedBlocks) {
        reset();
        (savedBlocks || []).forEach(function (b) {
            var block = $.extend({}, b, { _uid: uid() });
            appendBlock(block);
        });
    }

    function getBlocksJson() {
        return JSON.stringify(blocks.map(function (b) {
            var copy = $.extend({}, b);
            delete copy._uid;
            return copy;
        }));
    }

    // #add-block-btn opens/closes #add-block-menu via the site-wide
    // [data-dropdown-trigger] mechanism in main.js (handles outside-click,
    // aria-expanded, and closing other open menus) — only the block-type
    // click itself is this file's concern.
    $('#add-block-menu [data-block-type]').on('click', function (e) {
        e.preventDefault();
        appendBlock(newBlock($(this).data('block-type')));
        $('#add-block-menu').removeClass('open');
    });

    window.blogBlockEditor = { reset: reset, load: load, getBlocksJson: getBlocksJson, newBlock: newBlock };
    updateEmptyState();
})(jQuery);
