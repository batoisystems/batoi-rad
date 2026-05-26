<?php
$statusCounts = $this->runData['data']['status_counts'] ?? [];
$scopeCounts = $this->runData['data']['scope_counts'] ?? [];
$recentRuns = $this->runData['data']['recent_runs'] ?? [];
$trend = $this->runData['data']['trend'] ?? [];

$statusMap = [];
foreach ($statusCounts as $row) {
    $statusMap[$row['s_status']] = (int)$row['total'];
}
$scopeMap = [];
foreach ($scopeCounts as $row) {
    $scopeMap[$row['s_scope']] = (int)$row['total'];
}

$totalRuns = array_sum($statusMap);
$passed = $statusMap['passed'] ?? 0;
$failed = $statusMap['failed'] ?? 0;
$inProgress = $statusMap['in_progress'] ?? ($statusMap['pending'] ?? 0);
$blocked = $statusMap['blocked'] ?? 0;
$passRate = $totalRuns > 0 ? round(($passed / $totalRuns) * 100, 1) : 0;
?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Total Runs</div>
                <div class="display-6 mb-0"><?php echo $totalRuns; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Passed</div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="h4 mb-0"><?php echo $passed; ?></div>
                    <span class="badge text-bg-success"><?php echo $passRate; ?>%</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">In Progress</div>
                <div class="h4 mb-0"><?php echo $inProgress; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase mb-1">Blocked/Failed</div>
                <div class="h4 mb-0"><?php echo $blocked + $failed; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3">Run mix by scope</h6>
        <?php if (empty($scopeMap)) { ?>
            <p class="text-muted mb-0">No runs recorded yet.</p>
        <?php } else { ?>
            <?php foreach ($scopeMap as $scope => $count) {
                $pct = $totalRuns > 0 ? round(($count / $totalRuns) * 100) : 0;
                $label = $scope === 'microservice' ? 'Microservicelet' : ($scope === 'api' ? 'API Endpoint' : 'Route');
            ?>
                <div class="mb-2">
                    <div class="d-flex justify-content-between small">
                        <span><?php echo htmlspecialchars($label); ?></span>
                        <span class="text-muted"><?php echo $count; ?> runs</span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $pct; ?>%;" aria-valuenow="<?php echo $pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title mb-3">Outcome trend (last 14 days)</h6>
        <?php if (empty($trend)) { ?>
            <p class="text-muted mb-0">Trend not available yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Passed</th>
                            <th>Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trend as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['d']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge text-bg-success"><?php echo (int)$row['passed']; ?></span>
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: <?php echo min(100, ((int)$row['passed'] * 10)); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge text-bg-danger"><?php echo (int)$row['failed']; ?></span>
                                        <div class="progress flex-grow-1" style="height: 6px;">
                                            <div class="progress-bar bg-danger" style="width: <?php echo min(100, ((int)$row['failed'] * 10)); ?>%;"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="card-title mb-0">Recent Runs</h6>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/view" class="btn btn-sm btn-outline-secondary">Back to plans</a>
        </div>
        <?php if (empty($recentRuns)) { ?>
            <p class="text-muted mb-0">No runs recorded yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Passed</th>
                            <th>Failed</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRuns as $run) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($run['plan_name'] ?? ''); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($run['s_scope'] ?? ''); ?></td>
                                <td><span class="badge text-bg-light"><?php echo htmlspecialchars($run['s_status'] ?? ''); ?></span></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($run['createstamp']); ?></td>
                                <td><?php echo (int)($run['passed_count'] ?? 0); ?></td>
                                <td><?php echo (int)($run['failed_count'] ?? 0); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testrun/viewone/<?php echo (int)$run['id']; ?>">Open</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
