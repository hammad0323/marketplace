document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('dash-sidebar-toggle');
  const sidebar = document.querySelector('.dash-sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }

  // Reorder shop sections (drag handles) if sortable list present
  const sectionsList = document.getElementById('sections-list');
  if (sectionsList) {
    let dragEl;
    sectionsList.querySelectorAll('.section-item').forEach(item => {
      item.setAttribute('draggable', 'true');
      item.addEventListener('dragstart', () => { dragEl = item; item.style.opacity = '0.5'; });
      item.addEventListener('dragend', () => { item.style.opacity = '1'; });
      item.addEventListener('dragover', e => e.preventDefault());
      item.addEventListener('drop', function () {
        if (dragEl && dragEl !== this) {
          const items = Array.from(sectionsList.children);
          const dragIndex = items.indexOf(dragEl);
          const dropIndex = items.indexOf(this);
          if (dragIndex < dropIndex) this.after(dragEl); else this.before(dragEl);
        }
      });
    });
  }
});
