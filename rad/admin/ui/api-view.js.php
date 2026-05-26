<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.18.3/bootstrap-table.min.js"></script>
<script>
// Create copy function for the clipboard for uid value
$(document).ready(function() {
    $('.copy-uid').click(function(event) {
        event.preventDefault();
        var uid = $(this).data('uid');
        // Copy the uid to clipboard
        navigator.clipboard.writeText(uid).then(function() {
            // Show a toast
            var toastEl = document.querySelector('.toast');
            var toast = new bootstrap.Toast(toastEl);
            toast.show();
        }).catch(function(error) {
            console.error('Error copying text to clipboard: ', error);
        });
    });
});
</script>
