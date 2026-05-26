<script src="//cdnjs.cloudflare.com/ajax/libs/bootstrap-table/1.18.3/bootstrap-table.min.js"></script>
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        console.log('Text successfully copied to clipboard');
    }).catch(function(err) {
        console.error('Unable to copy text to clipboard', err);
    });
}
</script>