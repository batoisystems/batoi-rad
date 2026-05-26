<?php
$memberships = $this->runData['data']['memberships'];
$roles = $this->runData['data']['roles'];
$workspaceRoles = $this->runData['data']['workspace_roles'] ?? [];
$microservices = $this->runData['data']['microservices'];
$spaces = $this->runData['data']['spaces'];
$currentSpace = $this->runData['data']['current_space'] ?? null;
$filters = $this->runData['data']['filters'];
$hasFilters = !empty(array_filter($filters));
$simulation = $this->runData['data']['simulation'] ?? null;
$canManageSimulation = !empty($this->runData['data']['can_idm_manage']);
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'pages' => 1, 'sort' => 'created_desc'];
$csrfToken = htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8');
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <p class="text-muted mb-0">Assign workspace roles to entities.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($filters['space_id']) && $currentSpace) { ?>
                <a href="#add-member-card" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Add Member
                </a>
            <?php } ?>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/role/view" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-shield-lock me-1"></i>Manage Roles
            </a>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#inviteModal">
                <i class="bi bi-envelope-plus me-1"></i>Invite Members
            </button>
        </div>
    </div>
</div>

<?php if (!empty($filters['space_id']) && $currentSpace) { ?>
<div class="card mb-4" id="add-member-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="mb-1">Add Member</h5>
                <div class="text-muted small">
                    Workspace: <?php echo htmlspecialchars($currentSpace['s_name'] ?? ('Workspace #' . (int)$filters['space_id'])); ?>
                    · ID: <?php echo (int)$filters['space_id']; ?>
                </div>
            </div>
        </div>
        <form method="post" class="row g-3" id="add-member-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="invite_space_id" value="<?php echo (int)$filters['space_id']; ?>">
            <input type="hidden" name="invite_scope_level" value="workspace">
            <input type="hidden" name="invite_ms_id" value="">
            <input type="hidden" name="invite_entity_id" value="">
            <div class="col-lg-7 position-relative" id="add-member-picker" data-search-endpoint="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/searchEntities">
                <label class="form-label">User</label>
                <input type="text" name="invite_entity" class="form-control" placeholder="Search existing users by name, email, or UID" autocomplete="off" required>
                <div class="list-group position-absolute w-100 shadow-sm d-none" data-add-member-results style="max-height:220px; overflow:auto; z-index:1020;"></div>
                <div class="form-text text-danger d-none" id="add-member-existing-warning">Membership already exists for this user in this workspace.</div>
            </div>
            <div class="col-lg-5">
                <label class="form-label">Workspace Role</label>
                <select name="invite_role_id" class="form-select" required>
                    <option value="">Select workspace role</option>
                    <?php foreach ($workspaceRoles as $role) { ?>
                        <option value="<?php echo (int)$role['id']; ?>"><?php echo htmlspecialchars($role['s_role_name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" name="invite_membership" value="1" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>Add Member
                </button>
            </div>
        </form>
    </div>
</div>
<?php } ?>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Workspace</label>
                <select name="space_id" class="form-select form-select-sm">
                    <option value="">All workspaces</option>
                    <?php foreach ($spaces as $space) { ?>
                        <option value="<?php echo $space['id']; ?>" <?php echo (int)$filters['space_id'] === (int)$space['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($space['s_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Archived</option>
                    <option value="3" <?php echo $filters['status'] === '3' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Role</label>
                <select name="role_id" class="form-select form-select-sm">
                    <option value="">All roles</option>
                    <?php foreach ($roles as $role) { ?>
                        <option value="<?php echo $role['id']; ?>" <?php echo (int)$filters['role_id'] === (int)$role['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role['s_role_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Scope</label>
                <select name="scope_level" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="workspace" <?php echo $filters['scope_level'] === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                    <option value="ms" <?php echo $filters['scope_level'] === 'ms' ? 'selected' : ''; ?>>Microservice</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Microservicelet</label>
                <select name="ms_id" class="form-select form-select-sm">
                    <option value="">All microservicelets</option>
                    <?php foreach ($microservices as $ms) { ?>
                        <option value="<?php echo $ms['id']; ?>" <?php echo (int)$filters['ms_id'] === (int)$ms['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ms['s_name']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search']); ?>" class="form-control form-control-sm" placeholder="Name, membership #, entity #">
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select form-select-sm">
                    <option value="created_desc" <?php echo ($pagination['sort'] ?? '') === 'created_desc' ? 'selected' : ''; ?>>Newest</option>
                    <option value="created_asc" <?php echo ($pagination['sort'] ?? '') === 'created_asc' ? 'selected' : ''; ?>>Oldest</option>
                    <option value="entity_asc" <?php echo ($pagination['sort'] ?? '') === 'entity_asc' ? 'selected' : ''; ?>>Entity A→Z</option>
                    <option value="entity_desc" <?php echo ($pagination['sort'] ?? '') === 'entity_desc' ? 'selected' : ''; ?>>Entity Z→A</option>
                    <option value="space_asc" <?php echo ($pagination['sort'] ?? '') === 'space_asc' ? 'selected' : ''; ?>>Workspace A→Z</option>
                    <option value="space_desc" <?php echo ($pagination['sort'] ?? '') === 'space_desc' ? 'selected' : ''; ?>>Workspace Z→A</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([25, 50, 100, 200] as $option) { ?>
                        <option value="<?php echo $option; ?>" <?php echo (int)$pagination['per_page'] === $option ? 'selected' : ''; ?>>
                            <?php echo $option; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-lg-2 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($hasFilters) { ?>
                    <a class="btn btn-outline-secondary btn-sm" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/view">
                        Reset
                    </a>
                <?php } ?>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="missing_role" name="missing_role" <?php echo !empty($filters['missing_role']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="missing_role">
                        Only memberships missing a role
                    </label>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($memberships)) { ?>
    <div class="alert alert-info">No memberships match the current filters.</div>
<?php } else { ?>
    <?php if ($simulation) { ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="card-title mb-0">Role Simulation · Membership #<?php echo (int)$simulation['membership']['id']; ?></h5>
                    <small class="text-muted">Entity: #<?php echo (int)$simulation['membership']['s_entity_id']; ?></small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#simulation-details" aria-expanded="true">
                    Toggle Details
                </button>
            </div>
            <div class="collapse show" id="simulation-details">
                <div class="row g-4">
                    <div class="col-lg-4">
                        <h6 class="text-muted text-uppercase small">Assigned Roles</h6>
                        <?php if (empty($simulation['roles'])) { ?>
                            <span class="badge bg-warning text-dark">No roles assigned</span>
                        <?php } else { ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($simulation['roles'] as $role) { ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($role['s_role_name'] ?? ('Role #' . $role['s_role_id'])); ?></div>
                                            <small class="text-muted">
                                                Scope: <?php echo htmlspecialchars($role['scope_label'] ?? $role['s_scope_level']); ?>
                                                <?php if (($role['s_scope_level'] ?? '') === 'ms' && !empty($role['s_ms_id'])) { ?>
                                                    · MS #<?php echo (int)$role['s_ms_id']; ?>
                                                <?php } ?>
                                            </small>
                                        </div>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>
                    <div class="col-lg-8">
                        <h6 class="text-muted text-uppercase small">Permission Bindings (by role)</h6>
                        <?php if (empty($simulation['bindings'])) { ?>
                            <p class="text-muted mb-0">No bindings reference these roles.</p>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr class="text-muted small">
                                            <th>Role</th>
                                            <th>Scope</th>
                                            <th>Object</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($simulation['bindings'] as $binding) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($binding['s_role_name'] ?? ('Role #' . $binding['s_role_id'])); ?></td>
                                                <td><?php echo htmlspecialchars($binding['scope_label'] ?? 'Workspace'); ?></td>
                                                <td>
                                                    <div><?php echo htmlspecialchars(strtoupper($binding['s_object_type'])); ?> #<?php echo htmlspecialchars($binding['s_object_id']); ?></div>
                                                    <?php if (!empty($binding['object_name'])) { ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($binding['object_name']); ?></small>
                                                    <?php } ?>
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
        </div>
    </div>
    <?php } ?>
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div>
                <div class="text-muted small">Bulk actions apply to selected memberships.</div>
                <div class="small text-muted">
                    Total: <?php echo (int)($pagination['total'] ?? 0); ?> memberships
                </div>
            </div>
            <form method="post" id="bulk-action-form" class="d-flex flex-wrap gap-2">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="selected_memberships" id="selected-memberships-input">
                <select name="bulk_action" class="form-select form-select-sm" style="max-width:200px;">
                    <option value="">Bulk action</option>
                    <option value="activate">Activate</option>
                    <option value="suspend">Suspend</option>
                    <option value="set_role">Set role</option>
                </select>
                <select name="bulk_role_id" class="form-select form-select-sm d-none" data-bulk-role>
                    <option value="">Role</option>
                    <?php foreach ($roles as $role) { ?>
                        <option value="<?php echo $role['id']; ?>" data-scope="<?php echo htmlspecialchars($role['s_scope']); ?>">
                            <?php echo htmlspecialchars($role['s_role_name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <select name="bulk_scope_level" class="form-select form-select-sm d-none" data-bulk-scope>
                    <option value="workspace">Workspace</option>
                    <option value="ms">Microservice</option>
                </select>
                <select name="bulk_ms_id" class="form-select form-select-sm d-none" data-bulk-ms>
                    <option value="">Microservicelet</option>
                    <?php foreach ($microservices as $ms) { ?>
                        <option value="<?php echo $ms['id']; ?>" data-space-id="<?php echo (int)($ms['space_id'] ?? 0); ?>"><?php echo htmlspecialchars($ms['s_name']); ?></option>
                    <?php } ?>
                </select>
                <input type="datetime-local" name="bulk_effective_from" class="form-control form-control-sm d-none" data-bulk-from>
                <input type="datetime-local" name="bulk_effective_to" class="form-control form-control-sm d-none" data-bulk-to>
                <button type="submit" class="btn btn-sm btn-outline-primary" data-bulk-submit disabled onclick="return confirm('Apply this bulk action to selected memberships?');">
                    Apply
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" id="membership-table">
                <thead>
                    <tr>
                        <th style="width:35px;"><input type="checkbox" id="bulk-select-all"></th>
                        <th>Membership</th>
                        <th>Entity</th>
                        <th>Workspace</th>
                        <th>Role</th>
                        <th style="width: 320px;">Set Role</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($memberships as $membership) { ?>
                    <tr data-membership-id="<?php echo $membership['id']; ?>" data-entity-id="<?php echo (int)($membership['s_entity_id'] ?? 0); ?>" data-space-id="<?php echo (int)($membership['space_id'] ?? 0); ?>">
                        <td><input type="checkbox" class="bulk-select" value="<?php echo $membership['id']; ?>"></td>
                        <td>
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong>#<?php echo $membership['id']; ?></strong><br>
                                    <small class="text-muted">Entity membership</small><br>
                                    <span class="badge bg-<?php echo (string)$membership['livestatus'] === '1' ? 'success' : 'secondary'; ?>">
                                        <?php
                                            $status = (string)($membership['livestatus'] ?? '');
                                            echo $status === '1' ? 'Active' : ($status === '2' ? 'Archived' : ($status === '3' ? 'Suspended' : 'Inactive'));
                                        ?>
                                    </span>
                                    <?php if (!empty($membership['createstamp'])) { ?>
                                        <div class="small text-muted">Created: <?php echo htmlspecialchars($membership['createstamp']); ?></div>
                                    <?php } ?>
                                    <?php if (!empty($membership['updatestamp'])) { ?>
                                        <div class="small text-muted">Updated: <?php echo htmlspecialchars($membership['updatestamp']); ?></div>
                                    <?php } ?>
                                </div>
                                <form method="post" class="ms-2 d-flex gap-1">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                    <button type="submit" name="simulate_membership" value="1" class="btn btn-sm btn-outline-secondary" title="Simulate effective access" <?php echo !$canManageSimulation ? 'disabled' : ''; ?>>
                                        <i class="bi bi-graph-up"></i>
                                    </button>
                                    <?php if ($simulation && (int)$simulation['membership']['id'] === (int)$membership['id']) { ?>
                                        <button type="submit" name="export_simulation" value="1" class="btn btn-sm btn-outline-primary" title="Export simulation CSV" <?php echo !$canManageSimulation ? 'disabled' : ''; ?>>
                                            <i class="bi bi-download"></i>
                                        </button>
                                    <?php } ?>
                                </form>
                            </div>
                        </td>
                        <td>
                            <div class="fw-semibold">
                                <?php if (!empty($membership['entity_uid'])) { ?>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/viewone/<?php echo htmlspecialchars($membership['entity_uid']); ?>">
                                        <?php echo htmlspecialchars($membership['principal_display']); ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo htmlspecialchars($membership['principal_display']); ?>
                                <?php } ?>
                            </div>
                            <div class="text-muted small"><?php echo htmlspecialchars($membership['principal_label']); ?></div>
                        </td>
                        <td>
                            <?php if (!empty($membership['space_uid'])) { ?>
                                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/space/viewone/<?php echo htmlspecialchars($membership['space_uid']); ?>">
                                    <?php echo htmlspecialchars($membership['space_name'] ?? '—'); ?>
                                </a><br>
                            <?php } else { ?>
                                <?php echo htmlspecialchars($membership['space_name'] ?? '—'); ?><br>
                            <?php } ?>
                            <small class="text-muted">ID: <?php echo (int)$membership['space_id']; ?></small>
                        </td>
                        <td>
                            <?php
                            $roleId = (int)($membership['s_role_id'] ?? 0);
                            $roleName = $membership['s_role_name'] ?? ($roleId ? ('Role #' . $roleId) : null);
                            $scopeLevel = $membership['s_scope_level'] ?? 'workspace';
                            $scope = $membership['s_scope'] ?? 'workspace';
                            $msId = $membership['s_ms_id'] ?? null;
                            $msName = $membership['ms_name'] ?? null;
                            $isSaas = in_array($scope, ['workspace', 'ms'], true);
                            $isInactive = (string)($membership['livestatus'] ?? '') !== '1';
                            ?>
                            <?php if ($roleId <= 0) { ?>
                                <span class="badge bg-warning text-dark">None</span>
                            <?php } else { ?>
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($roleName); ?></span>
                                        <small class="text-muted">
                                            · <?php echo htmlspecialchars($scopeLevel); ?>
                                            <?php if ($scopeLevel === 'ms' && !empty($msId)) { ?>
                                                (<?php echo htmlspecialchars($msName ?: ('MS #' . $msId)); ?>)
                                            <?php } ?>
                                            <?php if ($isSaas) { ?> · <span class="badge bg-info text-dark">SaaS</span><?php } else { ?> · <span class="badge bg-light text-dark">Non-SaaS</span><?php } ?>
                                        </small>
                                        <?php if (!empty($membership['s_effective_from']) || !empty($membership['s_effective_to'])) { ?>
                                            <div class="small text-muted">
                                                Effective:
                                                <?php echo !empty($membership['s_effective_from']) ? htmlspecialchars($membership['s_effective_from']) : 'Now'; ?>
                                                → <?php echo !empty($membership['s_effective_to']) ? htmlspecialchars($membership['s_effective_to']) : 'No end'; ?>
                                            </div>
                                        <?php } ?>
                                        <?php if ($scopeLevel === 'ms' && empty($msId)) { ?>
                                            <div class="small text-danger">MS role missing microservicelet</div>
                                        <?php } ?>
                                        <?php if ($isInactive) { ?>
                                            <div class="small text-warning">Inactive membership with role</div>
                                        <?php } ?>
                                    </div>
                                    <form method="post" class="mb-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <input type="hidden" name="membership_id" value="<?php echo (int)$membership['id']; ?>">
                                        <button type="submit" name="remove_membership_role" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this role assignment?');">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                </div>
                            <?php } ?>
                        </td>
                        <td>
                            <form method="post" class="row gx-2 gy-1">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="membership_id" value="<?php echo $membership['id']; ?>">
                                <div class="col-12">
                                    <select name="role_id" class="form-select form-select-sm role-selector" data-scope-target="scope-<?php echo $membership['id']; ?>" required <?php echo $isInactive ? 'disabled' : ''; ?>>
                                        <option value="">Select role</option>
                                        <?php foreach ($roles as $role) { ?>
                                            <option value="<?php echo $role['id']; ?>" data-scope="<?php echo htmlspecialchars($role['s_scope']); ?>" <?php echo (int)$role['id'] === $roleId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role['s_role_name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="scope_level" class="form-select form-select-sm scope-selector" id="scope-<?php echo $membership['id']; ?>" data-target="ms-<?php echo $membership['id']; ?>" <?php echo $isInactive ? 'disabled' : ''; ?>>
                                        <option value="workspace" <?php echo $scopeLevel === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                                        <option value="ms" <?php echo $scopeLevel === 'ms' ? 'selected' : ''; ?>>Microservice</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <select name="s_ms_id" class="form-select form-select-sm <?php echo $scopeLevel === 'ms' ? '' : 'd-none'; ?> ms-selector" id="ms-<?php echo $membership['id']; ?>" data-space-id="<?php echo (int)$membership['space_id']; ?>" <?php echo $isInactive ? 'disabled' : ''; ?>>
                                        <option value="">Microservicelet</option>
                                        <?php foreach ($microservices as $ms) { ?>
                                            <option value="<?php echo $ms['id']; ?>" data-space-id="<?php echo (int)($ms['space_id'] ?? 0); ?>" <?php echo (int)$msId === (int)$ms['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ms['s_name']); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <input type="datetime-local" name="s_effective_from" class="form-control form-control-sm" value="<?php echo !empty($membership['s_effective_from']) ? htmlspecialchars(\Core\Sys\TimeHelper::formatUtc($membership['s_effective_from'], $timezone, 'Y-m-d\\TH:i')) : ''; ?>" <?php echo $isInactive ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-6">
                                    <input type="datetime-local" name="s_effective_to" class="form-control form-control-sm" value="<?php echo !empty($membership['s_effective_to']) ? htmlspecialchars(\Core\Sys\TimeHelper::formatUtc($membership['s_effective_to'], $timezone, 'Y-m-d\\TH:i')) : ''; ?>" <?php echo $isInactive ? 'disabled' : ''; ?>>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="assign_membership_role" value="1" class="btn btn-sm btn-primary w-100" <?php echo $isInactive ? 'disabled' : ''; ?>>
                                        <i class="bi bi-plus-circle me-1"></i>Set Role
                                    </button>
                                    <?php if ($isInactive) { ?>
                                        <div class="form-text text-warning">Activate membership to assign a role.</div>
                                    <?php } ?>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php
    $jsPath = $this->runData['config']['dir']['admin'] . '/ui/membership-view.js.php';
    if (file_exists($jsPath)) {
        include $jsPath;
    }
    ?>
<?php } ?>

<?php
$pages = (int)($pagination['pages'] ?? 1);
$page = (int)($pagination['page'] ?? 1);
if ($pages > 1) {
    $query = $this->runData['request']->get;
    $baseUrl = $this->runData['route']['rad_admin_url'] . '/membership/view';
    $prevPage = max(1, $page - 1);
    $nextPage = min($pages, $page + 1);
    $query['page'] = $prevPage;
    $prevUrl = $baseUrl . '?' . http_build_query($query);
    $query['page'] = $nextPage;
    $nextUrl = $baseUrl . '?' . http_build_query($query);
?>
    <div class="d-flex justify-content-end align-items-center gap-2 mt-3">
        <span class="text-muted small">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
        <div class="btn-group">
            <a class="btn btn-outline-secondary btn-sm <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($prevUrl); ?>">Prev</a>
            <a class="btn btn-outline-secondary btn-sm <?php echo $page >= $pages ? 'disabled' : ''; ?>" href="<?php echo htmlspecialchars($nextUrl); ?>">Next</a>
        </div>
    </div>
<?php } ?>

<div class="modal fade invite-members-modal" id="inviteModal" tabindex="-1" aria-labelledby="inviteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="inviteModalLabel">Invite Membership</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Workspace</label>
                            <select name="invite_space_id" class="form-select" required>
                                <option value="">Select workspace</option>
                                <?php foreach ($spaces as $space) { ?>
                                    <option value="<?php echo $space['id']; ?>"><?php echo htmlspecialchars($space['s_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 position-relative" id="invite-entity-picker" data-search-endpoint="<?php echo $this->runData['route']['rad_admin_url']; ?>/membership/searchEntities">
                            <label class="form-label">User (ID, email, or UID)</label>
                            <input type="hidden" name="invite_entity_id" value="">
                            <input type="text" name="invite_entity" class="form-control" placeholder="Search by name, email, UID" autocomplete="off" required>
                            <div class="list-group position-absolute w-100 shadow-sm d-none" data-invite-entity-results style="max-height:220px; overflow:auto; z-index:1020;"></div>
                            <div class="form-text text-danger d-none" id="invite-existing-warning">Membership already exists for this user.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="invite_create_user" name="invite_create_user">
                                <label class="form-check-label" for="invite_create_user">Create a new user instead</label>
                            </div>
                        </div>
                        <div class="col-md-6 d-none" id="invite-new-name">
                            <label class="form-label">New user name</label>
                            <input type="text" name="invite_new_name" class="form-control" placeholder="Full name">
                        </div>
                        <div class="col-md-6 d-none" id="invite-new-identity">
                            <label class="form-label">New user username / email</label>
                            <input type="text" name="invite_new_identity" class="form-control" placeholder="username or email">
                        </div>
                        <div class="col-md-6 d-none" id="invite-new-email">
                            <label class="form-label">New user email (optional)</label>
                            <input type="email" name="invite_new_email" class="form-control" placeholder="email@example.com">
                        </div>
                        <div class="col-md-6 d-none" id="invite-new-password">
                            <label class="form-label">New user password</label>
                            <input type="text" name="invite_new_password" class="form-control" placeholder="Minimum 8 characters">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="invite_new_autopass" name="invite_new_autopass">
                                <label class="form-check-label" for="invite_new_autopass">Auto-generate password</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role (optional)</label>
                            <select name="invite_role_id" class="form-select role-selector">
                                <option value="">No role yet</option>
                                <?php foreach ($roles as $role) { ?>
                                    <option value="<?php echo $role['id']; ?>" data-scope="<?php echo htmlspecialchars($role['s_scope']); ?>">
                                        <?php echo htmlspecialchars($role['s_role_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scope</label>
                            <select name="invite_scope_level" class="form-select scope-selector" data-target="invite-ms-id">
                                <option value="workspace">Workspace</option>
                                <option value="ms">Microservice</option>
                            </select>
                        </div>
                        <div class="col-md-6 d-none" id="invite-ms-wrapper">
                            <label class="form-label">Microservicelet</label>
                            <select name="invite_ms_id" class="form-select ms-selector" id="invite-ms-id">
                                <option value="">Microservicelet</option>
                                <?php foreach ($microservices as $ms) { ?>
                                    <option value="<?php echo $ms['id']; ?>" data-space-id="<?php echo (int)($ms['space_id'] ?? 0); ?>"><?php echo htmlspecialchars($ms['s_name']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective from</label>
                            <input type="datetime-local" name="invite_effective_from" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Effective to</label>
                            <input type="datetime-local" name="invite_effective_to" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="invite_notes" class="form-control" rows="2" placeholder="Optional notes for admins (not stored yet)." disabled></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="invite_membership" value="1" class="btn btn-primary">Create Membership</button>
                </div>
            </form>
        </div>
    </div>
</div>
