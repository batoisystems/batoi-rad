<script>
(function() {
    const table = document.getElementById('controller-table');
    if (!table) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.getElementById('controller-filter-search');
    const statusSelect = document.getElementById('controller-filter-status');
    const typeSelect = document.getElementById('controller-filter-type');
    const resetButton = document.getElementById('controller-filter-reset');
    const visibleCountEl = document.getElementById('controller-visible-count');

    function applyFilters() {
        const query = (searchInput.value || '').toLowerCase();
        const status = statusSelect.value;
        const type = typeSelect.value;

        let visible = 0;
        rows.forEach(row => {
            const matches =
                (!query || (row.dataset.search || '').includes(query)) &&
                (!status || row.dataset.status === status) &&
                (!type || row.dataset.type === type);

            row.classList.toggle('d-none', !matches);
            if (matches) {
                visible++;
            }
        });

        if (visibleCountEl) {
            visibleCountEl.textContent = visible;
        }
    }

    function resetFilters() {
        searchInput.value = '';
        statusSelect.value = '';
        typeSelect.value = '';
        applyFilters();
    }

    searchInput.addEventListener('input', applyFilters);
    statusSelect.addEventListener('change', applyFilters);
    typeSelect.addEventListener('change', applyFilters);
    resetButton.addEventListener('click', resetFilters);

    applyFilters();
})();
</script>
