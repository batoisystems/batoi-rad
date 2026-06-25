<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-uid').forEach(function(link) {
        link.addEventListener('click', function(event) {
            var uid = link.getAttribute('data-uid');
            var toastEl = document.querySelector('.toast');
            event.preventDefault();
            if (!uid) {
                return;
            }
            navigator.clipboard.writeText(uid).then(function() {
                if (window.RadAdminUI && window.RadAdminUI.showToast) {
                    window.RadAdminUI.showToast('UID copied to clipboard.', 'success');
                }
            }).catch(function(error) {
                console.error('Error copying text to clipboard: ', error);
            });
        });
    });

    var apiTable = document.getElementById('apiTable');
    if (apiTable && window.BatoiUIF && window.BatoiUIF.initTable) {
        window.BatoiUIF.initTable(apiTable);
    }
});
</script>
