<?php
$sync = $this->runData['data']['sync'] ?? [];
$missingTables = $sync['missing_tables'] ?? [];
$orphanTables = $sync['orphan_tables'] ?? [];
$columnIssues = $sync['column_issues'] ?? [];
$microservices = $this->runData['data']['microservices'] ?? [];
$pending = $this->runData['data']['sync_confirm'] ?? null;
$logs = $this->runData['data']['sync_logs'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$formUrl = $radAdminUrl . '/appdata/runsync';
$csrfToken = htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8');
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$metrics = [
    'controllers' => $this->runData['data']['total_controllers'] ?? 0,
    'tables' => $this->runData['data']['total_tables'] ?? 0,
    'missing_tables' => count($missingTables),
    'orphan_tables' => count($orphanTables),
    'column_issues' => count($columnIssues),
];
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <p class="text-muted mb-0">
            Detect missing tables, orphaned data models, and schema drift across all Data Model controllers. Use the actions below to repair inconsistencies.
        </p>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header">
        <strong>Recent sync runs</strong>
        <div class="text-muted small">Latest activity is shown first.</div>
    </div>
    <div class="card-body">
        <?php if (empty($logs)) { ?>
            <p class="text-muted mb-0">No sync activity recorded yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Timestamp</th>
                            <th>Summary</th>
                            <th>Result</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <?php $ts = !empty($log['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($log['timestamp'], $timezone, 'M j, Y g:i a') : null; ?>
                            <tr>
                                <td><?php echo $ts ? htmlspecialchars($ts) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($log['summary'] ?? ''); ?></td>
                                <td>
                                    <span class="badge <?php echo (isset($log['result']) && $log['result'] === 'success') ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($log['result'] ?? '')); ?>
                                    </span>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Missing tables</div>
                <div class="display-6"><?php echo count($missingTables); ?></div>
                <small class="text-muted">Controllers without physical tables</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Orphan tables</div>
                <div class="display-6"><?php echo count($orphanTables); ?></div>
                <small class="text-muted">Tables without Data Model controllers</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Inventory</div>
                <div class="display-6"><?php echo htmlspecialchars($metrics['tables']); ?></div>
                <small class="text-muted">Total a_* tables (controllers: <?php echo htmlspecialchars($metrics['controllers']); ?>)</small>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pending)) { ?>
<div class="card border-warning mb-4">
    <div class="card-header bg-warning-subtle text-warning d-flex justify-content-between align-items-center">
        <strong>Pending confirmation</strong>
        <div class="btn-group btn-group-sm">
            <a href="<?php echo $radAdminUrl; ?>/appdata/cancelsync" class="btn btn-outline-warning">Cancel</a>
        </div>
    </div>
    <div class="card-body">
        <p class="mb-3"><?php echo htmlspecialchars($pending['message'] ?? ''); ?></p>
        <form method="post" action="<?php echo $formUrl; ?>" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="<?php echo htmlspecialchars($pending['action']); ?>">
            <input type="hidden" name="confirm" value="1">
            <button class="btn btn-warning">
                <i class="bi bi-check2-square"></i> Confirm and execute
            </button>
        </form>
    </div>
</div>
<?php } ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Controllers without tables</strong>
            <div class="text-muted small">Create base tables and rebuild columns from metadata.</div>
        </div>
        <form method="post" action="<?php echo $formUrl; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="repair_tables">
            <button class="btn btn-primary btn-sm" <?php echo empty($missingTables) ? 'disabled' : ''; ?>>
                <i class="bi bi-tools"></i> Create Tables
            </button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($missingTables)) { ?>
            <p class="text-muted mb-0">All data models have matching tables.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Controller</th>
                            <th>Microservicelet</th>
                            <th>Expected Table</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missingTables as $row) {
                            $controller = $row['controller'];
                            $msName = $row['microservice']['s_name'] ?? 'Unknown';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($controller['s_name']); ?></td>
                            <td><?php echo htmlspecialchars($msName); ?></td>
                            <td><code><?php echo htmlspecialchars($row['table']); ?></code></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>Schema inconsistencies</strong>
            <div class="text-muted small">Add missing columns to align metadata and tables.</div>
        </div>
        <form method="post" action="<?php echo $formUrl; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="sync_columns">
            <button class="btn btn-outline-primary btn-sm" <?php echo empty($columnIssues) ? 'disabled' : ''; ?>>
                <i class="bi bi-arrow-repeat"></i> Sync All
            </button>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($columnIssues)) { ?>
            <p class="text-muted mb-0">No schema differences detected.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Controller</th>
                            <th>Scope</th>
                            <th>Missing Columns</th>
                            <th>Extra Columns</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($columnIssues as $issue) {
                            $controller = $issue['controller'];
                            $scope = ((int)($controller['s_ms_id'] ?? 0) === 0) ? 'Global' : 'Microservicelet';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($controller['s_name']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($scope); ?>
                                <?php if ((int)($controller['s_ms_id'] ?? 0) === 0): ?>
                                    <span class="badge text-bg-secondary ms-1">s_ms_id = 0</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo empty($issue['missing']) ? '<span class="text-muted">None</span>' : htmlspecialchars(implode(', ', $issue['missing'])); ?></td>
                            <td><?php echo empty($issue['extra']) ? '<span class="text-muted">None</span>' : htmlspecialchars(implode(', ', $issue['extra'])); ?></td>
                            <td>
                                <form method="post" action="<?php echo $formUrl; ?>" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="sync_columns">
                                    <input type="hidden" name="controller_id" value="<?php echo (int)$controller['id']; ?>">
                                    <button class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-magic"></i> Sync
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header">
        <strong>Tables without Data Model controllers</strong>
        <div class="text-muted small">Register orphan <code>a_*</code> tables by linking them to a microservicelet.</div>
    </div>
    <div class="card-body">
        <?php if (empty($orphanTables)) { ?>
            <p class="text-muted mb-0">No orphan tables detected.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Table</th>
                            <th>Register as</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orphanTables as $table) { ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($table); ?></code></td>
                            <td>
                                <form class="row g-2 align-items-center" method="post" action="<?php echo $formUrl; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="register_table">
                                    <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($table); ?>">
                                    <input type="hidden" name="microservice_id" value="0">
                                    <div class="col-auto">
                                        <span class="badge text-bg-secondary">Global (s_ms_id = 0)</span>
                                    </div>
                                    <div class="col-auto">
                                        <button class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-plus-circle"></i> Register
                                        </button>
                                    </div>
                                </form>
                            </td>
                            <td></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
