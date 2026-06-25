<script>
(function() {
    const table = document.getElementById('role-table');
    if (!table) {
        return;
    }
    table.querySelectorAll('.copy-uid').forEach(button => {
        button.addEventListener('click', event => {
            event.preventDefault();
            const uid = button.getAttribute('data-uid');
            if (!uid) {
                return;
            }
            const showToast = () => {
                if (window.RadAdminUI && window.RadAdminUI.showToast) {
                    window.RadAdminUI.showToast('UID copied to clipboard.', 'success');
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(uid).then(showToast);
                return;
            }
            const input = document.createElement('input');
            input.value = uid;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            showToast();
        });
    });
})();
</script>
