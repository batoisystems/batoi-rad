<?php
$queues = $this->runData['data']['queues'] ?? [];
$history = $this->runData['data']['history'] ?? [];
$stats = $this->runData['data']['stats'] ?? [];
$cron = $this->runData['data']['cron'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$today = (new DateTime('now', new DateTimeZone($timezone)))->format('Y-m-d');
$date = $this->runData['data']['date'] ?? $today;
$dateOptions = $this->runData['data']['date_options'] ?? [$date];

$successRate = $stats['total'] ? round(($stats['success'] / $stats['total']) * 100) : 0;
$trend = $stats['trend'] ?? [];
$maxDuration = 0;
foreach ($trend as $point) {
    $maxDuration = max($maxDuration, (float)($point['duration'] ?? 0));
}
$maxDuration = $maxDuration ?: 1;
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Queue Overview</h2>
            <p class="text-muted mb-0">Monitor scheduled jobs, recent runs, and cron configuration.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/queue/jobs" class="btn btn-outline-primary btn-sm">Jobs</a>
            <a href="<?php echo $radAdminUrl; ?>/queue/history" class="btn btn-outline-primary btn-sm">History</a>
            <a href="<?php echo $radAdminUrl; ?>/queue/cron" class="btn btn-outline-primary btn-sm">Cron Setup</a>
            <a href="<?php echo $radAdminUrl; ?>/governance/health" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-heart-pulse me-1"></i>System Health
            </a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Jobs</div>
                <div class="fs-3 fw-semibold"><?php echo count($queues); ?></div>
                <div class="small text-muted">Scheduled tasks</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Last Run</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($stats['last_run'] ?? '—'); ?></div>
                <div class="small text-muted">Latest queue execution</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Success Rate</div>
                <div class="fs-3 fw-semibold"><?php echo (int)$successRate; ?>%</div>
                <div class="small text-muted"><?php echo (int)($stats['success'] ?? 0); ?> success · <?php echo (int)($stats['failure'] ?? 0); ?> failure</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Avg Duration</div>
                <div class="fs-3 fw-semibold"><?php echo $stats['avg_duration'] !== null ? htmlspecialchars((string)$stats['avg_duration']) . 's' : '—'; ?></div>
                <div class="small text-muted">Latest runs (<?php echo (int)($stats['total'] ?? 0); ?>)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Scheduled Jobs</h3>
                <a class="small text-decoration-none" href="<?php echo $radAdminUrl; ?>/queue/jobs">Manage jobs</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job</th>
                            <th>Frequency</th>
                            <th>Next run</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($queues)) { ?>
                            <tr>
                                <td colspan="5" class="text-muted">No queue jobs found.</td>
                            </tr>
                        <?php } ?>
                        <?php foreach ($queues as $queue): ?>
                            <?php $status = strtolower($queue['s_queue_status'] ?? ''); ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($queue['s_queue_title'] ?? 'Job'); ?></div>
                                    <div class="text-muted small text-monospace"><?php echo htmlspecialchars($queue['s_queue_script_name'] ?? ''); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($queue['s_execution_frequency'] ?? ''); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($queue['s_next_execution'] ?? ''); ?></td>
                                <td>
                                    <span class="badge <?php echo $status === 'failure' ? 'text-bg-danger' : ($status === 'success' ? 'text-bg-success' : 'text-bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($queue['s_queue_status'] ?? '—'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="<?php echo $radAdminUrl; ?>/queue/run" class="d-inline">
                                        <input type="hidden" name="job" value="<?php echo htmlspecialchars($queue['s_queue_script_name'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Run now</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Cron Setup</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small">Use cPanel’s Cron Jobs to call the queue runner endpoint. Prefer curl or wget.</p>
                <div class="mb-3">
                    <div class="small text-muted mb-1">Run all due jobs</div>
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <pre class="bg-light border rounded p-2 small mb-0 flex-grow-1"><code id="cron-all">/usr/bin/curl -s "<?php echo htmlspecialchars($cron['run_all'] ?? ''); ?>" >/dev/null 2>&1</code></pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#cron-all">Copy</button>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted mb-1">Run activity ingest only</div>
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <pre class="bg-light border rounded p-2 small mb-0 flex-grow-1"><code id="cron-activity">/usr/bin/curl -s "<?php echo htmlspecialchars($cron['run_activity'] ?? ''); ?>" >/dev/null 2>&1</code></pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#cron-activity">Copy</button>
                    </div>
                </div>
                <div class="mb-0">
                    <div class="small text-muted mb-1">wget alternative</div>
                    <div class="d-flex flex-wrap align-items-start gap-2">
                        <pre class="bg-light border rounded p-2 small mb-0 flex-grow-1"><code id="cron-wget">/usr/bin/wget -qO- "<?php echo htmlspecialchars($cron['run_activity'] ?? ''); ?>" >/dev/null 2>&1</code></pre>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#cron-wget">Copy</button>
                    </div>
                </div>
                <?php if (!empty($cron['has_token'])) { ?>
                    <div class="alert alert-info mt-3 small mb-0">
                        Token is enabled. Use only the token value after <code>token=</code>. Do not paste the full URL as the token.
                    </div>
                <?php } else { ?>
                    <div class="alert alert-warning mt-3 small mb-0">
                        No queue token configured. For security, add <code>sys.queue_token</code> in <code>s_config</code>.
                    </div>
                <?php } ?>
                <div class="mt-3 small text-muted">
                    Need step-by-step help? <a href="<?php echo $radAdminUrl; ?>/queue/cron">Open Cron Setup guide</a>.
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0">Recent Runs</h3>
                <form method="get" action="<?php echo $radAdminUrl; ?>/queue/view" class="d-flex align-items-center gap-2">
                    <select class="form-select form-select-sm" name="date" onchange="this.form.submit()">
                        <?php foreach ($dateOptions as $opt): ?>
                            <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo $opt === $date ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="card-body">
                <?php if (empty($history)) { ?>
                    <div class="text-muted">No queue activity logged for this date.</div>
                <?php } else { ?>
                    <div class="vstack gap-3">
                        <?php foreach (array_slice($history, 0, 8) as $entry): ?>
                            <?php $ctx = $entry['context'] ?? []; ?>
                            <div class="border rounded p-2">
                                <div class="small text-muted"><?php echo htmlspecialchars($entry['timestamp'] ?? ''); ?></div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($ctx['job'] ?? $entry['message'] ?? 'Queue run'); ?></div>
                                <div class="text-muted small">
                                    Status: <?php echo htmlspecialchars($ctx['status'] ?? ''); ?>
                                    <?php if (!empty($ctx['duration'])): ?> · <?php echo htmlspecialchars($ctx['duration']); ?>s<?php endif; ?>
                                </div>
                                <?php if (!empty($ctx['error'])): ?>
                                    <div class="text-danger small mt-1"><?php echo htmlspecialchars($ctx['error']); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Run Trend</h3>
            </div>
            <div class="card-body">
                <?php if (empty($trend)) { ?>
                    <div class="text-muted">No run data yet.</div>
                <?php } else { ?>
                    <div class="d-flex align-items-end gap-2" style="height:120px;">
                        <?php foreach ($trend as $point): ?>
                            <?php
                            $height = max(12, (int)round(($point['duration'] / $maxDuration) * 100));
                            $color = ($point['status'] === 'failure') ? 'bg-danger' : 'bg-success';
                            ?>
                            <div class="flex-grow-1">
                                <div class="<?php echo $color; ?> rounded" style="height: <?php echo $height; ?>px;"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="d-flex justify-content-between text-muted small mt-2">
                        <span>Last <?php echo count($trend); ?> runs</span>
                        <span>Max <?php echo htmlspecialchars((string)$maxDuration); ?>s</span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<script>
(() => {
    const toast = document.createElement('div');
    toast.className = 'rad-copy-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);

    const showToast = (message, isError = false) => {
        toast.textContent = message;
        toast.classList.toggle('is-error', isError);
        toast.classList.add('show');
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(() => {
            toast.classList.remove('show');
        }, 1800);
    };

    const buttons = document.querySelectorAll('[data-copy]');
    if (!buttons.length) return;
    buttons.forEach((btn) => {
        btn.addEventListener('click', async () => {
            const target = document.querySelector(btn.getAttribute('data-copy'));
            if (!target) return;
            const text = target.textContent || '';
            try {
                await navigator.clipboard.writeText(text.trim());
                showToast('Copied to clipboard');
            } catch (err) {
                showToast('Copy failed', true);
            }
        });
    });
})();
</script>

<style>
.rad-copy-toast {
    position: fixed;
    right: 20px;
    bottom: 20px;
    background: #111827;
    color: #fff;
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 0.85rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.2);
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.2s ease, transform 0.2s ease;
    z-index: 1080;
}
.rad-copy-toast.is-error {
    background: #b42318;
}
.rad-copy-toast.show {
    opacity: 1;
    transform: translateY(0);
}
</style>
