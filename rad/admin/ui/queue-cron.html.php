<?php
$cron = $this->runData['data']['cron'] ?? [];
$cronJobs = $this->runData['data']['cron_jobs'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Cron Setup</h2>
            <p class="text-muted mb-0">Use cPanel or server cron to trigger the queue runner endpoint.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/queue/overview" class="btn btn-outline-secondary btn-sm">Overview</a>
            <a href="<?php echo $radAdminUrl; ?>/queue/history" class="btn btn-outline-secondary btn-sm">History</a>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h3 class="h6">Recommended cron commands</h3>
        <p class="text-muted small">Use curl or wget in cPanel. Configure frequency (e.g., every 5 minutes).</p>
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
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="h6 mb-1">Per-job cron commands</h3>
                <p class="text-muted small mb-0">Use these when you want different schedules per job.</p>
            </div>
        </div>
        <?php if (empty($cronJobs)) { ?>
            <div class="text-muted small">No queue jobs found.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr class="text-muted small">
                            <th>Job</th>
                            <th>Frequency</th>
                            <th>Recommended cron</th>
                            <th>Command</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cronJobs as $index => $job): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($job['title'] ?? $job['script']); ?></div>
                                    <div class="text-muted small text-monospace"><?php echo htmlspecialchars($job['script']); ?></div>
                                    <?php if (!empty($job['is_builtin'])): ?>
                                        <span class="badge text-bg-secondary">System</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($job['frequency'] ?? ''); ?></td>
                                <td class="text-muted small text-monospace"><?php echo htmlspecialchars($job['cron'] ?? ''); ?></td>
                                <td>
                                    <?php $codeId = 'cron-job-' . $index; ?>
                                    <div class="d-flex flex-wrap align-items-start gap-2">
                                        <pre class="bg-light border rounded p-2 small mb-0 flex-grow-1"><code id="<?php echo $codeId; ?>">/usr/bin/curl -s "<?php echo htmlspecialchars($job['run'] ?? ''); ?>" >/dev/null 2>&1</code></pre>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="#<?php echo $codeId; ?>">Copy</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h3 class="h6">cPanel hints</h3>
        <ul class="small text-muted mb-0">
            <li>Use the PHP-less curl command shown above in “Add New Cron Job”.</li>
            <li>Set the schedule to match your queue frequency (e.g., */5 * * * *).</li>
            <li>Confirm the command hits the correct domain (same as base_url).</li>
        </ul>
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
