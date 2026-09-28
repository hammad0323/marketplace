/* Webanza Tech — admin interactions */
(function () {
  'use strict';
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* Confirm destructive actions */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* Rich text editor */
  $$('[data-rte]').forEach(function (box) {
    var area = box.querySelector('.rte-area');
    var src = box.querySelector('.rte-source');
    var sync = function () { if (!box.classList.contains('source')) src.value = area.innerHTML; };
    area.addEventListener('input', sync);
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    $$('[data-cmd]', box).forEach(function (b) {
      b.addEventListener('click', function () { area.focus(); document.execCommand(b.getAttribute('data-cmd'), false, null); sync(); });
    });
    $$('[data-block]', box).forEach(function (b) {
      b.addEventListener('click', function () { area.focus(); document.execCommand('formatBlock', false, '<' + b.getAttribute('data-block') + '>'); sync(); });
    });
    var link = box.querySelector('[data-link]');
    if (link) link.addEventListener('click', function () {
      var url = window.prompt('Link URL (https://…)');
      if (url) { area.focus(); document.execCommand('createLink', false, url); sync(); }
    });
    var toggle = box.querySelector('[data-source]');
    toggle.addEventListener('click', function () {
      if (box.classList.contains('source')) { area.innerHTML = src.value; box.classList.remove('source'); toggle.classList.remove('on'); }
      else { src.value = area.innerHTML; box.classList.add('source'); toggle.classList.add('on'); }
    });
    var form = box.closest('form');
    if (form) form.addEventListener('submit', sync);
  });

  /* Icon picker */
  $$('.field-icon').forEach(function (field) {
    var input = field.querySelector('[data-icon-input]');
    var preview = field.querySelector('.icon-preview i');
    var grid = field.querySelector('.icon-grid');
    var update = function () { preview.className = input.value; };
    input.addEventListener('input', update);
    field.querySelector('[data-icon-toggle]').addEventListener('click', function () { grid.hidden = !grid.hidden; });
    $$('[data-icon]', grid).forEach(function (b) {
      b.addEventListener('click', function () { input.value = b.getAttribute('data-icon'); update(); grid.hidden = true; });
    });
  });

  /* Image preview before upload */
  $$('.img-field input[type=file]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      var file = inp.files && inp.files[0];
      if (!file) return;
      var box = inp.closest('.img-field').querySelector('.img-preview');
      var img = document.createElement('img');
      img.src = URL.createObjectURL(file);
      box.innerHTML = ''; box.appendChild(img);
    });
  });

  /* Color value label */
  $$('.color-field input[type=color]').forEach(function (c) {
    c.addEventListener('input', function () { c.nextElementSibling.textContent = c.value; });
  });

  /* Drag & drop reordering */
  $$('[data-sortable]').forEach(function (tbody) {
    var dragRow = null;
    $$('tr', tbody).forEach(function (tr) {
      var handle = tr.querySelector('.drag');
      if (!handle) return;
      handle.addEventListener('mousedown', function () { tr.draggable = true; });
      handle.addEventListener('touchstart', function () { tr.draggable = true; }, { passive: true });
      tr.addEventListener('dragstart', function (e) { dragRow = tr; tr.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', ''); } catch (x) {} });
      tr.addEventListener('dragend', function () {
        tr.classList.remove('dragging'); tr.draggable = false; dragRow = null; save();
      });
      tr.addEventListener('dragover', function (e) {
        e.preventDefault();
        if (!dragRow || dragRow === tr) return;
        var r = tr.getBoundingClientRect();
        tbody.insertBefore(dragRow, (e.clientY - r.top) > r.height / 2 ? tr.nextSibling : tr);
      });
    });
    function save() {
      var body = new FormData();
      body.append('do', 'reorder');
      body.append('offset', tbody.getAttribute('data-offset') || '0');
      var csrf = document.querySelector('input[name=csrf]');
      if (csrf) body.append('csrf', csrf.value);
      $$('tr', tbody).forEach(function (tr) { body.append('ids[]', tr.getAttribute('data-id')); });
      fetch(tbody.getAttribute('data-endpoint'), { method: 'POST', body: body, credentials: 'same-origin' });
    }
  });
})();
