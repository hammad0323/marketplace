<p class="small text-muted">Classic ABC inventory classification — enter each item's annual usage value (annual quantity × unit cost, or any other value that ranks its importance). Class A = the top ~80% of value, B = the next ~15%, C = the rest.</p>
<div id="tpAbcRows"></div>
<button type="button" class="tp-btn tp-btn-outline tp-btn-sm mb-3" id="tpAddRow"><i class="bi bi-plus-lg"></i> Add Item</button>
<button type="button" class="tp-btn-calc" id="tpCalculateBtn">Run ABC Analysis</button>
<div id="tpAbcOutput" class="mt-3"></div>
<script>
const tpAbcDefaults = [
  ['Item A - Best Seller', 45000],
  ['Item B - Steady Mover', 18000],
  ['Item C - Occasional', 6000],
  ['Item D - Slow Mover', 1500],
];

function tpAbcAddRow(name, value) {
  const row = document.createElement('div');
  row.className = 'row g-2 mb-2 tp-abc-row';
  row.innerHTML = `
    <div class="col-7"><input type="text" class="form-control form-control-sm tp-abc-name" placeholder="Item name" value="${name || ''}"></div>
    <div class="col-4"><input type="number" step="0.01" min="0" class="form-control form-control-sm tp-abc-value" placeholder="Annual value" value="${value !== undefined ? value : ''}"></div>
    <div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-sm text-danger p-0 tp-abc-remove" title="Remove"><i class="bi bi-x-lg"></i></button></div>
  `;
  document.getElementById('tpAbcRows').appendChild(row);
}
tpAbcDefaults.forEach((r) => tpAbcAddRow(r[0], r[1]));

document.getElementById('tpAddRow').addEventListener('click', () => tpAbcAddRow('', ''));
document.getElementById('tpAbcRows').addEventListener('click', (e) => {
  if (e.target.closest('.tp-abc-remove')) e.target.closest('.tp-abc-row').remove();
});

document.getElementById('tpCalculateBtn').addEventListener('click', function () {
  try {
    const rows = [...document.querySelectorAll('.tp-abc-row')];
    const items = rows.map((row) => ({
      name: row.querySelector('.tp-abc-name').value.trim(),
      value: Number(row.querySelector('.tp-abc-value').value),
    })).filter((i) => i.name && !Number.isNaN(i.value) && i.value > 0);

    if (items.length < 2) throw new Error('Enter at least 2 items with a name and a positive value.');

    const total = items.reduce((sum, i) => sum + i.value, 0);
    items.sort((a, b) => b.value - a.value);

    let cumulative = 0;
    const counts = { A: 0, B: 0, C: 0 };
    const rowsHtml = items.map((item, i) => {
      const pct = (item.value / total) * 100;
      cumulative += pct;
      const cls = cumulative <= 80 ? 'A' : cumulative <= 95 ? 'B' : 'C';
      counts[cls]++;
      const badgeClass = cls === 'A' ? 'tp-badge-featured' : cls === 'B' ? 'tp-badge-popular' : 'tp-badge-trending';
      return `<tr>
        <td>${i + 1}</td>
        <td>${item.name.replace(/</g, '&lt;')}</td>
        <td class="text-end">${item.value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
        <td class="text-end">${pct.toFixed(1)}%</td>
        <td class="text-end">${cumulative.toFixed(1)}%</td>
        <td class="text-center"><span class="tp-badge ${badgeClass}">${cls}</span></td>
      </tr>`;
    }).join('');

    document.getElementById('tpAbcOutput').innerHTML = `
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>#</th><th>Item</th><th class="text-end">Value</th><th class="text-end">% of Total</th><th class="text-end">Cumulative %</th><th class="text-center">Class</th></tr></thead>
          <tbody>${rowsHtml}</tbody>
        </table>
      </div>`;

    tpShowResult(`${counts.A} A / ${counts.B} B / ${counts.C} C`, {
      label: `ABC Analysis — ${items.length} items classified by value`,
      raw: `A:${counts.A} B:${counts.B} C:${counts.C}`, historyLabel: 'ABC Analysis',
    });
  } catch (e) { tpShowError(e.message); }
});
</script>
