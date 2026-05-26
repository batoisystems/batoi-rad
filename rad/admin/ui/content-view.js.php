<script>
(function() {
    const table = document.getElementById('content-table');
    if (!table) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.getElementById('content-filter-search');
    const statusSelect = document.getElementById('content-filter-status');
    const typeSelect = document.getElementById('content-filter-type');
    const resetButton = document.getElementById('content-filter-reset');
    const visibleCountEl = document.getElementById('content-visible-count');
    const pageSizeSelect = document.getElementById('content-page-size');
    const prevBtn = document.getElementById('content-page-prev');
    const nextBtn = document.getElementById('content-page-next');
    const pageSummary = document.getElementById('content-page-summary');

    let filteredRows = rows.slice(0);
    let currentPage = 1;

    function applyFilters(resetPage = true) {
        const query = (searchInput?.value || '').toLowerCase();
        const status = statusSelect?.value || '';
        const type = typeSelect?.value || '';

        filteredRows = [];
        rows.forEach(row => {
            const matches =
                (!query || (row.dataset.search || '').includes(query)) &&
                (!status || row.dataset.status === status) &&
                (!type || row.dataset.type === type);

            row.classList.add('d-none');
            if (matches) {
                filteredRows.push(row);
            }
        });

        if (visibleCountEl) {
            visibleCountEl.textContent = filteredRows.length.toString();
        }

        if (resetPage) {
            currentPage = 1;
        }
        renderPage();
    }

    function renderPage() {
        const total = filteredRows.length;
        const pageSize = parseInt(pageSizeSelect?.value || '25', 10) || 25;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);

        rows.forEach(row => row.classList.add('d-none'));
        const start = (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);
        for (let i = start; i < end; i++) {
            filteredRows[i].classList.remove('d-none');
        }

        if (pageSummary) {
            if (total === 0) {
                pageSummary.textContent = 'No content matches the selected filters.';
            } else {
                pageSummary.textContent = `Showing ${start + 1}–${end} of ${total}`;
            }
        }

        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1 || total === 0;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages || total === 0;
        }
    }

    function resetFilters() {
        if (searchInput) searchInput.value = '';
        if (statusSelect) statusSelect.value = '';
        if (typeSelect) typeSelect.value = '';
        applyFilters(true);
    }

    function attachHandlers() {
        searchInput?.addEventListener('input', () => applyFilters(true));
        statusSelect?.addEventListener('change', () => applyFilters(true));
        typeSelect?.addEventListener('change', () => applyFilters(true));
        resetButton?.addEventListener('click', resetFilters);
        if (pageSizeSelect) {
            const pref = parseInt(pageSizeSelect.dataset.pref || '0', 10);
            if (pref) {
                pageSizeSelect.value = String(pref);
            }
            pageSizeSelect.addEventListener('change', () => {
                const url = new URL(window.location.href);
                url.searchParams.set('per_page', pageSizeSelect.value);
                fetch(url.toString(), { credentials: 'same-origin' });
                currentPage = 1;
                renderPage();
            });
        }
        prevBtn?.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderPage();
            }
        });
        nextBtn?.addEventListener('click', () => {
            const pageSize = parseInt(pageSizeSelect?.value || '25', 10) || 25;
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            if (currentPage < totalPages) {
                currentPage++;
                renderPage();
            }
        });
    }

    attachHandlers();
    applyFilters(true);
})();
</script>
