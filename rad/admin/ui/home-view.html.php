<?php
$dashboard = $this->runData['data']['dashboard'] ?? [];
$metrics = $dashboard['metrics']['counts'] ?? ['access' => 0, 'error' => 0, 'sql' => 0];
$logDate = $dashboard['metrics']['date'] ?? '';
$recentErrors = $dashboard['recent_activity']['errors'] ?? [];
$recentAccess = $dashboard['recent_activity']['access'] ?? [];
$topWorkspaces = $dashboard['top_workspaces'] ?? [];
$dailySeries = $dashboard['daily_series'] ?? [];
$pendingUpgrades = $dashboard['pending_upgrades'] ?? [];
$acSummary = $dashboard['ac_summary'] ?? ['users_missing_primary' => 0, 'private_ms_unbound' => 0, 'private_route_unbound' => 0];
$bindingIntegrity = $dashboard['binding_integrity'] ?? ['routes_unbound' => 0, 'controllers_unbound' => 0];
$generatedAt = $dashboard['generated_at'] ?? null;
$isCached = (bool)($dashboard['cached'] ?? false);
$logHealth = $dashboard['log_health'] ?? [];
$cacheTtl = (int)($dashboard['cache_ttl'] ?? 0);
$latestSeries = $dailySeries[0] ?? null;
$prevSeries = $dailySeries[1] ?? null;
$deltaAccess = ($latestSeries && $prevSeries) ? ((int)$latestSeries['access'] - (int)$prevSeries['access']) : null;
$deltaError = ($latestSeries && $prevSeries) ? ((int)$latestSeries['error'] - (int)$prevSeries['error']) : null;
$deltaSql = ($latestSeries && $prevSeries) ? ((int)$latestSeries['sql'] - (int)$prevSeries['sql']) : null;
$logStatusLabel = (!empty($logHealth['log_dir_exists']) && !empty($logHealth['latest_day_found'])) ? 'Logs OK' : 'Logs missing';
$logStatusClass = (!empty($logHealth['log_dir_exists']) && !empty($logHealth['latest_day_found'])) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
$sparkSeries = array_reverse($dailySeries);
$sparkData = [
    'access' => array_map(fn($point) => ['label' => (string)($point['date'] ?? ''), 'value' => (int)($point['access'] ?? 0)], $sparkSeries),
    'error' => array_map(fn($point) => ['label' => (string)($point['date'] ?? ''), 'value' => (int)($point['error'] ?? 0)], $sparkSeries),
    'sql' => array_map(fn($point) => ['label' => (string)($point['date'] ?? ''), 'value' => (int)($point['sql'] ?? 0)], $sparkSeries),
];
$sparkOptions = [
    'access' => ['type' => 'sparkline', 'height' => 50, 'palette' => ['#0d6efd'], 'table' => 'none'],
    'error' => ['type' => 'sparkline', 'height' => 50, 'palette' => ['#dc3545'], 'table' => 'none'],
    'sql' => ['type' => 'sparkline', 'height' => 50, 'palette' => ['#198754'], 'table' => 'none'],
];
?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div class="text-muted small">
            <?php if (!empty($generatedAt)) { ?>
                Last updated <?php echo htmlspecialchars($generatedAt); ?>
                <?php if ($isCached) { ?>
                    <span class="badge bg-light text-dark ms-1">cached</span>
                <?php } ?>
                <?php if ($cacheTtl > 0) { ?>
                    <span class="text-muted ms-1">TTL <?php echo (int)$cacheTtl; ?>s</span>
                <?php } ?>
            <?php } else { ?>
                Dashboard refresh pending.
            <?php } ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge <?php echo $logStatusClass; ?>"><?php echo $logStatusLabel; ?></span>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/home/view?refresh=Y">
                <i class="bi bi-arrow-repeat me-1"></i>Refresh all
            </a>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/home/view?refresh=Y&amp;scope=logs">
                <i class="bi bi-journal-text me-1"></i>Refresh logs
            </a>
        </div>
    </div>
</div>
<?php if (!empty($logHealth) && (empty($logHealth['log_dir_exists']) || empty($logHealth['latest_day_found']))) { ?>
    <div class="alert alert-warning py-2 small mb-3">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Log health: <?php echo empty($logHealth['log_dir_exists']) ? 'Log directory missing.' : 'No log day found yet.'; ?>
        <a class="ms-2" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/techdocs/view">Review setup</a>
    </div>
<?php } ?>

<div class="mb-4">
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2 d-flex align-items-center gap-1">
                        Requests Today <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Total access log entries for the latest day."></i>
                    </div>
                    <div class="display-6 fw-semibold"><?php echo number_format($metrics['access']); ?></div>
                    <div class="text-muted small">
                        <?php echo $logDate ? 'Based on latest access log (' . htmlspecialchars($logDate) . ')' : 'No access log snapshot available.'; ?>
                    </div>
                    <?php if ($deltaAccess !== null) { ?>
                        <div class="small <?php echo $deltaAccess >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $deltaAccess >= 0 ? '+' : ''; ?><?php echo number_format($deltaAccess); ?> vs previous day
                        </div>
                    <?php } ?>
                    <div class="sparkline" data-uif="chart" data-uif-chart="sparkline" data-uif-data="<?php echo htmlspecialchars(json_encode($sparkData['access']), ENT_QUOTES, 'UTF-8'); ?>" data-uif-options="<?php echo htmlspecialchars(json_encode($sparkOptions['access']), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2 d-flex align-items-center gap-1">
                        Errors Today <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Total error log entries for the latest day."></i>
                    </div>
                    <div class="display-6 fw-semibold text-danger"><?php echo number_format($metrics['error']); ?></div>
                    <div class="text-muted small">
                        <?php echo $logDate ? 'Latest error log snapshot' : 'No error log snapshot available.'; ?>
                    </div>
                    <?php if ($deltaError !== null) { ?>
                        <div class="small <?php echo $deltaError >= 0 ? 'text-danger' : 'text-success'; ?>">
                            <?php echo $deltaError >= 0 ? '+' : ''; ?><?php echo number_format($deltaError); ?> vs previous day
                        </div>
                    <?php } ?>
                    <div class="sparkline" data-uif="chart" data-uif-chart="sparkline" data-uif-data="<?php echo htmlspecialchars(json_encode($sparkData['error']), ENT_QUOTES, 'UTF-8'); ?>" data-uif-options="<?php echo htmlspecialchars(json_encode($sparkOptions['error']), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small mb-2 d-flex align-items-center gap-1">
                        SQL Statements <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Total sql.log entries for the latest day."></i>
                    </div>
                    <div class="display-6 fw-semibold text-primary"><?php echo number_format($metrics['sql']); ?></div>
                    <div class="text-muted small">
                        <?php echo $logDate ? 'Latest sql.log snapshot' : 'No sql.log snapshot available.'; ?>
                    </div>
                    <?php if ($deltaSql !== null) { ?>
                        <div class="small <?php echo $deltaSql >= 0 ? 'text-primary' : 'text-danger'; ?>">
                            <?php echo $deltaSql >= 0 ? '+' : ''; ?><?php echo number_format($deltaSql); ?> vs previous day
                        </div>
                    <?php } ?>
                    <div class="sparkline" data-uif="chart" data-uif-chart="sparkline" data-uif-data="<?php echo htmlspecialchars(json_encode($sparkData['sql']), ENT_QUOTES, 'UTF-8'); ?>" data-uif-options="<?php echo htmlspecialchars(json_encode($sparkOptions['sql']), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
            </div>
        </div>
    </div>
    <?php if (empty($dailySeries)) { ?>
        <div class="text-muted small mt-2">No historical log series available yet.</div>
    <?php } ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="card-title mb-0">Access Control Snapshot</h5>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view">
                        <i class="bi bi-key me-1"></i>Bindings
                    </a>
                </div>
                <p class="text-muted small mb-2">Snapshot from live tables. Global-scoped microservicelets and routes do not require bindings.</p>
                <div class="mb-3">
                    <div class="text-muted text-uppercase small mb-2">Bindings</div>
                    <div class="row g-1 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['private_ms_unbound'] ? 'border-warning' : 'border-success'; ?>">
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <span class="text-truncate" style="max-width: calc(100% - 1.25rem);">Private MS without binding</span>
                                    <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Counts non-global microservicelets missing a permission binding."></i>
                                </div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['private_ms_unbound'] ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['private_ms_unbound']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view?object_type=ms">Fix now</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['private_route_unbound'] ? 'border-warning' : 'border-success'; ?>">
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <span class="text-truncate" style="max-width: calc(100% - 1.25rem);">Private routes without binding</span>
                                    <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Routes under non-global microservicelets without a binding."></i>
                                </div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['private_route_unbound'] ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['private_route_unbound']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view?object_type=route">Fix now</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $bindingIntegrity['routes_unbound'] ? 'border-danger' : 'border-success'; ?>">
                                <div class="text-muted small text-truncate" title="Routes not linked to a microservicelet">Routes not linked to a microservicelet</div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $bindingIntegrity['routes_unbound'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo (int)$bindingIntegrity['routes_unbound']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/viewall">Fix now</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $bindingIntegrity['controllers_unbound'] ? 'border-danger' : 'border-success'; ?>">
                                <div class="text-muted small text-truncate" title="Controllers not linked to a microservicelet">Controllers not linked to a microservicelet</div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $bindingIntegrity['controllers_unbound'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo (int)$bindingIntegrity['controllers_unbound']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/viewall">Fix now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="text-muted text-uppercase small mb-2">Membership hygiene</div>
                    <div class="row g-1 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['users_missing_primary'] ? 'border-danger' : 'border-success'; ?>">
                                <div class="text-muted small text-truncate" title="Users missing primary role">Users missing primary role</div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['users_missing_primary'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['users_missing_primary']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/view">Fix now</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['memberships_missing_role'] ? 'border-danger' : 'border-success'; ?>">
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <span class="text-truncate" style="max-width: calc(100% - 1.25rem);">Memberships missing role</span>
                                    <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Active workspace memberships without a role assignment."></i>
                                </div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['memberships_missing_role'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['memberships_missing_role']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/view">Fix now</a>
                                </div>
                            </div>
                        </div>
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['memberships_ms_missing_ms'] ? 'border-danger' : 'border-success'; ?>">
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <span class="text-truncate" style="max-width: calc(100% - 1.25rem);">MS memberships missing MS</span>
                                    <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Memberships with ms scope but missing microservicelet selection."></i>
                                </div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['memberships_ms_missing_ms'] ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['memberships_ms_missing_ms']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/view">Fix now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="text-muted text-uppercase small mb-2">Role health</div>
                    <div class="row g-1 row-cols-1 row-cols-sm-2 row-cols-lg-3">
                        <div class="col">
                            <div class="border rounded p-2 bg-body-tertiary h-100 small d-flex flex-column border-start border-3 <?php echo $acSummary['roles_missing_default_route'] ? 'border-warning' : 'border-success'; ?>">
                                <div class="text-muted small d-flex align-items-center gap-1">
                                    <span class="text-truncate" style="max-width: calc(100% - 1.25rem);">Roles missing default route</span>
                                    <i class="bi bi-info-circle text-muted" data-uif="tooltip" title="Roles without a default landing route configured."></i>
                                </div>
                                <div class="fs-5 fw-semibold mb-0 <?php echo $acSummary['roles_missing_default_route'] ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo (int)$acSummary['roles_missing_default_route']; ?>
                                </div>
                                <div class="mt-auto pt-1 border-top">
                                    <a class="small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/role/view">Fix now</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="card-title mb-0">Quick Actions</h5>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/view">
                Users & Roles
            </a>
        </div>
        <div class="row g-2">
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-primary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/space/add"><i class="bi bi-plus-circle me-1"></i>New Workspace</a>
            </div>
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-primary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/view"><i class="bi bi-people me-1"></i>Manage Members</a>
            </div>
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-secondary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view"><i class="bi bi-key me-1"></i>Bindings</a>
            </div>
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-secondary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/accesslog/view"><i class="bi bi-activity me-1"></i>View Logs</a>
            </div>
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-secondary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/view"><i class="bi bi-boxes me-1"></i>Microservicelets</a>
            </div>
            <div class="col-6 col-lg-3">
                <a class="btn btn-outline-secondary btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/view"><i class="bi bi-arrow-repeat me-1"></i>Upgrades</a>
            </div>
            <div class="col-12 col-lg-6">
                <a class="btn btn-outline-dark btn-sm w-100" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/all/view"><i class="bi bi-grid-3x3-gap me-1"></i>All RAD Admin</a>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-errors" type="button" role="tab">Recent Errors</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-requests" type="button" role="tab">Recent Requests</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-errors" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Recent Errors</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/errorlog/view">View log</a>
                        </div>
                        <?php if (!empty($recentErrors)) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentErrors as $entry) { ?>
                                <div class="list-group-item">
                                    <div class="text-muted small"><?php echo htmlspecialchars($entry['timestamp']); ?></div>
                                    <pre class="mb-0 text-break recent-payload"><?php echo htmlspecialchars($entry['payload']); ?></pre>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted mb-0">No errors recorded for the latest day.</p>
                        <?php } ?>
                    </div>
                    <div class="tab-pane fade" id="tab-requests" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Recent Requests</h5>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/accesslog/view">View log</a>
                        </div>
                        <?php if (!empty($recentAccess)) { ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentAccess as $entry) { ?>
                                <div class="list-group-item">
                                    <div class="text-muted small"><?php echo htmlspecialchars($entry['timestamp']); ?></div>
                                    <pre class="mb-0 text-break recent-payload"><?php echo htmlspecialchars($entry['payload']); ?></pre>
                                </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted mb-0">No request records for the latest day.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Top Workspaces</h5>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/space/view">Manage workspaces</a>
                </div>
                <?php if (!empty($topWorkspaces)) { ?>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead>
                                <tr class="text-muted small">
                                    <th>Name</th>
                                    <th>Owner</th>
                                    <th>Status</th>
                                    <th class="text-end">Members</th>
                                    <th class="text-end">Bindings</th>
                                    <th>Last Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topWorkspaces as $workspace) {
                                    $status = (int)($workspace['livestatus'] ?? 1);
                                    $statusMap = [
                                        1 => ['label' => 'Active', 'class' => 'bg-success-subtle text-success'],
                                        2 => ['label' => 'Archived', 'class' => 'bg-secondary-subtle text-secondary'],
                                        3 => ['label' => 'Suspended', 'class' => 'bg-warning-subtle text-warning'],
                                        0 => ['label' => 'Inactive', 'class' => 'bg-light text-muted'],
                                    ];
                                    $badge = $statusMap[$status] ?? ['label' => 'Unknown', 'class' => 'bg-light text-muted'];
                                ?>
                                <tr>
                                    <td><a href="<?php echo $this->runData['route']['rad_admin_url'] . '/space/viewone/' . urlencode($workspace['uid']); ?>" class="fw-semibold"><?php echo htmlspecialchars($workspace['s_name']); ?></a></td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($workspace['owner_name'] ?? 'Unassigned'); ?>
                                        <?php if (!empty($workspace['owner_identity'])) { ?>
                                            <div class="text-muted small">@<?php echo htmlspecialchars($workspace['owner_identity']); ?></div>
                                        <?php } ?>
                                    </td>
                                    <td><span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['label']; ?></span></td>
                                    <td class="text-end"><?php echo number_format($workspace['member_count']); ?></td>
                                    <td class="text-end"><?php echo number_format($workspace['binding_count']); ?></td>
                                    <td><?php echo htmlspecialchars($workspace['last_member_activity'] ?: 'No activity'); ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No workspace data available.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Pending Upgrades</h5>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/view">Upgrade center</a>
                </div>
                <?php if (!empty($pendingUpgrades)) { ?>
                    <ul class="list-group list-group-flush mb-3">
                        <?php foreach ($pendingUpgrades as $upgrade) { ?>
                        <li class="list-group-item">
                            <div class="fw-semibold"><?php echo htmlspecialchars($upgrade['id']); ?></div>
                            <div class="text-muted small"><?php echo htmlspecialchars($upgrade['description']); ?></div>
                        </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted">No pending upgrade scripts detected.</p>
                <?php } ?>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/view" class="btn btn-outline-primary btn-sm mt-auto">
                    <i class="bi bi-people me-1"></i>Review memberships
                </a>
            </div>
        </div>
    </div>
</div>



<style>
.sparkline {
    height: 50px;
    margin-top: 0.5rem;
}
.sparkline .uif-chart-svg {
    width: 100% !important;
    height: 50px !important;
}
.dashboard-link {
    color: #0d6efd;
    transition: color 0.15s ease;
}
.dashboard-link:hover,
.dashboard-link:focus {
    color: #0a58ca;
    text-decoration: none;
}
.recent-payload {
    max-height: 120px;
    overflow: hidden;
}
.recent-payload.is-expanded {
    max-height: none;
}
</style>
<script>
(function () {
    document.querySelectorAll('[data-uif="tooltip"]').forEach(function (el) {
        if (window.RadAdminUI && window.RadAdminUI.initTooltips) {
            window.RadAdminUI.initTooltips(el.parentNode || document);
        }
    });
    document.querySelectorAll('.recent-payload').forEach(function (el) {
        el.addEventListener('click', function () {
            el.classList.toggle('is-expanded');
        });
    });

    if (window.BatoiUIF && window.BatoiUIF.chart && typeof window.BatoiUIF.chart.init === 'function') {
        document.querySelectorAll('[data-uif="chart"]').forEach(function (el) {
            if (!el.querySelector('.uif-chart-svg')) {
                window.BatoiUIF.chart.init(el);
            }
        });
    }
})();
</script>
    
</div>
