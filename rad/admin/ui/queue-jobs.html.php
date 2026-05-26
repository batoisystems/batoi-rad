<?php
$queues = $this->runData['data']['queues'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$jobRoot = $this->runData['data']['job_root'] ?? '';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Queue Jobs</h2>
            <p class="text-muted mb-0">Review scheduled jobs and trigger runs on demand.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/queue/add" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Create Job
            </a>
            <a href="<?php echo $radAdminUrl; ?>/queue/overview" class="btn btn-outline-secondary btn-sm">Overview</a>
            <a href="<?php echo $radAdminUrl; ?>/queue/history" class="btn btn-outline-secondary btn-sm">History</a>
        </div>
    </div>
</div>

<div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="fw-semibold">Job scripts</div>
        <div class="small text-muted">Stored in <code><?php echo htmlspecialchars($jobRoot ?: 'rad/data/queue/jobs'); ?></code></div>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/queue/cron" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-terminal me-1"></i>Cron Setup
    </a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Job</th>
                    <th>Frequency</th>
                    <th>Last run</th>
                    <th>Next run</th>
                    <th>Status</th>
                    <th>Script</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queues)) { ?>
                    <tr>
                        <td colspan="7" class="text-muted">No queue jobs found.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($queues as $queue): ?>
                    <?php $status = strtolower($queue['s_queue_status'] ?? ''); ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($queue['s_queue_title'] ?? 'Job'); ?></div>
                            <div class="text-muted small text-monospace"><?php echo htmlspecialchars($queue['s_queue_script_name'] ?? ''); ?></div>
                            <?php if (!empty($queue['is_builtin'])) { ?>
                                <span class="badge text-bg-info mt-1">Built-in</span>
                            <?php } ?>
                        </td>
                        <td><?php echo htmlspecialchars($queue['s_execution_frequency'] ?? ''); ?></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($queue['s_last_executed'] ?? ''); ?></td>
                        <td class="text-muted small"><?php echo htmlspecialchars($queue['s_next_execution'] ?? ''); ?></td>
                        <td>
                            <span class="badge <?php echo $status === 'failure' ? 'text-bg-danger' : ($status === 'success' ? 'text-bg-success' : 'text-bg-secondary'); ?>">
                                <?php echo htmlspecialchars($queue['s_queue_status'] ?? '—'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($queue['script_exists'])) { ?>
                                <span class="badge text-bg-success"><?php echo !empty($queue['is_builtin']) ? 'System' : 'Ready'; ?></span>
                            <?php } else { ?>
                                <?php if (!empty($queue['is_builtin'])) { ?>
                                    <span class="badge text-bg-info">System</span>
                                <?php } else { ?>
                                    <span class="badge text-bg-warning">Missing</span>
                                <?php } ?>
                            <?php } ?>
                            <div class="small text-muted">
                                <?php if (!empty($queue['is_builtin'])) { ?>
                                    Managed in core
                                <?php } else { ?>
                                    <?php echo (int)($queue['version_count'] ?? 0); ?> versions
                                <?php } ?>
                            </div>
                        </td>
                        <td class="text-end">
                            <?php if (!empty($queue['s_queue_script_name'])) { ?>
                                <?php if (!empty($queue['is_builtin'])) { ?>
                                    <a href="<?php echo $radAdminUrl; ?>/queue/viewone/<?php echo urlencode($queue['s_queue_script_name'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary">
                                        View
                                    </a>
                                <?php } else { ?>
                                    <a href="<?php echo $radAdminUrl; ?>/queue/edit/<?php echo urlencode($queue['s_queue_script_name'] ?? ''); ?>" class="btn btn-sm btn-outline-secondary">
                                        Edit
                                    </a>
                                <?php } ?>
                            <?php } ?>
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
