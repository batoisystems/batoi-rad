<?php
$lastRun = $this->runData['data']['last_run'];
$logTail = $this->runData['data']['log_tail'];
$upgrades = $this->runData['data']['upgrades'] ?? [];
?>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="card-title mb-1">Upgrade Database</h5>
            <p class="text-muted mb-0">
                Upgrade scripts are versioned via their IDs. The newest executions appear at the top of the list and log tail, and this runner executes the same logic as the CLI while updating checkpoints automatically.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/add" class="btn btn-outline-secondary">
                <i class="bi bi-file-earmark-plus me-1"></i>New Upgrade
            </a>
        </div>
    </div>
</div>

<?php if (!empty($upgrades)) {
    $latestAppliedId = null;
    foreach ($upgrades as $upgradeMeta) {
        if (!empty($upgradeMeta['applied'])) {
            if ($latestAppliedId === null || strcmp($upgradeMeta['id'], $latestAppliedId) > 0) {
                $latestAppliedId = $upgradeMeta['id'];
            }
        }
    }

    $sortedUpgrades = $upgrades;
    usort($sortedUpgrades, function ($a, $b) {
        $aTime = $a['executed_at'] ?? '';
        $bTime = $b['executed_at'] ?? '';
        $aPending = empty($aTime);
        $bPending = empty($bTime);
        if ($aPending && !$bPending) {
            return -1;
        }
        if ($bPending && !$aPending) {
            return 1;
        }
        if ($aPending && $bPending) {
            return strcmp($b['id'], $a['id']);
        }
        $timeComparison = strcmp($bTime, $aTime);
        if ($timeComparison !== 0) {
            return $timeComparison;
        }
        return strcmp($b['id'], $a['id']);
    });
?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">Upgrade Status</h6>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Executed At</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sortedUpgrades as $upgrade) { ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($upgrade['id']); ?></code></td>
                                <td><?php echo htmlspecialchars($upgrade['description']); ?></td>
                                <td>
                                    <?php if ($upgrade['applied']) { ?>
                                        <span class="badge bg-success me-2">Applied</span>
                                        <form method="post" class="d-inline">
                                            <button type="submit" name="revert_upgrade" value="<?php echo htmlspecialchars($upgrade['id']); ?>" class="btn btn-link btn-sm p-0 align-baseline" title="Mark as pending for deployment on other servers">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    <?php } else { ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                        <?php if ($upgrade['locked']) { ?>
                                            <i class="bi bi-lock-fill text-muted ms-1" title="Already executed on this server. Deploy to another server to run again."></i>
                                        <?php } ?>
                                    <?php } ?>
                                </td>
                                <td><?php echo $upgrade['executed_at'] ? htmlspecialchars($upgrade['executed_at']) : '—'; ?></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <?php if (!$upgrade['applied'] && !$upgrade['locked']) { ?>
                                            <form method="post" class="mb-0">
                                                <button type="submit" name="run_upgrade" value="1" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Run Upgrade
                                                </button>
                                            </form>
                                        <?php } elseif ($upgrade['locked']) { ?>
                                            <span class="badge bg-secondary align-self-center">Locked</span>
                                        <?php } ?>
                                        <?php if ($upgrade['applied'] && !empty($upgrade['has_rollback']) && $latestAppliedId === $upgrade['id']) { ?>
                                            <form method="post" class="mb-0">
                                                <button type="submit" name="run_rollback" value="<?php echo htmlspecialchars($upgrade['id']); ?>" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-arrow-return-left me-1"></i>Rollback
                                                </button>
                                            </form>
                                        <?php } ?>
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/edit/<?php echo urlencode($upgrade['id']); ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="alert alert-info">No upgrade scripts were found in <code>rad/upgrades</code>.</div>
<?php } ?>

<?php if ($lastRun) { ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">Last Execution</h6>
            <p class="mb-1">
                <strong>Status:</strong>
                <?php echo $lastRun['success'] ? '<span class="badge bg-success">Success</span>' : '<span class="badge bg-danger">Failed</span>'; ?>
            </p>
            <p class="mb-2"><strong>Executed At:</strong> <?php echo htmlspecialchars($lastRun['executed_at']); ?></p>
            <?php if (!empty($lastRun['log_file'])) { ?>
                <p class="text-muted mb-2"><strong>Log File:</strong> <?php echo htmlspecialchars($lastRun['log_file']); ?></p>
            <?php } ?>
            <?php if (!empty($lastRun['output'])) { ?>
                <pre class="mb-0"><code><?php echo htmlspecialchars(implode(PHP_EOL, $lastRun['output'])); ?></code></pre>
            <?php } else { ?>
                <p class="text-muted mb-0">No output was produced.</p>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<div class="card">
    <div class="card-body">
        <h6 class="card-title">Upgrade Log Tail</h6>
        <?php if (!empty($logTail)) { ?>
            <pre class="mb-0"><code><?php echo htmlspecialchars(implode(PHP_EOL, $logTail)); ?></code></pre>
        <?php } else { ?>
            <p class="text-muted mb-0">No upgrade log entries yet.</p>
        <?php } ?>
    </div>
</div>
