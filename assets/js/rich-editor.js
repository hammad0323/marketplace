/**
 * Minimal contenteditable-based rich text editor. No external dependencies
 * (execCommand is deprecated but still broadly supported and is the
 * simplest way to get a working editor without vendoring a third-party lib).
 *
 * Usage: <div data-rich-editor data-target="#content-field"></div>
 *        <textarea id="content-field" name="content" style="display:none;"></textarea>
 */
(function () {
    'use strict';

    var TOOLBAR = [
        { cmd: 'bold', icon: 'ri-bold', title: 'Bold' },
        { cmd: 'italic', icon: 'ri-italic', title: 'Italic' },
        { cmd: 'underline', icon: 'ri-underline', title: 'Underline' },
        { sep: true },
        { block: 'H2', icon: 'ri-h-1', title: 'Heading' },
        { block: 'H3', icon: 'ri-h-2', title: 'Subheading' },
        { block: 'P', icon: 'ri-paragraph', title: 'Paragraph' },
        { sep: true },
        { cmd: 'insertUnorderedList', icon: 'ri-list-unordered', title: 'Bullet list' },
        { cmd: 'insertOrderedList', icon: 'ri-list-ordered', title: 'Numbered list' },
        { block: 'BLOCKQUOTE', icon: 'ri-double-quotes-l', title: 'Quote' },
        { sep: true },
        { action: 'link', icon: 'ri-link', title: 'Insert link' },
        { action: 'image', icon: 'ri-image-line', title: 'Insert image' },
        { sep: true },
        { cmd: 'undo', icon: 'ri-arrow-go-back-line', title: 'Undo' },
        { cmd: 'redo', icon: 'ri-arrow-go-forward-line', title: 'Redo' },
        { action: 'clear', icon: 'ri-format-clear', title: 'Clear formatting' },
    ];

    // Tags kept on paste (formatting only — no style/class/width/etc. on any
    // of them). Everything else is "unwrapped": its children are kept but
    // the wrapping tag itself (span, div, font, table cells, mso <o:p>...)
    // is dropped, so the underlying bold/italic/text still comes through.
    var PASTE_KEEP_TAGS = {
        B: true, STRONG: true, I: true, EM: true, U: true, S: true, STRIKE: true,
        P: true, UL: true, OL: true, LI: true, H2: true, H3: true, H4: true, BLOCKQUOTE: true,
    };
    // Dropped entirely, content included — never unwrapped.
    var PASTE_DROP_TAGS = {
        SCRIPT: true, STYLE: true, IFRAME: true, OBJECT: true, EMBED: true,
        NOSCRIPT: true, HEAD: true, TITLE: true, META: true, LINK: true, SVG: true, IMG: true,
    };

    function sanitizePastedNode(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return (node.textContent || '').replace(/ /g, ' ');
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
            return '';
        }
        var tag = node.tagName;
        if (PASTE_DROP_TAGS[tag]) {
            return '';
        }
        var inner = Array.prototype.map.call(node.childNodes, sanitizePastedNode).join('');
        if (tag === 'BR') {
            return '<br>';
        }
        if (tag === 'H1') {
            tag = 'H2'; // the editor's own toolbar never produces an H1
        }
        if (tag === 'A') {
            var href = node.getAttribute('href') || '';
            if (!/^(https?:|mailto:)/i.test(href)) {
                return inner;
            }
            return '<a href="' + href.replace(/"/g, '&quot;') + '">' + inner + '</a>';
        }
        if (PASTE_KEEP_TAGS[tag]) {
            var t = tag.toLowerCase();
            return '<' + t + '>' + inner + '</' + t + '>';
        }
        return inner; // unwrap
    }

    function sanitizePastedHtml(html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var out = Array.prototype.map.call(doc.body.childNodes, sanitizePastedNode).join('');
        return out.replace(/<p>(\s|&nbsp;)*<\/p>/gi, '').trim();
    }

    function initEditor(host) {
        var targetSel = host.getAttribute('data-target');
        var target = document.querySelector(targetSel);
        if (!target) return;
        var uploadUrl = host.getAttribute('data-upload-url') || '/ajax/admin-blog-image-upload.php';

        var toolbar = document.createElement('div');
        toolbar.className = 'rich-editor-toolbar';
        TOOLBAR.forEach(function (item) {
            if (item.sep) {
                var sep = document.createElement('span');
                sep.className = 'rich-editor-sep';
                toolbar.appendChild(sep);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rich-editor-btn';
            btn.title = item.title;
            btn.innerHTML = '<i class="' + item.icon + '"></i>';
            btn.addEventListener('mousedown', function (e) {
                // Prevent the editor from losing focus/selection before the command runs.
                e.preventDefault();
            });
            btn.addEventListener('click', function () {
                area.focus();
                if (item.cmd) {
                    document.execCommand(item.cmd, false, null);
                } else if (item.block) {
                    document.execCommand('formatBlock', false, item.block);
                } else if (item.action === 'link') {
                    var url = prompt('Link URL:', 'https://');
                    if (url) document.execCommand('createLink', false, url);
                } else if (item.action === 'image') {
                    triggerImageUpload();
                } else if (item.action === 'clear') {
                    document.execCommand('removeFormat', false, null);
                    document.execCommand('formatBlock', false, 'P');
                }
                sync();
            });
            toolbar.appendChild(btn);
        });

        var area = document.createElement('div');
        area.className = 'rich-editor-area';
        area.contentEditable = 'true';
        area.innerHTML = target.value || '<p></p>';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
        fileInput.style.display = 'none';

        host.innerHTML = '';
        host.appendChild(toolbar);
        host.appendChild(area);
        host.appendChild(fileInput);
        target.style.display = 'none';

        function sync() {
            target.value = area.innerHTML;
            target.dispatchEvent(new Event('change'));
        }
        area.addEventListener('input', sync);
        area.addEventListener('blur', sync);

        // Paste keeps basic formatting (bold/italic/underline/lists/
        // headings/links/quotes) but strips everything else the source
        // carries along — inline styles, classes, spans/divs/tables/fonts,
        // Word's mso-* junk. A straight passthrough of Word/Google Docs
        // paste is what broke the page layout before (fixed widths,
        // white-space:nowrap, absolute positioning riding along on a
        // <span style="...">), so instead of forwarding the source markup
        // as-is, only a fixed allowlist of tags survives and every
        // attribute except a sanitized <a href> is dropped.
        area.addEventListener('paste', function (e) {
            e.preventDefault();
            var cd = e.clipboardData || window.clipboardData;
            var html = cd.getData('text/html');
            var sanitized = html ? sanitizePastedHtml(html) : '';
            if (sanitized) {
                document.execCommand('insertHTML', false, sanitized);
            } else {
                document.execCommand('insertText', false, cd.getData('text/plain'));
            }
            sync();
        });

        function triggerImageUpload() {
            fileInput.value = '';
            fileInput.click();
        }
        fileInput.addEventListener('change', function () {
            if (!fileInput.files.length) return;
            var formData = new FormData();
            formData.append('csrf_token', window.APP.csrfToken);
            formData.append('image', fileInput.files[0]);
            var placeholder = document.createElement('span');
            placeholder.textContent = 'Uploading image…';
            placeholder.className = 'rich-editor-uploading';
            area.appendChild(placeholder);
            fetch(uploadUrl, { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    placeholder.remove();
                    if (res.success) {
                        area.focus();
                        document.execCommand('insertHTML', false, '<img src="' + res.url + '" alt="">');
                    } else {
                        alert(res.message || 'Image upload failed.');
                    }
                    sync();
                })
                .catch(function () {
                    placeholder.remove();
                    alert('Network error uploading image.');
                });
        });

        // Expose a reset hook so the host page can reload content when reused for edit/add.
        host.richEditorSetContent = function (html) {
            area.innerHTML = html || '<p></p>';
            sync();
        };
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-rich-editor]').forEach(initEditor);
    });

    // Exposed so the blog block editor (assets/js/blog-block-editor.js) can turn
    // freshly-inserted [data-rich-editor] hosts into live editors on demand,
    // for blocks added after DOMContentLoaded already fired.
    window.initRichEditor = initEditor;
})();
