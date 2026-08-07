(function (global) {
    'use strict';

    var ICONS = { success: 'ri-checkbox-circle-fill', error: 'ri-error-warning-fill', warning: 'ri-alert-fill', info: 'ri-information-fill' };

    function ensureStack() {
        var stack = document.querySelector('.toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function showToast(type, title, message, duration) {
        var stack = ensureStack();
        var toast = document.createElement('div');
        toast.className = 'toast ' + (type || 'info');
        toast.innerHTML =
            '<i class="toast-icon ' + (ICONS[type] || ICONS.info) + '"></i>' +
            '<div><strong>' + escapeHtml(title || '') + '</strong><span>' + escapeHtml(message || '') + '</span></div>';
        stack.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('removing');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration || 4500);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    global.showToast = showToast;
})(window);
