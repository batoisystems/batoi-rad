<script>
(function() {
    const table = document.getElementById('user-table');
    if (!table) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const visibleCountEl = document.getElementById('user-visible-count');
    if (visibleCountEl) {
        visibleCountEl.textContent = rows.length.toString();
    }

    function attachCopyHandlers() {
        const copyButtons = table.querySelectorAll('.copy-uid');
        copyButtons.forEach(button => {
            button.addEventListener('click', event => {
                event.preventDefault();
                const uid = button.getAttribute('data-uid');
                if (!uid) {
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(uid).then(showToast).catch(fallbackCopy);
                } else {
                    fallbackCopy();
                }

                function fallbackCopy() {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.value = uid;
                    document.body.appendChild(input);
                    input.select();
                    document.execCommand('copy');
                    document.body.removeChild(input);
                    showToast();
                }

                function showToast() {
                    if (window.RadAdminUI && window.RadAdminUI.showToast) {
                        window.RadAdminUI.showToast('UID copied to clipboard.', 'success');
                    }
                }
            });
        });
    }

    attachCopyHandlers();
})();
</script>
