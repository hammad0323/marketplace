document.addEventListener('DOMContentLoaded', function () {
  var burger = document.getElementById('adminBurger');
  var sidebar = document.querySelector('.admin-sidebar');
  if (burger && sidebar) {
    burger.addEventListener('click', function () { sidebar.classList.toggle('open'); });
  }

  if (window.jQuery && jQuery.fn.select2) {
    jQuery('.select2').select2({ width: '100%' });
  }

  window.__ckEditors = window.__ckEditors || [];
  if (window.ClassicEditor) {
    document.querySelectorAll('textarea.richtext').forEach(function (el) {
      ClassicEditor.create(el).then(function (editor) {
        window.__ckEditors.push({ el: el, editor: editor });
      }).catch(function (err) { console.error(err); });
    });
  }
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      (window.__ckEditors || []).forEach(function (item) {
        if (form.contains(item.el)) item.el.value = item.editor.getData();
      });
    }, true);
  });

  document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var target = document.querySelector(input.dataset.preview);
      if (target && input.files && input.files[0]) {
        target.src = URL.createObjectURL(input.files[0]);
        target.classList.remove('d-none');
      }
    });
  });

  document.querySelectorAll('form.confirm-delete').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (window.Swal) {
        Swal.fire({
          title: 'Are you sure?',
          text: form.dataset.message || 'This action cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#a5763f',
          confirmButtonText: 'Yes, delete it'
        }).then(function (res) { if (res.isConfirmed) form.submit(); });
      } else if (confirm('Are you sure?')) {
        form.submit();
      }
    });
  });

  document.querySelectorAll('.toggle-status-form').forEach(function (form) {
    form.addEventListener('change', function () { form.submit(); });
  });
});

function adminToast(message, icon) {
  if (window.Swal) {
    Swal.fire({ toast: true, position: 'top-end', icon: icon || 'success', title: message, showConfirmButton: false, timer: 2200 });
  } else {
    alert(message);
  }
}
