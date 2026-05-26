<?php
$objectType = $this->runData['data']['object_type'];
$objectId = $this->runData['data']['object_id'];
$bindings = $this->runData['data']['bindings'];
$validBindings = $this->runData['data']['valid_bindings'] ?? $bindings;
$invalidBindings = $this->runData['data']['invalid_bindings'] ?? [];
$roles = $this->runData['data']['roles'];
$rolesById = $this->runData['data']['roles_by_id'] ?? [];
$routesById = $this->runData['data']['routes_by_id'] ?? [];
$msById = $this->runData['data']['ms_by_id'] ?? [];
$objects = $this->runData['data']['objects'];
$objectTypes = $this->runData['data']['object_types'] ?? ['ms' => 'Microservicelet', 'route' => 'Route'];
$routeRestriction = $this->runData['data']['route_public_restricted'] ?? false;
$selectedRouteScope = $this->runData['data']['selected_route_scope'] ?? '';
$msGlobalRestricted = $this->runData['data']['ms_global_restricted'] ?? false;
$childRoutes = $this->runData['data']['child_routes'] ?? [];
$canManage = !empty($this->runData['data']['can_idm_manage']);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$msOptions = [];
foreach ($objects['ms'] ?? [] as $ms) {
    $label = $ms['s_name'] ?? ('#' . $ms['id']);
    $label .= ' · ID: ' . (int)$ms['id'];
    if (!empty($ms['uid'])) {
        $label .= ' · UID: ' . $ms['uid'];
    }
    $msOptions[] = [
        'id' => (int)$ms['id'],
        'label' => $label,
    ];
}
$routeOptions = [];
foreach ($objects['route'] ?? [] as $route) {
    $label = $route['s_name'] ?? $route['s_href'] ?? ('#' . $route['id']);
    if (!empty($route['is_public_ms'])) {
        $label .= ' (Public MS)';
    }
    $label .= ' · ID: ' . (int)$route['id'];
    if (!empty($route['uid'])) {
        $label .= ' · UID: ' . $route['uid'];
    }
    $msName = $route['ms_name'] ?? '';
    $msId = (int)($route['s_ms_id'] ?? 0);
    if ($msName !== '' || $msId > 0) {
        $label .= ' · MS: ' . ($msName !== '' ? $msName : 'Microservicelet');
        if ($msId > 0) {
            $label .= ' (ID: ' . $msId . ')';
        }
    }
    $routeOptions[] = [
        'id' => (int)$route['id'],
        'label' => $label,
    ];
}
?>

<style>
.permission-bindings-add .btn-success {
    background-color: #198754;
    border-color: #198754;
    color: #fff;
}
.permission-bindings-add .btn-success:hover,
.permission-bindings-add .btn-success:focus {
    background-color: #157347;
    border-color: #146c43;
    color: #fff;
}
</style>

<div class="card mb-4">
    <div class="card-body">
        <div class="alert alert-info">
            Permission bindings restrict access to a microservicelet or route by mapping it to one or more roles. Select the object, review current role bindings, and add or remove roles as needed. Route bindings apply only to private scopes; global routes ignore bindings.
            <?php if ($selectedRouteScope === 'global') { ?>
                <div class="mt-2 mb-0 text-muted small">
                    This route is global; bindings are ignored at runtime.
                </div>
            <?php } ?>
        </div>
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Object Type</label>
                <select name="object_type" class="form-select" id="pb-object-type">
                    <?php foreach ($objectTypes as $key => $label) { ?>
                        <option value="<?php echo $key; ?>" <?php echo $key === $objectType ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Object</label>
                <select name="object_id" class="form-select" id="pb-object-id">
                    <option value="0">Select</option>
                    <?php foreach ($objects[$objectType] as $object) { ?>
                        <option value="<?php echo $object['id']; ?>" <?php echo (int)$object['id'] === (int)$objectId ? 'selected' : ''; ?>>
                            <?php
                            $label = $object['s_name'] ?? $object['s_href'] ?? ('#' . $object['id']);
                            if (($objectType === 'route') && !empty($object['is_public_ms'])) {
                                $label .= ' (Public MS)';
                            }
                            if ($objectType === 'ms') {
                                $label .= ' · ID: ' . (int)$object['id'];
                                if (!empty($object['uid'])) {
                                    $label .= ' · UID: ' . $object['uid'];
                                }
                            } elseif ($objectType === 'route') {
                                $label .= ' · ID: ' . (int)$object['id'];
                                if (!empty($object['uid'])) {
                                    $label .= ' · UID: ' . $object['uid'];
                                }
                                $msName = $object['ms_name'] ?? '';
                                $msId = (int)($object['s_ms_id'] ?? 0);
                                if ($msName !== '' || $msId > 0) {
                                    $label .= ' · MS: ' . ($msName !== '' ? $msName : 'Microservicelet');
                                    if ($msId > 0) {
                                        $label .= ' (ID: ' . $msId . ')';
                                    }
                                }
                            }
                            echo htmlspecialchars($label);
                            ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Load</button>
            </div>
        </form>
    </div>
</div>

<?php
$selectedObject = null;
if ($objectId > 0) {
    foreach ($objects[$objectType] as $candidate) {
        if ((int)$candidate['id'] === (int)$objectId) {
            $selectedObject = $candidate;
            break;
        }
    }
}
$objectLink = null;
$objectLinkLabel = '';
if ($selectedObject) {
    $baseUrl = $this->runData['route']['rad_admin_url'];
    if ($objectType === 'ms' && !empty($selectedObject['uid'])) {
        $objectLink = $baseUrl . '/microservice/detail/' . $selectedObject['uid'];
        $objectLinkLabel = 'Go to Microservicelet';
    } elseif ($objectType === 'route' && !empty($selectedObject['uid'])) {
        $msRow = $this->runData['db']->select('s_ms', ['id' => $selectedObject['s_ms_id']], true);
        if (!empty($msRow)) {
            $objectLink = $baseUrl . '/route/edit/' . $selectedObject['uid'] . '/' . $msRow[0]['uid'];
            $objectLinkLabel = 'Go to Route';
        }
    }
}
?>

<?php if ($objectId > 0) { ?>
    <?php
    $routeMeta = null;
    if ($objectType === 'route') {
        $routeMeta = $selectedObject ?: ($routesById[$objectId] ?? null);
    }
    $msMeta = null;
    if ($objectType === 'ms') {
        $msMeta = $selectedObject ?: ($msById[$objectId] ?? null);
    } elseif ($objectType === 'route' && $routeMeta) {
        $msMeta = $msById[(int)($routeMeta['s_ms_id'] ?? 0)] ?? null;
    }
    $boundScopes = ['platform' => 0, 'workspace' => 0, 'ms' => 0, 'other' => 0];
    foreach ($validBindings as $binding) {
        $scope = strtolower((string)($rolesById[(int)$binding['s_role_id']]['s_scope'] ?? ''));
        if ($scope === 'platform') {
            $boundScopes['platform']++;
        } elseif ($scope === 'workspace') {
            $boundScopes['workspace']++;
        } elseif ($scope === 'ms') {
            $boundScopes['ms']++;
        } else {
            $boundScopes['other']++;
        }
    }
    ?>
    <?php if ($objectType === 'route' && $routeMeta) { ?>
        <div class="card mb-4">
            <div class="card-body d-flex flex-wrap justify-content-between gap-3">
                <div>
                    <div class="text-muted small">Route</div>
                    <h6 class="mb-1"><?php echo htmlspecialchars($routeMeta['s_name'] ?? $routeMeta['s_href'] ?? ('Route #' . (int)$objectId)); ?></h6>
                    <div class="text-muted small">
                        ID: <?php echo (int)($routeMeta['id'] ?? $objectId); ?>
                        <?php if (!empty($routeMeta['uid'])) { ?> · UID: <?php echo htmlspecialchars($routeMeta['uid']); ?><?php } ?>
                    </div>
                    <?php if (!empty($routeMeta['ms_name']) || !empty($routeMeta['s_ms_id'])) { ?>
                        <div class="text-muted small">
                            MS: <?php echo htmlspecialchars($routeMeta['ms_name'] ?? 'Microservicelet'); ?>
                            <?php if (!empty($routeMeta['s_ms_id'])) { ?> (ID: <?php echo (int)$routeMeta['s_ms_id']; ?>)<?php } ?>
                        </div>
                    <?php } ?>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Scope</div>
                    <span class="badge <?php echo $selectedRouteScope === 'public' ? 'bg-secondary' : 'bg-primary'; ?>">
                        <?php echo htmlspecialchars($selectedRouteScope ?: 'private'); ?>
                    </span>
                    <?php if ($objectLink) { ?>
                        <div class="mt-2">
                            <a href="<?php echo $objectLink; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-box-arrow-up-right"></i> <?php echo $objectLinkLabel; ?>
                            </a>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <div>
                    <h6 class="card-title mb-1">Bindings</h6>
                    <div class="text-muted small">Valid roles bound: <?php echo (int)count($validBindings); ?></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary pb-filter-btn active" data-scope="all">All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary pb-filter-btn" data-scope="platform">Platform (<?php echo (int)$boundScopes['platform']; ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary pb-filter-btn" data-scope="workspace">Workspace (<?php echo (int)$boundScopes['workspace']; ?>)</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary pb-filter-btn" data-scope="ms">MS (<?php echo (int)$boundScopes['ms']; ?>)</button>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <?php if (empty($validBindings)) { ?>
                        <div class="alert alert-info">No bindings found for this object.</div>
                    <?php } else { ?>
                        <div class="list-group">
                            <?php foreach ($validBindings as $binding) { ?>
                                <?php
                                $roleRow = $rolesById[(int)$binding['s_role_id']] ?? null;
                                $roleScope = strtolower((string)($roleRow['s_scope'] ?? ''));
                                $roleScopeLabel = $roleScope !== '' ? ucfirst($roleScope) : 'General';
                                $scopeBadge = $roleScope === 'platform' ? 'primary' : ($roleScope === 'workspace' ? 'success' : ($roleScope === 'ms' ? 'info' : 'secondary'));
                                $roleLabel = $roleRow['s_role_name'] ?? ('Role #' . (int)$binding['s_role_id']);
                                ?>
                                <div class="list-group-item d-flex justify-content-between align-items-start gap-3 pb-binding-row" data-role-scope="<?php echo htmlspecialchars($roleScope !== '' ? $roleScope : 'other'); ?>">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($roleLabel); ?></div>
                                        <div class="text-muted small">Role ID: <?php echo (int)$binding['s_role_id']; ?></div>
                                        <span class="badge bg-<?php echo $scopeBadge; ?>"><?php echo htmlspecialchars($roleScopeLabel); ?></span>
                                    </div>
                                    <?php if ($canManage) { ?>
                                        <form method="post" onsubmit="return confirm('Remove this binding?');">
                                            <input type="hidden" name="binding_id" value="<?php echo (int)$binding['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remove</button>
                                        </form>
                                    <?php } ?>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($invalidBindings)) { ?>
                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <div>
                                    <h6 class="card-title mb-1 text-warning">Legacy / Incompatible Bindings</h6>
                                    <div class="text-muted small">These bindings no longer match the current role-scope rules for this object.</div>
                                </div>
                                <?php if ($canManage) { ?>
                                    <form method="post" onsubmit="return confirm('Remove all incompatible bindings for this object?');">
                                        <input type="hidden" name="cleanup_invalid_bindings" value="1">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Cleanup Invalid Bindings
                                        </button>
                                    </form>
                                <?php } ?>
                            </div>
                            <div class="list-group">
                                <?php foreach ($invalidBindings as $binding) { ?>
                                    <?php
                                    $roleRow = $rolesById[(int)$binding['s_role_id']] ?? null;
                                    $roleScope = strtolower((string)($roleRow['s_scope'] ?? ''));
                                    $roleScopeLabel = $roleScope !== '' ? ucfirst($roleScope) : 'General';
                                    $roleLabel = $roleRow['s_role_name'] ?? ('Role #' . (int)$binding['s_role_id']);
                                    ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start gap-3 border-warning-subtle">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($roleLabel); ?></div>
                                            <div class="text-muted small">Role ID: <?php echo (int)$binding['s_role_id']; ?></div>
                                            <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($roleScopeLabel); ?></span>
                                            <span class="badge bg-light text-dark border">Invalid for this object</span>
                                        </div>
                                        <?php if ($canManage) { ?>
                                            <form method="post" onsubmit="return confirm('Remove this incompatible binding?');">
                                                <input type="hidden" name="binding_id" value="<?php echo (int)$binding['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remove</button>
                                            </form>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="col-lg-4">
                    <div class="border rounded p-3 h-100">
                        <div class="text-muted small mb-1">Binding target</div>
                        <?php if ($objectType === 'route' && $routeMeta) { ?>
                            <div class="fw-semibold"><?php echo htmlspecialchars($routeMeta['s_name'] ?? $routeMeta['s_href'] ?? ('Route #' . (int)$objectId)); ?></div>
                            <div class="text-muted small">Route ID: <?php echo (int)($routeMeta['id'] ?? $objectId); ?></div>
                            <?php if (!empty($routeMeta['uid'])) { ?>
                                <div class="text-muted small">UID: <?php echo htmlspecialchars($routeMeta['uid']); ?></div>
                            <?php } ?>
                        <?php } elseif ($objectType === 'ms' && $msMeta) { ?>
                            <div class="fw-semibold"><?php echo htmlspecialchars($msMeta['s_name'] ?? ('Microservicelet #' . (int)$objectId)); ?></div>
                            <div class="text-muted small">MS ID: <?php echo (int)($msMeta['id'] ?? $objectId); ?></div>
                            <?php if (!empty($msMeta['uid'])) { ?>
                                <div class="text-muted small">UID: <?php echo htmlspecialchars($msMeta['uid']); ?></div>
                            <?php } ?>
                        <?php } ?>
                        <?php if ($msMeta) { ?>
                            <div class="text-muted small mt-2">Microservicelet</div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($msMeta['s_name'] ?? 'Microservicelet'); ?></div>
                            <div class="text-muted small">ID: <?php echo (int)($msMeta['id'] ?? 0); ?></div>
                        <?php } ?>
                        <?php if ($objectType === 'route') { ?>
                            <div class="text-muted small mt-2">Scope</div>
                            <span class="badge <?php echo $selectedRouteScope === 'public' ? 'bg-secondary' : 'bg-primary'; ?>">
                                <?php echo htmlspecialchars($selectedRouteScope ?: 'private'); ?>
                            </span>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <?php if ($canManage) { ?>
                <hr>

                <h6 class="card-title">Add Binding</h6>
                <?php if ($objectType === 'ms' && $msGlobalRestricted): ?>
                    <div class="alert alert-warning">
                        Global microservicelets do not support permission bindings.
                    </div>
                <?php elseif ($objectType === 'route' && $routeRestriction): ?>
                    <div class="alert alert-warning">
                        Routes under public microservicelets are already open to all users and APIs. Access control cannot be configured for these routes.
                    </div>
                <?php else: ?>
                <?php
                $boundRoleIds = array_map(function ($binding) {
                    return (int)$binding['s_role_id'];
                }, $validBindings);
                $availableRoles = array_values(array_filter($roles, function ($role) use ($boundRoleIds) {
                    return !in_array((int)$role['id'], $boundRoleIds, true);
                }));
                ?>
                <form method="post" class="row g-3 permission-bindings-add">
                    <input type="hidden" name="object_type" value="<?php echo htmlspecialchars($objectType); ?>">
                    <input type="hidden" name="object_id" value="<?php echo (int)$objectId; ?>">

                    <div class="col-md-5">
                        <label class="form-label">Role</label>
                        <select name="role_id" class="form-select" required>
                            <option value="">Select a role</option>
                            <?php foreach ($availableRoles as $role) { ?>
                                <?php
                                $scope = strtolower($role['s_scope'] ?? '');
                                $scopeLabel = $scope !== '' ? ucfirst($scope) : 'General';
                                ?>
                                <option value="<?php echo $role['id']; ?>">
                                    <?php echo htmlspecialchars($role['s_role_name']); ?> (ID: <?php echo (int)$role['id']; ?> · <?php echo htmlspecialchars($scopeLabel); ?>)
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <?php if ($objectType === 'ms' && !$msGlobalRestricted) { ?>
                        <div class="col-12">
                            <div class="border rounded p-3">
                                <div class="fw-semibold mb-2">Route Binding Propagation</div>
                                <div class="text-muted small mb-3">
                                    Reset child route bindings only if you want affected routes to fall back to the parent microservicelet bindings at runtime.
                                </div>
                                <div class="d-flex flex-column gap-2">
                                    <label class="form-check">
                                        <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="none" checked>
                                        <span class="form-check-label">Do not change child route bindings</span>
                                    </label>
                                    <label class="form-check">
                                        <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="inherit_all">
                                        <span class="form-check-label">Reset all child routes to inherit this microservicelet</span>
                                    </label>
                                    <label class="form-check">
                                        <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="inherit_selected">
                                        <span class="form-check-label">Reset selected child routes to inherit this microservicelet</span>
                                    </label>
                                </div>
                                <div class="alert alert-warning mt-3 mb-0">
                                    Resetting a route to inherit removes its explicit route-level bindings.
                                </div>
                                <div class="pb-route-picker d-none mt-3">
                                    <?php if (empty($childRoutes)) { ?>
                                        <div class="alert alert-info mb-0">No child routes are available for this microservicelet.</div>
                                    <?php } else { ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm align-middle mb-0">
                                                <thead>
                                                    <tr>
                                                        <th style="width: 36px;"></th>
                                                        <th>Route</th>
                                                        <th>Current state</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($childRoutes as $childRoute) { ?>
                                                        <?php
                                                        $state = $childRoute['binding_state'] ?? 'none';
                                                        $stateLabel = $state === 'route' ? 'Route bindings' : ($state === 'inherits' ? 'Inherits microservice' : 'No bindings');
                                                        $stateBadge = $state === 'route' ? 'info text-dark' : ($state === 'inherits' ? 'primary' : 'secondary');
                                                        $routeLabel = $childRoute['s_name'] ?: ($childRoute['s_href'] ?: ('Route #' . (int)$childRoute['id']));
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <input type="checkbox" name="route_ids[]" value="<?php echo (int)$childRoute['id']; ?>">
                                                            </td>
                                                            <td>
                                                                <div class="fw-semibold"><?php echo htmlspecialchars($routeLabel); ?></div>
                                                                <div class="text-muted small">ID: <?php echo (int)$childRoute['id']; ?><?php if (!empty($childRoute['uid'])) { ?> · UID: <?php echo htmlspecialchars($childRoute['uid']); ?><?php } ?></div>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $stateBadge; ?>"><?php echo htmlspecialchars($stateLabel); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-success w-100 d-flex align-items-center justify-content-center gap-2 text-white">
                            <i class="bi bi-plus-circle"></i>
                            <span>Add</span>
                        </button>
                    </div>
                </form>

                <div class="mt-4">
                    <h6 class="card-title">Bulk Add Roles</h6>
                    <?php if (empty($availableRoles)) { ?>
                        <div class="alert alert-info mb-0">All roles are already bound to this object.</div>
                    <?php } else { ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="pb-select-all">Select all</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="pb-clear-all">Clear</button>
                            <input type="text" class="form-control form-control-sm w-auto" id="pb-filter" placeholder="Filter roles">
                        </div>
                        <form method="post" class="permission-bindings-bulk">
                            <input type="hidden" name="object_type" value="<?php echo htmlspecialchars($objectType); ?>">
                            <input type="hidden" name="object_id" value="<?php echo (int)$objectId; ?>">
                            <div class="row g-2" id="pb-role-grid">
                                <?php foreach ($availableRoles as $role) {
                                    $scope = strtolower($role['s_scope'] ?? '');
                                    $scopeLabel = $scope !== '' ? ucfirst($scope) : 'General';
                                    $scopeTone = $scope === 'platform' ? 'primary' : ($scope === 'workspace' ? 'success' : 'secondary');
                                ?>
                                    <div class="col-md-4 pb-role-item" data-role="<?php echo htmlspecialchars(strtolower($role['s_role_name'])); ?>">
                                        <label class="border rounded p-2 w-100 d-flex align-items-center gap-2">
                                            <input type="checkbox" name="role_ids[]" value="<?php echo (int)$role['id']; ?>">
                                            <div>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($role['s_role_name']); ?></div>
                                                <div class="text-muted small">ID: <?php echo (int)$role['id']; ?></div>
                                                <span class="badge bg-<?php echo $scopeTone; ?>"><?php echo htmlspecialchars($scopeLabel); ?></span>
                                            </div>
                                        </label>
                                    </div>
                                <?php } ?>
                            </div>
                            <?php if ($objectType === 'ms' && !$msGlobalRestricted) { ?>
                                <div class="border rounded p-3 mt-3">
                                    <div class="fw-semibold mb-2">Route Binding Propagation</div>
                                    <div class="text-muted small mb-3">
                                        Use this only if affected child routes should stop using their explicit route bindings and inherit from the microservicelet.
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <label class="form-check">
                                            <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="none" checked>
                                            <span class="form-check-label">Do not change child route bindings</span>
                                        </label>
                                        <label class="form-check">
                                            <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="inherit_all">
                                            <span class="form-check-label">Reset all child routes to inherit this microservicelet</span>
                                        </label>
                                        <label class="form-check">
                                            <input class="form-check-input pb-propagation-mode" type="radio" name="route_binding_propagation" value="inherit_selected">
                                            <span class="form-check-label">Reset selected child routes to inherit this microservicelet</span>
                                        </label>
                                    </div>
                                    <div class="pb-route-picker d-none mt-3">
                                        <?php if (empty($childRoutes)) { ?>
                                            <div class="alert alert-info mb-0">No child routes are available for this microservicelet.</div>
                                        <?php } else { ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 36px;"></th>
                                                            <th>Route</th>
                                                            <th>Current state</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($childRoutes as $childRoute) { ?>
                                                            <?php
                                                            $state = $childRoute['binding_state'] ?? 'none';
                                                            $stateLabel = $state === 'route' ? 'Route bindings' : ($state === 'inherits' ? 'Inherits microservice' : 'No bindings');
                                                            $stateBadge = $state === 'route' ? 'info text-dark' : ($state === 'inherits' ? 'primary' : 'secondary');
                                                            $routeLabel = $childRoute['s_name'] ?: ($childRoute['s_href'] ?: ('Route #' . (int)$childRoute['id']));
                                                            ?>
                                                            <tr>
                                                                <td>
                                                                    <input type="checkbox" name="route_ids[]" value="<?php echo (int)$childRoute['id']; ?>">
                                                                </td>
                                                                <td>
                                                                    <div class="fw-semibold"><?php echo htmlspecialchars($routeLabel); ?></div>
                                                                    <div class="text-muted small">ID: <?php echo (int)$childRoute['id']; ?><?php if (!empty($childRoute['uid'])) { ?> · UID: <?php echo htmlspecialchars($childRoute['uid']); ?><?php } ?></div>
                                                                </td>
                                                                <td>
                                                                    <span class="badge bg-<?php echo $stateBadge; ?>"><?php echo htmlspecialchars($stateLabel); ?></span>
                                                                </td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success text-white">
                                    <i class="bi bi-check2-square me-1"></i>Apply bindings
                                </button>
                            </div>
                        </form>
                    <?php } ?>
                </div>
                <?php endif; ?>
            <?php } ?>
        </div>
    </div>
<?php } else { ?>
    <div class="alert alert-info">Select an object to view or add bindings.</div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAllBtn = document.getElementById('pb-select-all');
    var clearAllBtn = document.getElementById('pb-clear-all');
    var filterInput = document.getElementById('pb-filter');
    var grid = document.getElementById('pb-role-grid');
    var objectTypeSelect = document.getElementById('pb-object-type');
    var objectSelect = document.getElementById('pb-object-id');
    var msOptions = <?php echo json_encode($msOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    var routeOptions = <?php echo json_encode($routeOptions, JSON_INVALID_UTF8_SUBSTITUTE); ?>;

    function applyScopeFilter(scope) {
        document.querySelectorAll('.pb-binding-row').forEach(function (row) {
            var rowScope = (row.getAttribute('data-role-scope') || 'other').toLowerCase();
            if (scope === 'all' || rowScope === scope) {
                row.classList.remove('d-none');
            } else {
                row.classList.add('d-none');
            }
        });
    }

    function populateObjects(list, selectedId) {
        if (!objectSelect) {
            return;
        }
        objectSelect.innerHTML = '';
        var placeholder = document.createElement('option');
        placeholder.value = '0';
        placeholder.textContent = 'Select';
        objectSelect.appendChild(placeholder);
        list.forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = String(item.id);
            opt.textContent = item.label;
            if (selectedId && String(item.id) === String(selectedId)) {
                opt.selected = true;
            }
            objectSelect.appendChild(opt);
        });
    }

    if (objectTypeSelect && objectSelect) {
        objectTypeSelect.addEventListener('change', function () {
            var type = objectTypeSelect.value;
            var currentId = objectSelect.value;
            var list = type === 'route' ? routeOptions : msOptions;
            var exists = list.some(function (item) { return String(item.id) === String(currentId); });
            populateObjects(list, exists ? currentId : '');
        });
    }

    if (grid) {
        var items = grid.querySelectorAll('.pb-role-item');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                grid.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = true;
                });
            });
        }
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function () {
                grid.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                    cb.checked = false;
                });
            });
        }
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                var q = this.value.toLowerCase();
                items.forEach(function (item) {
                    var name = item.getAttribute('data-role') || '';
                    item.style.display = name.indexOf(q) !== -1 ? '' : 'none';
                });
            });
        }
    }

    document.querySelectorAll('.pb-filter-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var scope = (btn.getAttribute('data-scope') || 'all').toLowerCase();
            document.querySelectorAll('.pb-filter-btn').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            applyScopeFilter(scope);
        });
    });

    document.querySelectorAll('form').forEach(function (form) {
        var propagationInputs = form.querySelectorAll('.pb-propagation-mode');
        var routePicker = form.querySelector('.pb-route-picker');
        if (!propagationInputs.length || !routePicker) {
            return;
        }
        function syncRoutePicker() {
            var selectedMode = '';
            propagationInputs.forEach(function (input) {
                if (input.checked) {
                    selectedMode = input.value;
                }
            });
            routePicker.classList.toggle('d-none', selectedMode !== 'inherit_selected');
        }
        propagationInputs.forEach(function (input) {
            input.addEventListener('change', syncRoutePicker);
        });
        syncRoutePicker();
    });
});
</script>
