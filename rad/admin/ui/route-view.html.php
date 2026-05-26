<?php
$routes = $this->runData['data']['route'];
$microservice = $this->runData['data']['ms'];
$totalRoutes = count($routes);
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);
$canBulkArchive = !empty($this->runData['data']['can_bulk_archive']);
$canBulkDelete = !empty($this->runData['data']['can_bulk_delete']);
$scopeSlug = strtolower($microservice['s_scope'] ?? '');
$isPublicMicroservice = $scopeSlug === 'global';

$statusMeta = [
    '0' => ['label' => 'Inactive', 'badge' => 'info', 'slug' => 'inactive'],
    '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
    '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
    '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
];

$activeCount = 0;
$boundCount = 0;
$inheritCount = 0;

$permissionService = $this->runData['permissionService'] ?? null;

foreach ($routes as &$route) {
    $meta = $statusMeta[$route['livestatus']] ?? $statusMeta['0'];
    $route['status_meta'] = $meta;
    if ($meta['slug'] === 'active') {
        $activeCount++;
    }

    $route['scope_slug'] = strtolower($route['s_entity_scope'] ?? 'u');
    $route['scope_label'] = match ($route['scope_slug']) {
        'a' => 'API only',
        'ua' => 'User + API',
        default => 'User only',
    };
    $routeBindings = $permissionService ? $permissionService->hasBindings('route', (int)$route['id']) : false;
    $msBindings = $permissionService ? $permissionService->hasBindings('ms', (int)$microservice['id']) : false;
    $route['binding_slug'] = $routeBindings ? 'bound' : ($msBindings ? 'inherited' : 'none');
    if ($routeBindings) {
        $boundCount++;
    } elseif ($msBindings) {
        $inheritCount++;
    }

    $route['search_blob'] = strtolower(trim($route['s_name'] . ' ' . ($route['s_description'] ?? '') . ' ' . $route['uid'] . ' ' . $route['id']));
}
unset($route);

if ($totalRoutes > 0) {
?>
<h2 class="h5 mb-1">
    Routes of the Microservicelet <?php echo htmlspecialchars($microservice['s_name']); ?>
</h2>
<div class="text-muted small mb-2">
    ID: <?php echo (int)$microservice['id']; ?> · UID: <?php echo htmlspecialchars($microservice['uid']); ?>
</div>
<div class="text-muted mb-2">
    <?php
        $msStatus = $microservice['livestatus'] == '1' ? ['success','Active'] : ($microservice['livestatus'] == '2' ? ['danger','Archived'] : ['secondary','Inactive']);
        $scope = $microservice['s_scope'] ?? 'platform';
        $scopeLabel = ($scope === 'workspace') ? 'SaaS (workspace)' : 'Non-SaaS';
        $accessBadge = $isPublicMicroservice ? ['primary', 'Public'] : ['success', 'Private'];
    ?>
    <span class="badge bg-<?php echo $msStatus[0]; ?>"><?php echo $msStatus[1]; ?></span>
    <span class="badge bg-<?php echo $accessBadge[0]; ?>"><?php echo $accessBadge[1]; ?></span>
    <span class="badge bg-success"><?php echo htmlspecialchars($scopeLabel); ?></span>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        Showing <span id="route-visible-count"><?php echo $totalRoutes; ?></span> of <?php echo $totalRoutes; ?> routes
    </div>
    <div class="btn-group">
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/detail/<?php echo $microservice['uid']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Microservicelet Overview
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/add/<?php echo $microservice['uid']; ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle-fill me-1"></i>Add Route
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/addmultiple/<?php echo $microservice['uid']; ?>" class="btn btn-outline-primary">
            <i class="bi bi-list-check me-1"></i>Add Multiple Routes
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 route-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Total Routes</div>
                <div class="display-6 fw-semibold"><?php echo $totalRoutes; ?></div>
                <div class="small text-muted">Under <?php echo htmlspecialchars($microservice['s_name']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 route-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Active</div>
                <div class="display-6 fw-semibold text-success"><?php echo $activeCount; ?></div>
                <div class="small text-muted">Serving traffic</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 route-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Route Bindings</div>
                <div class="display-6 fw-semibold text-info"><?php echo $boundCount; ?></div>
                <div class="small text-muted">Explicit permission rules</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 route-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Inherited Bindings</div>
                <div class="display-6 fw-semibold text-primary"><?php echo $inheritCount; ?></div>
                <div class="small text-muted">Using microservice rules</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <?php if ($isPublicMicroservice): ?>
            <div class="alert alert-warning mb-4">
                This microservicelet is <strong>public</strong>; all of its routes are publicly accessible and permission bindings are disabled.
            </div>
        <?php endif; ?>
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="route-filter-search" placeholder="Name, UID, description...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="route-filter-status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Scope</label>
                <select class="form-select" id="route-filter-scope">
                    <option value="">All</option>
                    <option value="ua">User + API</option>
                    <option value="u">User only</option>
                    <option value="a">API only</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Permissions</label>
                <select class="form-select" id="route-filter-binding">
                    <option value="">All</option>
                    <option value="public">Public access</option>
                    <option value="bound">Route bindings</option>
                    <option value="inherited">Inherits microservice</option>
                    <option value="none">No bindings</option>
                </select>
            </div>
            <div class="col-md-2 text-md-end">
                <button class="btn btn-outline-secondary w-100" id="route-filter-reset">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($canBulkArchive): ?>
<form method="post" id="route-bulk-archive-form" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/bulkarchive/<?php echo htmlspecialchars($microservice['uid']); ?>" data-archive-action="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/bulkarchive/<?php echo htmlspecialchars($microservice['uid']); ?>" data-delete-action="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/bulkdelete/<?php echo htmlspecialchars($microservice['uid']); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="bulk_intent" id="route-bulk-intent" value="archive">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
        <div class="small text-muted">
            Selected routes: <span id="route-selected-count">0</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="route-select-visible">Select visible</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="route-clear-selection">Clear</button>
            <button type="submit" class="btn btn-sm btn-outline-danger" id="route-bulk-archive-btn" disabled>
                <i class="bi bi-archive me-1"></i>Bulk Archive
            </button>
            <?php if ($canBulkDelete): ?>
                <button type="button" class="btn btn-sm btn-danger" id="route-bulk-delete-btn" disabled>
                    <i class="bi bi-trash me-1"></i>Bulk Delete
                </button>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle" id="route-table">
                <thead>
                    <tr>
                        <?php if ($canBulkArchive): ?>
                            <th class="text-center" style="width: 36px;">
                                <input type="checkbox" id="route-select-all-visible" aria-label="Select visible routes">
                            </th>
                        <?php endif; ?>
                        <th>Route</th>
                        <th>Status</th>
                        <th>Scope</th>
                        <th>Permissions</th>
                        <th>Description</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): ?>
                        <tr
                            data-status="<?php echo $route['status_meta']['slug']; ?>"
                            data-scope="<?php echo htmlspecialchars($route['scope_slug']); ?>"
                            data-binding="<?php echo htmlspecialchars($isPublicMicroservice ? 'public' : $route['binding_slug']); ?>"
                            data-search="<?php echo htmlspecialchars($route['search_blob'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <?php if ($canBulkArchive): ?>
                                <td class="text-center">
                                    <?php if ($route['livestatus'] === '2') { ?>
                                        <input type="checkbox" disabled aria-label="Archived route">
                                    <?php } else { ?>
                                        <input type="checkbox" class="route-row-checkbox" name="route_ids[]" value="<?php echo (int)$route['id']; ?>" aria-label="Select route <?php echo htmlspecialchars($route['s_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php } ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <div class="fw-semibold">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/detail/<?php echo $route['uid']; ?>">
                                        <?php echo htmlspecialchars($route['s_name']); ?>
                                    </a>
                                </div>
                                <div class="text-muted small">ID: <?php echo $route['id']; ?> &middot; UID: <?php echo htmlspecialchars(substr($route['uid'], 0, 12)); ?>...</div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $route['status_meta']['badge']; ?>">
                                    <?php echo $route['status_meta']['label']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($route['scope_slug'] === 'a'): ?>
                                    <span class="badge bg-dark-subtle text-dark"><i class="bi bi-code-slash me-1"></i>API only</span>
                                <?php elseif ($route['scope_slug'] === 'ua'): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-people me-1"></i>User + API</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-person me-1"></i>User only</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isPublicMicroservice): ?>
                                    <span class="badge bg-light text-dark">Public access</span>
                                <?php elseif ($route['binding_slug'] === 'bound'): ?>
                                    <span class="badge bg-info text-dark">Route bindings</span>
                                <?php elseif ($route['binding_slug'] === 'inherited'): ?>
                                    <span class="badge bg-primary text-white">Inherits microservice</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No bindings</span>
                                <?php endif; ?>
                                <?php if (!$isPublicMicroservice): ?>
                                    <div class="small mt-1">
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view?object_type=route&object_id=<?php echo $route['id']; ?>" class="link-primary">
                                            Manage route bindings
                                        </a>
                                        <?php if ($route['binding_slug'] === 'inherited'): ?>
                                            <span class="text-muted mx-1">|</span>
                                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view?object_type=ms&object_id=<?php echo (int)$microservice['id']; ?>" class="link-secondary">
                                                Manage microservice bindings
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo $route['s_description'] ? htmlspecialchars($route['s_description']) : '—'; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/detail/<?php echo $route['uid']; ?>" class="btn btn-outline-secondary" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/help/<?php echo $route['uid']; ?>" class="btn btn-outline-dark" title="View help">
                                        <i class="bi bi-journal-text"></i>
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/code/<?php echo $route['uid']; ?>/<?php echo $microservice['uid']; ?>" class="btn btn-outline-primary" title="Edit code">
                                        <i class="bi bi-code"></i>
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/edit/<?php echo $route['uid']; ?>/<?php echo $microservice['uid']; ?>" class="btn btn-outline-success" title="Edit route">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if (!$isPublicMicroservice): ?>
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/permissionbindings/view?object_type=route&object_id=<?php echo $route['id']; ?>" class="btn btn-outline-info" title="Permissions">
                                            <i class="bi bi-key"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <div class="small text-muted" id="route-page-summary"></div>
            <div class="d-flex align-items-center gap-2">
                <label for="route-page-size" class="form-label small mb-0">Rows</label>
                <select class="form-select form-select-sm" id="route-page-size" style="width: auto;" data-pref="<?php echo $perPagePref; ?>">
                    <?php foreach ([10, 25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                    <?php } ?>
                </select>
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" id="route-page-prev">Prev</button>
                    <button class="btn btn-outline-secondary" id="route-page-next">Next</button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if ($canBulkArchive): ?>
</form>
<?php endif; ?>
<?php } else { ?>
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-route.svg" alt="No route created." height="200">
    <h1 class="h4 mt-3 text-center">There is no route available.</h1>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/add/<?php echo $microservice['uid']; ?>" class="btn btn-primary mt-3"><i class="bi bi-plus-circle-fill me-1"></i> Add Route</a>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/addmultiple/<?php echo $microservice['uid']; ?>" class="btn btn-outline-primary mt-3 ms-2"><i class="bi bi-list-check me-1"></i> Add Multiple Routes</a>
</div>
<?php } ?>
