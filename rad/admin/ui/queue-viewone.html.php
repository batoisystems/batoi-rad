<?php
$job = $this->runData['data']['job'] ?? [];
$code = $this->runData['data']['code'] ?? '';
$isBuiltin = !empty($this->runData['data']['is_builtin']);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$jobRoot = $this->runData['data']['job_root'] ?? '';
$scriptName = $job['s_queue_script_name'] ?? '';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Queue Job — <?php echo htmlspecialchars($job['s_queue_title'] ?? $scriptName); ?></h2>
            <p class="text-muted mb-0"><?php echo $isBuiltin ? 'Built-in system job (read-only).' : 'Custom queue job.'; ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/queue/jobs" class="btn btn-outline-secondary btn-sm">Back to Jobs</a>
            <?php if (!empty($scriptName)) { ?>
                <form method="post" action="<?php echo $radAdminUrl; ?>/queue/run" class="d-inline">
                    <input type="hidden" name="job" value="<?php echo htmlspecialchars($scriptName); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Run now</button>
                </form>
            <?php } ?>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Script name</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($scriptName); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Frequency</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($job['s_execution_frequency'] ?? ''); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Status</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($job['s_queue_status'] ?? '—'); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Last executed</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($job['s_last_executed'] ?? ''); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Next execution</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($job['s_next_execution'] ?? ''); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Script location</div>
                <div class="fw-semibold">
                    <?php if ($isBuiltin) { ?>
                        Managed in core
                    <?php } else { ?>
                        <?php
                        $base = $jobRoot ?: 'rad/data/queue/jobs';
                        echo htmlspecialchars($base . '/' . $scriptName . '.php');
                        ?>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php if ($isBuiltin) { ?>
            <div class="alert alert-info mt-3 mb-0">
                Built-in jobs are managed in core code and are not versioned here.
            </div>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Job code preview</h3>
    </div>
    <div class="card-body">
        <pre class="bg-light border rounded p-3 mb-0"><code><?php echo htmlspecialchars($code); ?></code></pre>
    </div>
</div>
