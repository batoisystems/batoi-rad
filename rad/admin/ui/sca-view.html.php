<?php
$report = $this->runData['data']['sca'] ?? [];
$summary = [
    'files' => $report['files_scanned'] ?? 0,
    'findings' => $report['findings_total'] ?? 0,
    'high' => $report['severity_counts']['high'] ?? 0,
    'medium' => $report['severity_counts']['medium'] ?? 0,
    'low' => $report['severity_counts']['low'] ?? 0,
    'generated' => $report['generated_at'] ?? '',
];
$findings = $report['findings'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Read-only scan of application code.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/sca/export/html" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-html me-1"></i>Export HTML</a>
            <a href="<?php echo $radAdminUrl; ?>/sca/export/pdf" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
        </div>
    </div>
</div>

<?php
$chartJsCdn = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
?>
<script src="<?php echo htmlspecialchars($chartJsCdn); ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const metricsUrl = <?php echo json_encode($radAdminUrl . '/sca/metrics'); ?>;
    const charts = {};
    const downloadButtons = Array.from(document.querySelectorAll('[data-sca-chart-download]'));
    downloadButtons.forEach(btn => btn.disabled = true);

    const colors = {
        high: '#e74c3c',
        medium: '#f39c12',
        low: '#95a5a6',
        blue: '#0d6efd',
        teal: '#2ecc71',
    };

    fetch(metricsUrl, { headers: { 'Accept': 'application/json' } })
        .then(resp => resp.ok ? resp.json() : Promise.reject(new Error('Metrics load failed')))
        .then(renderCharts)
        .catch(err => console.error('SCA metrics error', err));

    function renderCharts(data) {
        charts.severity = new Chart(document.getElementById('sca-chart-severity'), {
            type: 'doughnut',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    data: [
                        data.severity?.high || 0,
                        data.severity?.medium || 0,
                        data.severity?.low || 0
                    ],
                    backgroundColor: [colors.high, colors.medium, colors.low]
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}}
        });

        const ruleLabels = Object.keys(data.rules || {});
        const ruleCounts = Object.values(data.rules || {});
        charts.rules = new Chart(document.getElementById('sca-chart-rules'), {
            type: 'bar',
            data: {
                labels: ruleLabels,
                datasets: [{
                    label: 'Findings',
                    data: ruleCounts,
                    backgroundColor: colors.blue,
                }]
            },
            options: {plugins: {legend: {display: false}}, scales: {x: {ticks: {autoSkip: false}}}}
        });

        const moduleLabels = Object.keys(data.modules || {});
        const moduleCounts = Object.values(data.modules || {});
        charts.modules = new Chart(document.getElementById('sca-chart-modules'), {
            type: 'bar',
            data: {
                labels: moduleLabels,
                datasets: [{
                    label: 'Findings',
                    data: moduleCounts,
                    backgroundColor: colors.teal,
                }]
            },
            options: {plugins: {legend: {display: false}}, scales: {x: {ticks: {autoSkip: false}}}}
        });

        charts.files = new Chart(document.getElementById('sca-chart-files'), {
            type: 'bar',
            data: {
                labels: ['Files Scanned', 'Total Findings'],
                datasets: [{
                    label: 'Count',
                    data: [data.files_scanned || 0, data.findings || 0],
                    backgroundColor: [colors.blue, colors.high],
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
        link.download = 'sca-' + key + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        const newWin = window.open(dataUrl, '_blank');
        if (newWin) { newWin.opener = null; }
    };

    document.querySelectorAll('[data-sca-chart-download]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const key = btn.getAttribute('data-sca-chart-download');
            triggerDownload(key);
        });
    });
});
</script>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Visualizations</h3>
        <p class="text-muted small mb-0">Severity distribution, top rules, affected modules, and scan volume.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $charts = [
                ['id' => 'severity', 'title' => 'Findings by Severity'],
                ['id' => 'rules', 'title' => 'Top Rules'],
                ['id' => 'modules', 'title' => 'Modules'],
                ['id' => 'files', 'title' => 'Files vs Findings'],
            ];
            foreach ($charts as $chart) { ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small"><?php echo htmlspecialchars($chart['title']); ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-sca-chart-download="<?php echo htmlspecialchars($chart['id']); ?>">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <canvas id="sca-chart-<?php echo htmlspecialchars($chart['id']); ?>" height="180"></canvas>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['label' => 'Files Scanned', 'value' => $summary['files'], 'icon' => 'bi bi-file-code', 'tone' => 'primary'],
        ['label' => 'Findings', 'value' => $summary['findings'], 'icon' => 'bi bi-flag', 'tone' => 'danger'],
        ['label' => 'High', 'value' => $summary['high'], 'icon' => 'bi bi-exclamation-octagon', 'tone' => 'danger'],
        ['label' => 'Medium', 'value' => $summary['medium'], 'icon' => 'bi bi-exclamation-triangle', 'tone' => 'warning'],
        ['label' => 'Low', 'value' => $summary['low'], 'icon' => 'bi bi-exclamation-circle', 'tone' => 'secondary'],
        ['label' => 'Generated', 'value' => $summary['generated'], 'icon' => 'bi bi-clock-history', 'tone' => 'info'],
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

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Findings</h3>
    </div>
    <div class="card-body">
        <?php if (empty($findings)) { ?>
            <p class="text-muted mb-0">No findings detected in the scanned files.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Severity</th>
                            <th>Rule</th>
                            <th>File</th>
                            <th>Line</th>
                            <th>Snippet</th>
                            <th>Remediation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($findings as $finding) { ?>
                        <tr>
                            <td>
                                <?php $sev = strtolower($finding['severity'] ?? ''); ?>
                                <span class="badge bg-<?php echo $sev === 'high' ? 'danger' : ($sev === 'medium' ? 'warning' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($finding['severity'] ?? '')); ?>
                                </span>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($finding['rule'] ?? ''); ?></td>
                            <td class="text-monospace small"><?php echo htmlspecialchars($finding['file'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo (int)($finding['line'] ?? 0); ?></td>
                            <td class="text-monospace small"><?php echo htmlspecialchars($finding['snippet'] ?? ''); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($finding['remediation'] ?? ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
