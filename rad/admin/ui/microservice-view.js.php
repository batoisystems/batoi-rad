<script>
(function() {
    const table = document.getElementById('ms-table');
    if (!table) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.getElementById('ms-filter-search');
    const statusSelect = document.getElementById('ms-filter-status');
    const typeSelect = document.getElementById('ms-filter-type');
    const scopeSelect = document.getElementById('ms-filter-scope');
    const saasSelect = document.getElementById('ms-filter-saas');
    const bindingSelect = document.getElementById('ms-filter-binding');
    const resetButton = document.getElementById('ms-filter-reset');
    const visibleCountEl = document.getElementById('ms-visible-count');
    const pageSizeSelect = document.getElementById('ms-page-size');
    const prevBtn = document.getElementById('ms-page-prev');
    const nextBtn = document.getElementById('ms-page-next');
    const pageSummary = document.getElementById('ms-page-summary');

    let filteredRows = [];
    let currentPage = 1;

    function applyFilters(resetPage = true) {
        const query = (searchInput.value || '').toLowerCase();
        const status = statusSelect.value;
        const type = typeSelect.value;
        const scope = scopeSelect.value;
        const saas = saasSelect.value;
        const binding = bindingSelect.value;

        filteredRows = [];
        rows.forEach(row => {
            const matches =
                (!query || (row.dataset.search || '').includes(query)) &&
                (!status || row.dataset.status === status) &&
                (!type || row.dataset.type === type) &&
                (!scope || row.dataset.scope === scope) &&
                (!saas || row.dataset.saas === saas) &&
                (!binding || row.dataset.binding === binding);

            row.classList.add('d-none');
            if (matches) {
                filteredRows.push(row);
            }
        });

        if (visibleCountEl) {
            visibleCountEl.textContent = filteredRows.length;
        }

        if (resetPage) {
            currentPage = 1;
        }
        renderPage();
    }

    function resetFilters() {
        searchInput.value = '';
        statusSelect.value = '';
        typeSelect.value = '';
        scopeSelect.value = '';
        saasSelect.value = '';
        bindingSelect.value = '';
        applyFilters(true);
    }

    function renderPage() {
        const total = filteredRows.length;
        const pageSize = parseInt(pageSizeSelect ? pageSizeSelect.value : '25', 10) || 25;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(Math.max(currentPage, 1), totalPages);

        rows.forEach(row => row.classList.add('d-none'));
        const startIndex = (currentPage - 1) * pageSize;
        const endIndex = Math.min(startIndex + pageSize, total);
        for (let i = startIndex; i < endIndex; i++) {
            filteredRows[i].classList.remove('d-none');
        }

        if (pageSummary) {
            if (total === 0) {
                pageSummary.textContent = 'No microservicelets match the selected filters.';
            } else {
                pageSummary.textContent = `Showing ${startIndex + 1}–${endIndex} of ${total}`;
            }
        }

        if (prevBtn) {
            prevBtn.disabled = currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = currentPage >= totalPages || total === 0;
        }
    }

    searchInput.addEventListener('input', () => applyFilters(true));
    [statusSelect, typeSelect, scopeSelect, saasSelect, bindingSelect].forEach(select => {
        select.addEventListener('change', () => applyFilters(true));
    });
    resetButton.addEventListener('click', resetFilters);
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
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                renderPage();
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            const pageSize = parseInt(pageSizeSelect ? pageSizeSelect.value : '25', 10) || 25;
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            if (currentPage < totalPages) {
                currentPage++;
                renderPage();
            }
        });
    }

    applyFilters(true);
})();
</script>
