<?php
$appdataList = $this->runData['data']['appdata'] ?? [];
$orphanTables = $this->runData['data']['orphan_tables'] ?? [];
$totalTables = count($appdataList);
$hiddenControllers = $this->runData['data']['hidden_controllers'] ?? [];
$allTables = $this->runData['data']['all_tables'] ?? [];
$inventory = $this->runData['data']['system_table_inventory'] ?? [];
$numberOfData = count($appdataList);
$statusSummary = [
    'active' => 0,
    'inactive' => 0,
    'archived' => 0,
    'suspended' => 0,
];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => '', 'scope' => '', 'quick' => ''];
$listMode = $this->runData['data']['list_mode'] ?? 'normal';
$microserviceUsage = [];
$orphanCount = count($orphanTables);
foreach ($appdataList as $row) {
    $status = (int)($row['livestatus'] ?? 0);
    if ($status === 1) {
        $statusSummary['active']++;
    } elseif ($status === 2) {
        $statusSummary['archived']++;
    } elseif ($status === 0) {
        $statusSummary['inactive']++;
    } else {
        $statusSummary['suspended']++;
    }
    $msName = $row['s_ms_name'] ?? 'Unknown Microservicelet';
    if (!isset($microserviceUsage[$msName])) {
        $microserviceUsage[$msName] = 0;
    }
    $microserviceUsage[$msName]++;
}
$uniqueMicroservicelets = count($microserviceUsage);
$topMicroservicelets = $microserviceUsage;
arsort($topMicroservicelets);
$topMicroservicelets = array_slice($topMicroservicelets, 0, 5, true);
$syncStatus = $this->runData['data']['sync_status'] ?? null;
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');

if (!function_exists('rad_appdata_badge')) {
    function rad_appdata_badge(?int $status): array {
        $map = [
            0 => ['label' => 'Inactive', 'class' => 'text-bg-secondary'],
            1 => ['label' => 'Active', 'class' => 'text-bg-success'],
            2 => ['label' => 'Archived', 'class' => 'text-bg-danger'],
        ];
        return $map[$status] ?? ['label' => 'Suspended', 'class' => 'text-bg-warning'];
    }
}
?>

<div class="appdata-hero card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
        <div>
            <div class="text-uppercase small text-muted mb-1">Application Data</div>
            <h3 class="h4 mb-2">Manage microservicelet data models</h3>
            <p class="text-muted mb-0">Track tables that power your RAD experiences, link them with business classes and data models, and keep your schema documentation organised.</p>
        </div>
        <div class="appdata-hero-cta mt-3 mt-lg-0">
            <div class="btn-group">
                <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/add?scope=global'); ?>" class="btn btn-primary">
                    <i class="bi bi-globe me-1"></i>New Controller (Global)
                </a>
                <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/add?scope=scoped'); ?>" class="btn btn-outline-primary">
                    <i class="bi bi-diagram-3 me-1"></i>New Controller (Scoped)
                </a>
                <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url']); ?>/appdata/sync" class="btn btn-outline-secondary">
                    <i class="bi bi-diagram-3-fill me-1"></i>Sync & Diagnostics
                </a>
            </div>
        </div>
    </div>
</div>

<div class="text-muted small mb-4">
    Global: s_ms_id = 0 for shared/reference data. Scoped: tied to a microservicelet.
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php
                $baseUrl = $this->runData['route']['rad_admin_url'] . '/appdata/view';
                $quickFilters = [
                    ['key' => '', 'label' => 'All'],
                    ['key' => 'global', 'label' => 'Global only'],
                    ['key' => 'missing', 'label' => 'Missing table'],
                    ['key' => 'hidden', 'label' => 'Hidden controllers'],
                    ['key' => 'orphan', 'label' => 'Orphan tables'],
                ];
            ?>
            <?php foreach ($quickFilters as $chip) { ?>
                <?php
                    $params = [
                        'q' => $filters['q'] ?? '',
                        'status' => $filters['status'] ?? '',
                        'scope' => $filters['scope'] ?? '',
                    ];
                    if ($chip['key'] !== '') {
                        $params['quick'] = $chip['key'];
                    }
                    $url = $baseUrl;
                    if (!empty(array_filter($params, function ($v) { return $v !== ''; }))) {
                        $url .= '?' . http_build_query($params);
                    }
                    $isActive = ($filters['quick'] ?? '') === $chip['key'];
                ?>
                <a class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo htmlspecialchars($url); ?>">
                    <?php echo htmlspecialchars($chip['label']); ?>
                </a>
            <?php } ?>
        </div>
        <form class="row g-2 align-items-end" method="get">
            <input type="hidden" name="quick" value="<?php echo htmlspecialchars($filters['quick']); ?>">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, table, description" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $filters['status'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Archived</option>
                    <option value="3" <?php echo $filters['status'] === '3' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Scope</label>
                <select name="scope" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="global" <?php echo $filters['scope'] === 'global' ? 'selected' : ''; ?>>Global</option>
                    <option value="platform" <?php echo $filters['scope'] === 'platform' ? 'selected' : ''; ?>>Platform</option>
                    <option value="workspace" <?php echo $filters['scope'] === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                </select>
            </div>
            <div class="col-md-2 d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                <?php if ($filters['q'] !== '' || $filters['status'] !== '' || $filters['scope'] !== '' || $filters['quick'] !== '') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/appdata/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($orphanTables)) { ?>
<div class="alert alert-warning border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="fw-semibold">Unregistered a_* tables (<?php echo count($orphanTables); ?>)</div>
            <div class="text-muted small">These tables have no data controller yet. Register them via Sync & Diagnostics.</div>
        </div>
        <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url']); ?>/appdata/sync" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-diagram-3-fill me-1"></i>Open Sync
        </a>
    </div>
    <div class="mt-2 d-flex flex-wrap gap-1">
        <?php foreach ($orphanTables as $table) { ?>
            <span class="badge bg-light text-dark border"><code><?php echo htmlspecialchars($table); ?></code></span>
        <?php } ?>
    </div>
</div>
<?php } ?>

<?php if (!empty($hiddenControllers)) { ?>
<div class="alert alert-info border-0 shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="fw-semibold">Hidden business classes & data models (<?php echo count($hiddenControllers); ?>)</div>
            <div class="text-muted small">These entries exist but are hidden due to microservice visibility or role restrictions.</div>
        </div>
    </div>
    <div class="mt-2 d-flex flex-wrap gap-1">
        <?php foreach ($hiddenControllers as $ctrl) { ?>
            <span class="badge bg-light text-dark border">
                <?php echo htmlspecialchars($ctrl['s_name'] ?? ''); ?>
                <span class="text-muted">ID: <?php echo (int)($ctrl['id'] ?? 0); ?> · UID: <?php echo htmlspecialchars($ctrl['uid'] ?? '-'); ?></span>
                <span class="text-muted">(
                    <?php echo (int)($ctrl['s_ms_id'] ?? 0) === 0 ? 'Global' : htmlspecialchars($ctrl['s_ms_name'] ?? ''); ?>
                    · ID: <?php echo (int)($ctrl['s_ms_id'] ?? 0); ?>
                    · UID: <?php echo htmlspecialchars($ctrl['s_ms_uid'] ?? ''); ?>
                )</span>
            </span>
        <?php } ?>
    </div>
</div>
<?php } ?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1">Data Model Sync Utility</h5>
            <small class="text-muted">Scan for missing tables or schema drift across all application data models.</small>
            <?php if (!empty($syncStatus)) { ?>
                <div class="mt-2">
                    <?php
                        $result = strtolower($syncStatus['result'] ?? '');
                        $badgeClass = $result === 'success' ? 'text-bg-success' : 'text-bg-danger';
                        $time = !empty($syncStatus['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($syncStatus['timestamp'], $timezone, 'M j, g:i a') : '';
                    ?>
                    <span class="badge <?php echo $badgeClass; ?>">
                        Last run: <?php echo htmlspecialchars(ucfirst($syncStatus['result'] ?? '')); ?><?php echo $time ? ' · ' . htmlspecialchars($time) : ''; ?>
                    </span>
                </div>
            <?php } ?>
        </div>
        <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url']); ?>/appdata/sync" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3"></i> Open Sync Tool
        </a>
    </div>
    <?php if (!empty($allTables)) { ?>
        <div class="border-top px-3 py-2 d-flex flex-wrap align-items-center gap-2">
            <div class="fw-semibold small">Detected a_* tables (<?php echo count($allTables); ?>)</div>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($allTables as $tbl) { ?>
                    <span class="badge bg-light text-dark border"><code><?php echo htmlspecialchars($tbl); ?></code></span>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($numberOfData > 0) { ?>
<div class="row g-3 mb-4 appdata-stats">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Tables</div>
                <div class="display-6 fw-semibold"><?php echo number_format($numberOfData); ?></div>
                <small class="text-muted">Tracked data models</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Active</div>
                <div class="display-6 fw-semibold text-success"><?php echo number_format($statusSummary['active']); ?></div>
                <small class="text-muted">Serving live traffic</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Inactive</div>
                <div class="display-6 fw-semibold text-secondary"><?php echo number_format($statusSummary['inactive']); ?></div>
                <small class="text-muted">Awaiting activation</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Microservicelets</div>
                <div class="display-6 fw-semibold text-primary"><?php echo number_format($uniqueMicroservicelets); ?></div>
                <small class="text-muted">Owning a data model</small>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Orphan Tables</div>
                <div class="display-6 fw-semibold text-warning"><?php echo number_format($orphanCount); ?></div>
                <small class="text-muted">Tables without a model</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100 appdata-quick-actions">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="card-title mb-0">Quick actions</h5>
                        <small class="text-muted">Speed up day-to-day modelling work</small>
                    </div>
                    <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/add'); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3 me-1"></i>New from scratch
                    </a>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="appdata-quick-card border rounded-3 p-3 h-100">
                            <div class="d-flex align-items-center mb-2">
                                <span class="appdata-quick-icon text-primary me-2"><i class="bi bi-cloud-upload"></i></span>
                                <strong>Import table definition</strong>
                            </div>
                            <p class="text-muted small mb-2">Start from an existing SQL table using the execute SQL tool.</p>
                            <a class="small" href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/executesql'); ?>">Open SQL console <i class="bi bi-arrow-up-right ms-1"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6">
                            <div class="appdata-quick-card border rounded-3 p-3 h-100">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="appdata-quick-icon text-warning me-2"><i class="bi bi-diagram-2"></i></span>
                                    <strong>Review linked business classes</strong>
                                </div>
                                <p class="text-muted small mb-2">Ensure the right business logic is reading/writing your tables.</p>
                                <a class="small" href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/controller/view'); ?>">Go to business classes <i class="bi bi-arrow-up-right ms-1"></i></a>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100 appdata-usage-card">
            <div class="card-body">
                <h5 class="card-title">Usage highlights</h5>
                <p class="text-muted small mb-3">Top microservicelets owning data tables.</p>
                <?php if (!empty($topMicroservicelets)) { ?>
                    <ul class="list-group list-group-flush appdata-usage-list">
                        <?php foreach ($topMicroservicelets as $msName => $count) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="fw-semibold"><?php echo htmlspecialchars($msName); ?></span>
                                <span class="badge rounded-pill text-bg-light"><?php echo number_format($count); ?></span>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="text-muted mb-0">Data will appear once tables are registered.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header border-0 bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="mb-0">
                <?php echo $listMode === 'hidden' ? 'Hidden data models' : ($listMode === 'orphan' ? 'Orphan tables' : 'All application data tables'); ?>
            </h5>
            <small class="text-muted">Search, sort, and export the current catalogue.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="rad-table-tools input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control" placeholder="Search table" data-uif-table-filter="#msTable">
            </div>
            <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/add'); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg me-1"></i>Table
            </a>
            <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/executesql'); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-terminal me-1"></i>SQL
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table
                class="table table-hover align-middle"
                id="msTable"
                data-uif="table"
            >
                <thead class="table-light">
                    <tr>
                        <th data-uif-sort="asc">Data Model</th>
                        <th data-uif-sort="asc">Table</th>
                        <th data-uif-sort="asc">Status</th>
                        <th data-uif-sort="asc">Microservicelet</th>
                        <th data-uif-sort="asc">Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appdataList as $appdata) { ?>
                        <?php $badge = rad_appdata_badge(isset($appdata['livestatus']) ? (int)$appdata['livestatus'] : null); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($appdata['s_name'] ?? 'Unknown'); ?></div>
                                <small class="text-muted">ID: <?php echo (int)($appdata['id'] ?? 0); ?> · UID: <?php echo htmlspecialchars($appdata['uid'] ?? '-'); ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><code><?php echo htmlspecialchars($appdata['s_table_name'] ?? ''); ?></code></div>
                                <?php
                                    echo !empty($appdata['table_exists'])
                                        ? '<span class="badge text-bg-success">Table exists</span>'
                                        : '<span class="badge text-bg-warning">Missing table</span>';
                                ?>
                                <?php if (!empty($appdata['is_orphan_table'])) { ?>
                                    <span class="badge text-bg-danger">Orphan</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($appdata['livestatus'] === null) { ?>
                                    <span class="text-muted">—</span>
                                <?php } else { ?>
                                    <span class="badge <?php echo $badge['class']; ?>"><?php echo $badge['label']; ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (!empty($appdata['s_ms_uid'])) { ?>
                                    <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $appdata['s_ms_uid']); ?>" class="fw-semibold">
                                        <?php echo htmlspecialchars($appdata['s_ms_name'] ?? 'Microservicelet'); ?>
                                    </a>
                                    <div class="small text-muted">ID: <?php echo (int)($appdata['s_ms_id'] ?? 0); ?> · UID: <?php echo htmlspecialchars($appdata['s_ms_uid']); ?></div>
                                <?php } else { ?>
                                    <?php echo htmlspecialchars($appdata['s_ms_name'] ?? ''); ?>
                                    <div class="small text-muted">ID: 0 · UID: —</div>
                                <?php } ?>
                            </td>
                            <td class="text-muted"><?php echo htmlspecialchars($appdata['s_description'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($appdata['uid'])) { ?>
                                    <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/controller/detail/' . ($appdata['uid'] ?? '')); ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                <?php } else { ?>
                                    <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url']); ?>/appdata/sync" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-diagram-3"></i> Sync
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } else { ?>
<div class="text-center py-5">
    <img src="<?php echo htmlspecialchars($this->runData['route']['rad_assets_url']); ?>/img/no-ms.svg" alt="No application data table created." height="200">
    <h2 class="h4 mt-3">No application data tables yet</h2>
    <p class="text-muted">Start by creating a table or import an existing SQL schema.</p>
    <div class="d-flex justify-content-center gap-2">
        <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/add'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Create table
        </a>
        <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/executesql'); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-terminal me-1"></i>Execute SQL
        </a>
    </div>
</div>
<?php } ?>
