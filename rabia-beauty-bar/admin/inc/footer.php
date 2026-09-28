    </div>
</div>
<script>
document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
});
document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
    inp.addEventListener('change', function () {
        var img = document.getElementById(inp.getAttribute('data-preview'));
        if (img && inp.files[0]) { img.src = URL.createObjectURL(inp.files[0]); img.style.display = 'block'; }
    });
});
</script>
</body>
</html>
