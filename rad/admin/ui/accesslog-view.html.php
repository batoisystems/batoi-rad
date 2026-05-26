<?php
$accessLog = $this->runData['data']['accesslog'];
$requestGroups = $this->runData['data']['request_groups'];
$metrics = $this->runData['data']['metrics'];
$hourlyChart = $this->runData['data']['hourly_chart'];
$topEndpoints = $this->runData['data']['top_endpoints'];
$selectedDate = $this->runData['data']['selected_date'];
$availableDates = $this->runData['data']['date_options'] ?? [];
$highlightDates = array_slice($availableDates, 0, 12);
$maxDate = $availableDates[0] ?? $selectedDate;
$minDate = $availableDates[array_key_last($availableDates)] ?? $selectedDate;
$entryLimit = (int)($this->runData['data']['entry_limit'] ?? 0);
$filters = $this->runData['data']['filters'] ?? ['date' => $selectedDate];
$rangeDays = (int)($filters['range_days'] ?? 1);
$hourFilter = $filters['hour'] ?? '';
$searchQuery = $filters['q'] ?? '';
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);
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

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="log-date" class="form-label">Select Date</label>
                <input type="date"
                       name="date"
                       id="log-date"
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
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" placeholder="URI, user, IP, session" value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i>Load</button>
                <?php if ($hasFilters) { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/accesslog/view" class="btn btn-outline-secondary w-100">Reset</a>
                <?php } ?>
            </div>
            <div class="col-md-3 ms-auto text-end d-flex justify-content-end gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/accesslog/exportcsv?<?php echo htmlspecialchars($exportQuery); ?>">
                    <i class="bi bi-download"></i> Export CSV
                </a>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#accessPurgeModal">
                    <i class="bi bi-trash"></i> Purge Logs
                </button>
            </div>
            <?php if (!empty($availableDates)) { ?>
            <div class="col-12">
                <div class="form-text mb-1">Dates with access logs</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($highlightDates as $dateOption) { 
                        $isSelected = $dateOption === $selectedDate;
                        $formatted = \DateTime::createFromFormat('Y-m-d', $dateOption);
                        $label = $formatted ? $formatted->format('M j') : $dateOption;
                    ?>
                    <button type="button"
                            class="btn btn-sm <?php echo $isSelected ? 'btn-primary text-white' : 'btn-outline-secondary'; ?> access-date-pill"
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

<div class="modal fade" id="accessPurgeModal" tabindex="-1" aria-labelledby="accessPurgeModalLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/accesslog/purge">
                <div class="modal-header">
                    <h5 class="modal-title" id="accessPurgeModalLabel">Purge Old Access Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Select a window to permanently delete access logs older than the chosen period.</p>
                    <div class="mb-3">
                        <label for="access-purge-window" class="form-label">Remove logs older than</label>
                        <select class="form-select" id="access-purge-window" name="purge_window" required>
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
                    <div class="alert alert-warning mb-0" id="access-purge-warning">
                        Select a window to see what will be removed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="access-purge-confirm" disabled>
                        <i class="bi bi-exclamation-triangle"></i> Confirm Purge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($requestGroups)) { ?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Requests</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['requests']); ?></div>
                <div class="text-muted small">Total hits for <?php echo htmlspecialchars($selectedDate); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Avg. Execution Time</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['avg_time'], 4); ?>s</div>
                <div class="text-muted small">Across all tracked requests</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-2">Unique IPs</div>
                <div class="display-6 fw-semibold"><?php echo number_format($metrics['unique_ips']); ?></div>
                <div class="text-muted small">Distinct sources generating traffic</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Requests & Response Time</h5>
                    <span class="badge bg-light text-dark">hourly</span>
                </div>
                <canvas id="trafficChart" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <h5 class="card-title mb-3">Top Endpoints</h5>
                <?php if (!empty($topEndpoints)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($topEndpoints as $endpoint) { ?>
                            <li class="list-group-item d-flex flex-column">
                                <div class="fw-semibold text-truncate" title="<?php echo htmlspecialchars($endpoint['uri']); ?>">
                                    <?php echo htmlspecialchars($endpoint['uri']); ?>
                                </div>
                                <div class="d-flex justify-content-between text-muted small">
                                    <span><?php echo number_format($endpoint['count']); ?> hits</span>
                                    <span><?php echo number_format($endpoint['avg_time'], 4); ?>s avg</span>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted mb-0">No endpoint data available.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-2">Request Details</h5>
            <div class="d-flex gap-2 mb-2">
                <input type="search" id="log-search" class="form-control" placeholder="Search logs…">
                <select id="page-size" class="form-select" style="width: 120px;" data-pref="<?php echo $perPagePref; ?>">
                    <?php foreach ([25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?> / page</option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="access-log-table">
                <thead class="table-light">
                    <tr>
                        <th class="small">Timestamp</th>
                        <th class="small">Request</th>
                        <th class="small">User</th>
                        <th class="small">Assets</th>
                        <th class="small text-end">Execution Time (s)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($requestGroups as $rowIndex => $group) {
                        $parent = $group['parent'] ?? null;
                        $children = $group['children'] ?? [];
                        $primary = $parent ?? ($children[0] ?? []);
                        $detailId = 'log-detail-' . $rowIndex;
                        $userAgentRaw = $primary['user_agent'] ?? '';
                        preg_match('/\(([^)]+)\)/', $userAgentRaw, $osInfo);
                        preg_match('/(Chrome|Firefox|Safari|Edge)\/([\d\.]+)/', $userAgentRaw, $browserInfo);
                        $userAgentPretty = [];
                        if (!empty($osInfo[1])) {
                            $userAgentPretty[] = $osInfo[1];
                        }
                        if (!empty($browserInfo[1])) {
                            $userAgentPretty[] = $browserInfo[1] . ' ' . ($browserInfo[2] ?? '');
                        }
                        $uaLabel = $userAgentPretty ? implode(' • ', $userAgentPretty) : $userAgentRaw;
                        $queryString = $primary['query_string'] ?? '';
                        $methodLabel = strtoupper($primary['method'] ?? 'GET');
                        $assetSummaryParts = [];
                        if (!empty($group['asset_types'])) {
                            foreach ($group['asset_types'] as $label => $count) {
                                $assetSummaryParts[] = $count . ' ' . $label;
                            }
                        }
                        $searchPieces = [
                            $primary['timestamp'] ?? '',
                            $primary['ip'] ?? '',
                            $primary['uri'] ?? '',
                            $primary['path'] ?? '',
                            $queryString,
                            $primary['user_agent'] ?? '',
                            $primary['user_label'] ?? '',
                            $primary['user_id'] ?? '',
                        ];
                        foreach ($children as $child) {
                            $searchPieces[] = $child['uri'] ?? '';
                        }
                        $searchAttr = strtolower(implode(' ', array_filter($searchPieces, static function ($item) {
                            return $item !== '';
                        })));
                        $userLabel = $primary['user_label'] ?? 'Guest';
                        $username = $primary['username'] ?? '';
                        $childCount = $group['child_count'] ?? count($children);
                        $isAssetOnly = !$parent;
                    ?>
                    <tr class="log-row-summary" data-detail-id="<?php echo $detailId; ?>" data-search="<?php echo htmlspecialchars($searchAttr, ENT_QUOTES); ?>">
                        <td class="small text-nowrap">
                            <button class="btn btn-link btn-sm p-0 me-2 log-toggle collapsed" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#<?php echo $detailId; ?>"
                                    aria-expanded="false" aria-controls="<?php echo $detailId; ?>">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="fw-semibold"><?php echo htmlspecialchars($primary['timestamp'] ?? ''); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($primary['ip'] ?? ''); ?></div>
                        </td>
                        <td class="small">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-uppercase bg-primary-subtle text-primary fw-semibold"><?php echo htmlspecialchars($methodLabel); ?></span>
                                <div class="text-truncate" style="max-width: 260px;">
                                    <span class="fw-semibold"><?php echo htmlspecialchars($primary['path'] ?? $primary['uri'] ?? '/'); ?></span>
                                    <?php if ($queryString !== '') { ?>
                                        <span class="text-muted">?<?php echo htmlspecialchars($queryString); ?></span>
                                    <?php } ?>
                                    <?php if ($isAssetOnly) { ?>
                                        <span class="badge bg-warning-subtle text-warning ms-1">asset</span>
                                    <?php } ?>
                                </div>
                                <a href="<?php echo htmlspecialchars($primary['full_url'] ?? $primary['uri'] ?? '#'); ?>" class="text-decoration-none" target="_blank" rel="noopener" title="Open request">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </td>
                        <td class="small">
                            <div class="d-flex align-items-center gap-2">
                                <div class="fw-semibold mb-0"><?php echo htmlspecialchars($userLabel); ?></div>
                                <?php if (!empty($primary['user_id'])) { ?>
                                    <span class="badge bg-light text-dark border">#<?php echo (int)$primary['user_id']; ?></span>
                                <?php } ?>
                            </div>
                            <div class="text-muted small">
                                <?php if ($username) { ?>
                                    <span class="me-2 text-body-secondary">@<?php echo htmlspecialchars($username); ?></span>
                                <?php } ?>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;"><?php echo htmlspecialchars($uaLabel); ?></span>
                            </div>
                        </td>
                        <td class="small">
                            <?php if ($childCount) { ?>
                                <div class="fw-semibold"><?php echo (int)$childCount; ?> dependent</div>
                                <?php if ($assetSummaryParts) { ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars(implode(', ', $assetSummaryParts)); ?></div>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-muted">-</span>
                            <?php } ?>
                        </td>
                        <td class="small text-end"><?php echo number_format($primary['execution_time'] ?? 0, 4); ?></td>
                    </tr>
                    <tr class="log-row-detail collapse bg-body-tertiary" id="<?php echo $detailId; ?>">
                        <td colspan="5" class="border-top">
                            <div class="row g-4 small py-3">
                                <?php if ($isAssetOnly) { ?>
                                    <div class="col-12">
                                        <div class="alert alert-warning py-2 mb-0">
                                            Asset request captured without a preceding page view (possibly due to caching or CDN prefetch).
                                        </div>
                                    </div>
                                <?php } ?>
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small mb-1">Full URL</div>
                                    <div class="text-break">
                                        <a href="<?php echo htmlspecialchars($primary['full_url'] ?? '#'); ?>" target="_blank" rel="noopener">
                                            <?php echo htmlspecialchars($primary['full_url'] ?? $primary['uri'] ?? ''); ?>
                                        </a>
                                    </div>
                                    <?php if (!empty($primary['fragment'])) { ?>
                                        <div class="text-muted mt-2">Fragment: <span class="fw-semibold">#<?php echo htmlspecialchars($primary['fragment']); ?></span></div>
                                    <?php } ?>
                                    <div class="mt-3">
                                        <div class="text-uppercase text-muted small">Execution</div>
                                        <div class="fw-semibold"><?php echo number_format($primary['execution_time'] ?? 0, 4); ?> seconds</div>
                                        <?php if ($childCount) { ?>
                                            <div class="text-muted small">Dependencies: <?php echo (int)$childCount; ?></div>
                                        <?php } ?>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small mb-2">Query Parameters</div>
                                    <?php if (!empty($primary['query_params'])) { ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($primary['query_params'] as $paramKey => $paramValue) { 
                                                $displayValue = is_array($paramValue) ? json_encode($paramValue) : (string)$paramValue;
                                            ?>
                                                <li class="mb-1">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($paramKey); ?></span>
                                                    <span class="text-muted">= <?php echo htmlspecialchars($displayValue); ?></span>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } else { ?>
                                        <p class="text-muted mb-0">No query parameters supplied.</p>
                                    <?php } ?>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small mb-1">Client Context</div>
                                    <div class="mb-2"><span class="text-muted">IP:</span> <span class="fw-semibold"><?php echo htmlspecialchars($primary['ip'] ?? ''); ?></span></div>
                                    <div class="mb-2">
                                        <span class="text-muted">User:</span>
                                        <?php if (!empty($primary['user_id'])) { ?>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($userLabel); ?></span>
                                            <span class="badge bg-secondary-subtle text-secondary ms-2">#<?php echo (int)$primary['user_id']; ?></span>
                                        <?php } else { ?>
                                            <span class="fw-semibold">Guest (not signed in)</span>
                                        <?php } ?>
                                    </div>
                                    <?php if ($username) { ?>
                                        <div class="mb-2"><span class="text-muted">Username:</span> <span class="fw-semibold"><?php echo htmlspecialchars($username); ?></span></div>
                                    <?php } ?>
                                    <?php if (!empty($primary['referrer'])) { ?>
                                        <div class="mb-2"><span class="text-muted">Referrer:</span> <span class="text-break"><?php echo htmlspecialchars($primary['referrer']); ?></span></div>
                                    <?php } ?>
                                    <div class="text-muted small mb-1">User Agent</div>
                                    <div class="border rounded bg-white p-2 text-break"><?php echo htmlspecialchars($primary['user_agent'] ?? ''); ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="text-uppercase text-muted small">Dependent Requests</div>
                                        <span class="badge bg-light text-dark"><?php echo (int)$childCount; ?> assets</span>
                                    </div>
                                    <?php if (!empty($children)) { ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped align-middle">
                                                <thead>
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>URI</th>
                                                        <th>Method</th>
                                                        <th class="text-end">Exec (s)</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($children as $child) { 
                                                        $childQuery = $child['query_string'] ?? '';
                                                        $childType = $child['asset_type'] ?? '';
                                                    ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($childType); ?></td>
                                                        <td>
                                                            <div class="text-truncate" style="max-width: 320px;">
                                                                <span class="fw-semibold"><?php echo htmlspecialchars($child['path'] ?? $child['uri'] ?? ''); ?></span>
                                                                <?php if ($childQuery !== '') { ?>
                                                                    <span class="text-muted">?<?php echo htmlspecialchars($childQuery); ?></span>
                                                                <?php } ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(strtoupper($child['method'] ?? 'GET')); ?></td>
                                                        <td class="text-end"><?php echo number_format($child['execution_time'] ?? 0, 4); ?></td>
                                                    </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } else { ?>
                                        <p class="text-muted mb-0">No dependent assets captured for this request.</p>
                                    <?php } ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3" id="log-pagination">
            <div class="small text-muted" id="log-count"></div>
            <div class="btn-group" role="group" aria-label="Pagination controls">
                <button class="btn btn-outline-secondary btn-sm" id="prev-page">Prev</button>
                <button class="btn btn-outline-secondary btn-sm" id="next-page">Next</button>
            </div>
        </div>
    </div>
</div>

<?php } else { ?>
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url'];?>/img/no-access-log.svg" alt="No access log for this date." height="200">
    <h2 class="h4 mt-3 text-center">No access log for the selected date.</h2>
    <p class="text-muted">Choose a different day from the filter above.</p>
</div>
<?php } ?>

<style>
.log-toggle .bi {
    transition: transform 0.2s ease;
}
.log-toggle:not(.collapsed) .bi {
    transform: rotate(180deg);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const hourlyData = <?php echo json_encode($hourlyChart); ?>;
    const dateInput = document.getElementById('log-date');
    const availableDates = <?php echo json_encode($availableDates); ?>;
    const availableSet = new Set(availableDates);
    let lastValidDate = dateInput ? dateInput.value : '';

    if (dateInput) {
        dateInput.addEventListener('input', enforceAvailableDates);
        dateInput.addEventListener('change', enforceAvailableDates);
        document.querySelectorAll('.access-date-pill').forEach(btn => {
            btn.addEventListener('click', () => {
                dateInput.value = btn.dataset.date;
                dateInput.form.submit();
            });
        });
    }

    function enforceAvailableDates(event) {
        const value = event.target.value;
        if (value === '') { return; }
        if (availableSet.has(value)) {
            lastValidDate = value;
            event.target.setCustomValidity('');
            return;
        }
        event.target.value = lastValidDate;
        event.target.setCustomValidity('Select a date that has access log data.');
        event.target.reportValidity();
        setTimeout(() => event.target.setCustomValidity(''), 2500);
    }

    if (hourlyData.labels && hourlyData.labels.length) {
        const ctx = document.getElementById('trafficChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: hourlyData.labels,
                    datasets: [
                        {
                            label: 'Requests',
                            backgroundColor: 'rgba(13,110,253,0.6)',
                            borderColor: 'rgba(13,110,253,1)',
                            borderWidth: 1,
                            data: hourlyData.requests
                        },
                        {
                            label: 'Avg Time (s)',
                            type: 'line',
                            borderColor: '#ffc107',
                            backgroundColor: 'rgba(255,193,7,0.2)',
                            yAxisID: 'y1',
                            data: hourlyData.avg_time
                        }
                    ]
                },
                options: {
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Requests' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Seconds' } }
                    },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: true }
                    }
                }
            });
        }
    }

    const purgeSelect = document.getElementById('access-purge-window');
    const purgeWarning = document.getElementById('access-purge-warning');
    const purgeConfirm = document.getElementById('access-purge-confirm');
    if (purgeSelect && purgeWarning && purgeConfirm) {
        purgeSelect.addEventListener('change', function() {
            const label = this.options[this.selectedIndex]?.text || '';
            if (label) {
                purgeWarning.textContent = 'This will delete access logs ' + label.toLowerCase() + '. This action cannot be undone.';
                purgeConfirm.disabled = false;
            } else {
                purgeWarning.textContent = 'Select a window to see what will be removed.';
                purgeConfirm.disabled = true;
            }
        });
    }

    const table = document.getElementById('access-log-table');
    if (!table) { return; }
    const tableBody = table.querySelector('tbody');
    const summaryRows = Array.from(tableBody.querySelectorAll('tr.log-row-summary'));
    const detailMap = new Map();
    summaryRows.forEach(row => {
        const detailId = row.dataset.detailId;
        if (detailId) {
            const detailRow = tableBody.querySelector('#' + detailId);
            if (detailRow) {
                detailMap.set(row, detailRow);
            }
        }
    });
    let filteredRows = summaryRows.slice();
    const pageSizeSelect = document.getElementById('page-size');
    const searchInput = document.getElementById('log-search');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const countLabel = document.getElementById('log-count');
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

        summaryRows.forEach(row => row.classList.add('d-none'));
        detailMap.forEach(detail => detail.classList.add('d-none'));

        filteredRows.slice(start, end).forEach(row => {
            row.classList.remove('d-none');
            const detail = detailMap.get(row);
            if (detail) {
                detail.classList.remove('d-none');
            }
        });

        countLabel.textContent = total
            ? `Showing ${start + 1}-${Math.min(end, total)} of ${total} requests`
            : 'No matching requests';

        prevBtn.disabled = currentPage === 1;
        nextBtn.disabled = currentPage === totalPages || total === 0;
    }

    function applyFilters() {
        const query = searchInput.value.toLowerCase();
        filteredRows = summaryRows.filter(row => {
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
