/* Super Admin dashboard charts (Chart.js). Data comes from window.PH_CHARTS, built from the database. */
(function () {
    'use strict';
    var d = window.PH_CHARTS;
    if (!d || !window.Chart) { return; }
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.color = '#64748B';
    Chart.defaults.borderColor = '#EEF2F6';
    var teal = '#0F766E', tealSoft = 'rgba(20,184,166,.18)';
    var money = function (v) { return 'Rs. ' + Number(v).toLocaleString('en-US'); };
    var base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } } };

    new Chart(document.getElementById('chRevenue'), {
        type: 'bar', data: { labels: d.labels, datasets: [{ label: 'Revenue', data: d.revenue, backgroundColor: teal, borderRadius: 6, maxBarThickness: 34 }] },
        options: Object.assign({}, base, { plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return money(c.raw); } } } },
            scales: { y: { beginAtZero: true, ticks: { callback: money } }, x: { grid: { display: false } } } })
    });
    new Chart(document.getElementById('chShops'), {
        type: 'line', data: { labels: d.labels, datasets: [{ label: 'New shops', data: d.shops, borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,.12)', fill: true, tension: .35, pointRadius: 3 }] }, options: base
    });
    new Chart(document.getElementById('chOrders'), {
        type: 'line', data: { labels: d.labels, datasets: [
            { label: 'All orders', data: d.orders, borderColor: teal, backgroundColor: tealSoft, fill: true, tension: .35, pointRadius: 3 },
            { label: 'Completed', data: d.completed, borderColor: '#7C3AED', backgroundColor: 'transparent', tension: .35, pointRadius: 3 }] },
        options: Object.assign({}, base, { plugins: { legend: { display: true, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } })
    });
    new Chart(document.getElementById('chStatus'), {
        type: 'doughnut', data: { labels: Object.keys(d.status), datasets: [{ data: Object.values(d.status), backgroundColor: ['#10B981', '#F59E0B', '#F43F5E', '#94A3B8'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }
    });
})();
