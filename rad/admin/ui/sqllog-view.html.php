<?php
$sqlLog = $this->runData['data']['sqllog'];
$metrics = $this->runData['data']['metrics'];
$hourlyChart = $this->runData['data']['hourly_chart'];
$typeChart = $this->runData['data']['type_chart'];
$topTables = $this->runData['data']['top_tables'];
$selectedDate = $this->runData['data']['selected_date'];
$dateOptions = $this->runData['data']['date_options'];
$highlightDates = array_slice($dateOptions, 0, 12);
$maxDate = $dateOptions[0] ?? $selectedDate;
$minDate = $dateOptions[array_key_last($dateOptions)] ?? $selectedDate;
$filters = $this->runData['data']['filters'] ?? ['date' => $selectedDate];
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);
$rangeDays = (int)($filters['range_days'] ?? 1);
$hourFilter = $filters['hour'] ?? '';
$searchQuery = $filters['q'] ?? '';
$typeFilter = $filters['type'] ?? '';
$exportQuery = http_build_query([
    'date' => $filters['date'] ?? $selectedDate,
    'range_days' => $rangeDays,
    'hour' => $hourFilter,
    'q' => $searchQuery,
    'type' => $typeFilter,
]);
$hasFilters = ($filters['date'] ?? $selectedDate) !== $maxDate
    || $rangeDays !== 1
    || $hourFilter !== ''
    || $searchQuery !== ''
    || $typeFilter !== '';
?>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-body">
        <form class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="sql-log-date" class="form-label">Select Date</label>
                <input type="date"
                       id="sql-log-date"
                       name="date"
                       class="form-control"
                       value="<?php echo htmlspecialchars($selectedDate); ?>"
                       min="<?php echo htmlspecialchars($minDate); ?>"
                       max="<?php echo htmlspecialchars($maxDate); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Last N days</label>
                <select class="form-select" name="range_days">
                    <?php foreach ([1, 3, 7, 14, 30] as $opt) { ?>
                        <option value="<?php echo $opt; ?>" <?php echo $rangeDays === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Hour</label>
                <select class="form-select" name="hour">
                    <option value="">All</option>
                    <?php for ($h = 0; $h < 24; $h++) { ?>
                        <?php $label = sprintf('%02d:00', $h); ?>
                        <option value="<?php echo $h; ?>" <?php echo ((string)$hourFilter === (string)$h) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="type">
                    <option value="">All</option>
                    <?php foreach (['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'CREATE', 'DROP'] as $typeOpt) { ?>
                        <option value="<?php echo $typeOpt; ?>" <?php echo strtoupper($typeFilter) === $typeOpt ? 'selected' : ''; ?>><?php echo $typeOpt; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Query or parameters" value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i>Load</button>
                <?php if ($hasFilters) { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/sqllog/view" class="btn btn-outline-secondary w-100">Reset</a>
                <?php } ?>
            </div>
            <div class="col-md-3 ms-auto text-end d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/sqllog/exportcsv?<?php echo htmlspecialchars($exportQuery); ?>">
                    <i class="bi bi-download"></i> Export CSV
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#sqlPurgeModal">
                    <i class="bi bi-trash"></i> Purge Logs
                </button>
            </div>
            <?php if (!empty($dateOptions)) { ?>
            <div class="col-12">
                <div class="form-text mb-1">Dates with SQL logs</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($highlightDates as $dateOption) { 
                        $isSelected = $dateOption === $selectedDate;
                        $formatted = \DateTime::createFromFormat('Y-m-d', $dateOption);
                        $label = $formatted ? $formatted->format('M j') : $dateOption;
                    ?>
                    <button type="button"
                            class="btn btn-sm <?php echo $isSelected ? 'btn-primary text-white' : 'btn-outline-secondary'; ?> sql-date-pill"
                            data-date="<?php echo htmlspecialchars($dateOption); ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </button>
                    <?php } ?>
                    <?php if (count($dateOptions) > count($highlightDates)) { ?>
                        <span class="text-muted small align-self-center">
                            +<?php echo count($dateOptions) - count($highlightDates); ?> more
                        </span>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </form>
    </div>
</div>

<div class="modal fade" id="sqlPurgeModal" tabindex="-1" aria-labelledby="sqlPurgeModalLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/sqllog/purge">
                <div class="modal-header">
                    <h5 class="modal-title" id="sqlPurgeModalLabel">Purge Old SQL Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Select a window to permanently delete SQL logs older than the chosen period.</p>
                    <div class="mb-3">
                        <label for="sql-purge-window" class="form-label">Remove logs older than</label>
                        <select class="form-select" id="sql-purge-window" name="purge_window" required>
                            <option value="">Select an option</option>
                            <option value="30">More than last 30 days</option>
                            <option value="60">More than last 60 days</option>
                            <option value="90">More than last 90 days</option>
                            <option value="180">More than last 6 months</option>
                            <option value="365">More than last 1 year</option>
                            <option value="730">More than last 2 years</option>
                            <option value="1095">More than last 3 years</option>
                            <option value="1460">More than last 4 years</option>
                            <option value="1825">More than last 5 years</option>
                            <option value="2190">More than last 6 years</option>
                        </select>
                        <div class="invalid-feedback">Please choose a purge window.</div>
                    </div>
                    <div class="alert alert-warning mb-0" id="sql-purge-warning">
                        Select a window to see what will be removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="sql-purge-confirm" disabled>
                        <i class="bi bi-exclamation-triangle"></i> Confirm Purge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($sqlLog)) { ?>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Total Queries</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['total']); ?></div>
                <div class="text-muted small">Captured for <?php echo htmlspecialchars($selectedDate); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Reads</div>
                <div class="display-6 fw-semibold text-success"><?php echo number_format($metrics['reads']); ?></div>
                <div class="text-muted small">SELECT statements</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Writes</div>
                <div class="display-6 fw-semibold text-primary"><?php echo number_format($metrics['writes']); ?></div>
                <div class="text-muted small">INSERT/UPDATE/DELETE</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Avg Parameters</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['avg_params'], 2); ?></div>
                <div class="text-muted small">Per executed query</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Hourly Throughput</h5>
                    <span class="badge bg-light text-dark">queries/hour</span>
                </div>
                <canvas id="sqlHourlyChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Query Mix</h5>
                <canvas id="sqlTypeChart" height="220"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Top Tables</h5>
                <?php if (!empty($topTables)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topTables as $table) { ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span class="text-truncate" style="max-width: 230px;"><?php echo htmlspecialchars($table['table']); ?></span>
                            <span class="badge bg-primary-subtle text-primary"><?php echo number_format($table['count']); ?></span>
                        </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted mb-0">Insufficient data to rank tables.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-2">SQL Statement Details</h5>
            <div class="d-flex gap-2 mb-2">
                <input type="search" id="sql-log-search" class="form-control" placeholder="Search query or table…">
                <select id="sql-page-size" class="form-select" style="width: 120px;" data-pref="<?php echo $perPagePref; ?>">
                    <?php foreach ([25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?> / page</option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="sql-log-table">
                <thead class="table-light">
                    <tr>
                        <th class="small">Timestamp</th>
                        <th class="small">Query</th>
                        <th class="small">Type</th>
                        <th class="small">Table</th>
                        <th class="small text-end">Parameters</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sqlLog as $index => $entry) { 
                        $detailId = 'sql-log-detail-' . $index;
                        $params = $entry['params'] ?? [];
                        $paramCount = $entry['param_count'] ?? 0;
                        $primaryTable = $entry['primary_table'] ?? '';
                        $searchBlob = $entry['search_blob'] ?? '';
                    ?>
                    <tr class="sql-log-row" data-detail-id="<?php echo $detailId; ?>" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES); ?>">
                        <td class="small text-nowrap">
                            <button class="btn btn-link btn-sm p-0 me-2 sql-log-toggle collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?php echo $detailId; ?>"
                                    aria-expanded="false" aria-controls="<?php echo $detailId; ?>">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="fw-semibold"><?php echo htmlspecialchars($entry['timestamp']); ?></div>
                        </td>
                        <td class="small text-truncate" style="max-width: 340px;" title="<?php echo htmlspecialchars($entry['query']); ?>">
                            <?php echo htmlspecialchars($entry['query']); ?>
                        </td>
                        <td class="small">
                            <span class="badge bg-secondary-subtle text-secondary text-uppercase"><?php echo htmlspecialchars($entry['query_type']); ?></span>
                        </td>
                        <td class="small">
                            <?php if ($primaryTable) { ?>
                                <span class="fw-semibold"><?php echo htmlspecialchars($primaryTable); ?></span>
                            <?php } else { ?>
                                <span class="text-muted">n/a</span>
                            <?php } ?>
                        </td>
                        <td class="small text-end">
                            <?php echo $paramCount ? $paramCount . ' values' : '-'; ?>
                        </td>
                    </tr>
                    <tr class="sql-log-detail collapse bg-body-tertiary" id="<?php echo $detailId; ?>">
                        <td colspan="5" class="border-top">
                            <div class="row g-4 small py-3">
                                <div class="col-md-7">
                                    <div class="text-uppercase text-muted small mb-2">Statement</div>
                                    <pre class="bg-dark text-light rounded p-3 mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($entry['query']); ?></pre>
                                </div>
                                <div class="col-md-5">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-uppercase text-muted small">Parameters</span>
                                        <span class="badge bg-light text-dark"><?php echo $paramCount; ?></span>
                                    </div>
                                    <?php if ($paramCount) { ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($params as $key => $value) { 
                                                $displayValue = is_scalar($value) ? $value : json_encode($value);
                                            ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                                <span class="fw-semibold me-2"><?php echo htmlspecialchars((string)$key); ?></span>
                                                <span class="text-break text-muted"><?php echo htmlspecialchars((string)$displayValue); ?></span>
                                            </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <p class="text-muted mb-0">No bound parameters.</p>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3" id="sql-log-pagination">
            <div class="small text-muted" id="sql-log-count"></div>
            <div class="btn-group" role="group" aria-label="Pagination controls">
                <button class="btn btn-outline-secondary btn-sm" id="sql-prev-page">Prev</button>
                <button class="btn btn-outline-secondary btn-sm" id="sql-next-page">Next</button>
            </div>
        </div>
    </div>
</div>

<?php } else { ?>
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url'];?>/img/no-sql-log.svg" alt="No SQL log yet today." height="200">
    <h2 class="h4 mt-3 text-center">No SQL activity for the selected date.</h2>
    <p class="text-muted">Choose a different day from the filter above.</p>
</div>
<?php } ?>

<style>
.sql-log-toggle .bi {
    transition: transform 0.2s ease;
}
.sql-log-toggle:not(.collapsed) .bi {
    transform: rotate(180deg);
}
</style>

<script>
(function() {
    const hourlyData = <?php echo json_encode($hourlyChart); ?>;
    const typeData = <?php echo json_encode($typeChart); ?>;
    const availableDates = <?php echo json_encode($dateOptions); ?>;
    const availableSet = new Set(availableDates);
    const dateInput = document.getElementById('sql-log-date');
    let lastValidDate = dateInput ? dateInput.value : '';

    if (dateInput) {
        dateInput.addEventListener('input', enforceDate);
        dateInput.addEventListener('change', enforceDate);
        document.querySelectorAll('.sql-date-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                dateInput.value = btn.dataset.date;
                dateInput.form.submit();
            });
        });
    }

    const purgeSelect = document.getElementById('sql-purge-window');
    const purgeWarning = document.getElementById('sql-purge-warning');
    const purgeConfirm = document.getElementById('sql-purge-confirm');
    if (purgeSelect && purgeWarning && purgeConfirm) {
        purgeSelect.addEventListener('change', function() {
            const label = this.options[this.selectedIndex]?.text || '';
            if (label) {
                purgeWarning.textContent = 'This will delete SQL logs ' + label.toLowerCase() + '. This action cannot be undone.';
                purgeConfirm.disabled = false;
            } else {
                purgeWarning.textContent = 'Select a window to see what will be removed.';
                purgeConfirm.disabled = true;
            }
        });
    }

    function enforceDate(event) {
        const value = event.target.value;
        if (value && availableSet.has(value)) {
            lastValidDate = value;
            event.target.setCustomValidity('');
            return;
        }
        if (!value) { return; }
        event.target.value = lastValidDate;
        event.target.setCustomValidity('Select a date that has SQL log data.');
        event.target.reportValidity();
        setTimeout(() => event.target.setCustomValidity(''), 2500);
    }

    if (hourlyData.labels && hourlyData.labels.length) {
        const ctx = document.getElementById('sqlHourlyChart');
        if (ctx) {
            window.RadAdminCharts.render(ctx, {
                type: 'line',
                data: {
                    labels: hourlyData.labels,
                    datasets: [{
                        label: 'Queries',
                        borderColor: '#0d6efd',
                        backgroundColor: 'rgba(13,110,253,0.15)',
                        data: hourlyData.counts,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    scales: { y: { beginAtZero: true } },
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    if (typeData.labels && typeData.labels.length) {
        const ctxType = document.getElementById('sqlTypeChart');
        if (ctxType) {
            window.RadAdminCharts.render(ctxType, {
                type: 'doughnut',
                data: {
                    labels: typeData.labels,
                    datasets: [{
                        data: typeData.counts,
                        backgroundColor: ['#0d6efd', '#6610f2', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#adb5bd']
                    }]
                },
                options: {
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    }

    const table = document.getElementById('sql-log-table');
    if (!table) { return; }
    const rows = Array.from(table.querySelectorAll('tr.sql-log-row'));
    const detailMap = new Map();
    rows.forEach(row => {
        const detailId = row.dataset.detailId;
        const detailRow = document.getElementById(detailId);
        if (detailRow) {
            detailMap.set(row, detailRow);
        }
    });

    const pageSizeSelect = document.getElementById('sql-page-size');
    const searchInput = document.getElementById('sql-log-search');
    const prevBtn = document.getElementById('sql-prev-page');
    const nextBtn = document.getElementById('sql-next-page');
    const countLabel = document.getElementById('sql-log-count');
    let filteredRows = rows.slice();
    if (pageSizeSelect) {
        const pref = parseInt(pageSizeSelect.dataset.pref || '0', 10);
        if (pref) {
            pageSizeSelect.value = String(pref);
        }
    }
    let pageSize = parseInt(pageSizeSelect.value, 10);
    let currentPage = 1;

    function renderPagination() {
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;

        rows.forEach(row => row.classList.add('d-none'));
        detailMap.forEach(detail => detail.classList.add('d-none'));
        filteredRows.slice(start, end).forEach(row => {
            row.classList.remove('d-none');
            const detail = detailMap.get(row);
            if (detail) {
                detail.classList.remove('d-none');
            }
        });

        countLabel.textContent = total
            ? `Showing ${start + 1}-${Math.min(end, total)} of ${total} queries`
            : 'No matching queries';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || total === 0;
    }

    function applyFilters() {
        const query = (searchInput.value || '').toLowerCase();
        filteredRows = rows.filter(row => {
            const haystack = (row.dataset.search || row.textContent || '').toLowerCase();
            return haystack.includes(query);
        });
        currentPage = 1;
        renderPagination();
    }

    searchInput.addEventListener('input', applyFilters);
    pageSizeSelect.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('per_page', pageSizeSelect.value);
        fetch(url.toString(), { credentials: 'same-origin' });
        pageSize = parseInt(pageSizeSelect.value, 10);
        currentPage = 1;
        renderPagination();
    });
    prevBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderPagination();
        }
    });
    nextBtn.addEventListener('click', () => {
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        if (currentPage < totalPages) {
            currentPage++;
            renderPagination();
        }
    });

    renderPagination();
})();
</script>
