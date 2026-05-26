<?php
$summary = $this->runData['data']['summary'] ?? [];
$vendorHygiene = $this->runData['data']['vendor_hygiene'] ?? ['outdated' => [], 'missing' => []];
$bindingsByType = $this->runData['data']['bindings_by_type'] ?? [];
$queues = $this->runData['data']['queues'] ?? [];
$branches = $this->runData['data']['branches'] ?? [];
$findings = $this->runData['data']['findings'] ?? [];
$recommendations = $this->runData['data']['recommendations'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Snapshot of security, dependency hygiene, access controls, jobs, and governance across your application.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/techdocs/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-richtext me-1"></i>Technical Docs</a>
            <a href="<?php echo $radAdminUrl; ?>/vendor/view" class="btn btn-outline-primary btn-sm"><i class="bi bi-puzzle me-1"></i>Vendor Libraries</a>
            <a href="<?php echo $radAdminUrl; ?>/devsecops/export/html" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-html me-1"></i>Export HTML</a>
            <a href="<?php echo $radAdminUrl; ?>/devsecops/export/pdf" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
        </div>
    </div>
</div>

<?php
$radAssetsUrl = $this->runData['route']['rad_assets_url'] ?? '';
$chartJsCdn = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
?>
<script src="<?php echo htmlspecialchars($chartJsCdn); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const assetsUrl = <?php echo json_encode($radAssetsUrl); ?>;
    const metricsUrl = <?php echo json_encode($radAdminUrl . '/devsecops/metrics'); ?>;
    let charts = {};

    const downloadButtons = Array.from(document.querySelectorAll('[data-chart-download]'));

    const colors = {
        green: '#2ecc71',
        orange: '#f39c12',
        red: '#e74c3c',
        blue: '#3498db',
        gray: '#95a5a6'
    };

    downloadButtons.forEach(btn => btn.disabled = true);

    fetch(metricsUrl, { headers: {'Accept': 'application/json'} })
        .then(resp => resp.ok ? resp.json() : Promise.reject(new Error('Metrics load failed')))
        .then(renderCharts)
        .catch((err) => {
            console.error('DevSecOps metrics error', err);
            downloadButtons.forEach(btn => btn.disabled = true);
        });

    function renderCharts(data) {
        charts.severity = new Chart(document.getElementById('chart-severity'), {
            type: 'doughnut',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    data: [
                        data.severity?.high || 0,
                        data.severity?.medium || 0,
                        data.severity?.low || 0
                    ],
                    backgroundColor: [colors.red, colors.orange, colors.gray]
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}}
        });

        charts.vendor = new Chart(document.getElementById('chart-vendor'), {
            type: 'bar',
            data: {
                labels: ['Up-to-date', 'Outdated', 'Missing'],
                datasets: [{
                    label: 'Libraries',
                    data: [
                        data.vendor?.up_to_date || 0,
                        data.vendor?.outdated || 0,
                        data.vendor?.missing || 0
                    ],
                    backgroundColor: [colors.green, colors.orange, colors.blue]
                }]
            },
            options: {plugins: {legend: {display: false}}}
        });

        charts.access = new Chart(document.getElementById('chart-access'), {
            type: 'bar',
            data: {
                labels: ['Microservicelets', 'Routes'],
                datasets: [{
                    label: 'Bound',
                    data: [
                        data.access?.microservices_bound || 0,
                        data.access?.routes_bound || 0
                    ],
                    backgroundColor: colors.green
                }, {
                    label: 'Unbound',
                    data: [
                        Math.max(0, (data.access?.microservices_total || 0) - (data.access?.microservices_bound || 0)),
                        Math.max(0, (data.access?.routes_total || 0) - (data.access?.routes_bound || 0))
                    ],
                    backgroundColor: colors.red
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}, scales: {x: {stacked: true}, y: {stacked: true}}}
        });

        charts.jobs = new Chart(document.getElementById('chart-jobs'), {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failure', 'Unknown'],
                datasets: [{
                    data: [
                        data.jobs?.success || 0,
                        data.jobs?.failure || 0,
                        data.jobs?.unknown || 0
                    ],
                    backgroundColor: [colors.green, colors.red, colors.gray]
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}}
        });

        downloadButtons.forEach(btn => btn.disabled = false);
    }

    const triggerDownload = (key) => {
        const chart = charts[key];
        if (!chart || !chart.canvas) {
            alert('Chart not ready yet. Please wait for data to load.');
            return;
        }
        try {
            const dataUrl = chart.canvas.toDataURL('image/png');
            if (!dataUrl) {
                throw new Error('toDataURL returned empty');
            }
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'devsecops-' + key + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            const newWin = window.open(dataUrl, '_blank');
            if (newWin) { newWin.opener = null; }
        } catch (e) {
            console.error('Chart download failed', e);
            alert('Unable to download chart PNG right now. Please try again after the page fully loads.');
        }
    };

    document.querySelectorAll('[data-chart-download]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const key = btn.getAttribute('data-chart-download');
            try {
                triggerDownload(key);
            } catch (e) {
                console.error('Chart download failed', e);
                alert('Unable to download chart PNG right now. Please try again after the page fully loads.');
            }
        });
    });
});
</script>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Visualizations</h3>
        <p class="text-muted small mb-0">Findings by severity, dependency hygiene, access coverage, and job status.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $charts = [
                ['id' => 'severity', 'title' => 'Findings by Severity'],
                ['id' => 'vendor', 'title' => 'Dependency Hygiene'],
                ['id' => 'access', 'title' => 'Access Coverage'],
                ['id' => 'jobs', 'title' => 'Job Status'],
            ];
            foreach ($charts as $chart) { ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small"><?php echo htmlspecialchars($chart['title']); ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-chart-download="<?php echo htmlspecialchars($chart['id']); ?>">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <canvas id="chart-<?php echo htmlspecialchars($chart['id']); ?>" height="180"></canvas>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['label' => 'Microservicelets', 'value' => $summary['microservices'] ?? 0, 'icon' => 'bi bi-boxes', 'tone' => 'primary'],
        ['label' => 'Routes', 'value' => $summary['routes'] ?? 0, 'icon' => 'bi bi-signpost-2', 'tone' => 'secondary'],
        ['label' => 'Controllers', 'value' => $summary['controllers'] ?? 0, 'icon' => 'bi bi-diagram-3', 'tone' => 'info'],
        ['label' => 'Vendors', 'value' => $summary['vendors'] ?? 0, 'icon' => 'bi bi-puzzle', 'tone' => 'dark'],
        ['label' => 'Outdated Vendors', 'value' => $summary['vendors_outdated'] ?? 0, 'icon' => 'bi bi-exclamation-diamond', 'tone' => 'warning'],
        ['label' => 'Roles', 'value' => $summary['roles'] ?? 0, 'icon' => 'bi bi-person-badge', 'tone' => 'success'],
        ['label' => 'Bindings', 'value' => $summary['bindings'] ?? 0, 'icon' => 'bi bi-key', 'tone' => 'danger'],
        ['label' => 'Jobs', 'value' => $summary['queues'] ?? 0, 'icon' => 'bi bi-clock-history', 'tone' => 'primary'],
        ['label' => 'Branch Events', 'value' => $summary['branches'] ?? 0, 'icon' => 'bi bi-git', 'tone' => 'info'],
    ];
    foreach ($statCards as $card) { ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="badge bg-<?php echo $card['tone']; ?> rounded-circle p-3"><i class="<?php echo $card['icon']; ?>"></i></span>
                <div>
                    <div class="text-muted text-uppercase small"><?php echo $card['label']; ?></div>
                    <div class="h4 mb-0"><?php echo (int)$card['value']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Findings</h3>
                    <p class="text-muted small mb-0">Security, dependency, and operations findings with severity.</p>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($findings)) { ?>
                    <p class="text-muted mb-0">No findings detected in this snapshot.</p>
                <?php } else { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($findings as $finding) { ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($finding['title'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($finding['category'] ?? ''); ?></div>
                                        <?php if (!empty($finding['items'])) { ?>
                                            <ul class="small mb-0 mt-1">
                                                <?php foreach ($finding['items'] as $item) { ?>
                                                    <li><?php echo htmlspecialchars($item); ?></li>
                                                <?php } ?>
                                            </ul>
                                        <?php } ?>
                                    </div>
                                    <span class="badge bg-<?php echo ($finding['severity'] ?? '') === 'high' ? 'danger' : (($finding['severity'] ?? '') === 'medium' ? 'warning' : 'secondary'); ?>">
                                        <?php echo ucfirst($finding['severity'] ?? ''); ?>
                                    </span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Recommendations</h3>
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach ($recommendations as $rec) { ?>
                        <li class="text-muted small"><?php echo htmlspecialchars($rec); ?></li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Dependency Hygiene</h3>
                    <p class="text-muted small mb-0">Vendor libraries with version gaps or missing installs.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/vendor/view" class="btn btn-outline-secondary btn-sm">Vendor Library</a>
            </div>
            <div class="card-body">
                <?php $outdated = $vendorHygiene['outdated'] ?? []; $missing = $vendorHygiene['missing'] ?? []; ?>
                <?php if (empty($outdated) && empty($missing)) { ?>
                    <p class="text-muted mb-0">All vendor libraries are aligned with available versions.</p>
                <?php } else { ?>
                    <?php if (!empty($outdated)) { ?>
                        <h6 class="fw-semibold">Outdated</h6>
                        <ul class="small">
                            <?php foreach ($outdated as $v) { ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($v['s_title'] ?? $v['s_handle'] ?? ''); ?></strong>
                                    <span class="text-muted">Installed: <?php echo htmlspecialchars($v['s_version_installed'] ?? 'n/a'); ?> · Available: <?php echo htmlspecialchars($v['s_version_available'] ?? 'n/a'); ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                    <?php if (!empty($missing)) { ?>
                        <h6 class="fw-semibold mt-3">Not Installed</h6>
                        <ul class="small">
                            <?php foreach ($missing as $v) { ?>
                                <li><?php echo htmlspecialchars($v['s_title'] ?? $v['s_handle'] ?? ''); ?></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Access & Permissions</h3>
                    <p class="text-muted small mb-0">Bindings across microservicelets, routes, and navigation.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/permissionbindings/view" class="btn btn-outline-secondary btn-sm">Bindings</a>
            </div>
            <div class="card-body">
                <?php if (empty($bindingsByType)) { ?>
                    <p class="text-muted mb-0">No permission bindings recorded.</p>
                <?php } else { ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($bindingsByType as $type => $list) { ?>
                            <li class="mb-2">
                                <span class="badge bg-light text-dark text-uppercase"><?php echo htmlspecialchars($type); ?></span>
                                <span class="text-muted small ms-1"><?php echo count($list); ?> binding(s)</span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Jobs & Schedules</h3>
            <p class="text-muted small mb-0">Cron/queue status with next execution times.</p>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($queues)) { ?>
            <p class="text-muted mb-0">No scheduled jobs configured.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Job</th>
                            <th>Status</th>
                            <th>Last Executed</th>
                            <th>Next Execution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($queues as $q) { ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($q['s_queue_title'] ?? $q['s_queue_script_name'] ?? 'Job'); ?></div>
                                <div class="text-muted small text-monospace"><?php echo htmlspecialchars($q['s_queue_script_name'] ?? ''); ?></div>
                            </td>
                            <td>
                                <?php $status = strtolower($q['s_queue_status'] ?? ''); ?>
                                <span class="badge bg-<?php echo $status === 'failure' ? 'danger' : ($status === 'success' ? 'success' : 'secondary'); ?>">
                                    <?php echo $status === '' ? 'Unknown' : ucfirst($status); ?>
                                </span>
                                <?php if (!empty($q['s_error_message'])) { ?>
                                    <div class="text-muted small"><?php echo htmlspecialchars($q['s_error_message']); ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($q['s_last_executed'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($q['s_next_execution'] ?? ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Branch Events</h3>
            <p class="text-muted small mb-0">Latest beta/live branch actions across routes and microservicelets.</p>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($branches)) { ?>
            <p class="text-muted mb-0">No branch activity recorded yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Object</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th>Note</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $entry) { ?>
                            <tr>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars($entry['s_object_type'] ?? ''); ?> #<?php echo (int)($entry['s_object_id'] ?? 0); ?>
                                </td>
                                <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($entry['s_branch'] ?? 'beta'); ?></span></td>
                                <td><?php echo htmlspecialchars($entry['s_status'] ?? ''); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($entry['s_note'] ?? ''); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($entry['createstamp'] ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
