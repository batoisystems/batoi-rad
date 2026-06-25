<?php
$history = $this->runData['data']['history'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$today = (new DateTime('now', new DateTimeZone($timezone)))->format('Y-m-d');
$date = $this->runData['data']['date'] ?? $today;
$dateOptions = $this->runData['data']['date_options'] ?? [$date];
$rangeDays = (int)($this->runData['data']['range_days'] ?? 1);
$jobFilter = $this->runData['data']['job_filter'] ?? '';
$jobOptions = $this->runData['data']['job_options'] ?? [];
$builtinJobs = $this->runData['data']['builtin_jobs'] ?? [];
$summary = $this->runData['data']['summary'] ?? [];
$page = (int)($this->runData['data']['page'] ?? 1);
$perPage = (int)($this->runData['data']['per_page'] ?? 25);
$pages = (int)($this->runData['data']['pages'] ?? 1);
$total = (int)($this->runData['data']['history_total'] ?? 0);

$queryBase = [
    'date' => $date,
    'range_days' => $rangeDays,
    'job' => $jobFilter,
    'per_page' => $perPage,
];
$baseUrl = $radAdminUrl . '/queue/history';
$statusChart = $summary['chart']['status'] ?? ['labels' => [], 'values' => []];
$jobChart = $summary['chart']['jobs'] ?? ['labels' => [], 'values' => []];
$successRate = ($summary['total'] ?? 0) ? round((($summary['success'] ?? 0) / ($summary['total'] ?? 1)) * 100) : 0;
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Queue History</h2>
            <p class="text-muted mb-0">Explore run history, status trends, and job health.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/queue/overview" class="btn btn-outline-secondary btn-sm">Overview</a>
            <a href="<?php echo $radAdminUrl; ?>/queue/jobs" class="btn btn-outline-secondary btn-sm">Jobs</a>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?php echo $baseUrl; ?>" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted">Date</label>
                <select class="form-select form-select-sm" name="date">
                    <?php foreach ($dateOptions as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $opt === $date ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Job</label>
                <select class="form-select form-select-sm" name="job">
                    <option value="">All jobs</option>
                    <?php foreach ($jobOptions as $job): ?>
                        <option value="<?php echo htmlspecialchars($job); ?>" <?php echo $jobFilter === $job ? 'selected' : ''; ?>><?php echo htmlspecialchars($job); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Last N days</label>
                <select class="form-select form-select-sm" name="range_days">
                    <?php foreach ([1, 3, 7, 14, 30] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $rangeDays === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">Per page</label>
                <select class="form-select form-select-sm" name="per_page">
                    <?php foreach ([25, 50, 100, 150, 200] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo $perPage === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Runs</div>
                <div class="fs-3 fw-semibold"><?php echo (int)($summary['total'] ?? 0); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($summary['last_run'] ?? '—'); ?> latest</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Success Rate</div>
                <div class="fs-3 fw-semibold"><?php echo $successRate; ?>%</div>
                <div class="small text-muted"><?php echo (int)($summary['success'] ?? 0); ?> success · <?php echo (int)($summary['failure'] ?? 0); ?> failure</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Average Duration</div>
                <div class="fs-3 fw-semibold"><?php echo $summary['avg_duration'] !== null ? htmlspecialchars((string)$summary['avg_duration']) . 's' : '—'; ?></div>
                <div class="small text-muted">Across filtered runs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Results</div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge text-bg-success"><?php echo (int)($summary['success'] ?? 0); ?> success</span>
                    <span class="badge text-bg-danger"><?php echo (int)($summary['failure'] ?? 0); ?> failure</span>
                    <span class="badge text-bg-secondary"><?php echo (int)($summary['other'] ?? 0); ?> other</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Status Mix</h3>
                <span class="small text-muted">Filtered runs</span>
            </div>
            <div class="card-body">
                <?php if (empty($statusChart['labels'])) { ?>
                    <div class="text-muted">No chart data available.</div>
                <?php } else { ?>
                    <canvas id="queue-history-status" height="220"></canvas>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Top Jobs</h3>
                <span class="small text-muted">By run count</span>
            </div>
            <div class="card-body">
                <?php if (empty($jobChart['labels'])) { ?>
                    <div class="text-muted">No chart data available.</div>
                <?php } else { ?>
                    <canvas id="queue-history-jobs" height="220"></canvas>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="text-muted small">
                Showing <?php echo count($history); ?> of <?php echo $total; ?> run(s).
            </div>
            <div class="small text-muted">
                Page <?php echo $page; ?> of <?php echo $pages; ?>
            </div>
        </div>
        <?php if (empty($history)) { ?>
            <div class="text-muted">No queue activity logged for the selected filters.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Job</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <?php $ctx = $entry['context'] ?? []; ?>
                            <?php $status = strtolower($ctx['status'] ?? ''); ?>
                            <?php $jobName = (string)($ctx['job'] ?? $entry['message'] ?? 'Queue run'); ?>
                            <tr>
                                <td class="small text-muted"><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></td>
                                <td class="fw-semibold">
                                    <?php echo htmlspecialchars($jobName); ?>
                                    <?php if ($jobName !== '' && in_array($jobName, $builtinJobs, true)) { ?>
                                        <span class="badge text-bg-info ms-2">Built-in</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status === 'failure' ? 'text-bg-danger' : ($status === 'success' ? 'text-bg-success' : 'text-bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($ctx['status'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?php echo !empty($ctx['duration']) ? htmlspecialchars((string)$ctx['duration']) . 's' : '—'; ?></td>
                                <td class="small text-muted">
                                    <?php echo !empty($ctx['error']) ? htmlspecialchars((string)$ctx['error']) : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php if ($pages > 1) { ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php
            $prev = max(1, $page - 1);
            $next = min($pages, $page + 1);
            $queryBaseStr = http_build_query($queryBase);
            ?>
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . '?' . $queryBaseStr . '&page=' . $prev; ?>">Prev</a>
            </li>
            <?php for ($p = 1; $p <= $pages; $p++) { ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo $baseUrl . '?' . $queryBaseStr . '&page=' . $p; ?>"><?php echo $p; ?></a>
                </li>
            <?php } ?>
            <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . '?' . $queryBaseStr . '&page=' . $next; ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php } ?>

<script>
(() => {
    const statusEl = document.getElementById('queue-history-status');
    const jobEl = document.getElementById('queue-history-jobs');
    const statusData = <?php echo json_encode($statusChart); ?>;
    const jobData = <?php echo json_encode($jobChart); ?>;

    if (statusEl && statusData.labels && statusData.labels.length) {
        window.RadAdminCharts.render(statusEl, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: ['#16a34a', '#dc2626', '#94a3b8']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    if (jobEl && jobData.labels && jobData.labels.length) {
        window.RadAdminCharts.render(jobEl, {
            type: 'bar',
            data: {
                labels: jobData.labels,
                datasets: [{
                    label: 'Runs',
                    data: jobData.values,
                    backgroundColor: '#2563eb'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
})();
</script>
