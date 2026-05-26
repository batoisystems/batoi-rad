<?php
$config = $this->runData['data']['telemetry_config'] ?? [];
$summary = $this->runData['data']['telemetry_summary'] ?? [];
$events = $this->runData['data']['telemetry_events'] ?? [];
$tokens = $this->runData['data']['telemetry_tokens'] ?? [];
$priv = $this->runData['data']['privilege_flags'] ?? ['settings' => false, 'manage_tokens' => false];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$activityLastRun = $this->runData['data']['activity_ingest_last_run'] ?? '';
$activityLastRange = $this->runData['data']['activity_ingest_last_range'] ?? null;
$enabled = ($config['enabled'] ?? 'Y') === 'Y';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <h2 class="h4 mb-1">Telemetry</h2>
            <p class="text-muted mb-0">Capture and review application telemetry (requests, errors, jobs, custom events). External access available via system APIs.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/telemetry/export/html" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-html me-1"></i>Export HTML</a>
            <a href="<?php echo $radAdminUrl; ?>/telemetry/export/pdf" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
            <?php if ($priv['settings']) { ?>
                <form method="post" action="<?php echo $radAdminUrl; ?>/telemetry/view" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <input type="hidden" name="telemetry_ingest_logs" value="1">
                    <input type="hidden" name="ingest_date" value="">
                    <button type="submit" class="btn btn-outline-secondary btn-sm" <?php echo $enabled ? '' : 'disabled'; ?>><i class="bi bi-cloud-download me-1"></i>Ingest from Logs (today)</button>
                </form>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Last Activity Ingest</div>
                <div class="fw-semibold"><?php echo $activityLastRun !== '' ? htmlspecialchars($activityLastRun) : '—'; ?></div>
                <?php if (!empty($activityLastRange['start']) || !empty($activityLastRange['end'])) { ?>
                    <div class="small text-muted">
                        Range: <?php echo htmlspecialchars(($activityLastRange['start'] ?? '') . ' → ' . ($activityLastRange['end'] ?? '')); ?>
                    </div>
                <?php } ?>
                <div class="small text-muted mt-2">
                    Manage queue runs from <a href="<?php echo $radAdminUrl; ?>/queue/overview">Queue Overview</a>.
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$chartJsCdn = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
?>
<script src="<?php echo htmlspecialchars($chartJsCdn); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const metricsUrl = <?php echo json_encode($radAdminUrl . '/telemetry/metrics'); ?>;
    const charts = {};
    const downloadButtons = Array.from(document.querySelectorAll('[data-telemetry-download]'));
    downloadButtons.forEach(btn => btn.disabled = true);

    const colors = {
        red: '#e74c3c',
        orange: '#f39c12',
        green: '#2ecc71',
        blue: '#0d6efd',
        gray: '#95a5a6'
    };

    fetch(metricsUrl, { headers: { 'Accept': 'application/json' } })
        .then(resp => resp.ok ? resp.json() : Promise.reject(new Error('Metrics load failed')))
        .then(renderCharts)
        .catch(err => console.error('Telemetry metrics error', err));

    function renderCharts(data) {
        charts.severity = new Chart(document.getElementById('telemetry-chart-severity'), {
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

        const compLabels = Object.keys(data.components || {});
        const compCounts = Object.values(data.components || {});
        charts.components = new Chart(document.getElementById('telemetry-chart-components'), {
            type: 'bar',
            data: {
                labels: compLabels,
                datasets: [{
                    label: 'Events',
                    data: compCounts,
                    backgroundColor: colors.blue,
                }]
            },
            options: {plugins: {legend: {display: false}}, scales: {x: {ticks: {autoSkip: false}}}}
        });

        charts.events = new Chart(document.getElementById('telemetry-chart-events'), {
            type: 'bar',
            data: {
                labels: ['Events'],
                datasets: [{
                    label: 'Count',
                    data: [data.events || 0],
                    backgroundColor: colors.green,
                }]
            },
            options: {plugins: {legend: {display: false}}}
        });

        downloadButtons.forEach(btn => btn.disabled = false);
    }

    const triggerDownload = (key) => {
        const chart = charts[key];
        if (!chart || !chart.canvas) {
            alert('Chart not ready yet. Please wait for data to load.');
            return;
        }
        const dataUrl = chart.canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.href = dataUrl;
        link.download = 'telemetry-' + key + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        const newWin = window.open(dataUrl, '_blank');
        if (newWin) { newWin.opener = null; }
    };

    document.querySelectorAll('[data-telemetry-download]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const key = btn.getAttribute('data-telemetry-download');
            triggerDownload(key);
        });
    });
});
</script>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Visualizations</h3>
        <p class="text-muted small mb-0">Severity mix, component spread, and total events.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $charts = [
                ['id' => 'severity', 'title' => 'Severity'],
                ['id' => 'components', 'title' => 'Components'],
                ['id' => 'events', 'title' => 'Event Volume'],
            ];
            foreach ($charts as $chart) { ?>
            <div class="col-12 col-md-4">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small"><?php echo htmlspecialchars($chart['title']); ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-telemetry-download="<?php echo htmlspecialchars($chart['id']); ?>">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <canvas id="telemetry-chart-<?php echo htmlspecialchars($chart['id']); ?>" height="180"></canvas>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['label' => 'Events', 'value' => $summary['events'] ?? 0, 'icon' => 'bi bi-activity', 'tone' => 'primary'],
        ['label' => 'High', 'value' => $summary['high'] ?? 0, 'icon' => 'bi bi-exclamation-octagon', 'tone' => 'danger'],
        ['label' => 'Medium', 'value' => $summary['medium'] ?? 0, 'icon' => 'bi bi-exclamation-triangle', 'tone' => 'warning'],
        ['label' => 'Low', 'value' => $summary['low'] ?? 0, 'icon' => 'bi bi-exclamation-circle', 'tone' => 'secondary'],
    ];
    foreach ($statCards as $card) { ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="badge bg-<?php echo $card['tone']; ?> rounded-circle p-3"><i class="<?php echo $card['icon']; ?>"></i></span>
                <div>
                    <div class="text-muted text-uppercase small"><?php echo htmlspecialchars($card['label']); ?></div>
                    <div class="h5 mb-0"><?php echo htmlspecialchars((string)$card['value']); ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Settings</h3>
    </div>
    <div class="card-body">
        <?php if ($priv['settings']) { ?>
            <form method="post" action="<?php echo $radAdminUrl; ?>/telemetry/view">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                <input type="hidden" name="telemetry_config_save" value="1">
                <div class="row g-3">
                    <div class="col-md-2">
                        <div class="form-check mt-4 pt-2">
                            <input class="form-check-input" type="checkbox" name="enabled" id="telemetry-enabled" value="Y" <?php echo ($config['enabled'] ?? 'Y') === 'Y' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="telemetry-enabled">Enabled</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sampling Rate (%)</label>
                        <input type="number" name="sampling_rate" class="form-control" value="<?php echo htmlspecialchars($config['sampling_rate'] ?? 100); ?>" min="0" max="100">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Retention (days)</label>
                        <input type="number" name="retention_days" class="form-control" value="<?php echo htmlspecialchars($config['retention_days'] ?? 30); ?>" min="1" max="365">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Collectors</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="collect_requests" id="collect-requests" value="Y" <?php echo ($config['collect_requests'] ?? 'Y') === 'Y' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="collect-requests">Requests</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="collect_errors" id="collect-errors" value="Y" <?php echo ($config['collect_errors'] ?? 'Y') === 'Y' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="collect-errors">Errors</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="collect_jobs" id="collect-jobs" value="Y" <?php echo ($config['collect_jobs'] ?? 'Y') === 'Y' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="collect-jobs">Jobs</label>
                        </div>
                    </div>
                    <div class="col-12 text-end">
                        <button class="btn btn-primary" type="submit">Save Settings</button>
                    </div>
                </div>
            </form>
        <?php } else { ?>
            <div class="alert alert-light border mb-0">Settings are restricted to privileged users.</div>
        <?php } ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">API Tokens</h3>
            <p class="text-muted small mb-0">Generate scoped tokens to access telemetry via system APIs.</p>
        </div>
    </div>
    <div class="card-body">
        <?php if ($priv['manage_tokens']) { ?>
            <form method="post" action="<?php echo $radAdminUrl; ?>/telemetry/view" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                <input type="hidden" name="telemetry_token_create" value="1">
                <div class="col-md-4">
                    <label class="form-label">Scopes</label>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scopes[]" value="events" id="scope-events" checked>
                        <label class="form-check-label" for="scope-events">Events</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scopes[]" value="rollups" id="scope-rollups">
                        <label class="form-check-label" for="scope-rollups">Rollups</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scopes[]" value="stats" id="scope-stats">
                        <label class="form-check-label" for="scope-stats">Stats</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expires At (optional)</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Create Token</button>
                </div>
            </form>
        <?php } else { ?>
            <div class="alert alert-light border">Token management is restricted to system administrators.</div>
        <?php } ?>

        <?php if (empty($tokens)) { ?>
            <p class="text-muted mb-0">No telemetry tokens created yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>UID</th>
                            <th>Scopes</th>
                            <th>Status</th>
                            <th>Expires</th>
                            <th>Last Used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token) { ?>
                        <tr>
                            <td class="text-monospace small"><?php echo htmlspecialchars($token['uid'] ?? ''); ?></td>
                            <td class="small"><?php echo htmlspecialchars($token['scopes'] ?? ''); ?></td>
                            <td><span class="badge bg-<?php echo ($token['status'] ?? '') === 'active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($token['status'] ?? ''); ?></span></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($token['expires_at'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($token['last_used_at'] ?? ''); ?></td>
                            <td class="text-end">
                                <?php if ($priv['manage_tokens']) { ?>
                                    <a class="btn btn-outline-danger btn-sm" href="<?php echo $radAdminUrl; ?>/telemetry/revoke/<?php echo (int)($token['id'] ?? 0); ?>" onclick="return confirm('Revoke this token?');">Revoke</a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Recent Events</h3>
    </div>
    <div class="card-body">
        <?php if (empty($events)) { ?>
            <p class="text-muted mb-0">No telemetry events recorded yet.</p>
        <?php } else { ?>
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-muted small">Showing up to 100 recent events</div>
                <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/telemetry/list">View all events</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Time</th>
                            <th>Component</th>
                            <th>Severity</th>
                            <th>Message</th>
                            <th>Duration (ms)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event) { ?>
                        <tr>
                            <td class="text-muted small"><?php echo htmlspecialchars($event['created_at'] ?? ''); ?></td>
                            <td class="text-monospace small"><?php echo htmlspecialchars(($event['component_type'] ?? '') . ':' . ($event['component_ref'] ?? '')); ?></td>
                            <td>
                                <?php $sev = strtolower($event['severity'] ?? ''); ?>
                                <span class="badge bg-<?php echo $sev === 'high' ? 'danger' : ($sev === 'medium' ? 'warning' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($event['severity'] ?? '')); ?>
                                </span>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($event['message'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($event['duration_ms'] ?? ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
