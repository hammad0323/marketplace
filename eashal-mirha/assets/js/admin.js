/* Eashal Mirha — admin panel interactions */
(function () {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  // Sidebar on mobile
  const side = $('#sidebar'), bd = $('#sideBackdrop');
  $('#sideToggle') && $('#sideToggle').addEventListener('click', () => { side.classList.toggle('open'); bd.classList.toggle('show'); });
  bd && bd.addEventListener('click', () => { side.classList.remove('open'); bd.classList.remove('show'); });

  // Confirm dangerous actions + row action forms
  document.addEventListener('click', e => {
    const c = e.target.closest('[data-confirm]');
    if (c && !confirm(c.dataset.confirm)) { e.preventDefault(); return; }
    const d = e.target.closest('[data-do]');
    if (d) { const f = document.getElementById(d.getAttribute('form')); if (f && f.elements.do) f.elements.do.value = d.dataset.do; }
    const b = e.target.closest('[data-confirm-bulk]');
    if (b) {
      const f = b.form, sel = f.elements.bulk;
      if (!sel.value) { e.preventDefault(); alert('Choose a bulk action first.'); return; }
      if (!$$('input[name="ids[]"]:checked', f).length) { e.preventDefault(); alert('Select at least one row.'); return; }
      if (sel.value === 'delete' && !confirm('Delete the selected items permanently?')) e.preventDefault();
    }
    const row = e.target.closest('tr.clickable');
    if (row && !e.target.closest('a,button,input')) location.href = row.dataset.href;
  });
  $$('[data-check-all]').forEach(cb => cb.addEventListener('change', () => $$('input[name="ids[]"]', cb.closest('form')).forEach(x => x.checked = cb.checked)));

  // Slug generation
  const slugify = s => s.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const src = $('[data-slug-source]'), tgt = $('[data-slug-target]');
  if (src && tgt) {
    let touched = tgt.value !== '';
    tgt.addEventListener('input', () => { touched = tgt.value !== ''; updSlug(); });
    src.addEventListener('input', () => { if (!touched) { tgt.value = slugify(src.value); updSlug(); } });
    function updSlug() { $$('[data-slug-preview]').forEach(p => p.textContent = slugify(tgt.value || src.value) || 'your-product'); }
  }

  // Character counters + SERP preview
  $$('[data-count]').forEach(inp => {
    const max = +inp.dataset.count;
    const lab = inp.closest('.field').querySelector('span');
    const c = document.createElement('em'); c.className = 'counter'; lab.appendChild(c);
    const upd = () => {
      c.textContent = inp.value.length + ' / ' + max;
      c.classList.toggle('over', inp.value.length > max);
      if (inp.dataset.serp) { const t = $('[data-serp-' + inp.dataset.serp + ']'); if (t && inp.value) t.textContent = inp.value; }
    };
    inp.addEventListener('input', upd); upd();
  });

  // Image previews
  $$('[data-preview]').forEach(inp => inp.addEventListener('change', () => {
    const f = inp.files[0]; if (!f) return;
    const box = inp.closest('.img-pick').querySelector('.img-pick__preview');
    box.innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt="">';
  }));
  $$('[data-multi-preview]').forEach(inp => {
    const out = $('[data-multi-out]');
    const zone = inp.closest('.dropzone');
    inp.addEventListener('change', () => {
      out.innerHTML = '';
      Array.from(inp.files).forEach(f => { const d = document.createElement('div'); d.className = 'img-item'; d.innerHTML = '<img src="' + URL.createObjectURL(f) + '" alt=""><span class="img-del">New</span>'; out.appendChild(d); });
    });
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, () => zone.classList.add('drag')));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, () => zone.classList.remove('drag')));
  });

  // Drag to reorder product images
  $$('[data-sortable]').forEach(grid => {
    let drag = null;
    grid.addEventListener('dragstart', e => { drag = e.target.closest('.img-item'); drag && drag.classList.add('dragging'); });
    grid.addEventListener('dragend', () => { drag && drag.classList.remove('dragging'); drag = null; renumber(); });
    grid.addEventListener('dragover', e => {
      e.preventDefault();
      const over = e.target.closest('.img-item');
      if (!drag || !over || over === drag) return;
      const r = over.getBoundingClientRect();
      grid.insertBefore(drag, (e.clientX - r.left) > r.width / 2 ? over.nextSibling : over);
    });
    function renumber() {
      $$('.img-item', grid).forEach((it, i) => {
        it.querySelector('[data-order]').value = i;
        const m = it.querySelector('.img-main'); if (m) m.remove();
        if (i === 0) { const s = document.createElement('span'); s.className = 'img-main'; s.textContent = 'Main'; it.appendChild(s); }
      });
    }
  });

  // Category → sub-category filtering
  const ps = $('[data-parent-select]'), cs = $('[data-child-select]');
  if (ps && cs) {
    const filter = () => {
      $$('option[data-parent]', cs).forEach(o => { o.hidden = ps.value !== '' && o.dataset.parent !== ps.value; });
      const sel = cs.selectedOptions[0];
      if (sel && sel.hidden) cs.value = '';
    };
    ps.addEventListener('change', filter); filter();
  }

  // Quick-fill buttons
  $$('[data-fill]').forEach(b => b.addEventListener('click', () => { const i = document.querySelector('[name="' + b.dataset.fill + '"]'); if (i) i.value = b.dataset.v; }));

  // Tabs on settings page
  $$('[data-admin-tabs]').forEach(wrap => {
    const links = $$('.atabs a', wrap), input = $('[data-tab-input]', wrap);
    const show = id => {
      links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
      $$('.atab', wrap).forEach(t => t.classList.toggle('active', t.id === id));
      if (input) input.value = id;
    };
    links.forEach(a => a.addEventListener('click', e => { e.preventDefault(); const id = a.getAttribute('href').slice(1); show(id); history.replaceState(null, '', '#' + id); }));
    const h = location.hash.slice(1);
    if (h && $('#' + h, wrap)) show(h);
  });

  // Hero slide live preview
  const pv = $('[data-slide-preview]');
  if (pv) {
    $$('[data-live]').forEach(i => i.addEventListener('input', () => { const t = $('[data-pv="' + i.dataset.live + '"]', pv); if (t) t.textContent = i.value; }));
    const col = $('[data-live-color]'); col && col.addEventListener('input', () => $('.slide-preview__text', pv).style.color = col.value);
    const ov = $('[data-live-overlay]'); ov && ov.addEventListener('input', () => { $('.slide-preview__ov', pv).style.opacity = ov.value; $('[data-range-out]').textContent = Math.round(ov.value * 100) + '%'; });
    const al = $('[data-live-align]'); al && al.addEventListener('change', () => { pv.className = 'slide-preview align-' + al.value; });
    const img = $('input[name=image]'); img && img.addEventListener('change', () => { if (img.files[0]) pv.style.backgroundImage = 'url(' + URL.createObjectURL(img.files[0]) + ')'; });
  }

  // Lightweight rich-text editor
  $$('textarea[data-editor]').forEach(ta => {
    const wrap = document.createElement('div'); wrap.className = 'editor';
    const bar = document.createElement('div'); bar.className = 'editor__bar';
    const area = document.createElement('div'); area.className = 'editor__area'; area.contentEditable = 'true'; area.innerHTML = ta.value;
    const tools = [['B', 'bold', '<b>B</b>'], ['I', 'italic', '<i>I</i>'], ['U', 'underline', '<u>U</u>'], ['H2', 'formatBlock:h2', 'H2'], ['H3', 'formatBlock:h3', 'H3'], ['P', 'formatBlock:p', '¶'],
      ['UL', 'insertUnorderedList', '• List'], ['OL', 'insertOrderedList', '1. List'], ['Link', 'link', 'Link'], ['Clear', 'removeFormat', 'Clear'], ['HTML', 'html', '&lt;/&gt; HTML']];
    tools.forEach(([title, cmd, label]) => {
      const b = document.createElement('button'); b.type = 'button'; b.title = title; b.innerHTML = label;
      b.addEventListener('click', () => {
        if (cmd === 'html') {
          const showing = ta.style.display !== 'none';
          if (showing) { area.innerHTML = ta.value; ta.style.display = 'none'; area.style.display = ''; b.classList.remove('on'); }
          else { ta.value = area.innerHTML; ta.style.display = ''; area.style.display = 'none'; b.classList.add('on'); }
          return;
        }
        area.focus();
        if (cmd === 'link') { const u = prompt('Link URL'); if (u) document.execCommand('createLink', false, u); }
        else if (cmd.indexOf(':') > 0) { const [c, v] = cmd.split(':'); document.execCommand(c, false, v); }
        else document.execCommand(cmd, false, null);
        ta.value = area.innerHTML;
      });
      bar.appendChild(b);
    });
    ta.parentNode.insertBefore(wrap, ta);
    wrap.appendChild(bar); wrap.appendChild(area); wrap.appendChild(ta);
    ta.style.display = 'none';
    area.addEventListener('input', () => ta.value = area.innerHTML);
    ta.form && ta.form.addEventListener('submit', () => { if (ta.style.display === 'none') ta.value = area.innerHTML; });
  });
})();
