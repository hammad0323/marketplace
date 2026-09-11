/**
 * crm.js — shared behavior across crm/*.php pages: kanban drag-and-drop
 * stage changes, delete confirmations, and a geolocation helper for
 * field-visit GPS check-in. Page-specific logic (document line-item
 * builder, etc.) lives inline in its own page, same convention as the
 * public tool pages.
 */
const baseUrl = document.body.dataset.baseUrl || '';

if (window.jQuery) {
  jQuery('.tp-datatable').DataTable({ pageLength: 25, order: [] });
}

/* ---------------- Kanban drag-and-drop ---------------- */
(function crmKanban() {
  const board = document.querySelector('.crm-kanban');
  if (!board) return;

  let draggedCard = null;

  board.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.crm-lead-card');
    if (!card) return;
    draggedCard = card;
    card.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
  });

  board.addEventListener('dragend', (e) => {
    const card = e.target.closest('.crm-lead-card');
    if (card) card.classList.remove('dragging');
  });

  board.querySelectorAll('.crm-kanban-col').forEach((col) => {
    col.addEventListener('dragover', (e) => {
      e.preventDefault();
      col.classList.add('drag-over');
    });
    col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
    col.addEventListener('drop', (e) => {
      e.preventDefault();
      col.classList.remove('drag-over');
      if (!draggedCard) return;
      const newStatus = col.dataset.status;
      const leadId = draggedCard.dataset.leadId;
      col.querySelector('.crm-kanban-cards').appendChild(draggedCard);

      fetch(`${baseUrl}/crm/ajax/update-lead-stage.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `lead_id=${encodeURIComponent(leadId)}&status=${encodeURIComponent(newStatus)}&csrf_token=${encodeURIComponent(document.body.dataset.csrf || '')}`,
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success && window.Swal) {
            Swal.fire({ icon: 'error', title: 'Could not move lead', text: data.error || 'Please try again.' });
          }
        })
        .catch(() => {
          if (window.Swal) Swal.fire({ icon: 'error', title: 'Network error', text: 'Could not save the change.' });
        });
    });
  });
})();

/* ---------------- Delete confirmation ---------------- */
document.addEventListener('click', (e) => {
  const link = e.target.closest('[data-crm-confirm-delete]');
  if (!link) return;
  e.preventDefault();
  const label = link.dataset.crmConfirmDelete || 'this item';
  if (window.Swal) {
    Swal.fire({
      title: `Delete ${label}?`, text: 'This cannot be undone.', icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Delete', confirmButtonColor: '#DC2626',
    }).then((result) => { if (result.isConfirmed) window.location.href = link.href; });
  } else if (confirm(`Delete ${label}? This cannot be undone.`)) {
    window.location.href = link.href;
  }
});

/* ---------------- GPS check-in (real browser Geolocation API) ---------------- */
function crmCaptureLocation(latInputId, lngInputId, statusElId) {
  const statusEl = statusElId ? document.getElementById(statusElId) : null;
  if (!navigator.geolocation) {
    if (statusEl) statusEl.textContent = 'Geolocation is not supported by this browser.';
    return;
  }
  if (statusEl) statusEl.textContent = 'Getting your location…';
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      document.getElementById(latInputId).value = pos.coords.latitude.toFixed(7);
      document.getElementById(lngInputId).value = pos.coords.longitude.toFixed(7);
      if (statusEl) statusEl.textContent = `✓ Location captured (±${Math.round(pos.coords.accuracy)}m accuracy)`;
    },
    (err) => {
      if (statusEl) statusEl.textContent = 'Could not get location: ' + err.message;
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
}
