<script>
(function() {
    const copyButton = document.querySelector('.copy-uid');
    if (!copyButton) {
        return;
    }
    copyButton.addEventListener('click', function (event) {
        event.preventDefault();
        const uid = copyButton.getAttribute('data-uid');
        if (!uid) {
            return;
        }

        const onSuccess = () => {
            if (window.RadAdminUI && window.RadAdminUI.showToast) {
                window.RadAdminUI.showToast('UID copied to clipboard.', 'success');
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(uid).then(onSuccess);
            return;
        }

        const input = document.createElement('input');
        input.value = uid;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        onSuccess();
    });
})();
</script>
