<?php
$microservices = $this->runData['data']['ms'];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'scope' => '', 'status' => ''];
$numberOfMesh = count($microservices);
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);

$statusMeta = [
    '0' => ['label' => 'Inactive', 'badge' => 'info', 'slug' => 'inactive'],
    '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
    '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
    '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
];

$activeCount = 0;
$privateCount = 0;
$saasCount = 0;
$bindingsCount = 0;
$legacyCount = 0;

foreach ($microservices as &$ms) {
    $meta = $statusMeta[$ms['livestatus']] ?? $statusMeta['0'];
    $ms['status_meta'] = $meta;
    if ($meta['slug'] === 'active') {
        $activeCount++;
    }

    $ms['scope_slug'] = (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private';
    if ($ms['scope_slug'] === 'private') {
        $privateCount++;
    }

    $scope = $ms['s_scope'] ?? 'platform';
    $ms['saas_slug'] = ($scope === 'workspace') ? 'saas' : 'non-saas';
    if ($ms['saas_slug'] === 'saas') {
        $saasCount++;
    }

    $ms['type_slug'] = strtolower($ms['s_type'] ?: 'STA');

    $ms['has_bindings'] = $this->runData['permissionService']->hasBindings('ms', (int)$ms['id']);
    if ($ms['has_bindings']) {
        $bindingsCount++;
    } else {
        $legacyCount++;
    }

    $ms['search_blob'] = strtolower(
        trim(
            ($ms['s_name'] ?? '') . ' ' .
            ($ms['s_description'] ?? '') . ' ' .
            ($ms['s_tpl_name'] ?? '') . ' ' .
            $ms['uid'] . ' ' .
            $ms['id']
        )
    );
}
unset($ms);

if ($numberOfMesh > 0) {
?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small">
            Showing <span id="ms-visible-count"><?php echo $numberOfMesh; ?></span> of <?php echo $numberOfMesh; ?> microservicelets
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/devguide/diagrams" class="btn btn-outline-primary">
                <i class="bi bi-diagram-3 me-1"></i>Architecture Diagrams
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/aiwizard" class="btn btn-outline-info">
                <i class="bi bi-stars me-1"></i>AI Wizard
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/add" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i>Add Microservicelet
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/import" class="btn btn-outline-secondary">
                <i class="bi bi-box-arrow-in-down"></i> Import
            </a>
            <?php if ((int)($this->runData['entity']['id'] ?? 0) === 1) { ?>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/trash_clean"
                   class="btn btn-outline-danger"
                   onclick="return confirm('This will permanently delete all archived microservicelets and their routes, controllers, data fields/methods, and content blocks. Continue?');">
                    <i class="bi bi-trash3 me-1"></i>Delete Archived Microservicelets
                </a>
            <?php } ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-3" method="get">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Name, UID, description" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Scope</label>
                <select name="scope" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="global" <?php echo $filters['scope'] === 'global' ? 'selected' : ''; ?>>Global</option>
                    <option value="platform" <?php echo $filters['scope'] === 'platform' ? 'selected' : ''; ?>>Platform</option>
                    <option value="workspace" <?php echo $filters['scope'] === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Archived</option>
                    <option value="3" <?php echo $filters['status'] === '3' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                <?php if ($filters['q'] !== '' || $filters['scope'] !== '' || $filters['status'] !== '') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 shadow-sm ms-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Total</div>
                <div class="display-6 fw-semibold"><?php echo $numberOfMesh; ?></div>
                <div class="text-muted small">Registered microservicelets</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm ms-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Active</div>
                <div class="display-6 fw-semibold text-success"><?php echo $activeCount; ?></div>
                <div class="text-muted small">Currently serving traffic</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm ms-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Private Scope</div>
                <div class="display-6 fw-semibold text-primary"><?php echo $privateCount; ?></div>
                <div class="text-muted small">Restricted microservicelets</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm ms-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">SaaS Ready</div>
                <div class="display-6 fw-semibold text-info"><?php echo $saasCount; ?></div>
                <div class="text-muted small">Enabled for SaaS spaces</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="ms-filter-search" placeholder="Name, ID, template, description...">
            </div>
            <div class="col-md-2 col-lg-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="ms-filter-status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2">
                <label class="form-label">Type</label>
                <select class="form-select" id="ms-filter-type">
                    <option value="">All</option>
                    <option value="sta">Static</option>
                    <option value="dyn">Dynamic</option>
                    <option value="id">ID-Based</option>
                    <option value="uid">UID-Based</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2">
                <label class="form-label">Scope</label>
                <select class="form-select" id="ms-filter-scope">
                    <option value="">All</option>
                    <option value="private">Private</option>
                    <option value="public">Public</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2">
                <label class="form-label">SaaS</label>
                <select class="form-select" id="ms-filter-saas">
                    <option value="">All</option>
                    <option value="saas">Enabled</option>
                    <option value="non-saas">Disabled</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2">
                <label class="form-label">Permissions</label>
                <select class="form-select" id="ms-filter-binding">
                    <option value="">All</option>
                    <option value="bound">Bindings Active</option>
                    <option value="legacy">Legacy Roles</option>
                </select>
            </div>
            <div class="col-md-2 col-lg-2 text-lg-end">
                <button class="btn btn-outline-secondary w-100" id="ms-filter-reset">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="ms-table">
                <thead>
                    <tr>
                        <th>Microservicelet</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Scope</th>
                        <th>SaaS</th>
                        <th>User Roles</th>
                        <th>Template</th>
                        <th>Default Route</th>
                        <th class="text-center">Details</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($microservices as $ms): ?>
                    <?php
                        $routeLink = '<span class="text-muted small">Not configured</span>';
                        if (!empty($ms['s_default_route_id'])) {
                            $routeRow = $this->runData['db']->select('s_msroute', ['id' => $ms['s_default_route_id']], true);
                            if (!empty($routeRow)) {
                                $routeName = htmlspecialchars($routeRow[0]['s_name']);
                                $routeUid = htmlspecialchars($routeRow[0]['uid']);
                                $routeId = (int)$routeRow[0]['id'];
                                $routeLink = '<a href="' . $this->runData['route']['rad_admin_url'] . '/route/detail/' . $routeRow[0]['uid'] . '">' . $routeName . '</a>'
                                    . '<div class="text-muted small">ID: ' . $routeId . ' · UID: ' . $routeUid . '</div>';
                            }
                        }
                        $searchBlob = htmlspecialchars($ms['search_blob'], ENT_QUOTES, 'UTF-8');
                        $uidPreview = $ms['uid'] ? substr($ms['uid'], 0, 12) : 'n/a';
                    ?>
                    <tr
                        data-status="<?php echo $ms['status_meta']['slug']; ?>"
                        data-type="<?php echo htmlspecialchars($ms['type_slug']); ?>"
                        data-scope="<?php echo htmlspecialchars($ms['scope_slug']); ?>"
                        data-saas="<?php echo htmlspecialchars($ms['saas_slug']); ?>"
                        data-binding="<?php echo $ms['has_bindings'] ? 'bound' : 'legacy'; ?>"
                        data-search="<?php echo $searchBlob; ?>"
                    >
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($ms['s_name']); ?></div>
                            <div class="text-muted small">ID: <?php echo $ms['id']; ?> &middot; UID: <?php echo htmlspecialchars($uidPreview); ?>...</div>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $ms['status_meta']['badge']; ?>">
                                <?php echo $ms['status_meta']['label']; ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $typeLabels = [
                                'sta' => 'Static',
                                'dyn' => 'Dynamic',
                                'id' => 'ID-Based',
                                'uid' => 'UID-Based'
                            ];
                            echo $typeLabels[$ms['type_slug']] ?? strtoupper($ms['type_slug']);
                            ?>
                        </td>
                        <td>
                            <?php if ($ms['scope_slug'] === 'private'): ?>
                                <span class="badge bg-light text-success"><i class="bi bi-lock-fill me-1"></i>Private</span>
                            <?php else: ?>
                                <span class="badge bg-light text-info"><i class="bi bi-unlock-fill me-1"></i>Public</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ms['saas_slug'] === 'saas'): ?>
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-check2-circle me-1"></i>Enabled</span>
                            <?php else: ?>
                                <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-slash-circle me-1"></i>Disabled</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($ms['has_bindings']): ?>
                                <span class="badge bg-info text-dark">Bindings active</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Legacy roles</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($ms['s_tpl_name'] ?: 'Default'); ?></td>
                        <td>
                            <?php echo $routeLink; ?>
                            <?php if (empty($ms['s_default_route_id'])) { ?>
                                <div><span class="badge bg-warning text-dark">Missing default route</span></div>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid']; ?>" class="btn btn-sm btn-outline-secondary" title="View details">
                                <i class="bi bi-eye-fill"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <div class="small text-muted" id="ms-page-summary"></div>
            <div class="d-flex align-items-center gap-2">
                <label for="ms-page-size" class="form-label small mb-0">Rows</label>
                <select class="form-select form-select-sm" id="ms-page-size" style="width: auto;" data-pref="<?php echo $perPagePref; ?>">
                    <?php foreach ([10, 25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                    <?php } ?>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" id="ms-page-prev">Prev</button>
                    <button class="btn btn-outline-secondary" id="ms-page-next">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } else { ?>
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-ms.svg" alt="No microservice available." height="200">
    <h1 class="h4 mt-3 text-center">There is no Microservicelet available.</h1>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/add" class="btn btn-primary mt-3"><i class="bi bi-plus-circle-fill me-1"></i> Add Microservicelet</a>
</div>
<?php } ?>
