<script>
(function() {
    const copyButton = document.querySelector('.copy-uid');
    if (!copyButton) {
        return;
    }
    const toastEl = document.getElementById('user-detail-toast');
    const toast = toastEl && window.bootstrap ? bootstrap.Toast.getOrCreateInstance(toastEl) : null;

    copyButton.addEventListener('click', function (event) {
        event.preventDefault();
        const uid = copyButton.getAttribute('data-uid');
        if (!uid) {
            return;
        }

        const onSuccess = () => {
            if (toast) {
                toast.show();
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
