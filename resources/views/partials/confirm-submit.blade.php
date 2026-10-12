<script>
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.getAttribute('data-confirmed') === '1') {
                return;
            }
            event.preventDefault();
            if (window.confirm(form.getAttribute('data-confirm'))) {
                form.setAttribute('data-confirmed', '1');
                form.requestSubmit();
            }
        });
    });
</script>
