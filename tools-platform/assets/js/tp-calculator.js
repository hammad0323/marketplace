/**
 * tp-calculator.js — generic result-box behavior shared by EVERY tool
 * page. Individual tool logic files (see /tools/**) only need to call
 * tpShowResult()/tpShowError() — copy/print/share/reset are wired here
 * once, generically, via data-attributes on the standard markup that
 * includes/tool-page.php renders around every calculator.
 */

/**
 * Visitor-selectable currency SYMBOL/format, not a live FX converter: a
 * "loan amount" of 250000 the visitor typed means 250000 in whatever
 * currency they had in mind, so switching currency only changes how a
 * number is displayed, never rescales it (that's the honest behavior —
 * see the Currency Converter tool for the one place real FX math happens).
 */
const TP_CURRENCIES = {
  USD: { symbol: '$', name: 'US Dollar' },
  EUR: { symbol: '€', name: 'Euro' },
  GBP: { symbol: '£', name: 'British Pound' },
  PKR: { symbol: '₨', name: 'Pakistani Rupee' },
  INR: { symbol: '₹', name: 'Indian Rupee' },
  AED: { symbol: 'د.إ', name: 'UAE Dirham' },
  SAR: { symbol: '﷼', name: 'Saudi Riyal' },
  CAD: { symbol: 'C$', name: 'Canadian Dollar' },
  AUD: { symbol: 'A$', name: 'Australian Dollar' },
  JPY: { symbol: '¥', name: 'Japanese Yen' },
  CNY: { symbol: '¥', name: 'Chinese Yuan' },
  SGD: { symbol: 'S$', name: 'Singapore Dollar' },
  BDT: { symbol: '৳', name: 'Bangladeshi Taka' },
  ZAR: { symbol: 'R', name: 'South African Rand' },
  NGN: { symbol: '₦', name: 'Nigerian Naira' },
};

function tpGetCurrency() {
  try {
    const stored = localStorage.getItem('tp_currency');
    return stored && TP_CURRENCIES[stored] ? stored : 'USD';
  } catch (e) { return 'USD'; }
}

function tpSetCurrency(code) {
  if (!TP_CURRENCIES[code]) return;
  try { localStorage.setItem('tp_currency', code); } catch (e) { /* private mode etc — safe to ignore */ }
  document.dispatchEvent(new CustomEvent('tp:currencychange', { detail: { code } }));
}

function tpFormatMoney(amount, decimals = 2) {
  const symbol = TP_CURRENCIES[tpGetCurrency()].symbol;
  const formatted = Number(amount).toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  return symbol + formatted;
}

function tpShowResult(value, opts) {
  opts = opts || {};
  const box = document.getElementById('tpResultBox');
  const valueEl = document.getElementById('tpResultValue');
  const labelEl = document.getElementById('tpResultLabel');
  if (!box || !valueEl) return;
  valueEl.textContent = value;
  if (labelEl && opts.label) labelEl.textContent = opts.label;
  box.classList.add('show');
  document.getElementById('tpCalcError')?.classList.add('d-none');
  box.dataset.rawValue = opts.raw !== undefined ? opts.raw : value;

  if (window.localStorage && opts.historyLabel) {
    try {
      const key = 'tp_history_' + (document.body.dataset.toolSlug || 'tool');
      const history = JSON.parse(localStorage.getItem(key) || '[]');
      history.unshift({ label: opts.historyLabel, value: value, at: new Date().toISOString() });
      localStorage.setItem(key, JSON.stringify(history.slice(0, 10)));
    } catch (e) { /* localStorage unavailable (private mode etc) — safe to ignore */ }
  }
}

function tpShowError(message) {
  const err = document.getElementById('tpCalcError');
  if (!err) return;
  err.textContent = message;
  err.classList.remove('d-none');
  document.getElementById('tpResultBox')?.classList.remove('show');
}

function tpValidateNumber(value, { min = null, max = null, label = 'Value', allowNegative = true } = {}) {
  if (value === '' || value === null || value === undefined) {
    throw new Error(`${label} is required.`);
  }
  const num = Number(value);
  if (Number.isNaN(num)) {
    throw new Error(`${label} must be a valid number.`);
  }
  if (!allowNegative && num < 0) {
    throw new Error(`${label} cannot be negative.`);
  }
  if (min !== null && num < min) {
    throw new Error(`${label} must be at least ${min}.`);
  }
  if (max !== null && num > max) {
    throw new Error(`${label} must be at most ${max}.`);
  }
  return num;
}

document.addEventListener('click', (e) => {
  if (e.target.closest('[data-copy-result]')) {
    const box = document.getElementById('tpResultBox');
    const text = box ? box.dataset.rawValue || document.getElementById('tpResultValue').textContent : '';
    navigator.clipboard?.writeText(String(text)).then(() => {
      if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Copied to clipboard', showConfirmButton: false, timer: 1500 });
    });
  }
  if (e.target.closest('[data-print-result]')) {
    window.print();
  }
  if (e.target.closest('[data-share-result]')) {
    const url = window.location.href;
    if (navigator.share) {
      navigator.share({ title: document.title, url }).catch(() => {});
    } else {
      navigator.clipboard?.writeText(url);
      if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Link copied', showConfirmButton: false, timer: 1500 });
    }
  }
  if (e.target.closest('[data-reset-result]')) {
    const form = document.getElementById('tpCalcForm');
    form?.reset();
    document.getElementById('tpResultBox')?.classList.remove('show');
    document.getElementById('tpCalcError')?.classList.add('d-none');
  }
});
