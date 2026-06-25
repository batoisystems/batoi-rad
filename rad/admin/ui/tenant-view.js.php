<script>
document.addEventListener('DOMContentLoaded', function() {
    var table = document.getElementById('dataTable');
    if (table && window.BatoiUIF && window.BatoiUIF.initTable) {
        window.BatoiUIF.initTable(table);
    }
});
</script>
