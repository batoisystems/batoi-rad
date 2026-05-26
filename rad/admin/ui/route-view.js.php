<script>
(function() {
    const table = document.getElementById('route-table');
    if (!table) {
        return;
    }

    const rows = Array.from(table.querySelectorAll('tbody tr'));
    const searchInput = document.getElementById('route-filter-search');
    const statusSelect = document.getElementById('route-filter-status');
    const scopeSelect = document.getElementById('route-filter-scope');
    const bindingSelect = document.getElementById('route-filter-binding');
    const resetButton = document.getElementById('route-filter-reset');
    const visibleCountEl = document.getElementById('route-visible-count');
    const pageSizeSelect = document.getElementById('route-page-size');
    const prevBtn = document.getElementById('route-page-prev');
    const nextBtn = document.getElementById('route-page-next');
    const pageSummary = document.getElementById('route-page-summary');
    const bulkForm = document.getElementById('route-bulk-archive-form');
    const selectVisibleBtn = document.getElementById('route-select-visible');
    const clearSelectionBtn = document.getElementById('route-clear-selection');
    const bulkArchiveBtn = document.getElementById('route-bulk-archive-btn');
    const bulkDeleteBtn = document.getElementById('route-bulk-delete-btn');
    const bulkIntentInput = document.getElementById('route-bulk-intent');
    const selectedCountEl = document.getElementById('route-selected-count');
    const selectAllVisible = document.getElementById('route-select-all-visible');
    const rowCheckboxes = Array.from(document.querySelectorAll('.route-row-checkbox'));
    let filteredRows = [];
    let currentPage = 1;

    function getVisibleSelectableCheckboxes() {
        return rows
            .filter(row => !row.classList.contains('d-none'))
            .map(row => row.querySelector('.route-row-checkbox'))
            .filter(cb => cb && !cb.disabled);
    }

    function updateBulkSelectionState() {
        const selectedCount = rowCheckboxes.filter(cb => cb.checked).length;
        if (selectedCountEl) {
            selectedCountEl.textContent = String(selectedCount);
        }
        if (bulkArchiveBtn) {
            bulkArchiveBtn.disabled = selectedCount === 0;
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = selectedCount === 0;
        }
        if (selectAllVisible) {
            const visibleCbs = getVisibleSelectableCheckboxes();
            selectAllVisible.checked = visibleCbs.length > 0 && visibleCbs.every(cb => cb.checked);
            selectAllVisible.indeterminate = visibleCbs.some(cb => cb.checked) && !selectAllVisible.checked;
        }
    }

    function applyFilters(resetPage = true) {
        const query = (searchInput.value || '').toLowerCase();
        const status = statusSelect.value;
        const scope = scopeSelect.value;
        const binding = bindingSelect.value;

        filteredRows = [];
        rows.forEach(row => {
            const matches =
                (!query || (row.dataset.search || '').includes(query)) &&
                (!status || row.dataset.status === status) &&
                (!scope || row.dataset.scope === scope) &&
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
        scopeSelect.value = '';
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
                pageSummary.textContent = 'No routes match the selected filters.';
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
        updateBulkSelectionState();
    }

    searchInput.addEventListener('input', () => applyFilters(true));
    statusSelect.addEventListener('change', () => applyFilters(true));
    scopeSelect.addEventListener('change', () => applyFilters(true));
    bindingSelect.addEventListener('change', () => applyFilters(true));
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

    rowCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkSelectionState);
    });
    if (selectAllVisible) {
        selectAllVisible.addEventListener('change', () => {
            const visibleCbs = getVisibleSelectableCheckboxes();
            visibleCbs.forEach(cb => {
                cb.checked = selectAllVisible.checked;
            });
            updateBulkSelectionState();
        });
    }
    if (selectVisibleBtn) {
        selectVisibleBtn.addEventListener('click', () => {
            getVisibleSelectableCheckboxes().forEach(cb => {
                cb.checked = true;
            });
            updateBulkSelectionState();
        });
    }
    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', () => {
            rowCheckboxes.forEach(cb => {
                cb.checked = false;
            });
            updateBulkSelectionState();
        });
    }
    if (bulkForm) {
        if (bulkArchiveBtn) {
            bulkArchiveBtn.addEventListener('click', () => {
                if (bulkIntentInput) {
                    bulkIntentInput.value = 'archive';
                }
            });
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', () => {
                if (bulkIntentInput) {
                    bulkIntentInput.value = 'delete';
                }
                bulkForm.requestSubmit();
            });
        }
        bulkForm.addEventListener('submit', (event) => {
            const selectedCount = rowCheckboxes.filter(cb => cb.checked).length;
            if (selectedCount === 0) {
                event.preventDefault();
                alert('Select at least one route.');
                return;
            }
            const intent = bulkIntentInput ? bulkIntentInput.value : 'archive';
            const isDelete = intent === 'delete';
            if (isDelete && bulkForm.dataset.deleteAction) {
                bulkForm.action = bulkForm.dataset.deleteAction;
            } else if (bulkForm.dataset.archiveAction) {
                bulkForm.action = bulkForm.dataset.archiveAction;
            }
            const ok = window.confirm(
                isDelete
                    ? `Delete ${selectedCount} selected route(s) from database and route files? This cannot be undone.`
                    : `Archive ${selectedCount} selected route(s)?`
            );
            if (!ok) {
                event.preventDefault();
            }
        });
    }

    applyFilters(true);
})();
</script>
