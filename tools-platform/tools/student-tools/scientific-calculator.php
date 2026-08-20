<div class="mb-3">
  <input type="text" class="form-control text-end" id="tpSciDisplay" value="0" readonly style="font-size:1.5rem;font-family:monospace;">
</div>
<div class="row g-1">
  <?php
  $buttons = [
    ['sin(', 'cos(', 'tan(', 'log(', 'ln(' ],
    ['(', ')', 'sqrt(', '^', '/' ],
    ['7', '8', '9', '×', '×√'],
    ['4', '5', '6', '-', 'π'],
    ['1', '2', '3', '+', 'e'],
    ['0', '.', '%', '=', 'C'],
  ];
  foreach ($buttons as $row): ?>
    <div class="col-12 d-flex gap-1 mb-1">
      <?php foreach ($row as $btn): ?>
        <button type="button" class="btn btn-outline-secondary flex-fill tp-sci-btn" data-val="<?= e($btn) ?>"><?= e($btn) ?></button>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
<script>
let tpSciExpr = '';
function tpSciUpdate() { document.getElementById('tpSciDisplay').value = tpSciExpr || '0'; }
document.querySelectorAll('.tp-sci-btn').forEach((btn) => {
  btn.addEventListener('click', function () {
    const val = this.dataset.val;
    try {
      if (val === 'C') { tpSciExpr = ''; tpSciUpdate(); return; }
      if (val === '=') {
        let expr = tpSciExpr
          .replace(/×√/g, 'Math.sqrt')
          .replace(/×/g, '*')
          .replace(/\^/g, '**')
          .replace(/sin\(/g, 'Math.sin(')
          .replace(/cos\(/g, 'Math.cos(')
          .replace(/tan\(/g, 'Math.tan(')
          .replace(/log\(/g, 'Math.log10(')
          .replace(/ln\(/g, 'Math.log(')
          .replace(/sqrt\(/g, 'Math.sqrt(')
          .replace(/π/g, 'Math.PI')
          .replace(/(?<![\w.])e(?![\w(])/g, 'Math.E');
        if (!/^[0-9+\-*/.()%\s\w]*$/.test(expr)) throw new Error('bad');
        // eslint-disable-next-line no-new-func
        const result = Function('"use strict"; return (' + expr + ')')();
        if (typeof result !== 'number' || !isFinite(result)) throw new Error('bad');
        tpShowResult(String(Math.round(result * 1e10) / 1e10), { label: tpSciExpr, raw: result });
        tpSciExpr = String(Math.round(result * 1e10) / 1e10);
        tpSciUpdate();
        return;
      }
      tpSciExpr += val;
      tpSciUpdate();
    } catch (e) { tpShowError('Invalid expression.'); tpSciExpr = ''; tpSciUpdate(); }
  });
});
</script>
