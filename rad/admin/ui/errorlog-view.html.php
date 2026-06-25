<?php
$entries = $this->runData['data']['errors'];
$metrics = $this->runData['data']['metrics'];
$hourlyChart = $this->runData['data']['hourly_chart'];
$severityChart = $this->runData['data']['severity_chart'];
$topFiles = $this->runData['data']['top_files'];
$topMessages = $this->runData['data']['top_messages'];
$selectedDate = $this->runData['data']['selected_date'];
$availableDates = $this->runData['data']['available_dates'];
$highlightDates = array_slice($availableDates, 0, 12);
$maxDate = $availableDates[0] ?? $selectedDate;
$minDate = $availableDates[array_key_last($availableDates)] ?? $selectedDate;
$deleteUrl = $this->runData['route']['rad_admin_url'] . '/errorlog/deletelog/' . $selectedDate;
$entryLimit = (int)($this->runData['data']['entry_limit'] ?? 0);
$filters = $this->runData['data']['filters'] ?? ['date' => $selectedDate];
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);
$rangeDays = (int)($filters['range_days'] ?? 1);
$hourFilter = $filters['hour'] ?? '';
$searchQuery = $filters['q'] ?? '';
$exportQuery = http_build_query([
    'date' => $filters['date'] ?? $selectedDate,
    'range_days' => $rangeDays,
    'hour' => $hourFilter,
    'q' => $searchQuery,
]);
$hasFilters = ($filters['date'] ?? $selectedDate) !== $maxDate
    || $rangeDays !== 1
    || $hourFilter !== ''
    || $searchQuery !== '';
?>

<style>
.pagination-hidden { display: none !important; }
.error-analytics canvas { pointer-events: none !important; }
.error-analytics .card-body { position: relative; z-index: 0; }
.error-analytics .btn-outline-danger:hover,
.error-analytics .btn-outline-danger:focus {
    color: #dc3545 !important;
    background-color: rgba(220,53,69,0.08) !important;
    border-color: #dc3545 !important;
}
.error-analytics .btn-danger:hover,
.error-analytics .btn-danger:focus {
    background-color: #c82333;
    border-color: #bd2130;
}
.chartjs-size-monitor,
.chartjs-size-monitor-expand,
.chartjs-size-monitor-shrink {
    pointer-events: none !important;
    position: relative !important;
    display: none !important;
}
</style>

<div class="error-analytics" style="position:relative;z-index:0;">
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="error-log-date" class="form-label">Select Date</label>
                <input type="date"
                       id="error-log-date"
                       class="form-control"
                       name="date"
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
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="Message, file, request" value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Load</button>
                <?php if ($hasFilters) { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/errorlog/view" class="btn btn-outline-secondary w-100">Reset</a>
                <?php } ?>
            </div>
            <div class="col-md-3 ms-auto text-end d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/errorlog/exportcsv?<?php echo htmlspecialchars($exportQuery); ?>">
                    <i class="bi bi-download"></i> Export CSV
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeLogsModal">
                    <i class="bi bi-trash"></i> Purge Logs
                </button>
            </div>
            <?php if (!empty($availableDates)) { ?>
            <div class="col-12">
                <div class="form-text mb-1">Dates with error logs</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($highlightDates as $dateOption) { 
                        $isSelected = $dateOption === $selectedDate;
                        $formatted = \DateTime::createFromFormat('Y-m-d', $dateOption);
                        $label = $formatted ? $formatted->format('M j') : $dateOption;
                    ?>
                    <button type="button"
                            class="btn btn-sm <?php echo $isSelected ? 'btn-primary text-white' : 'btn-outline-secondary'; ?> error-date-pill"
                            data-date="<?php echo htmlspecialchars($dateOption); ?>">
                        <?php echo htmlspecialchars($label); ?>
                    </button>
                    <?php } ?>
                    <?php if (count($availableDates) > count($highlightDates)) { ?>
                        <span class="text-muted small align-self-center">
                            +<?php echo count($availableDates) - count($highlightDates); ?> more
                        </span>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </form>
</div>
</div>

<?php if ($entryLimit > 0) { ?>
<div class="text-muted small px-2 pb-2">
    Showing the latest <?php echo number_format($entryLimit); ?> log entries to keep analytics responsive.
</div>
<?php } ?>

<div class="modal fade" id="purgeLogsModal" tabindex="-1" aria-labelledby="purgeLogsModalLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/errorlog/purge">
                <div class="modal-header">
                    <h5 class="modal-title" id="purgeLogsModalLabel">Purge Old Error Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Select a window to permanently delete logs older than the chosen period.</p>
                    <div class="mb-3">
                        <label for="purge-window" class="form-label">Remove logs older than</label>
                        <select class="form-select" id="purge-window" name="purge_window" required>
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
                    <div class="alert alert-warning mb-0" id="purge-warning">
                        Select a window to see what will be removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="purge-confirm" disabled>
                        <i class="bi bi-exclamation-triangle"></i> Confirm Purge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($entries)) { ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Logged Errors</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['total']); ?></div>
                <div class="text-muted small">Total exceptions on <?php echo htmlspecialchars($selectedDate); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Affected Files</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['unique_files']); ?></div>
                <div class="text-muted small">Unique PHP files involved</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Distinct Messages</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['unique_messages']); ?></div>
                <div class="text-muted small">Different error statements</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="card-title mb-0">Hourly Error Trend</h5>
                    <span class="badge bg-light text-dark">per hour</span>
                </div>
                <canvas id="errorHourlyChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <h5 class="card-title mb-0">Severity Breakdown</h5>
                </div>
                <canvas id="severityChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Files Triggering Most Errors</h5>
                <?php if (!empty($topFiles)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topFiles as $file) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-truncate" style="max-width: 75%;" title="<?php echo htmlspecialchars($file['file']); ?>">
                                    <?php echo htmlspecialchars($file['file']); ?>
                                </span>
                                <span class="badge bg-secondary"><?php echo $file['count']; ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted mb-0">No file data.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Recurring Messages</h5>
                <?php if (!empty($topMessages)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topMessages as $message) { ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <span class="text-truncate me-3" style="max-width: 80%;" title="<?php echo htmlspecialchars($message['message']); ?>">
                                        <?php echo htmlspecialchars($message['message']); ?>
                                    </span>
                                    <span class="badge bg-secondary"><?php echo $message['count']; ?></span>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted mb-0">No repeating messages.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
            <h5 class="card-title mb-2">Log Entries</h5>
            <div class="d-flex gap-2 mb-2">
                <input type="search" id="error-search" class="form-control" placeholder="Search errors…">
                <select id="error-page-size" class="form-select" style="width: 120px;" data-pref="<?php echo $perPagePref; ?>">
                    <?php foreach ([25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?> / page</option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="error-log-table">
                <thead class="table-light">
                    <tr>
                        <th class="small">Timestamp</th>
                        <th class="small">Type</th>
                        <th class="small text-center">Response</th>
                        <th class="small">File</th>
                        <th class="small text-end">Line</th>
                        <th class="small">Message</th>
                        <th class="small">Request</th>
                        <th class="small text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $index => $entry) { 
                        $detailId = 'error-detail-' . $index;
                        $entryJson = htmlspecialchars(json_encode($entry, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                    ?>
                        <tr class="log-row" data-detail="<?php echo $detailId; ?>" data-entry="<?php echo $entryJson; ?>">
                            <td class="small"><?php echo htmlspecialchars($entry['timestamp']); ?></td>
                            <td class="small"><span class="badge bg-dark-subtle text-uppercase"><?php echo htmlspecialchars($entry['error_type']); ?></span></td>
                            <td class="small text-center">
                                <?php
                                    $status = $entry['status_code'] ?? $entry['response_code'] ?? $entry['error_code'] ?? null;
                                    if ($status) {
                                        $badgeClass = 'bg-secondary';
                                        if ($status >= 500) {
                                            $badgeClass = 'bg-danger';
                                        } elseif ($status >= 400) {
                                            $badgeClass = 'bg-warning text-dark';
                                        } elseif ($status >= 300) {
                                            $badgeClass = 'bg-info text-dark';
                                        } elseif ($status >= 200) {
                                            $badgeClass = 'bg-success';
                                        }
                                        echo '<span class="badge '.$badgeClass.'">'.htmlspecialchars($status).'</span>';
                                    } else {
                                        echo '<span class="text-muted">—</span>';
                                    }
                                ?>
                            </td>
                            <td class="small text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($entry['file_path']); ?>">
                                <?php echo htmlspecialchars($entry['file_path']); ?>
                            </td>
                            <td class="small text-end"><?php echo htmlspecialchars($entry['line_number'] ?? '-'); ?></td>
                            <td class="small text-truncate" style="max-width: 260px;" title="<?php echo htmlspecialchars($entry['message']); ?>">
                                <?php echo htmlspecialchars(mb_strimwidth($entry['message'], 0, 120, '…')); ?>
                            </td>
                            <td class="small text-truncate" style="max-width: 220px;">
                                <?php if (!empty($entry['request_method'])) { ?>
                                    <span class="badge bg-light text-dark border me-1 text-uppercase"><?php echo htmlspecialchars($entry['request_method']); ?></span>
                                <?php } ?>
                                <?php echo htmlspecialchars($entry['request_uri'] ?? '-'); ?>
                            </td>
                            <td class="small text-end">
                                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#<?php echo $detailId; ?>" aria-expanded="false" aria-controls="<?php echo $detailId; ?>">
                                    Details
                                </button>
                            </td>
                        </tr>
                        <tr class="collapse bg-light detail-row" id="<?php echo $detailId; ?>">
                            <td colspan="7">
                                <div class="d-flex flex-column gap-2">
                                    <div>
                                        <strong>Full Message:</strong>
                                        <div class="text-break"><?php echo nl2br(htmlspecialchars($entry['message'])); ?></div>
                                    </div>
                                    <div class="small text-muted">
                                        <strong>File:</strong> <?php echo htmlspecialchars($entry['file_path'] ?? ''); ?>
                                        <?php if (!empty($entry['line_number'])) { ?>
                                            &middot; <strong>Line:</strong> <?php echo htmlspecialchars($entry['line_number']); ?>
                                        <?php } ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-3 small text-muted">
                                        <?php if (!empty($entry['reference'])) { ?>
                                            <span><strong>Reference:</strong> <?php echo htmlspecialchars($entry['reference']); ?></span>
                                        <?php } ?>
                                        <?php if (!empty($entry['request_host'])) { ?>
                                            <span><strong>Host:</strong> <?php echo htmlspecialchars($entry['request_host']); ?></span>
                                        <?php } ?>
                                        <?php if (!empty($entry['request_method'])) { ?>
                                            <span><strong>Method:</strong> <?php echo htmlspecialchars($entry['request_method']); ?></span>
                                        <?php } ?>
                                        <?php if (!empty($entry['request_uri'])) { ?>
                                            <span><strong>URI:</strong> <?php echo htmlspecialchars($entry['request_uri']); ?></span>
                                        <?php } ?>
                                        <?php if (!empty($entry['referer'])) { ?>
                                            <span><strong>Referer:</strong> <?php echo htmlspecialchars($entry['referer']); ?></span>
                                        <?php } ?>
                                        <?php if (!empty($entry['user_id']) || !empty($entry['user_name'])) { ?>
                                            <span><strong>User:</strong>
                                                <?php echo htmlspecialchars($entry['user_name'] ?? ''); ?>
                                                <?php if (!empty($entry['user_id'])) { ?> (ID: <?php echo htmlspecialchars($entry['user_id']); ?>)<?php } ?>
                                            </span>
                                        <?php } ?>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        <button class="btn btn-outline-secondary btn-sm copy-error" data-entry="<?php echo $entryJson; ?>">
                                            <i class="bi bi-clipboard"></i> Copy Details
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm ai-insight-btn" data-entry="<?php echo $entryJson; ?>">
                                            <i class="bi bi-stars"></i> AI Fix Advice
                                        </button>
                                    </div>
                                    <div class="ai-response alert alert-secondary d-none mb-0"></div>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3" id="error-log-pagination">
            <div class="small text-muted" id="error-log-count"></div>
            <div class="btn-group" role="group" aria-label="Pagination controls">
                <button class="btn btn-outline-secondary btn-sm" id="error-prev">Prev</button>
                <button class="btn btn-outline-secondary btn-sm" id="error-next">Next</button>
            </div>
        </div>
    </div>
</div>

<?php } else { ?>
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url'];?>/img/no-error-log.svg" alt="No errors logged." height="200">
    <h2 class="h4 mt-3 text-center">No error log for this date.</h2>
    <p class="text-muted">Select another date that contains an error log.</p>
</div>
<?php } ?>
</div>

<script>
(function() {
    const hourlyData = <?php echo json_encode($hourlyChart); ?>;
    const severityData = <?php echo json_encode($severityChart); ?>;
    const availableDates = <?php echo json_encode($availableDates); ?>;
    const availableSet = new Set(availableDates);
    const dateInput = document.getElementById('error-log-date');
    let lastValid = dateInput ? dateInput.value : '';

    if (dateInput) {
        dateInput.addEventListener('input', enforceAvailable);
        dateInput.addEventListener('change', enforceAvailable);
        document.querySelectorAll('.error-date-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                dateInput.value = btn.dataset.date;
                dateInput.form.submit();
            });
        });
    }

    function enforceAvailable(event) {
        const value = event.target.value;
        if (value && availableSet.has(value)) {
            lastValid = value;
            event.target.setCustomValidity('');
            return;
        }
        if (!value) { return; }
        event.target.value = lastValid;
        event.target.setCustomValidity('Select a date that has error log data.');
        event.target.reportValidity();
        setTimeout(() => event.target.setCustomValidity(''), 2500);
    }

    if (hourlyData.labels && hourlyData.labels.length) {
        const ctx = document.getElementById('errorHourlyChart');
        if (ctx) {
            window.RadAdminCharts.render(ctx, {
                type: 'line',
                data: {
                    labels: hourlyData.labels,
                    datasets: [{
                        label: 'Errors',
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220,53,69,0.1)',
                        tension: 0.3,
                        data: hourlyData.counts,
                        fill: true
                    }]
                },
                options: {
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Count' } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }
    }

    if (severityData.labels && severityData.labels.length) {
        const pieCtx = document.getElementById('severityChart');
        if (pieCtx) {
            window.RadAdminCharts.render(pieCtx, {
                type: 'doughnut',
                data: {
                    labels: severityData.labels,
                    datasets: [{
                        data: severityData.counts,
                        backgroundColor: ['#dc3545', '#ffc107', '#0d6efd', '#20c997', '#6f42c1'],
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

    const table = document.getElementById('error-log-table');
    if (!table) { return; }
    const tableBody = table.querySelector('tbody');
    const summaryRows = Array.from(tableBody.querySelectorAll('tr.log-row'));
    const detailMap = new Map();
    summaryRows.forEach(row => {
        const detailId = row.dataset.detail;
        if (detailId) {
            const detailRow = document.getElementById(detailId);
            if (detailRow) {
                detailMap.set(row, detailRow);
            }
        }
    });
    let filteredRows = summaryRows.slice();
    const pageSizeSelect = document.getElementById('error-page-size');
    const searchInput = document.getElementById('error-search');
    const prevBtn = document.getElementById('error-prev');
    const nextBtn = document.getElementById('error-next');
    const countLabel = document.getElementById('error-log-count');
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

        summaryRows.forEach(row => {
            row.classList.add('d-none');
            const detail = detailMap.get(row);
            if (detail) {
                detail.classList.add('pagination-hidden');
                detail.classList.remove('show');
            }
        });
        filteredRows.slice(start, end).forEach(row => {
            row.classList.remove('d-none');
            const detail = detailMap.get(row);
            if (detail) {
                detail.classList.remove('pagination-hidden');
            }
        });

        countLabel.textContent = total
            ? `Showing ${start + 1}-${Math.min(end, total)} of ${total} errors`
            : 'No matching entries';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || total === 0;
    }

    function applyFilters() {
        const query = searchInput.value.toLowerCase();
        filteredRows = summaryRows.filter(row => {
            const entryExtra = (row.dataset.entry || '').toLowerCase();
            return (row.textContent.toLowerCase() + entryExtra).includes(query);
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

    const analyzeUrl = '<?php echo $this->runData['route']['rad_admin_url']; ?>/errorlog/analyze';
    document.querySelectorAll('.copy-error').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.entry || '{}');
            const text = [
                `Timestamp: ${data.timestamp || ''}`,
                `Type: ${data.error_type || ''}`,
                data.error_code ? `Code: ${data.error_code}` : '',
                `File: ${data.file_path || ''}`,
                data.line_number ? `Line: ${data.line_number}` : '',
                `Message: ${data.message || ''}`
            ].filter(Boolean).join('\n');
            navigator.clipboard.writeText(text).then(() => {
                btn.textContent = 'Copied!';
                setTimeout(() => btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy Details', 1200);
            }).catch(() => alert('Unable to copy to clipboard.'));
        });
    });

    document.querySelectorAll('.ai-insight-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const data = JSON.parse(btn.dataset.entry || '{}');
            const container = btn.closest('.detail-row')?.querySelector('.ai-response');
            if (!container) { return; }
            container.classList.remove('d-none', 'alert-success', 'alert-danger');
            container.classList.add('alert-secondary');
            container.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating advice…';

            fetch(analyzeUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(async response => {
                const text = await response.text();
                let payload = {};
                if (text) {
                    try {
                        payload = JSON.parse(text);
                    } catch (err) {
                        throw new Error('Invalid JSON response: ' + text.substring(0, 120));
                    }
                }
                if (!response.ok) {
                    throw new Error(payload.error || ('HTTP ' + response.status));
                }
                return payload;
            })
            .then(res => {
                if (res && res.advice) {
                    container.classList.remove('alert-secondary', 'alert-danger');
                    container.classList.add('alert-success');
                    container.innerHTML = '<strong>AI Advice:</strong><br>' + renderMarkdown(res.advice);
                } else {
                    throw new Error(res.error || 'Could not generate advice.');
                }
            })
            .catch(err => {
                container.classList.remove('alert-secondary');
                container.classList.add('alert-danger');
                container.textContent = err.message || 'Unable to reach AI service.';
            });
        });
    });

    function renderMarkdown(text) {
        if (!text) {
            return '';
        }
        const escaped = String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        const fenceToken = '___CODEBLOCK___';
        const fences = [];
        let html = escaped.replace(/```([\s\S]*?)```/g, (_, code) => {
            fences.push(code.trim());
            return fenceToken;
        });

        html = html
            .replace(/^### (.+)$/gm, '<h6 class="mt-3">$1</h6>')
            .replace(/^## (.+)$/gm, '<h5 class="mt-3">$1</h5>')
            .replace(/^# (.+)$/gm, '<h4 class="mt-3">$1</h4>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/`([^`]+)`/g, '<code>$1</code>')
            .replace(/^\s*-\s+(.+)$/gm, '<li>$1</li>');

        html = html.replace(/(<li>[\s\S]*?<\/li>)/g, '<ul class="mb-2">$1</ul>');
        html = html.replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');
        html = '<p>' + html + '</p>';

        let idx = 0;
        html = html.replace(new RegExp(fenceToken, 'g'), () => {
            const code = fences[idx++] || '';
            return '<pre class="bg-light border rounded p-2"><code>' + code + '</code></pre>';
        });
        return html;
    }
})();
</script>
